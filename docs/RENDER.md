# Going live on Render — step by step

This guide puts NISconnect on [Render](https://render.com), a hosting company that runs the
servers for you: no Linux, no commands, HTTPS included. Everything lives on one address:

| Address | What it is |
|---|---|
| `https://YOUR-ADDRESS/` | the NISconnect web app for officers (any browser, phone or computer) |
| `https://YOUR-ADDRESS/download` | the page where officers download the Android app (and iPhone instructions) |
| `https://YOUR-ADDRESS/admin` | the Super Admin portal |

`YOUR-ADDRESS` is the address Render gives you, for example `nisconnect.onrender.com`, or your
own domain such as `apps.immigration.gov.ng` (Part F).

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

The Android app must know `YOUR-ADDRESS` so officers don't have to type it. Using your own
address such as `apps.immigration.gov.ng`? Do Part F1 first.

19. On GitHub: **Dago001/nisconnect → Settings → Secrets and variables → Actions →
    Variables → New repository variable**. Name `LIVE_SERVER_URL`, value
    `https://YOUR-ADDRESS` (no slash at the end). Save. (Or just tell Claude the address.)
20. Start a new build: **Actions → Mobile & Web CI → Run workflow** (main branch) → **Run**.
    When it is green, open **Releases → NISconnect Android preview** and download
    `nisconnect.apk` and `nisconnect-older-phones.apk`. (No release there? Open the run and
    download **nisconnect-android-apk** under **Artifacts**, then unzip it:
    `app-arm64-v8a-release.apk` is the 64-bit app, `app-armeabi-v7a-release.apk` the
    older-phones one. To get the release back, set **Settings → Actions → General →
    Workflow permissions** to **Read and write**.)
21. Portal → **App downloads** → upload `nisconnect.apk` as **64-bit phones (recommended)**
    and `nisconnect-older-phones.apk` as **Older 32-bit phones**, with the version (e.g.
    `1.0.0`).
22. Open `https://YOUR-ADDRESS/download` on a phone, download, install, register. That page is
    the link to send to officers.

**iPhone:** Apple does not allow installing apps from a website. iPhone users open
`https://YOUR-ADDRESS` in Safari → **Share** → **Add to Home Screen**; NISconnect then opens
like an app. The download page explains this. An App Store version needs an Apple Developer
account (docs/GO_LIVE.md, Part 7).

## Part F — Your own address: apps.immigration.gov.ng now, connect.immigration.gov.ng later

Do F1 right after Part C and **before Part E**, so the Android app is built for your own
address. F2 is for later, when you move to `connect.immigration.gov.ng`.

`apps.immigration.gov.ng` must be used **only** for NISconnect (the app sits at the root of the
address). If other NIS services already live on that address, use a different sub-domain, e.g.
`nisconnect.immigration.gov.ng`; serving NISconnect under a path such as
`apps.immigration.gov.ng/nisconnect` is not supported.

### F1 — Start on https://apps.immigration.gov.ng

23. Render service → **Settings → Custom Domains → Add Custom Domain** →
    `apps.immigration.gov.ng` → **Save**. Render shows a DNS record to create.
24. Ask whoever manages DNS for `immigration.gov.ng` (NIS ICT) to add it: a **CNAME** record,
    name `apps`, value your Render address (e.g. `nisconnect.onrender.com`). If the domain
    has **CAA** records, they must also allow `letsencrypt.org` and `pki.goog`, or the HTTPS
    certificate can't be issued.
25. Back in Render, click **Verify**. When it shows **Certificate issued**, open
    `https://apps.immigration.gov.ng` — you should see NISconnect.
26. Render service → **Environment** → add `APP_URL` = `https://apps.immigration.gov.ng` →
    **Save** (the service restarts in about a minute).
27. Continue with Part E using `https://apps.immigration.gov.ng` as `LIVE_SERVER_URL`. The
    download page for officers is then `https://apps.immigration.gov.ng/download`.

### F2 — Move to https://connect.immigration.gov.ng (when ready)

Nobody has to reinstall anything: the same Render service answers on both addresses during
the move, the website redirects browsers, and the apps switch over by themselves.

28. Render service → **Settings → Custom Domains → Add Custom Domain** →
    `connect.immigration.gov.ng`. Keep `apps.immigration.gov.ng` in the list.
29. DNS: add a **CNAME** `connect` → your Render address (same as step 24). Wait for
    **Certificate issued** and check `https://connect.immigration.gov.ng` opens NISconnect.
30. Render service → **Environment**:
    - change `APP_URL` to `https://connect.immigration.gov.ng`
    - add `APP_CANONICAL_URL` = `https://connect.immigration.gov.ng`

    then **Save**. From now on:
    - anyone opening `apps.immigration.gov.ng` in a browser (web app, download page, admin
      portal) is sent to the same page on `connect.immigration.gov.ng`;
    - installed Android apps keep working on the old address, learn the new one from the
      server and use it from the next time they are opened. Officers stay signed in and keep
      their chats — it is the same service and the same data.
31. GitHub: change the `LIVE_SERVER_URL` variable to `https://connect.immigration.gov.ng`, run
    **Mobile & Web CI** again and upload the new APKs on **App downloads**, so new installs
    start on the new address directly.
32. Administrators sign in again on the new address (browser sign-ins belong to one address);
    two-factor settings are unchanged. iPhone users who added the web app to their Home
    Screen remove the old icon and add it again from `https://connect.immigration.gov.ng`.
33. Leave `apps.immigration.gov.ng` attached for **at least 3 months**, so phones that were
    switched off or offline during the move still find their way. Then remove it from
    **Custom Domains** and DNS.

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
| App says it cannot reach the server | The APK was built without `LIVE_SERVER_URL` (Part E), or an old address was removed too early (step 33); in the app use **Server address** and enter `https://YOUR-ADDRESS`. |
| `apps.immigration.gov.ng` shows a certificate error | DNS isn't pointing at Render yet, or a CAA record blocks the certificate (step 24). Render's **Custom Domains** shows the status. |
