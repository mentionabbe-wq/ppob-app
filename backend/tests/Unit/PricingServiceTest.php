<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Product;
use App\Models\Promo;
use App\Services\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricing;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new PricingService;
    }

    public function test_margin_tetap_dibulatkan_ke_atas_per_seratus(): void
    {
        $this->assertSame(11_000.0, $this->pricing->applyMargin(10_000, 'fixed', 1_000));
        $this->assertSame(11_400.0, $this->pricing->applyMargin(10_350, 'fixed', 1_000));
    }

    public function test_margin_persen_dihitung_dari_harga_modal(): void
    {
        $this->assertSame(11_000.0, $this->pricing->applyMargin(10_000, 'percent', 10));
        $this->assertSame(10_500.0, $this->pricing->applyMargin(10_000, 'percent', 5));
    }

    public function test_diskon_promo_dibatasi_max_discount(): void
    {
        $promo = new Promo([
            'discount_type' => 'percent',
            'discount_value' => 50,
            'max_discount' => 2_000,
            'min_transaction' => 0,
        ]);

        $this->assertSame(2_000.0, $promo->discountFor(20_000));
    }

    public function test_promo_tidak_berlaku_di_bawah_minimum_transaksi(): void
    {
        $promo = new Promo([
            'discount_type' => 'fixed',
            'discount_value' => 5_000,
            'min_transaction' => 50_000,
        ]);

        $this->assertSame(0.0, $promo->discountFor(20_000));
    }

    public function test_quote_menghitung_total_dan_laba(): void
    {
        $product = new Product([
            'base_price' => 10_000,
            'sell_price' => 12_000,
            'admin_fee' => 1_000,
            'margin_value' => 2_000,
        ]);

        $quote = $this->pricing->quote($product);

        $this->assertSame(13_000.0, $quote['total_paid']);
        $this->assertSame(3_000.0, $quote['profit']);
    }
}
