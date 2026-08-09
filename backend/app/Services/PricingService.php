<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Product;
use App\Models\Promo;
use App\Models\User;

/**
 * Satu-satunya sumber kebenaran perhitungan harga jual, margin,
 * diskon, dan laba. Controller/provider tidak menghitung harga sendiri.
 */
class PricingService
{
    public function applyMargin(float $basePrice, string $marginType, float $marginValue): float
    {
        $sell = $marginType === 'percent'
            ? $basePrice + ($basePrice * $marginValue / 100)
            : $basePrice + $marginValue;

        // Bulatkan ke atas per 100 rupiah agar harga jual rapi.
        return (float) (ceil($sell / 100) * 100);
    }

    public function defaultSellPrice(float $basePrice): float
    {
        return $this->applyMargin(
            $basePrice,
            (string) config('ppob.pricing.default_margin_type', 'fixed'),
            (float) config('ppob.pricing.default_margin_value', 1000),
        );
    }

    /**
     * Rincian biaya sebuah transaksi.
     *
     * @return array{base_price: float, sell_price: float, admin_fee: float, discount: float, total_paid: float, profit: float}
     */
    public function quote(Product $product, ?Promo $promo = null, ?float $billAmount = null): array
    {
        $basePrice = $billAmount ?? (float) $product->base_price;
        $sellPrice = $billAmount !== null
            ? $billAmount + (float) $product->margin_value
            : (float) $product->sell_price;

        $adminFee = (float) $product->admin_fee;
        $subtotal = $sellPrice + $adminFee;
        $discount = $promo?->discountFor($subtotal) ?? 0.0;
        $totalPaid = max(0, round($subtotal - $discount, 2));

        return [
            'base_price' => round($basePrice, 2),
            'sell_price' => round($sellPrice, 2),
            'admin_fee' => round($adminFee, 2),
            'discount' => round($discount, 2),
            'total_paid' => $totalPaid,
            'profit' => round($totalPaid - $basePrice, 2),
        ];
    }

    /** Validasi kelayakan promo untuk user tertentu. */
    public function validatePromo(?string $code, User $user, float $amount, ?int $categoryId = null): ?Promo
    {
        if (blank($code)) {
            return null;
        }

        $promo = Promo::active()->where('code', strtoupper($code))->first();

        if ($promo === null || ! $promo->hasQuota()) {
            return null;
        }

        if ($promo->category_id !== null && $promo->category_id !== $categoryId) {
            return null;
        }

        if ($amount < (float) $promo->min_transaction) {
            return null;
        }

        $usedByUser = $promo->transactions()->where('user_id', $user->id)->count();
        if ($usedByUser >= $promo->per_user_limit) {
            return null;
        }

        return $promo;
    }
}
