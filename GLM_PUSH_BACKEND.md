# TASK (cloud GLM-5.2, VPS `/opt/edifis`) — Push Notifications: Backend Wiring

**START:** `cd /opt/edifis && git pull --no-edit`
**END:** `git commit && git push`, then rebuild: `cd edifis-infra/prod && docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`

## Context — most of this is ALREADY built, do NOT recreate
- `app/Domain/Notifications/Channels/FcmChannel.php` — full FCM HTTP v1 sender + OAuth from a service-account JSON. Has a **simulation mode**: if `config('services.fcm.test_access_token')` is set it skips OAuth. Reads `config('services.fcm.project_id')` and `config('services.fcm.credentials_path')`.
- `app/Domain/Notifications/Models/FcmToken.php`, table `fcm_tokens` (exists).
- `app/Http/Controllers/Api/FcmTokenController.php@register` — validates `token` + `device_name`, upserts FcmToken for `auth user`. **Just not routed.**
- `app/Domain/Notifications/Notifications/FeePosted.php` and `ResultsPublished.php` — Laravel Notification classes. Open them and confirm/implement a `toFcm($notifiable): array` method returning `['title'=>..., 'body'=>..., 'data'=>[...]]`. If missing, add it.

## Your job — 4 small things

### 1. Route the token register endpoint
In `routes/api.php`, inside the `auth:sanctum` group, add:
```php
Route::post('/fcm/register', [\App\Http\Controllers\Api\FcmTokenController::class, 'register'])->name('fcm.register');
```

### 2. Add the FCM config block
In `config/services.php` add:
```php
'fcm' => [
    'project_id' => env('FCM_PROJECT_ID'),
    'credentials_path' => env('FCM_CREDENTIALS_PATH', storage_path('app/firebase/service-account.json')),
    'test_access_token' => env('FCM_TEST_ACCESS_TOKEN'),
],
```
Add the same keys (empty) to `.env.example`. **Do not** put real secrets in git.

### 3. Fire the notifications from the real events
Dispatch through the FcmChannel. Use a tiny dispatcher: `app(\App\Domain\Notifications\Channels\FcmChannel::class)->send($parentUser, new FeePosted(...))`. Two trigger points:

a. **Fee posted** → in `app/Domain/Ledger/Actions/PostLedgerDebit.php`, after `debit()` and `credit()` create the entry, notify the student's **parent users** (guardians). Look up parents via the existing guardian relation (`guardian_student` table / `User::children()` inverse). Send `FeePosted` with the student name + new balance. Wrap in try/catch + Log so a notification failure never breaks the ledger write.

b. **Results published** → in `app/Http/Controllers/Api/ResultsController.php@compute`, after results are computed successfully, notify each ranked student's parents with `ResultsPublished` (student name + term). Again try/catch + Log.

Keep payloads scoped — a parent only ever gets notifications about **their own** child.

### 4. Verify with simulation mode (NO real Firebase needed yet)
Set `FCM_PROJECT_ID=demo` and `FCM_TEST_ACCESS_TOKEN=fake` in `.env.prod`, rebuild, then:
- Register a token via `POST /api/fcm/register` (logged in as the demo parent `demoparent@pssnkwen.local` / `secret`, login field is **`identifier`** not email).
- Post a fee for that parent's child (bursar `nebaluices@pssnkwen.local`) and confirm the FcmChannel attempts a send (check `storage/logs` — it will log a send attempt / failure to the fake endpoint, proving the trigger fires). Real delivery waits on the Firebase project + service-account JSON (user will provide; drop it at `storage/app/firebase/service-account.json`).

## Acceptance
- `php artisan route:list | grep fcm` shows `api/fcm/register`.
- Posting a fee and computing results both reach `FcmChannel::send()` (log evidence) without throwing.
- No secrets committed. `php -l` clean on every edited file.
