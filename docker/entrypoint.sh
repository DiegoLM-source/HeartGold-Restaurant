#!/bin/sh
set -eu

PORT="${PORT:-10000}"

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
    export ASSET_URL="${RENDER_EXTERNAL_URL}"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${DB_CONNECTION:-sqlite}" = "sqlite" ]; then
    if [ -n "${DB_DATABASE:-}" ]; then
        touch "${DB_DATABASE}"
    else
        touch database/database.sqlite
    fi
fi

php artisan migrate --force --no-interaction
php artisan optimize:clear
php artisan view:cache
php artisan route:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT}"
