# syntax=docker/dockerfile:1
#
# Image web: nginx + document root aplikasi.
# Dibuat terpisah agar berkas compose CasaOS tidak perlu bind mount
# konfigurasi apa pun dari host — cukup tempel YML, semua sudah di dalam.

FROM nginx:1.27-alpine

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY backend/public /var/www/html/public

# Berkas unggahan (bukti transfer, avatar, banner) berasal dari volume
# storage bersama yang di-mount saat runtime.
#
# Symlink dibuat di sini, bukan lewat `artisan storage:link`: direktori
# public/ hanya ada di image ini, sedangkan perintah artisan berjalan di
# container app — symlink yang dibuatnya tidak akan terlihat oleh nginx.
RUN mkdir -p /var/www/html/storage/app/public \
    && ln -sfn /var/www/html/storage/app/public /var/www/html/public/storage \
    && chown -R nginx:nginx /var/www/html

EXPOSE 80
