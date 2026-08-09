<?php

declare(strict_types=1);

namespace App\Enums;

enum TransactionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Canceled = 'canceled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu',
            self::Processing => 'Diproses',
            self::Success => 'Berhasil',
            self::Failed => 'Gagal',
            self::Refunded => 'Dana Dikembalikan',
            self::Canceled => 'Dibatalkan',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Processing => 'amber',
            self::Success => 'emerald',
            self::Failed, self::Canceled => 'rose',
            self::Refunded => 'sky',
        };
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Success, self::Failed, self::Refunded, self::Canceled], true);
    }

    /** Status yang saldonya wajib dikembalikan ke user. */
    public function needsRefund(): bool
    {
        return in_array($this, [self::Failed, self::Canceled], true);
    }
}
