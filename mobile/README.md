# NISconnect — Mobile (Flutter)

Officer-facing client for **Android, iOS and the web**, from one Flutter codebase. Clean Architecture, feature-first, **Riverpod**
state management, **Arial** typography, professional green design system.

## Structure
```
lib/
├── core/          config, theme (colors/typography/theme), network (Dio), storage
│                  (secure), router (go_router)
├── features/      auth, chat, directory, groups, calls, settings, home
│                  each: data/ (repositories, DTOs), domain/ (entities),
│                  presentation/ (screens + Riverpod controllers)
└── shared/        reusable widgets (ServiceNumberField, PrimaryButton)
```

## Implemented flows
- **Service Number onboarding** (crown feature): numeric-only field (digits keyboard +
  `FilteringTextInputFormatter.digitsOnly`), verify → personnel record → "This is me" →
  phone → OTP → PIN → account created, all driven by `OnboardingController` against the
  backend. Never contacts the NIS personnel source directly.
- Login by Service Number + PIN; token stored in Keystore/Keychain via `flutter_secure_storage`.
- Chats list, conversation view (history + send), directory search + start chat, groups list,
  profile with sign-out.
- **Realtime** (`services/websocket/realtime_service.dart`): connects to Reverb (Pusher
  protocol), authorises private channels via the backend, streams `message.new` into the open
  conversation live.
- **Calling** (`features/calls/`): `CallController` signals the backend and connects to the
  LiveKit room; incoming-call and in-call screens with mute/camera/hang-up.
- **Voice notes** (`features/chat/.../voice_note_recorder.dart`): record/cancel/send with a
  live timer via the `record` package (microphone permission handled).

## Targets

| Target  | Folder     | Build command (from `mobile/`)                               |
|---------|------------|--------------------------------------------------------------|
| Web     | `web/`     | `flutter build web --release --no-web-resources-cdn`         |
| Android | `android/` | `flutter build apk --release` (or `appbundle` for Play)      |
| iOS     | `ios/`     | `flutter build ipa` (on a Mac with Xcode)                    |

App id / bundle id: `ng.gov.immigration.nisconnect`. CI (`.github/workflows/mobile.yml`)
analyzes, tests and builds all three on every push touching `mobile/`.

Fonts: `assets/fonts/Arial.ttf` / `Arial-Bold.ttf` are **Arimo** (metric-compatible with
Arial, SIL OFL, see `assets/fonts/OFL.txt`) registered under the family name `Arial`. Swap in a
licensed Arial with the same file names if preferred.

## Run locally

```bash
cd mobile
flutter pub get
flutter run -d chrome          # website, talks to http://localhost:8000
flutter run -d emulator-5554   # Android emulator, talks to http://10.0.2.2:8000
flutter run -d "iPhone 16"     # iOS simulator (Mac), talks to http://localhost:8000
flutter analyze && flutter test
```

Configuration is via `--dart-define` (`API_BASE_URL`, `WS_SCHEME`, `WS_HOST`, `WS_PORT`,
`WS_KEY`, `LIVEKIT_URL`). With no `API_BASE_URL`/`WS_HOST`, the app picks the dev host for the
platform it runs on. Production builds must pass the real `https://` / `wss` endpoints. No
secrets are compiled into the app.

## Platform notes

- **Web.** `--no-web-resources-cdn` bundles CanvasKit with the site instead of loading it
  from Google's CDN. On wide screens the phone-designed UI is shown centred at phone width.
  The API must allow the site's origin (Laravel's default CORS config allows `api/*` from any
  origin; tighten `config/cors.php` for production). Not yet on web: push notifications
  (needs a Firebase web app + VAPID key + service worker) and biometric unlock (no browser
  equivalent; the session token sits in browser storage, WebCrypto-encrypted with a key that also lives
  there, so treat it as obfuscation rather than Keychain-grade protection).
- **Android.** minSdk 24. Release signing reads `android/key.properties` (git-ignored:
  `storeFile`, `storePassword`, `keyAlias`, `keyPassword`); without it, release builds use the
  debug key. Debug builds allow cleartext http for the local dev stack; release builds do not.
- **iOS.** Deployment target 15.0. Info.plist carries the microphone, camera, photo library
  and Face ID usage strings plus audio/VoIP/remote-notification background modes. To ship:
  open `ios/Runner.xcworkspace` in Xcode, set the signing team, enable the Push
  Notifications capability, then `flutter build ipa`.
- **Push (Android & iOS).** `PushService` expects Firebase to be initialised. Add
  `google-services.json` (Android) and `GoogleService-Info.plist` (iOS), or run
  `flutterfire configure`, and call `Firebase.initializeApp()` in `main()`. Until then push
  registration silently no-ops.
