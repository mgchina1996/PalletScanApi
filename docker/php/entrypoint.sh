#!/bin/sh
set -eu

mkdir -p \
    storage/app/public \
    storage/database \
    storage/framework/cache/data \
    storage/framework/sessions \
    storage/framework/views \
    storage/logs

if [ "${DB_CONNECTION:-sqlsrv}" = "sqlite" ]; then
    database_file="${DB_DATABASE:-/var/www/html/storage/database/database.sqlite}"
    mkdir -p "$(dirname "$database_file")"
    touch "$database_file"
fi

chown -R www-data:www-data storage bootstrap/cache

php artisan config:cache
php artisan route:cache
php artisan view:cache

if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    php artisan migrate --force
fi

exec "$@"
