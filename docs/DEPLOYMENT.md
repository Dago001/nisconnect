# NISconnect — Deployment Guide

> **Going live?** Follow **[GO_LIVE.md](GO_LIVE.md)** — a step-by-step guide for a single
> Ubuntu 24.04 server using the production stack in
> [`infrastructure/production/`](../infrastructure/production/) (Caddy with automatic HTTPS,
> Laravel app, queue worker, scheduler, Reverb, PostgreSQL 16, Redis 7, nightly backups),
> plus publishing the Android and iOS apps with `.github/workflows/release.yml`.

## Deployment options at a glance

| Setup | Files | Use it for |
|---|---|---|
| **Production** (one server, Docker) | `infrastructure/production/` (`docker-compose.yml`, `Caddyfile`, `web.Dockerfile`, `.env.example`, `backup.sh`) + `backend/Dockerfile` | The real service — see [GO_LIVE.md](GO_LIVE.md) |
| Development stack | `infrastructure/docker-compose.yml` (source mounted, nginx + php-fpm, MinIO, LiveKit `--dev`) | Local development only |
| Free test server | `render.yaml` + `backend/Dockerfile` | Demo/test API on Render (demo personnel source, OTPs shown in the app, no websockets) |

### Production routing (Caddy, one domain)
- `/api/*`, `/admin`, `/admin/*`, `/broadcasting/*`, `/sanctum/*`, `/portal-assets/*`, `/up`,
  `/robots.txt` → Laravel (`app`, Apache + PHP 8.4 from `backend/Dockerfile`).
- `/app/*`, `/apps/*` → Reverb websockets (`reverb:8080`).
- Everything else → the Flutter web build (`/srv/web`), unknown paths fall back to
  `index.html`. The web build has no server address compiled in: served from a real domain it
  uses its own origin for the API and `wss://` websockets (`mobile/lib/core/config/app_config.dart`).
- The same backend image runs `app` (migrates on start when `RUN_MIGRATIONS=true`), `worker`
  (`queue:work redis`), `scheduler` (`schedule:work`) and `reverb` (`reverb:start`) via
  `backend/docker/start.sh`.

## Components (development stack)
Nginx → Laravel (PHP-FPM) API + admin portal · Reverb (WebSockets) · queue workers ·
PostgreSQL · Redis · MinIO/S3 (private media) · LiveKit (SFU). See
`infrastructure/docker-compose.yml`.

## Local development with Docker

```bash
cd backend && cp .env.example .env
# set APP_KEY (php artisan key:generate), DB_*, REDIS_*, storage + provider vars
cd ../infrastructure
docker compose up -d --build
docker compose exec app php artisan migrate --seed
```

- API + admin portal: http://localhost:8000  (admin at `/admin/login`)
- Reverb WS: ws://localhost:8080
- MinIO console: http://localhost:9001
- LiveKit: ws://localhost:7880

Secrets live only in `backend/.env` (git-ignored). Never commit them.

## Production checklist
(The production stack's `.env.example` already sets these; the full checklist is in
[GO_LIVE.md §30](GO_LIVE.md#30-security-checklist).)

- `APP_ENV=production`, `APP_DEBUG=false`, unique `APP_KEY`.
- `PERSONNEL_PROVIDER=api` or `database` (the demo provider refuses to boot in production).
- TLS terminated at Nginx/load balancer; HSTS on; force HTTPS.
- Redis for cache, queue, session, broadcasting and presence.
- `OTP_DRIVER=sms`, real SMS credentials; `OTP_EXPOSE_IN_RESPONSE` unset/false.
- Object storage: S3/MinIO with a **private** bucket; app serves via signed, expiring URLs.
- Run `php artisan config:cache route:cache view:cache` in the release step.
- Horizontal scale: multiple `app`, `worker` and `reverb` replicas behind the LB; sticky
  sessions not required (tokens are stateless; Reverb uses Redis pub/sub).
- Health probe: `/up`.

## Database
- PostgreSQL 16. Migrations: `php artisan migrate --force`. Seed base roles/permissions and
  organisation with `--seed` (idempotent seeders).
- Indexed for scale: message pagination, directory search, audit lookups, FK columns.

## Backups & DR
- **PostgreSQL:** nightly `pg_dump` (custom format) + streaming WAL archiving for
  point-in-time recovery. Encrypt dumps at rest (GPG/age) before shipping off-box.
- **Media:** object-store versioning + cross-region/off-site replication.
- **Retention:** 7 daily, 4 weekly, 12 monthly (adjust to NIS policy).
- **Restore drill:** documented `pg_restore` runbook, tested quarterly.
- **Targets (recommended for the compose-shaped deployment):** **RPO ≤ 15 min** (WAL
  archiving), **RTO ≤ 2 h** (restore latest base backup + replay WAL, redeploy stateless
  services). Tighten to RPO ≈ 0 with synchronous streaming replication + a hot standby.

In the production stack, `infrastructure/production/backup.sh` implements the nightly
`pg_dump` + media archive with retention (`--install-cron`) and a guided `--restore`; see
[GO_LIVE.md §28](GO_LIVE.md#28-backups). WAL archiving / hot standby are not set up by it.

## CI/CD
- `.github/workflows/backend.yml` runs composer install, migrates a throwaway PostgreSQL
  service and executes the test suite on every push/PR.
- `.github/workflows/backend-image.yml` builds `backend/Dockerfile` and smoke-tests it.
- `.github/workflows/mobile.yml` analyses/tests the Flutter app and builds web, a preview
  per-CPU APKs (arm64 + older 32-bit phones) and an iOS simulator build.
- `.github/workflows/release.yml` (tags `v*` or manual with `server_url`) builds the web zip,
  signed per-CPU Android APKs + AAB (debug-signed when the keystore secrets are absent) and a signed
  iOS `.ipa` (unsigned `Runner.app` build when the Apple secrets are absent), and attaches
  them to a GitHub Release. Secrets are listed at the top of the workflow and in GO_LIVE.md.
