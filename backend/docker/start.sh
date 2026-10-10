#!/bin/sh
# Container entrypoint.
#
#   nisconnect-start              prepare Laravel, migrate (unless
#                                 RUN_MIGRATIONS=false), then serve the API +
#                                 admin portal with Apache on $PORT.
#   nisconnect-start <command...> prepare Laravel, then run <command> instead of
#                                 Apache, e.g. `php artisan queue:work`. Used by
#                                 the worker / scheduler / reverb containers of
#                                 infrastructure/production. Never migrates.
#
# RUN_MIGRATIONS (default true) and RUN_SEEDERS (default true) keep the Render
# test deployment's behaviour: migrate and run the idempotent base seeders on
# every start.
set -e
cd /var/www/html

# Laravel needs a 32-byte key in "base64:..." form. Hosting platforms that
# generate a random secret (Render's generateValue) don't use that format, so
# derive a stable key from whatever secret was provided.
if [ -z "$APP_KEY" ]; then
    echo "APP_KEY is not set" >&2
    exit 1
fi
case "$APP_KEY" in
    base64:*) ;;
    *) APP_KEY=$(php -r 'echo "base64:".base64_encode(hash("sha256", getenv("APP_KEY"), true));')
       export APP_KEY ;;
esac

php artisan package:discover --ansi

if [ "$#" -eq 0 ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    if [ "${RUN_SEEDERS:-true}" = "true" ]; then
        php artisan migrate --force --seed
    else
        php artisan migrate --force
    fi
fi

php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

# Only possible (and only needed) when the container runs as root; the
# production worker/scheduler/reverb containers already run as www-data.
if [ "$(id -u)" = "0" ]; then
    chown -R www-data:www-data storage bootstrap/cache
fi

if [ "$#" -gt 0 ]; then
    exec "$@"
fi

sed -ri "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
