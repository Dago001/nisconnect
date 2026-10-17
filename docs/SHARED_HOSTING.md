# Putting NISconnect live at apps.immigration.gov.ng (shared hosting)

This guide installs NISconnect on a shared hosting account with a web control panel, a
File Manager and a Terminal: the Nairahost "server3" account that serves
`apps.immigration.gov.ng`. When you finish, officers use three addresses:

| Address | What it is |
|---|---|
| https://apps.immigration.gov.ng | the web app for officers |
| https://apps.immigration.gov.ng/download | where officers download the Android app |
| https://apps.immigration.gov.ng/admin | the Super Admin portal |

The account needs a **MySQL** (or MariaDB) database, **PHP 8.3** (8.2 or newer) and a **Terminal** (or SSH). PostgreSQL also works: see the note in step 8.
Words in CAPITALS are placeholders for your own values. The account name in the examples
is `immigrat`; use yours if it differs.

**What is different on shared hosting.** Everything works as on the other setups except:

- Chats refresh every few seconds instead of instantly (no websocket server can run).
- Voice and video calls are not available (they need a separate LiveKit server).
- Push notifications stay off until Firebase is set up.
- The server is shared with the account's other websites, so it is less isolated than a
  server of your own. Move to a VPS or Render later without reinstalling the apps
  (the apps follow an address change by themselves).

## Step 1 — Prepare the hosting account (10 minutes, in the control panel)

1. **PHP version:** open **Select PHP Version** (or **PHP Selector**), choose **8.3** and
   make sure these extensions are ticked: `pdo_mysql`, `mbstring`, `openssl`,
   `fileinfo`, `curl`, `dom`, `tokenizer`, `iconv`. Under **Options** set
   `upload_max_filesize` and `post_max_size` to `64M` and `memory_limit` to `256M`
   (Android app files are about 20 MB). Save.
2. **Database:** open **MySQL Databases** (or **MySQL Management**) and create a
   database and a user with a strong password, and give the user all privileges on the
   database. Write down the **full** names the panel shows: they usually start with the
   account name, e.g. `immigrat_nisconnect` and `immigrat_nis`.
3. **HTTPS:** open **SSL Certificates** for `apps.immigration.gov.ng` and issue a free
   Let's Encrypt certificate if there is none yet. If the panel offers **Force SSL /
   HTTPS redirect**, turn it on.

## Step 2 — Upload NISconnect (10 minutes)

4. Download the package `nisconnect-server-….zip` (Claude sends it in the project thread,
   or GitHub → Releases → **NISconnect server package**).
5. Open **File Manager** and go to your **home folder** (the top level, where `domains`
   and `public_html` folders are listed). **Not** inside `public_html`: the settings file
   must never be reachable from the web.
6. **Upload** the zip there, then select it and choose **Extract**. You now have a folder
   called `nisconnect` next to `domains`. Delete the zip.

## Step 3 — Install from the Terminal (10 minutes)

7. Open **Terminal** in the control panel and check the PHP version:

   ```bash
   cd ~/nisconnect
   php -v
   ```

   It must say `PHP 8.3`. If it shows an older version, use `/opt/alt/php83/usr/bin/php`
   wherever these steps say `php`.
