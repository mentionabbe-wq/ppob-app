<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->word();

        return [
            'name' => Str::headline($name),
            'slug' => Str::slug($name),
            'type' => 'prepaid',
            'input_label' => 'Nomor Tujuan',
            'input_type' => 'phone',
            'is_active' => true,
        ];
    }

    public function postpaid(): static
    {
        return $this->state(fn () => ['type' => 'postpaid']);
    }
}
