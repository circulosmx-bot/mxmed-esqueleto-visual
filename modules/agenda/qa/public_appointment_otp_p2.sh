#!/usr/bin/env bash
set -euo pipefail

# QA reproducible for Agenda Publica P2 (OTP)
# Usage:
#   BASE_URL=http://127.0.0.1:8090 QA_MODE=1 bash modules/agenda/qa/public_appointment_otp_p2.sh
# Optional:
#   DOCTOR_ID=1 CONSULTORIO_ID=1 PATIENT_NAME="Paciente QA" PATIENT_PHONE="+5215512345678" PATIENT_EMAIL="qa@example.com"
#   START_AT="YYYY-MM-DD HH:MM:SS" END_AT="YYYY-MM-DD HH:MM:SS" (used when jq is unavailable)

BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-}"
PATIENT_NAME="${PATIENT_NAME:-Paciente Publico QA}"
PATIENT_PHONE="${PATIENT_PHONE:-+5215512345678}"
PATIENT_EMAIL="${PATIENT_EMAIL:-qa.publico@example.com}"
QA_MODE="${QA_MODE:-1}"
START_AT="${START_AT:-}"
END_AT="${END_AT:-}"

has_jq=0
if command -v jq >/dev/null 2>&1; then
  has_jq=1
fi

echo "============================================================"
echo "QA Agenda Publica P2 (OTP)"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "QA_MODE=$QA_MODE"
echo "============================================================"

availability_url="$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=next&days=1&limit_per_day=1"
if [[ -n "$CONSULTORIO_ID" ]]; then
  availability_url+="&consultorio_id=$CONSULTORIO_ID"
fi

echo
echo "[1] Fetch first available slot"
AVAIL_JSON="$(curl -sS "$availability_url")"
echo "$AVAIL_JSON" | head -c 800; echo

if [[ $has_jq -eq 1 ]]; then
  SLOT_START="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].start_at // empty')"
  SLOT_END="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].end_at // empty')"
  CONSULTORIO_USED="$(printf '%s' "$AVAIL_JSON" | jq -r '.meta.consultorio_id_used // empty')"
else
  SLOT_START="$START_AT"
  SLOT_END="$END_AT"
  CONSULTORIO_USED="$CONSULTORIO_ID"
fi

if [[ -z "$SLOT_START" || -z "$SLOT_END" ]]; then
  echo "[FAIL] Could not determine slot."
  echo "- If jq is not installed, provide START_AT and END_AT env vars."
  exit 1
fi

echo "[OK] Slot selected: $SLOT_START -> $SLOT_END"
if [[ -n "$CONSULTORIO_USED" ]]; then
  echo "[OK] consultorio_id_used: $CONSULTORIO_USED"
fi

echo
echo "[2] Request OTP"
REQUEST_PAYLOAD="$(cat <<JSON
{"doctor_id":"$DOCTOR_ID","start_at":"$SLOT_START","end_at":"$SLOT_END","patient_name":"$PATIENT_NAME","patient_phone":"$PATIENT_PHONE","patient_email":"$PATIENT_EMAIL"$( [[ -n "$CONSULTORIO_USED" ]] && printf ',"consultorio_id":"%s"' "$CONSULTORIO_USED" )}
JSON
)"

REQ_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/request" \
  -H "Content-Type: application/json" \
  -H "X-QA-Mode: $QA_MODE" \
  -d "$REQUEST_PAYLOAD")"

echo "$REQ_JSON" | head -c 1000; echo

if [[ $has_jq -eq 1 ]]; then
  REQ_OK="$(printf '%s' "$REQ_JSON" | jq -r '.ok // false')"
  REQUEST_ID="$(printf '%s' "$REQ_JSON" | jq -r '.data.request_id // empty')"
  OTP_DEBUG="$(printf '%s' "$REQ_JSON" | jq -r '.meta.otp_debug // empty')"
else
  REQ_OK="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"ok":\(true\|false\).*/\1/p' | head -n1)"
  REQUEST_ID="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"request_id":"\([^"]*\)".*/\1/p' | head -n1)"
  OTP_DEBUG="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"otp_debug":"\([0-9]\{6\}\)".*/\1/p' | head -n1)"
fi

if [[ "$REQ_OK" != "true" || -z "$REQUEST_ID" ]]; then
  echo "[FAIL] Request endpoint did not return request_id"
  exit 1
fi

if [[ -z "$OTP_DEBUG" ]]; then
  echo "[FAIL] otp_debug not found. Run with QA_MODE=1 for reproducible QA."
  exit 1
fi

echo "[OK] request_id=$REQUEST_ID"
echo "[OK] otp_debug=$OTP_DEBUG"

echo
echo "[3] Verify OTP"
VERIFY_PAYLOAD="{\"request_id\":\"$REQUEST_ID\",\"otp\":\"$OTP_DEBUG\"}"
VERIFY_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/verify" \
  -H "Content-Type: application/json" \
  -H "X-QA-Mode: $QA_MODE" \
  -d "$VERIFY_PAYLOAD")"

echo "$VERIFY_JSON" | head -c 1000; echo

if [[ $has_jq -eq 1 ]]; then
  VERIFY_OK="$(printf '%s' "$VERIFY_JSON" | jq -r '.ok // false')"
  APPOINTMENT_ID="$(printf '%s' "$VERIFY_JSON" | jq -r '.data.appointment_id // empty')"
else
  VERIFY_OK="$(printf '%s' "$VERIFY_JSON" | sed -n 's/.*"ok":\(true\|false\).*/\1/p' | head -n1)"
  APPOINTMENT_ID="$(printf '%s' "$VERIFY_JSON" | sed -n 's/.*"appointment_id":"\([^"]*\)".*/\1/p' | head -n1)"
fi

if [[ "$VERIFY_OK" != "true" || -z "$APPOINTMENT_ID" ]]; then
  echo "[FAIL] Verify endpoint did not confirm appointment"
  exit 1
fi

echo "[OK] appointment_id=$APPOINTMENT_ID"

echo
echo "[4] Fetch appointment"
GET_JSON="$(curl -sS "$BASE_URL/api/agenda/index.php/appointments/$APPOINTMENT_ID")"
echo "$GET_JSON" | head -c 1000; echo

if [[ $has_jq -eq 1 ]]; then
  GET_OK="$(printf '%s' "$GET_JSON" | jq -r '.ok // false')"
else
  GET_OK="$(printf '%s' "$GET_JSON" | sed -n 's/.*"ok":\(true\|false\).*/\1/p' | head -n1)"
fi

if [[ "$GET_OK" != "true" ]]; then
  echo "[FAIL] Appointment lookup failed"
  exit 1
fi

echo
echo "[OK] QA flow completed successfully"
exit 0