8. Create the settings file and fill in the database details from step 2:

   ```bash
   cp .env.example .env
   nano .env
   ```

   Change `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD`. Save with **Ctrl+O**, **Enter**,
   then **Ctrl+X**. (Using a PostgreSQL database instead? Also set `DB_CONNECTION=pgsql`
   and `DB_PORT=5432`.) (No Terminal editor? Edit `.env` in File Manager; turn on "show hidden
   files" to see it.)
9. Create the application key and the database tables:

   ```bash
   php artisan key:generate --force
   php artisan migrate --force --seed
   ```

   Keep a copy of the whole `.env` file in a password manager. Never change `APP_KEY`
   afterwards: it would sign everyone out and make admin two-factor settings unreadable.
10. Check the server. Every line must say **OK** (a WARN about `APP_URL` is fine until the
    address is live):

    ```bash
    php artisan nisconnect:check-server
    ```

    It names anything that is missing and how to fix it (usually a PHP extension from
    step 1 or a database setting).
11. Speed it up:

    ```bash
    php artisan config:cache && php artisan route:cache && php artisan view:cache
    ```

12. Point the website at NISconnect. This keeps the old folder as `public_html.old`:

    ```bash
    cd ~/domains/apps.immigration.gov.ng
    mv public_html public_html.old
    ln -s ~/nisconnect/public public_html
    ```

13. Open https://apps.immigration.gov.ng. You should see the NISconnect welcome screen.
    If the browser shows **403 Forbidden**, the host does not follow folder links: use the
    fix in the troubleshooting table.

## Step 4 — First Super Administrator (10 minutes)

14. In the Terminal, with the administrator's real Service Number (digits only):

    ```bash
    cd ~/nisconnect
    php artisan nisconnect:make-admin SERVICE-NUMBER --name="FULL NAME"
    ```

    It prints a temporary password **once**. Copy it.
15. Open https://apps.immigration.gov.ng/admin, sign in, and choose a new password.
16. Turn on two-factor authentication: top-right menu → **Two-factor authentication** →
    scan the QR code with Google Authenticator or Microsoft Authenticator → enter the code →
    **save the recovery codes** somewhere safe.
17. Portal → **Settings** → turn on **Require two-factor authentication for administrators**.

## Step 5 — Housekeeping job (2 minutes)

18. Control panel → **Cron Jobs** → add a job that runs **every minute** (`*` in all five
    time fields) with this command:

    ```bash
    cd /home/immigrat/nisconnect && php artisan schedule:run >/dev/null 2>&1
    ```

    (Use the PHP path from step 7 if `php` was the wrong version.) It clears expired
    sign-ins and other old records. Portal → **System health** shows whether it runs.

## Step 6 — Allow officers to register

Only officers listed in **Personnel records** with status *active* can create an account.

19. Portal → **Personnel records** → **Import** → **Download template**.
20. Fill it in, one officer per row (`service_number`, `surname` and `first_name` are
    required). In Excel, format the Service Number column as **Text** first so leading
    zeroes are kept.
21. Upload it, check the preview, confirm. Use **Add record** for single officers, and change
    the status to suspend or retire someone.

Until SMS is set up, the 6-digit sign-up code appears on the officer's screen.

## Step 7 — Put the Android app on the download page

22. On GitHub: **Dago001/nisconnect → Settings → Actions → General → Workflow permissions**
    → **Read and write permissions** → **Save**.
23. Tell Claude in the project thread that https://apps.immigration.gov.ng is working.
    Claude builds the Android app for that address and sends `nisconnect.apk` and
    `nisconnect-older-phones.apk`.
24. Portal → **App downloads** → upload `nisconnect.apk` as **64-bit phones
    (recommended)** and `nisconnect-older-phones.apk` as **Older 32-bit phones**, with
    version `1.0.0`.
25. On an Android phone open https://apps.immigration.gov.ng/download, install the app and
    register a test officer. Then send officers that link.

**iPhone users:** Apple does not allow installing apps from a website. They open
https://apps.immigration.gov.ng in Safari → **Share** → **Add to Home Screen**. The download
page explains this.

## Step 8 — Backups

26. Use the control panel's **Backups** (or **Create/Restore Backups**) to make a full
    backup including databases, and download it to a safe place regularly (weekly at least).
    A backup that stays on the same hosting account is lost with the account.
27. Point a free uptime monitor (for example UptimeRobot) at
    https://apps.immigration.gov.ng/up so you hear when it stops answering.

## Updating to a new version

Upload the new `nisconnect-server-….zip` to the home folder, then in the Terminal:

```bash
cd ~
mv nisconnect nisconnect-previous
unzip -q nisconnect-server-NEW.zip
cp nisconnect-previous/.env nisconnect/
rm -rf nisconnect/storage && cp -a nisconnect-previous/storage nisconnect/
cd nisconnect
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache
```

The website link from step 12 keeps pointing at `~/nisconnect/public`. When everything works,
delete `nisconnect-previous` and the zip.

## If something goes wrong

| Problem | Fix |
|---|---|
| Any page shows "500" or "Server Error" | Run `php artisan nisconnect:check-server`. Details are in `~/nisconnect/storage/logs/`. |
| 403 Forbidden after step 12 | The host does not follow folder links. Run: `cd ~/domains/apps.immigration.gov.ng && rm public_html && cp -a ~/nisconnect/public public_html && sed -i "s#__DIR__.'/../#'$HOME/nisconnect/#g" public_html/index.php`. Repeat it after each update. |
| Certificate or "not secure" warning | Step 3: issue the SSL certificate for apps.immigration.gov.ng. |
| You changed `.env` but nothing happened | Run `php artisan config:cache` again. |
| Officer sees "Service Number not found" | Their number isn't in Personnel records, or its status isn't *active* (Step 6). |
| Uploading an app file fails | Raise `upload_max_filesize` and `post_max_size` (step 1). |
| Phone says "App not installed" | Uninstall any older NISconnect test version first. |
