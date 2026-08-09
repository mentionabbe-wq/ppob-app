<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DepositController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\TransactionController;
use App\Http\Controllers\Admin\UserController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/admin');

Route::prefix('admin')->name('admin.')->group(function () {

    Route::middleware('guest')->group(function () {
        Route::get('login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('login', [AuthController::class, 'login'])->middleware('throttle:10,1');
    });

    Route::middleware(['auth', 'admin'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');

        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        // ── Transaksi ───────────────────────────────────────
        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionController::class, 'index'])->name('index');
            Route::get('export/excel', [TransactionController::class, 'exportExcel'])->name('export.excel');
            Route::get('export/pdf', [TransactionController::class, 'exportPdf'])->name('export.pdf');
            Route::get('{transaction}', [TransactionController::class, 'show'])->name('show');

            Route::middleware('role:super-admin|admin|finance')->group(function () {
                Route::post('{transaction}/refund', [TransactionController::class, 'refund'])->name('refund');
                Route::post('{transaction}/resend', [TransactionController::class, 'resend'])->name('resend');
            });

            Route::post('{transaction}/sync', [TransactionController::class, 'sync'])->name('sync');
        });

        // ── Deposit ─────────────────────────────────────────
        Route::prefix('deposits')->name('deposits.')->group(function () {
            Route::get('/', [DepositController::class, 'index'])->name('index');
            Route::get('{deposit}', [DepositController::class, 'show'])->name('show');

            Route::middleware('role:super-admin|admin|finance')->group(function () {
                Route::post('{deposit}/approve', [DepositController::class, 'approve'])->name('approve');
                Route::post('{deposit}/reject', [DepositController::class, 'reject'])->name('reject');
            });
        });

        // ── Produk ──────────────────────────────────────────
        Route::prefix('products')->name('products.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('{product}/edit', [ProductController::class, 'edit'])->name('edit');
            Route::put('{product}', [ProductController::class, 'update'])->name('update');
            Route::post('{product}/toggle', [ProductController::class, 'toggle'])->name('toggle');
            Route::post('bulk-margin', [ProductController::class, 'bulkMargin'])->name('bulk-margin');
            Route::post('sync', [ProductController::class, 'sync'])->name('sync');
        });

        // ── Pengguna ────────────────────────────────────────
        Route::prefix('users')->name('users.')->group(function () {
            Route::get('/', [UserController::class, 'index'])->name('index');
            Route::get('{user}', [UserController::class, 'show'])->name('show');
            Route::put('{user}', [UserController::class, 'update'])->middleware('role:super-admin|admin')->name('update');
            Route::post('{user}/balance', [UserController::class, 'adjustBalance'])
                ->middleware('role:super-admin|finance')
                ->name('balance');
        });

        // ── Laporan ─────────────────────────────────────────
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');

        // ── Pengaturan ──────────────────────────────────────
        Route::middleware('role:super-admin|admin')->prefix('settings')->name('settings.')->group(function () {
            Route::get('/', [SettingController::class, 'index'])->name('index');
            Route::put('/', [SettingController::class, 'update'])->name('update');
            Route::put('providers/{provider}', [SettingController::class, 'updateProvider'])->name('providers.update');
            Route::get('banners', [SettingController::class, 'banners'])->name('banners');
            Route::post('banners', [SettingController::class, 'storeBanner'])->name('banners.store');
            Route::delete('banners/{banner}', [SettingController::class, 'destroyBanner'])->name('banners.destroy');
            Route::get('promos', [SettingController::class, 'promos'])->name('promos');
            Route::post('promos', [SettingController::class, 'storePromo'])->name('promos.store');
            Route::get('activity-logs', [SettingController::class, 'activityLogs'])->name('logs');
        });
    });
});
