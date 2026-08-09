<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Hasil cek tagihan produk pascabayar (PLN, BPJS, PDAM, Telkom, Multi Finance).
 */
final readonly class InquiryResultData
{
    public function __construct(
        public bool $found,
        public string $customerNo,
        public ?string $customerName = null,
        public float $billAmount = 0,
        public float $adminFee = 0,
        public ?string $period = null,
        public ?string $providerRef = null,
        public ?string $message = null,
        public array $detail = [],
    ) {}

    public function total(): float
    {
        return $this->billAmount + $this->adminFee;
    }

    public static function notFound(string $customerNo, ?string $message = null): self
    {
        return new self(false, $customerNo, message: $message ?? 'Tagihan tidak ditemukan.');
    }
}
