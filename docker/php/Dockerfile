# Ubah 8.2 menjadi 8.4
FROM php:8.4-fpm-alpine

# Install dependensi OS untuk PostgreSQL
RUN apk add --no-cache libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Set working directory ke folder standar web
WORKDIR /var/www/html
