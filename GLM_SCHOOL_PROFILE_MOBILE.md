# TASK (local GLM-5.2, Kilo on PC) — Show school identity in the app   [DO NOT DELETE]

**START:** `git pull --no-edit`. Work only in `edifis-mobile/`.
**END:** `flutter analyze` clean → `git commit && git push`. Do NOT build the APK (Claude ships it).

## Goal
Display the school's identity (name, motto, logo) in the app, from the backend that's being built in
parallel. Build against this CONTRACT — `GET /school/profile` returns:
`{ name, school_type, motto, logo_url, currency, principal_name, address, phone, email }`
(any field may be empty; `logo_url` is a hosted image URL or empty.)

## Steps
1. **Service** `lib/core/services/school_api.dart`:
   - A `SchoolProfile` model (name, schoolType, motto, logoUrl, currency... all String, null-safe).
   - `final schoolProfileProvider = FutureProvider<SchoolProfile>(...)` that GETs `/school/profile`
     via the existing `dioProvider`. On error, return a default empty profile (never throw).

2. **Login screen** — show the school **name** (and **logo** via `Image.network(logoUrl)` if non-empty,
   else fall back to the existing EDIFIS branding). Keep it tasteful; don't break the current layout.

3. **Staff + Parent dashboards** — in the hero header, show the school **name** (and motto as a small
   subtitle if present) instead of / alongside what's there now. Use `schoolProfileProvider`.

## Constraints
- Fully null-safe: every field may be empty; `Image.network` must have an `errorBuilder` that hides the
  image on failure (no crash, no broken-image icon).
- No new packages. Match existing style (Riverpod, GlassCard, AppColors, lucide_icons_flutter).
- The app MUST still render if `/school/profile` 404s or errors (backend may not be deployed yet).
- `flutter analyze` = 0 errors.

## Acceptance
- App shows the configured school name/logo/motto when the endpoint is live, and looks fine when it isn't.
- Commit + push, tell Claude → he builds + ships the APK and end-to-end tests.
