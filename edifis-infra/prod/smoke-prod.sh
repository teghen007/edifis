#!/usr/bin/env bash
# Post-deploy smoke for EDIFIS production. Run from this directory (edifis-infra/prod)
# on the VPS after deploying:  ./smoke-prod.sh [https://pssnkwen.myedifis.com]
#
# Two nets:
#   1. Panel smoke AS www-data — renders every panel page as the web user and
#      checks the compiled-view cache is writable by www-data. This catches the
#      "root-owned cache poisons www-data -> every page 500s" outage that a
#      root-run smoke test silently passes.
#   2. A real HTTP GET of the live login page (through nginx/php-fpm as www-data).
set -uo pipefail

DC="docker compose -f docker-compose.prod.yml --env-file .env.prod"
BASE="${1:-https://pssnkwen.myedifis.com}"
fail=0

echo "== 1/2 Panel smoke (as www-data) =="
$DC exec -u www-data -T app php artisan edifis:smoke-panel || fail=1

echo ""
echo "== 2/2 Live login page =="
code=$(curl -s -o /dev/null -w "%{http_code}" "$BASE/staff/login" || echo "000")
if [ "$code" = "200" ]; then
  echo "PASS — $BASE/staff/login returned 200"
else
  echo "FAIL — $BASE/staff/login returned $code"
  fail=1
fi

echo ""
if [ "$fail" = "0" ]; then
  echo "Prod smoke PASSED."
else
  echo "Prod smoke FAILED."
  exit 1
fi
