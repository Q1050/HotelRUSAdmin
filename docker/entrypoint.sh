#!/bin/sh
set -eu

port="${PORT:-8080}"
if [ -z "${APP_URL:-}" ] && [ -n "${RAILWAY_PUBLIC_DOMAIN:-}" ]; then
    export APP_URL="https://${RAILWAY_PUBLIC_DOMAIN}"
fi
export APP_NAME="${APP_NAME:-HotelCheckin}"

echo "Starting HotelCheckin with APP_ENV=${APP_ENV:-production}, DB_CONNECTION=${DB_CONNECTION:-unset}, APP_URL=${APP_URL:-unset}"

rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
sed -i -E "s/^Listen [0-9]+$/Listen ${port}/" /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

exec "$@"
