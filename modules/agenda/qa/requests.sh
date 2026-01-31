#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8089/api/agenda/index.php}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
APPOINTMENT_ID="${APPOINTMENT_ID:-unknown}"
PATIENT_ID="${PATIENT_ID:-unknown}"
DATE="${DATE:-2026-02-01}"

need() {
  if ! command -v "$1" >/dev/null 2>&1; then
    echo "Dependency '$1' is required" >&2
    exit 1
  fi
}

need curl
need jq

print_header() {
  local title="$1"
  echo
  echo "=== $title ==="
}

LAST_RESPONSE=""

assert_contract() {
  local body="$1"
  if ! echo "$body" | jq -e 'has("ok") and has("error") and has("message") and has("data") and has("meta")' >/dev/null; then
    echo "Response is missing JSON contract fields" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

assert_meta_object() {
  local body="$1"
  if ! echo "$body" | jq -e '.meta | type == "object"' >/dev/null; then
    echo "meta is not an object" >&2
    echo "$body" | jq .meta >&2 || true
    exit 1
  fi
}

assert_error_exact() {
  local body="$1" code="$2" message="$3"
  if ! echo "$body" | jq -e --arg code "$code" --arg msg "$message" '(.error == $code) and (.message == $msg) and (.meta | type == "object")' >/dev/null; then
    echo "Expected error=$code message='$message' but got:" >&2
    echo "$body" | jq . >&2 || true
    exit 1
  fi
}

call() {
  local title="$1"
  shift
  print_header "$title"
  local response
  response="$("$@")"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  LAST_RESPONSE="$response"
}

call "GET availability" curl -s -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE"

print_header "Overrides scenarios (controlled via modules/agenda/config/agenda.php)"
echo "- Set overrides_table=null to keep availability running with meta.overrides_enabled=false"
echo "- Set overrides_table='tabla_missing' (absent table) to observe db_not_ready 'availability overrides not ready'"
echo "- Once overrides_table points to an existing table, expect ok:true with meta.overrides_enabled=true"

echo

call "GET appointment events" curl -s -X GET "$BASE_URL/appointments/$APPOINTMENT_ID/events"

call "GET patient flags" curl -s -X GET "$BASE_URL/patients/$PATIENT_ID/flags"

call "POST create appointment (invalid payload)" curl -s -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d '{}'

call "PATCH reschedule (unknown appointment)" \
  curl -s -X PATCH "$BASE_URL/appointments/unknown/reschedule" -H 'Content-Type: application/json' \
    -d '{"motivo_code":"test","from_start_at":"2026-02-01 09:00:00","from_end_at":"2026-02-01 09:30:00","to_start_at":"2026-02-02 09:00:00","to_end_at":"2026-02-02 09:30:00"}'
assert_error_exact "$LAST_RESPONSE" "not_found" "appointment not found"

call "POST cancel (unknown appointment)" \
  curl -s -X POST "$BASE_URL/appointments/unknown/cancel" -H 'Content-Type: application/json' -d '{"motivo_code":"test"}'
assert_error_exact "$LAST_RESPONSE" "not_found" "appointment not found"

call "POST no_show (unknown appointment)" \
  curl -s -X POST "$BASE_URL/appointments/unknown/no_show" -H 'Content-Type: application/json' -d '{"motivo_code":"test"}'
assert_error_exact "$LAST_RESPONSE" "not_found" "appointment not found"

echo
print_header "QA script completed"
echo "Adjust BASE_URL / IDs / DATE via env vars and rerun."
