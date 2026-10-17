#!/usr/bin/env bash
# Checks an installed shared-hosting package over HTTP: the web app, admin
# portal, download page, API, and that only onboarded officers can register.
#
#   smoke-test.sh BASE-URL ONBOARDED-SERVICE-NUMBER
#
# Extra curl options (e.g. a Host header) go in CURL_OPTS. Used by
# .github/workflows/shared-hosting.yml; safe to run against a live server.
set -euo pipefail
base="${1:?base URL}"; sn="${2:?an onboarded Service Number}"
read -r -a opts <<< "${CURL_OPTS:-}"
c() { curl -sS "${opts[@]}" "$@"; }
status() { c -H 'Accept: application/json' -o /dev/null -w '%{http_code}' "$base$1"; }
expect() { local got; got="$(status "$1")"; echo "$1 -> $got"; [ "$got" = "$2" ] || { echo "expected $2"; exit 1; }; }
api() { c -H 'Accept: application/json' -H 'Content-Type: application/json' -X POST "$base/api/v1/auth/$1" -d "$2"; }

expect / 200
c "$base/" | grep -q '<title>NISconnect' || { echo "/ is not the web app"; exit 1; }
c -D - -o /dev/null "$base/main.dart.js" | grep -qi '^cache-control: no-cache' || { echo "main.dart.js may be cached"; exit 1; }
expect /admin/login 200
expect /download 200
expect /download/latest.json 200
expect /up 200
c "$base/api/v1/meta" | grep -q '"name":"NISconnect"' || { echo "meta endpoint"; exit 1; }
expect /api/v1/channels 401   # signed-out API calls are refused, not redirected
expect /.env 404              # settings live outside the web folder

echo "-- unknown Service Number cannot register"
api verify-service-number '{"service_number":"987654321"}' | grep -vq '"verification_id":"' || { echo "unknown number got through"; exit 1; }
echo "-- onboarded officer gets a sign-up code on screen"
id="$(api verify-service-number "{\"service_number\":\"$sn\"}" | sed -n 's/.*"verification_id":"\([^"]*\)".*/\1/p')"
[ -n "$id" ] || { echo "onboarded number refused"; exit 1; }
api confirm-identity "{\"verification_id\":\"$id\",\"phone\":\"08031234567\"}" | grep -Eq '"debug_code":"[0-9]{6}"' \
    || { echo "no on-screen code"; exit 1; }
echo "All checks passed."
