<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\Providers\ProviderManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tiga lapis proteksi webhook provider:
 *   1. IP allowlist (bila dikonfigurasi)
 *   2. Verifikasi signature HMAC milik provider
 *   3. Replay guard — payload identik ditolak selama 24 jam
 */
class VerifyWebhookSignature
{
    public function __construct(private readonly ProviderManager $providers) {}

    public function handle(Request $request, Closure $next, ?string $providerCode = null): Response
    {
        $providerCode ??= (string) $request->route('provider');

        $allowedIps = array_filter((array) config("ppob.providers.{$providerCode}.allowed_ips", []));

        if ($allowedIps !== [] && ! in_array($request->ip(), $allowedIps, true)) {
            Log::warning('Webhook ditolak: IP tidak dikenal', [
                'provider' => $providerCode,
                'ip' => $request->ip(),
            ]);

            return $this->reject('IP tidak diizinkan.');
        }

        $raw = $request->getContent();

        try {
            $driver = $this->providers->driver($providerCode);
        } catch (\Throwable $e) {
            return $this->reject('Provider tidak dikenal.');
        }

        if (! $driver->verifyWebhook($raw, $request->headers->all())) {
            Log::warning('Webhook ditolak: signature tidak valid', [
                'provider' => $providerCode,
                'ip' => $request->ip(),
            ]);

            return $this->reject('Signature tidak valid.');
        }

        $fingerprint = 'webhook:'.$providerCode.':'.hash('sha256', $raw);

        if (! Cache::add($fingerprint, true, now()->addDay())) {
            // Sudah pernah diproses — balas 200 agar provider berhenti mengulang.
            return response()->json(['success' => true, 'message' => 'Duplikat, diabaikan.']);
        }

        return $next($request);
    }

    private function reject(string $message): Response
    {
        return response()->json([
            'success' => false,
            'code' => 'INVALID_WEBHOOK',
            'message' => $message,
        ], 401);
    }
}
