# NISconnect — Acceptance Test Mapping (§73)

Each acceptance point maps to a backend endpoint (tested) and the mobile screen/
service that drives it. Backend items are covered by the PostgreSQL test suite;
mobile items build with the Flutter SDK and run against the backend.

## Onboarding (Officer A)
| # | Step | Backend | Mobile |
|---|------|---------|--------|
| 1–3 | Install, open, Create Account | — | `SplashScreen` → `WelcomeScreen` |
| 4 | Enter numeric Service Number | `POST /auth/verify-service-number` | `OnboardingScreen` + `ServiceNumberField` (digits-only) |
| 5 | Service Number verified | idem (anti-enumeration) | `OnboardingController` |
| 6 | Personnel info auto-appears | verify response `record` | `_PersonnelRecordStep` |
| 7 | Officer confirms ("This is me") | — | `confirmIsMe()` |
| 8 | Phone verification | `POST /auth/confirm-identity` | `_PhoneStep` |
| 9 | OTP verification | `POST /auth/verify-otp` | `_OtpStep` |
| 10 | Create PIN/password | `POST /auth/set-credentials` | `_PinStep` |
| 11 | Device registered | set-credentials (device) | device payload |
| 12–13 | Account active, enters app | account_state=active | `→ /home` |

## Communication (Officer A)
| # | Step | Backend | Mobile |
|---|------|---------|--------|
| 14 | Search NIS directory | `GET /directory/search` | `DirectoryScreen` |
| 15 | Find Officer B | idem | idem |
| 16 | Start private chat | `POST /chats` | `startChat()` |
| 17–18 | Send / receive messages | `POST/GET /chats/{c}/messages` + `message.new` | `ConversationScreen` + `RealtimeService` |
| 19 | Delivery/read status | `POST /messages/{m}/read` + `message.read` | status ticks |
| 20 | Send images | `POST /media` + message `type=image` | `image_picker` |
| 21 | Send documents | `POST /media` + `type=document` | `file_picker` |
| 22 | Send voice notes | `POST /media` + `type=voice` + `voice_notes` | `VoiceNoteRecorder` |
| 23 | Voice call | `POST /calls` (LiveKit token) | `CallController` → `InCallScreen` |
| 24 | Video call | `POST /calls {type:video}` | idem |
| 25 | Create authorised group | `POST /groups` (or admin org group) | groups |
| 26 | Add officers | `POST /groups/{g}/members` | idem |
| 27 | Group messages | `POST /chats/{c}/messages` | `ConversationScreen` |
| 28 | Group voice/video call | `POST /calls` (mode=group) | `CallController` |
| 29 | Notifications | in-app `notifications` + FCM/APNs push | `PushService` |
| 30 | Search messages | `GET /messages/search` (GIN FTS) | search |
| 31 | Manage privacy | `PUT /users/me/privacy` (+ `PresenceService`) | privacy |
| 32 | Manage devices | `GET/DELETE /devices` | My Devices |
| 33 | Biometric auth | device-local (`BiometricService`) | `SplashScreen` gate |
| 34 | Log out securely | `POST /auth/logout` (token revoked) | Profile → Sign out |

Extra messaging (§14): edit (`PATCH /messages/{m}`), pin (`POST /messages/{m}/pin`),
forward (`POST /messages/{m}/forward`), react, delete — `MessageActionsSheet`.
Safety: block/unblock/report + `GET /safety/blocked` — `BlockedUsersScreen`.
Recovery (§68): `POST /auth/recover/{start,verify,reset}`.

## Administration
| # | Step | Backend (admin portal) |
|---|------|------------------------|
| 35 | Manage users | `admin/users` |
| 36 | Suspend / reactivate | `admin/users/{u}/suspend|reactivate` |
| 37 | Revoke devices | `admin/users/{u}/revoke-devices` |
| 38 | Manage organisational groups | `admin/org/groups` |
| 39 | Manage official channels | `admin/org/channels` (+ publishers) |
| 40 | Review reports | `admin/reports` (action/dismiss) |
| 41 | Review security events | `admin/security` |
| 42 | Review audit logs | `admin/audit` |

All admin routes are server-side RBAC-gated (`role:` middleware); non-admins get 403.

## Verify it
```bash
# Backend (all of the above server-side items are tested):
cd backend && php artisan test          # PostgreSQL, 80+ tests

# Mobile (needs Flutter SDK):
cd mobile && flutter pub get && flutter analyze && flutter test
flutter run --dart-define=API_BASE_URL=http://10.0.2.2:8000/api/v1
```

## Still requires real infrastructure/credentials (config only, no code changes)
- NIS personnel source (`PERSONNEL_PROVIDER=api|database`), SMS gateway
  (`OTP_DRIVER=sms`), FCM/APNs (`PUSH_DRIVER=http` + Firebase config files),
  LiveKit server (`LIVEKIT_*`). All are behind adapters selected by `.env`.
