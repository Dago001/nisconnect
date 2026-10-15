# Hosting the web app on Netlify

Netlify serves the **Flutter web client**. It cannot run the rest of
NISconnect: Netlify hosts static files, while the Laravel API needs PHP and
PostgreSQL, and Reverb needs a long-lived websocket process.

```
Netlify            your API host (Render, or infrastructure/production)
  web client  ──▶  Laravel API  +  PostgreSQL  +  Reverb websockets
                   admin portal at /admin/login
```

So Netlify is one half of a deployment, not a replacement for one. Stand the
API up first — the site is useless without it.

---

## 1. Deploy the API first

Either option gives you an HTTPS address:

- **Render** (free, for testing) — `render.yaml` in the repo root. Render
  dashboard → New → Blueprint → pick this repo → Apply. You get
  `https://nisconnect-api.onrender.com`. Demo personnel data, OTPs shown in
  the app, **no websockets, calls or push**.
- **Docker** (production) — `infrastructure/production/docker-compose.yml`:
  Caddy with HTTPS, API, worker, Reverb, PostgreSQL, Redis, backups.

Note the address. Everything below needs it.

---

## 2. Connect the repository

Netlify → **Add new site → Import an existing project** → GitHub →
`Dago001/nisconnect`, branch `claude/nisconnect-platform-development-nawwwp`.

Leave the build command and publish directory **empty** — `netlify.toml` in the
repo root sets both. It installs a pinned Flutter SDK (3.47.2), builds, and
publishes `mobile/build/web`.

---

## 3. Set the environment variables

**This is the step that silently breaks the site if you skip it.** Site
configuration → Environment variables:

| Variable | Value (example) |
|---|---|
| `API_BASE_URL` | `https://nisconnect-api.onrender.com/api/v1` |
| `WS_SCHEME` | `wss` |
| `WS_HOST` | `nisconnect-api.onrender.com` |
| `WS_PORT` | `443` |
| `WS_KEY` | your Reverb app key |

Without `API_BASE_URL` the app falls back to the origin it was served from, so
it calls `https://<your-site>.netlify.app/api/v1`, which is Netlify's 404 page.
The symptom is that sign-in fails with a vague network error and nothing in the
console names the real cause.

`API_BASE_URL` **must end in `/api/v1`**. The other three only matter once a
Reverb server exists; Render has none, so live chat and presence stay off there
while the REST app works normally.

Then **Deploys → Trigger deploy → Clear cache and deploy site**. These values
are compiled into the bundle, so changing one needs a rebuild, not a restart.

---

## 4. Allow the browser to call the API

The web app and the API are on different origins, so the API must send CORS
headers naming the Netlify site, or the browser blocks every request.

In the API's environment (`backend/.env`, or Render's variables):

```dotenv
CORS_ALLOWED_ORIGINS=https://your-site.netlify.app
```

Comma-separate several, and add your custom domain once you have one. Then
`php artisan config:clear` (Render: redeploy).

Netlify gives each pull request a deploy preview on its own generated
subdomain. To let those through without listing each one:

```dotenv
CORS_ALLOWED_ORIGIN_PATTERNS=^https://[a-z0-9-]+--your-site\.netlify\.app$
```

The default is to allow **no** cross-origin access, which is correct for
`infrastructure/production` — Caddy serves the web app and the API from one
domain, so CORS never applies there.

A missing origin shows up as "blocked by CORS policy" in the browser console
and nowhere in the API's logs, which is why it is easy to misdiagnose as a
Netlify problem. It is a server setting.

---

## 5. Check it

1. Open the site. The welcome screen should render the NIS crest.
2. Open DevTools → Network, sign in with a demo Service Number
   (`123456`, `001234`, `654321` on the Render test API).
3. The request should go to **your API host**, not the Netlify domain.

| Symptom | Cause |
|---|---|
| Blank page, console mentions `main.dart.js` | Deploy published the wrong directory. `netlify.toml` sets `mobile/build/web` |
| 404 on refresh at `/chats` | The SPA redirect is missing — it is in `netlify.toml`, confirm the file is at the repo root |
| Sign-in fails, requests go to the netlify.app domain | `API_BASE_URL` not set, or set without `/api/v1` |
| "blocked by CORS policy" | The API is not allowing the Netlify origin (step 4) |
| Works, but no live messages | Expected without Reverb. Render has none |
| Build fails fetching Flutter | Netlify's build image has no Flutter; the clone is in `netlify.toml`. Check the deploy log for the `git clone` step |

---

## What does not work on Netlify

- **Push notifications** — the service worker and VAPID key are not wired up
  (see [PUSH_NOTIFICATIONS.md](PUSH_NOTIFICATIONS.md)). Android and iOS have them.
- **Biometric unlock** — no browser equivalent; the app hides it.
- **Voice notes** — the recorder has no web implementation.
- **Calls** — need a LiveKit server, independent of Netlify.
- **The admin portal** — Blade templates served by Laravel, so it lives at
  `https://<your-api-host>/admin/login`, not on Netlify.

---

## Custom domain

Netlify → Domain management → Add domain. Netlify issues the TLS certificate.
Afterwards, add the new origin to `CORS_ALLOWED_ORIGINS` on the API and
redeploy the site, or requests from the new domain will be blocked.
