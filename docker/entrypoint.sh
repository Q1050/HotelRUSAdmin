#!/bin/sh
set -eu

port="${PORT:-8080}"
rm -f /etc/apache2/mods-enabled/mpm_event.load /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_worker.load /etc/apache2/mods-enabled/mpm_worker.conf
sed -i "s/Listen 80/Listen ${port}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:8080>/<VirtualHost *:${port}>/" /etc/apache2/sites-available/000-default.conf

php artisan package:discover --ansi
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
