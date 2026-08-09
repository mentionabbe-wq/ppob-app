<?php

declare(strict_types=1);

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    protected function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn ($q, $v) => $q->where('status', $v))
            ->when($filters['role'] ?? null, fn ($q, $v) => $q->role($v))
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(function ($w) use ($v) {
                $w->where('name', 'like', "%{$v}%")
                    ->orWhere('email', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%")
                    ->orWhere('referral_code', $v);
            }));
    }

    public function findByEmail(string $email): ?User
    {
        return User::where('email', $email)->first();
    }

    public function findByPhone(string $phone): ?User
    {
        return User::where('phone', $phone)->first();
    }

    public function findByGoogleId(string $googleId): ?User
    {
        return User::where('google_id', $googleId)->first();
    }

    public function stats(): array
    {
        $row = User::query()
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(status = "active") as active')
            ->selectRaw('SUM(DATE(created_at) = CURDATE()) as new_today')
            ->selectRaw('SUM(created_at >= ?) as new_this_month', [now()->startOfMonth()])
            ->first();

        return [
            'total' => (int) $row->total,
            'active' => (int) $row->active,
            'new_today' => (int) $row->new_today,
            'new_this_month' => (int) $row->new_this_month,
        ];
    }
}
