# NISconnect — Go-Live Guide

This guide takes NISconnect from this repository to a live service on **one Linux server**:
the website, the admin portal, the API and real-time messaging on your own domain, plus the
Android and iOS apps in the stores. It is written for someone who has not run a server
before. Do the parts in order; every command can be copied and pasted.

**What you end up with**

| Address | What it is |
|---|---|
| `https://YOUR-DOMAIN/` | The officer web app (same app as on phones) |
| `https://YOUR-DOMAIN/admin` | Admin portal (Super Admin, officers, personnel, system health) |
| `https://YOUR-DOMAIN/api/v1` | API used by the Android and iOS apps |
| `wss://YOUR-DOMAIN/app/...` | Real-time messaging (websockets) |
| `https://YOUR-DOMAIN/up` | Health check (answers when the backend is running) |

**How it fits together.** Everything runs in Docker from `infrastructure/production/`:

```
Internet ──443──> caddy ─┬─ /api/*, /admin*, /broadcasting/*, /sanctum/*,
 (HTTPS, automatic       │  /portal-assets/*, /up, /robots.txt ──> app (Laravel)
  Let's Encrypt          ├─ /app/*, /apps/* (websockets) ─────────> reverb
  certificates)          └─ everything else ──> web app files (index.html fallback)

app, worker (queue), scheduler, reverb ──> postgres (database)  +  redis (cache/queue)
```

Only Caddy is reachable from the internet (ports 80 and 443). The database and Redis are on a
private Docker network and are never exposed.

> Words in `CAPITALS` such as `YOUR-DOMAIN` or `SERVER-IP` are placeholders: replace them with
> your own values.

---

## Part 1 — Get a domain and a server

### 1. Choose the address

Pick the address officers will use, for example `connect.immigration.gov.ng`. You need
control of the domain's DNS (for a `.gov.ng` name, ask the team that manages the domain).

### 2. Rent a server (VPS)

Any provider is fine (for example DigitalOcean, Hetzner, AWS Lightsail, Linode, or a
government data centre). Choose:

| | Minimum | Recommended |
|---|---|---|
| CPU | 2 vCPU | 4 vCPU |
| Memory | 4 GB | 8 GB |
| Disk | 40 GB SSD | 80 GB SSD or more (media uploads grow over time) |
| System | **Ubuntu 24.04 LTS** | **Ubuntu 24.04 LTS** |

When creating it, add your SSH key if the provider offers it (more secure than a password).
Write down the server's **public IPv4 address** (`SERVER-IP`).

> On a 4 GB server, use the "prebuilt web app" option in step 9 instead of compiling the web
> app on the server (compiling needs about 3 GB of free memory).

### 3. Point the domain at the server

In your DNS provider, create an **A record**:

| Type | Name | Value | TTL |
|---|---|---|---|
| A | `connect` (or `@` for the bare domain) | `SERVER-IP` | 300 |

If the server also has an IPv6 address, add an **AAAA** record too; otherwise do not add one.
Check it worked (it can take a few minutes to an hour):

```bash
nslookup YOUR-DOMAIN
```

It must show `SERVER-IP`. HTTPS certificates are only issued once this is true.

---

## Part 2 — Prepare the server

### 4. Log in

From your computer (Windows: use PowerShell or Windows Terminal):

```bash
ssh root@SERVER-IP
```

(Some providers create a user called `ubuntu` instead of `root`: use `ssh ubuntu@SERVER-IP`
and put `sudo` in front of the commands below.)

### 5. Update the system, turn on the firewall and automatic security updates

```bash
apt update && apt -y upgrade
apt -y install ufw unattended-upgrades git curl
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw allow 443/udp
ufw --force enable
dpkg-reconfigure -f noninteractive unattended-upgrades
timedatectl set-timezone Africa/Lagos
```

On a 4 GB server also add swap space (helps during builds):

