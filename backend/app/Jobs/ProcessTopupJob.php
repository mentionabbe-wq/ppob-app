<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Transaction;
use App\Services\TransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;

/**
 * Mengirim transaksi ke provider di luar siklus request agar
 * respons API tetap cepat. Percobaan ulang memakai backoff bertingkat.
 */
class ProcessTopupJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [10, 30, 120];

    public int $timeout = 60;

    public function __construct(public readonly int $transactionId)
    {
        $this->onQueue('topup');
    }

    /** Cegah dua worker memproses transaksi yang sama. */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->transactionId))->releaseAfter(30)];
    }

    public function handle(TransactionService $transactions): void
    {
        $transaction = Transaction::with(['product.provider', 'user'])->find($this->transactionId);

        if ($transaction === null || $transaction->status->isFinal()) {
            return;
        }

        $transactions->dispatchToProvider($transaction);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('ProcessTopupJob gagal permanen', [
            'transaction_id' => $this->transactionId,
            'error' => $e->getMessage(),
        ]);

        // Tidak auto-refund di sini: status akhir ditentukan oleh
        // rekonsiliasi (SyncPendingTransactionsJob) agar tidak terjadi
        // refund atas transaksi yang sebenarnya sukses di provider.
    }
}
