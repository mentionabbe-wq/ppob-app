<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepositStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Deposit extends Model
{
    use HasFactory;

    protected $fillable = [
        'code', 'user_id', 'amount', 'unique_code', 'total_amount', 'method', 'channel',
        'va_number', 'qris_payload', 'payment_ref', 'proof_path', 'status', 'note',
        'reject_reason', 'approved_by', 'expired_at', 'paid_at', 'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => DepositStatus::class,
            'amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'unique_code' => 'integer',
            'expired_at' => 'datetime',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function mutations()
    {
        return $this->morphMany(WalletMutation::class, 'reference');
    }

    public function scopeStatus(Builder $q, string|DepositStatus|null $status): Builder
    {
        return $q->when($status, fn ($b) => $b->where('status', $status instanceof DepositStatus ? $status->value : $status));
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($b) => $b->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($b) => $b->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    public function isExpired(): bool
    {
        return $this->expired_at !== null && $this->expired_at->isPast() && ! $this->status->isFinal();
    }
}
