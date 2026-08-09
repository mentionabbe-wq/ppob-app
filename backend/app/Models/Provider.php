<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Crypt;

class Provider extends Model
{
    protected $fillable = [
        'name', 'code', 'base_url', 'credentials_encrypted', 'balance',
        'is_active', 'priority', 'balance_synced_at', 'products_synced_at',
    ];

    protected $hidden = ['credentials_encrypted'];

    protected function casts(): array
    {
        return [
            'balance' => 'decimal:2',
            'is_active' => 'boolean',
            'balance_synced_at' => 'datetime',
            'products_synced_at' => 'datetime',
        ];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function apiLogs(): HasMany
    {
        return $this->hasMany(ApiLog::class);
    }

    /** Kredensial provider disimpan terenkripsi, tidak pernah plain di DB. */
    public function credentials(): array
    {
        if (blank($this->credentials_encrypted)) {
            return [];
        }

        try {
            return json_decode(Crypt::decryptString($this->credentials_encrypted), true) ?: [];
        } catch (\Throwable) {
            return [];
        }
    }

    public function setCredentials(array $credentials): void
    {
        $this->credentials_encrypted = Crypt::encryptString(json_encode($credentials));
    }

    public function credential(string $key, mixed $default = null): mixed
    {
        return data_get($this->credentials(), $key, $default);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true)->orderBy('priority');
    }
}
