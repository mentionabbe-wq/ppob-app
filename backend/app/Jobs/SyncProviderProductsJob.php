<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Provider;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Providers\ProviderManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Menarik katalog produk dari provider dan menyinkronkannya ke
 * tabel products. Inilah alasan produk baru bisa muncul di aplikasi
 * mobile tanpa perlu update aplikasi.
 */
class SyncProviderProductsJob implements ShouldQueue
{
    use Queueable;

    public int $timeout = 300;

    public function __construct(public readonly ?int $providerId = null)
    {
        $this->onQueue('maintenance');
    }

    public function handle(ProviderManager $manager, ProductRepositoryInterface $products): void
    {
        $models = $this->providerId !== null
            ? Provider::whereKey($this->providerId)->get()
            : Provider::active()->get();

        foreach ($models as $model) {
            try {
                $driver = $manager->make($model);
                $items = $driver->fetchProducts();

                $result = $products->syncFromProvider($model->id, ...$items);

                $model->update([
                    'products_synced_at' => now(),
                    'balance' => $driver->balance(),
                    'balance_synced_at' => now(),
                ]);

                Log::info('Sinkronisasi produk provider selesai', [
                    'provider' => $model->code,
                    'total' => count($items),
                ] + $result);
            } catch (\Throwable $e) {
                Log::error('Sinkronisasi produk provider gagal', [
                    'provider' => $model->code,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
