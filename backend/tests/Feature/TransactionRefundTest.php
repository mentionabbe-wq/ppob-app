<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\TopupResultData;
use App\Enums\TransactionStatus;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionRefundTest extends TestCase
{
    use RefreshDatabase;

    private function pendingTransaction(User $user): Transaction
    {
        $product = Product::factory()->create(['base_price' => 10_000, 'sell_price' => 11_000]);

        return Transaction::create([
            'invoice_no' => 'INV'.now()->format('Ymd').'000001',
            'ref_id' => 'TRXREFUND000001',
            'user_id' => $user->id,
            'product_id' => $product->id,
            'provider_id' => $product->provider_id,
            'product_name' => $product->name,
            'customer_no' => '081234567890',
            'base_price' => 10_000,
            'sell_price' => 11_000,
            'total_paid' => 11_000,
            'profit' => 1_000,
            'status' => TransactionStatus::Processing,
        ]);
    }

    public function test_hasil_gagal_dari_provider_mengembalikan_saldo(): void
    {
        $user = User::factory()->withBalance(39_000)->create();
        $transaction = $this->pendingTransaction($user);

        app(TransactionService::class)->applyResult(
            $transaction,
            TopupResultData::failed('Nomor tujuan diblokir', 'PRV-1'),
        );

        $transaction->refresh();

        $this->assertSame(TransactionStatus::Refunded, $transaction->status);
        $this->assertNotNull($transaction->refunded_at);
        $this->assertEquals(50_000, (float) $user->fresh()->wallet->balance);
    }

    public function test_refund_bersifat_idempoten(): void
    {
        $user = User::factory()->withBalance(39_000)->create();
        $transaction = $this->pendingTransaction($user);
        $service = app(TransactionService::class);

        $service->refund($transaction, 'Percobaan pertama');
        $service->refund($transaction->fresh(), 'Percobaan kedua');

        // Saldo hanya bertambah satu kali walau refund dipanggil dua kali.
        $this->assertEquals(50_000, (float) $user->fresh()->wallet->balance);
        $this->assertSame(1, $transaction->mutations()->where('type', 'refund')->count());
    }

    public function test_status_final_tidak_ditimpa_webhook_yang_datang_terlambat(): void
    {
        $user = User::factory()->withBalance(39_000)->create();
        $transaction = $this->pendingTransaction($user);
        $service = app(TransactionService::class);

        $service->applyResult($transaction, TopupResultData::success('SN-123', 'PRV-1'));
        $service->applyResult($transaction->fresh(), TopupResultData::failed('Terlambat', 'PRV-1'));

        $transaction->refresh();

        $this->assertSame(TransactionStatus::Success, $transaction->status);
        $this->assertSame('SN-123', $transaction->serial_number);
        $this->assertEquals(39_000, (float) $user->fresh()->wallet->balance);
    }
}
