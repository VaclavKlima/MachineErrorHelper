FROM node:22-bookworm-slim AS frontend-assets

WORKDIR /app

COPY apps/backend/package*.json ./
RUN if [ -f package-lock.json ]; then npm ci; else npm install; fi

COPY apps/backend/vite.config.js ./
COPY apps/backend/resources ./resources
COPY apps/backend/public ./public
RUN npm run build

FROM composer:2 AS composer-vendor

WORKDIR /app

COPY apps/backend/composer.json apps/backend/composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --no-scripts \
    --optimize-autoloader \
    --prefer-dist \
    --ignore-platform-req=ext-exif \
    --ignore-platform-req=ext-intl \
    --ignore-platform-req=ext-pcntl

COPY apps/backend ./
RUN composer dump-autoload --no-dev --no-interaction --optimize

FROM php:8.5-fpm AS app

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        imagemagick \
        libicu-dev \
        libjpeg-dev \
        libpng-dev \
        libpq-dev \
        libwebp-dev \
        libzip-dev \
        ocrmypdf \
        poppler-utils \
        tesseract-ocr \
        tesseract-ocr-ces \
        tesseract-ocr-eng \
        unzip \
    && docker-php-ext-configure gd --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        bcmath \
        exif \
        gd \
        intl \
        pcntl \
        pdo_pgsql \
        zip \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/backend/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html

COPY --from=composer-vendor /app ./
COPY --from=frontend-assets /app/public/build ./public/build

RUN mkdir -p \
        bootstrap/cache \
        storage/app/public \
        storage/framework/cache \
        storage/framework/sessions \
        storage/framework/testing \
        storage/framework/views \
        storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R ug+rwX storage bootstrap/cache

CMD ["php-fpm"]

FROM nginx:1.29-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY --from=app /var/www/html/public /var/www/html/public

RUN mkdir -p /var/www/html/storage/app/public \
    && ln -s /var/www/html/storage/app/public /var/www/html/public/storage || true
