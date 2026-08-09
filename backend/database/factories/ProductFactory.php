<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        $basePrice = fake()->numberBetween(5_000, 100_000);

        return [
            'category_id' => Category::factory(),
            'provider_id' => Provider::factory(),
            'sku' => strtoupper(Str::random(10)),
            'provider_sku' => strtolower(Str::random(10)),
            'name' => 'Produk '.fake()->word(),
            'brand' => fake()->randomElement(['TELKOMSEL', 'INDOSAT', 'XL', 'PLN', 'DANA']),
            'type' => 'Umum',
            'base_price' => $basePrice,
            'sell_price' => $basePrice + 1_000,
            'margin_type' => 'fixed',
            'margin_value' => 1_000,
            'admin_fee' => 0,
            'is_active' => true,
            'is_available' => true,
        ];
    }

    public function unavailable(): static
    {
        return $this->state(fn () => ['is_available' => false]);
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
