# TASK (cloud GLM-5.2, VPS) — School Settings (institution profile)   [DO NOT DELETE]

**START:** `cd /opt/edifis && git pull --no-edit`
**END:** `git commit && git push`, rebuild `cd edifis-infra/prod && docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`, then `php artisan migrate --force` and `php artisan filament:optimize`.

## Goal
Let the **System Admin** configure the institution's identity & type in the Filament panel — the
things a principal doesn't set up. Singleton per school (one row; this is already a per-tenant DB).

## 1. Migration — `school_settings` (singleton table)
Columns (all nullable except name): `id` (uuid pk), `name` (string), `school_type`
(string, one of `boarding|day|both`, default `day`), `motto` (string), `address` (text),
`phone` (string), `email` (string), `currency` (string, default `XAF`), `principal_name` (string),
`logo_url` (string — a hosted image URL, NOT a file upload; we have no persistent public storage in
Docker, so do NOT use Filament FileUpload), timestamps.

## 2. Model + accessor
`App\Domain\School\Models\SchoolSetting` (HasUuids). Add a static `current(): self` that returns the
single row (create a default row if none exists, seeding `name` from `config('app.name')`). Cache it
per-request (a static property is fine).

## 3. Filament page — "School Settings"
A custom Filament **Page** (not a Resource — it edits one singleton), nav group `Settings`,
access `hasAnyRoleName(['school_admin','principal'])`. Form fields for every column above;
`school_type` as a Select (Boarding / Day / Both). On mount, load `SchoolSetting::current()`;
on save, update that row. Confirm it saves and reloads.

## 4. Wire the school name/type everywhere it's currently hardcoded
Today the school name comes from `config('app.name')`. Replace with `SchoolSetting::current()->name`
(fallback to config if empty) in ALL of these:
- `app/Http/Controllers/Api/AuthController.php@me` (`school_name`)
- `app/Domain/AI/Actions/ParentAssistant.php` and `PrincipalVacuum.php` (the `$school` var)
- `app/Domain/AI/Jobs/GenerateTermRemarks.php` (`$school`)
- `app/Http/Controllers/Api/ResultsController.php@reportCardPdf` (`$data['school_name']`)

## 5. Public profile endpoint (for the mobile app)
Add `GET /school/profile` (inside the `auth:sanctum` group is fine) returning:
`{ name, school_type, motto, logo_url, currency, principal_name, address, phone, email }`
from `SchoolSetting::current()`. Add a controller `SchoolController@profile`.

## Acceptance
- Migration runs; Filament shows a **School Settings** page where admin sets name + **Boarding/Day/Both** + motto etc., and it persists.
- `GET /school/profile` returns the settings (test with principal token `bih.patience@pssnkwen.local`/`secret`, login field `identifier`).
- Report card PDF + AI now use the configured school name.
- `php -l` clean on every edited file. Report what you changed when pushed.
