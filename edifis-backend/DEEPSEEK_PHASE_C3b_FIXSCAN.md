# DeepSeek Phase C3b — Fix attendance scan (500 on manual present-marking)

> VPS task (backend). `POST /attendance/sessions/{id}/scan` with `source: "manual_override"` (and no
> void_reason) returns **500**. Cause found by the architect: `App\Domain\Attendance\Actions\RecordScan`
> throws `InvalidArgumentException('manual_override requires void_reason')` for ANY manual scan — but
> marking a student PRESENT during roll-call has no void_reason (voiding has its own `voidScan` endpoint).
> The check is wrong for present-marking.
>
> ## RULES
> - START: `cd /opt/edifis && git pull`
> - REBUILD after change: `docker compose -f docker-compose.prod.yml --env-file .env.prod up -d --build app horizon`
> - END: `git add -A && git commit -m "fix: attendance manual present-scan no longer requires void_reason" && git push`
> - Delete this file; report "C3b done" + the 201 proof.

## Fix
In `app/Domain/Attendance/Actions/RecordScan.php`, **remove the void_reason requirement for present
marking**. A `manual_override` PRESENT scan must NOT require `void_reason`. Simplest: delete the block
```php
if ($source === 'manual_override' && empty($voidReason)) {
    throw new \InvalidArgumentException('manual_override requires void_reason');
}
```
(Voiding is handled by the separate `voidScan` endpoint, which already requires a reason — so nothing
is lost.) Leave the rest of `handle()` intact (it creates the AttendanceEvent with status `present`).

## Verify (paste)
```bash
cd /opt/edifis && git pull && cd edifis-infra/prod
DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
$DC up -d --build app horizon
API=https://pssnkwen.myedifis.com/api
TOK=$(curl -s -X POST $API/auth/login -H "Content-Type: application/json" -H "Accept: application/json" -d '{"identifier":"ngufor.calvin@pssnkwen.local","password":"secret"}' | sed -n 's/.*"token":"\([^"]*\)".*/\1/p')
A="Authorization: Bearer $TOK"; J="Accept: application/json"; C="Content-Type: application/json"
CLASS=$(curl -s $API/classes -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
SUBJ=$(curl -s $API/subjects -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
STU=$(curl -s $API/students -H "$A" -H "$J" | python3 -c 'import sys,json;print(json.load(sys.stdin)[0]["id"])')
SID=$(curl -s -X POST $API/attendance/sessions -H "$A" -H "$J" -H "$C" -d "{\"class_id\":\"$CLASS\",\"subject_id\":\"$SUBJ\",\"period\":\"Period 1\"}" | python3 -c 'import sys,json;print(json.load(sys.stdin)["id"])')
echo "scan:"; curl -s -w '\n[%{http_code}]\n' -X POST $API/attendance/sessions/$SID/scan -H "$A" -H "$J" -H "$C" -d "{\"student_id\":\"$STU\",\"source\":\"manual_override\"}"
echo "tally:"; curl -s $API/attendance/sessions/$SID/tally -H "$A" -H "$J"
```
The scan must be **201** and the tally `scanned` must be **1**. Report "C3b done" + those outputs.
