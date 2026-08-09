<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Provider> */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    public function definition(): array
    {
        return [
            'name' => 'Digiflazz',
            'code' => 'digiflazz',
            'base_url' => 'https://api.digiflazz.com/v1',
            'is_active' => true,
            'priority' => 1,
            'balance' => 1_000_000,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
