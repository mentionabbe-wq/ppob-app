<?php

declare(strict_types=1);

namespace App\Services\Providers\Contracts;

use App\DTO\InquiryResultData;
use App\DTO\ProviderProductData;
use App\DTO\TopupResultData;
use App\Models\Transaction;

/**
 * Kontrak tunggal seluruh penyedia API PPOB.
 *
 * Menambah provider baru = membuat satu kelas yang mengimplementasi
 * antarmuka ini + mendaftarkannya di ProviderManager. Tidak ada
 * perubahan pada controller, service, maupun aplikasi mobile.
 */
interface PpobProviderInterface
{
    /** Kode unik provider, sama dengan kolom `providers.code`. */
    public function code(): string;

    /** Eksekusi pembelian produk prabayar / pembayaran tagihan. */
    public function topup(Transaction $transaction): TopupResultData;

    /** Cek status transaksi ke provider (polling / rekonsiliasi). */
    public function checkStatus(Transaction $transaction): TopupResultData;

    /** Cek tagihan produk pascabayar sebelum dibayar. */
    public function inquiry(Transaction|string $productSku, string $customerNo = ''): InquiryResultData;

    /**
     * Tarik seluruh katalog produk provider.
     *
     * @return ProviderProductData[]
     */
    public function fetchProducts(): array;

    /** Saldo deposit milik merchant di sisi provider. */
    public function balance(): float;

    /** Verifikasi keaslian webhook (signature/IP). */
    public function verifyWebhook(string $payload, array $headers): bool;

    /** Terjemahkan payload webhook menjadi hasil transaksi standar. */
    public function parseWebhook(array $payload): TopupResultData;

    /** Ambil ref_id transaksi kita dari payload webhook. */
    public function webhookRefId(array $payload): ?string;
}
