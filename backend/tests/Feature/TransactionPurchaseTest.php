<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\DTO\TopupRequestData;
use App\Enums\MutationType;
use App\Enums\TransactionStatus;
use App\Exceptions\InsufficientBalanceException;
use App\Models\Product;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TransactionPurchaseTest extends TestCase
{
    use RefreshDatabase;

    private function makeProduct(array $attributes = []): Product
    {
        return Product::factory()->create([
            'base_price' => 10_000,
            'sell_price' => 11_000,
            ...$attributes,
        ]);
    }

    public function test_pembelian_memotong_saldo_dan_membuat_ledger(): void
    {
        Queue::fake();

        $user = User::factory()->withBalance(50_000)->create();
        $product = $this->makeProduct();

        $transaction = app(TransactionService::class)->purchase(TopupRequestData::make(
            user: $user,
            product: $product,
            customerNo: '081234567890',
            refId: 'TRXTEST00000001',
        ));

        $this->assertSame(TransactionStatus::Pending, $transaction->status);
        $this->assertEquals(11_000, (float) $transaction->total_paid);
        $this->assertEquals(1_000, (float) $transaction->profit);

        // Saldo terpotong tepat sekali dan tercatat di ledger.
        $this->assertEquals(39_000, (float) $user->fresh()->wallet->balance);
        $this->assertDatabaseHas('wallet_mutations', [
            'user_id' => $user->id,
            'type' => MutationType::Purchase->value,
            'amount' => -11_000,
            'balance_before' => 50_000,
            'balance_after' => 39_000,
        ]);
    }

    public function test_saldo_kurang_menolak_transaksi_tanpa_menyisakan_data(): void
    {
        Queue::fake();

        $user = User::factory()->withBalance(5_000)->create();
        $product = $this->makeProduct();

        $this->expectException(InsufficientBalanceException::class);

        try {
            app(TransactionService::class)->purchase(TopupRequestData::make(
                user: $user,
                product: $product,
                customerNo: '081234567890',
                refId: 'TRXTEST00000002',
            ));
        } finally {
            // Rollback harus membatalkan pembuatan transaksi juga.
            $this->assertDatabaseCount('transactions', 0);
            $this->assertEquals(5_000, (float) $user->fresh()->wallet->balance);
        }
    }

    public function test_ref_id_yang_sama_tidak_menggandakan_transaksi(): void
    {
        Queue::fake();

        $user = User::factory()->withBalance(100_000)->create();
        $product = $this->makeProduct();
        $service = app(TransactionService::class);

        $payload = fn () => TopupRequestData::make(
            user: $user->fresh(),
            product: $product,
            customerNo: '081234567890',
            refId: 'TRXIDEMPOTEN001',
        );

        $first = $service->purchase($payload());
        $second = $service->purchase($payload());

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('transactions', 1);
        $this->assertEquals(89_000, (float) $user->fresh()->wallet->balance);
    }

    public function test_produk_tidak_tersedia_ditolak(): void
    {
        Queue::fake();

        $user = User::factory()->withBalance(100_000)->create();
        $product = $this->makeProduct(['is_available' => false]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TransactionService::class)->purchase(TopupRequestData::make(
            user: $user,
            product: $product,
            customerNo: '081234567890',
            refId: 'TRXTEST00000003',
        ));
    }

    public function test_pin_salah_menolak_transaksi(): void
    {
        Queue::fake();

        $user = User::factory()->withPin('123456')->withBalance(100_000)->create();
        $product = $this->makeProduct();

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(TransactionService::class)->purchase(TopupRequestData::make(
            user: $user,
            product: $product,
            customerNo: '081234567890',
            refId: 'TRXTEST00000004',
            pin: '999999',
        ));
    }
}
