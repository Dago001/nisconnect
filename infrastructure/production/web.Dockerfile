# syntax=docker/dockerfile:1
#
# Caddy image for the NISconnect production stack: Caddy + the Caddyfile +
# the Flutter web app. Build context is the repository root (see
# docker-compose.yml). Two ways to get the web app into the image:
#
#   WEB_SOURCE=build     (default) compile mobile/ with Flutter inside Docker.
#                        Needs ~3 GB free RAM and a few minutes on first build.
#   WEB_SOURCE=prebuilt  copy an already-built web app from
#                        infrastructure/production/web/ (e.g. the
#                        nisconnect-web-*.zip attached to a GitHub Release, or
#                        `flutter build web --release --no-web-resources-cdn`
#                        run on another computer). Good for small servers.
#
# The web build needs no server address: served from a real domain, the app
# talks to the same origin it was loaded from (mobile/lib/core/config/app_config.dart).

ARG WEB_SOURCE=build

# ---- Option 1: compile the Flutter web app ---------------------------------
FROM debian:bookworm-slim AS web-build
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
# Public Pusher/Reverb app key (must equal REVERB_APP_KEY). Not a secret.
ARG WS_KEY=nisconnect
# CanvasKit is bundled instead of fetched from Google's CDN, so the site works
# on networks that block gstatic.com.
RUN flutter build web --release --no-web-resources-cdn --dart-define=WS_KEY="${WS_KEY}" \
    && mkdir -p /out && cp -r build/web/. /out/

# ---- Option 2: use a prebuilt web app ---------------------------------------
FROM scratch AS web-prebuilt
COPY infrastructure/production/web/ /out/

# ---- Pick one (only the chosen stage is built) ------------------------------
FROM web-${WEB_SOURCE} AS web

# ---- Final image ------------------------------------------------------------
FROM caddy:2.10-alpine
COPY infrastructure/production/Caddyfile /etc/caddy/Caddyfile
COPY --from=web /out/ /srv/web/
