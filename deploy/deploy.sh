#!/usr/bin/env bash
#
# Rilis aplikasi ke server produksi.
# Jalankan dari root repositori:  bash deploy/deploy.sh
#
set -euo pipefail

COMPOSE="docker compose -f docker-compose.yml -f docker-compose.prod.yml"
APP="${COMPOSE} exec -T app"

info() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }

info "Memeriksa konfigurasi"
if [ ! -f backend/.env ]; then
    echo "backend/.env tidak ditemukan. Salin dari .env.example dan isi kredensial produksi." >&2
    exit 1
fi

if grep -qE '^APP_DEBUG=true' backend/.env; then
    echo "APP_DEBUG masih true — batalkan rilis." >&2
    exit 1
fi

info "Mengaktifkan mode pemeliharaan"
# `|| true` karena aplikasi mungkin belum berjalan pada rilis pertama.
${APP} php artisan down --render="errors::503" --retry=60 || true

info "Membangun ulang image"
${COMPOSE} build --pull

info "Menjalankan container"
${COMPOSE} up -d --remove-orphans

info "Menunggu database siap"
until ${COMPOSE} exec -T mysql mysqladmin ping --silent 2>/dev/null; do
    printf '.'
    sleep 2
done

info "Menjalankan migrasi"
${APP} php artisan migrate --force

info "Menyiapkan tautan storage"
${APP} php artisan storage:link || true

info "Membangun cache"
${APP} php artisan config:cache
${APP} php artisan route:cache
${APP} php artisan view:cache
${APP} php artisan event:cache
${APP} php artisan l5-swagger:generate

info "Me-restart worker antrean"
# Worker lama berhenti setelah job berjalan selesai, bukan dipaksa mati.
${APP} php artisan queue:restart

info "Menonaktifkan mode pemeliharaan"
${APP} php artisan up

info "Memeriksa kesehatan aplikasi"
if ${COMPOSE} exec -T nginx wget -q -O /dev/null http://localhost/up; then
    echo "Aplikasi sehat."
else
    echo "PERINGATAN: health check gagal — periksa log dengan '${COMPOSE} logs app'." >&2
    exit 1
fi

info "Rilis selesai"
