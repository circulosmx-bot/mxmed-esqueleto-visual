#!/usr/bin/env bash
set -euo pipefail

if [[ -z "${BASE_URL:-}" ]]; then
  echo "BASE_URL env var required"
  exit 1
fi
if [[ -z "${QA_MODE:-}" ]]; then
  echo "QA_MODE env var required"
  exit 1
fi

BASE_URL="$BASE_URL"
API_BASE="$BASE_URL/api/agenda/index.php"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-1}"
DATE="${DATE:-2026-02-03}"
SLOT_MINUTES="${SLOT_MINUTES:-30}"
QA_MODE="$QA_MODE"

fail=0

need() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing dependency: $1"; exit 1; }
}

need curl
need jq
need mysql

QA_HEADER=()
if [[ -n "$QA_MODE" ]]; then
  QA_HEADER=(-H "X-QA-Mode: $QA_MODE")
fi

fail_assert() {
  echo "FAIL: $*"
  fail=1
}

curl_request() {
  curl -sS "${QA_HEADER[@]}" "$@"
}

mysql_exec() {
  local sql="$1"
  if [[ -n "${MYSQL_PASS:-}" ]]; then
    mysql -h 127.0.0.1 -P 3306 -u mxmed -p"$MYSQL_PASS" mxmed -e "$sql"
  else
    mysql -h 127.0.0.1 -P 3306 -u mxmed -p mxmed -e "$sql"
  fi
}

cleanup_fixtures() {
  mysql_exec "DELETE e FROM agenda_appointment_events e JOIN agenda_appointments a ON a.appointment_id = e.appointment_id WHERE a.doctor_id='${DOCTOR_ID}' AND a.consultorio_id='${CONSULTORIO_ID}' AND DATE(a.start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_appointments WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND DATE(start_at)='${DATE}';"
}

assert_json() {
  local resp="$1"
  if [[ "${resp:0:1}" != "{" ]]; then
    fail_assert "response is not JSON"
    return 1
  fi
  for k in '"ok"' '"error"' '"message"' '"data"' '"meta"'; do
    if ! grep -q "$k" <<<"$resp"; then
      fail_assert "missing key $k"
      return 1
    fi
  done
}

assert_ok() {
  local resp="$1"
  if ! echo "$resp" | jq -e '.ok == true' >/dev/null; then
    fail_assert "expected ok:true"
    echo "$resp" | jq .
  fi
}

assert_error() {
  local resp="$1"
  local code="$2"
  if ! echo "$resp" | jq -e --arg code "$code" '.ok == false and .error == $code' >/dev/null; then
    fail_assert "expected error=$code"
    echo "$resp" | jq .
  fi
}

print_header() {
  echo
  echo "=== $1 ==="
}

print_header "Cleanup fixtures (appointments/events)"
cleanup_fixtures

print_header "Check availability with slots"
availability_resp=$(curl_request -X GET "$API_BASE/availability?doctor_id=$DOCTOR_ID&consultorio_id=$CONSULTORIO_ID&date=$DATE&slot_minutes=$SLOT_MINUTES")
echo "$availability_resp" | jq .
assert_json "$availability_resp"
if ! echo "$availability_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "availability not ok:true"
else
  slots_count=$(echo "$availability_resp" | jq -r '.data.slots | length')
  if [[ "$slots_count" -lt 1 ]]; then
    fail_assert "expected slots_count >= 1 for $DATE"
  fi
fi
busy_count=$(echo "$availability_resp" | jq -r '.meta.busy_count // empty')
if [[ -z "$busy_count" ]]; then
  fail_assert "busy_count missing; aborting QA to avoid false collisions"
elif [[ "$busy_count" != "0" ]]; then
  fail_assert "DB not clean; busy_count=$busy_count; aborting QA to avoid false collisions"
fi

create_payload() {
  local start_at="$1"
  local end_at="$2"
  cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "start_at": "$start_at",
  "end_at": "$end_at",
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa",
  "slot_minutes": $SLOT_MINUTES
}
EOF
}

print_header "Create appointment A (valid slot)"
resp_a=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$(create_payload "$DATE 09:00:00" "$DATE 09:30:00")")
echo "$resp_a" | jq .
assert_json "$resp_a"
assert_ok "$resp_a"
appointment_a=$(echo "$resp_a" | jq -r '.data.appointment_id // empty')
if [[ -z "$appointment_a" ]]; then
  fail_assert "missing appointment_id for A"
fi

print_header "Create appointment B (valid slot)"
resp_b=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$(create_payload "$DATE 10:00:00" "$DATE 10:30:00")")
echo "$resp_b" | jq .
assert_json "$resp_b"
assert_ok "$resp_b"
appointment_b=$(echo "$resp_b" | jq -r '.data.appointment_id // empty')
if [[ -z "$appointment_b" ]]; then
  fail_assert "missing appointment_id for B"
fi

print_header "Create appointment collision (should fail)"
resp_collision=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$(create_payload "$DATE 09:00:00" "$DATE 09:30:00")")
echo "$resp_collision" | jq .
assert_json "$resp_collision"
assert_error "$resp_collision" "collision"

print_header "Create appointment outside schedule (should fail)"
resp_outside=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$(create_payload "$DATE 07:00:00" "$DATE 07:30:00")")
echo "$resp_outside" | jq .
assert_json "$resp_outside"
if ! echo "$resp_outside" | jq -e '.ok == false and (.error == "outside_schedule" or .error == "slot_unavailable")' >/dev/null; then
  fail_assert "expected outside_schedule or slot_unavailable"
  echo "$resp_outside" | jq .
fi

reschedule_payload() {
  local from_start="$1"
  local from_end="$2"
  local to_start="$3"
  local to_end="$4"
  cat <<EOF
{
  "motivo_code": "qa_reschedule",
  "motivo_text": "QA reschedule",
  "from_start_at": "$from_start",
  "from_end_at": "$from_end",
  "to_start_at": "$to_start",
  "to_end_at": "$to_end",
  "channel_origin": "qa_script",
  "actor_role": "system",
  "actor_id": "qa",
  "slot_minutes": $SLOT_MINUTES
}
EOF
}

print_header "Reschedule A into collision (should fail)"
resp_reschedule_collision=$(curl_request -X PATCH "$API_BASE/appointments/$appointment_a/reschedule" -H 'Content-Type: application/json' -d "$(reschedule_payload "$DATE 09:00:00" "$DATE 09:30:00" "$DATE 10:00:00" "$DATE 10:30:00")")
echo "$resp_reschedule_collision" | jq .
assert_json "$resp_reschedule_collision"
assert_error "$resp_reschedule_collision" "collision"

print_header "Reschedule A into valid slot (should pass)"
resp_reschedule_ok=$(curl_request -X PATCH "$API_BASE/appointments/$appointment_a/reschedule" -H 'Content-Type: application/json' -d "$(reschedule_payload "$DATE 09:00:00" "$DATE 09:30:00" "$DATE 11:00:00" "$DATE 11:30:00")")
echo "$resp_reschedule_ok" | jq .
assert_json "$resp_reschedule_ok"
assert_ok "$resp_reschedule_ok"

if (( fail > 0 )); then
  echo
  print_header "Cleanup fixtures (post-run)"
  cleanup_fixtures
  echo "RESULT: FAIL ($fail issues)"
  exit 1
fi

print_header "Cleanup fixtures (post-run)"
cleanup_fixtures

echo
echo "RESULT: PASS"
