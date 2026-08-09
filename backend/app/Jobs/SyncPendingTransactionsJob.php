<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\TransactionService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Rekonsiliasi berkala: menanyakan status transaksi yang masih
 * menggantung ke provider, dan me-refund yang kedaluwarsa.
 */
class SyncPendingTransactionsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct()
    {
        $this->onQueue('maintenance');
    }

    public function handle(
        TransactionRepositoryInterface $repository,
        TransactionService $transactions,
    ): void {
        $stale = $repository->stalePending((int) config('ppob.transaction.stale_after_minutes', 15));
        $autoRefundAfter = now()->subHours((int) config('ppob.transaction.auto_refund_after_hours', 24));

        foreach ($stale as $transaction) {
            try {
                $synced = $transactions->syncStatus($transaction);

                // Masih menggantung terlalu lama → kembalikan dana user.
                if (! $synced->status->isFinal() && $synced->created_at->lt($autoRefundAfter)) {
                    $transactions->refund($synced, 'Transaksi tidak mendapat kepastian dari provider.');
                }
            } catch (\Throwable $e) {
                Log::warning('Rekonsiliasi transaksi gagal', [
                    'invoice_no' => $transaction->invoice_no,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
