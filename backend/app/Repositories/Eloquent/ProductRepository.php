<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\DTO\ProviderProductData;
use App\Models\Category;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ProductRepository extends BaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model, private readonly PricingService $pricing)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['category_id'] ?? null, fn ($q, $v) => $q->where('category_id', $v))
            ->when($filters['category_slug'] ?? null, fn ($q, $v) => $q->whereHas('category', fn ($c) => $c->where('slug', $v)))
            ->when($filters['provider_id'] ?? null, fn ($q, $v) => $q->where('provider_id', $v))
            ->when($filters['brand'] ?? null, fn ($q, $v) => $q->where('brand', $v))
            ->when(isset($filters['is_active']), fn ($q) => $q->where('is_active', (bool) $filters['is_active']))
            ->when($filters['sellable'] ?? false, fn ($q) => $q->sellable())
            ->search($filters['search'] ?? null);
    }

    public function findBySku(string $sku): ?Product
    {
        return Product::where('sku', $sku)->first();
    }

    public function sellable(array $filters = []): Collection
    {
        return $this->query($filters + ['sellable' => true], ['category:id,name,slug,icon,type'])
            ->orderBy('brand')
            ->orderBy('sell_price')
            ->get();
    }

    public function brands(int $categoryId): Collection
    {
        return Product::sellable()
            ->where('category_id', $categoryId)
            ->whereNotNull('brand')
            ->distinct()
            ->orderBy('brand')
            ->pluck('brand');
    }

    public function syncFromProvider(int $providerId, ProviderProductData ...$items): array
    {
        $created = 0;
        $updated = 0;
        $categoryCache = [];

        foreach ($items as $item) {
            $categoryId = $categoryCache[$item->categorySlug] ??= Category::firstOrCreate(
                ['slug' => $item->categorySlug],
                ['name' => Str::headline($item->categorySlug), 'type' => 'prepaid'],
            )->id;

            $product = Product::withTrashed()
                ->where('provider_id', $providerId)
                ->where('provider_sku', $item->providerSku)
                ->first();

            if ($product === null) {
                Product::create([
                    'category_id' => $categoryId,
                    'provider_id' => $providerId,
                    'sku' => $this->makeInternalSku($item->providerSku),
                    'provider_sku' => $item->providerSku,
                    'name' => $item->name,
                    'brand' => $item->brand,
                    'type' => $item->type,
                    'base_price' => $item->basePrice,
                    'sell_price' => $this->pricing->defaultSellPrice($item->basePrice),
                    'margin_type' => config('ppob.pricing.default_margin_type'),
                    'margin_value' => config('ppob.pricing.default_margin_value'),
                    'is_available' => $item->isAvailable,
                    'is_active' => true,
                    'description' => $item->description,
                ]);
                $created++;

                continue;
            }

            // Harga modal & ketersediaan selalu mengikuti provider;
            // harga jual dihitung ulang dari margin yang ditetapkan admin.
            $product->fill([
                'name' => $item->name,
                'brand' => $item->brand,
                'type' => $item->type,
                'base_price' => $item->basePrice,
                'is_available' => $item->isAvailable,
                'sell_price' => $this->pricing->applyMargin(
                    $item->basePrice,
                    $product->margin_type,
                    (float) $product->margin_value,
                ),
            ]);

            if ($product->isDirty()) {
                $product->save();
                $updated++;
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    private function makeInternalSku(string $providerSku): string
    {
        $base = Str::upper(Str::slug($providerSku, ''));
        $sku = $base;
        $i = 1;

        while (Product::withTrashed()->where('sku', $sku)->exists()) {
            $sku = $base.'-'.$i++;
        }

        return $sku;
    }
}
