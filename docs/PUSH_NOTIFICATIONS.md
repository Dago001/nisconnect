# Push notifications — setup

What a maintainer has to do once, in the Firebase console and the server `.env`,
to make message and call notifications arrive on real devices.

The app code is finished. Everything below is credentials.

---

## 1. Create the Firebase project

<https://console.firebase.google.com> → **Add project** → name it `nisconnect`.

Google Analytics is optional and not used by the app; skipping it is fine.

---

## 2. Register the Android app

In the project, **Add app → Android**.

| Field | Value |
|---|---|
| Android package name | `ng.gov.immigration.nisconnect` |
| App nickname | NISconnect |
| Debug signing certificate SHA-1 | optional (see below) |

> **The package name must match exactly.** It is set in
> `mobile/android/app/build.gradle.kts` as `applicationId`. A mismatch makes
> Firebase reject the app's token requests, and the only symptom is that
> notifications never arrive.

SHA-1 is only needed for Google Sign-In and Dynamic Links, neither of which
NISconnect uses. Leave it blank.

Download **`google-services.json`** and put it at:

```
mobile/android/app/google-services.json
```

It is git-ignored. Every developer and the CI runner needs their own copy.

Nothing else to wire: `android/app/build.gradle.kts` applies the Google
Services plugin automatically when that file is present, and skips it (with a
build log line) when it is not — so a checkout without it still builds, just
without notifications.

---

## 3. Register the iOS app

**Add app → iOS**, bundle id `ng.gov.immigration.nisconnect` — the same value as
`PRODUCT_BUNDLE_IDENTIFIER` in the Xcode project.

Download **`GoogleService-Info.plist`** to:

```
mobile/ios/Runner/GoogleService-Info.plist
```

Then add it to the Xcode target (open `ios/Runner.xcworkspace`, drag it into
`Runner`, tick *Copy items if needed* and the *Runner* target). Dropping the
file on disk alone is not enough — it must be in the bundle.

**APNs key.** iOS push also needs an Apple key uploaded to Firebase:

1. Apple Developer → Certificates, Identifiers & Profiles → **Keys** → `+`
2. Enable **Apple Push Notifications service (APNs)**, download the `.p8`
   (downloadable once)
3. Firebase → Project settings → **Cloud Messaging** → *APNs authentication key*
   → upload it with the Key ID and your Team ID

Requires a paid Apple Developer membership.

Capabilities on the Runner target: **Push Notifications**, and **Background
Modes** with *Remote notifications* ticked. Add *Voice over IP* too if calls
should ring while the app is backgrounded.

---

## 4. Point the backend at Firebase

The server sends through FCM's HTTP v1 API, which authenticates with a service
account rather than the old legacy server key.

Firebase → Project settings → **Service accounts** → *Generate new private key*.
Keep the JSON off the repo.

In `backend/.env`:

```dotenv
PUSH_DRIVER=http
FCM_ENDPOINT=https://fcm.googleapis.com/v1/projects/<PROJECT_ID>/messages:send
FCM_SERVER_KEY=<OAuth2 access token for that service account>

# iOS, if you are sending to APNs directly rather than through FCM
APNS_ENDPOINT=https://api.push.apple.com/3/device
APNS_KEY=<provider token>
```

Then `php artisan config:clear`.

> `PUSH_DRIVER` defaults to `log`, which writes notifications to
> `storage/logs/laravel.log` instead of sending them. That is deliberate for
> development — check the log to confirm the payload before involving real
> devices.

> **FCM v1 access tokens are short-lived (an hour).** `FCM_SERVER_KEY` holding a
> pasted token works for a smoke test but will stop sending once it expires. For
> production, mint the token from the service-account JSON on each send, or run
> a scheduled refresh. This is the one piece of plumbing still to do.

---

## 5. Check it works

1. `flutter run` on a real device — the simulator cannot receive push
2. Sign in. The app asks for notification permission, then registers its token
   (`POST /api/v1/devices/push-token`)
3. Confirm the row exists:
   `php artisan tinker --execute="echo App\Models\PushToken::count();"`
4. Send a message from a second account with the app **backgrounded**

Expected: a notification with the NISconnect tone — a rising chime for a
message, a repeating ring for a call.

### When nothing arrives

| Symptom | Cause |
|---|---|
| No token row after sign-in | Firebase did not initialise. Run `flutter run` and read the console: a missing `google-services.json` logs "Firebase not configured, notifications disabled" |
| Token exists, nothing sends | `PUSH_DRIVER` still `log`. Check `storage/logs/laravel.log` — the payload will be there |
| Sends, device silent | Package name mismatch, or the FCM access token expired |
| Arrives with the stock Android tone | The channel is missing on the device. Android creates channels at first launch; reinstall after changing them |
| Worked, then stopped | The FCM v1 access token expired — see the note above |

---

## Sounds

Three tones, built from the same two notes so the app has one voice:

| Tone | Character | Used for |
|---|---|---|
| `nis_alert` | rising, short | messages |
| `nis_notice` | falling | announcements, security events |
| `nis_ring` | four-pulse, loops | incoming calls |

Android resolves the sound from the **notification channel**, so the server
names a channel in every payload and the client creates them at launch. The
pairing lives in two places that must agree:

- `backend/app/Services/Push/NotificationChannels.php`
- `mobile/lib/services/push/notification_channels.dart`

`backend/tests/Unit/NotificationChannelsTest.php` pins the ids and sound names
so a change on one side without the other fails the suite.

**Changing a tone needs a new channel id.** Android freezes a channel's sound
when the app first creates it; later edits are ignored for anyone who already
installed. Bump the `_v1` suffix in both files together.

Android source files are `mobile/android/app/src/main/res/raw/*.wav`. iOS wants
the same tones as `.caf` in `mobile/ios/Runner/Sounds/`, added to the bundle:

```bash
afconvert -f caff -d LEI16@44100 -c 1 nis_alert.wav nis_alert.caf
```

Keep each under 30 seconds or iOS silently plays the default.
