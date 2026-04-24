#!/bin/sh

set -eu

cd /var/www/html

mkdir -p bootstrap/cache
mkdir -p storage/framework/cache
mkdir -p storage/framework/sessions
mkdir -p storage/framework/testing
mkdir -p storage/framework/views
mkdir -p storage/logs

chmod -R 777 storage bootstrap/cache

if [ ! -f .env ]; then
    cp .env.example .env
fi

echo "Waiting for PostgreSQL..."
until pg_isready \
    -h "${DB_HOST:-postgres}" \
    -p "${DB_PORT:-5432}" \
    -U "${DB_USERNAME:-app}" \
    -d "${DB_DATABASE:-machine_error_helper}" >/dev/null 2>&1
do
    sleep 1
done

echo "Waiting for Redis..."
until redis-cli -h "${REDIS_HOST:-redis}" -p "${REDIS_PORT:-6379}" ping >/dev/null 2>&1
do
    sleep 1
done

echo "Installing PHP dependencies..."
composer install --no-interaction --prefer-dist

if [ -f package.json ]; then
    echo "Installing frontend dependencies..."
    npm install --no-package-lock

    echo "Building frontend assets..."
    npm run build
fi

if ! grep -Eq '^APP_KEY=.+$' .env; then
    echo "Generating application key..."
    php artisan key:generate --force
fi

echo "Clearing Laravel caches..."
php artisan optimize:clear

echo "Running database migrations..."
php artisan migrate --force

if [ ! -L public/storage ]; then
    php artisan storage:link
fi

echo "Backend bootstrap complete."
