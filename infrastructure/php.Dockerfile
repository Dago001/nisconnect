# NISconnect backend PHP-FPM image
FROM php:8.4-fpm-alpine

RUN apk add --no-cache postgresql-dev icu-dev libzip-dev oniguruma-dev linux-headers $PHPIZE_DEPS \
    && docker-php-ext-install pdo pdo_pgsql pgsql intl zip bcmath pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/backend

# Dependencies are installed at build time; source is mounted in compose.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist || true

EXPOSE 9000
CMD ["php-fpm"]
