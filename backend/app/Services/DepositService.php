<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\DepositStatus;
use App\Enums\MutationType;
use App\Models\ActivityLog;
use App\Models\Deposit;
use App\Models\User;
use App\Repositories\Contracts\DepositRepositoryInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DepositService
{
    public function __construct(
        private readonly DepositRepositoryInterface $deposits,
        private readonly WalletService $wallet,
        private readonly NotificationService $notifications,
        private readonly PaymentGatewayService $gateway,
    ) {}

    /**
     * Ajukan deposit. Untuk metode gateway (VA/QRIS/e-wallet) sekaligus
     * membuat tagihan di payment gateway; untuk transfer bank manual
     * ditambahkan kode unik agar mudah direkonsiliasi.
     */
    public function request(User $user, float $amount, string $method, ?string $channel = null): Deposit
    {
        $this->assertAmount($amount);

        $uniqueCode = 0;
        if ($method === 'bank_transfer' && config('ppob.deposit.use_unique_code')) {
            $uniqueCode = $this->deposits->availableUniqueCode($amount);
        }

        $deposit = $this->deposits->create([
            'code' => $this->deposits->nextCode(),
            'user_id' => $user->id,
            'amount' => $amount,
            'unique_code' => $uniqueCode,
            'total_amount' => $amount + $uniqueCode,
            'method' => $method,
            'channel' => $channel,
            'status' => DepositStatus::WaitingPayment,
            'expired_at' => now()->addHours((int) config('ppob.deposit.expire_hours', 24)),
        ]);

        if (in_array($method, ['virtual_account', 'qris', 'ewallet'], true)) {
            $charge = $this->gateway->createCharge($deposit);

            $deposit->update([
                'payment_ref' => $charge['payment_ref'] ?? null,
                'va_number' => $charge['va_number'] ?? null,
                'qris_payload' => $charge['qris_payload'] ?? null,
            ]);
        }

        ActivityLog::record('deposit.requested', $deposit, ['amount' => $amount, 'method' => $method]);

        return $deposit->fresh();
    }

    /** Unggah bukti transfer (opsional, untuk metode manual). */
    public function attachProof(Deposit $deposit, UploadedFile $file): Deposit
    {
        if ($deposit->status->isFinal()) {
            throw ValidationException::withMessages(['deposit' => 'Deposit ini sudah selesai diproses.']);
        }

        $path = $file->store('deposits/'.now()->format('Y/m'), 'public');

        $deposit->update([
            'proof_path' => $path,
            'status' => DepositStatus::Paid,
            'paid_at' => now(),
        ]);

        $this->notifications->depositProofUploaded($deposit);

        return $deposit->fresh();
    }

    /**
     * Setujui deposit dan tambahkan saldo. Idempoten: deposit yang
     * sudah approved tidak akan menambah saldo dua kali.
     */
    public function approve(Deposit $deposit, ?int $actorId = null, ?string $note = null): Deposit
    {
        return DB::transaction(function () use ($deposit, $actorId, $note) {
            $locked = $this->deposits->lockForUpdate($deposit->id);

            if ($locked === null || $locked->status === DepositStatus::Approved) {
                return $locked ?? $deposit;
            }

            if ($locked->status->isFinal()) {
                throw ValidationException::withMessages([
                    'status' => 'Deposit sudah berstatus '.$locked->status->label().'.',
                ]);
            }

            $locked->update([
                'status' => DepositStatus::Approved,
                'approved_by' => $actorId,
                'approved_at' => now(),
                'paid_at' => $locked->paid_at ?? now(),
                'note' => $note,
            ]);

            $this->wallet->credit(
                $locked->user,
                (float) $locked->amount,
                MutationType::Deposit,
                "Deposit {$locked->code} disetujui",
                $locked,
                $actorId,
            );

            ActivityLog::record('deposit.approved', $locked, ['amount' => $locked->amount]);
            $this->notifications->depositApproved($locked->fresh());

            return $locked->fresh();
        });
    }

    public function reject(Deposit $deposit, string $reason, ?int $actorId = null): Deposit
    {
        if ($deposit->status->isFinal()) {
            throw ValidationException::withMessages([
                'status' => 'Deposit sudah berstatus '.$deposit->status->label().'.',
            ]);
        }

        $deposit->update([
            'status' => DepositStatus::Rejected,
            'reject_reason' => $reason,
            'approved_by' => $actorId,
            'approved_at' => now(),
        ]);

        ActivityLog::record('deposit.rejected', $deposit, ['reason' => $reason]);
        $this->notifications->depositRejected($deposit->fresh());

        return $deposit->fresh();
    }

    /** Dipanggil webhook payment gateway saat pembayaran diterima. */
    public function markPaid(Deposit $deposit, ?string $paymentRef = null): Deposit
    {
        if ($deposit->status->isFinal()) {
            return $deposit;
        }

        $deposit->update([
            'status' => DepositStatus::Paid,
            'paid_at' => now(),
            'payment_ref' => $paymentRef ?? $deposit->payment_ref,
        ]);

        // Pembayaran lewat gateway sudah terverifikasi mesin,
        // jadi dapat langsung disetujui bila diaktifkan.
        if (config('ppob.deposit.auto_approve') || $deposit->method !== 'bank_transfer') {
            return $this->approve($deposit->fresh());
        }

        return $deposit->fresh();
    }

    public function expire(Deposit $deposit): Deposit
    {
        if ($deposit->status->isFinal()) {
            return $deposit;
        }

        $deposit->update(['status' => DepositStatus::Expired]);

        return $deposit->fresh();
    }

    private function assertAmount(float $amount): void
    {
        $min = (float) config('ppob.deposit.min');
        $max = (float) config('ppob.deposit.max');

        if ($amount < $min || $amount > $max) {
            throw ValidationException::withMessages([
                'amount' => sprintf(
                    'Nominal deposit harus antara Rp%s dan Rp%s.',
                    number_format($min, 0, ',', '.'),
                    number_format($max, 0, ',', '.'),
                ),
            ]);
        }
    }
}
