<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\TransactionStatus;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TransactionRepository extends BaseRepository implements TransactionRepositoryInterface
{
    public function __construct(Transaction $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['product_id'] ?? null, fn ($q, $v) => $q->where('product_id', $v))
            ->when($filters['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->whereHas('product', fn ($p) => $p->where('category_id', $v)))
            ->status($filters['status'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->search($filters['search'] ?? null);
    }

    public function findByRefId(string $refId): ?Transaction
    {
        return Transaction::where('ref_id', $refId)->first();
    }

    public function findByInvoice(string $invoiceNo): ?Transaction
    {
        return Transaction::where('invoice_no', $invoiceNo)->first();
    }

    public function findByProviderRef(string $providerRef): ?Transaction
    {
        return Transaction::where('provider_ref', $providerRef)->first();
    }

    public function summary(?string $from = null, ?string $to = null, ?int $userId = null): array
    {
        $row = Transaction::query()
            ->between($from, $to)
            ->when($userId, fn ($q) => $q->where('user_id', $userId))
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(status = ?) as success_count', [TransactionStatus::Success->value])
            ->selectRaw('SUM(status = ?) as failed_count', [TransactionStatus::Failed->value])
            ->selectRaw('SUM(status IN (?, ?)) as pending_count', [
                TransactionStatus::Pending->value, TransactionStatus::Processing->value,
            ])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_paid END), 0) as omzet', [TransactionStatus::Success->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN profit END), 0) as profit', [TransactionStatus::Success->value])
            ->first();

        return [
            'total_count' => (int) $row->total_count,
            'success_count' => (int) $row->success_count,
            'failed_count' => (int) $row->failed_count,
            'pending_count' => (int) $row->pending_count,
            'omzet' => (float) $row->omzet,
            'profit' => (float) $row->profit,
        ];
    }

    public function dailySeries(int $days = 30): array
    {
        return Transaction::query()
            ->where('created_at', '>=', now()->subDays($days - 1)->startOfDay())
            ->selectRaw('DATE(created_at) as date')
            ->selectRaw('COUNT(*) as trx_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_paid END), 0) as omzet', [TransactionStatus::Success->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN profit END), 0) as profit', [TransactionStatus::Success->value])
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();
    }

    public function monthlySeries(int $months = 12): array
    {
        return Transaction::query()
            ->where('created_at', '>=', now()->subMonths($months - 1)->startOfMonth())
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month")
            ->selectRaw('COUNT(*) as trx_count')
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN total_paid END), 0) as omzet', [TransactionStatus::Success->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN profit END), 0) as profit', [TransactionStatus::Success->value])
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->toArray();
    }

    public function bestSellingProducts(int $limit = 10, ?string $from = null, ?string $to = null): array
    {
        return Transaction::query()
            ->where('status', TransactionStatus::Success)
            ->between($from, $to)
            ->join('products', 'products.id', '=', 'transactions.product_id')
            ->selectRaw('products.id, products.name, products.sku')
            ->selectRaw('COUNT(transactions.id) as trx_count')
            ->selectRaw('SUM(transactions.total_paid) as omzet')
            ->selectRaw('SUM(transactions.profit) as profit')
            ->groupBy('products.id', 'products.name', 'products.sku')
            ->orderByDesc('trx_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function mostActiveUsers(int $limit = 10, ?string $from = null, ?string $to = null): array
    {
        return Transaction::query()
            ->where('status', TransactionStatus::Success)
            ->between($from, $to)
            ->join('users', 'users.id', '=', 'transactions.user_id')
            ->selectRaw('users.id, users.name, users.email')
            ->selectRaw('COUNT(transactions.id) as trx_count')
            ->selectRaw('SUM(transactions.total_paid) as omzet')
            ->groupBy('users.id', 'users.name', 'users.email')
            ->orderByDesc('trx_count')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    public function stalePending(int $minutes = 15): Collection
    {
        return Transaction::query()
            ->whereIn('status', [TransactionStatus::Pending, TransactionStatus::Processing])
            ->where('created_at', '<=', now()->subMinutes($minutes))
            ->orderBy('created_at')
            ->limit(200)
            ->get();
    }

    /** Kunci baris transaksi untuk update aman dari race condition webhook. */
    public function lockForUpdate(int $id): ?Transaction
    {
        return Transaction::whereKey($id)->lockForUpdate()->first();
    }

    /**
     * Nomor invoice: prefix tanggal + counter atomik Redis, sehingga
     * aman dari race condition antar worker (tidak memakai COUNT(*)).
     */
    public function nextInvoiceNumber(): string
    {
        $prefix = 'INV'.now()->format('Ymd');
        $sequence = Cache::increment("invoice_seq:{$prefix}");

        if ($sequence === 1) {
            // Jaga-jaga bila cache di-flush: lanjutkan dari data hari ini.
            $existing = DB::table('transactions')->where('invoice_no', 'like', $prefix.'%')->count();
            if ($existing > 0) {
                $sequence = $existing + 1;
                Cache::put("invoice_seq:{$prefix}", $sequence);
            }
            Cache::put("invoice_seq:{$prefix}", $sequence, now()->addDays(2));
        }

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }
}
