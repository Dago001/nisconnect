<!-- NISconnect -->
<p align="center">
  <img src="assets/logos/nis-logo.jpg" alt="Nigeria Immigration Service" width="120"/>
</p>

<h1 align="center">NISconnect</h1>
<p align="center"><b>Secure internal communication platform for the Nigeria Immigration Service</b></p>

---

NISconnect is a secure, real-time communication platform for authorised NIS officers and
personnel — private and group messaging, voice/video calls, an organisational directory,
official channels and device management — built around a **numeric Service Number identity**
that is verified against the authorised NIS personnel source before any account is created.

> Functionality is inspired by mature messengers; the **design, architecture, security model
> and NIS personnel workflow are original**. Green brand system, Arial typography, NIS crest.

## Monorepo layout

```
nisconnect/
├── backend/          Laravel 11 + PostgreSQL API (runs & tested)
├── mobile/           Flutter (Android/iOS) app  (code; needs a Flutter SDK to build)
├── admin/            Admin portal (Laravel/Blade)
├── infrastructure/   Docker, nginx, deploy config
├── docs/             Architecture, security, DB, API, design system, deployment
├── assets/           Provided NIS crest + HQ imagery (source of truth)
└── README.md
```

## Documentation

| Doc | Contents |
|-----|----------|
| [docs/ARCHITECTURE.md](docs/ARCHITECTURE.md) | System / mobile / backend / WebRTC architecture, ERD, full DB schema, API map, onboarding sequence, folder structure, roadmap |
| [docs/SECURITY.md](docs/SECURITY.md) | Threat model, auth, OTP, RBAC + org scoping, message security (what is/ isn't encrypted), audit, recovery |
| [docs/PERSONNEL_INTEGRATION.md](docs/PERSONNEL_INTEGRATION.md) | Personnel provider interface, demo/api/database adapters, Service Number rules |
| [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md) | Green colour system, Arial typography, components, dark mode, accessibility |
| [docs/API.md](docs/API.md) | Full v1 endpoint reference and realtime channels |
| [docs/DEPLOYMENT.md](docs/DEPLOYMENT.md) | Docker stack, production checklist, backups, RPO/RTO, CI/CD |

## What is implemented and tested (backend)

- **Numeric Service Number onboarding** — verify → personnel lookup → confirm identity →
  phone OTP → set PIN → device registration → active account. Digits-only, leading zeroes
  preserved (`001234` ≠ `1234`), anti-enumeration responses.
- **Personnel provider architecture** — `PersonnelProviderInterface` with `Demo` (dev-only,
  refuses production), `Api` and `Database` adapters, selected by config.
- **Authentication** — Sanctum tokens bound to devices; login by Service Number + PIN;
  account states enforced; logout.
- **OTP** — hashed codes, expiry, attempt limits, resend delay/cap, rate limiting.
- **Directory** — rate-limited, filter-required search (no bulk enumeration), officer cards.
- **Device management** — list, revoke one, revoke all others.
- **Audit + security events**, RBAC + organisational schema, full normalised PostgreSQL
  schema (45+ tables), seeders.
- **Admin portal** (Laravel/Blade, server-rendered, RBAC-gated): dashboard stats,
  user search, suspend/reactivate, revoke devices — all audited. At `/admin/login`.
- **Docker stack** (`infrastructure/`): Nginx, PHP-FPM, PostgreSQL, Redis, Reverb
  (WebSockets), queue worker, MinIO, LiveKit; plus GitHub Actions CI.
- **58 passing tests** (feature + unit) against PostgreSQL.

Also implemented and tested: **private & group messaging** (realtime events, read
receipts, reactions, blocking), **media sharing** (private disk, signed download,
validation), **indexed message search**, **voice/video call signalling** (LiveKit
server-minted JWTs), **official channels**, **blocking/reporting**, **push
notifications** (FCM/APNs adapters + dispatch), and a **security test suite**
(enumeration rate limiting, IDOR, auth bypass, injection).

See the roadmap in [docs/ARCHITECTURE.md §12](docs/ARCHITECTURE.md#12-development-roadmap)
for full phase status. The **Flutter client** is written per the architecture (onboarding,
chat, directory, calls scaffold) but builds on a machine with the Flutter SDK — it was not
compiled in this environment. Remaining media-plane work (WebRTC device media, real
FCM/APNs credentials) connects via the documented adapters without code changes.

## Run the backend locally

Requirements: PHP 8.4 (`pgsql`, `redis`, `sodium`), Composer, PostgreSQL 16.

```bash
cd backend
composer install
cp .env.example .env
php artisan key:generate

# create databases
createdb nisconnect && createdb nisconnect_testing   # or use psql CREATE DATABASE

# point .env at your PostgreSQL (DB_* vars), then:
php artisan migrate --seed
php artisan serve            # http://127.0.0.1:8000
```

Try the onboarding flow (demo provider — Service Number `123456` or `001234`):

```bash
curl -s -X POST http://127.0.0.1:8000/api/v1/auth/verify-service-number \
  -H 'Content-Type: application/json' -d '{"service_number":"123456"}'
```

## Test

```bash
cd backend
php artisan test
```

## Security notes

- No secrets in the repo or the mobile app. Personnel/SMS/LiveKit credentials are env-only.
- The mobile app never talks to the NIS personnel source directly — only via the backend.
- Message content is protected in transit (TLS) and at rest; NISconnect does **not** claim
  end-to-end encryption by default. See [docs/SECURITY.md](docs/SECURITY.md).

## Licensing

Arial is proprietary; the app centralises the font family so a licensed Arial (or the
metric-compatible Liberation Sans / Arimo) is dropped in at deploy time without code changes.
See [docs/DESIGN_SYSTEM.md](docs/DESIGN_SYSTEM.md#3-typography--arial-only).
