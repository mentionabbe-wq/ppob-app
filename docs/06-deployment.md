# 6. Docker & Deployment ke VPS

## 6.1 Development (lokal)

```bash
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan storage:link
docker compose exec app php artisan l5-swagger:generate
```

| Layanan | Alamat |
|---|---|
| API | http://localhost:8080/api/v1 |
| Panel admin | http://localhost:8080/admin |
| Swagger | http://localhost:8080/api/documentation |
| Mailpit (email OTP) | http://localhost:8025 |
| MySQL | localhost:33061 |

Aplikasi Flutter menuju host dari emulator Android:

```bash
cd mobile && flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
```

## 6.2 Persiapan VPS (Ubuntu 22.04)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y docker.io docker-compose-plugin git ufw fail2ban
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

Firewall — hanya SSH, HTTP, HTTPS:

```bash
sudo ufw allow OpenSSH && sudo ufw allow 80 && sudo ufw allow 443 && sudo ufw --force enable
```

Perkuat SSH (`/etc/ssh/sshd_config`): `PermitRootLogin no`, `PasswordAuthentication no`, lalu `sudo systemctl restart ssh`.

## 6.3 Rilis Produksi

```bash
git clone <repo> /opt/ppob && cd /opt/ppob
cp backend/.env.example backend/.env
```

Wajib diubah di `.env` produksi:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.domain-anda.id
DB_PASSWORD=<sandi kuat>
SESSION_SECURE_COOKIE=true
DIGIFLAZZ_USERNAME=... DIGIFLAZZ_API_KEY=... DIGIFLAZZ_WEBHOOK_SECRET=...
DIGIFLAZZ_ALLOWED_IPS=<IP webhook provider>
MIDTRANS_SERVER_KEY=... MIDTRANS_IS_PRODUCTION=true
```

Jalankan:

```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml up -d --build
bash deploy/deploy.sh
```

`deploy/deploy.sh` menjalankan migrasi, cache config/route/view, membangun ulang autoload, dan me-restart worker antrean.

## 6.4 HTTPS (Let's Encrypt)

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d api.domain-anda.id
```

Salin `deploy/nginx-production.conf` ke `/etc/nginx/sites-available/ppob`, sesuaikan `server_name`, lalu:

```bash
sudo ln -s /etc/nginx/sites-available/ppob /etc/nginx/sites-enabled/ && sudo nginx -t && sudo systemctl reload nginx
```

Perpanjangan otomatis sudah ditangani timer certbot; verifikasi dengan `sudo certbot renew --dry-run`.

## 6.5 Proses Latar Belakang

Compose sudah menyediakan container `queue` (worker antrean) dan `scheduler` (`schedule:work`). Bila menjalankan tanpa Docker, pakai `deploy/supervisor-worker.conf` dan satu baris cron:

```bash
* * * * * cd /opt/ppob/backend && php artisan schedule:run >> /dev/null 2>&1
```

Antrean yang dipakai: `topup` (prioritas tertinggi), `notifications`, `maintenance`, `default`.

## 6.6 Backup

```bash
bash deploy/backup.sh          # dump MySQL + arsip storage, retensi 14 hari
```

Pasang di cron harian pukul 02:00 dan **salin hasilnya ke penyimpanan lain** (S3/Backblaze) — backup yang hanya ada di server yang sama tidak melindungi dari kegagalan server.

## 6.7 Checklist Sebelum Go-Live

- [ ] `APP_DEBUG=false`, `APP_ENV=production`
- [ ] Kata sandi seluruh akun seeder diganti (`admin@ppob.test` dkk. memakai `password`)
- [ ] `APP_KEY` dan `JWT_SECRET` di-generate ulang di server produksi
- [ ] HTTPS aktif + `SESSION_SECURE_COOKIE=true`
- [ ] Kredensial provider diisi lewat panel admin (tersimpan terenkripsi)
- [ ] `DIGIFLAZZ_ALLOWED_IPS` diisi IP webhook resmi provider
- [ ] URL webhook didaftarkan di dashboard provider: `https://api.domain-anda.id/api/v1/webhooks/providers/digiflazz`
- [ ] URL notifikasi payment gateway: `https://api.domain-anda.id/api/v1/webhooks/payment`
- [ ] Kredensial FCM (`storage/app/firebase/service-account.json`) terpasang
- [ ] Port MySQL **tidak** diekspos publik (hapus blok `ports` di produksi)
- [ ] Backup terjadwal dan sudah diuji restore-nya
- [ ] Monitoring: Horizon (`/horizon`) dibatasi hanya untuk admin

## 6.8 Rilis Aplikasi Mobile

```bash
flutter build appbundle --release --dart-define=API_BASE_URL=https://api.domain-anda.id/api/v1
flutter build ipa --release --dart-define=API_BASE_URL=https://api.domain-anda.id/api/v1
```

Tanda tangani Android dengan keystore rilis (`android/key.properties`) — jangan pakai debug keystore. Setelan `app.min_version_android` / `app.min_version_ios` di panel admin dapat dipakai untuk memaksa pembaruan aplikasi.
