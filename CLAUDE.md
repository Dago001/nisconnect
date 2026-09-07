# CLAUDE.md — NISconnect

Guidance for AI assistants (and humans) working in this repo.

## What this is
NISconnect — secure internal communication platform for the Nigeria Immigration Service.
Monorepo: **Laravel 11 + PostgreSQL API** (`backend/`), **Flutter app** (`mobile/`),
**admin portal** (Blade, inside `backend/`), **Docker infra** (`infrastructure/`), docs
(`docs/`), and the provided NIS crest + HQ imagery (`assets/`).

The defining rule: **every officer registers with a numeric Service Number**, verified
against the authorised NIS personnel source *before* an account is created. Service Numbers
are digits-only and stored as strings so leading zeroes survive (`001234` ≠ `1234`).

## Layout
```
backend/    Laravel 11 API + admin portal. Runs & fully tested.
mobile/     Flutter (Riverpod, go_router, Dio). Clean Architecture, feature-first.
infrastructure/  docker-compose (nginx, php-fpm, postgres, redis, reverb, worker, minio, livekit)
docs/       ARCHITECTURE, SECURITY, PERSONNEL_INTEGRATION, DESIGN_SYSTEM, API, DEPLOYMENT, openapi.yaml
assets/     Provided NIS logo + HQ photos (source of truth; never regenerate)
.claude/    SessionStart hook (boots Postgres + backend deps in web sessions)
```

## Backend — how to run & test
```bash
cd backend
composer install
cp .env.example .env && php artisan key:generate      # first time
php artisan migrate --seed
php artisan test          # PostgreSQL-backed feature + unit suite (65 tests)
vendor/bin/pint           # code style (Laravel preset) — keep it clean
php artisan serve         # http://127.0.0.1:8000  (admin portal at /admin/login)
```
- Tests run against a **PostgreSQL** `nisconnect_testing` DB (see `phpunit.xml`), not sqlite —
  some features use PG-specific SQL (GIN full-text search, `~` regex checks).
- Demo Service Numbers (dev provider): `123456`, `001234`, `654321`. `999999` = retired
  (found but not authorised). OTP codes are returned as `debug_code` in non-production.

## Mobile — how to run & test (needs Flutter SDK, not present in cloud sessions)
```bash
cd mobile
flutter pub get
flutter analyze          # must be clean
flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```
- Provide a licensed **Arial** (or metric-compatible Liberation Sans / Arimo renamed to
  `Arial.ttf` / `Arial-Bold.ttf`) in `mobile/assets/fonts/` — the design system references
  family `Arial` everywhere.
- Realtime uses **`dart_pusher_channels`** against self-hosted Laravel **Reverb** (not
  Pusher's hosted cloud). Config via `--dart-define` (`WS_HOST`, `WS_PORT`, `WS_KEY`,
  `WS_SCHEME`, `LIVEKIT_URL`). No secrets compiled into the app.

## Conventions (follow these)
- **Controllers are thin**: Form Request (validate) → Policy/guard (authorize) → Service →
  API Resource. Business logic lives in `app/Services/**`.
- **UUID primary keys** everywhere (`HasUuidPrimaryKey` trait).
- **External systems behind interfaces**: personnel (`App\Personnel\*`), OTP sender, push
  sender, LiveKit token — selected by config, dev implementations included. Real NIS
  infra connects via `.env`, no code changes. The demo personnel provider throws in
  production.
- **No secrets in code or the mobile app.** Everything sensitive is `.env`-only.
- **Security defaults**: Argon2id for PIN/password; OTPs stored hashed; anti-enumeration
  generic responses; rate limits + per-account login lockout; private media with
  ownership-checked downloads; append-only `audit_logs` / `security_events` (never log
  message content or secrets).
- Message content is **not** claimed end-to-end encrypted — see `docs/SECURITY.md`.
- After changing backend code, run `php artisan test` **and** `vendor/bin/pint` before committing.

## Git / branch
- Active branch: `claude/nisconnect-platform-development-nawwwp`. `main` is the (empty)
  integration base.
- **Two-session workflow caution**: this project is sometimes worked on from both a cloud
  session and a local VS Code session on the same branch. Before pushing, `git fetch` and
  fast-forward/rebase to avoid clobbering the other session's commits. Prefer one active
  driver at a time.

## Key docs
Start with `docs/ARCHITECTURE.md` (system design, ERD, full schema, roadmap) and
`docs/API.md` + `docs/openapi.yaml` (endpoint contract). `README.md` has the feature status.
