<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Services\DepositService;
use App\Services\PaymentGatewayService;
use App\Services\Providers\ProviderManager;
use App\Services\TransactionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Endpoint callback. Seluruh rute di sini sudah melewati middleware
 * `webhook.signature` (kecuali gateway pembayaran yang memverifikasi
 * signature-nya sendiri) dan dikecualikan dari CSRF.
 */
class WebhookController extends BaseApiController
{
    public function __construct(
        private readonly ProviderManager $providers,
        private readonly TransactionRepositoryInterface $transactions,
        private readonly TransactionService $transactionService,
        private readonly DepositRepositoryInterface $deposits,
        private readonly DepositService $depositService,
        private readonly PaymentGatewayService $gateway,
    ) {}

    /** Callback status transaksi dari provider PPOB. */
    public function provider(Request $request, string $provider): JsonResponse
    {
        $driver = $this->providers->driver($provider);
        $payload = $request->all();

        $refId = $driver->webhookRefId($payload);

        if ($refId === null) {
            Log::warning('Webhook provider tanpa ref_id', ['provider' => $provider]);

            return $this->ok(null, 'Payload diabaikan: ref_id tidak ditemukan.');
        }

        $transaction = $this->transactions->findByRefId($refId);

        if ($transaction === null) {
            Log::warning('Webhook untuk transaksi tak dikenal', ['provider' => $provider, 'ref_id' => $refId]);

            return $this->ok(null, 'Transaksi tidak ditemukan.');
        }

        $driver->logIncomingWebhook($payload, $transaction);
        $this->transactionService->applyResult($transaction, $driver->parseWebhook($payload));

        return $this->ok(null, 'Webhook diproses.');
    }

    /** Notifikasi pembayaran dari payment gateway (deposit). */
    public function payment(Request $request): JsonResponse
    {
        $payload = $request->all();

        if (! $this->gateway->verifyMidtransSignature($payload)) {
            Log::warning('Signature notifikasi pembayaran tidak valid', ['ip' => $request->ip()]);

            return $this->fail('Signature tidak valid.', 'INVALID_SIGNATURE', 401);
        }

        $deposit = $this->deposits->findByCode((string) ($payload['order_id'] ?? ''));

        if ($deposit === null) {
            return $this->ok(null, 'Deposit tidak ditemukan.');
        }

        if ($this->gateway->isSettlement($payload)) {
            $this->depositService->markPaid($deposit, $payload['transaction_id'] ?? null);
        } elseif (in_array($payload['transaction_status'] ?? '', ['expire', 'cancel', 'deny'], true)) {
            $this->depositService->expire($deposit);
        }

        return $this->ok(null, 'Notifikasi pembayaran diproses.');
    }
}
