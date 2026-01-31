#!/bin/bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8089/api/agenda/index.php}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
APPOINTMENT_ID="${APPOINTMENT_ID:-demo}"
PATIENT_ID="${PATIENT_ID:-demo}"
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

run_test() {
  local title="$1"
  local expected_code="$2"
  local expected_message="$3"
  shift 3
  print_header "$title"
  local response
  response="$("$@")"
  echo "$response" | jq .
  assert_contract "$response"
  assert_meta_object "$response"
  assert_error_exact "$response" "$expected_code" "$expected_message"
}

run_test "Given agenda tables absent / GET availability" \
  db_not_ready "availability base schedule not ready" \
  curl -s -X GET "$BASE_URL/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE"

run_test "Given appointment events missing / GET events" \
  db_not_ready "appointment events not ready" \
  curl -s -X GET "$BASE_URL/appointments/$APPOINTMENT_ID/events"

run_test "Given patient flags missing / GET flags" \
  db_not_ready "patient flags not ready" \
  curl -s -X GET "$BASE_URL/patients/$PATIENT_ID/flags"

run_test "Given appointments missing / POST create" \
  invalid_params "invalid_params" \
  curl -s -X POST "$BASE_URL/appointments" -H 'Content-Type: application/json' -d '{}'

run_test "Given no appointment / PATCH reschedule" \
  not_found "appointment not found" \
  curl -s -X PATCH "$BASE_URL/appointments/unknown/reschedule" -H 'Content-Type: application/json' \
    -d '{"motivo_code":"test","from_start_at":"2026-02-01 09:00:00","from_end_at":"2026-02-01 09:30:00","to_start_at":"2026-02-02 09:00:00","to_end_at":"2026-02-02 09:30:00"}'

run_test "Given no appointment / POST cancel" \
  not_found "appointment not found" \
  curl -s -X POST "$BASE_URL/appointments/unknown/cancel" -H 'Content-Type: application/json' -d '{"motivo_code":"test"}'

run_test "Given invalid payload / POST no_show" \
  invalid_params "invalid_params" \
  curl -s -X POST "$BASE_URL/appointments/unknown/no_show" -H 'Content-Type: application/json' -d '{"motivo_code":"test"}'

print_header "Overrides note"
echo "Control overrides via modules/agenda/config/agenda.php (overrides_table=null / missing table / existing table)."

echo
print_header "QA script finished"
echo "Use environment vars to point to your server and adjust IDs/dates."
