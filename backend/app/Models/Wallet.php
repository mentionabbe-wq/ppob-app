<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Wallet extends Model
{
    protected $fillable = ['user_id', 'balance', 'locked_balance', 'currency', 'version'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'locked_balance' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(WalletMutation::class);
    }

    public function available(): float
    {
        return (float) $this->balance - (float) $this->locked_balance;
    }

    public function hasSufficient(float $amount): bool
    {
        return $this->available() >= $amount;
    }
}
