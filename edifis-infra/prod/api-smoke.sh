#!/usr/bin/env bash
# EDIFIS API smoke test — happy / error / edge across the whole HTTP API.
#
# Logs in as every staff role and fires ~200 requests at ~70 endpoints through
# the real stack (TLS -> nginx -> auth -> roles -> validation -> controller -> DB).
# It asserts: auth is enforced (401 w/o token), roles are enforced (403 wrong
# role), reads return 2xx, bad writes are 4xx — and NOTHING returns 5xx.
#
# Read-only + validation focused: it does NOT persist data into the target.
# Runs against the demo school by default; override for another tenant:
#   SCHOOL=pssnkwen PW=secret ./api-smoke.sh
#   BASE=https://otherschool.myedifis.com/api ./api-smoke.sh
#
# Exit code 0 = all green, 1 = failures (use as a post-deploy gate).

SCHOOL="${SCHOOL:-pssnkwen}"
BASE="${BASE:-https://${SCHOOL}.myedifis.com/api}"
PW="${PW:-secret}"
P=0; F=0; FIVE=0
BODYF="$(mktemp)"

login() { # email -> token
  curl -s --max-time 30 -X POST "$BASE/auth/login" -H "Content-Type: application/json" -H "Accept: application/json" \
    -d "{\"identifier\":\"$1\",\"password\":\"${PW}\"}" | grep -oE '"token":"[^"]+"' | head -1 | sed 's/.*:"//;s/"//'
}

req() { # METHOD PATH TOKEN DATA  (retries transient connection drops)
  local m=$1 p=$2 tok=$3 data=$4
  local a=(-s --max-time 30 -o "$BODYF" -w "%{http_code}" -X "$m" "$BASE$p" -H "Accept: application/json")
  [ -n "$tok" ] && a+=(-H "Authorization: Bearer $tok")
  [ -n "$data" ] && a+=(-H "Content-Type: application/json" -d "$data")
  for _ in 1 2 3; do
    STATUS=$(curl "${a[@]}")
    [ "$STATUS" != "000" ] && break
    sleep 1
  done
}

t() { # label expected METHOD PATH TOKEN DATA   (expected: exact code | 2xx | 4xx)
  local label=$1 exp=$2; shift 2
  req "$@"
  local ok=0
  case "$exp" in
    2xx) [[ "$STATUS" =~ ^2 ]] && ok=1 ;;
    4xx) [[ "$STATUS" =~ ^4 ]] && ok=1 ;;
    *)   [ "$STATUS" = "$exp" ] && ok=1 ;;
  esac
  [[ "$STATUS" =~ ^5 ]] && FIVE=$((FIVE+1))
  if [ "$ok" = 1 ]; then P=$((P+1));
  else F=$((F+1)); echo "  FAIL exp=$exp got=$STATUS  $label  :: $(head -c 110 "$BODYF" | tr -d '\n')"; fi
}

echo "Target: $BASE"
echo "Logging in as each role..."
PRIN=$(login bih.patience@${SCHOOL}.local)
VP=$(login nkweta.therese@${SCHOOL}.local)
BUR=$(login nebaluices@${SCHOOL}.local)
CM=$(login songhi.kingsley@${SCHOOL}.local)
DM=$(login tangwo.jerome@${SCHOOL}.local)
SEC=$(login rita.awah@${SCHOOL}.local)
ST=$(login ngufor.calvin@${SCHOOL}.local)
SA=$(login admin@${SCHOOL}.local)
for v in PRIN VP BUR CM DM SEC ST SA; do [ -n "${!v}" ] && echo "  ok $v" || echo "  MISSING TOKEN $v"; done
if [ -z "$PRIN" ]; then echo "FATAL: could not log in (check SCHOOL/PW or that the stack is up)."; exit 1; fi

# Real ids for parametrised routes
get_id() { curl -s --max-time 30 "$BASE$1" -H "Accept: application/json" -H "Authorization: Bearer $PRIN" | grep -oE '"id":"[^"]+"' | head -1 | sed 's/.*:"//;s/"//'; }
SID=$(get_id /students)
STREAM=$(get_id /streams)
TERM=$(get_id /terms)
YEAR=$(get_id /season/years)
echo "  sample student=$SID stream=$STREAM term=$TERM year=$YEAR"
BOGUS="00000000-0000-0000-0000-000000000000"

echo ""; echo "== GROUP A: auth required (no token -> 401) =="
for ep in \
  "GET /me" "GET /me/assignments" "GET /school/profile" "GET /dashboard/summary" \
  "GET /students" "GET /classes" "GET /subjects" "GET /streams" "GET /terms" "GET /conduct" \
  "POST /students" "POST /conduct" "POST /academics/marks" "GET /students/admission-template" \
  "POST /students/admission-upload" "GET /students/$BOGUS/id-card" "POST /students/$BOGUS/photo" \
  "GET /enrollment/template" "POST /enrollment/upload" "GET /marks/template" "POST /marks/upload" \
  "POST /issuance/issue" "POST /issuance/return" "GET /attendance/sections" "GET /attendance/rollcall" \
  "POST /attendance/rollcall" "GET /attendance/absentees" "POST /attendance/sessions" \
  "POST /attendance/void" "GET /attendance/sessions/$BOGUS/tally" "GET /fees/balances" "GET /fees/overview" \
  "GET /fees/template" "POST /fees/upload" "POST /fees/bill" "GET /fees/students/$BOGUS/balance" \
  "POST /timetable" "GET /timetable" "POST /timetable/$BOGUS/approve" "POST /vacuum/query" "POST /vacuum/command" \
  "GET /parent/children" "POST /parent/ask" "GET /parent/calendar" "GET /onboarding/requests" \
  "GET /season" "GET /season/years" "POST /season/advance" "POST /season/close-year" "POST /results/compute" \
  "POST /promotions/deliberate" "GET /promotions" "GET /results/overview" "POST /fcm/register" ; do
  set -- $ep; t "no-token $1 $2" 401 "$1" "$2" "" ""
