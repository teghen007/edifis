# TASK (BACKEND dev #1) — Conduct & Discipline   [DO NOT DELETE]

**Checkout:** use the VPS `/opt/edifis`.  **START:** `git pull --no-edit`.
**END:** `git commit && git push`, rebuild `cd edifis-infra/prod && docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`, `php artisan migrate --force`.

⚠️ **Touch only these files** (keep the diff tight; do NOT edit DemoDataSeeder — Claude seeds demo data):
- NEW: `database/migrations/2026_06_23_000001_create_conduct_records_table.php`
- NEW: `app/Domain/Conduct/Models/ConductRecord.php`
- NEW: `app/Http/Controllers/Api/ConductController.php`
- NEW: `app/Filament/Resources/ConductRecordResource.php` (+ its Pages)
- EDIT: `routes/api.php` (add 2 routes only, near the other student routes)
- EDIT: `app/Http/Controllers/Api/ResultsController.php` (method `buildReportCard` only — add conduct)
- EDIT: `resources/views/results/report-card.blade.php` (show conduct)

## Build
1. **Migration** `conduct_records`: `id` uuid pk, `student_id` uuid, `term_id` uuid, `stream_id` uuid,
   `conduct_grade` string (e.g. Excellent/Good/Fair/Poor), `punctuality` string nullable,
   `comment` text nullable, `recorded_by` uuid nullable, timestamps. Unique `(student_id, term_id)`.
2. **Model** `ConductRecord` (HasUuids, table `conduct_records`, fillable all above).
3. **API** `ConductController`:
   - `POST /conduct` — record/update conduct for a student+term (upsert on student_id+term_id).
     Validate. Middleware `role:discipline_master|principal|vice_principal`.
   - `GET /conduct?stream_id=&term_id=` — list conduct for a stream/term (same roles).
   Add both routes in `routes/api.php` inside the `auth:sanctum` group.
4. **Filament** `ConductRecordResource` — let `discipline_master|principal|school_admin` CRUD conduct
   (form: student select, term select, conduct_grade, punctuality, comment). `canAccess()` via
   `hasAnyRoleName([...])`. Nav group `Discipline`.
5. **Report card** — in `buildReportCard`, look up the student's `conduct_records` row for the term and
   add to the returned array: `conduct_grade`, `conduct_comment` (null-safe). In the PDF blade, show a
   small "Conduct: X" line in the meta/summary area (don't break A4).

## Acceptance
- Migration runs; `POST /conduct` upserts; report card JSON includes `conduct_grade`.
- `php -l` clean. Test live (principal `bih.patience@pssnkwen.local`/`secret`, login field `identifier`).
- Report what you changed when pushed.
