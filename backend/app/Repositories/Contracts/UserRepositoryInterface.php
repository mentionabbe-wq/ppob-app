<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\User;

interface UserRepositoryInterface extends BaseRepositoryInterface
{
    public function findByEmail(string $email): ?User;

    public function findByPhone(string $phone): ?User;

    public function findByGoogleId(string $googleId): ?User;

    public function stats(): array;
}
