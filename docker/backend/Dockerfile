FROM php:8.5-fpm

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        curl \
        git \
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

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

COPY docker/backend/uploads.ini /usr/local/etc/php/conf.d/uploads.ini

WORKDIR /var/www/html
