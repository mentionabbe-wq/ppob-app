<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MutationType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WalletMutation extends Model
{
    protected $fillable = [
        'wallet_id', 'user_id', 'type', 'amount', 'balance_before', 'balance_after',
        'reference_type', 'reference_id', 'description', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MutationType::class,
            'amount' => 'decimal:2',
            'balance_before' => 'decimal:2',
            'balance_after' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($b) => $b->whereDate('created_at', '>=', $from))
            ->when($to, fn ($b) => $b->whereDate('created_at', '<=', $to));
    }
}
