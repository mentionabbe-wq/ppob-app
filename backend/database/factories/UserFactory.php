<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake('id_ID')->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->unique()->numerify('##########'),
            'email_verified_at' => now(),
            'password' => 'password',
            'status' => 'active',
            'referral_code' => strtoupper(Str::random(8)),
        ];
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['status' => 'suspended']);
    }

    public function withBalance(float $balance): static
    {
        return $this->afterCreating(fn (User $user) => $user->wallet()->update(['balance' => $balance]));
    }

    public function withPin(string $pin = '123456'): static
    {
        return $this->state(fn () => ['pin_hash' => password_hash($pin, PASSWORD_BCRYPT)]);
    }
}