```bash
fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

### 6. Install Docker

```bash
curl -fsSL https://get.docker.com | sh
docker --version
docker compose version
```

Both commands must print a version (Docker Compose v2.24 or newer).

### 7. Download NISconnect

> **Before you start:** the go-live code is in pull request #1 on GitHub. Merge it first
> (open the pull request, click **Merge**). The commands below then download the merged
> code. Until it is merged, add `-b claude/web-ios-android-targets` after `git clone`.

```bash
mkdir -p /opt && cd /opt
git clone https://github.com/Dago001/nisconnect.git
cd /opt/nisconnect/infrastructure/production
```

If the repository is private, GitHub asks for a username and password: use your GitHub
username and a **personal access token** (GitHub > Settings > Developer settings > Personal
access tokens, read-only "Contents" access to this repository) as the password. To deploy a
particular release, run `git checkout v1.0.0` (the tag name) after cloning.

---

## Part 3 — Configure and start

### 8. Fill in the settings file

```bash
cd /opt/nisconnect/infrastructure/production
cp .env.example .env
chmod 600 .env
```

Create three strong random passwords and note them:

```bash
openssl rand -hex 24   # database password
openssl rand -hex 24   # Redis password
openssl rand -hex 32   # websocket secret
```

Open the file with `nano .env` and set at least:

| Setting | Value |
|---|---|
| `APP_DOMAIN` | your domain, e.g. `connect.immigration.gov.ng` (no `https://`) |
| `ACME_EMAIL` | an e-mail address that receives certificate warnings |
| `DB_PASSWORD` | first random value |
| `REDIS_PASSWORD` | second random value |
| `REVERB_APP_SECRET` | third random value |
| `PERSONNEL_PROVIDER` + its settings | see step 15 (you can start with the values there later) |
| `SMS_ENDPOINT`, `SMS_API_KEY` | see step 16 |

Every setting is explained inside the file. Save with `Ctrl+O`, `Enter`, then `Ctrl+X`.

Leave `APP_KEY` empty for now — the next step creates it.

### 9. Choose how the web app is built

The website (officer web app) is compiled from `mobile/` into the Caddy image.

* **Recommended on 8 GB servers — build on the server:** keep `WEB_SOURCE=build` in `.env`.
  Nothing else to do; the first build takes 5–15 minutes.
* **On small servers — use a prebuilt web app:** download `nisconnect-web-vX.Y.Z.zip` from
  the repository's **Releases** page (produced by the release workflow, Part 6), then:

  ```bash
  cd /opt/nisconnect/infrastructure/production
  apt -y install unzip
  unzip -o /path/to/nisconnect-web-vX.Y.Z.zip -d web/
  ls web/index.html                       # must exist
  sed -i 's/^WEB_SOURCE=.*/WEB_SOURCE=prebuilt/' .env
  ```

  You can also build it on any computer with Flutter:
  `cd mobile && flutter build web --release --no-web-resources-cdn` and copy the contents of
  `mobile/build/web/` into `infrastructure/production/web/`.

Either way, the web app needs no server address: it automatically uses the domain it is
opened from.

### 10. Create the application key

```bash
cd /opt/nisconnect/infrastructure/production
docker compose run --rm --no-deps app php artisan key:generate --show
```

The first time, this builds the backend image (a few minutes). It then prints a line like
`base64:AbCd...=`. Copy the **whole** line into `.env` as `APP_KEY=base64:AbCd...=`.

> Keep this key safe and never change it. Changing it signs everyone out and makes encrypted
> data (such as admin two-factor secrets) unreadable. Store a copy of the whole `.env` file in
> your organisation's password manager.

### 11. Start everything

```bash
docker compose up -d --build
```

Watch progress (press `Ctrl+C` to stop watching; the services keep running):

```bash
docker compose ps
docker compose logs -f app caddy
```

After a few minutes `docker compose ps` should show `app` and `reverb` as **healthy** and
every service as **running**. On first start the app automatically creates the database
tables and the built-in roles, permissions and organisation structure (`RUN_MIGRATIONS=true`,
`RUN_SEEDERS=true`). You can also run this by hand at any time — it is safe to repeat:

