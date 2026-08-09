<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Transaction;

interface TransactionRepositoryInterface extends BaseRepositoryInterface
{
    public function findByRefId(string $refId): ?Transaction;

    public function findByInvoice(string $invoiceNo): ?Transaction;

    public function findByProviderRef(string $providerRef): ?Transaction;

    /** Ringkasan omzet, profit, jumlah transaksi pada rentang tanggal. */
    public function summary(?string $from = null, ?string $to = null, ?int $userId = null): array;

    /** Deret harian untuk grafik dashboard admin. */
    public function dailySeries(int $days = 30): array;

    /** Deret bulanan (12 bulan terakhir). */
    public function monthlySeries(int $months = 12): array;

    public function bestSellingProducts(int $limit = 10, ?string $from = null, ?string $to = null): array;

    public function mostActiveUsers(int $limit = 10, ?string $from = null, ?string $to = null): array;

    /** Transaksi yang macet di status processing melebihi X menit. */
    public function stalePending(int $minutes = 15): \Illuminate\Support\Collection;

    /** Ambil transaksi dengan row lock (dipakai di dalam DB::transaction). */
    public function lockForUpdate(int $id): ?Transaction;

    public function nextInvoiceNumber(): string;
}
