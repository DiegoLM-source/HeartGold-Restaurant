#!/bin/sh
set -eu

PORT="${PORT:-10000}"
sed -i "s/10000/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

if [ -n "${RENDER_EXTERNAL_URL:-}" ]; then
    export APP_URL="${RENDER_EXTERNAL_URL}"
    export ASSET_URL="${RENDER_EXTERNAL_URL}"
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache
php artisan config:cache

exec apache2-foreground
