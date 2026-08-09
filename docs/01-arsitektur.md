# 1. Arsitektur Sistem

## 1.1 Diagram Konteks

```
        ┌────────────────┐        ┌────────────────┐
        │  Flutter App   │        │  Panel Admin   │
        │ (Android/iOS)  │        │ (Blade + TW)   │
        └───────┬────────┘        └────────┬───────┘
                │ HTTPS / JWT               │ Session + CSRF
                ▼                           ▼
        ┌──────────────────────────────────────────┐
        │           Nginx (TLS termination)         │
        └──────────────────┬───────────────────────┘
                           ▼
        ┌──────────────────────────────────────────┐
        │        Laravel 12 (PHP-FPM 8.3)          │
        │  HTTP Layer → Service → Repository       │
        └───┬───────────┬──────────────┬───────────┘
            │           │              │
            ▼           ▼              ▼
      ┌─────────┐  ┌─────────┐   ┌──────────────┐
      │ MySQL 8 │  │  Redis  │   │ Queue Worker │
      │         │  │ cache / │   │  (Horizon)   │
      └─────────┘  │ queue   │   └──────┬───────┘
                   └─────────┘          │
                                        ▼
                     ┌──────────────────────────────────┐
                     │ Provider Gateway (Service Layer) │
                     │  Digiflazz │ VIP Reseller │ ...  │
                     └──────────────────────────────────┘
                                        ▲
                                        │ Webhook (signature verified)
                                        │
                     ┌──────────────────────────────────┐
                     │ Payment Gateway (VA/QRIS/e-wallet)│
                     └──────────────────────────────────┘
```

## 1.2 Prinsip

1. **Clean Architecture** — dependensi selalu mengarah ke dalam (HTTP → Service → Repository Contract ← Eloquent Repository).
2. **Repository Pattern** — controller/service tidak pernah menyentuh Eloquent query builder langsung.
3. **Service Layer** — seluruh aturan bisnis (potong saldo, hitung margin, refund) berada di service, bukan controller.
4. **DTO** — data antar-layer dikirim sebagai objek immutable (`readonly class`), bukan array liar.
5. **Dependency Injection** — binding contract → implementasi di `RepositoryServiceProvider` & `ProviderServiceProvider`.
6. **API Versioning** — prefix `/api/v1`, namespace `App\Http\Controllers\Api\V1`.
7. **Idempotency** — setiap transaksi punya `ref_id` unik (client-generated) untuk mencegah double-charge.

## 1.3 Struktur Direktori Backend

```
backend/app/
├── DTO/                        Objek transfer data immutable
│   ├── TopupRequestData.php
│   ├── TopupResultData.php
│   ├── ProductData.php
│   └── DepositRequestData.php
├── Enums/                      Status & tipe (backed enum PHP 8.1)
│   ├── TransactionStatus.php
│   ├── DepositStatus.php
│   ├── MutationType.php
│   └── ProviderCode.php
├── Exceptions/
│   ├── InsufficientBalanceException.php
│   ├── ProviderException.php
│   └── Handler-nya di bootstrap/app.php
├── Http/
│   ├── Controllers/Api/V1/     AuthController, ProductController, ...
│   ├── Controllers/Admin/      Panel admin
│   ├── Middleware/             EnsureJwtAuth, AdminOnly, VerifyWebhookSignature
│   ├── Requests/               FormRequest (validasi seluruh input)
│   └── Resources/              JsonResource (kontrak respons stabil)
├── Jobs/                       ProcessTopupJob, SyncProviderProductsJob, ...
├── Models/
├── Repositories/
│   ├── Contracts/              Interface
│   └── Eloquent/               Implementasi
├── Services/
│   ├── AuthService.php
│   ├── WalletService.php
│   ├── TransactionService.php
│   ├── DepositService.php
│   ├── PricingService.php
│   ├── NotificationService.php
│   └── Providers/              Provider gateway PPOB
│       ├── Contracts/PpobProviderInterface.php
│       ├── DigiflazzProvider.php
│       ├── VipResellerProvider.php
│       └── ProviderManager.php
└── Support/                    Helper (Money, RefId, Signature)
```

## 1.4 Alur Transaksi Topup (Happy Path)

```
Client                 API                Service              Provider
  │  POST /transactions │                    │                     │
  ├────────────────────►│  validate + auth   │                     │
  │                     ├───────────────────►│ lock wallet (FOR UPDATE)
  │                     │                    │ cek saldo ≥ harga jual
  │                     │                    │ debit saldo + mutasi
  │                     │                    │ create trx (PENDING)
  │                     │                    │ commit DB transaction
  │                     │                    ├────────────────────►│ topup()
  │   202 Accepted      │◄───────────────────┤                     │
  │◄────────────────────┤   dispatch job     │                     │
  │                     │                    │◄────────────────────┤ callback/webhook
  │                     │                    │ SUCCESS → set SN     │
  │                     │                    │ FAILED  → refund     │
  │  FCM push + polling │◄───────────────────┤                     │
```

Kunci konsistensi:
- Debit saldo & pembuatan transaksi berada dalam **satu DB transaction** dengan `lockForUpdate()` pada baris wallet.
- Refund otomatis saat provider mengembalikan status gagal (idempotent — dicek `refunded_at`).
- Setiap panggilan provider dicatat di `api_logs` (request, response, http_code, durasi).

## 1.5 Arsitektur Flutter

```
mobile/lib/
├── core/
│   ├── config/       env, konstanta
│   ├── network/      DioClient, interceptor (JWT + refresh), ApiException
│   ├── theme/        AppTheme (light/dark), AppColors, AppTypography
│   ├── router/       GoRouter + guard auth
│   ├── storage/      SecureStorage (token), Hive (cache)
│   └── widgets/      Komponen reusable
└── features/<fitur>/
    ├── data/         models (freezed), datasources (remote), repository impl
    ├── domain/       entities, repository contract, usecases
    └── presentation/ providers (Riverpod), pages, widgets
```

Aturan: `presentation` → `domain` ← `data`. Widget tidak pernah memanggil Dio langsung.

## 1.6 Keamanan (ringkas, detail di docs/06)

| Ancaman | Mitigasi |
|---|---|
| Credential stuffing | Rate limit login 5/menit per IP+email, lockout |
| Token bocor | JWT TTL 60 menit + refresh token rotatif, disimpan di `flutter_secure_storage` |
| SQL Injection | Eloquent/PDO prepared statement, tanpa raw query tanpa binding |
| XSS | Blade auto-escape, `{!! !!}` dilarang di data user |
| CSRF | Middleware CSRF pada seluruh route web/admin |
| Double spend | `lockForUpdate` + unique `ref_id` |
| Webhook palsu | Verifikasi signature + IP allowlist + replay guard (cache ref_id 24 jam) |
| Brute force OTP | Maks 5 percobaan, OTP hash di DB, kedaluwarsa 10 menit |
| Audit | `activity_logs` mencatat aktor, aksi, IP, user agent, before/after |
