<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DepositController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\TransactionController;
use App\Http\Controllers\Api\V1\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API v1
|--------------------------------------------------------------------------
| Seluruh endpoint memakai prefix /api/v1. Versi berikutnya cukup
| menambah grup /v2 tanpa mengganggu klien lama.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {

    // ── Publik ──────────────────────────────────────────────
    Route::prefix('auth')->name('auth.')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('register');
        Route::post('login', [AuthController::class, 'login'])->name('login');
        Route::post('google', [AuthController::class, 'google'])->name('google');
        Route::post('password/forgot', [AuthController::class, 'forgotPassword'])->name('password.forgot');
        Route::post('password/reset', [AuthController::class, 'resetPassword'])->name('password.reset');

        Route::middleware('throttle:otp')->group(function () {
            Route::post('otp/send', [AuthController::class, 'sendOtp'])->name('otp.send');
            Route::post('otp/verify', [AuthController::class, 'verifyOtp'])->name('otp.verify');
        });
    });

    // Katalog dapat diakses tanpa login agar app bisa menampilkan
    // harga sebelum user mendaftar.
    Route::middleware('throttle:api')->group(function () {
        Route::get('categories', [ProductController::class, 'categories'])->name('categories.index');
        Route::get('categories/{category}/brands', [ProductController::class, 'brands'])->name('categories.brands');
        Route::get('products', [ProductController::class, 'index'])->name('products.index');
        Route::get('products/detect-operator', [ProductController::class, 'detectOperator'])->name('products.detect');
        Route::get('products/{product}', [ProductController::class, 'show'])->name('products.show');
    });

    // ── Webhook (server-to-server) ──────────────────────────
    Route::prefix('webhooks')->name('webhooks.')->middleware('throttle:webhook')->group(function () {
        // Kode provider diambil middleware dari parameter rute {provider}.
        Route::post('providers/{provider}', [WebhookController::class, 'provider'])
            ->middleware('webhook.signature')
            ->name('provider');

        Route::post('payment', [WebhookController::class, 'payment'])->name('payment');
    });

    // ── Terautentikasi ──────────────────────────────────────
    Route::middleware(['auth:api', 'active', 'throttle:api'])->group(function () {

        Route::prefix('auth')->name('auth.')->group(function () {
            Route::get('me', [AuthController::class, 'me'])->name('me');
            Route::post('refresh', [AuthController::class, 'refresh'])->name('refresh');
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        });

        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

        Route::post('products/inquiry', [ProductController::class, 'inquiry'])->name('products.inquiry');

        Route::prefix('transactions')->name('transactions.')->group(function () {
            Route::get('/', [TransactionController::class, 'index'])->name('index');
            Route::post('/', [TransactionController::class, 'store'])
                ->middleware('throttle:transaction')
                ->name('store');
            Route::get('{transaction}', [TransactionController::class, 'show'])->name('show');
            Route::get('{transaction}/status', [TransactionController::class, 'status'])->name('status');
            Route::get('{transaction}/invoice', [TransactionController::class, 'invoice'])->name('invoice');
        });

        Route::prefix('deposits')->name('deposits.')->group(function () {
            Route::get('methods', [DepositController::class, 'methods'])->name('methods');
            Route::get('/', [DepositController::class, 'index'])->name('index');
            Route::post('/', [DepositController::class, 'store'])->name('store');
            Route::get('{deposit}', [DepositController::class, 'show'])->name('show');
            Route::post('{deposit}/proof', [DepositController::class, 'uploadProof'])->name('proof');
        });

        Route::prefix('profile')->name('profile.')->group(function () {
            Route::put('/', [ProfileController::class, 'update'])->name('update');
            Route::put('password', [ProfileController::class, 'changePassword'])->name('password');
            Route::put('pin', [ProfileController::class, 'setPin'])->name('pin');
            Route::put('fcm-token', [ProfileController::class, 'updateFcmToken'])->name('fcm');
            Route::get('mutations', [ProfileController::class, 'mutations'])->name('mutations');
            Route::get('bank-accounts', [ProfileController::class, 'bankAccounts'])->name('banks.index');
            Route::post('bank-accounts', [ProfileController::class, 'storeBankAccount'])->name('banks.store');
            Route::delete('bank-accounts/{bankAccount}', [ProfileController::class, 'destroyBankAccount'])->name('banks.destroy');
        });

        Route::prefix('notifications')->name('notifications.')->group(function () {
            Route::get('/', [NotificationController::class, 'index'])->name('index');
            Route::get('unread-count', [NotificationController::class, 'unreadCount'])->name('unread');
            Route::put('read-all', [NotificationController::class, 'markAllAsRead'])->name('read.all');
            Route::put('{notification}/read', [NotificationController::class, 'markAsRead'])->name('read');
        });
    });
});
