<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Kategori mengikuti daftar produk yang diminta. Menambah layanan
     * baru cukup menambah baris di sini (atau lewat panel admin) —
     * aplikasi mobile menariknya secara dinamis, tanpa update APK.
     */
    private const CATEGORIES = [
        ['name' => 'Pulsa', 'slug' => 'pulsa', 'icon' => 'smartphone', 'color' => '#2563eb', 'type' => 'prepaid', 'input_label' => 'Nomor HP', 'input_type' => 'phone'],
        ['name' => 'Paket Data', 'slug' => 'paket-data', 'icon' => 'wifi', 'color' => '#0ea5e9', 'type' => 'prepaid', 'input_label' => 'Nomor HP', 'input_type' => 'phone'],
        ['name' => 'Token Listrik', 'slug' => 'token-listrik', 'icon' => 'zap', 'color' => '#f59e0b', 'type' => 'prepaid', 'input_label' => 'Nomor Meter / ID Pelanggan', 'input_type' => 'number'],
        ['name' => 'Tagihan Listrik', 'slug' => 'tagihan-listrik', 'icon' => 'zap-off', 'color' => '#f97316', 'type' => 'postpaid', 'input_label' => 'ID Pelanggan PLN', 'input_type' => 'number'],
        ['name' => 'BPJS Kesehatan', 'slug' => 'bpjs', 'icon' => 'heart-pulse', 'color' => '#16a34a', 'type' => 'postpaid', 'input_label' => 'Nomor Kartu BPJS', 'input_type' => 'number'],
        ['name' => 'PDAM', 'slug' => 'pdam', 'icon' => 'droplet', 'color' => '#0891b2', 'type' => 'postpaid', 'input_label' => 'Nomor Pelanggan PDAM', 'input_type' => 'number'],
        ['name' => 'Telkom / IndiHome', 'slug' => 'telkom', 'icon' => 'phone', 'color' => '#dc2626', 'type' => 'postpaid', 'input_label' => 'Nomor Telepon / IndiHome', 'input_type' => 'number'],
        ['name' => 'TV Kabel', 'slug' => 'tv-kabel', 'icon' => 'tv', 'color' => '#7c3aed', 'type' => 'postpaid', 'input_label' => 'Nomor Pelanggan', 'input_type' => 'number'],
        ['name' => 'E-Wallet', 'slug' => 'e-wallet', 'icon' => 'wallet', 'color' => '#10b981', 'type' => 'prepaid', 'input_label' => 'Nomor HP Terdaftar', 'input_type' => 'phone'],
        ['name' => 'Voucher Game', 'slug' => 'voucher-game', 'icon' => 'gamepad-2', 'color' => '#8b5cf6', 'type' => 'prepaid', 'input_label' => 'User ID', 'input_type' => 'text'],
        ['name' => 'Internet', 'slug' => 'internet', 'icon' => 'globe', 'color' => '#3b82f6', 'type' => 'postpaid', 'input_label' => 'Nomor Pelanggan', 'input_type' => 'number'],
        ['name' => 'Multi Finance', 'slug' => 'multi-finance', 'icon' => 'landmark', 'color' => '#64748b', 'type' => 'postpaid', 'input_label' => 'Nomor Kontrak', 'input_type' => 'number'],
    ];

    public function run(): void
    {
        foreach (self::CATEGORIES as $index => $category) {
            Category::updateOrCreate(
                ['slug' => $category['slug']],
                $category + ['is_active' => true, 'sort_order' => $index + 1],
            );
        }
    }
}
