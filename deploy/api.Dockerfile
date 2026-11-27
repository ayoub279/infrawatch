FROM composer:2 AS composer
FROM php:8.3-fpm-alpine AS base
RUN apk add --no-cache libpq icu-libs libzip fcgi \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev icu-dev libzip-dev linux-headers \
 && docker-php-ext-install pdo_pgsql intl bcmath zip opcache \
 && apk del .build-deps
WORKDIR /app
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
COPY backend/ ./
COPY deploy/api-health.sh deploy/api-health.sh
COPY deploy/entrypoint.sh /usr/local/bin/infrawatch-entrypoint
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
 && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
 && chmod +x deploy/api-health.sh /usr/local/bin/infrawatch-entrypoint \
 && chown -R www-data:www-data storage bootstrap/cache
USER www-data
ENTRYPOINT ["infrawatch-entrypoint"]
CMD ["php-fpm", "-F"]
