<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OTP
    |--------------------------------------------------------------------------
    */
    'otp' => [
        'ttl_minutes' => (int) env('PPOB_OTP_TTL_MINUTES', 10),
        'max_attempt' => (int) env('PPOB_OTP_MAX_ATTEMPT', 5),
        'length' => 6,
        'resend_cooldown_seconds' => 60,
    ],

    /*
    |--------------------------------------------------------------------------
    | Deposit
    |--------------------------------------------------------------------------
    */
    'deposit' => [
        'min' => (float) env('PPOB_DEPOSIT_MIN', 10_000),
        'max' => (float) env('PPOB_DEPOSIT_MAX', 10_000_000),
        'expire_hours' => (int) env('PPOB_DEPOSIT_EXPIRE_HOURS', 24),
        'auto_approve' => (bool) env('PPOB_AUTO_APPROVE_DEPOSIT', false),
        'use_unique_code' => true,
        'methods' => ['bank_transfer', 'virtual_account', 'qris', 'ewallet'],
        'banks' => [
            ['code' => 'bca', 'name' => 'BCA', 'account_number' => '1234567890', 'account_name' => 'PT PPOB Nusantara'],
            ['code' => 'bni', 'name' => 'BNI', 'account_number' => '0987654321', 'account_name' => 'PT PPOB Nusantara'],
            ['code' => 'mandiri', 'name' => 'Mandiri', 'account_number' => '1122334455', 'account_name' => 'PT PPOB Nusantara'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Harga & Margin Default
    |--------------------------------------------------------------------------
    | Dipakai saat produk baru tersinkron dari provider dan admin belum
    | menetapkan margin khusus.
    */
    'pricing' => [
        'default_margin_type' => env('PPOB_DEFAULT_MARGIN_TYPE', 'fixed'),
        'default_margin_value' => (float) env('PPOB_DEFAULT_MARGIN_VALUE', 1_000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaksi
    |--------------------------------------------------------------------------
    */
    'transaction' => [
        'stale_after_minutes' => 15,
        'auto_refund_after_hours' => 24,
        'max_retry' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | Provider PPOB
    |--------------------------------------------------------------------------
    | Nilai di sini adalah fallback. Kredensial produksi sebaiknya
    | disimpan terenkripsi pada kolom providers.credentials_encrypted.
    */
    'providers' => [
        'digiflazz' => [
            'enabled' => (bool) env('DIGIFLAZZ_ENABLED', true),
            'base_url' => env('DIGIFLAZZ_BASE_URL', 'https://api.digiflazz.com/v1'),
            'username' => env('DIGIFLAZZ_USERNAME'),
            'api_key' => env('DIGIFLAZZ_API_KEY'),
            'webhook_secret' => env('DIGIFLAZZ_WEBHOOK_SECRET'),
            'allowed_ips' => array_filter(explode(',', (string) env('DIGIFLAZZ_ALLOWED_IPS'))),
            'testing' => (bool) env('DIGIFLAZZ_TESTING', false),
        ],
        'vipreseller' => [
            'enabled' => (bool) env('VIPRESELLER_ENABLED', false),
            'base_url' => env('VIPRESELLER_BASE_URL', 'https://vip-reseller.co.id/api'),
            'api_id' => env('VIPRESELLER_API_ID'),
            'api_key' => env('VIPRESELLER_API_KEY'),
            'allowed_ips' => array_filter(explode(',', (string) env('VIPRESELLER_ALLOWED_IPS'))),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Payment Gateway (deposit VA/QRIS/e-wallet)
    |--------------------------------------------------------------------------
    */
    'payment' => [
        'gateway' => env('PAYMENT_GATEWAY', 'midtrans'),
        'midtrans' => [
            'server_key' => env('MIDTRANS_SERVER_KEY'),
            'client_key' => env('MIDTRANS_CLIENT_KEY'),
            'is_production' => (bool) env('MIDTRANS_IS_PRODUCTION', false),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Push Notification (FCM)
    |--------------------------------------------------------------------------
    */
    'fcm' => [
        'project_id' => env('FCM_PROJECT_ID'),
        'credentials_path' => env('FCM_CREDENTIALS_PATH'),
    ],

];
