#!/bin/sh
set -eu

port="${PORT:-8080}"
if [ -z "${APP_URL:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
export APP_NAME="${APP_NAME:-HotelCheckin}"

if [ "${APP_ENV:-production}" = "production" ]; then
    : "${APP_KEY:?APP_KEY must be configured on the Railway web service}"
    : "${DB_CONNECTION:?DB_CONNECTION must be configured on the Railway web service}"
    : "${DB_HOST:?DB_HOST must be configured on the Railway web service}"
    : "${DB_DATABASE:?DB_DATABASE must be configured on the Railway web service}"
    : "${DB_USERNAME:?DB_USERNAME must be configured on the Railway web service}"
    : "${DB_PASSWORD:?DB_PASSWORD must be configured on the Railway web service}"
fi

echo "Starting HotelCheckin with APP_ENV=${APP_ENV:-production}, DB_CONNECTION=${DB_CONNECTION:-unset}, APP_URL=${APP_URL:-unset}"

rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
sed -i -E "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
