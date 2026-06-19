# DeepSeek Phase C2-Foundation — Academic backbone (subjects, classes, student→class)

> VPS task (backend). The DB has NO subjects/classes tables — only `marks`. Build the academic
> backbone so the app can list classes/subjects and (next) submit marks. Anglophone Cameroon structure.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - Backend code baked into image → after changes REBUILD:
>   `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - Run migrations AFTER rebuild: `... exec -T app php artisan migrate --force`
> - END: `git add -A && git commit -m "feat: academic backbone (subjects, classes, student-class)" && git push`
> - Delete this file; report "C2-Foundation done" + the curl outputs.

## 1. Migrations
- `school_classes`: `id` (uuid), `name` (string, e.g. "Form 1"), `level` (int, 1..7), `active` (bool default true), timestamps.
- `subjects`: `id` (uuid), `name` (string), `code` (string, short), `active` (bool default true), timestamps.
- Add `class_id` (uuid, nullable, FK→school_classes) to the `students` table.

## 2. Models
`App\Domain\Academics\Models\SchoolClass` (table `school_classes`) and
`App\Domain\Academics\Models\Subject`. On Student add `belongsTo(SchoolClass::class, 'class_id')` as `schoolClass()`.

## 3. Seed (idempotent — use firstOrCreate)
- **Classes** (level): Form 1 (1), Form 2 (2), Form 3 (3), Form 4 (4), Form 5 (5), Lower Sixth (6), Upper Sixth (7).
- **Subjects**: English Language (ENG), Literature in English (LIT), French (FRE), Mathematics (MAT),
  Biology (BIO), Chemistry (CHE), Physics (PHY), Geography (GEO), History (HIS), Economics (ECO),
  Computer Science (CSC), Religious Studies (RES), Citizenship (CIT).
- **Assign every existing student a class**: distribute the ~30 students across Form 1–Form 5
  (round-robin or random among levels 1–5). Make it idempotent (only set if `class_id` is null).

## 4. List endpoints (in the `auth:sanctum` group)
```php
Route::get('/classes',  [SchoolClassController::class, 'index'])->name('classes.index');   // any staff
Route::get('/subjects', [SubjectController::class, 'index'])->name('subjects.index');       // any staff
```
- `GET /classes`  → `[{ "id","name","level" }]` (active, ordered by level)
- `GET /subjects` → `[{ "id","name","code" }]` (active, ordered by name)
- (No role restriction beyond auth — all staff may read these.)

## 5. Update `GET /students` to return the real class
In `StudentController@index`, join the class and return its name:
```php
'class_name' => optional($s->schoolClass)->name ?? '',
'class_id'   => $s->class_id,
```
(Add `class_id` to the returned shape; keep the rest.)

## 6. Deploy + verify (paste outputs)
```bash
cd /opt/edifis && git pull && cd edifis-infra/prod
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
$DC up -d --build app horizon
$DC exec -T app php artisan migrate --force
$DC exec -T app php artisan db:seed --class=DemoDataSeeder --force   # adjust seeder name
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"ngufor.calvin@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
echo "classes:";  curl -s $API/classes  -H "Authorization: Bearer $TOK" -H "Accept: application/json"
echo; echo "subjects:"; curl -s $API/subjects -H "Authorization: Bearer $TOK" -H "Accept: application/json"
echo; echo "students w/ class:"; curl -s $API/students -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(d[0])'
```
Report "C2-Foundation done" + classes list + subjects list + one student showing a real `class_name`.
