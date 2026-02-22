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
DOCTOR_ID="${DOCTOR_ID:-qa_doc_no_show}"
CONSULTORIO_ID="${CONSULTORIO_ID:-qa_cons_no_show}"
PATIENT_ID="${PATIENT_ID:-p_qa_no_show}"
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
PYTHON_BIN=$(command -v python3 || command -v python || true)
if [[ -z "$PYTHON_BIN" ]]; then
  echo "Missing dependency: python3/python"
  exit 1
fi

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

weekday=$($PYTHON_BIN - <<PY
import datetime
print(datetime.datetime.strptime("${DATE}", "%Y-%m-%d").isoweekday())
PY
)
START_TIME="09:00:00"
END_TIME="10:00:00"

cleanup_fixtures() {
  mysql_exec "DELETE e FROM agenda_appointment_events e JOIN agenda_appointments a ON a.appointment_id = e.appointment_id WHERE a.doctor_id='${DOCTOR_ID}' AND a.consultorio_id='${CONSULTORIO_ID}' AND DATE(a.start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_appointments WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND DATE(start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code IN ('no_show');"
  mysql_exec "DELETE FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${weekday} AND start_time='${START_TIME}' AND end_time='${END_TIME}';"
}

setup_schedule() {
  mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '${DOCTOR_ID}','${CONSULTORIO_ID}',${weekday},'${START_TIME}','${END_TIME}',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${weekday} AND start_time='${START_TIME}' AND end_time='${END_TIME}');"
}

print_header() {
  echo
  echo "=== $1 ==="
}

print_header "Cleanup fixtures (appointments/events/flags/schedule)"
cleanup_fixtures

print_header "Setup schedule fixture"
setup_schedule

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

print_header "Create appointment (fixture)"
create_resp=$(curl_request -X POST "$API_BASE/appointments" -H 'Content-Type: application/json' -d "$create_payload")
echo "$create_resp" | jq .
if ! echo "$create_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "create appointment failed"
fi
appointment_id=$(echo "$create_resp" | jq -r '.data.appointment_id // empty')
if [[ -z "$appointment_id" ]]; then
  fail_assert "missing appointment_id"
fi

no_show_payload=$(cat <<EOF
{
  "reason_code": "qa_no_show",
  "reason_text": "QA no show",
  "actor_role": "system",
  "actor_id": "qa",
  "channel_origin": "qa_script",
  "patient_id": "$PATIENT_ID",
  "notify_patient": false,
  "contact_method": "whatsapp"
}
EOF
)

print_header "POST no-show"
no_show_resp=$(curl_request -X POST "$API_BASE/appointments/$appointment_id/no-show" -H 'Content-Type: application/json' -d "$no_show_payload")
echo "$no_show_resp" | jq .
if ! echo "$no_show_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "no-show failed"
fi

print_header "Verify no_show event + black flag"
event_count=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_no_show');" | tail -n 1 | tr -d '[:space:]')
flag_count=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code='no_show' AND flag_type='black';" | tail -n 1 | tr -d '[:space:]')
if [[ -z "$event_count" || "$event_count" -lt 1 ]]; then
  fail_assert "missing no_show event for appointment_id=$appointment_id"
fi
if [[ -z "$flag_count" || "$flag_count" -lt 1 ]]; then
  fail_assert "missing black flag for patient_id=$PATIENT_ID"
fi

print_header "POST no-show again (idempotent)"
no_show_again=$(curl_request -X POST "$API_BASE/appointments/$appointment_id/no-show" -H 'Content-Type: application/json' -d "$no_show_payload")
echo "$no_show_again" | jq .
event_count_after=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_no_show');" | tail -n 1 | tr -d '[:space:]')
flag_count_after=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code='no_show' AND flag_type='black';" | tail -n 1 | tr -d '[:space:]')
if [[ "$event_count_after" != "$event_count" ]]; then
  fail_assert "no_show event duplicated (before=$event_count after=$event_count_after)"
fi
if [[ "$flag_count_after" != "$flag_count" ]]; then
  fail_assert "no_show flag duplicated (before=$flag_count after=$flag_count_after)"
fi

print_header "Cleanup fixtures (post-run)"
cleanup_fixtures

if (( fail > 0 )); then
  echo
  echo "RESULT: FAIL ($fail issues)"
  exit 1
fi
echo
echo "RESULT: PASS"
