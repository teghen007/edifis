# HANDOFF → DeepSeek: run EDIFIS in Docker + build the Android APK (local)

Context for taking over from the architect. **You (DeepSeek) edit code/configs and give the user exact commands; the user runs them on their Windows machine and pastes output back.** The user has Docker + Flutter installed. Goal: (A) EDIFIS running in Docker containers, (B) a working debug APK on the user's phone.

## Machine facts
- OS: Windows 11. Host PHP 8.5 at `C:\Program Files\php\php.exe`. Flutter at `C:\src\flutter\bin` (PATH set; `flutter doctor` green for Android).
- **User's LAN IP: `192.168.1.109`** (phone on same Wi-Fi reaches the PC here).
- ⚠️ **Disk filled up earlier and broke both builds.** It was pruned (`docker system prune -af`) → ~35 GB free. **Watch disk space.**

## What's already set up
- **Lean container stack** (this folder, `edifis-infra/local-quick/`): `Dockerfile` (php:8.3-cli + pdo_pgsql/gd/zip/mbstring/bcmath, **no Swoole** — the Swoole compile is what stalled the original lab build) and `docker-compose.yml` (`edifis-db` postgres:16 + `edifis-app` running `php artisan serve` on 8080, code bind-mounted from `../../edifis-backend`). **A build was already kicked off** — check `docker compose -f edifis-infra/local-quick/docker-compose.yml ps`.
- `edifis-backend/.env`: set to `pgsql`, `EDIFIS_MODE=local`, `APP_DEBUG=false`, file cache/session, `MAIL_MAILER=log`. (Compose overrides `DB_HOST=db`.)
- `edifis-backend/public/index.php`: added `error_reporting(E_ALL & ~E_DEPRECATED); ini_set('display_errors','0');` at top — silences the PHP 8.5 `PDO::MYSQL_ATTR_SSL_CA` deprecation so API JSON stays clean. (Remove on PHP 8.3.)
- `edifis-mobile/android/app/src/main/AndroidManifest.xml`: added `INTERNET` permission + `android:usesCleartextTraffic="true"` (needed because the local backend is `http://` on the LAN).
- Old test DB containers `edifis-pg`/`edifis-test-pg` were removed.
- Firewall rule `EDIFIS local 8080` added (so the phone can reach the PC).

## A) Finish the Docker run
1. `docker compose -f edifis-infra/local-quick/docker-compose.yml up -d --build` (if not already up).
2. Seed: `docker compose -f edifis-infra/local-quick/docker-compose.yml exec app php artisan migrate:fresh --seed --seeder=LabSeeder`
   - The host `vendor/` is bind-mounted (installed on Windows). It's pure PHP so should work in the Linux container; **if anything errors about missing packages, run `... exec app composer install` first.**
3. Verify: `curl http://localhost:8080/api/health` → clean `{"status":"ok",...}`; open `http://localhost:8080/staff`.
4. Demo logins (password **`secret`**): `bih.patience@pssnkwen.local` (principal), `nebaluices@pssnkwen.local` (bursar), `ngufor.calvin@pssnkwen.local` (subject_teacher), `songhi.kingsley@pssnkwen.local` (class_master), `rita.awah@pssnkwen.local` (secretary).
5. User should see `edifis-app` + `edifis-db` in Docker Desktop.

## B) Finish the APK
- App base-URL env var is **`EDIFIS_API_BASE`**. Build:
  `cd edifis-mobile; flutter build apk --debug --dart-define=EDIFIS_API_BASE=http://192.168.1.109:8080/api`
- The Android **NDK `28.2.13676358` was deleted** (it was corrupted by the disk-full); Gradle will **re-download** it on this build (~1 GB — may be slow on the user's intermittent connection). If it stalls/fails on the NDK download, options: retry, or **fall back to Flutter Web** (`flutter run -d chrome` / `flutter build web`) which skips the entire Android toolchain — but check `mobile_scanner` web support first.
- Output: `edifis-mobile\build\app\outputs\flutter-apk\app-debug.apk` → user copies to phone (same Wi-Fi) → install (allow unknown apps).
- Note: `firebase_messaging` is in `pubspec.yaml` but **not used in Dart code yet**, so it won't block the build and push isn't wired (fine for local testing).

## Working style
Give the user **one command at a time**, wait for pasted output, fix, repeat. Keep an eye on disk space. Update `PROGRESS.md` when done.