```bash
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force
```

Check it from your computer: open `https://YOUR-DOMAIN/up` — you should see a green "Application
up" page with a valid padlock. If the certificate fails, check step 3 and run
`docker compose logs caddy`.

### 12. Create the first Super Administrator

Use a real Service Number (digits only) of the officer who will administer the system:

```bash
docker compose exec app php artisan nis:create-admin SERVICE-NUMBER
```

It asks for the full name (if the account does not exist yet) and a strong password
(upper and lower case letters, a number and a symbol). Other roles can be given with
`--role=`, e.g. `--role=nis_admin` or `--role=security_admin`.

### 13. Sign in and turn on two-factor authentication

1. Open `https://YOUR-DOMAIN/admin` and sign in with the Service Number and password.
2. Go to **Account > Two-factor authentication** (`/admin/account/two-factor`), scan the QR
   code with an authenticator app (Google Authenticator, Microsoft Authenticator, Authy…),
   enter the 6-digit code, and **store the recovery codes** somewhere safe.
3. Open **System** (`/admin/system`): it shows the server details and a production
   checklist (debug off, https, real personnel source, SMS, mail…). Fix anything marked as
   failing.

### 14. Open the web app

Go to `https://YOUR-DOMAIN/`. Officers register with their Service Number once the personnel
source (step 15) and SMS (step 16) are connected.

---

## Part 4 — Connect the real NIS services

After changing `.env`, always apply it with:

```bash
cd /opt/nisconnect/infrastructure/production
docker compose up -d
```

(Containers are recreated with the new settings in a few seconds.)

### 15. Personnel source (Service Number verification)

Officers can only register when their Service Number is found **and authorised** in the
official personnel source. The demo source is refused in production. Choose one:

**A. Personnel web service (API)** — NISconnect calls
`GET {PERSONNEL_API_BASE_URL}/personnel/{service_number}` with the header
`Authorization: Bearer {PERSONNEL_API_KEY}`. A 404 means "not found". The expected JSON is
described in `docs/PERSONNEL_INTEGRATION.md`.

```dotenv
PERSONNEL_PROVIDER=api
PERSONNEL_API_BASE_URL=https://personnel.internal.example.gov.ng/v1
PERSONNEL_API_KEY=the-key-issued-by-the-personnel-team
```

**B. Read-only database connection** (PostgreSQL) — the table (default `personnel`) needs a
`service_number` column plus the fields listed in `docs/PERSONNEL_INTEGRATION.md`. Ask for a
**read-only** database account.

```dotenv
PERSONNEL_PROVIDER=database
PERSONNEL_DB_HOST=10.0.0.20
PERSONNEL_DB_PORT=5432
PERSONNEL_DB_DATABASE=personnel
PERSONNEL_DB_USERNAME=nisconnect_ro
PERSONNEL_DB_PASSWORD=...
PERSONNEL_DB_TABLE=personnel
PERSONNEL_DB_SSLMODE=require
```

If the personnel system is on a private network, the server needs a route to it (VPN or
firewall rule) — arrange this with the network team. Admins can also import personnel from
CSV in the admin portal. Set `PERSONNEL_SERVICE_NUMBER_LENGTH` if all Service Numbers have the
same number of digits.

### 16. SMS for one-time codes

Registration and recovery send a 6-digit code by SMS. NISconnect sends:

```
POST {SMS_ENDPOINT}
Authorization: Bearer {SMS_API_KEY}
Content-Type: application/json

{"to": "PHONE-NUMBER", "from": "{SMS_SENDER_ID}", "message": "Your NISconnect verification code is 123456. ..."}
```

```dotenv
OTP_DRIVER=sms
OTP_EXPOSE_IN_RESPONSE=false
SMS_ENDPOINT=https://sms-gateway.example.gov.ng/api/send
SMS_API_KEY=...
SMS_SENDER_ID=NISconnect
```

