<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Notifikasi in-app (tabel `notifications`). Dinamai AppNotification
 * agar tidak bentrok dengan Illuminate\Notifications\Notification.
 */
class AppNotification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'image_path', 'data', 'read_at'];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'read_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread(Builder $q): Builder
    {
        return $q->whereNull('read_at');
    }

    /** Notifikasi milik user + broadcast (user_id null). */
    public function scopeVisibleTo(Builder $q, int $userId): Builder
    {
        return $q->where(fn ($b) => $b->where('user_id', $userId)->orWhereNull('user_id'));
    }
}
