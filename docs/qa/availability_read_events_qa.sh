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
DOCTOR_ID="${DOCTOR_ID:-qa_doc_events}"
CONSULTORIO_ID="${CONSULTORIO_ID:-qa_cons_events}"
PATIENT_ID="${PATIENT_ID:-p_qa_events}"
DATE="${DATE:-2026-02-03}"
SLOT_MINUTES="${SLOT_MINUTES:-30}"
QA_MODE="$QA_MODE"
MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="${MYSQL_USER:-mxmed}"
MYSQL_DB="${MYSQL_DB:-mxmed}"
MYSQL_ARGS=""
if [[ -n "${MYSQL_PASS:-}" ]]; then
  MYSQL_ARGS="-p${MYSQL_PASS}"
else
  MYSQL_ARGS="-p"
fi

fail=0

need() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing dependency: $1"; exit 1; }
}

need curl
need jq
need mysql
need python3

QA_HEADER=()
if [[ -n "$QA_MODE" ]]; then
  QA_HEADER=(-H "X-QA-Mode: $QA_MODE")
fi

mysql_exec() {
  local sql="$1"
  mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" $MYSQL_ARGS "$MYSQL_DB" -e "$sql"
}

fail_assert() {
  echo "FAIL: $*"
  fail=1
}

curl_request() {
  curl -sS "${QA_HEADER[@]}" "$@"
}

print_header() {
  echo
  echo "=== $1 ==="
}

weekday=$(python3 - <<PY
import datetime
date = datetime.datetime.strptime("${DATE}", "%Y-%m-%d")
print(date.isoweekday())
PY
)

cleanup() {
  mysql_exec "DELETE e FROM agenda_appointment_events e JOIN agenda_appointments a ON a.appointment_id=e.appointment_id WHERE a.doctor_id='${DOCTOR_ID}' AND a.consultorio_id='${CONSULTORIO_ID}' AND DATE(a.start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_appointments WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND DATE(start_at)='${DATE}';"
  mysql_exec "DELETE FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${weekday} AND start_time='09:00:00' AND end_time='10:00:00';"
}

schedule() {
  mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '${DOCTOR_ID}','${CONSULTORIO_ID}',${weekday},'09:00:00','10:00:00',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${weekday} AND start_time='09:00:00' AND end_time='10:00:00');"
}

print_header "Cleanup fixtures"
cleanup

print_header "Setup schedule"
schedule

create_payload=$(cat <<EOF
{
  "doctor_id": "$DOCTOR_ID",
  "consultorio_id": "$CONSULTORIO_ID",
  "patient_id": "$PATIENT_ID",
  "start_at": "$DATE 09:00:00",
  "end_at": "$DATE 09:30:00",
  "slot_minutes": $SLOT_MINUTES,
  "modality": "presencial",
  "channel_origin": "qa_script",
  "created_by_role": "system",
  "created_by_id": "qa"
}
EOF
)

print_header "Create appointment for events"
create_resp=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$create_payload")
echo "$create_resp" | jq .
if ! echo "$create_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "create appointment failed"
fi
appointment_id=$(echo "$create_resp" | jq -r '.data.appointment_id // empty')
if [[ -z "$appointment_id" ]]; then
  fail_assert "missing appointment_id"
fi

print_header "GET appointment events"
events_resp=$(curl_request -X GET "$API_BASE/appointments/$appointment_id/events?limit=200")
echo "$events_resp" | jq .
if ! echo "$events_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "events read failed"
fi
count=$(echo "$events_resp" | jq -r '.meta.count // empty')
if [[ -z "$count" || "$count" -lt 1 ]]; then
  fail_assert "meta.count missing or <1"
fi
if echo "$events_resp" | jq -e '.data | type == "array"' >/dev/null; then
  length=$(echo "$events_resp" | jq -r '.data | length')
  if [[ "$length" -lt 1 ]]; then
    fail_assert "events data array empty"
  fi
  if echo "$events_resp" | jq -e '.data[0] | has("event_type")' >/dev/null; then
    created_count=$(echo "$events_resp" | jq -r '[.data[] | select(.event_type == "appointment_created")] | length')
    if [[ "$created_count" -lt 1 ]]; then
      fail_assert "missing appointment_created event"
    fi
  fi
else
  fail_assert "events data is not an array"
fi

print_header "Cleanup fixtures post-run"
cleanup

if (( fail > 0 )); then
  echo
  echo "RESULT: FAIL ($fail issues)"
  exit 1
fi

echo
echo "RESULT: PASS"
