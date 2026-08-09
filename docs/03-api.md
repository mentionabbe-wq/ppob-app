# 3. REST API v1

Base URL: `{APP_URL}/api/v1` · Auth: `Authorization: Bearer <JWT>` · Dokumentasi interaktif: `/api/documentation`

## 3.1 Bentuk Respons

Sukses:

```json
{ "success": true, "message": "Berhasil.", "data": { }, "meta": { } }
```

Gagal:

```json
{
  "success": false,
  "code": "INSUFFICIENT_BALANCE",
  "message": "Saldo tidak mencukupi. Saldo tersedia Rp5.000, dibutuhkan Rp11.000.",
  "errors": { "field": ["pesan"] }
}
```

Kode galat: `VALIDATION_ERROR` (422), `UNAUTHENTICATED` (401), `ACCOUNT_INACTIVE` (403), `NOT_FOUND` (404), `INSUFFICIENT_BALANCE` (422), `BILL_NOT_FOUND` (404), `PROVIDER_ERROR` (502), `SERVER_ERROR` (500).

## 3.2 Daftar Endpoint

### Autentikasi (`throttle:auth` — 5/menit per IP & per email)

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/auth/register` | Registrasi + kirim OTP email |
| POST | `/auth/login` | Login, mengembalikan JWT |
| POST | `/auth/google` | Login dengan Google ID token |
| POST | `/auth/otp/send` | Kirim ulang OTP (`throttle:otp` 3/10 menit) |
| POST | `/auth/otp/verify` | Verifikasi email |
| POST | `/auth/password/forgot` | Minta OTP reset kata sandi |
| POST | `/auth/password/reset` | Reset kata sandi dengan OTP |
| GET | `/auth/me` 🔒 | Profil pengguna aktif |
| POST | `/auth/refresh` 🔒 | Perbarui token |
| POST | `/auth/logout` 🔒 | Logout & hapus FCM token |

### Katalog (publik)

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/categories` | Kategori aktif + subkategori (cache 10 menit) |
| GET | `/categories/{slug}/brands` | Daftar operator dalam kategori |
| GET | `/products` | Filter: `category_slug`, `brand`, `search` |
| GET | `/products/{id}` | Detail produk |
| GET | `/products/detect-operator?phone=` | Deteksi operator dari prefiks |
| POST | `/products/inquiry` 🔒 | Cek tagihan pascabayar |

### Dashboard, Transaksi, Deposit, Profil, Notifikasi 🔒

| Method | Endpoint | Keterangan |
|---|---|---|
| GET | `/dashboard` | Saldo, ringkasan, banner, promo, menu, riwayat |
| GET | `/transactions` | Riwayat (paginated) |
| POST | `/transactions` | Beli produk (`throttle:transaction` 20/menit) → **202** |
| GET | `/transactions/{id}` | Detail |
| GET | `/transactions/{id}/status` | Status realtime (polling) |
| GET | `/transactions/{id}/invoice` | Invoice PDF |
| GET | `/deposits/methods` | Metode & kanal pembayaran |
| GET/POST | `/deposits` | Riwayat / ajukan deposit |
| GET | `/deposits/{id}` | Detail + instruksi bayar |
| POST | `/deposits/{id}/proof` | Unggah bukti transfer |
| PUT | `/profile` | Perbarui profil (multipart untuk avatar) |
| PUT | `/profile/password` | Ganti kata sandi |
| PUT | `/profile/pin` | Atur PIN transaksi |
| PUT | `/profile/fcm-token` | Perbarui token push |
| GET | `/profile/mutations` | Mutasi saldo |
| GET/POST/DELETE | `/profile/bank-accounts` | Kelola rekening |
| GET | `/notifications` | Daftar notifikasi |
| GET | `/notifications/unread-count` | Jumlah belum dibaca |
| PUT | `/notifications/{id}/read`, `/notifications/read-all` | Tandai dibaca |

### Webhook (server-to-server, tanpa JWT)

| Method | Endpoint | Proteksi |
|---|---|---|
| POST | `/webhooks/providers/{provider}` | IP allowlist + signature HMAC + replay guard 24 jam |
| POST | `/webhooks/payment` | Signature SHA-512 payment gateway |

## 3.3 Idempotency Pembelian

`POST /transactions` menerima `ref_id` dari klien (aplikasi Flutter membuatnya sekali per lembar checkout). Permintaan berulang dengan `ref_id` sama **mengembalikan transaksi yang sudah ada**, bukan membuat yang baru — jaringan putus saat menekan "Bayar" tidak menggandakan potongan saldo.

## 3.4 Alur Pembelian dari Sisi Klien

```
POST /transactions            → 202 Accepted, status "pending"
GET  /transactions/{id}/status → polling tiap 5 detik (maks ±2 menit)
Push FCM                       → status final juga dikirim via notifikasi
```

## 3.5 Catatan Kebijakan Data

`base_price` (harga modal) dan `profit` **hanya dikembalikan** untuk akun ber-role `reseller`, `finance`, `admin`, atau `super-admin`. Spesifikasi awal meminta ketiga angka (modal, jual, untung) tampil di detail transaksi; menampilkan harga modal ke pengguna ritel berarti membuka margin terhadap provider kepada publik, jadi field-nya dibatasi per-role. Untuk membuka bagi seluruh pengguna, hapus penjagaan `$showMargin` di `app/Http/Resources/TransactionResource.php`.

## 3.6 Rate Limit

| Nama | Batas | Cakupan |
|---|---|---|
| `api` | 60/menit | per user (atau IP untuk tamu) |
| `auth` | 5/menit | per IP **dan** per email |
| `otp` | 3/10 menit | per email+IP |
| `transaction` | 20/menit | per user |
| `webhook` | 300/menit | per IP |
