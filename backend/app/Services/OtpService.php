<?php

declare(strict_types=1);

namespace App\Services;

use App\Mail\OtpMail;
use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * Pembuatan & verifikasi OTP. Kode disimpan dalam bentuk hash,
 * dibatasi masa berlaku, jumlah percobaan, dan cooldown pengiriman.
 */
class OtpService
{
    public function send(string $identifier, string $purpose): OtpCode
    {
        $throttleKey = "otp:{$purpose}:{$identifier}";
        $cooldown = (int) config('ppob.otp.resend_cooldown_seconds', 60);

        if (RateLimiter::tooManyAttempts($throttleKey, 1)) {
            throw ValidationException::withMessages([
                'otp' => 'Mohon tunggu '.RateLimiter::availableIn($throttleKey).' detik sebelum meminta kode baru.',
            ]);
        }

        RateLimiter::hit($throttleKey, $cooldown);

        // Kode lama untuk tujuan yang sama dianggap hangus.
        OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->whereNull('used_at')
            ->update(['used_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), (int) config('ppob.otp.length', 6), '0', STR_PAD_LEFT);

        $otp = OtpCode::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes((int) config('ppob.otp.ttl_minutes', 10)),
            'ip_address' => request()->ip(),
        ]);

        Mail::to($identifier)->queue(new OtpMail($code, $purpose));

        return $otp;
    }

    /**
     * Verifikasi kode OTP.
     *
     * @throws ValidationException bila kode salah, kedaluwarsa, atau percobaan habis
     */
    public function verify(string $identifier, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('identifier', $identifier)
            ->where('purpose', $purpose)
            ->usable()
            ->latest('id')
            ->first();

        if ($otp === null) {
            throw ValidationException::withMessages([
                'otp' => 'Kode OTP tidak ditemukan atau sudah kedaluwarsa.',
            ]);
        }

        if ($otp->isExhausted()) {
            throw ValidationException::withMessages([
                'otp' => 'Percobaan kode OTP habis. Silakan minta kode baru.',
            ]);
        }

        $otp->increment('attempts');

        if (! Hash::check($code, $otp->code_hash)) {
            throw ValidationException::withMessages(['otp' => 'Kode OTP salah.']);
        }

        $otp->update(['used_at' => now()]);

        return true;
    }
}
