# Going live on Render — step by step

This guide puts NISconnect on [Render](https://render.com), a hosting company that runs the
servers for you: no Linux, no commands, HTTPS included. Everything lives on one address:

| Address | What it is |
|---|---|
| `https://YOUR-ADDRESS/` | the NISconnect web app for officers (any browser, phone or computer) |
| `https://YOUR-ADDRESS/download` | the page where officers download the Android app (and iPhone instructions) |
| `https://YOUR-ADDRESS/admin` | the Super Admin portal |

`YOUR-ADDRESS` is the address Render gives you, for example `nisconnect.onrender.com`, or your
own domain (Part F).

The setup is described in `render.production.yaml` at the top of the repository. The free test
server (`render.yaml`) is separate and keeps working.

**What it costs.** About US$45 a month: the web service (Standard, ~$25), the database
(Basic 1 GB, ~$19) and a 5 GB disk for photos, files and the app downloads (~$1.25). Check
<https://render.com/pricing> for current prices. You can cancel any time.

**Where the data is.** Render's Frankfurt (Germany) region, the closest to Nigeria. If NIS
policy requires data to stay in Nigeria, use your own server instead (docs/GO_LIVE.md).

---

## Part A — Before you start (10 minutes)

1. **Merge the pull request.** On GitHub open *Dago001/nisconnect → Pull requests → #1* and
   click **Merge pull request**. Render then uses the main branch. (If you don't merge, choose
   the branch `claude/web-ios-android-targets` in step 5 instead.)
2. **Create a Render account** at <https://dashboard.render.com/register>. Choose
   **Sign up with GitHub** — it makes connecting the code easier.
3. **Add a payment card**: Render dashboard → your name (top right) → **Billing**.
4. **Pick the first Super Administrator**: their Service Number (digits only), full name, and
   a temporary password of at least 12 characters with upper- and lower-case letters, a
   number and a symbol, e.g. `Green-Eagle-2026!`. It must be changed at first sign-in.

## Part B — Create the service (5 minutes, then about 20 minutes of waiting)

5. In the Render dashboard click **New +** → **Blueprint**. If asked, connect GitHub and give
   Render access to the **nisconnect** repository. Select **Dago001/nisconnect** and the
   branch (the main branch after step 1).
6. **Blueprint Name:** `NISconnect`. **Blueprint Path:** type `render.production.yaml`.
7. Render lists what it will create: a web service **nisconnect**, a disk and a database
   **nisconnect-live-db**. Fill in the three values it asks for:
   - `NIS_ADMIN_SERVICE_NUMBER` – the Super Administrator's Service Number
   - `NIS_ADMIN_NAME` – their full name
   - `NIS_ADMIN_PASSWORD` – the temporary password from step 4
8. Click **Apply** (or **Deploy Blueprint**).
9. Open the **nisconnect** service and watch **Logs**. The first build takes 15–25 minutes
   (it compiles the web app). It is ready when the top of the page shows **Live**.
10. At the top of the service page you'll see its address, e.g.
    `https://nisconnect.onrender.com` (Render may add a few letters if the name is taken).
    **Write it down** — this is `YOUR-ADDRESS`.

## Part C — First sign-in and security (10 minutes)

11. Open `https://YOUR-ADDRESS/admin`, sign in with the Service Number and temporary password.
    You are asked to choose a new password straight away.
12. Turn on two-factor authentication: top-right menu → **Two-factor authentication** → scan
    the QR code with Google Authenticator or Microsoft Authenticator → enter the code →
    **save the recovery codes** somewhere safe.
13. **Settings** → turn on **Require two-factor authentication for administrators**.
14. Back in Render: service **nisconnect** → **Environment** → delete `NIS_ADMIN_PASSWORD`
    (no longer needed; deleting it does not change your new password) → **Save**.
15. **System health** shows what is configured. Expected warnings for now: sign-up codes on
    screen (Part G), no push notifications, mail not configured.

## Part D — Allow officers to register (as long as you need)

Only officers whose Service Number is in **Personnel records** with status *active* can create
an account.

16. Portal → **Personnel records** → **Import** → **Download template**.
17. Fill it in (one officer per row: `service_number`, `surname`, `first_name` are required;
    rank, command, etc. are optional). In Excel, format the Service Number column as **Text**
    first so leading zeroes are kept.
18. Upload it, check the preview, confirm. You can add single officers with **Add record**,
    and suspend or retire someone by changing their status.

## Part E — Put the apps on the download page

The Android app must know `YOUR-ADDRESS` so officers don't have to type it.

19. On GitHub: **Dago001/nisconnect → Settings → Secrets and variables → Actions →
    Variables → New repository variable**. Name `LIVE_SERVER_URL`, value
    `https://YOUR-ADDRESS` (no slash at the end). Save. (Or just tell Claude the address.)
20. Start a new build: **Actions → Mobile & Web CI → Run workflow** (main branch) → **Run**.
    When it is green, open **Releases → NISconnect Android preview** and download
    `nisconnect.apk` and `nisconnect-older-phones.apk`.
21. Portal → **App downloads** → upload `nisconnect.apk` as **64-bit phones (recommended)**
    and `nisconnect-older-phones.apk` as **Older 32-bit phones**, with the version (e.g.
    `1.0.0`).
22. Open `https://YOUR-ADDRESS/download` on a phone, download, install, register. That page is
    the link to send to officers.

**iPhone:** Apple does not allow installing apps from a website. iPhone users open
`https://YOUR-ADDRESS` in Safari → **Share** → **Add to Home Screen**; NISconnect then opens
like an app. The download page explains this. An App Store version needs an Apple Developer
account (docs/GO_LIVE.md, Part 7).

## Part F — Your own domain (optional, do it before step 19)

23. Render service → **Settings → Custom Domains → Add** → e.g. `connect.immigration.gov.ng`.
24. At your domain's DNS provider add the record Render shows (a CNAME to
    `nisconnect.onrender.com`). Render issues the HTTPS certificate automatically.
