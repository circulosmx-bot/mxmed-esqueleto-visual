#!/usr/bin/env bash
set -euo pipefail

API_BASE="${API_BASE:-http://127.0.0.1:8091/api/clinical/index.php}"
API_BASE="${API_BASE%/}"
APPT_ID="${1:-${APPT_ID:-fe61cdd67e97dcfde3a70c02}}"

MYSQL_HOST="${MYSQL_HOST:-127.0.0.1}"
MYSQL_PORT="${MYSQL_PORT:-3306}"
MYSQL_DB="${MYSQL_DB:-mxmed}"
MYSQL_USER="${MYSQL_USER:-mxmed}"

need_cmd() {
  command -v "$1" >/dev/null 2>&1 || {
    echo "ERROR: missing dependency '$1'" >&2
    exit 2
  }
}

need_cmd curl
need_cmd jq
need_cmd mysql

if [[ -z "$APPT_ID" ]]; then
  echo "ERROR: APPT_ID is required" >&2
  exit 2
fi

resolver_encounter_key=""
resolver_encounter_id=""
db_latest_encounter_id=""

encoded_appt_key="$(printf '%s' "appt:${APPT_ID}" | jq -sRr @uri)"
resolver_url="${API_BASE}/encounters/${encoded_appt_key}"

resolver_body="$(curl -sS "$resolver_url")"
resolver_ok="$(printf '%s' "$resolver_body" | jq -r '.ok // false')"
if [[ "$resolver_ok" != "true" ]]; then
  echo "FAIL: resolver returned ok=false"
  echo "resolver_encounter_key="
  echo "resolver_encounter_id="
  echo "db_latest_encounter_id="
  exit 1
fi

resolver_encounter_key="$(printf '%s' "$resolver_body" | jq -r '.data.encounter_key // ""')"
if [[ -z "$resolver_encounter_key" ]]; then
  echo "FAIL: resolver returned empty data.encounter_key"
  echo "resolver_encounter_key="
  echo "resolver_encounter_id="
  echo "db_latest_encounter_id="
  exit 1
fi

if [[ "$resolver_encounter_key" =~ \#enc:([0-9]+)$ ]]; then
  resolver_encounter_id="${BASH_REMATCH[1]}"
else
  echo "FAIL: unexpected encounter_key format: $resolver_encounter_key"
  echo "resolver_encounter_key=$resolver_encounter_key"
  echo "resolver_encounter_id="
  echo "db_latest_encounter_id="
  exit 1
fi

mysql_args=(-h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" "$MYSQL_DB" -N -s)
if [[ -z "${MYSQL_PWD:-}" ]]; then
  mysql_args=(-h "$MYSQL_HOST" -P "$MYSQL_PORT" -u "$MYSQL_USER" -p "$MYSQL_DB" -N -s)
fi

sql="SELECT encounter_id FROM clinical_encounters WHERE appointment_id='${APPT_ID//\'/\'\'}' ORDER BY encounter_dt DESC, encounter_id DESC LIMIT 1;"
db_latest_encounter_id="$(mysql "${mysql_args[@]}" -e "$sql" | tr -d '[:space:]')"

if [[ -z "$db_latest_encounter_id" ]]; then
  echo "FAIL: DB has no encounter for appointment_id=$APPT_ID"
  echo "resolver_encounter_key=$resolver_encounter_key"
  echo "resolver_encounter_id=$resolver_encounter_id"
  echo "db_latest_encounter_id="
  exit 1
fi

if [[ "$resolver_encounter_id" == "$db_latest_encounter_id" ]]; then
  echo "PASS: resolver returns latest encounter"
  exit 0
fi

echo "FAIL: resolver does not return latest encounter"
echo "resolver_encounter_key=$resolver_encounter_key"
echo "resolver_encounter_id=$resolver_encounter_id"
echo "db_latest_encounter_id=$db_latest_encounter_id"
exit 1