If your SMS provider expects a different request format, a developer adapts
`backend/app/Services/Otp/SmsOtpSender.php` (a small change), or put a tiny relay in front of
the provider. Test by registering a test officer and confirming the SMS arrives.
**Never** set `OTP_EXPOSE_IN_RESPONSE=true` in production.

### 17. E-mail

```dotenv
MAIL_MAILER=smtp
MAIL_HOST=smtp.example.gov.ng
MAIL_PORT=587
MAIL_USERNAME=no-reply@example.gov.ng
MAIL_PASSWORD=...
MAIL_FROM_ADDRESS=no-reply@example.gov.ng
MAIL_FROM_NAME=NISconnect
```

### 18. Push notifications (FCM / APNs) — read before enabling

Keep `PUSH_DRIVER=log` for launch. Push notifications need further developer work before
they can be switched on:

* The apps do not yet include Firebase configuration: a developer must create a Firebase
  project, add `google-services.json` (Android) and `GoogleService-Info.plist` (iOS) — e.g.
  with `flutterfire configure` — and call `Firebase.initializeApp()` at start-up.
* The backend's HTTP push sender sends `FCM_SERVER_KEY` as a fixed bearer token to
  `FCM_ENDPOINT`. Google's current FCM API (HTTP v1,
  `https://fcm.googleapis.com/v1/projects/PROJECT-ID/messages:send`) needs a short-lived
  OAuth token generated from a service-account key, and APNs needs a signed JWT; the sender
  must be extended to generate these before `PUSH_DRIVER=http` works reliably.

Messages still arrive instantly while the app is open (websockets), and the app shows unread
messages when opened.

### 19. Voice and video calls (optional)

Calls use a LiveKit server, which is **not** part of this stack. Use LiveKit Cloud or run a
LiveKit server on a separate machine (it needs its own UDP ports), then set `LIVEKIT_HOST`,
`LIVEKIT_API_KEY`, `LIVEKIT_API_SECRET` in `.env`, and build the apps with
`--dart-define=LIVEKIT_URL=wss://your-livekit-host`.

---

## Part 5 — Publish the Android app (Google Play)

### 20. Create the signing key (once — guard it with your life)

On any computer with Java (or Android Studio) installed:

```bash
keytool -genkey -v -keystore nisconnect-upload.jks -keyalg RSA -keysize 2048 \
  -validity 10000 -alias upload
```

Choose a strong password. **Back up `nisconnect-upload.jks` and the password in at least two
safe places** (e.g. the organisation's password manager and an encrypted offline drive).
Never commit it to Git (the repository ignores `*.jks` and `key.properties`).

With Play App Signing (the default), Google keeps the real app-signing key and this file is
your *upload* key; if it is lost, Google support can reset it, but it takes time.

### 21. Build the signed app

**Option A — GitHub Actions (no Android tools needed):** add these repository secrets
(GitHub > Settings > Secrets and variables > Actions > New repository secret):

| Secret | Value |
|---|---|
| `ANDROID_KEYSTORE_BASE64` | output of `base64 -w0 nisconnect-upload.jks` (macOS: `base64 -i nisconnect-upload.jks`) |
| `ANDROID_KEYSTORE_PASSWORD` | the keystore password |
| `ANDROID_KEY_ALIAS` | `upload` |
| `ANDROID_KEY_PASSWORD` | the key password (same as the keystore password if you did not choose another) |

Then publish a release as described in Part 6. You get `nisconnect-vX.Y.Z.aab` (for Play)
and `nisconnect-vX.Y.Z.apk` (for direct installation). Without the secrets the files end in
`-debug-signed` and Google Play will reject them.

**Option B — on your own computer** with Flutter and Android Studio: create
`mobile/android/key.properties`:

```properties
storeFile=/full/path/to/nisconnect-upload.jks
storePassword=...
keyAlias=upload
keyPassword=...
```

```bash
cd mobile
flutter build appbundle --release \
  --dart-define=API_BASE_URL=https://YOUR-DOMAIN/api/v1 \
  --dart-define=WS_HOST=YOUR-DOMAIN --dart-define=WS_PORT=443 --dart-define=WS_SCHEME=wss
# result: build/app/outputs/bundle/release/app-release.aab
```

Release builds only allow HTTPS connections.

### 22. Upload to Google Play

1. Create a Google Play developer account at <https://play.google.com/console> (one-time
   fee). For a government organisation, register as an organisation.