25. Render service → **Environment** → add `APP_URL` = `https://connect.immigration.gov.ng`
    → **Save**.
26. Use the new domain as `LIVE_SERVER_URL` (step 19). If you change domain after officers
    installed the app, they must install the new APK (or change **Server address** in the app).

## Part G — Switch sign-up codes to SMS (when you have an SMS account)

Today the 6-digit sign-up code is shown on the officer's screen. To send it by SMS with
[Termii](https://termii.com) (Nigerian SMS gateway):

27. Create a Termii account, request the sender ID `NISconnect` (approval takes a few days),
    fund the wallet and copy your **API key** (Termii dashboard → API settings).
28. Render service → **Environment**, add or change:
    - `OTP_DRIVER` = `termii`
    - `TERMII_API_KEY` = your key
    - `TERMII_SENDER_ID` = `NISconnect`
    - `OTP_ONBOARDING_SHOW_CODE` = `false`
    then **Save**. Render restarts the service with the new settings.
29. Register a test officer to confirm the SMS arrives.

**Forgotten PINs:** without SMS, officers can't reset their own PIN. An administrator does it:
portal → **Officers** → the officer → **Reset PIN**.

## Part H — Running it

- **Updates:** every change merged on GitHub deploys automatically (Render → **Events** shows
  each deploy). Officers' web app updates on their next visit; the Android app updates when
  you upload a new APK (Part E) and they install it.
- **Backups:** Render backs up the paid database automatically (database → **Recovery**) and
  takes daily snapshots of the disk (service → **Disks**).
- **Problems:** Render service → **Logs**; portal → **System health** and **Audit log**.
- **Not available on this setup yet:** instant chat delivery (messages appear within a few
  seconds instead), push notifications, voice/video calls.

## Troubleshooting

| Problem | Fix |
|---|---|
| Build fails with "out of memory" or is killed while compiling the web app | In Render's **Workspace Settings → Build Pipeline**, choose the larger (Performance) build machine, then on the service click **Manual Deploy → Deploy latest commit**. |
| `/admin` says the Service Number or password is wrong | The admin is created only if `NIS_ADMIN_*` were set at the first start. Set them under **Environment**, **Save**, wait for the restart. |
| Officer sees "Service Number not found" | Their number is not in Personnel records, or its status isn't *active* (Part D). |
| Phone says "App not installed" | Uninstall any older NISconnect test version first. |
| App says it cannot reach the server | The APK was built without `LIVE_SERVER_URL` (Part E), or the address changed; in the app use **Server address** and enter `https://YOUR-ADDRESS`. |
