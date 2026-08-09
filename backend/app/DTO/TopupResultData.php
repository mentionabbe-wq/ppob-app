<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\TransactionStatus;

/**
 * Hasil normalisasi respons provider. Seluruh provider WAJIB
 * mengembalikan objek ini agar service layer tidak tahu bentuk
 * respons masing-masing API.
 */
final readonly class TopupResultData
{
    public function __construct(
        public TransactionStatus $status,
        public ?string $serialNumber = null,
        public ?string $providerRef = null,
        public ?string $message = null,
        public ?float $basePrice = null,
        public ?string $customerName = null,
        public array $raw = [],
    ) {}

    public static function pending(?string $providerRef = null, ?string $message = null, array $raw = []): self
    {
        return new self(TransactionStatus::Processing, null, $providerRef, $message, raw: $raw);
    }

    public static function success(
        ?string $serialNumber,
        ?string $providerRef = null,
        ?string $message = null,
        ?float $basePrice = null,
        ?string $customerName = null,
        array $raw = [],
    ): self {
        return new self(
            TransactionStatus::Success,
            $serialNumber,
            $providerRef,
            $message,
            $basePrice,
            $customerName,
            $raw,
        );
    }

    public static function failed(?string $message, ?string $providerRef = null, array $raw = []): self
    {
        return new self(TransactionStatus::Failed, null, $providerRef, $message, raw: $raw);
    }
}
