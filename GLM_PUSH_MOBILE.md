# TASK (local GLM-5.2, Kilo on PC) — Push Notifications: Flutter Client

**START:** `git pull --no-edit` (in repo root). Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do **not** build the APK or deploy — Claude ships the APK.

## Goal
After login, register this device's FCM token with the backend so the school can push **fees / results** notifications to parents (and staff later). Show foreground notifications.

## Steps

### 1. Packages
```
cd edifis-mobile
flutter pub add firebase_core firebase_messaging flutter_local_notifications
```
Run `flutterfire configure` ONLY if the user has created the Firebase project and given you the config; otherwise **stop after wiring the code** and leave a `// TODO: flutterfire configure` note — do not fake `firebase_options.dart`. The Android `google-services.json` + Gradle plugin also wait on the Firebase project. Code everything else so it compiles and is ready.

### 2. A small PushService
Create `lib/core/services/push_service.dart`:
- `Future<void> init(Ref ref)`:
  - `await Firebase.initializeApp(...)` (guard with try/catch so the app still runs if Firebase isn't configured yet).
  - request permission (`FirebaseMessaging.instance.requestPermission()`).
  - get token (`getToken()`), then `POST /fcm/register` with `{ "token": <token>, "device_name": <model> }` via the existing `dioProvider` (see `lib/core/network/dio_client.dart`). Only call this when a user is authenticated.
  - listen `onTokenRefresh` → re-register.
  - `FirebaseMessaging.onMessage.listen(...)` → show a local notification via `flutter_local_notifications`.
- Make it **fail-soft**: any Firebase/permission error is caught and logged, never crashes the app.

### 3. Call it after login
Find where auth state flips to authenticated (look in `lib/core/auth/auth_state.dart` / wherever `/me` is loaded after login). After a successful login, call `PushService.init`. Also attempt re-register on app start if a token already exists.

### 4. Don't break existing flows
- The app MUST still launch and work fully even with no Firebase project configured yet (token registration just silently no-ops).
- Match existing code style (Riverpod providers, `dioProvider`, lucide_icons_flutter, etc.).

## Acceptance
- `flutter analyze` passes (0 errors).
- App still boots and logs in with Firebase **not** configured (no crash).
- When configured, login triggers a `POST /fcm/register` (verify in network/Dio logs).
- Commit + push. Tell Claude it's ready so he builds + ships the APK and end-to-end tests.

## Notes
- Login API: field is `identifier` (not `email`), returns `{token, role, user_id}`.
- Demo parent: `demoparent@pssnkwen.local` / `secret`.
