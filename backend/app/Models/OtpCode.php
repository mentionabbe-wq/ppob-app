<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class OtpCode extends Model
{
    protected $fillable = [
        'identifier', 'purpose', 'code_hash', 'attempts',
        'expires_at', 'used_at', 'ip_address',
    ];

    protected $hidden = ['code_hash'];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    public function scopeUsable(Builder $q): Builder
    {
        return $q->whereNull('used_at')->where('expires_at', '>', now());
    }

    public function isExhausted(): bool
    {
        return $this->attempts >= (int) config('ppob.otp.max_attempt', 5);
    }
}
