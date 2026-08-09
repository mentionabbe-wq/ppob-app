# syntax=docker/dockerfile:1

# ─────────────────────────────────────────────────────────────
# Base: ekstensi PHP yang dibutuhkan Laravel + MySQL + Redis
# ─────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS base

RUN apk add --no-cache \
        git curl zip unzip icu-dev oniguruma-dev libzip-dev \
        freetype-dev libjpeg-turbo-dev libpng-dev supervisor su-exec \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql mbstring bcmath intl zip gd opcache pcntl \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html

# ─────────────────────────────────────────────────────────────
# Development: kode di-bind mount, dependensi lengkap
# ─────────────────────────────────────────────────────────────
FROM base AS development

ENV APP_ENV=local
RUN echo "opcache.validate_timestamps=1" > /usr/local/etc/php/conf.d/opcache-dev.ini

CMD ["php-fpm"]

# ─────────────────────────────────────────────────────────────
# Production: kode ikut di-copy, autoload & config di-cache
# ─────────────────────────────────────────────────────────────
FROM base AS production

ENV APP_ENV=production

COPY backend/composer.json backend/composer.lock* ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

COPY backend/ ./
RUN composer dump-autoload --optimize --no-dev --classmap-authoritative \
    && mkdir -p storage/framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# OPcache agresif: kode tidak berubah di dalam image produksi.
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.validate_timestamps=0"; \
        echo "opcache.memory_consumption=192"; \
        echo "opcache.max_accelerated_files=20000"; \
        echo "opcache.jit_buffer_size=64M"; \
        echo "opcache.jit=tracing"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# Entrypoint menyiapkan .env, migrasi, dan cache saat container start,
# sehingga pemasangan di CasaOS cukup menempelkan berkas compose.
COPY docker/php/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

# Master php-fpm tetap root (perlu untuk chown volume saat start);
# worker berjalan sebagai www-data lewat konfigurasi pool bawaan.
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
