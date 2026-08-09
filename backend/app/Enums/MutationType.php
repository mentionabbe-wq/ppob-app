<?php

declare(strict_types=1);

namespace App\Enums;

enum MutationType: string
{
    case Deposit = 'deposit';
    case Purchase = 'purchase';
    case Refund = 'refund';
    case Adjustment = 'adjustment';
    case Bonus = 'bonus';
    case Withdrawal = 'withdrawal';

    public function label(): string
    {
        return match ($this) {
            self::Deposit => 'Isi Saldo',
            self::Purchase => 'Pembelian',
            self::Refund => 'Pengembalian Dana',
            self::Adjustment => 'Penyesuaian',
            self::Bonus => 'Bonus',
            self::Withdrawal => 'Penarikan',
        };
    }

    /** True bila mutasi menambah saldo. */
    public function isCredit(): bool
    {
        return in_array($this, [self::Deposit, self::Refund, self::Bonus], true);
    }
}
