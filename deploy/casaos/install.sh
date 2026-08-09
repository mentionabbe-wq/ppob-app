#!/usr/bin/env bash
#
# Pemasangan PPOB App di CasaOS.
#
# Jalankan lewat SSH ke server CasaOS:
#   bash /DATA/AppData/ppob/src/deploy/casaos/install.sh
#
set -euo pipefail

APP_DIR="/DATA/AppData/ppob"
SRC_DIR="${APP_DIR}/src"
COMPOSE_FILE="${SRC_DIR}/deploy/casaos/docker-compose.casaos.yml"

info() { printf '\n\033[1;34m▶ %s\033[0m\n' "$1"; }
warn() { printf '\033[1;33m! %s\033[0m\n' "$1"; }

# ── 1. Prasyarat ────────────────────────────────────────
info "Memeriksa prasyarat"

command -v docker >/dev/null || { echo "Docker tidak ditemukan." >&2; exit 1; }

if [ ! -d "${SRC_DIR}" ]; then
    echo "Sumber kode tidak ada di ${SRC_DIR}." >&2
    echo "Salin folder ppob-app ke sana lebih dulu (scp / git clone / File Manager CasaOS)." >&2
    exit 1
fi

# Port 80 dipakai UI CasaOS; pastikan 8081 bebas.
if ss -ltn 2>/dev/null | grep -q ':8081 '; then
    warn "Port 8081 sudah dipakai. Ubah pemetaan port di ${COMPOSE_FILE}."
fi

# ── 2. Struktur folder ──────────────────────────────────
info "Menyiapkan folder data"
mkdir -p "${APP_DIR}"/{env,mysql,redis,storage,public,firebase,nginx}
mkdir -p "${APP_DIR}"/storage/{app/public,framework/{cache,sessions,views},logs}

cp -rn "${SRC_DIR}/backend/public/." "${APP_DIR}/public/" 2>/dev/null || true
cp "${SRC_DIR}/docker/nginx/default.conf" "${APP_DIR}/nginx/default.conf"

# PHP-FPM di container berjalan sebagai www-data (uid 82 pada image Alpine).
chown -R 82:82 "${APP_DIR}/storage" "${APP_DIR}/public" 2>/dev/null || \
    warn "Gagal mengubah owner storage — jalankan skrip ini dengan sudo."
chmod -R 775 "${APP_DIR}/storage"

# ── 3. Konfigurasi ──────────────────────────────────────
if [ ! -f "${APP_DIR}/env/.env" ]; then
    info "Membuat berkas konfigurasi"

    cp "${SRC_DIR}/backend/.env.example" "${APP_DIR}/env/.env"

    DB_PASSWORD="$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)"
    HOST_IP="$(hostname -I | awk '{print $1}')"

    sed -i \
        -e "s|^APP_ENV=.*|APP_ENV=production|" \
        -e "s|^APP_DEBUG=.*|APP_DEBUG=false|" \
        -e "s|^APP_URL=.*|APP_URL=http://${HOST_IP}:8081|" \
        -e "s|^DB_PASSWORD=.*|DB_PASSWORD=${DB_PASSWORD}|" \
        "${APP_DIR}/env/.env"

    # Simpan sandi DB agar dipakai container MySQL juga.
    cat > "${APP_DIR}/.env" <<EOF
DB_PASSWORD=${DB_PASSWORD}
DB_ROOT_PASSWORD=$(openssl rand -base64 24 | tr -d '/+=' | head -c 24)
EOF
    chmod 600 "${APP_DIR}/.env" "${APP_DIR}/env/.env"

    echo "  Sandi database dibuat otomatis dan disimpan di ${APP_DIR}/.env"
else
    info "Konfigurasi sudah ada — dipertahankan"
fi

# ── 4. Build image ──────────────────────────────────────
info "Membangun image aplikasi (butuh beberapa menit)"
docker build -t ppob-app:latest -f "${SRC_DIR}/docker/php/Dockerfile" --target production "${SRC_DIR}"

# ── 5. Menjalankan stack ────────────────────────────────
info "Menjalankan container"
cd "${APP_DIR}"
docker compose -f "${COMPOSE_FILE}" --env-file "${APP_DIR}/.env" up -d

info "Menunggu database siap"
until docker exec ppob-mysql mysqladmin ping --silent >/dev/null 2>&1; do
    printf '.'
    sleep 3
done
echo

# ── 6. Inisialisasi Laravel ─────────────────────────────
info "Menyiapkan aplikasi"
EXEC="docker exec ppob-app php artisan"

grep -q '^APP_KEY=.\+' "${APP_DIR}/env/.env" || ${EXEC} key:generate --force
grep -q '^JWT_SECRET=.\+' "${APP_DIR}/env/.env" || ${EXEC} jwt:secret --force

${EXEC} migrate --force
${EXEC} db:seed --force
${EXEC} storage:link || true
${EXEC} config:cache
${EXEC} route:cache
${EXEC} view:cache
${EXEC} l5-swagger:generate

HOST_IP="$(hostname -I | awk '{print $1}')"

cat <<EOF

╭─────────────────────────────────────────────────────────────╮
│  PPOB App berhasil dipasang                                 │
╰─────────────────────────────────────────────────────────────╯

  Panel admin : http://${HOST_IP}:8081/admin
  REST API    : http://${HOST_IP}:8081/api/v1
  Swagger     : http://${HOST_IP}:8081/api/documentation

  Login awal  : admin@ppob.test  /  password
                ^ GANTI SEGERA lewat panel admin.

  Langkah berikutnya:
   1. Isi kredensial Digiflazz / VIP Reseller di
      Pengaturan → Provider (tersimpan terenkripsi).
   2. Klik "Sinkronkan Katalog Provider" di halaman Produk.
   3. Build aplikasi Flutter dengan:
      flutter build appbundle --release \\
        --dart-define=API_BASE_URL=http://${HOST_IP}:8081/api/v1

EOF
