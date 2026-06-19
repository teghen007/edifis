# DeepSeek Phase B — Parent sees ONLY their own children (+ secure the endpoints)

> VPS task (backend). Right now `ParentPortalController@children` returns ALL students (pilot hack),
> and the per-child endpoints don't check ownership — any parent could read any student's data.
> Fix both: link guardians→children, return only the parent's kids, and authorize every per-child call.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - Backend code is baked into the image → after changes REBUILD:
>   `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - Migrations: run `... exec -T app php artisan migrate --force` AFTER the rebuild.
> - END: `git add -A && git commit -m "feat: scope parent to own children + authorize per-child endpoints" && git push`
> - Delete this file; report "Phase B done" + the checks.

## 1. Guardian→child link
If there's no guardian-student relationship yet, add one:
- Migration `guardian_students` table: `id` (uuid), `guardian_id` (→ users.id), `student_id` (→ students.id),
  timestamps; unique(`guardian_id`,`student_id`).
- On the `User` model add: `public function children(){ return $this->belongsToMany(\App\Domain\Students\Models\Student::class, 'guardian_students', 'guardian_id', 'student_id'); }`
  (adjust the Student model namespace/import to match this codebase.)
- Helper: `public function ownsStudent(string $studentId): bool { return $this->children()->whereKey($studentId)->exists(); }`

## 2. Seed: link the demo parent to 2 students (idempotent)
In the demo seeder, link parent phone `+237600000001` to **2** existing active students
(use `syncWithoutDetaching` / `firstOrCreate` so re-running is safe). Keep the seeder idempotent.

## 3. `ParentPortalController` — scope + authorize
- `children()`: return the authenticated parent's linked children only:
  ```php
  return response()->json($request->user()->children()->where('active', true)->get());
  ```
- `childBalance()`, `childResults()`, `childAttendance()`: **authorize ownership first**. At the top of each:
  ```php
  abort_unless($request->user()->ownsStudent($studentId), 403, 'Not your child.');
  ```
  (Add `Request $request` to each method signature if missing.)

## 4. Deploy
```bash
cd /opt/edifis && git pull
cd edifis-infra/prod
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon
docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan migrate --force
docker compose -f docker-compose.prod.yml --env-file .env.prod exec -T app php artisan db:seed --class=DemoDataSeeder --force   # adjust seeder name
```

## 5. Verify (paste)
```bash
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
echo -n "guardian links for demo parent: "
$DC exec -T postgres psql -U edifis -d edifis -t -c "SELECT count(*) FROM guardian_students gs JOIN users u ON u.id=gs.guardian_id WHERE u.phone='+237600000001';"
$DC exec -T app php artisan route:list 2>/dev/null | grep -c "parent/children"
```
Report "Phase B done" + the link count (should be **2**) + confirm `children()` now uses the
relationship and the 3 per-child endpoints call `abort_unless(... ownsStudent ...)`.
(The architect will verify via a real parent token that the app now shows only 2 children.)
