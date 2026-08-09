<?php

declare(strict_types=1);

namespace Tests;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    /** Header Authorization berisi JWT milik user. */
    protected function jwtHeaders(User $user): array
    {
        return ['Authorization' => 'Bearer '.JWTAuth::fromUser($user)];
    }

    protected function actingAsApi(User $user): static
    {
        $this->withHeaders($this->jwtHeaders($user));

        return $this;
    }
}
