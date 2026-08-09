<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Deposit;
use Illuminate\Support\Collection;

interface DepositRepositoryInterface extends BaseRepositoryInterface
{
    public function findByCode(string $code): ?Deposit;

    public function findByPaymentRef(string $paymentRef): ?Deposit;

    public function lockForUpdate(int $id): ?Deposit;

    /** Kode unik transfer bank yang belum dipakai hari ini. */
    public function availableUniqueCode(float $amount): int;

    public function summary(?string $from = null, ?string $to = null): array;

    public function expired(): Collection;

    public function nextCode(): string;
}
