#!/usr/bin/env bash
set -e

DB_TARGET="${DB_DATABASE:-/tmp/gramef_database.sqlite}"
if [ ! -f "$DB_TARGET" ]; then
    cp /var/www/database/demo.sqlite "$DB_TARGET"
    chmod 666 "$DB_TARGET"
fi

mkdir -p /var/www/storage/app/public \
         /var/www/storage/framework/cache/data \
         /var/www/storage/framework/sessions \
         /var/www/storage/framework/views \
         /var/www/storage/logs \
         /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

php artisan config:clear || true
php artisan view:clear || true
php artisan migrate --force || true

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}"
