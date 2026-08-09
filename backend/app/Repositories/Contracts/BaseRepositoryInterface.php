<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface BaseRepositoryInterface
{
    public function find(int $id, array $relations = []): ?Model;

    public function findOrFail(int $id, array $relations = []): Model;

    public function all(array $filters = [], array $relations = []): Collection;

    public function paginate(array $filters = [], int $perPage = 20, array $relations = []): LengthAwarePaginator;

    public function create(array $attributes): Model;

    public function update(Model|int $model, array $attributes): Model;

    public function delete(Model|int $model): bool;
}
