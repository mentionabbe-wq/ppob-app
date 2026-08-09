<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Repositories\Contracts\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

abstract class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(protected Model $model) {}

    /** Terapkan filter spesifik entitas. Dioverride oleh child repository. */
    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query;
    }

    protected function query(array $filters = [], array $relations = []): Builder
    {
        return $this->applyFilters($this->model->newQuery()->with($relations), $filters);
    }

    public function find(int $id, array $relations = []): ?Model
    {
        return $this->model->newQuery()->with($relations)->find($id);
    }

    public function findOrFail(int $id, array $relations = []): Model
    {
        return $this->model->newQuery()->with($relations)->findOrFail($id);
    }

    public function all(array $filters = [], array $relations = []): Collection
    {
        return $this->query($filters, $relations)->get();
    }

    public function paginate(array $filters = [], int $perPage = 20, array $relations = []): LengthAwarePaginator
    {
        return $this->query($filters, $relations)
            ->latest($this->model->getTable().'.id')
            ->paginate(min($perPage, 100))
            ->withQueryString();
    }

    public function create(array $attributes): Model
    {
        return $this->model->newQuery()->create($attributes);
    }

    public function update(Model|int $model, array $attributes): Model
    {
        $model = $model instanceof Model ? $model : $this->findOrFail($model);
        $model->update($attributes);

        return $model->refresh();
    }

    public function delete(Model|int $model): bool
    {
        $model = $model instanceof Model ? $model : $this->findOrFail($model);

        return (bool) $model->delete();
    }
}
