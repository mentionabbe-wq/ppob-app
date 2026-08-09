<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Deposit;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pembungkus payment gateway untuk deposit (VA, QRIS, e-wallet).
 * Saat ini mengimplementasi Midtrans Core API; gateway lain cukup
 * menambahkan method baru dan cabang pada createCharge().
 */
class PaymentGatewayService
{
    public function createCharge(Deposit $deposit): array
    {
        return match (config('ppob.payment.gateway')) {
            'midtrans' => $this->midtransCharge($deposit),
            default => [],
        };
    }

    /** Verifikasi signature notifikasi Midtrans. */
    public function verifyMidtransSignature(array $payload): bool
    {
        $serverKey = (string) config('ppob.payment.midtrans.server_key');

        $expected = hash('sha512',
            ($payload['order_id'] ?? '').
            ($payload['status_code'] ?? '').
            ($payload['gross_amount'] ?? '').
            $serverKey
        );

        return hash_equals($expected, (string) ($payload['signature_key'] ?? ''));
    }

    /** True bila notifikasi menandakan pembayaran lunas. */
    public function isSettlement(array $payload): bool
    {
        $status = $payload['transaction_status'] ?? '';
        $fraud = $payload['fraud_status'] ?? 'accept';

        return in_array($status, ['settlement', 'capture'], true) && $fraud === 'accept';
    }

    private function midtransCharge(Deposit $deposit): array
    {
        $baseUrl = config('ppob.payment.midtrans.is_production')
            ? 'https://api.midtrans.com/v2'
            : 'https://api.sandbox.midtrans.com/v2';

        $payload = [
            'payment_type' => $this->midtransPaymentType($deposit),
            'transaction_details' => [
                'order_id' => $deposit->code,
                'gross_amount' => (int) $deposit->total_amount,
            ],
            'customer_details' => [
                'first_name' => $deposit->user->name,
                'email' => $deposit->user->email,
                'phone' => $deposit->user->phone,
            ],
        ];

        if ($deposit->method === 'virtual_account') {
            $payload['bank_transfer'] = ['bank' => $deposit->channel ?? 'bca'];
        } elseif ($deposit->method === 'ewallet') {
            $payload['gopay'] = ['enable_callback' => true];
        }

        try {
            $response = Http::withBasicAuth((string) config('ppob.payment.midtrans.server_key'), '')
                ->acceptJson()
                ->timeout(20)
                ->post($baseUrl.'/charge', $payload);

            $body = $response->json() ?? [];
        } catch (\Throwable $e) {
            Log::error('Gagal membuat tagihan di payment gateway', [
                'deposit' => $deposit->code,
                'error' => $e->getMessage(),
            ]);

            return [];
        }

        return [
            'payment_ref' => $body['transaction_id'] ?? null,
            'va_number' => data_get($body, 'va_numbers.0.va_number') ?? ($body['permata_va_number'] ?? null),
            'qris_payload' => data_get($body, 'actions.0.url'),
            'raw' => $body,
        ];
    }

    private function midtransPaymentType(Deposit $deposit): string
    {
        return match ($deposit->method) {
            'virtual_account' => $deposit->channel === 'permata' ? 'permata' : 'bank_transfer',
            'qris' => 'qris',
            'ewallet' => 'gopay',
            default => 'bank_transfer',
        };
    }
}
