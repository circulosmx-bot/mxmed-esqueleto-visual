#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8092}"
PATIENT_ID="${PATIENT_ID:-demo}"

fail() {
  echo "FAIL: $1"
  exit 1
}

extract_case_id() {
  sed -n 's/.*"case_id"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -n1
}

extract_active_case_id() {
  sed -n 's/.*"data"[[:space:]]*:[[:space:]]*{.*"case_id"[[:space:]]*:[[:space:]]*\([0-9][0-9]*\).*/\1/p' | head -n1
}

echo "BASE_URL=$BASE_URL"
echo "PATIENT_ID=$PATIENT_ID"

echo "--- create case #1 ---"
resp1="$(curl -sS -X POST "$BASE_URL/api/clinical/index.php/patients/$PATIENT_ID/cases" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"title":"Caso QA 1"}')"
echo "$resp1" | grep -q '"ok":true' || fail "create case #1"
case1="$(echo "$resp1" | extract_case_id)"
[ -n "$case1" ] || fail "case_id #1 missing"
echo "case1=$case1"

echo "--- get active (expect case1) ---"
active1="$(curl -sS "$BASE_URL/api/clinical/index.php/patients/$PATIENT_ID/cases/active")"
echo "$active1" | grep -q '"ok":true' || fail "get active #1"
active_case1="$(echo "$active1" | extract_active_case_id)"
[ "$active_case1" = "$case1" ] || fail "active case is not case1"

echo "--- assign fake item to case1 ---"
assign1="$(curl -sS -X POST "$BASE_URL/api/clinical/index.php/cases/$case1/items" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"item_type":"document","item_ref":"demo"}')"
echo "$assign1" | grep -q '"ok":true' || fail "assign item to case1"

echo "--- create case #2 (auto active) ---"
resp2="$(curl -sS -X POST "$BASE_URL/api/clinical/index.php/patients/$PATIENT_ID/cases" \
  -H 'Accept: application/json' -H 'Content-Type: application/json' \
  -d '{"title":"Caso QA 2"}')"
echo "$resp2" | grep -q '"ok":true' || fail "create case #2"
case2="$(echo "$resp2" | extract_case_id)"
[ -n "$case2" ] || fail "case_id #2 missing"
echo "case2=$case2"

echo "--- get active (expect case2) ---"
active2="$(curl -sS "$BASE_URL/api/clinical/index.php/patients/$PATIENT_ID/cases/active")"
echo "$active2" | grep -q '"ok":true' || fail "get active #2"
active_case2="$(echo "$active2" | extract_active_case_id)"
[ "$active_case2" = "$case2" ] || fail "active case is not case2"

echo "--- activate case1 explicitly ---"
act1="$(curl -sS -X POST "$BASE_URL/api/clinical/index.php/cases/$case1/activate" -H 'Accept: application/json')"
echo "$act1" | grep -q '"ok":true' || fail "activate case1"

echo "--- get active (expect case1 again) ---"
active3="$(curl -sS "$BASE_URL/api/clinical/index.php/patients/$PATIENT_ID/cases/active")"
echo "$active3" | grep -q '"ok":true' || fail "get active #3"
active_case3="$(echo "$active3" | extract_active_case_id)"
[ "$active_case3" = "$case1" ] || fail "active case is not case1 after activate"

echo "PASS: cases_smoke"