2. **Create app**: name *NISconnect*, app, free.
3. Complete **App content** (privacy policy URL, data safety, target audience, content
   rating). An internal staff app can be distributed only to your organisation via a
   *closed testing* track or **Managed Google Play** (private app) — ask your IT team which
   you use.
4. **Testing > Internal testing > Create release** → upload the `.aab`, add testers' e-mails,
   roll out, and test on real phones.
5. When ready, promote the release to **Production** (or your private track).

The package name is `ng.gov.immigration.nisconnect`. Every new upload needs a higher build
number; the release workflow uses the GitHub run number automatically.

---

## Part 6 — Release builds with GitHub Actions

The workflow `.github/workflows/release.yml` builds the web app, Android and iOS.

1. In GitHub > Settings > Secrets and variables > Actions > **Variables**, add
   `SERVER_URL` = `https://YOUR-DOMAIN`. (Optional: `WS_KEY` if you changed
   `REVERB_APP_KEY`.)
2. Update `version:` in `mobile/pubspec.yaml` and `appVersion` in
   `mobile/lib/core/config/app_config.dart` if you want, commit, then tag and push:

   ```bash
   git tag v1.0.0
   git push origin v1.0.0
   ```

3. Watch **Actions > Release**. When it finishes, **Releases > v1.0.0** holds:
   `nisconnect-web-v1.0.0.zip`, `nisconnect-v1.0.0.apk`, `nisconnect-v1.0.0.aab` and, if iOS
   signing is set up, `nisconnect-v1.0.0.ipa`.

You can also run it by hand (Actions > Release > Run workflow) and type a `server_url`; the
files are then under the run's **Artifacts**.

---

## Part 7 — Publish the iOS app (App Store / TestFlight)

