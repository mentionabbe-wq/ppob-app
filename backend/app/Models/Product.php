<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id', 'provider_id', 'sku', 'provider_sku', 'name', 'brand', 'type',
        'base_price', 'sell_price', 'margin_type', 'margin_value', 'admin_fee',
        'is_active', 'is_available', 'is_featured', 'description', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'base_price' => 'decimal:2',
            'sell_price' => 'decimal:2',
            'margin_value' => 'decimal:2',
            'admin_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(Provider::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /** Produk yang boleh dibeli user: aktif, tersedia, provider aktif. */
    public function scopeSellable(Builder $q): Builder
    {
        return $q->where('is_active', true)
            ->where('is_available', true)
            ->whereHas('provider', fn ($p) => $p->where('is_active', true));
    }

    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        return $q->when($term, fn ($b) => $b->where(function ($w) use ($term) {
            $w->where('name', 'like', "%{$term}%")
                ->orWhere('sku', 'like', "%{$term}%")
                ->orWhere('brand', 'like', "%{$term}%");
        }));
    }

    public function profit(): float
    {
        return (float) $this->sell_price - (float) $this->base_price;
    }
}
