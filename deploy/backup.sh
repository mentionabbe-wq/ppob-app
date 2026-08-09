#!/usr/bin/env bash
#
# Backup harian: dump database + arsip storage.
# Pasang di cron:  0 2 * * * bash /opt/ppob/deploy/backup.sh >> /var/log/ppob/backup.log 2>&1
#
set -euo pipefail

BACKUP_DIR="${BACKUP_DIR:-/var/backups/ppob}"
RETENTION_DAYS="${RETENTION_DAYS:-14}"
STAMP="$(date +%Y%m%d-%H%M%S)"
COMPOSE="docker compose -f /opt/ppob/docker-compose.yml -f /opt/ppob/docker-compose.prod.yml"

mkdir -p "${BACKUP_DIR}"

# Kredensial dibaca dari .env agar tidak tertulis di skrip.
DB_DATABASE="$(grep -E '^DB_DATABASE=' /opt/ppob/backend/.env | cut -d= -f2-)"
DB_USERNAME="$(grep -E '^DB_USERNAME=' /opt/ppob/backend/.env | cut -d= -f2-)"
DB_PASSWORD="$(grep -E '^DB_PASSWORD=' /opt/ppob/backend/.env | cut -d= -f2-)"

echo "[$(date '+%F %T')] Memulai backup"

# --single-transaction menjaga konsistensi tanpa mengunci tabel InnoDB.
${COMPOSE} exec -T mysql mysqldump \
    --single-transaction --quick --routines --events \
    -u"${DB_USERNAME}" -p"${DB_PASSWORD}" "${DB_DATABASE}" \
    | gzip -9 > "${BACKUP_DIR}/db-${STAMP}.sql.gz"

echo "  Database → db-${STAMP}.sql.gz ($(du -h "${BACKUP_DIR}/db-${STAMP}.sql.gz" | cut -f1))"

# Berkas unggahan: bukti transfer, avatar, banner.
tar -czf "${BACKUP_DIR}/storage-${STAMP}.tar.gz" \
    -C /var/lib/docker/volumes/ppob_storage-data/_data app/public 2>/dev/null \
    || echo "  PERINGATAN: arsip storage gagal — periksa nama volume."

echo "  Menghapus backup lebih tua dari ${RETENTION_DAYS} hari"
find "${BACKUP_DIR}" -name '*.gz' -type f -mtime "+${RETENTION_DAYS}" -delete

# PENTING: backup yang hanya tersimpan di server yang sama tidak
# melindungi dari kegagalan server. Aktifkan salah satu baris berikut.
# aws s3 sync "${BACKUP_DIR}" s3://bucket-anda/ppob/ --storage-class STANDARD_IA
# rclone copy "${BACKUP_DIR}" remote:ppob-backup

echo "[$(date '+%F %T')] Backup selesai"
