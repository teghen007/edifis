# EDIFIS Mobile — Flutter Setup & First APK (Windows)

How to install Flutter, run the EDIFIS app, point it at the lab, and build the first Android APK. The app is **online-only** (talks to the cloud); **iOS needs a Mac** — Android first.

---

## 0. What you'll end up with
- Flutter + Android toolchain installed and `flutter doctor` green.
- The EDIFIS app running (emulator or a real Android tablet), logged into a seeded user against the lab cloud.
- A debug `app-debug.apk` you can install on a teacher's/parent's Android device.
- (Optional, for push) Firebase wired so FCM notifications arrive.

---

## 1. Install the toolchain (one-time, ~30–60 min, ~8 GB)

1. **Git** — likely already installed (`git --version`). If not: https://git-scm.com.
2. **Flutter SDK**
   - Download the Windows zip from https://docs.flutter.dev/get-started/install/windows
   - Extract to `C:\src\flutter` (avoid spaces / `Program Files`).
   - Add `C:\src\flutter\bin` to your **PATH** (System Properties → Environment Variables → Path → New).
   - New terminal → `flutter --version`.
3. **Android Studio** (easiest — bundles the JDK, Android SDK, and an emulator)
   - https://developer.android.com/studio → install with default options.
   - First launch → **SDK Manager** → install: *Android SDK Platform*, *Android SDK Command-line Tools*, *Android SDK Build-Tools*, *Android Emulator*.
   - (Optional) **Device Manager** → create a virtual device (e.g. Pixel 7, a recent system image) — or skip and use a real tablet (Section 4).
4. **Accept licenses + verify**
   ```powershell
   flutter doctor --android-licenses    # press y to all
   flutter doctor                       # aim for green on Flutter + Android toolchain
   ```
   (Ignore the "Visual Studio (Windows desktop)" and "Xcode" lines unless you're building Windows-desktop/iOS.)

---

## 2. Build the app (codegen + checks)

```powershell
cd "C:\Users\teghe\OneDrive\Dokumenty\SMS RESEARCH\edifis-mobile"
flutter create --platforms=android .                       # FIRST TIME ONLY: generates the android/ build folder around the existing lib/ code (the project was scaffolded code-first, so this platform folder doesn't exist yet). Does not touch lib/.
flutter pub get
dart run build_runner build --delete-conflicting-outputs   # regenerate DTOs / Riverpod / json (drop hand-stubs)
flutter analyze
flutter test
```
> This is the first time the code actually compiles — expect to fix a few things (that's Phase 12 T-12.2). DeepSeek can drive the fixes; you run the commands and paste errors back.

---

## 3. Point the app at the lab and run (emulator)

Start the lab first (separate terminal):
```powershell
cd "C:\Users\teghe\OneDrive\Dokumenty\SMS RESEARCH\edifis-infra\lab"
docker compose -f docker-compose.lab.yml up -d
```
The lab **cloud** is on `localhost:8080`. From the **Android emulator**, the host machine is reached at **`10.0.2.2`** (not `localhost`):

```powershell
cd "C:\Users\teghe\OneDrive\Dokumenty\SMS RESEARCH\edifis-mobile"
flutter run --dart-define=EDIFIS_CLOUD_BASE=http://10.0.2.2:8080/api
```
Log in with a seeded account from `LabSeeder` (e.g. the principal/parent it prints; password `secret`).

> **Cleartext HTTP gotcha:** Android blocks plain `http://` by default. For the lab only, allow it — set `android:usesCleartextTraffic="true"` in `android/app/src/main/AndroidManifest.xml` (`<application>` tag), **or** add a debug `network_security_config`. Production uses `https://…edifis.cm` so this is lab-only.

---

## 4. Build & install the APK on a real Android tablet

1. On the tablet: **Settings → About → tap Build number 7×** to enable Developer options → enable **USB debugging**. (Or just build the APK and sideload it.)
2. Find your desktop's **LAN IP** (`ipconfig` → IPv4, e.g. `192.168.1.20`). The tablet must be on the **same Wi-Fi** as the desktop running the lab.
3. Build the debug APK pointed at the lab cloud over the LAN:
   ```powershell
   cd "C:\Users\teghe\OneDrive\Dokumenty\SMS RESEARCH\edifis-mobile"
   flutter build apk --debug --dart-define=EDIFIS_CLOUD_BASE=http://192.168.1.20:8080/api
   ```
   Output: `build\app\outputs\flutter-apk\app-debug.apk`
4. Transfer the APK to the tablet (USB / email / drive), tap to install (allow "install unknown apps").
5. Open the app → log in as a seeded user → confirm reads work.

> For a **release** APK later: `flutter build apk --release` + app signing (a keystore). Debug is fine for the pilot trial.

---

## 5. (Optional) Wire FCM push end-to-end

The backend FCM v1 channel + token endpoint are already built (T-12.3/T-12.6). To actually deliver pushes you need a Firebase project:

1. **Firebase console** → create a project (free).
2. Install the FlutterFire CLI and configure (auto-generates platform configs):
   ```powershell
   dart pub global activate flutterfire_cli
   flutterfire configure        # pick the project; select Android (+ iOS later)
   ```
   This adds `google-services.json` (Android) + `firebase_options.dart`.
3. **Backend (cloud `.env`):** set `FCM_PROJECT_ID` and point to a **service-account JSON** (Firebase → Project settings → Service accounts → Generate new private key). Keep that file out of git.
4. Run the app → it registers its FCM token via `POST /api/fcm/register` → publish results for that user's child → the push should arrive.

> Push is **not required** to pilot. Web Push (the parent web portal) already works without any of this; FCM just gives the native app reliable push.

---

## 6. iOS (later)
Requires a **Mac + Xcode + an Apple Developer account** ($99/yr) to build/sign. Not possible on Windows. Defer until/if iOS devices are in scope; the same Flutter codebase builds for it on a Mac.

---

## Quick reference
| Goal | Command |
|------|---------|
| Verify toolchain | `flutter doctor` |
| Install deps + codegen | `flutter pub get && dart run build_runner build --delete-conflicting-outputs` |
| Lint + test | `flutter analyze && flutter test` |
| Run on emulator → lab | `flutter run --dart-define=EDIFIS_CLOUD_BASE=http://10.0.2.2:8080/api` |
| Build APK → lab over LAN | `flutter build apk --debug --dart-define=EDIFIS_CLOUD_BASE=http://<LAN-IP>:8080/api` |
| Wire push | `flutterfire configure` + backend service-account `.env` |
