# DeepSeek Phase D1 — Academic core (Years, Sections, Streams, Terms, Tests + joins)

> VPS task (backend). Add the full Cameroon-secondary academic model (modelled on the Flexio SMS):
> **Academic Year → Section → Class → Stream → Students**, plus the assignment join tables that enable
> scoped permissions and per-student subjects. This phase is **purely additive** — do NOT change the
> existing marks/attendance/students tables' behaviour; we layer on top. Seed it by mapping the data we
> already have (7 classes, 13 subjects, ~30 students, demo teachers) into the new structure.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - then `... exec -T app php artisan migrate --force` and `... exec -T app php artisan db:seed --class=DemoDataSeeder --force`
> - END: `git add -A && git commit -m "feat: academic core (years/sections/streams/terms/tests + joins)" && git push`
> - Delete this file; report "D1 done" + the verify outputs.

## 1. Migrations (uuid PKs, timestamps)
- `academic_years`: name (e.g. "2025-2026"), is_current (bool), starts_on (date,nullable), ends_on (date,nullable)
- `sections`: name (e.g. "Secondary")
- `streams`: name (e.g. "Form 1"), class_id→school_classes, section_id→sections, academic_year_id→academic_years, active(bool)
- `terms`: name (e.g. "Term 1"), academic_year_id, position(int)
- `tests`: name (e.g. "Sequence 1"), term_id→terms, position(int), default_max(int default 20)
- `subject_stream`: subject_id→subjects, stream_id→streams  (which subjects a stream offers) — unique(subject_id,stream_id)
- `student_stream`: student_id→students, stream_id→streams, academic_year_id  (enrolment) — unique(student_id,stream_id)
- `student_subject`: student_id→students, subject_id→subjects, stream_id→streams  (per-student subjects) — unique(student_id,subject_id)
- `teacher_assignments`: teacher_id→users, subject_id→subjects, stream_id→streams  (scoped: teaches subject to stream) — unique(teacher_id,subject_id,stream_id)
- `class_masters`: teacher_id→users, stream_id→streams  (class master of a stream) — unique(teacher_id,stream_id)

## 2. Models + relationships
Create Eloquent models in `App\Domain\Academics\Models` (AcademicYear, Section, Stream, Term, Test) and
pivots where useful. Wire `belongsTo`/`belongsToMany`:
- Stream belongsTo SchoolClass, Section, AcademicYear; belongsToMany Subject (via subject_stream), Student (via student_stream).
- Student belongsToMany Stream, Subject (student_subject).
- User (teacher): hasMany TeacherAssignment; helper `teachesSubjectInStream($subjectId,$streamId): bool` and
  `assignedStreams()` / `assignedSubjects()` for scoping later phases.

## 3. Idempotent seed — map existing data into the new model
In the demo seeder (safe to re-run):
- Academic year **"2025-2026"** with `is_current=true`.
- One section **"Secondary"**.
- For **each** existing `school_classes` row → one `streams` row (name = the class name, e.g. "Form 1"),
  linked to that class + the section + the year.
- Three terms: **Term 1, Term 2, Term 3** (position 1-3).
- Tests: **two sequences per term** → Sequence 1..6 (Seq 1,2 in Term 1; 3,4 in Term 2; 5,6 in Term 3), default_max 20.
- `subject_stream`: offer **all 13 subjects** to every stream (default; admin refines later).
- `student_stream`: enrol each existing student into the stream whose class matches the student's `class_id`.
- `student_subject`: give each student **all** their stream's subjects (default).
- `teacher_assignments`: assign **ngufor.calvin** (subject_teacher) to **Mathematics + Biology** in **Form 4 and Form 5** streams.
- `class_masters`: make **songhi.kingsley** (class_master) the class master of the **Form 3** stream.

## 4. Verify endpoints (add to the auth:sanctum group, any staff)
```php
Route::get('/streams',  [App\Http\Controllers\Api\AcademicController::class, 'streams']);   // [{id,name,class_name,section_name,year}]
Route::get('/terms',    [App\Http\Controllers\Api\AcademicController::class, 'terms']);     // [{id,name,position,tests:[{id,name}]}]
```
(Just enough to confirm the model; richer endpoints come in later phases.)

## 5. Deploy + verify (paste)
```bash
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"bih.patience@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
A="Authorization: Bearer $TOK"; J="Accept: application/json"
echo "streams:"; curl -s $API/streams -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(len(d),"streams; sample:",d[0] if d else "none")'
echo "terms:";   curl -s $API/terms   -H "$A" -H "$J" | python3 -c 'import sys,json;d=json.load(sys.stdin);print(len(d),"terms; tests in t1:",len(d[0]["tests"]) if d else 0)'
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
echo "enrolments:"; $DC exec -T postgres psql -U edifis -d edifis -t -c "SELECT count(*) FROM student_stream;"
echo "teacher assignments:"; $DC exec -T postgres psql -U edifis -d edifis -t -c "SELECT count(*) FROM teacher_assignments;"
```
Report "D1 done" + streams count + terms/tests + enrolment count + teacher-assignment count.
