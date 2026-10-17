#!/usr/bin/env bash
# Builds the NISconnect upload package for shared hosting (docs/SHARED_HOSTING.md):
# the Laravel backend with its PHP libraries, and the officer web app inside
# public/, as one zip to upload and extract in the hosting account.
#
#   infrastructure/shared-hosting/build-package.sh [output-dir]
#
# Needs git, php 8.3+, composer, zip and Flutter (FLUTTER=/path/to/flutter to
# pick one). Builds from the committed code (HEAD), not uncommitted changes.
set -euo pipefail

root="$(git -C "$(dirname "$0")" rev-parse --show-toplevel)"
out="$(mkdir -p "${1:-$root/dist}" && cd "${1:-$root/dist}" && pwd)"
flutter="${FLUTTER:-flutter}"
version="$(git -C "$root" describe --tags --match 'v*' --always)"
work="$(mktemp -d)"
trap 'rm -rf "$work"' EXIT

echo "==> Exporting the code at $version"
git -C "$root" archive HEAD backend mobile | tar -x -C "$work"
app="$work/nisconnect"
mv "$work/backend" "$app"

echo "==> Installing PHP libraries"
(cd "$app" && COMPOSER_ALLOW_SUPERUSER=1 composer install --no-dev --optimize-autoloader \
    --no-interaction --no-progress --prefer-dist)
(cd "$app" && COMPOSER_ALLOW_SUPERUSER=1 composer check-platform-reqs --no-dev)
# Composer falls back to git checkouts when a download fails; their history
# is not needed on the server.
find "$app/vendor" -name .git -type d -prune -exec rm -rf {} +

echo "==> Building the officer web app"
(cd "$work/mobile" && "$flutter" pub get && "$flutter" build web --release --no-web-resources-cdn)
# The web app's files sit next to Laravel's index.php; the server hands every
# path that is not a file to Laravel.
cp -R "$work/mobile/build/web/." "$app/public/"
{ cat "$root/infrastructure/shared-hosting/htaccess-web.conf"; cat "$root/backend/public/.htaccess"; } \
    > "$app/public/.htaccess"
cp "$root/infrastructure/shared-hosting/user.ini" "$app/public/.user.ini"

echo "==> Tidying"
cp "$root/infrastructure/shared-hosting/env.example" "$app/.env.example"
rm -rf "$app/tests" "$app/phpunit.xml" "$app/docker" "$app/.env" "$app/node_modules"
mkdir -p "$app/storage/framework/cache/data" "$app/storage/framework/sessions" \
    "$app/storage/framework/views" "$app/storage/logs" "$app/storage/app/private" \
    "$app/bootstrap/cache"
echo "$version" > "$app/VERSION"

zipfile="$out/nisconnect-server-$version.zip"
rm -f "$zipfile"
(cd "$work" && zip -qr -X "$zipfile" nisconnect)
echo "==> $zipfile ($(du -h "$zipfile" | cut -f1))"
