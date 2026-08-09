<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\MutationType;
use App\Exceptions\InsufficientBalanceException;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletMutation;
use App\Repositories\Contracts\WalletRepositoryInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Seluruh perubahan saldo HARUS melewati service ini agar setiap
 * pergerakan tercatat di ledger `wallet_mutations`.
 */
class WalletService
{
    public function __construct(private readonly WalletRepositoryInterface $wallets) {}

    public function balance(User $user): float
    {
        return (float) $this->wallets->forUser($user->id)->balance;
    }

    /**
     * Kurangi saldo. Dipanggil di dalam DB::transaction milik caller
     * agar debit + pembuatan transaksi bersifat atomik.
     *
     * @throws InsufficientBalanceException
     */
    public function debit(
        User $user,
        float $amount,
        MutationType $type,
        string $description,
        ?Model $reference = null,
    ): WalletMutation {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $type, $description, $reference) {
            $wallet = $this->wallets->lockForUser($user->id);

            if (! $wallet->hasSufficient($amount)) {
                throw new InsufficientBalanceException(sprintf(
                    'Saldo tidak mencukupi. Saldo tersedia Rp%s, dibutuhkan Rp%s.',
                    number_format($wallet->available(), 0, ',', '.'),
                    number_format($amount, 0, ',', '.'),
                ));
            }

            return $this->write($wallet, -$amount, $type, $description, $reference);
        });
    }

    /** Tambah saldo (deposit, refund, bonus, penyesuaian admin). */
    public function credit(
        User $user,
        float $amount,
        MutationType $type,
        string $description,
        ?Model $reference = null,
        ?int $actorId = null,
    ): WalletMutation {
        $this->assertPositive($amount);

        return DB::transaction(function () use ($user, $amount, $type, $description, $reference, $actorId) {
            $wallet = $this->wallets->lockForUser($user->id);

            return $this->write($wallet, $amount, $type, $description, $reference, $actorId);
        });
    }

    /** Penyesuaian manual oleh admin: nilai boleh negatif. */
    public function adjust(User $user, float $amount, string $reason, int $actorId): WalletMutation
    {
        return DB::transaction(function () use ($user, $amount, $reason, $actorId) {
            $wallet = $this->wallets->lockForUser($user->id);

            if ($amount < 0 && ! $wallet->hasSufficient(abs($amount))) {
                throw new InsufficientBalanceException('Saldo user tidak mencukupi untuk penyesuaian ini.');
            }

            return $this->write($wallet, $amount, MutationType::Adjustment, $reason, null, $actorId);
        });
    }

    private function write(
        Wallet $wallet,
        float $signedAmount,
        MutationType $type,
        string $description,
        ?Model $reference = null,
        ?int $actorId = null,
    ): WalletMutation {
        $before = (float) $wallet->balance;
        $after = round($before + $signedAmount, 2);

        $wallet->forceFill([
            'balance' => $after,
            'version' => $wallet->version + 1,
        ])->save();

        return WalletMutation::create([
            'wallet_id' => $wallet->id,
            'user_id' => $wallet->user_id,
            'type' => $type,
            'amount' => round($signedAmount, 2),
            'balance_before' => $before,
            'balance_after' => $after,
            'reference_type' => $reference ? $reference::class : null,
            'reference_id' => $reference?->getKey(),
            'description' => $description,
            'created_by' => $actorId,
        ]);
    }

    private function assertPositive(float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Nominal mutasi saldo harus lebih besar dari nol.');
        }
    }
}
