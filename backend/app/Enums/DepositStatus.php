<?php

declare(strict_types=1);

namespace App\Enums;

enum DepositStatus: string
{
    case Pending = 'pending';
    case WaitingPayment = 'waiting_payment';
    case Paid = 'paid';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::WaitingPayment => 'Menunggu Pembayaran',
            self::Paid => 'Sudah Dibayar',
            self::Approved => 'Disetujui',
            self::Rejected => 'Ditolak',
            self::Expired => 'Kedaluwarsa',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::WaitingPayment => 'amber',
            self::Paid => 'sky',
            self::Approved => 'emerald',
            self::Rejected, self::Expired => 'rose',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Approved, self::Rejected, self::Expired], true);
    }
}
