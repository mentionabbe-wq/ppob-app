<?php

declare(strict_types=1);

namespace App\DTO;

use App\Models\Product;
use App\Models\User;

/**
 * Payload permintaan topup dari HTTP layer menuju service/provider.
 */
final readonly class TopupRequestData
{
    public function __construct(
        public User $user,
        public Product $product,
        public string $customerNo,
        public string $refId,
        public ?string $promoCode = null,
        public ?string $pin = null,
        public array $meta = [],
    ) {}

    public static function make(
        User $user,
        Product $product,
        string $customerNo,
        string $refId,
        ?string $promoCode = null,
        ?string $pin = null,
        array $meta = [],
    ): self {
        return new self($user, $product, trim($customerNo), $refId, $promoCode, $pin, $meta);
    }
}