done

echo ""; echo "== GROUP B: happy reads (authorised role -> 2xx) =="
t "me"                 2xx GET /me "$PRIN"
t "me/assignments"     2xx GET /me/assignments "$PRIN"
t "school/profile"     2xx GET /school/profile "$PRIN"
t "dashboard/summary"  2xx GET /dashboard/summary "$PRIN"
t "students"           2xx GET /students "$PRIN"
t "classes"            2xx GET /classes "$PRIN"
t "subjects"           2xx GET /subjects "$PRIN"
t "streams"            2xx GET /streams "$PRIN"
t "terms"              2xx GET /terms "$PRIN"
t "conduct index"      2xx GET "/conduct?stream_id=$STREAM&term_id=$TERM" "$PRIN"
t "attendance/sections" 2xx GET /attendance/sections "$PRIN"
t "attendance/absentees" 2xx GET /attendance/absentees "$PRIN"
t "fees/balances"      2xx GET /fees/balances "$BUR"
t "fees/overview"      2xx GET /fees/overview "$BUR"
t "fees/template"      2xx GET /fees/template "$BUR"
t "timetable index"    2xx GET /timetable "$PRIN"
t "season show"        2xx GET /season "$PRIN"
t "season years"       2xx GET /season/years "$PRIN"
t "promotions index"   2xx GET "/promotions?stream_id=$STREAM&academic_year_id=$YEAR" "$PRIN"
t "results/overview"   2xx GET /results/overview "$PRIN"
[ -n "$SID" ] && t "student id-card pdf" 2xx GET "/students/$SID/id-card" "$PRIN"
[ -n "$SID" ] && t "fees balance"        2xx GET "/fees/students/$SID/balance" "$BUR"

echo ""; echo "== GROUP C: role enforcement (wrong role -> 403) =="
t "students.store as teacher"      403 POST /students "$ST" '{}'
t "vacuum.command as bursar"       403 POST /vacuum/command "$BUR" '{}'
t "timetable.approve as VP"        403 POST "/timetable/$BOGUS/approve" "$VP" '{}'
t "season.close-year as bursar"    403 POST /season/close-year "$BUR" '{}'
t "issuance.issue as principal"    403 POST /issuance/issue "$PRIN" '{}'
t "onboarding.requests as prin"    403 GET /onboarding/requests "$PRIN" ""
t "parent.children as principal"   403 GET /parent/children "$PRIN" ""
t "marks.store as discipline"      403 POST /academics/marks "$DM" '{}'

echo ""; echo "== GROUP D: validation (authorised + bad/empty body -> 4xx, never 5xx) =="
t "login empty"                4xx POST /auth/login "" '{}'
t "students.store empty"       4xx POST /students "$SEC" '{}'
t "admission-upload no file"   4xx POST /students/admission-upload "$SEC" '{}'
t "marks.store empty"          4xx POST /academics/marks "$ST" '{}'
t "conduct.store empty"        4xx POST /conduct "$DM" '{}'
t "timetable.store empty"      4xx POST /timetable "$PRIN" '{}'
t "fees.bill empty"            4xx POST /fees/bill "$BUR" '{}'
t "enrollment.upload no file"  4xx POST /enrollment/upload "$CM" '{}'
t "marks.upload no file"       4xx POST /marks/upload "$ST" '{}'
t "photo no file"              4xx POST "/students/$BOGUS/photo" "$SEC" '{}'

echo ""; echo "== GROUP E: edge cases =="
t "id-card bogus id -> 404"        404 GET "/students/$BOGUS/id-card" "$PRIN" ""
t "fees balance bogus id (graceful, no 5xx)" 2xx GET "/fees/students/$BOGUS/balance" "$BUR" ""
t "garbage bearer token -> 401"    401 GET /me "garbage.token.value" ""
t "wrong password -> 4xx"          4xx POST /auth/login "" "{\"identifier\":\"bih.patience@${SCHOOL}.local\",\"password\":\"WRONG\"}"
t "unknown user -> 4xx"            4xx POST /auth/login "" '{"identifier":"ghost@nope.local","password":"x"}'
t "approve bogus timetable (prin)" 4xx POST "/timetable/$BOGUS/approve" "$PRIN" '{}'
[ -n "$STREAM" ] && t "enrollment template ok" 2xx GET "/enrollment/template?stream_id=$STREAM" "$PRIN" ""

echo ""; echo "== PUBLIC endpoints =="
t "health"            200 GET /health "" ""
t "schools"           2xx GET /schools "" ""
t "onboarding submit empty" 4xx POST /onboarding/request "" '{}'

echo ""
echo "================ RESULTS ================"
echo "PASS=$P  FAIL=$F   5xx-responses=$FIVE"
rm -f "$BODYF"
if [ "$F" = 0 ] && [ "$FIVE" = 0 ]; then
  echo "ALL GREEN"
  exit 0
fi
echo "SEE FAILURES ABOVE"
exit 1
