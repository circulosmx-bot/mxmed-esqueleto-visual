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
if [[ -z "${MYSQL_HOST:-}" ]]; then
  echo "MYSQL_HOST env var required"
  exit 1
fi
if [[ -z "${MYSQL_USER:-}" ]]; then
  echo "MYSQL_USER env var required"
  exit 1
fi
if [[ -z "${MYSQL_PASS:-}" ]]; then
  echo "MYSQL_PASS env var required"
  exit 1
fi

BASE_URL="$BASE_URL"
API_BASE="$BASE_URL/api/agenda/index.php"
QA_MODE="$QA_MODE"
MYSQL_HOST="$MYSQL_HOST"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_USER="$MYSQL_USER"
MYSQL_DB="${MYSQL_DB:-mxmed}"
MYSQL_ARGS="-p${MYSQL_PASS}"

need() {
  command -v "$1" >/dev/null 2>&1 || { echo "Missing dependency: $1"; exit 1; }
}

need mysql
need curl
need jq
need python3

QA_HEADER=()
if [[ -n "$QA_MODE" ]]; then
  QA_HEADER=(-H "X-QA-Mode: $QA_MODE")
fi

mysql_exec() {
  local sql="$1"
  mysql -h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" $MYSQL_ARGS "$MYSQL_DB" -e "$sql"
}

print_header() {
  echo
  echo "=== $1 ==="
}

print_header "Disable overrides for QA dates"
mysql_exec "UPDATE agenda_availability_overrides SET is_active=0 WHERE doctor_id='1' AND consultorio_id='1' AND date_ymd IN ('2026-02-02','2026-02-03','2026-02-04','2026-02-05','2026-02-06') AND is_active=1;"

print_header "Ensure base schedule (weekday=2) for doctor_id=1 consultorio_id=1"
mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '1','1',2,'09:00:00','12:00:00',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2 AND start_time='09:00:00' AND end_time='12:00:00');"
mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '1','1',2,'13:00:00','14:00:00',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2 AND start_time='13:00:00' AND end_time='14:00:00');"
mysql_exec "INSERT INTO consultorio_schedule (doctor_id, consultorio_id, weekday, start_time, end_time, is_active) SELECT '1','1',2,'15:00:00','18:00:00',1 WHERE NOT EXISTS (SELECT 1 FROM consultorio_schedule WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2 AND start_time='15:00:00' AND end_time='18:00:00');"

print_header "Overrides (doctor_id=1, consultorio_id=1)"
mysql_exec "SELECT override_id, date_ymd, type, start_at, end_at, is_active FROM agenda_availability_overrides WHERE doctor_id='1' AND consultorio_id='1' AND date_ymd IN ('2026-02-02','2026-02-03','2026-02-04','2026-02-05','2026-02-06') ORDER BY date_ymd, start_at;"

print_header "Schedule (weekday=2)"
mysql_exec "SELECT schedule_id, doctor_id, consultorio_id, weekday, start_time, end_time, is_active FROM consultorio_schedule WHERE doctor_id='1' AND consultorio_id='1' AND weekday=2 ORDER BY start_time;"

print_header "Ping (optional)"
curl -sS "${QA_HEADER[@]}" "$API_BASE/ping" | head -c 300 || true

echo
echo "RESULT: PASS"
