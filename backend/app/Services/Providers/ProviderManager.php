<?php

declare(strict_types=1);

namespace App\Services\Providers;

use App\Exceptions\ProviderException;
use App\Models\Provider;
use App\Models\Transaction;
use App\Services\Providers\Contracts\PpobProviderInterface;
use Illuminate\Support\Collection;

/**
 * Pabrik + registry provider PPOB.
 *
 * Menambah provider baru cukup dengan:
 *   1. Buat kelas implementasi PpobProviderInterface.
 *   2. Daftarkan di ProviderManager::$drivers (atau via extend()).
 *   3. Insert satu baris ke tabel `providers`.
 */
class ProviderManager
{
    /** @var array<string, class-string<PpobProviderInterface>> */
    protected array $drivers = [
        'digiflazz' => DigiflazzProvider::class,
        'vipreseller' => VipResellerProvider::class,
    ];

    /** @var array<string, PpobProviderInterface> */
    protected array $resolved = [];

    /** Daftarkan driver kustom saat runtime (mis. dari package pihak ketiga). */
    public function extend(string $code, string $driverClass): void
    {
        $this->drivers[$code] = $driverClass;
    }

    public function driver(string $code): PpobProviderInterface
    {
        if (isset($this->resolved[$code])) {
            return $this->resolved[$code];
        }

        $model = Provider::where('code', $code)->first();

        if ($model === null) {
            throw new ProviderException("Provider '{$code}' belum terdaftar di database.");
        }

        return $this->resolved[$code] = $this->make($model);
    }

    public function make(Provider $model): PpobProviderInterface
    {
        $class = $this->drivers[$model->code] ?? null;

        if ($class === null) {
            throw new ProviderException("Driver untuk provider '{$model->code}' tidak tersedia.");
        }

        return new $class($model);
    }

    /** Provider yang menangani sebuah transaksi (mengikuti produk). */
    public function forTransaction(Transaction $transaction): PpobProviderInterface
    {
        $model = $transaction->provider ?? $transaction->product->provider;

        if ($model === null || ! $model->is_active) {
            throw new ProviderException('Provider untuk produk ini sedang tidak aktif.');
        }

        return $this->make($model);
    }

    /** @return Collection<int, PpobProviderInterface> */
    public function active(): Collection
    {
        return Provider::active()
            ->get()
            ->filter(fn (Provider $p) => isset($this->drivers[$p->code]))
            ->map(fn (Provider $p) => $this->make($p))
            ->values();
    }

    /** @return array<string, class-string<PpobProviderInterface>> */
    public function drivers(): array
    {
        return $this->drivers;
    }
}
