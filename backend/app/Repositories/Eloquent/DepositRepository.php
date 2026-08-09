<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Enums\DepositStatus;
use App\Models\Deposit;
use App\Repositories\Contracts\DepositRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class DepositRepository extends BaseRepository implements DepositRepositoryInterface
{
    public function __construct(Deposit $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['user_id'] ?? null, fn ($q, $v) => $q->where('user_id', $v))
            ->when($filters['method'] ?? null, fn ($q, $v) => $q->where('method', $v))
            ->status($filters['status'] ?? null)
            ->between($filters['from'] ?? null, $filters['to'] ?? null)
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('code', 'like', "%{$v}%")
                    ->orWhere('va_number', 'like', "%{$v}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%"));
            }));
    }

    public function findByCode(string $code): ?Deposit
    {
        return Deposit::where('code', $code)->first();
    }

    public function findByPaymentRef(string $paymentRef): ?Deposit
    {
        return Deposit::where('payment_ref', $paymentRef)->first();
    }

    public function lockForUpdate(int $id): ?Deposit
    {
        return Deposit::whereKey($id)->lockForUpdate()->first();
    }

    public function availableUniqueCode(float $amount): int
    {
        $used = Deposit::query()
            ->where('amount', $amount)
            ->whereIn('status', [DepositStatus::Pending, DepositStatus::WaitingPayment])
            ->pluck('unique_code')
            ->all();

        for ($i = 0; $i < 200; $i++) {
            $code = random_int(101, 999);
            if (! in_array($code, $used, true)) {
                return $code;
            }
        }

        // Semua kode terpakai — biarkan tanpa kode unik, admin verifikasi manual.
        return 0;
    }

    public function summary(?string $from = null, ?string $to = null): array
    {
        $row = Deposit::query()
            ->between($from, $to)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw('SUM(status = ?) as pending_count', [DepositStatus::WaitingPayment->value])
            ->selectRaw('COALESCE(SUM(CASE WHEN status = ? THEN amount END), 0) as approved_amount', [DepositStatus::Approved->value])
            ->first();

        return [
            'total_count' => (int) $row->total_count,
            'pending_count' => (int) $row->pending_count,
            'approved_amount' => (float) $row->approved_amount,
        ];
    }

    public function expired(): Collection
    {
        return Deposit::query()
            ->whereIn('status', [DepositStatus::Pending, DepositStatus::WaitingPayment])
            ->whereNotNull('expired_at')
            ->where('expired_at', '<=', now())
            ->limit(500)
            ->get();
    }

    public function nextCode(): string
    {
        $prefix = 'DPS'.now()->format('Ymd');
        $sequence = Cache::increment("deposit_seq:{$prefix}");
        Cache::put("deposit_seq:{$prefix}", $sequence, now()->addDays(2));

        return $prefix.str_pad((string) $sequence, 5, '0', STR_PAD_LEFT);
    }
}
