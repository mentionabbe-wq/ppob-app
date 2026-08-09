<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promo extends Model
{
    protected $fillable = [
        'code', 'title', 'description', 'image_path', 'discount_type', 'discount_value',
        'max_discount', 'min_transaction', 'category_id', 'quota', 'used',
        'per_user_limit', 'start_date', 'end_date', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'discount_value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_transaction' => 'decimal:2',
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(Voucher::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('is_active', true)
            ->whereDate('start_date', '<=', $today)
            ->whereDate('end_date', '>=', $today);
    }

    public function hasQuota(): bool
    {
        return $this->quota === null || $this->used < $this->quota;
    }

    /** Hitung diskon untuk sebuah nominal, dibatasi max_discount. */
    public function discountFor(float $amount): float
    {
        if ($amount < (float) $this->min_transaction) {
            return 0;
        }

        $discount = $this->discount_type === 'percent'
            ? $amount * ((float) $this->discount_value / 100)
            : (float) $this->discount_value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        return round(min($discount, $amount), 2);
    }
}
