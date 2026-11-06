FROM composer:2 AS vendor

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --no-scripts --optimize-autoloader

FROM node:24-bookworm-slim AS assets

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
COPY --from=vendor /app/vendor /app/vendor
RUN npm run build

FROM php:8.4-cli-bookworm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        git \
        libfreetype6-dev \
        libicu-dev \
        libjpeg62-turbo-dev \
        libpng-dev \
        libpq-dev \
        libzip-dev \
        unzip \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install bcmath gd intl pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

COPY . .
COPY --from=vendor /app/vendor /app/vendor
COPY --from=assets /app/public/build /app/public/build

RUN php artisan package:discover --ansi \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chmod -R ug+rwx storage bootstrap/cache

ENV PORT=8080

EXPOSE 8080

CMD ["sh", "/app/docker/start.sh"]
