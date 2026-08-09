<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Provider;
use App\Services\PricingService;
use Illuminate\Database\Seeder;

/**
 * Produk contoh untuk pengembangan lokal. Di produksi, katalog
 * sesungguhnya datang dari SyncProviderProductsJob.
 */
class ProductSeeder extends Seeder
{
    public function run(PricingService $pricing): void
    {
        $provider = Provider::where('code', 'digiflazz')->first();

        if ($provider === null) {
            $this->command->warn('Provider digiflazz belum ada, ProductSeeder dilewati.');

            return;
        }

        $catalog = [
            'pulsa' => [
                ['TSEL5', 'Telkomsel 5.000', 'TELKOMSEL', 5_800],
                ['TSEL10', 'Telkomsel 10.000', 'TELKOMSEL', 10_800],
                ['TSEL25', 'Telkomsel 25.000', 'TELKOMSEL', 25_300],
                ['ISAT10', 'Indosat 10.000', 'INDOSAT', 10_500],
                ['XL10', 'XL 10.000', 'XL', 10_600],
                ['TRI10', 'Tri 10.000', 'TRI', 10_400],
            ],
            'paket-data' => [
                ['TSELDATA1', 'Telkomsel Data 1GB / 30 Hari', 'TELKOMSEL', 18_500],
                ['TSELDATA5', 'Telkomsel Data 5GB / 30 Hari', 'TELKOMSEL', 49_000],
                ['XLDATA3', 'XL Data 3GB / 30 Hari', 'XL', 27_500],
            ],
            'token-listrik' => [
                ['PLN20', 'Token PLN 20.000', 'PLN', 20_500],
                ['PLN50', 'Token PLN 50.000', 'PLN', 50_500],
                ['PLN100', 'Token PLN 100.000', 'PLN', 100_500],
            ],
            'e-wallet' => [
                ['DANA25', 'Saldo DANA 25.000', 'DANA', 25_500],
                ['OVO50', 'Saldo OVO 50.000', 'OVO', 50_800],
                ['GOPAY50', 'Saldo GoPay 50.000', 'GOPAY', 50_800],
            ],
            'voucher-game' => [
                ['ML86', 'Mobile Legends 86 Diamonds', 'MOBILE LEGENDS', 21_000],
                ['FF70', 'Free Fire 70 Diamonds', 'FREE FIRE', 9_500],
            ],
            'tagihan-listrik' => [
                ['PLNPASCA', 'Tagihan Listrik PLN Pascabayar', 'PLN', 0],
            ],
            'bpjs' => [
                ['BPJSKS', 'BPJS Kesehatan', 'BPJS', 0],
            ],
        ];

        foreach ($catalog as $slug => $items) {
            $category = Category::where('slug', $slug)->first();

            if ($category === null) {
                continue;
            }

            foreach ($items as [$sku, $name, $brand, $basePrice]) {
                Product::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'category_id' => $category->id,
                        'provider_id' => $provider->id,
                        'provider_sku' => strtolower($sku),
                        'name' => $name,
                        'brand' => $brand,
                        'type' => $category->name,
                        'base_price' => $basePrice,
                        'sell_price' => $basePrice > 0 ? $pricing->defaultSellPrice((float) $basePrice) : 0,
                        'margin_type' => config('ppob.pricing.default_margin_type'),
                        'margin_value' => config('ppob.pricing.default_margin_value'),
                        'admin_fee' => $category->type === 'postpaid' ? 2_500 : 0,
                        'is_active' => true,
                        'is_available' => true,
                    ],
                );
            }
        }
    }
}
