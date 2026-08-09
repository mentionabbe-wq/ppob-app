<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;

class AuthService
{
    public function __construct(
        private readonly UserRepositoryInterface $users,
        private readonly OtpService $otp,
    ) {}

    public function register(array $data): User
    {
        $user = $this->users->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'referred_by' => $this->resolveReferrer($data['referral_code'] ?? null),
            'status' => 'active',
        ]);

        $user->assignRole('user');
        $this->otp->send($user->email, 'register');

        ActivityLog::record('auth.registered', $user);

        return $user;
    }

    /**
     * @return array{user: User, token: string, refresh_token: string, expires_in: int}
     */
    public function login(string $email, string $password, ?string $fcmToken = null): array
    {
        $throttleKey = 'login:'.strtolower($email).'|'.request()->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            throw ValidationException::withMessages([
                'email' => 'Terlalu banyak percobaan login. Coba lagi dalam '.RateLimiter::availableIn($throttleKey).' detik.',
            ]);
        }

        $token = auth('api')->attempt(['email' => $email, 'password' => $password]);

        if ($token === false) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages(['email' => 'Email atau kata sandi salah.']);
        }

        RateLimiter::clear($throttleKey);

        /** @var User $user */
        $user = auth('api')->user();

        if (! $user->isActive()) {
            auth('api')->logout();

            throw ValidationException::withMessages([
                'email' => 'Akun Anda '.($user->status === 'suspended' ? 'diblokir' : 'nonaktif').'. Hubungi dukungan.',
            ]);
        }

        return $this->completeLogin($user, $token, $fcmToken);
    }

    /** Login dengan Google ID token (opsional). */
    public function loginWithGoogle(string $idToken, ?string $fcmToken = null): array
    {
        $payload = Http::get('https://oauth2.googleapis.com/tokeninfo', ['id_token' => $idToken])->json();

        if (! is_array($payload) || ($payload['aud'] ?? null) !== config('services.google.client_id')) {
            throw ValidationException::withMessages(['google' => 'Token Google tidak valid.']);
        }

        $user = $this->users->findByGoogleId($payload['sub'])
            ?? $this->users->findByEmail($payload['email']);

        if ($user === null) {
            $user = $this->users->create([
                'name' => $payload['name'] ?? $payload['email'],
                'email' => $payload['email'],
                'google_id' => $payload['sub'],
                'password' => Hash::make(str()->random(32)),
                'email_verified_at' => now(),
                'status' => 'active',
            ]);
            $user->assignRole('user');
        } elseif (blank($user->google_id)) {
            $user->update(['google_id' => $payload['sub']]);
        }

        return $this->completeLogin($user, JWTAuth::fromUser($user), $fcmToken);
    }

    public function verifyEmail(string $email, string $code): User
    {
        $this->otp->verify($email, 'register', $code);

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Akun tidak ditemukan.']);
        }

        $user->update(['email_verified_at' => now()]);

        return $user;
    }

    public function sendPasswordResetOtp(string $email): void
    {
        // Selalu balas sukses agar tidak membocorkan email terdaftar.
        if ($this->users->findByEmail($email) !== null) {
            $this->otp->send($email, 'reset_password');
        }
    }

    public function resetPassword(string $email, string $code, string $password): User
    {
        $this->otp->verify($email, 'reset_password', $code);

        $user = $this->users->findByEmail($email);

        if ($user === null) {
            throw ValidationException::withMessages(['email' => 'Akun tidak ditemukan.']);
        }

        $user->update(['password' => $password]);

        // Token JWT lama tetap valid sampai TTL habis (stateless).
        // Untuk pencabutan seketika, aktifkan blacklist JWT dan panggil
        // JWTAuth::invalidate() pada token yang tersimpan per perangkat.
        ActivityLog::record('auth.password_reset', $user);

        return $user;
    }

    public function changePassword(User $user, string $current, string $new): void
    {
        if (! Hash::check($current, $user->password)) {
            throw ValidationException::withMessages(['current_password' => 'Kata sandi saat ini salah.']);
        }

        $user->update(['password' => $new]);

        ActivityLog::record('auth.password_changed', $user);
    }

    public function logout(): void
    {
        $user = auth('api')->user();
        $user?->update(['fcm_token' => null]);

        auth('api')->logout();
    }

    public function refresh(): array
    {
        $token = auth('api')->refresh();

        return [
            'token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }

    private function completeLogin(User $user, string $token, ?string $fcmToken): array
    {
        $user->update([
            'last_login_at' => now(),
            'last_login_ip' => request()->ip(),
            'fcm_token' => $fcmToken ?? $user->fcm_token,
        ]);

        ActivityLog::record('auth.login', $user);

        return [
            'user' => $user->fresh(['wallet']),
            'token' => $token,
            'refresh_token' => $token,
            'expires_in' => auth('api')->factory()->getTTL() * 60,
        ];
    }

    private function resolveReferrer(?string $code): ?int
    {
        if (blank($code)) {
            return null;
        }

        return User::where('referral_code', strtoupper($code))->value('id');
    }
}
