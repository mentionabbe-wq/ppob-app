<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Wallet;

interface WalletRepositoryInterface
{
    public function forUser(int $userId): Wallet;

    /** Ambil dompet dengan `SELECT ... FOR UPDATE` — wajib di dalam DB::transaction. */
    public function lockForUser(int $userId): Wallet;

    public function totalBalance(): float;
}
