<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use PHPOpenSourceSaver\JWTAuth\Contracts\JWTSubject;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected $fillable = [
        'name', 'email', 'phone', 'password', 'pin_hash', 'avatar_path',
        'status', 'referral_code', 'referred_by', 'fcm_token', 'google_id',
        'email_verified_at', 'phone_verified_at', 'last_login_at', 'last_login_ip',
    ];

    protected $hidden = ['password', 'pin_hash', 'remember_token', 'fcm_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $user) {
            $user->referral_code ??= static::generateReferralCode();
        });

        // Setiap user baru otomatis memiliki dompet bersaldo nol.
        static::created(function (self $user) {
            $user->wallet()->firstOrCreate([], ['balance' => 0]);
        });
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (static::where('referral_code', $code)->exists());

        return $code;
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    public function deposits(): HasMany
    {
        return $this->hasMany(Deposit::class);
    }

    public function mutations(): HasMany
    {
        return $this->hasMany(WalletMutation::class);
    }

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    public function appNotifications(): HasMany
    {
        return $this->hasMany(AppNotification::class);
    }

    public function referrals(): HasMany
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function balance(): float
    {
        return (float) ($this->wallet?->balance ?? 0);
    }

    // ── JWTSubject ────────────────────────────────────────────
    public function getJWTIdentifier(): mixed
    {
        return $this->getKey();
    }

    public function getJWTCustomClaims(): array
    {
        return [
            'name' => $this->name,
            'roles' => $this->getRoleNames()->toArray(),
        ];
    }
}
