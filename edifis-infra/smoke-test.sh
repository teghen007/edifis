#!/usr/bin/env bash
# Smoke test for EDIFIS infra dev stack. Run after `docker compose up -d`.
# Confirms the health endpoint responds through the compose network.
set -euo pipefail

BASE_URL="${1:-http://localhost:8000}"
MAX_RETRIES=30
RETRY_DELAY=2

echo "Smoke test: EDIFIS infra stack"
echo "Target: $BASE_URL/api/health"
echo ""

for i in $(seq 1 $MAX_RETRIES); do
  RESP=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/health" 2>/dev/null || echo "000")
  if [ "$RESP" = "200" ]; then
    echo "PASS — health endpoint returned 200 on attempt $i"
    BODY=$(curl -s "$BASE_URL/api/health")
    echo "  Response: $BODY"
    if echo "$BODY" | grep -q '"status":"ok"'; then
      echo "PASS — status is ok"
    else
      echo "FAIL — status field missing or not ok"
      exit 1
    fi
    if echo "$BODY" | grep -q '"mode":'; then
      echo "PASS — mode field present"
    else
      echo "FAIL — mode field missing"
      exit 1
    fi
    echo "Smoke test passed."
    exit 0
  fi
  echo "Attempt $i/$MAX_RETRIES: health not ready (HTTP $RESP), retrying in ${RETRY_DELAY}s..."
  sleep $RETRY_DELAY
done

echo "FAIL — health endpoint did not respond with 200 after $MAX_RETRIES attempts"
exit 1
