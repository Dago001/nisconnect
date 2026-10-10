# syntax=docker/dockerfile:1
#
# One-container NISconnect for Render (../../render.production.yaml): Apache +
# PHP serving, on one https address,
#   /                the Flutter web app (officers)
#   /api, /admin ... the Laravel API and the Super Admin portal
#   /download        the page where officers download the mobile apps
# Build context is the repository root.
#
# The PHP part mirrors backend/Dockerfile (used by the Docker server stack in
# infrastructure/production); keep the two in step.

# ---- Flutter web app ---------------------------------------------------------
FROM debian:bookworm-slim AS web
ARG FLUTTER_VERSION=3.47.6
RUN apt-get update \
    && apt-get install -y --no-install-recommends git curl ca-certificates unzip xz-utils \
    && rm -rf /var/lib/apt/lists/*
RUN git clone --depth 1 --branch "${FLUTTER_VERSION}" https://github.com/flutter/flutter.git /opt/flutter
ENV PATH=/opt/flutter/bin:$PATH
RUN flutter config --no-analytics --enable-web >/dev/null && flutter precache --web
WORKDIR /src
COPY mobile/pubspec.yaml mobile/pubspec.lock ./
RUN flutter pub get
COPY mobile/ ./
# No API_BASE_URL: served from the same address as the API, the web app talks
# to the origin it was loaded from (mobile/lib/core/config/app_config.dart).
# CanvasKit is bundled so the site works where gstatic.com is blocked.
RUN flutter build web --release --no-web-resources-cdn

# ---- PHP dependencies --------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --no-interaction \
        --prefer-dist --ignore-platform-reqs
COPY backend/ .
RUN composer dump-autoload --optimize --no-dev --no-scripts

# ---- Runtime -----------------------------------------------------------------
FROM php:8.4-apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libpq-dev libicu-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql pgsql intl zip bcmath pcntl opcache \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /tmp/pear \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && { \
        echo 'upload_max_filesize=200M'; \
        echo 'post_max_size=210M'; \
        echo 'memory_limit=256M'; \
        echo 'max_execution_time=120'; \
        echo 'expose_php=Off'; \
        echo 'opcache.enable=1'; \
        echo 'opcache.enable_cli=0'; \
        echo 'opcache.memory_consumption=128'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.validate_timestamps=0'; \
    } > "$PHP_INI_DIR/conf.d/zz-nisconnect.ini"

COPY infrastructure/render/apache.conf /etc/apache2/conf-available/zz-nisconnect.conf
RUN a2enconf zz-nisconnect

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
        /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf \
    && sed -ri -e 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

WORKDIR /var/www/html
COPY --from=vendor /app /var/www/html
# The web app's files sit next to Laravel's index.php; Apache serves them
# directly and hands every other path to Laravel.
COPY --from=web /src/build/web/ /var/www/html/public/
RUN mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
        storage/logs storage/app bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache

COPY backend/docker/start.sh /usr/local/bin/nisconnect-start
RUN chmod +x /usr/local/bin/nisconnect-start

ENV PORT=10000
EXPOSE 10000
CMD ["nisconnect-start"]
