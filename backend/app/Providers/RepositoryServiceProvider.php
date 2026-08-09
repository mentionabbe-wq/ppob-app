<?php

declare(strict_types=1);

namespace App\Providers;

use App\Repositories\Contracts\DepositRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\TransactionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\Contracts\WalletRepositoryInterface;
use App\Repositories\Eloquent\DepositRepository;
use App\Repositories\Eloquent\ProductRepository;
use App\Repositories\Eloquent\TransactionRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WalletRepository;
use App\Services\Providers\ProviderManager;
use Illuminate\Support\ServiceProvider;

/**
 * Titik tunggal pemetaan contract → implementasi. Mengganti sumber
 * data (mis. ke repository berbasis API) cukup diubah di sini.
 */
class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    public array $bindings = [
        UserRepositoryInterface::class => UserRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        TransactionRepositoryInterface::class => TransactionRepository::class,
        DepositRepositoryInterface::class => DepositRepository::class,
        WalletRepositoryInterface::class => WalletRepository::class,
    ];

    public function register(): void
    {
        $this->app->singleton(ProviderManager::class);
    }
}
