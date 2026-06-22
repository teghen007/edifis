# TASK (cloud GLM-5.2, VPS) — Report Card v2: Mention + Class Statistics   [DO NOT DELETE]

**START:** `cd /opt/edifis && git pull --no-edit`
**END:** `git commit && git push`, then rebuild:
`cd edifis-infra/prod && docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
Then `php artisan migrate --force` if you add a migration (you shouldn't need one).

## Goal
Enrich the report card with the things a Cameroon principal expects: a **Mention** (appreciation
band) and **class statistics**. Backend only. Files: `app/Http/Controllers/Api/ResultsController.php`
(method `buildReportCard`) + the PDF blade `resources/views/results/report-card.blade.php`.

## Context (already in place — read before editing)
- `buildReportCard($studentId, $termId)` returns the report-card array (student_name, stream_name,
  term_name, overall_average, grade, position, out_of, ai_remark, subjects[]).
- Each `subjects[]` row already has: subject_name, average, grade, remark, **coefficient, weighted**.
- `term_results` has overall_average per student per (stream, term). `subject_results` has average per
  (student, subject, term). Weighted averages are already computed.

## Add these fields to the array returned by `buildReportCard`

1. **`mention`** (string) — derived from `overall_average` (out of 20):
   `>=18 Excellent`, `>=16 Very Good`, `>=14 Good`, `>=12 Fairly Good`, `>=10 Average`, `<10 Weak`.

2. **`class_average`** (number, 2dp) — average of `term_results.overall_average` for all students in
   this `stream_id` + `term_id`.

3. **Per-subject class stats** — for EACH row in `subjects[]`, add `class_avg`, `class_high`, `class_low`
   computed from `subject_results.average` across all students in this stream+term for that subject.
   Do it efficiently: one grouped query
   `SELECT subject_id, ROUND(AVG(average)::numeric,2) avg, MAX(average) hi, MIN(average) lo
    FROM subject_results WHERE stream_id=? AND term_id=? GROUP BY subject_id`
   then map onto each subject row. **Important:** Postgres `ROUND(double precision, n)` does NOT exist —
   cast to `::numeric` first (this exact bug already bit us once).

## Surface in the PDF (`report-card.blade.php`)
- Show **Mention** in the summary band (next to average/grade/position).
- Show **Class average** somewhere in the header meta.
- Add a small **"Class avg"** value under or beside each subject's mark (use `class_avg`).
  Keep the layout clean on A4; don't overflow.

## Acceptance
- `GET /results/report-card?student_id=&term_id=` returns `mention`, `class_average`, and each subject
  has `class_avg/class_high/class_low`.
- PDF renders Mention + class average without layout breakage.
- `php -l` clean. Test live: principal `bih.patience@pssnkwen.local` / `secret` (login field `identifier`),
  compute a stream first via `POST /results/compute`, then fetch a report card.
- Tell Claude when pushed so he reviews + the mobile side consumes the new fields.
