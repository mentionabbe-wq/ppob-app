<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Kirim push notification via Firebase Cloud Messaging HTTP v1.
 */
class SendPushNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 60];

    public function __construct(
        public readonly int $userId,
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {
        $this->onQueue('notifications');
    }

    public function handle(): void
    {
        $user = User::find($this->userId);
        $projectId = config('ppob.fcm.project_id');

        if ($user === null || blank($user->fcm_token) || blank($projectId)) {
            return;
        }

        $accessToken = $this->accessToken();

        if ($accessToken === null) {
            return;
        }

        $response = Http::withToken($accessToken)
            ->timeout(15)
            ->post("https://fcm.googleapis.com/v1/projects/{$projectId}/messages:send", [
                'message' => [
                    'token' => $user->fcm_token,
                    'notification' => ['title' => $this->title, 'body' => $this->body],
                    'data' => array_map('strval', $this->data),
                    'android' => ['priority' => 'high'],
                    'apns' => ['headers' => ['apns-priority' => '10']],
                ],
            ]);

        // Token perangkat sudah tidak valid → bersihkan agar tidak dicoba lagi.
        if ($response->status() === 404 || $response->status() === 400) {
            $user->update(['fcm_token' => null]);
            Log::info('FCM token dihapus karena tidak valid', ['user_id' => $user->id]);
        }
    }

    /** OAuth2 access token dari service account, di-cache 55 menit. */
    private function accessToken(): ?string
    {
        return Cache::remember('fcm.access_token', now()->addMinutes(55), function () {
            $path = base_path((string) config('ppob.fcm.credentials_path'));

            if (! is_file($path)) {
                Log::warning('Berkas kredensial FCM tidak ditemukan', ['path' => $path]);

                return null;
            }

            $credentials = json_decode((string) file_get_contents($path), true);
            $now = time();

            $claim = [
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now,
            ];

            $segments = [
                $this->base64Url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'])),
                $this->base64Url(json_encode($claim)),
            ];

            openssl_sign(implode('.', $segments), $signature, $credentials['private_key'], 'sha256');
            $segments[] = $this->base64Url($signature);

            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => implode('.', $segments),
            ]);

            return $response->json('access_token');
        });
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
