FROM php:8.3-cli AS php-dependencies

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN apt-get update \
    && apt-get install -y --no-install-recommends git libxml2-dev unzip \
    && docker-php-ext-install -j"$(nproc)" dom \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app
COPY . .
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

FROM node:22-alpine AS frontend

WORKDIR /app
COPY . .
COPY --from=php-dependencies /app/vendor ./vendor
RUN npm ci --no-audit --no-fund && npm run build

FROM php:8.3-apache AS runtime

RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev libicu-dev libonig-dev libxml2-dev libzip-dev \
    && docker-php-ext-install -j"$(nproc)" curl dom intl mbstring opcache pcntl pdo_mysql zip \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /var/www/html
COPY --from=frontend /app .
COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/hotelcheckin-entrypoint

RUN chmod +x /usr/local/bin/hotelcheckin-entrypoint \
    && mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

ENV APP_ENV=production \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    PORT=8080

EXPOSE 8080
ENTRYPOINT ["hotelcheckin-entrypoint"]
CMD ["apache2-foreground"]
