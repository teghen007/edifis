# Backend SPEC 08 — Parent Portal + Notifications (cloud-direct)  (Phase 10)

ADR-017/018. A mobile-first **Livewire/Blade parent portal on the CLOUD only**, installable as a PWA, with push. Parents never touch a node.

## 1. Parent auth (T-10.1) — PIN bootstrap + new-device OTP (ADR-019)
- **Username = phone number.** Bootstrap credential = the **phone digits reversed** (`must_reset_credential = true`).
- **First login → forced to set a 4–6 digit PIN** (not a password — friendlier). Store `pin_hash` (bcrypt/argon), clear the reset flag. Rate-limit attempts (e.g. 5/min then lockout).
- **New-device email OTP (all users, T-10.1b):** on login from an untrusted device (no valid `trusted_devices` cookie), send a **6-digit OTP to email** (`login_otps`, short TTL, attempt-capped). On success set a signed **trusted-device cookie (~90d)**; that device skips OTP next time. **Device identity = this signed token, NOT IMEI** (browsers cannot read IMEI). Email-less guardians: PIN + rate-limit only. Apply the SAME new-device OTP to the staff (Filament) login path.
- `ModeGate::requireFeature('parent_portal')` → portal only exists when `EDIFIS_MODE=cloud`; the node registers no parent routes.
- Read-only, role `parent`; a guardian sees only their own children.

## 2. Portal screens (T-10.2) — mobile-first Blade/Livewire
Per child: balance (derived), **published** results, attendance summary (e.g. 58/60), downloadable receipts + report cards, notices + calendar. Cache-friendly for the result-day read burst (read models / short cache, not N+1).

## 3. PWA (T-10.3)
- `public/manifest.webmanifest` (name, icons, `display: standalone`, theme) + `public/sw.js` (service worker: cache shell, receive `push` events, show notification, focus on click).
- Register the SW from the portal layout; prompt "Add to home screen".

## 4. Notifications (T-10.4) — Laravel Notifications. PILOT: Web Push + in-portal ONLY (no SMS)
- Add `laravel-notification-channels/webpush` (Web Push / VAPID). VAPID keys in `.env`.
- `PushSubscription` model/table (user_id, endpoint, p256dh, auth) — created when the browser grants permission.
- Pilot channels per notification: **webpush** (smartphone portal) + **database** (in-portal feed, + Reverb broadcast for real-time when open). **NO SMS in the pilot** (ADR-018).
- Typed Notification classes, each `via()` + `toWebPush()` + `toDatabase()`:
  `ResultsPublished`, `FeePosted`, `AttendanceFlagged`, `ExeatIssued`, `CalendarEventPosted`.

## 5. Triggers (T-10.5) — fire on the CLOUD where data becomes parent-authoritative
- Cloud-side Domain Actions dispatch to the affected child's guardians, e.g. `PublishResults($student, $sequence)` → notify guardians of that student; `PostLedgerDebit` (on cloud apply) → `FeePosted`; attendance flag → `AttendanceFlagged`.
- **Idempotent:** dispatch keyed so a sync replay (same event id) does NOT re-notify. (Reuse `Idempotency` or a `notified_event_ids` guard.)

## 6. SMS — OUT OF PILOT (do not build now)
- No SMS in the pilot (ADR-018). Leave only an empty `SmsChannel` interface stub so it can be added later; do **not** wire it to notifications, do not add a gateway, do not add it to any `via()`. Guardians without a push subscription rely on the in-portal feed (and printed reports per §14.3).

## Outputs
```
app/Domain/Auth/{Actions/{SetParentPin,VerifyNewDeviceOtp,TrustDevice}.php, Models/{TrustedDevice,LoginOtp}.php}
app/Domain/Notifications/{Notifications/*.php, Channels/SmsChannel.php (EMPTY stub only), Models/PushSubscription.php}
app/Livewire/Parent/*  +  resources/views/parent/*        # the portal (cloud only)
public/{manifest.webmanifest, sw.js, icons/*}
routes/web.php (cloud): parent portal routes behind ModeGate
database/migrations: push_subscriptions, notifications (Laravel), trusted_devices, login_otps, (users: pin_hash + must_reset_credential)
tests/Feature/Parent/*  +  tests/Feature/Auth/NewDeviceOtpTest.php  +  tests/Feature/Notifications/*
```
**Must-test:** phone-reversed bootstrap forces a PIN set on first login; a new device triggers email OTP, a trusted device skips it; PIN/login attempts are rate-limited; a guardian sees only their children; `PublishResults` queues exactly one webpush + one DB notification per affected guardian (no double on replay); **no SMS is sent / no `via()` includes sms**; parent routes 404 in `local` mode; PWA manifest + SW served.
