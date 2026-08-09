<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Exceptions\ProviderException;
use App\Models\ApiLog;
use App\Models\Provider;
use App\Models\Transaction;
use App\Services\Providers\Contracts\PpobProviderInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Perilaku umum seluruh provider: HTTP client dengan retry,
 * pencatatan api_logs, dan penyamaran data sensitif.
 */
abstract class AbstractProvider implements PpobProviderInterface
{
    protected const TIMEOUT = 30;

    protected const RETRY_TIMES = 2;

    /** Field yang tidak boleh tersimpan apa adanya di api_logs. */
    protected array $sensitiveKeys = ['sign', 'signature', 'api_key', 'apikey', 'key', 'password', 'secret'];

    public function __construct(protected readonly Provider $provider) {}

    public function code(): string
    {
        return $this->provider->code;
    }

    public function model(): Provider
    {
        return $this->provider;
    }

    protected function http(): PendingRequest
    {
        return Http::baseUrl($this->provider->base_url)
            ->timeout(self::TIMEOUT)
            ->retry(self::RETRY_TIMES, 1500, throw: false)
            ->acceptJson()
            ->asJson();
    }

    /**
     * Kirim request ke provider sambil mencatat api_logs.
     *
     * @throws ProviderException
     */
    protected function request(
        string $method,
        string $endpoint,
        array $payload = [],
        ?Transaction $transaction = null,
    ): array {
        $startedAt = microtime(true);
        $response = null;
        $error = null;

        try {
            /** @var Response $response */
            $response = $this->http()->send(strtoupper($method), $endpoint, ['json' => $payload]);
            $body = $response->json() ?? [];
        } catch (\Throwable $e) {
            $error = $e->getMessage();
            $body = [];
            Log::error('Panggilan provider gagal', [
                'provider' => $this->code(),
                'endpoint' => $endpoint,
                'error' => $error,
            ]);
        }

        $this->log($endpoint, $method, $payload, $body, $response?->status(), $startedAt, $transaction, $error);

        if ($error !== null) {
            throw new ProviderException("Tidak dapat menghubungi provider {$this->code()}: {$error}");
        }

        if ($response !== null && $response->serverError()) {
            throw new ProviderException("Provider {$this->code()} mengembalikan galat {$response->status()}.");
        }

        return $body;
    }

    protected function log(
        string $endpoint,
        string $method,
        array $request,
        array $response,
        ?int $httpCode,
        float $startedAt,
        ?Transaction $transaction = null,
        ?string $error = null,
        string $direction = 'outgoing',
    ): void {
        ApiLog::create([
            'provider_id' => $this->provider->id,
            'transaction_id' => $transaction?->id,
            'direction' => $direction,
            'endpoint' => $endpoint,
            'method' => strtoupper($method),
            'request_payload' => $this->mask($request),
            'response_payload' => $this->mask($response),
            'http_code' => $httpCode,
            'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            'ip_address' => request()->ip(),
            'error_message' => $error,
        ]);
    }

    protected function mask(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->mask($value);

                continue;
            }

            if (in_array(strtolower((string) $key), $this->sensitiveKeys, true)) {
                $data[$key] = '***';
            }
        }

        return $data;
    }

    /** Catat webhook masuk agar terlihat di panel admin. */
    public function logIncomingWebhook(array $payload, ?Transaction $transaction = null): void
    {
        $this->log('webhook', 'POST', $payload, [], 200, microtime(true), $transaction, null, 'incoming');
    }
}
