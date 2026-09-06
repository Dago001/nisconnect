# NISconnect — Deployment Guide

## Components
Nginx → Laravel (PHP-FPM) API + admin portal · Reverb (WebSockets) · queue workers ·
PostgreSQL · Redis · MinIO/S3 (private media) · LiveKit (SFU). See
`infrastructure/docker-compose.yml`.

## Local / staging with Docker

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

## CI/CD
`.github/workflows/backend.yml` runs composer install, migrates a throwaway PostgreSQL
service and executes the test suite on every push/PR. Extend with static analysis
(`php artisan test`, PHPStan) and a build/deploy stage gated on green.
