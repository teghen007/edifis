# DeepSeek Task — Backend foundation for dashboards (`/me` + `/dashboard/summary` + seed)

> Work in `edifis-backend/`. The Flutter app needs read endpoints + demo data so the
> dashboards aren't empty. Add two endpoints and seed demo data, then deploy on the server.
> Keep responses EXACTLY as specified below (the app is built against these shapes).
> When done, delete this file and report "Dashboard API done" with the curl outputs.

## 0. SYNC FIRST (always)
```bash
cd /opt/edifis && git pull
```
Make sure you have the latest commit BEFORE editing anything.

## 1. `GET /me` (authenticated)
Add inside the `auth:sanctum` group in `routes/api.php`:
```php
Route::get('/me', [AuthController::class, 'me'])->name('auth.me');
```
`AuthController@me`:
```php
public function me(\Illuminate\Http\Request $request)
{
    $u = $request->user();
    return response()->json([
        'user_id'     => $u->id,
        'name'        => $u->name,
        'role'        => $u->getRoleNames()->first(),
        'email'       => $u->email,
        'school_name' => config('app.name'),  // or the tenant's display name if available
    ]);
}
```
**Required response shape:** `{ user_id, name, role, email, school_name }`.

## 2. `GET /dashboard/summary` (authenticated, role-aware)
Add inside the `auth:sanctum` group:
```php
Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->name('dashboard.summary');
```
Create `App\Http\Controllers\Api\DashboardController@summary`. It returns a **list of cards**
tailored to the caller's role. **Required shape:**
```json
{ "cards": [ { "key": "students", "label": "Students", "value": "42", "icon": "users" } ] }
```
- `value` is always a **string** (so "96%" and "1,250,000 XAF" work).
- `icon` is a **Lucide name** (kebab-case): `users`, `calendar-check`, `wallet`, `book-open`,
  `clipboard-check`, `award`, `bell`.
- Compute real values from the tenant DB. Suggested cards per role:
  - **principal / vice_principal:** Students (count), Attendance today (%), Fees collected (term total), Timetable approvals (pending count)
  - **bursar:** Fees collected, Outstanding balance, Students
  - **class_master:** My class students, Attendance today (%)
  - **subject_teacher:** My subjects, Marks submitted
  - **secretary:** Students
  - **discipline_master:** Attendance today (%), Absentees today
- If a value can't be computed yet, return a sensible `"0"` — never error.

## 3. Seed demo data (so the dashboards + timetable aren't empty)
Extend the tenant seeder (or add a `DemoDataSeeder`) for `pssnkwen` to create:
- ~30 students across a couple of classes
- some marks (a few subjects), some attendance sessions with scans
- some fee issuances / balances
- a few **timetable** entries (so `GET /timetable` returns data) + a couple **calendar** events
- **one demo parent**: phone `+237600000001`, PIN `1234`, linked to 2 of the students
  (so we can test parent login + parent dashboard later)
Make the seeder **idempotent** (safe to re-run).

## 4. Deploy on the server
```bash
# on your PC: commit + push
git add edifis-backend && git commit -m "feat(api): /me + /dashboard/summary + demo seed" && git push
# on the server:
ssh edifis "cd /opt/edifis && git pull && cd edifis-infra/prod && \
  docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan migrate --force && \
  docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan db:seed --class=DemoDataSeeder --force && \
  docker compose -f docker-compose.prod.yml --env-file .env.prod restart app"
```
(Adjust the seeder class name to whatever you create.)

## 5. Verify (paste these outputs back)
```bash
TOKEN=$(curl -s https://pssnkwen.myedifis.com/api/auth/login -H 'Content-Type: application/json' \
  -d '{"identifier":"bih.patience@pssnkwen.local","password":"secret"}' | python3 -c 'import sys,json;print(json.load(sys.stdin)["token"])')
curl -s https://pssnkwen.myedifis.com/api/me            -H "Authorization: Bearer $TOKEN"
curl -s https://pssnkwen.myedifis.com/api/dashboard/summary -H "Authorization: Bearer $TOKEN"
curl -s https://pssnkwen.myedifis.com/api/timetable     -H "Authorization: Bearer $TOKEN"
```
Both `/me` and `/dashboard/summary` must return the shapes above, and `/timetable` must be non-empty.

## 6. Finish
Delete this file. Report "Dashboard API done" + the three curl outputs so the architect can
write the dashboard UI stage against the real data.
