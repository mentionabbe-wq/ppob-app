# PPOB App — Jualan Pulsa & Payment Point Online Bank

Aplikasi PPOB siap produksi: **Flutter** (Android & iOS), **Laravel 12** REST API, **MySQL 8**, dan **Panel Admin** berbasis Laravel + Blade/Tailwind.

```
ppob-app/
├── backend/          Laravel 12 — REST API v1 + Panel Admin
├── mobile/           Flutter 3.x — Clean Architecture
├── docker/           Dockerfile, nginx, supervisor, php-fpm
├── deploy/           Skrip & panduan deployment VPS
├── docs/             Arsitektur, ERD, API, testing, deployment
└── docker-compose.yml
```

## Ringkasan Stack

| Layer | Teknologi |
|---|---|
| Mobile | Flutter 3.22+, Riverpod, Dio, GoRouter, Freezed, Hive |
| Backend | Laravel 12 (PHP 8.3), JWT (php-open-source-saver/jwt-auth), Spatie Permission |
| Database | MySQL 8 + Redis (cache, queue, rate limit) |
| Admin | Laravel Blade + Tailwind + Chart.js |
| Docs API | Swagger / OpenAPI 3 (l5-swagger) |
| Queue | Laravel Horizon (Redis) |
| Provider PPOB | Digiflazz, VIP Reseller, (pluggable) |
| Deploy | Docker Compose, Nginx, VPS Ubuntu 22.04 |

## Urutan Baca Dokumen

1. [`docs/01-arsitektur.md`](docs/01-arsitektur.md) — arsitektur sistem & clean architecture
2. [`docs/02-erd.md`](docs/02-erd.md) — ERD dan struktur database
3. [`docs/03-api.md`](docs/03-api.md) — daftar endpoint REST API v1
4. [`docs/04-provider-integrasi.md`](docs/04-provider-integrasi.md) — integrasi Digiflazz / VIP Reseller
5. [`docs/05-testing.md`](docs/05-testing.md) — strategi & perintah testing
6. [`docs/06-deployment.md`](docs/06-deployment.md) — Docker & deployment VPS
7. [`docs/07-casaos.md`](docs/07-casaos.md) — pemasangan di CasaOS (self-hosted)

## Pasang di CasaOS (tanpa SSH)

Salin isi [`casaos-ppob.yml`](casaos-ppob.yml) → CasaOS → **Apps** → **+** → **Custom Install** → **Import** → tempel → ganti `DB_PASSWORD` dan `APP_URL` → **Install**.

Migrasi database, `APP_KEY`, dan data awal dibuat otomatis saat container pertama start. Panduan lengkap: [`docs/07-casaos.md`](docs/07-casaos.md).

Alternatif lewat SSH (build image sendiri):

```bash
sudo git clone https://github.com/mentionabbe-wq/ppob-app.git /DATA/AppData/ppob/src && sudo bash /DATA/AppData/ppob/src/deploy/casaos/install.sh
```

## Quick Start (Development)

```bash
git clone https://github.com/mentionabbe-wq/ppob-app.git && cd ppob-app
cp backend/.env.example backend/.env
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan jwt:secret
docker compose exec app php artisan migrate --seed
docker compose exec app php artisan l5-swagger:generate
```

API: `http://localhost:8080/api/v1` · Admin: `http://localhost:8080/admin` · Swagger: `http://localhost:8080/api/documentation`

Akun seeder default:

| Role | Email | Password |
|---|---|---|
| Super Admin | `admin@ppob.test` | `password` |
| User | `user@ppob.test` | `password` |

```bash
cd mobile && flutter pub get && flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8080/api/v1
```

## Catatan Keamanan Produksi

- Ganti seluruh kredensial di `.env` (JWT_SECRET, APP_KEY, DB, provider).
- Aktifkan HTTPS (Let's Encrypt) — lihat `deploy/`.
- `APP_DEBUG=false`, `APP_ENV=production`.
- Webhook provider divalidasi via signature (`md5(username+secret)` untuk Digiflazz) + IP allowlist.
