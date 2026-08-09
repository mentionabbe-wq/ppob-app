<?php

declare(strict_types=1);

use App\Jobs\SyncPendingTransactionsJob;
use App\Jobs\SyncProviderProductsJob;
use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Services\DepositService;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Penjadwalan
|--------------------------------------------------------------------------
*/

// Rekonsiliasi transaksi menggantung tiap 5 menit.
Schedule::job(new SyncPendingTransactionsJob)->everyFiveMinutes()->withoutOverlapping();

// Sinkronisasi katalog & saldo provider tiap jam.
Schedule::job(new SyncProviderProductsJob)->hourly()->withoutOverlapping();

// Tandai deposit yang lewat masa berlaku.
Schedule::call(function (DepositRepositoryInterface $deposits, DepositService $service) {
    foreach ($deposits->expired() as $deposit) {
        $service->expire($deposit);
    }
})->everyTenMinutes()->name('expire-deposits')->withoutOverlapping();

// Bersihkan log API lama agar tabel tidak membengkak (retensi 90 hari).
Schedule::call(fn () => App\Models\ApiLog::where('created_at', '<', now()->subDays(90))->limit(10_000)->delete())
    ->dailyAt('03:00')
    ->name('prune-api-logs');

Schedule::command('queue:prune-failed --hours=336')->daily();
Schedule::command('horizon:snapshot')->everyFiveMinutes();
