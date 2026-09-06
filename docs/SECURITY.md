# NISconnect — Security Architecture

## Threat model (summary)
NISconnect is an authorised internal system for NIS personnel. Primary risks: unauthorised
account creation, Service Number enumeration, session/device compromise, privilege
escalation across organisational scope, bulk personnel data exfiltration, and abuse of media
storage. Controls below map to these.

## Authentication
- **Onboarding:** numeric Service Number → server‑side personnel lookup → identity confirm →
  phone OTP → PIN/password → device registration. No account exists until all steps pass.
- **Login:** Service Number + PIN/password, bound to a registered device; biometric unlock is
  a device‑local convenience gate over stored tokens (raw biometrics never leave the device,
  never sent to server).
- **Tokens:** Laravel Sanctum personal access tokens, per device, with abilities. Short‑lived
  access token + longer refresh token; refresh **rotates** the token and revokes the prior.
- **Password/PIN hashing:** **Argon2id** (`PASSWORD_ARGON2ID`), never plaintext.

## OTP
- Cryptographically random code, **stored only as a hash**.
- Expiry (default 5 min), max attempts, resend delay + resend cap, per‑phone + per‑IP rate
  limiting, lockout after excessive failures. Verified server‑side only.

## Authorisation — RBAC + organisational scope
- Roles: `super_admin`, `nis_admin`, `directorate_admin`, `group_admin`, `security_admin`,
  `officer`.
- Permissions are checked **server‑side** in Policies/Gates for every protected action.
  Hiding UI is never the control.
- **Scoping:** admin roles carry a scope (`scope_type`,`scope_id`). A Directorate admin can
  only act within their Directorate; cross‑scope access is denied and audited.

## Anti‑enumeration (Service Number)
- Verify + directory search are rate limited per user/IP and audited.
- Not‑found and not‑authorised return an identical generic response and comparable timing.
- Directory search caps result counts and blocks bulk scraping patterns.

## Transport & headers
- HTTPS/TLS enforced; HSTS. Secure headers: CSP, `X-Content-Type-Options: nosniff`,
  `X-Frame-Options: DENY`, `Referrer-Policy: strict-origin-when-cross-origin`,
  `Permissions-Policy`.

## Input & injection
- All input validated via Form Requests. Eloquent/parameter binding prevents SQL injection.
- Output encoding + CSP mitigate XSS in the admin portal. File uploads validated (MIME +
  extension + size) and scanned (adapter) before becoming available.

## Media & storage
- Private object storage (MinIO/S3), **never public web dirs**. Access via short‑lived signed
  URLs after ownership/policy check. MIME/extension/size validation; malware‑scan adapter;
  server‑side thumbnails.

## Data protection
- **In transit:** TLS. **At rest:** encrypted DB volume; sensitive columns (phone, tokens,
  keys) encrypted via Laravel `encrypted` casts. Secrets only in server env / secret manager,
  never in the mobile app or repo.
- **Local (mobile):** tokens/PIN salt in Android Keystore / iOS Keychain via
  `flutter_secure_storage`. Never plaintext SharedPreferences.

## Message security — what is and isn't encrypted
- Messages are protected **in transit by TLS** and **at rest** server‑side (encrypted volume;
  bodies stored in PostgreSQL under DB‑level encryption).
- **NISconnect does not claim end‑to‑end encryption by default.** As an authorised internal
  system with lawful administrative/audit obligations, message content is server‑readable for
  the platform's own processing and administrator functions defined by NIS policy.
- **Keys:** TLS keys at the edge; at‑rest encryption keys held by the platform/secret manager.
- **Metadata retained:** sender, conversation, timestamps, delivery/read status, attachment
  references — required for delivery, search and audit.
- **Administrators can:** manage accounts/devices/groups/channels, review reports, view audit
  and security events. **Administrators do not** get a bulk message‑content export tool by
  default; any content access is policy‑gated and audited.
- **Future E2EE:** designated channels can adopt a reviewed protocol (e.g. Signal) later;
  no custom crypto will be invented. This document will be updated when/if enabled.

## Auditing
- Append‑only `audit_logs` (login, logout, failed login, account/device lifecycle, Service
  Number verification, admin actions, group changes) and `security_events` (new device,
  credential change, suspicious activity). Admin actions are themselves audited. Message
  **contents** are not recorded in audit logs.

## Account recovery
- Never by Service Number alone. Requires additional factors (registered phone + OTP, an
  existing authorised device, or administrator‑assisted recovery). All recovery actions
  logged and trigger a security event/notification.

## Security alerts to officers
- New device sign‑in, PIN/password change, admin security action, suspicious activity —
  delivered as push + in‑app notification with a "secure your account" path.
