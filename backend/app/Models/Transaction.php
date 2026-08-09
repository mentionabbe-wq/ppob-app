<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'invoice_no', 'ref_id', 'user_id', 'product_id', 'provider_id', 'promo_id',
        'product_name', 'customer_no', 'customer_name',
        'base_price', 'sell_price', 'admin_fee', 'discount', 'total_paid', 'profit',
        'status', 'serial_number', 'provider_ref', 'provider_message', 'meta',
        'retry_count', 'paid_at', 'completed_at', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => TransactionStatus::class,
            'base_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'discount' => 'decimal:2',
            'total_paid' => 'decimal:2',
            'profit' => 'decimal:2',
            'meta' => 'array',
            'paid_at' => 'datetime',
            'completed_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    public function mutations()
    {
        return $this->morphMany(WalletMutation::class, 'reference');
    }

    public function scopeStatus(Builder $q, string|TransactionStatus|null $status): Builder
    {
        return $q->when($status, fn ($b) => $b->where('status', $status instanceof TransactionStatus ? $status->value : $status));
    }

    public function scopeBetween(Builder $q, ?string $from, ?string $to): Builder
    {
        return $q->when($from, fn ($b) => $b->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($to, fn ($b) => $b->where('created_at', '<=', Carbon::parse($to)->endOfDay()));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($b) => $b->where(function ($w) use ($term) {
            $w->where('invoice_no', 'like', "%{$term}%")
                ->orWhere('customer_no', 'like', "%{$term}%")
                ->orWhere('product_name', 'like', "%{$term}%")
                ->orWhere('serial_number', 'like', "%{$term}%");
        }));
    }

    public function isRefundable(): bool
    {
        return $this->refunded_at === null
            && in_array($this->status, [TransactionStatus::Failed, TransactionStatus::Canceled, TransactionStatus::Success], true);
    }
}
