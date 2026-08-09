<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\DTO\InquiryResultData;
use App\DTO\ProviderProductData;
use App\DTO\TopupResultData;
use App\Models\Transaction;
use Illuminate\Support\Str;

/**
 * Integrasi Digiflazz (https://developer.digiflazz.com).
 *
 * Signature = md5(username + apiKey + refId|"depo"|"pricelist").
 */
class DigiflazzProvider extends AbstractProvider
{
    private function username(): string
    {
        return (string) $this->provider->credential('username', config('ppob.providers.digiflazz.username'));
    }

    private function apiKey(): string
    {
        return (string) $this->provider->credential('api_key', config('ppob.providers.digiflazz.api_key'));
    }

    private function sign(string $suffix): string
    {
        return md5($this->username().$this->apiKey().$suffix);
    }

    public function topup(Transaction $transaction): TopupResultData
    {
        $body = $this->request('POST', '/transaction', [
            'username' => $this->username(),
            'buyer_sku_code' => $transaction->product->provider_sku,
            'customer_no' => $transaction->customer_no,
            'ref_id' => $transaction->ref_id,
            'sign' => $this->sign($transaction->ref_id),
            'testing' => (bool) config('ppob.providers.digiflazz.testing', false),
        ], $transaction);

        return $this->mapResult($body['data'] ?? []);
    }

    public function checkStatus(Transaction $transaction): TopupResultData
    {
        // Digiflazz memakai endpoint yang sama; request ulang ref_id
        // yang sudah ada mengembalikan status terkini, bukan transaksi baru.
        return $this->topup($transaction);
    }

    public function inquiry(Transaction|string $productSku, string $customerNo = ''): InquiryResultData
    {
        $sku = $productSku instanceof Transaction ? $productSku->product->provider_sku : $productSku;
        $number = $productSku instanceof Transaction ? $productSku->customer_no : $customerNo;
        $refId = 'INQ'.Str::upper(Str::random(12));

        $body = $this->request('POST', '/transaction', [
            'commands' => 'inq-pasca',
            'username' => $this->username(),
            'buyer_sku_code' => $sku,
            'customer_no' => $number,
            'ref_id' => $refId,
            'sign' => $this->sign($refId),
        ]);

        $data = $body['data'] ?? [];
        $rc = (string) ($data['rc'] ?? '');

        if ($rc !== '00') {
            return InquiryResultData::notFound($number, $data['message'] ?? 'Tagihan tidak ditemukan.');
        }

        return new InquiryResultData(
            found: true,
            customerNo: $number,
            customerName: $data['customer_name'] ?? null,
            billAmount: (float) ($data['price'] ?? 0),
            adminFee: (float) ($data['admin'] ?? 0),
            period: data_get($data, 'desc.detail.0.periode'),
            providerRef: $data['ref_id'] ?? $refId,
            message: $data['message'] ?? null,
            detail: (array) ($data['desc'] ?? []),
        );
    }

    public function fetchProducts(): array
    {
        $body = $this->request('POST', '/price-list', [
            'cmd' => 'prepaid',
            'username' => $this->username(),
            'sign' => $this->sign('pricelist'),
        ]);

        return array_map(
            fn (array $row) => new ProviderProductData(
                providerSku: (string) $row['buyer_sku_code'],
                name: (string) $row['product_name'],
                brand: (string) ($row['brand'] ?? ''),
                type: (string) ($row['type'] ?? ''),
                basePrice: (float) $row['price'],
                isAvailable: (bool) ($row['buyer_product_status'] ?? false) && (bool) ($row['seller_product_status'] ?? false),
                categorySlug: $this->mapCategory((string) ($row['category'] ?? 'lainnya')),
                description: $row['desc'] ?? null,
                raw: $row,
            ),
            $body['data'] ?? [],
        );
    }

    public function balance(): float
    {
        $body = $this->request('POST', '/cek-saldo', [
            'cmd' => 'deposit',
            'username' => $this->username(),
            'sign' => $this->sign('depo'),
        ]);

        return (float) data_get($body, 'data.deposit', 0);
    }

    public function verifyWebhook(string $payload, array $headers): bool
    {
        $secret = (string) $this->provider->credential('webhook_secret', config('ppob.providers.digiflazz.webhook_secret'));
        $received = $headers['x-hub-signature'][0] ?? $headers['X-Hub-Signature'][0] ?? '';

        if (blank($secret) || blank($received)) {
            return false;
        }

        $expected = 'sha1='.hash_hmac('sha1', $payload, $secret);

        return hash_equals($expected, $received);
    }

    public function parseWebhook(array $payload): TopupResultData
    {
        return $this->mapResult($payload['data'] ?? $payload);
    }

    public function webhookRefId(array $payload): ?string
    {
        return data_get($payload, 'data.ref_id') ?? data_get($payload, 'ref_id');
    }

    /** Terjemahkan response code Digiflazz ke status internal. */
    private function mapResult(array $data): TopupResultData
    {
        $status = strtolower((string) ($data['status'] ?? ''));
        $message = $data['message'] ?? null;
        $providerRef = $data['trx_id'] ?? ($data['ref_id'] ?? null);

        return match ($status) {
            'sukses', 'success' => TopupResultData::success(
                serialNumber: $data['sn'] ?? null,
                providerRef: $providerRef,
                message: $message,
                basePrice: isset($data['price']) ? (float) $data['price'] : null,
                customerName: $data['customer_name'] ?? null,
                raw: $data,
            ),
            'gagal', 'failed' => TopupResultData::failed($message, $providerRef, $data),
            default => TopupResultData::pending($providerRef, $message, $data),
        };
    }

    private function mapCategory(string $digiflazzCategory): string
    {
        return match (strtolower($digiflazzCategory)) {
            'pulsa' => 'pulsa',
            'data' => 'paket-data',
            'pln' => 'token-listrik',
            'games' => 'voucher-game',
            'e-money', 'emoney' => 'e-wallet',
            'voucher' => 'voucher',
            'tv' => 'tv-kabel',
            'masa aktif' => 'masa-aktif',
            default => Str::slug($digiflazzCategory) ?: 'lainnya',
        };
    }
}
