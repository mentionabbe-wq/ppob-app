<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\Wallet;
use App\Repositories\Contracts\WalletRepositoryInterface;

class WalletRepository implements WalletRepositoryInterface
{
    public function forUser(int $userId): Wallet
    {
        return Wallet::firstOrCreate(['user_id' => $userId], ['balance' => 0]);
    }

    public function lockForUser(int $userId): Wallet
    {
        $this->forUser($userId);

        return Wallet::where('user_id', $userId)->lockForUpdate()->firstOrFail();
    }

    public function totalBalance(): float
    {
        return (float) Wallet::sum('balance');
    }
}
