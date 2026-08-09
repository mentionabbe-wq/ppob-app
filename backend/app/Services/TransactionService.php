<?php

declare(strict_types=1);

namespace App\Services;

use App\DTO\TopupRequestData;
use App\DTO\TopupResultData;
use App\Enums\MutationType;
use App\Enums\TransactionStatus;
use App\Exceptions\ProviderException;
use App\Jobs\ProcessTopupJob;
use App\Models\ActivityLog;
use App\Models\Transaction;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class TransactionService
{
    public function __construct(
        private readonly TransactionRepositoryInterface $transactions,
        private readonly WalletService $wallet,
        private readonly PricingService $pricing,
        private readonly ProviderManager $providers,
        private readonly NotificationService $notifications,
    ) {}

    /**
     * Buat transaksi baru: validasi → potong saldo → simpan → antrikan ke provider.
     *
     * Bersifat idempoten terhadap `ref_id`: permintaan berulang dengan
     * ref_id sama mengembalikan transaksi yang sudah ada.
     */
    public function purchase(TopupRequestData $data): Transaction
    {
        if ($existing = $this->transactions->findByRefId($data->refId)) {
            return $existing;
        }

        $product = $data->product->loadMissing('provider', 'category');

        if (! $product->is_active || ! $product->is_available || ! $product->provider->is_active) {
            throw ValidationException::withMessages([
                'product_id' => 'Produk sedang tidak tersedia. Silakan pilih produk lain.',
            ]);
        }

        $this->assertPin($data);

        $promo = $this->pricing->validatePromo(
            $data->promoCode,
            $data->user,
            (float) $product->sell_price,
            $product->category_id,
        );

        $quote = $this->pricing->quote($product, $promo, $data->meta['bill_amount'] ?? null);

        $transaction = DB::transaction(function () use ($data, $product, $promo, $quote) {
            $transaction = $this->transactions->create([
                'invoice_no' => $this->transactions->nextInvoiceNumber(),
                'ref_id' => $data->refId,
                'user_id' => $data->user->id,
                'product_id' => $product->id,
                'provider_id' => $product->provider_id,
                'promo_id' => $promo?->id,
                'product_name' => $product->name,
                'customer_no' => $data->customerNo,
                'customer_name' => $data->meta['customer_name'] ?? null,
                'base_price' => $quote['base_price'],
                'sell_price' => $quote['sell_price'],
                'admin_fee' => $quote['admin_fee'],
                'discount' => $quote['discount'],
                'total_paid' => $quote['total_paid'],
                'profit' => $quote['profit'],
                'status' => TransactionStatus::Pending,
                'meta' => $data->meta,
                'paid_at' => now(),
            ]);

            // Debit saldo di dalam transaksi DB yang sama — bila gagal,
            // pembuatan transaksi ikut dibatalkan (tanpa saldo hangus).
            $this->wallet->debit(
                $data->user,
                (float) $quote['total_paid'],
                MutationType::Purchase,
                "Pembelian {$product->name} ke {$data->customerNo}",
                $transaction,
            );

            $promo?->increment('used');

            return $transaction;
        });

        ProcessTopupJob::dispatch($transaction->id)->afterCommit();

        ActivityLog::record('transaction.created', $transaction, [
            'invoice_no' => $transaction->invoice_no,
            'total_paid' => $transaction->total_paid,
        ]);

        return $transaction->fresh(['product.category', 'provider']);
    }

    /** Kirim transaksi ke provider dan simpan hasilnya. */
    public function dispatchToProvider(Transaction $transaction): Transaction
    {
        if ($transaction->status->isFinal()) {
            return $transaction;
        }

        $transaction->update(['status' => TransactionStatus::Processing]);

        try {
            $result = $this->providers->forTransaction($transaction)->topup($transaction);
        } catch (ProviderException $e) {
            Log::warning('Topup gagal dikirim ke provider', [
                'invoice_no' => $transaction->invoice_no,
                'error' => $e->getMessage(),
            ]);

            // Biarkan tetap Processing; SyncPendingTransactions akan
            // merekonsiliasi. Refund hanya bila provider menyatakan gagal.
            throw $e;
        }

        return $this->applyResult($transaction, $result);
    }

    /**
     * Terapkan hasil dari provider (respons langsung maupun webhook).
     * Aman dipanggil berulang — status final tidak akan ditimpa.
     */
    public function applyResult(Transaction $transaction, TopupResultData $result): Transaction
    {
        return DB::transaction(function () use ($transaction, $result) {
            $locked = $this->transactions->lockForUpdate($transaction->id);

            if ($locked === null || $locked->status->isFinal()) {
                return $locked ?? $transaction;
            }

            $attributes = [
                'status' => $result->status,
                'serial_number' => $result->serialNumber ?? $locked->serial_number,
                'provider_ref' => $result->providerRef ?? $locked->provider_ref,
                'provider_message' => $result->message,
            ];

            // Harga modal riil dari provider dipakai untuk profit sebenarnya.
            if ($result->basePrice !== null && $result->basePrice > 0) {
                $attributes['base_price'] = $result->basePrice;
                $attributes['profit'] = round((float) $locked->total_paid - $result->basePrice, 2);
            }

            if ($result->customerName !== null) {
                $attributes['customer_name'] = $result->customerName;
            }

            if ($result->status->isFinal()) {
                $attributes['completed_at'] = now();
            }

            $locked->update($attributes);

            if ($result->status->needsRefund()) {
                $this->refund($locked, 'Transaksi gagal di provider: '.($result->message ?? 'tanpa keterangan'));
            }

            $this->notifications->transactionUpdated($locked->fresh());

            return $locked->fresh();
        });
    }

    /**
     * Kembalikan dana ke saldo user. Idempoten via kolom `refunded_at`.
     */
    public function refund(Transaction $transaction, string $reason, ?int $actorId = null): Transaction
    {
        if ($transaction->refunded_at !== null) {
            return $transaction;
        }

        $this->wallet->credit(
            $transaction->user,
            (float) $transaction->total_paid,
            MutationType::Refund,
            "Refund {$transaction->invoice_no}: {$reason}",
            $transaction,
            $actorId,
        );

        $transaction->update([
            'status' => TransactionStatus::Refunded,
            'refunded_at' => now(),
            'provider_message' => $reason,
        ]);

        $transaction->promo?->decrement('used');

        ActivityLog::record('transaction.refunded', $transaction, ['reason' => $reason]);
        $this->notifications->transactionRefunded($transaction);

        return $transaction->fresh();
    }

    /** Kirim ulang transaksi gagal ke provider dengan ref_id baru. */
    public function resend(Transaction $transaction, int $actorId): Transaction
    {
        if (! in_array($transaction->status, [TransactionStatus::Failed, TransactionStatus::Refunded], true)) {
            throw ValidationException::withMessages([
                'status' => 'Hanya transaksi gagal atau refund yang dapat dikirim ulang.',
            ]);
        }

        // Bila dana sudah dikembalikan, saldo harus dipotong lagi
        // sebelum transaksi diulang; bila belum, saldo masih tertahan.
        $needsDebit = $transaction->refunded_at !== null;

        $transaction->update([
            'ref_id' => $transaction->ref_id.'-R'.($transaction->retry_count + 1),
            'status' => TransactionStatus::Pending,
            'retry_count' => $transaction->retry_count + 1,
            'refunded_at' => null,
            'serial_number' => null,
            'provider_message' => null,
        ]);

        if ($needsDebit) {
            $this->wallet->debit(
                $transaction->user,
                (float) $transaction->total_paid,
                MutationType::Purchase,
                "Kirim ulang {$transaction->invoice_no}",
                $transaction,
            );
        }

        ProcessTopupJob::dispatch($transaction->id)->afterCommit();
        ActivityLog::record('transaction.resent', $transaction, ['actor_id' => $actorId]);

        return $transaction->fresh();
    }

    /** Sinkronkan status transaksi yang menggantung ke provider. */
    public function syncStatus(Transaction $transaction): Transaction
    {
        try {
            $result = $this->providers->forTransaction($transaction)->checkStatus($transaction);
        } catch (ProviderException $e) {
            Log::warning('Sinkronisasi status gagal', [
                'invoice_no' => $transaction->invoice_no,
                'error' => $e->getMessage(),
            ]);

            return $transaction;
        }

        return $this->applyResult($transaction, $result);
    }

    private function assertPin(TopupRequestData $data): void
    {
        if (blank($data->user->pin_hash)) {
            return; // user belum mengaktifkan PIN transaksi
        }

        if (blank($data->pin) || ! password_verify($data->pin, $data->user->pin_hash)) {
            throw ValidationException::withMessages(['pin' => 'PIN transaksi salah.']);
        }
    }
}
