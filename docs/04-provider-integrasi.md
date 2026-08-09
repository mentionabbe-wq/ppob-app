# 4. Integrasi Provider PPOB

## 4.1 Kontrak Tunggal

Seluruh penyedia mengimplementasi `App\Services\Providers\Contracts\PpobProviderInterface`:

```php
public function code(): string;
public function topup(Transaction $t): TopupResultData;
public function checkStatus(Transaction $t): TopupResultData;
public function inquiry(Transaction|string $sku, string $customerNo = ''): InquiryResultData;
public function fetchProducts(): array;          // ProviderProductData[]
public function balance(): float;
public function verifyWebhook(string $payload, array $headers): bool;
public function parseWebhook(array $payload): TopupResultData;
public function webhookRefId(array $payload): ?string;
```

Karena service layer hanya berbicara dengan antarmuka ini, mengganti provider **tidak menyentuh** controller, service transaksi, maupun aplikasi Flutter.

## 4.2 Menambah Provider Baru (3 langkah)

**1. Buat kelas driver** — turunkan dari `AbstractProvider` agar dapat HTTP retry, `api_logs`, dan penyamaran field sensitif secara gratis:

```php
// app/Services/Providers/ApigamesProvider.php
class ApigamesProvider extends AbstractProvider
{
    public function topup(Transaction $transaction): TopupResultData
    {
        $body = $this->request('POST', '/v1/transaksi', [
            'merchant' => $this->provider->credential('merchant_id'),
            'secret'   => $this->provider->credential('secret'),
            'produk'   => $transaction->product->provider_sku,
            'tujuan'   => $transaction->customer_no,
            'reff_id'  => $transaction->ref_id,
        ], $transaction);

        return match ((string) data_get($body, 'data.status')) {
            'Sukses' => TopupResultData::success(data_get($body, 'data.sn'), ...),
            'Gagal'  => TopupResultData::failed(data_get($body, 'data.message'), ...),
            default  => TopupResultData::pending(),
        };
    }

    // checkStatus, inquiry, fetchProducts, balance, verifyWebhook, parseWebhook, webhookRefId
}
```

**2. Daftarkan driver** di `ProviderManager::$drivers` (atau saat runtime lewat `$manager->extend('apigames', ApigamesProvider::class)`).

**3. Tambahkan satu baris di tabel `providers`** lewat panel admin (Pengaturan → Provider), isi base URL dan kredensial. Kredensial tersimpan **terenkripsi** di `credentials_encrypted`.

Setelah itu jalankan `Sinkronkan Katalog Provider` di halaman Produk.

## 4.3 Digiflazz

| Aspek | Nilai |
|---|---|
| Base URL | `https://api.digiflazz.com/v1` |
| Signature | `md5(username + apiKey + refId)` untuk transaksi, `+ "depo"` untuk saldo, `+ "pricelist"` untuk katalog |
| Endpoint | `/transaction`, `/price-list`, `/cek-saldo` |
| Webhook | Header `X-Hub-Signature: sha1=<hmac_sha1(payload, secret)>` |
| Status | `Sukses` → success, `Gagal` → failed, `Pending` → processing |

Kredensial: `username`, `api_key`, `webhook_secret`. Aktifkan mode uji dengan `DIGIFLAZZ_TESTING=true`.

## 4.4 VIP Reseller

| Aspek | Nilai |
|---|---|
| Base URL | `https://vip-reseller.co.id/api` |
| Signature | `md5(apiId + apiKey)` |
| Endpoint | `/prepaid` (`type=order|status|services`), `/postpaid`, `/profile` |
| Status | `success`, `error`/`failed`, selain itu dianggap pending |

Kredensial: `api_id`, `api_key`.

## 4.5 Keandalan

| Risiko | Penanganan |
|---|---|
| Provider timeout | Retry 2× (jeda 1,5 detik) di `AbstractProvider::http()` |
| Job gagal permanen | Status dibiarkan `processing`; **tidak** langsung refund |
| Transaksi menggantung | `SyncPendingTransactionsJob` tiap 5 menit menanyakan status |
| Menggantung > 24 jam | Refund otomatis (`ppob.transaction.auto_refund_after_hours`) |
| Webhook ganda | Replay guard: hash payload disimpan 24 jam; status final tidak ditimpa |
| Webhook palsu | IP allowlist + verifikasi signature per provider |
| Balapan webhook vs respons langsung | `lockForUpdate()` pada baris transaksi di `applyResult()` |

Prinsipnya: **jangan pernah refund atas ketidaktahuan.** Refund hanya dilakukan bila provider secara eksplisit menyatakan gagal, atau setelah batas waktu rekonsiliasi terlewati.

## 4.6 Penyesuaian Harga

`SyncProviderProductsJob` memperbarui `base_price` dan `is_available` dari provider, lalu **menghitung ulang** `sell_price` dari margin yang ditetapkan admin (`margin_type` + `margin_value`). Harga jual tidak pernah ditulis manual, sehingga tidak mungkin ada produk yang dijual di bawah modal saat harga provider naik.
