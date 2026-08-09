# 7. Pemasangan di CasaOS

Ada dua cara. **Cara 1 tidak butuh SSH sama sekali** — cukup tempel satu berkas YML.

## 7.1 Cara 1 — tempel YML (disarankan)

Image sudah dibangun otomatis dan tersedia publik di GitHub Container Registry, jadi CasaOS tinggal menariknya.

1. Buka [`casaos-ppob.yml`](https://github.com/mentionabbe-wq/ppob-app/blob/main/casaos-ppob.yml) → klik **Raw** → salin seluruh isinya
2. CasaOS → **Apps** → tombol **+** → **Custom Install**
3. Klik ikon **Import** (pojok kanan atas) → tempel → **Submit**
4. **Ganti dulu dua hal** sebelum Install:
   - `DB_PASSWORD` — muncul di empat tempat (`app`, `queue`, `scheduler`, `mysql`), semuanya harus sama
   - `APP_URL` — ganti `192.168.1.100` dengan IP CasaOS Anda
5. **Install**, tunggu ±2 menit (menarik image + migrasi database)

Buka `http://IP-CASAOS:8081/admin` → login `admin@ppob.test` / `password` → **ganti kata sandinya**.

Migrasi database, pembuatan `APP_KEY` & `JWT_SECRET`, dan pengisian data awal dijalankan sendiri oleh container saat pertama start — tidak ada perintah `artisan` yang perlu Anda ketik.

## 7.2 Cara 2 — lewat SSH (kalau ingin build sendiri)

```bash
sudo git clone https://github.com/mentionabbe-wq/ppob-app.git /DATA/AppData/ppob/src && sudo bash /DATA/AppData/ppob/src/deploy/casaos/install.sh
```

Cara ini membangun image di perangkat Anda sendiri (lebih lama, ±10 menit) dan menyimpan data di `/DATA/AppData/ppob/` sebagai folder biasa, bukan volume Docker. Pilih ini bila ingin memodifikasi kode.

Kalau `git` belum ada:

```bash
sudo apt update && sudo apt install -y git
```

Skrip itu membuat struktur folder, meng-generate `.env` + sandi database acak, membangun image, menjalankan 6 container, lalu menjalankan migrasi & seeder. Selesai dalam 5–10 menit (mayoritas untuk build image).

Akses: `http://<ip-casaos>:8081/admin` — login `admin@ppob.test` / `password` (**ganti segera**).

## 7.2 Menambahkan ke dashboard CasaOS

Agar muncul sebagai ikon aplikasi di UI CasaOS:

1. Buka CasaOS → **Apps** → tombol **+** → **Custom Install**
2. Klik ikon **Import** (pojok kanan atas)
3. Tempel isi [`deploy/casaos/docker-compose.casaos.yml`](../deploy/casaos/docker-compose.casaos.yml)
4. **Install**

Karena container sudah berjalan dengan nama yang sama, CasaOS akan mengadopsinya, bukan membuat duplikat. Blok `x-casaos` di berkas itu yang mengatur nama, ikon, kategori, dan URL yang dibuka saat ikon diklik (`/admin`).

## 7.3 Struktur folder data

```
/DATA/AppData/ppob/
├── src/          kode sumber (untuk build & update)
├── env/.env      konfigurasi aplikasi — kredensial provider, DB, JWT
├── .env          sandi MySQL yang dipakai docker compose
├── mysql/        data database
├── redis/        antrean & cache
├── storage/      log, cache, bukti transfer, avatar, banner
├── public/       document root web
├── nginx/        konfigurasi nginx
└── firebase/     service-account.json untuk push notification
```

Semua di bawah `/DATA/AppData` mengikuti konvensi CasaOS, jadi ikut ter-backup bila Anda memakai fitur backup CasaOS.

## 7.4 Hal spesifik CasaOS yang perlu diperhatikan

| Hal | Keterangan |
|---|---|
| **Port 80** | Dipakai UI CasaOS. Aplikasi ini di **8081**. Kalau bentrok, ubah `"8081:80"` di compose. |
| **Port 3306** | MySQL sengaja **tidak** diekspos ke host — hanya diakses antar-container. |
| **Filesystem `/DATA`** | Bind mount MySQL butuh filesystem Linux (ext4/btrfs). Kalau `/DATA` ada di drive **exFAT/NTFS**, MySQL akan gagal start — pindahkan `- /DATA/AppData/ppob/mysql:/var/lib/mysql` ke named volume. |
| **RAM** | 6 container (nginx, php-fpm, mysql, redis, queue, scheduler) butuh ±1,5 GB. Di perangkat 2 GB, matikan `scheduler` dan jalankan `schedule:run` lewat cron host. |
| **ARM (Raspberry Pi)** | Image `mysql:8.4` mendukung arm64. Untuk armv7 (Pi 3 dan lebih lama), ganti ke `mariadb:11`. |
| **Container `queue`** | Wajib jalan. Tanpa itu transaksi berhenti di status "pending" karena topup dikirim lewat antrean. |

## 7.5 Perintah harian

Semua dijalankan lewat SSH (atau **Terminal** bawaan CasaOS):

```bash
docker compose -f /DATA/AppData/ppob/src/deploy/casaos/docker-compose.casaos.yml ps
```

```bash
docker logs -f ppob-queue
```

```bash
docker exec ppob-app php artisan queue:failed
```

Bersihkan cache setelah mengubah `.env`:

```bash
docker exec ppob-app php artisan config:cache
```

Sinkronkan katalog provider secara manual:

```bash
docker exec ppob-app php artisan queue:work redis --queue=maintenance --once
```

## 7.6 Memperbarui aplikasi

```bash
cd /DATA/AppData/ppob/src && sudo git pull origin main
```

```bash
sudo bash /DATA/AppData/ppob/src/deploy/casaos/install.sh
```

Skrip aman dijalankan ulang: `.env` yang sudah ada tidak ditimpa, `APP_KEY`/`JWT_SECRET` tidak di-generate ulang (kalau di-generate ulang, semua sesi pengguna terputus dan kredensial provider yang terenkripsi jadi tidak terbaca).

## 7.7 Backup

```bash
docker exec ppob-mysql mysqldump --single-transaction -u ppob -p"$(grep DB_PASSWORD /DATA/AppData/ppob/.env | cut -d= -f2)" ppob | gzip > /DATA/Backup/ppob-$(date +%F).sql.gz
```

Yang **wajib** ikut dicadangkan selain database: `/DATA/AppData/ppob/env/.env` (berisi `APP_KEY` — tanpa itu kredensial provider yang terenkripsi tidak bisa didekripsi) dan `/DATA/AppData/ppob/storage/app/public` (bukti transfer & banner).

## 7.8 Akses dari internet

Untuk aplikasi Flutter yang dipakai di luar rumah, jangan buka port 8081 langsung. Pakai salah satu:

- **Cloudflare Tunnel** (gratis, tanpa buka port, dapat HTTPS otomatis) — arahkan ke `http://ppob-nginx:80` dari container `cloudflared` di jaringan `ppob`
- **Nginx Proxy Manager** (tersedia di App Store CasaOS) — proxy host ke `ppob-nginx:80` + sertifikat Let's Encrypt

Setelah punya domain HTTPS, ubah di `/DATA/AppData/ppob/env/.env`:

```env
APP_URL=https://ppob.domain-anda.id
SESSION_SECURE_COOKIE=true
```

lalu `docker exec ppob-app php artisan config:cache` dan build ulang Flutter dengan `--dart-define=API_BASE_URL=https://ppob.domain-anda.id/api/v1`.

> Webhook provider PPOB **harus** bisa dijangkau dari internet dengan HTTPS. Selama masih di jaringan lokal saja, status transaksi hanya diperbarui lewat rekonsiliasi terjadwal (tiap 5 menit), bukan realtime.
