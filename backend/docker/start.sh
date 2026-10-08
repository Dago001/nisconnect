#!/bin/sh
# Container entrypoint: prepare Laravel, migrate, then serve on $PORT.
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
php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache || true
php artisan view:cache || true

chown -R www-data:www-data storage bootstrap/cache

sed -ri "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf

exec apache2-foreground
