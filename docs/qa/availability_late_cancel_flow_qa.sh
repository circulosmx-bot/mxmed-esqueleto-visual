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
DOCTOR_ID="${DOCTOR_ID:-qa_doc_late_cancel}"
CONSULTORIO_ID="${CONSULTORIO_ID:-qa_cons_late_cancel}"
PATIENT_ID="${PATIENT_ID:-p_qa_late_cancel}"
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

calc=$($PYTHON_BIN - <<PY
import datetime
now = datetime.datetime.now()
start = now + datetime.timedelta(hours=2)
end = start + datetime.timedelta(minutes=30)
if end.date() != start.date():
    start = (now + datetime.timedelta(days=1)).replace(hour=9, minute=0, second=0, microsecond=0)
    end = start + datetime.timedelta(minutes=30)
print("|".join([
    start.strftime("%Y-%m-%d %H:%M:%S"),
    end.strftime("%Y-%m-%d %H:%M:%S"),
    start.strftime("%Y-%m-%d"),
    start.strftime("%H:%M:%S"),
    end.strftime("%H:%M:%S"),
    str(start.isoweekday()),
]))
PY
)
IFS='|' read -r START_AT END_AT DATE START_TIME END_TIME WEEKDAY <<< "$calc"

cleanup_fixtures() {
  mysql_exec "DELETE e FROM agenda_appointment_events e JOIN agenda_appointments a ON a.appointment_id = e.appointment_id WHERE a.doctor_id='${DOCTOR_ID}' AND a.consultorio_id='${CONSULTORIO_ID}' AND DATE(a.start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_appointments WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND DATE(start_at)='${DATE}';"
  mysql_exec "DELETE FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code IN ('late_cancel');"
  mysql_exec "DELETE FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${WEEKDAY} AND start_time='${START_TIME}' AND end_time='${END_TIME}';"
}

setup_schedule() {
  mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '${DOCTOR_ID}','${CONSULTORIO_ID}',${WEEKDAY},'${START_TIME}','${END_TIME}',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='${DOCTOR_ID}' AND consultorio_id='${CONSULTORIO_ID}' AND weekday=${WEEKDAY} AND start_time='${START_TIME}' AND end_time='${END_TIME}');"
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
  "start_at": "$START_AT",
  "end_at": "$END_AT",
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

cancel_payload=$(cat <<EOF
{
  "reason_code": "late_cancel_qa",
  "reason_text": "QA late cancel",
  "actor_role": "system",
  "actor_id": "qa",
  "channel_origin": "qa_script",
  "patient_id": "$PATIENT_ID",
  "notify_patient": false,
  "contact_method": "whatsapp"
}
EOF
)

print_header "POST cancel (late cancel expected)"
cancel_resp=$(curl_request -X POST "$API_BASE/appointments/$appointment_id/cancel" -H 'Content-Type: application/json' -d "$cancel_payload")
echo "$cancel_resp" | jq .
if ! echo "$cancel_resp" | jq -e '.ok == true' >/dev/null; then
  fail_assert "cancel failed"
fi

print_header "Verify cancel + late_cancel events and grey flag"
cancel_event_count=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_canceled');" | tail -n 1 | tr -d '[:space:]')
late_event_count=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_late_cancel');" | tail -n 1 | tr -d '[:space:]')
flag_count=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code='late_cancel' AND flag_type='grey';" | tail -n 1 | tr -d '[:space:]')
if [[ -z "$cancel_event_count" || "$cancel_event_count" -lt 1 ]]; then
  fail_assert "missing appointment_canceled event"
fi
if [[ -z "$late_event_count" || "$late_event_count" -lt 1 ]]; then
  fail_assert "missing appointment_late_cancel event"
fi
if [[ -z "$flag_count" || "$flag_count" -lt 1 ]]; then
  fail_assert "missing grey flag for patient_id=$PATIENT_ID"
fi

print_header "POST cancel again (idempotent)"
cancel_again=$(curl_request -X POST "$API_BASE/appointments/$appointment_id/cancel" -H 'Content-Type: application/json' -d "$cancel_payload")
echo "$cancel_again" | jq .
cancel_event_after=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_canceled');" | tail -n 1 | tr -d '[:space:]')
late_event_after=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_appointment_events WHERE appointment_id='${appointment_id}' AND event_type IN ('appointment_late_cancel');" | tail -n 1 | tr -d '[:space:]')
flag_after=$(mysql_exec "SELECT COUNT(*) AS c FROM agenda_patient_flags WHERE patient_id='${PATIENT_ID}' AND reason_code='late_cancel' AND flag_type='grey';" | tail -n 1 | tr -d '[:space:]')
if [[ "$cancel_event_after" != "$cancel_event_count" ]]; then
  fail_assert "appointment_canceled duplicated (before=$cancel_event_count after=$cancel_event_after)"
fi
if [[ "$late_event_after" != "$late_event_count" ]]; then
  fail_assert "appointment_late_cancel duplicated (before=$late_event_count after=$late_event_after)"
fi
if [[ "$flag_after" != "$flag_count" ]]; then
  fail_assert "late_cancel flag duplicated (before=$flag_count after=$flag_after)"
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
