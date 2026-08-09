# 2. ERD & Struktur Database

## 2.1 Diagram Relasi

```mermaid
erDiagram
    users ||--o| wallets : "punya"
    users ||--o{ transactions : "melakukan"
    users ||--o{ deposits : "mengajukan"
    users ||--o{ notifications : "menerima"
    users ||--o{ bank_accounts : "mendaftarkan"
    users ||--o{ wallet_mutations : "mencatat"
    users ||--o{ activity_logs : "menghasilkan"
    users }o--o{ roles : "model_has_roles"
    roles }o--o{ permissions : "role_has_permissions"

    categories ||--o{ products : "mengelompokkan"
    providers  ||--o{ products : "menyediakan"
    providers  ||--o{ api_logs : "dipanggil"
    products   ||--o{ transactions : "dibeli"
    transactions ||--o{ api_logs : "menghasilkan"
    wallets    ||--o{ wallet_mutations : "berubah"
    deposits   ||--o| wallet_mutations : "menambah"
    promos     ||--o{ transactions : "mendiskon"
    users      ||--o{ voucher_redemptions : "menukar"
    vouchers   ||--o{ voucher_redemptions : "ditukar"

    users {
        bigint id PK
        string name
        string email UK
        string phone UK
        timestamp email_verified_at
        string password
        enum status
        string pin_hash
        string fcm_token
        string referral_code UK
        bigint referred_by FK
        timestamp last_login_at
        string last_login_ip
    }
    wallets {
        bigint id PK
        bigint user_id FK UK
        decimal balance
        decimal locked_balance
        string currency
        int version
    }
    wallet_mutations {
        bigint id PK
        bigint wallet_id FK
        bigint user_id FK
        enum type
        decimal amount
        decimal balance_before
        decimal balance_after
        string reference_type
        bigint reference_id
        string description
    }
    categories {
        bigint id PK
        string name
        string slug UK
        string icon
        string type
        bigint parent_id FK
        boolean is_active
        int sort_order
    }
    providers {
        bigint id PK
        string name
        string code UK
        string base_url
        text credentials_encrypted
        boolean is_active
        int priority
        decimal balance
    }
    products {
        bigint id PK
        bigint category_id FK
        bigint provider_id FK
        string sku UK
        string provider_sku
        string name
        string brand
        string type
        decimal base_price
        decimal sell_price
        decimal margin_value
        enum margin_type
        boolean is_active
        boolean is_available
        text description
    }
    transactions {
        bigint id PK
        string invoice_no UK
        string ref_id UK
        bigint user_id FK
        bigint product_id FK
        bigint provider_id FK
        string customer_no
        string customer_name
        decimal base_price
        decimal sell_price
        decimal discount
        decimal admin_fee
        decimal total_paid
        decimal profit
        enum status
        string serial_number
        string provider_ref
        text provider_message
        timestamp paid_at
        timestamp completed_at
        timestamp refunded_at
    }
    deposits {
        bigint id PK
        string code UK
        bigint user_id FK
        decimal amount
        int unique_code
        decimal total_amount
        enum method
        string channel
        string va_number
        string qris_payload
        string proof_path
        enum status
        bigint approved_by FK
        timestamp expired_at
        timestamp paid_at
    }
    api_logs {
        bigint id PK
        bigint provider_id FK
        bigint transaction_id FK
        string endpoint
        string method
        json request_payload
        json response_payload
        int http_code
        int duration_ms
        string direction
    }
    notifications {
        bigint id PK
        bigint user_id FK
        string type
        string title
        text body
        json data
        timestamp read_at
    }
    banners {
        bigint id PK
        string title
        string image_path
        string action_type
        string action_value
        boolean is_active
        date start_date
        date end_date
        int sort_order
    }
    promos {
        bigint id PK
        string code UK
        string title
        enum discount_type
        decimal discount_value
        decimal max_discount
        decimal min_transaction
        int quota
        int used
        date start_date
        date end_date
        boolean is_active
    }
    vouchers {
        bigint id PK
        bigint promo_id FK
        string code UK
        bigint user_id FK
        timestamp used_at
    }
    settings {
        bigint id PK
        string key UK
        text value
        string group
        string type
    }
    bank_accounts {
        bigint id PK
        bigint user_id FK
        string bank_name
        string account_number
        string account_name
        boolean is_primary
    }
    activity_logs {
        bigint id PK
        bigint user_id FK
        string action
        string subject_type
        bigint subject_id
        json properties
        string ip_address
        string user_agent
    }
```

## 2.2 Catatan Desain

| Keputusan | Alasan |
|---|---|
| `decimal(20,2)` untuk seluruh nominal | Menghindari galat floating point pada uang |
| `wallet_mutations` sebagai ledger append-only | Saldo dapat direkonsiliasi ulang; audit finansial |
| `balance_before` & `balance_after` disimpan | Deteksi anomali tanpa replay seluruh ledger |
| `ref_id` unik dari client | Idempotency — retry jaringan tidak menggandakan transaksi |
| `credentials_encrypted` pada `providers` | API key provider dienkripsi (`Crypt::encryptString`) |
| `products.provider_sku` terpisah dari `sku` | SKU internal stabil walau provider berganti |
| Produk baru cukup insert row | Aplikasi mobile menarik katalog dinamis — tanpa update APK |
| `unique_code` pada deposit transfer bank | Rekonsiliasi manual mutasi bank |
| Soft delete pada users/products/transactions | Kepatuhan audit; data finansial tak boleh hilang |
| Index komposit `(user_id, status, created_at)` | Query riwayat transaksi & laporan cepat |

## 2.3 Enum Status

**transactions.status**: `pending` → `processing` → `success` | `failed` | `refunded` | `canceled`

**deposits.status**: `pending` → `waiting_payment` → `paid` → `approved` | `rejected` | `expired`

**wallet_mutations.type**: `deposit`, `purchase`, `refund`, `adjustment`, `bonus`, `withdrawal`

## 2.4 Ringkasan Tabel

| Tabel | Fungsi |
|---|---|
| `users` | Akun pengguna & reseller |
| `wallets` | Saldo per user (1:1) |
| `wallet_mutations` | Buku besar pergerakan saldo |
| `categories` | Kategori/subkategori produk |
| `providers` | Penyedia API PPOB |
| `products` | Katalog produk digital |
| `transactions` | Transaksi pembelian |
| `deposits` | Pengisian saldo |
| `api_logs` | Log request/response provider & webhook |
| `notifications` | Notifikasi in-app |
| `banners` | Banner dashboard |
| `promos` / `vouchers` | Diskon & kode voucher |
| `settings` | Konfigurasi dinamis (key-value) |
| `bank_accounts` | Rekening milik user |
| `activity_logs` | Audit log |
| `roles`, `permissions`, `model_has_roles`, `role_has_permissions` | RBAC (Spatie) |
