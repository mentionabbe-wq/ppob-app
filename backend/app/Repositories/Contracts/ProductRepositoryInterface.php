<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\DTO\ProviderProductData;
use App\Models\Product;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function findBySku(string $sku): ?Product;

    /** Produk siap jual untuk aplikasi mobile (sudah difilter aktif & tersedia). */
    public function sellable(array $filters = []): Collection;

    /** Daftar brand unik dalam satu kategori (Telkomsel, Indosat, dst). */
    public function brands(int $categoryId): Collection;

    /** Upsert katalog hasil sinkronisasi provider; mengembalikan jumlah (baru, terbarui). */
    public function syncFromProvider(int $providerId, ProviderProductData ...$items): array;
}
