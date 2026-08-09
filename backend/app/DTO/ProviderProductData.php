<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Bentuk produk yang seragam dari provider mana pun, dipakai oleh
 * SyncProviderProductsJob untuk upsert ke tabel products.
 */
final readonly class ProviderProductData
{
    public function __construct(
        public string $providerSku,
        public string $name,
        public string $brand,
        public string $type,
        public float $basePrice,
        public bool $isAvailable,
        public string $categorySlug,
        public ?string $description = null,
        public array $raw = [],
    ) {}
}
