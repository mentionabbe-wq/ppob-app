<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Banner extends Model
{
    protected $fillable = [
        'title', 'image_path', 'action_type', 'action_value',
        'is_active', 'start_date', 'end_date', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function scopeVisible(Builder $q): Builder
    {
        $today = now()->toDateString();

        return $q->where('is_active', true)
            ->where(fn ($b) => $b->whereNull('start_date')->orWhereDate('start_date', '<=', $today))
            ->where(fn ($b) => $b->whereNull('end_date')->orWhereDate('end_date', '>=', $today))
            ->orderBy('sort_order');
    }
}
