#!/bin/sh
#
# Entrypoint container aplikasi.
#
# Tujuannya: pengguna cukup menempelkan satu berkas compose di CasaOS
# tanpa perlu menjalankan perintah artisan apa pun secara manual.
# Skrip ini aman dijalankan berulang (idempoten).
#
set -e

APP_DIR=/var/www/html
ENV_FILE="${APP_DIR}/storage/.env"
ROLE="${CONTAINER_ROLE:-app}"

log() { printf '\033[1;34m[ppob]\033[0m %s\n' "$1"; }

# ── Menyiapkan .env di volume persisten ─────────────────
# .env tinggal di dalam volume storage agar APP_KEY dan JWT_SECRET
# bertahan saat image diperbarui. Tanpa itu, kredensial provider yang
# terenkripsi di database tidak akan bisa didekripsi lagi.
init_env() {
    if [ ! -f "${ENV_FILE}" ]; then
        log "Membuat konfigurasi awal"
        cp "${APP_DIR}/.env.example" "${ENV_FILE}"
    fi

    ln -sf "${ENV_FILE}" "${APP_DIR}/.env"

    # Nilai dari environment container menimpa isi .env.
    for key in APP_NAME APP_URL APP_DEBUG APP_ENV \
               DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD \
               REDIS_HOST REDIS_PORT REDIS_PASSWORD \
               MAIL_MAILER MAIL_HOST MAIL_PORT MAIL_USERNAME MAIL_PASSWORD \
               MAIL_ENCRYPTION MAIL_FROM_ADDRESS \
               CACHE_STORE QUEUE_CONNECTION SESSION_DRIVER; do
        value=$(printenv "${key}" || true)

        [ -z "${value}" ] && continue

        if grep -q "^${key}=" "${ENV_FILE}"; then
            # Pemisah | dipakai agar URL berisi / tidak merusak sed.
            sed -i "s|^${key}=.*|${key}=${value}|" "${ENV_FILE}"
        else
            echo "${key}=${value}" >> "${ENV_FILE}"
        fi
    done
}

wait_for_env() {
    log "Menunggu container utama menyiapkan konfigurasi"

    while [ ! -f "${ENV_FILE}" ]; do
        sleep 2
    done

    ln -sf "${ENV_FILE}" "${APP_DIR}/.env"
}

wait_for_database() {
    log "Menunggu database"

    until php -r "
        \$h = getenv('DB_HOST') ?: 'mysql';
        \$p = getenv('DB_PORT') ?: 3306;
        exit(@fsockopen(\$h, (int) \$p) ? 0 : 1);
    " 2>/dev/null; do
        sleep 3
    done
}

prepare_storage() {
    mkdir -p "${APP_DIR}"/storage/app/public \
             "${APP_DIR}"/storage/app/firebase \
             "${APP_DIR}"/storage/framework/cache/data \
             "${APP_DIR}"/storage/framework/sessions \
             "${APP_DIR}"/storage/framework/views \
             "${APP_DIR}"/storage/logs

    chown -R www-data:www-data "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
    chmod -R 775 "${APP_DIR}/storage" "${APP_DIR}/bootstrap/cache" 2>/dev/null || true
}

run_as_app() {
    su-exec www-data php "$@"
}

# ── Alur utama ──────────────────────────────────────────
if [ "${ROLE}" = "app" ]; then
    prepare_storage
    init_env

    # Kunci dibuat sekali seumur hidup instalasi.
    grep -q '^APP_KEY=.\+' "${ENV_FILE}"    || run_as_app artisan key:generate --force
    grep -q '^JWT_SECRET=.\+' "${ENV_FILE}" || run_as_app artisan jwt:secret --force

    wait_for_database

    log "Menjalankan migrasi"
    run_as_app artisan migrate --force

    # Seeder hanya pada instalasi pertama; penanda disimpan di volume
    # agar restart container tidak mengulang dan menimpa data admin.
    if [ ! -f "${APP_DIR}/storage/.seeded" ]; then
        log "Mengisi data awal (kategori, produk contoh, akun admin)"
        run_as_app artisan db:seed --force
        touch "${APP_DIR}/storage/.seeded"
        chown www-data:www-data "${APP_DIR}/storage/.seeded"

        log "Akun admin: admin@ppob.test / password  — GANTI SEGERA"
    fi

    run_as_app artisan storage:link 2>/dev/null || true

    log "Membangun cache"
    run_as_app artisan config:cache
    run_as_app artisan route:cache
    run_as_app artisan view:cache
    run_as_app artisan l5-swagger:generate 2>/dev/null || true

    log "Aplikasi siap"
else
    wait_for_env
    wait_for_database
    log "Container '${ROLE}' siap"
fi

exec "$@"
