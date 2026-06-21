# DeepSeek Phase F — Results engine (grade rules + compute averages/grades/ranks + report card)

> VPS task (backend). Turn raw marks into RESULTS — the thing schools pay for. Modeled on
> UnifiedTransform's `grade_rules` + `final_marks`. Compute per-subject averages → grade, per-student
> term average → class position, and expose a report card + mastersheet. EDIFIS marks are on a /20 scale
> (score out of max_score). Reuse the academic core from D1.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `... up -d --build app horizon`; then `migrate --force` + `db:seed --class=DemoDataSeeder --force`
> - END: `git add -A && git commit -m "feat: results engine (grades, averages, ranks, report card)" && git push`
> - Delete this file; report "Phase F done" + the report-card + mastersheet JSON.

## 1. Migrations (uuid PKs)
- `grade_rules`: `grade` (string e.g. "A"), `point` (float), `min_score` (float), `max_score` (float),
  `remark` (string). (Bands on a /20 scale.)
- `subject_results`: `student_id`, `subject_id`, `stream_id`, `term_id`, `average` (float /20),
  `grade` (string), `point` (float). unique(student_id,subject_id,term_id).
- `term_results`: `student_id`, `stream_id`, `term_id`, `overall_average` (float /20), `grade` (string),
  `total_points` (float), `position` (int), `subjects_count` (int). unique(student_id,term_id).

## 2. Seed grade_rules (idempotent) — standard Cameroon /20 bands
| grade | point | min | max | remark |
|---|---|---|---|---|
| A | 4 | 16 | 20 | Excellent |
| B | 3 | 14 | 15.99 | Very Good |
| C | 2 | 12 | 13.99 | Good |
| D | 1.5 | 10 | 11.99 | Average |
| E | 1 | 8 | 9.99 | Weak |
| F | 0 | 0 | 7.99 | Fail |

## 3. Compute action `App\Domain\Results\Actions\ComputeResults`
`handle(string $streamId, string $termId)`:
- Students = enrolled in the stream (`student_stream`).
- Tests = the tests in `$termId`.
- For each student × each subject they take (`student_subject`):
  - Pull that student's `marks` for that subject whose `sequence` matches a test name in the term
    (marks store `sequence` = test name; match by the term's test names). Normalise each to /20:
    `score / max_score * 20`. **average** them → subject `average` (/20).
  - Map `average` to a `grade_rules` row (min ≤ average ≤ max) → `grade` + `point`. Upsert `subject_results`.
- Per student: `overall_average` = mean of their subject averages; `total_points` = sum of points;
  grade from grade_rules; `subjects_count`. Then **rank** students in the stream by `overall_average`
  DESC → `position` (ties share the position). Upsert `term_results`.
- Idempotent (re-running recomputes/overwrites).

## 4. Endpoints (auth:sanctum)
```php
Route::post('/results/compute', [ResultsController::class,'compute'])->middleware('role:principal|vice_principal|school_admin'); // {stream_id, term_id}
Route::get('/results/report-card', [ResultsController::class,'reportCard']);   // ?student_id=&term_id=  (staff, or parent for own child)
Route::get('/results/mastersheet', [ResultsController::class,'mastersheet']);  // ?stream_id=&term_id=  (staff)
```
- **reportCard** → `{ student_name, stream_name, term_name, overall_average, grade, position, out_of,
  subjects:[{subject_name, average, grade, remark}] }`.
- **mastersheet** → `{ stream_name, term_name, subjects:[names], students:[{name, marks:{subject:avg}, overall_average, grade, position}] }`.
- Parent calling reportCard must own the student (reuse `ownsStudent`).

## 5. Deploy + verify (paste)
```bash
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"bih.patience@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
A="Authorization: Bearer $TOK"; J="Accept: application/json"
STREAM=$(curl -s $API/streams -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
TERM=$(curl -s $API/terms -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
echo "compute:"; curl -s -w '\n[%{http_code}]\n' -X POST $API/results/compute -H "$A" -H "$J" -H "Content-Type: application/json" -d "{\"stream_id\":\"$STREAM\",\"term_id\":\"$TERM\"}"
STU=$(curl -s $API/students -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
echo "report card:"; curl -s $API/results/report-card?student_id=$STU\&term_id=$TERM -H "$A" -H "$J"
echo; echo "mastersheet (first student):"; curl -s "$API/results/mastersheet?stream_id=$STREAM&term_id=$TERM" -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print("subjects:",d.get("subjects"));print("student0:",d.get("students",[{}])[0])'
```
Report "Phase F done" + the compute result + a sample report card + the mastersheet sample.
(If a student has no marks yet, averages will be 0/F — that's fine; the engine still ranks.)
