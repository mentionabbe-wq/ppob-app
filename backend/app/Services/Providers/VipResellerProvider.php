<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\InquiryResultData;
use App\DTO\ProviderProductData;
use App\DTO\TopupResultData;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Integrasi VIP Reseller (https://vip-reseller.co.id/page/api).
 *
 * Signature = md5(apiId + apiKey). Request memakai form-encoded.
 */
class VipResellerProvider extends AbstractProvider
{
    private function apiId(): string
    {
        return (string) $this->provider->credential('api_id', config('ppob.providers.vipreseller.api_id'));
    }

    private function apiKey(): string
    {
        return (string) $this->provider->credential('api_key', config('ppob.providers.vipreseller.api_key'));
    }

    private function credentials(): array
    {
        return [
            'key' => $this->apiKey(),
            'sign' => md5($this->apiId().$this->apiKey()),
        ];
    }

    public function topup(Transaction $transaction): TopupResultData
    {
        $body = $this->request('POST', '/prepaid', $this->credentials() + [
            'type' => 'order',
            'service' => $transaction->product->provider_sku,
            'data_no' => $transaction->customer_no,
            'trxid' => $transaction->ref_id,
        ], $transaction);

        return $this->mapResult($body);
    }

    public function checkStatus(Transaction $transaction): TopupResultData
    {
        $body = $this->request('POST', '/prepaid', $this->credentials() + [
            'type' => 'status',
            'trxid' => $transaction->ref_id,
        ], $transaction);

        return $this->mapResult($body);
    }

    public function inquiry(Transaction|string $productSku, string $customerNo = ''): InquiryResultData
    {
        $sku = $productSku instanceof Transaction ? $productSku->product->provider_sku : $productSku;
        $number = $productSku instanceof Transaction ? $productSku->customer_no : $customerNo;

        $body = $this->request('POST', '/postpaid', $this->credentials() + [
            'type' => 'inq-pasca',
            'service' => $sku,
            'data_no' => $number,
        ]);

        if (! ($body['result'] ?? false)) {
            return InquiryResultData::notFound($number, $body['message'] ?? null);
        }

        $data = $body['data'] ?? [];

        return new InquiryResultData(
            found: true,
            customerNo: $number,
            customerName: $data['customer_name'] ?? null,
            billAmount: (float) ($data['price'] ?? 0),
            adminFee: (float) ($data['admin'] ?? 0),
            period: $data['period'] ?? null,
            providerRef: $data['trxid'] ?? null,
            message: $body['message'] ?? null,
            detail: (array) ($data['desc'] ?? []),
        );
    }

    public function fetchProducts(): array
    {
        $body = $this->request('POST', '/prepaid', $this->credentials() + ['type' => 'services']);

        return array_map(
            fn (array $row) => new ProviderProductData(
                providerSku: (string) $row['code'],
                name: (string) $row['name'],
                brand: (string) ($row['brand'] ?? ''),
                type: (string) ($row['category'] ?? ''),
                basePrice: (float) data_get($row, 'price.basic', $row['price'] ?? 0),
                isAvailable: strtolower((string) ($row['status'] ?? '')) === 'available',
                categorySlug: Str::slug((string) ($row['category'] ?? 'lainnya')),
                description: $row['note'] ?? null,
                raw: $row,
            ),
            $body['data'] ?? [],
        );
    }

    public function balance(): float
    {
        $body = $this->request('POST', '/profile', $this->credentials());

        return (float) data_get($body, 'data.balance', 0);
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        $secret = (string) $this->provider->credential('webhook_secret', '');
        $received = $headers['x-signature'][0] ?? '';

        if (blank($secret) || blank($received)) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $payload, $secret), $received);
    }

    public function parseWebhook(array $payload): TopupResultData
    {
        return $this->mapResult(['result' => true, 'data' => $payload['data'] ?? $payload]);
    }

    public function webhookRefId(array $payload): ?string
    {
        return data_get($payload, 'data.trxid') ?? data_get($payload, 'trxid');
    }

    private function mapResult(array $body): TopupResultData
    {
        $data = $body['data'] ?? [];
        $status = strtolower((string) ($data['status'] ?? ''));
        $message = $body['message'] ?? ($data['note'] ?? null);
        $providerRef = $data['trxid'] ?? null;

        if (($body['result'] ?? false) === false && $status === '') {
            return TopupResultData::failed($message, $providerRef, $body);
        }

        return match ($status) {
            'success' => TopupResultData::success(
                serialNumber: $data['sn'] ?? null,
                providerRef: $providerRef,
                message: $message,
                basePrice: isset($data['price']) ? (float) $data['price'] : null,
                raw: $data,
            ),
            'error', 'failed' => TopupResultData::failed($message, $providerRef, $data),
            default => TopupResultData::pending($providerRef, $message, $data),
        };
    }
}
