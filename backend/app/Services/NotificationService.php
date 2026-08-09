<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\AppNotification;
use App\Models\Deposit;
use App\Models\Transaction;
use App\Models\User;
use App\Jobs\SendPushNotificationJob;

/**
 * Menyimpan notifikasi in-app sekaligus mengantrikan push FCM.
 */
class NotificationService
{
    public function push(
        ?User $user,
        string $type,
        string $title,
        string $body,
        array $data = [],
        ?string $imagePath = null,
    ): AppNotification {
        $notification = AppNotification::create([
            'user_id' => $user?->id,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'image_path' => $imagePath,
            'data' => $data,
        ]);

        if ($user?->fcm_token) {
            SendPushNotificationJob::dispatch($user->id, $title, $body, $data);
        }

        return $notification;
    }

    public function transactionUpdated(Transaction $transaction): void
    {
        $title = match ($transaction->status) {
            TransactionStatus::Success => 'Transaksi Berhasil',
            TransactionStatus::Failed => 'Transaksi Gagal',
            TransactionStatus::Refunded => 'Dana Dikembalikan',
            default => 'Status Transaksi Diperbarui',
        };

        $body = match ($transaction->status) {
            TransactionStatus::Success => sprintf(
                '%s ke %s berhasil.%s',
                $transaction->product_name,
                $transaction->customer_no,
                $transaction->serial_number ? ' SN: '.$transaction->serial_number : '',
            ),
            TransactionStatus::Failed => sprintf(
                '%s ke %s gagal. %s',
                $transaction->product_name,
                $transaction->customer_no,
                $transaction->provider_message ?? '',
            ),
            default => sprintf('%s ke %s: %s.',
                $transaction->product_name,
                $transaction->customer_no,
                $transaction->status->label(),
            ),
        };

        $this->push($transaction->user, 'transaction', $title, trim($body), [
            'transaction_id' => $transaction->id,
            'invoice_no' => $transaction->invoice_no,
            'status' => $transaction->status->value,
        ]);
    }

    public function transactionRefunded(Transaction $transaction): void
    {
        $this->push(
            $transaction->user,
            'transaction',
            'Dana Dikembalikan',
            sprintf(
                'Rp%s telah dikembalikan ke saldo Anda untuk invoice %s.',
                number_format((float) $transaction->total_paid, 0, ',', '.'),
                $transaction->invoice_no,
            ),
            ['transaction_id' => $transaction->id, 'invoice_no' => $transaction->invoice_no],
        );
    }

    public function depositApproved(Deposit $deposit): void
    {
        $this->push(
            $deposit->user,
            'deposit',
            'Deposit Berhasil',
            sprintf(
                'Saldo Anda bertambah Rp%s dari deposit %s.',
                number_format((float) $deposit->amount, 0, ',', '.'),
                $deposit->code,
            ),
            ['deposit_id' => $deposit->id, 'code' => $deposit->code],
        );
    }

    public function depositRejected(Deposit $deposit): void
    {
        $this->push(
            $deposit->user,
            'deposit',
            'Deposit Ditolak',
            sprintf('Deposit %s ditolak. %s', $deposit->code, $deposit->reject_reason ?? ''),
            ['deposit_id' => $deposit->id, 'code' => $deposit->code],
        );
    }

    public function depositProofUploaded(Deposit $deposit): void
    {
        $this->push(
            $deposit->user,
            'deposit',
            'Bukti Transfer Diterima',
            sprintf('Bukti transfer untuk %s sedang diverifikasi admin.', $deposit->code),
            ['deposit_id' => $deposit->id],
        );
    }

    /** Notifikasi broadcast (promo/informasi sistem) ke seluruh user. */
    public function broadcast(string $title, string $body, string $type = 'promo', array $data = []): AppNotification
    {
        return $this->push(null, $type, $title, $body, $data);
    }
}
