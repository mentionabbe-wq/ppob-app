<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // Cegah lazy loading tak sengaja (N+1) di luar produksi.
        Model::preventLazyLoading(! app()->isProduction());
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::unguard(false);

        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        // Kuota umum API per user (atau per IP untuk tamu).
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Endpoint autentikasi lebih ketat: rawan brute force.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(5)->by($request->ip()),
            Limit::perMinute(5)->by(strtolower((string) $request->input('email'))),
        ]);

        // Pembelian: batasi agar bot tidak menguras saldo.
        RateLimiter::for('transaction', fn (Request $request) => Limit::perMinute(20)
            ->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('otp', fn (Request $request) => Limit::perMinutes(10, 3)
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));

        // Webhook provider bisa datang bertubi-tubi saat rekonsiliasi.
        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(300)->by($request->ip()));
    }
}
