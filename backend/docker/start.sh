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
# every start. RUN_SCHEDULER=true also runs the Laravel scheduler in the
# background (single-container hosts such as Render).
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

# Render gives every service its public address; use it unless APP_URL is set
# (e.g. after adding a custom domain).
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    APP_URL="$RENDER_EXTERNAL_URL"
    export APP_URL
fi

php artisan package:discover --ansi

if [ "$#" -eq 0 ] && [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    if [ "${RUN_SEEDERS:-true}" = "true" ]; then
        php artisan migrate --force --seed
    else
        php artisan migrate --force
    fi
fi

# First Super Administrator from NIS_ADMIN_* (Render blueprint). No-op when
# those are unset or the account already has a password.
if [ "$#" -eq 0 ]; then
    php artisan nis:create-admin --from-env
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

# After a move to a new address (APP_CANONICAL_URL, see config/app.php),
# browsers on any other address are sent there. The API, health check and
# websocket auth keep answering on the old address, so installed apps work
# until they switch over (they read the new address from /api/v1/meta).
# REDIRECT_STATUS is empty only on the first pass: after Laravel's rewrite to
# index.php the rules run again with REQUEST_URI=/index.php.
case "${APP_CANONICAL_URL:-}" in
    https://*)
        canonical_host=$(echo "$APP_CANONICAL_URL" | sed -E 's#^https://([^/:]+).*#\1#')
        if ! grep -q 'nisconnect-canonical' public/.htaccess; then
            sed -i "/^    RewriteEngine On/a\\
\\
    # nisconnect-canonical: redirect browsers to $canonical_host\\
    RewriteCond %{ENV:REDIRECT_STATUS} ^\$\\
    RewriteCond %{HTTP_HOST} !^${canonical_host}(:[0-9]+)?\$ [NC]\\
    RewriteCond %{REQUEST_URI} !^/(api|up|broadcasting|sanctum)(/|\$)\\
    RewriteRule ^ https://${canonical_host}%{REQUEST_URI} [R=301,L]" public/.htaccess
        fi
        ;;
esac

sed -ri "s/^Listen .*/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -ri "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT:-8080}>/" /etc/apache2/sites-available/000-default.conf

# Single-container hosts (Render) have no separate scheduler service: run
# `schedule:work` alongside Apache when RUN_SCHEDULER=true.
if [ "${RUN_SCHEDULER:-false}" = "true" ]; then
    if [ "$(id -u)" = "0" ]; then
        su -s /bin/sh www-data -c 'php artisan schedule:work' &
    else
        php artisan schedule:work &
    fi
fi

exec apache2-foreground
