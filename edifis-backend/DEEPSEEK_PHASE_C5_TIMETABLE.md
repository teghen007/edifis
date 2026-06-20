# DeepSeek Phase C5-backend — Timetable: real ids + resolved names + pending entries

> VPS task (backend). The seeded timetable uses placeholder class/subject ids that don't match the
> real `school_classes`/`subjects`/`users` — so it can't be displayed. Re-seed with real ids and
> return human names in `GET /timetable`, so the app can show a clean timetable + approvals list.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`; then `... exec -T app php artisan db:seed --class=DemoDataSeeder --force`
> - END: `git add -A && git commit -m "feat: timetable real ids + resolved names + pending entries" && git push`
> - Delete this file; report "C5-backend done" + the curl output.

## 1. Re-seed the timetable (idempotent) with REAL ids
Replace the old placeholder timetable rows. Create ~10 entries using **real** ids:
- `class_id` from `school_classes`, `subject_id` from `subjects`, `teacher_id` = a real teacher user
  (e.g. the subject_teacher `ngufor.calvin@pssnkwen.local`).
- `day_of_week` "1".."5" (Mon–Fri), `period_start`/`period_end` like "08:00"/"09:00", a `room`.
- Leave **3 of them `is_approved = false`** (pending) so the principal has something to approve;
  the rest `is_approved = true`.
- Delete/replace any rows whose class_id/subject_id don't exist in the new tables (the `f1a0…`/`a1a0…` ones).

## 2. `GET /timetable` — include resolved names
In `TimetableController@index`, after building the query, map each entry to include readable names
(join `school_classes`, `subjects`, and `users` for the teacher). Keep all existing fields AND add:
```json
{ "...existing fields...", "class_name":"Form 1", "subject_name":"Mathematics", "teacher_name":"Ngufor Calvin", "is_approved": false }
```
- `day_of_week` stays as-is (the app maps 1→Mon…5→Fri).
- Keep the role filtering already in `index()`.

## 3. (approve already works) — just confirm
`POST /timetable/{id}/approve` (principal) flips `is_approved`/sets `approved_by`. No change needed.

## 4. Deploy + verify (paste)
```bash
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"bih.patience@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
curl -s $API/timetable -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json
d=json.load(sys.stdin); print("count:",len(d)); print("pending:",sum(1 for e in d if not e.get("is_approved")))
print("sample:",{k:d[0][k] for k in ("class_name","subject_name","teacher_name","day_of_week","period_start","is_approved")})'
```
Report "C5-backend done" + the count + pending count + the sample (with real names), so the architect
confirms the shape before building the principal Approvals/Timetable screen.
