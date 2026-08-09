# 5. Testing

## 5.1 Menjalankan

```bash
docker compose exec app php artisan test
```

```bash
cd mobile && flutter test
```

## 5.2 Cakupan Backend

| Berkas | Yang diuji |
|---|---|
| `tests/Unit/PricingServiceTest.php` | Margin tetap & persen, pembulatan, batas diskon promo, perhitungan laba |
| `tests/Feature/TransactionPurchaseTest.php` | Potong saldo + ledger, saldo kurang (rollback penuh), idempotency `ref_id`, produk kosong, PIN salah |
| `tests/Feature/TransactionRefundTest.php` | Refund otomatis saat provider gagal, refund idempoten, status final tidak ditimpa webhook telat |
| `tests/Feature/DepositApprovalTest.php` | Kode unik transfer, approve tepat sekali, batas nominal, penolakan tidak menambah saldo |
| `tests/Feature/AuthApiTest.php` | Registrasi + dompet + OTP, login JWT, akun suspended, endpoint terproteksi, forgot-password tidak membocorkan email |

Kasus paling kritis dan alasannya:

- **Rollback saat saldo kurang** — memastikan tidak ada transaksi "hantu" tanpa pembayaran.
- **Idempotency `ref_id`** — mencegah double-charge saat pengguna menekan Bayar dua kali.
- **Status final tidak ditimpa** — webhook yang datang terlambat tidak boleh mengubah transaksi sukses menjadi gagal (dan memicu refund atas transaksi yang benar-benar terkirim).
- **Approve deposit idempoten** — dua admin menekan Setujui bersamaan tidak menggandakan saldo.

## 5.3 Cakupan Flutter

`test/formatter_test.dart` — format rupiah, penyamaran nomor, parser JSON longgar (angka string vs numerik), pemetaan status transaksi, dan pemastian `base_price` tetap `null` bila API tidak mengirimnya.

## 5.4 Catatan Lingkungan Uji

- Uji berjalan di **SQLite in-memory** (`phpunit.xml`) agar cepat.
- Query laporan yang memakai `DATE_FORMAT` (`monthlySeries`) khusus MySQL — uji untuk laporan bulanan harus dijalankan dengan `DB_CONNECTION=mysql`.
- `Queue::fake()` dipakai pada uji pembelian agar pemanggilan provider tidak benar-benar terjadi.
- `Mail::fake()` dipakai pada uji autentikasi.

## 5.5 Yang Belum Diuji (kandidat berikutnya)

- Endpoint webhook end-to-end (signature valid/tidak valid, replay).
- `SyncProviderProductsJob` terhadap respons provider tiruan.
- Alur panel admin (approve deposit, refund) lewat HTTP session.
- Widget test Flutter untuk `CheckoutSheet` (tombol nonaktif saat saldo kurang).
