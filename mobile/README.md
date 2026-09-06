# NISconnect — Mobile (Flutter)

Officer-facing Android/iOS client. Clean Architecture, feature-first, **Riverpod**
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
  profile with sign-out. Calls screen scaffolds the LiveKit flow.

## Build

> This repository was assembled in an environment **without the Flutter SDK**, so the app was
> not compiled here. On a machine with Flutter 3.4+:

```bash
cd mobile
# Provide a licensed Arial (or metric-compatible Liberation Sans / Arimo) as
# assets/fonts/Arial.ttf and Arial-Bold.ttf  — see docs/DESIGN_SYSTEM.md §3.
flutter pub get
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
flutter test
```

Configuration is via `--dart-define` (`API_BASE_URL`, `WS_HOST`, `WS_PORT`, `WS_KEY`,
`LIVEKIT_URL`). No secrets are compiled into the app.