You need an **Apple Developer Program** membership (enrol as an organisation at
<https://developer.apple.com/programs/>; it needs a D-U-N-S number and takes a few days).
A Mac is helpful but not required — the release workflow builds on GitHub's Macs.

### 23. Register the app

1. <https://developer.apple.com/account> > **Identifiers** > **+** > App IDs > App:
   Bundle ID **explicit** `ng.gov.immigration.nisconnect`, description *NISconnect*. Tick
   **Push Notifications** (for later).
2. <https://appstoreconnect.apple.com> > **Apps** > **+** > New App: platform iOS, name
   *NISconnect*, the bundle ID above, SKU `nisconnect`.
3. Note your **Team ID** (Developer account > Membership details).

### 24. Create the distribution certificate and profile

Without a Mac (on any Linux/WSL machine with `openssl`):

```bash
openssl req -new -newkey rsa:2048 -nodes -keyout ios_dist.key -out ios_dist.csr \
  -subj "/emailAddress=you@example.gov.ng/CN=Nigeria Immigration Service/C=NG"
```

1. Developer account > **Certificates** > **+** > **Apple Distribution** > upload
   `ios_dist.csr` > download `distribution.cer`.
2. Convert to a `.p12` (choose an export password):

   ```bash
   openssl x509 -inform der -in distribution.cer -out ios_dist.pem
   openssl pkcs12 -export -legacy -inkey ios_dist.key -in ios_dist.pem -out ios_dist.p12
   ```

   (With a Mac: create the certificate in Xcode or Keychain Access and export it as `.p12`.)
3. **Profiles** > **+** > **App Store Connect** > App ID `ng.gov.immigration.nisconnect` >
   the certificate > name it e.g. *NISconnect App Store* > download the `.mobileprovision`.

Store the `.p12`, its password and the private key safely.

### 25. Add the iOS secrets to GitHub

| Secret | Value |
|---|---|
| `IOS_CERTIFICATE_P12_BASE64` | `base64 -w0 ios_dist.p12` |
| `IOS_CERTIFICATE_PASSWORD` | the `.p12` export password |
| `IOS_PROVISIONING_PROFILE_BASE64` | `base64 -w0 NISconnect_App_Store.mobileprovision` |
| `IOS_TEAM_ID` | your 10-character Team ID |

Optional, to upload to TestFlight automatically: App Store Connect > Users and Access >
Integrations > **App Store Connect API** > create a key with *App Manager* access, download
`AuthKey_XXXX.p8`, and add `APP_STORE_CONNECT_KEY_ID`, `APP_STORE_CONNECT_ISSUER_ID` and
`APP_STORE_CONNECT_API_KEY_BASE64` (`base64 -w0 AuthKey_XXXX.p8`).

The workflow writes the export options (`app-store-connect`, manual signing, your profile)
itself. Without these secrets it only builds an unsigned app to prove the code compiles.

### 26. Release

1. Push a tag (Part 6). The `.ipa` appears on the release (and in TestFlight if the API key
   is set; otherwise upload it with Apple's **Transporter** app on a Mac).
2. App Store Connect > your app > **TestFlight**: answer the export-compliance question
   (already answered in the app: `ITSAppUsesNonExemptEncryption = false`, because the app
   only uses standard HTTPS/TLS and the operating system's own encryption), add internal
   testers and test.
3. For staff-only distribution use **TestFlight**, **Apple Business Manager custom apps**
   (private distribution to your organisation), or a public App Store listing. Fill in the
   App Privacy details, screenshots and review notes (give Apple a demo Service Number and a
   way to receive its code), then **Submit for Review**.

Building on your own Mac instead: open `mobile/ios/Runner.xcworkspace` in Xcode, select your
team under *Signing & Capabilities*, then
`flutter build ipa --dart-define=API_BASE_URL=https://YOUR-DOMAIN/api/v1 --dart-define=WS_HOST=YOUR-DOMAIN --dart-define=WS_PORT=443 --dart-define=WS_SCHEME=wss`
and upload `build/ios/ipa/*.ipa` with Transporter.

---

## Part 8 — Run the service

### 27. Updating to a new version

```bash
cd /opt/nisconnect
sudo infrastructure/production/backup.sh            # always back up first
git fetch --tags
git checkout v1.1.0                                  # or: git pull  (to follow a branch)
cd infrastructure/production
docker compose up -d --build
docker compose ps
```

Database changes are applied automatically when the new app container starts. If you use
`WEB_SOURCE=prebuilt`, unzip the new web zip into `web/` first. Phones update through the
stores. Clean up old images now and then: `docker image prune -f`.

### 28. Backups

`infrastructure/production/backup.sh` saves the database (`pg_dump`) and the uploaded media,
and deletes copies older than `BACKUP_RETENTION_DAYS` (default 14) from `BACKUP_DIR`
(default `/var/backups/nisconnect`).

```bash
cd /opt/nisconnect/infrastructure/production
sudo ./backup.sh                  # back up now
sudo ./backup.sh --install-cron   # every night at 02:30 (log: /var/log/nisconnect-backup.log)
sudo ./backup.sh --list
```

**Copy backups off the server** — a backup on the same server is lost with the server. For
example, from another machine every day:

```bash
rsync -avz root@SERVER-IP:/var/backups/nisconnect/ /secure/offsite/nisconnect/
```

Also keep a copy of `infrastructure/production/.env` (it holds `APP_KEY`) in the password
manager. Without `APP_KEY` a restored database is only partly usable.

**Restore** (for example onto a fresh server after steps 4–11):

```bash
cd /opt/nisconnect/infrastructure/production
sudo ./backup.sh --restore /var/backups/nisconnect/nisconnect-db-YYYYMMDD-HHMMSS.dump \
                           /var/backups/nisconnect/nisconnect-media-YYYYMMDD-HHMMSS.tar.gz
```

It asks you to type `RESTORE`, stops the app, replaces the database (and media), and starts
everything again. Practise a restore on a spare server every few months.

### 29. Monitoring

* **Uptime:** point a free uptime monitor (UptimeRobot, Better Stack, Uptime Kuma…) at
  `https://YOUR-DOMAIN/up`; it should alert you by e-mail/SMS when it stops answering.
* **Admin portal:** `/admin/system` shows server details and the production checklist.
* **On the server:**

  ```bash
  cd /opt/nisconnect/infrastructure/production
  docker compose ps                       # all running / healthy?
  docker compose logs --tail=200 app      # recent backend logs (also: worker, reverb, caddy)
  df -h                                   # disk space (keep at least 20% free)
  docker stats --no-stream                # memory/CPU per service
  ```

* Restart one service: `docker compose restart worker`. Restart everything:
  `docker compose restart`. The stack starts by itself after a server reboot.

### 30. Security checklist

- [ ] `.env` has `APP_ENV=production`, `APP_DEBUG=false`, `OTP_EXPOSE_IN_RESPONSE=false`,
      `SESSION_SECURE_COOKIE=true`, and `PERSONNEL_PROVIDER` is `api` or `database`.
- [ ] Strong unique values for `DB_PASSWORD`, `REDIS_PASSWORD`, `REVERB_APP_SECRET`; `APP_KEY`
      generated once and backed up; `.env` is `chmod 600` and never committed or shared.
- [ ] Only ports 22, 80 and 443 are open (`ufw status`). Database and Redis have no published
      ports (`docker compose ps` shows ports only for `caddy`).
- [ ] SSH: key login only — after confirming your key works, set `PasswordAuthentication no`
      in `/etc/ssh/sshd_config` and run `systemctl restart ssh`.
- [ ] Every admin has two-factor authentication on; the first Super Admin's recovery codes are
      stored safely; remove admin accounts that are no longer needed.
- [ ] `https://YOUR-DOMAIN` shows a valid certificate; `http://` redirects to `https://`.
- [ ] Nightly backups installed, copied off the server, and a restore has been tested.
- [ ] Android keystore and iOS certificate backed up; signing secrets only in GitHub
      Secrets.
- [ ] Automatic security updates on (step 5); run `apt upgrade` and update NISconnect
      regularly.
- [ ] Uptime monitor configured for `/up`.
- [ ] Read `docs/SECURITY.md` (what is and is not encrypted, audit logs).

### Troubleshooting

| Problem | What to check |
|---|---|
| Browser says the certificate is invalid | DNS A record points at this server (`nslookup`); ports 80/443 open; `docker compose logs caddy` |
| `docker compose up` says "Set APP_DOMAIN in .env" (or another variable) | You are not in `infrastructure/production`, or `.env` is missing that value |
| `app` stays "unhealthy" | `docker compose logs app` — usually a wrong `APP_KEY` or database password |
| Changed `DB_PASSWORD` after the first start and now the app cannot connect | The database keeps the password it was created with. Change it back, or change it inside Postgres too |
| Messages only appear after refreshing | `docker compose logs reverb worker`; `REVERB_APP_KEY` must equal the apps' `WS_KEY` |
| "Verification service unavailable" when registering | Personnel source settings (step 15) and network access to it |
| No SMS codes | `SMS_*` settings (step 16); `docker compose logs worker app` |
| Web build fails with "killed" / out of memory | Use the prebuilt web app (step 9) or add swap (step 5) |

Other deployment notes: `docs/DEPLOYMENT.md`. Architecture: `docs/ARCHITECTURE.md`.
