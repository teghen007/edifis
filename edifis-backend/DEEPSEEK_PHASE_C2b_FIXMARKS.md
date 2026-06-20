# DeepSeek Phase C2b — Fix POST /academics/marks (500 on a valid payload)

> VPS task (backend debug). `POST /api/academics/marks` returns **500** even with a fully valid
> payload. The architect confirmed: validation passes, every NOT-NULL column on `marks` is provided,
> and seeded marks exist (so `Mark::create` works). The error is in the WRITE path —
> `App\Domain\Academics\Actions\RecordMark` → `App\Domain\Audit\Services\AuditLogger` (the seeder
> bypassed audit, which is why seeded marks worked but a real POST doesn't).
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD after code changes: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "fix: marks write path (RecordMark/AuditLogger)" && git push`
> - Delete this file; report "C2b done" + the 201 proof.

## 1. Reproduce
```bash
cd /opt/edifis/edifis-infra/prod
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"ngufor.calvin@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
CLASS=$(curl -s $API/classes  -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
SUBJ=$(curl -s $API/subjects -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
STU=$(curl -s $API/students -H "Authorization: Bearer $TOK" -H "Accept: application/json" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
ID=$(python3 -c 'import uuid;print(uuid.uuid4())')
curl -s -w '\n[%{http_code}]\n' -X POST $API/academics/marks -H "Authorization: Bearer $TOK" -H "Content-Type: application/json" -H "Accept: application/json" \
  -d "{\"id\":\"$ID\",\"revision\":\"1\",\"student_id\":\"$STU\",\"subject_id\":\"$SUBJ\",\"class_id\":\"$CLASS\",\"sequence\":\"Sequence 1\",\"score\":15,\"max_score\":20,\"published\":true}"
```

## 2. Get the REAL exception
`php artisan tinker` is NOT installed (prod is --no-dev). To see the actual error, either:
- temporarily set `APP_DEBUG=true` in `.env.prod`, `$DC up -d --force-recreate app`, re-run the curl
  (the JSON response will now include the exception message + file/line), then **set it back to false**
  and recreate again; **OR**
- inspect `App\Domain\Audit\Services\AuditLogger::log()` and the `audit_logs` (or equivalent) table —
  a missing table/column or a non-nullable field there is the most likely cause.

## 3. Fix
Likely culprits (confirm with the real error):
- The audit table/columns the `AuditLogger` writes to don't exist or have a NOT-NULL field that isn't set
  → add the migration / make nullable / set the value.
- A JSON cast on `before`/`after`.
- A FK on `marks.subject_id`/`class_id` pointing at a table that didn't exist when older rows were seeded.
Fix the root cause so a valid POST returns **201** with the created mark.

## 4. Verify
Re-run the curl from step 1 → must be **201** and return the mark JSON. Also confirm an audit row
was written. Report "C2b done" + the 201 body + which fix you applied.
```
```
