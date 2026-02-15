#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-}"

if ! command -v jq >/dev/null 2>&1; then
  echo "[FAIL] jq is required for this QA script"
  exit 1
fi

echo "============================================================"
echo "QA Agenda Publica P3 (reserve + otp + confirm + anti doble-booking)"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "============================================================"

availability_url="$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=next&days=1&limit_per_day=1"
if [[ -n "$CONSULTORIO_ID" ]]; then
  availability_url+="&consultorio_id=$CONSULTORIO_ID"
fi

echo
echo "[1] Fetch first available slot"
AVAIL_JSON="$(curl -sS "$availability_url")"
echo "$AVAIL_JSON" | jq .

SLOT_START="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].start_at // empty')"
SLOT_END="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].end_at // empty')"
CONSULTORIO_USED="$(printf '%s' "$AVAIL_JSON" | jq -r '.meta.consultorio_id_used // empty')"

if [[ -z "$SLOT_START" || -z "$SLOT_END" ]]; then
  echo "[FAIL] Could not determine slot from availability"
  exit 1
fi

if [[ -z "$CONSULTORIO_USED" && -n "$CONSULTORIO_ID" ]]; then
  CONSULTORIO_USED="$CONSULTORIO_ID"
fi

echo "[OK] Slot selected: $SLOT_START -> $SLOT_END"
echo "[OK] consultorio_id_used: ${CONSULTORIO_USED:-auto}"

echo
echo "[2] Reserve slot (pending_otp)"
RESERVE_PAYLOAD="$(jq -n \
  --arg doctor_id "$DOCTOR_ID" \
  --arg consultorio_id "$CONSULTORIO_USED" \
  --arg start_at "$SLOT_START" \
  --arg end_at "$SLOT_END" \
  '{
    doctor_id: $doctor_id,
    consultorio_id: $consultorio_id,
    start_at: $start_at,
    end_at: $end_at,
    visit_kind: "presencial",
    patient_type: "first_time",
    booker_is_patient: true,
    booker: {
      name: "Paciente QA",
      phone: "+5215512345678",
      email: "qa.publico@example.com"
    },
    patient: {
      name: "Paciente QA",
      phone: "+5215512345678",
      email: "qa.publico@example.com",
      dob: "1990-01-01",
      gender: "M",
      reason: "Chequeo QA"
    },
    extras: {
      address: {line1: "Calle QA 123", cp: "01000", city: "CDMX", state: "CDMX"},
      allergies: "ninguna",
      habits: "camina diario"
    },
    otp: {channel: "sms"},
    payment_mode: "none"
  }')"

RESERVE_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
  -H 'Content-Type: application/json' \
  -d "$RESERVE_PAYLOAD")"

echo "$RESERVE_JSON" | jq .

RESERVE_OK="$(printf '%s' "$RESERVE_JSON" | jq -r '.ok // false')"
APPOINTMENT_ID="$(printf '%s' "$RESERVE_JSON" | jq -r '.data.appointment_id // empty')"
if [[ "$RESERVE_OK" != "true" || -z "$APPOINTMENT_ID" ]]; then
  echo "[FAIL] reserve did not return appointment_id"
  exit 1
fi

echo "[OK] appointment_id=$APPOINTMENT_ID"

echo
echo "[3] Request OTP (QA debug_code)"
OTP_REQ_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/otp/request" \
  -H 'Content-Type: application/json' \
  -H 'X-MXMED-QA-Mode: 1' \
  -d "{\"doctor_id\":\"$DOCTOR_ID\",\"contact_type\":\"sms\",\"contact_value\":\"+5215512345678\"}")"

echo "$OTP_REQ_JSON" | jq .

OTP_REQ_OK="$(printf '%s' "$OTP_REQ_JSON" | jq -r '.ok // false')"
OTP_ID="$(printf '%s' "$OTP_REQ_JSON" | jq -r '.data.otp_id // empty')"
DEBUG_CODE="$(printf '%s' "$OTP_REQ_JSON" | jq -r '.meta.debug_code // empty')"

if [[ "$OTP_REQ_OK" != "true" || -z "$OTP_ID" || -z "$DEBUG_CODE" ]]; then
  echo "[FAIL] otp/request missing otp_id or debug_code"
  exit 1
fi

echo "[OK] otp_id=$OTP_ID debug_code=$DEBUG_CODE"

echo
echo "[4] Confirm appointment with OTP"
CONFIRM_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/confirm" \
  -H 'Content-Type: application/json' \
  -d "{\"appointment_id\":\"$APPOINTMENT_ID\",\"otp_id\":$OTP_ID,\"code\":\"$DEBUG_CODE\"}")"

echo "$CONFIRM_JSON" | jq .

CONFIRM_OK="$(printf '%s' "$CONFIRM_JSON" | jq -r '.ok // false')"
CONFIRM_STATUS="$(printf '%s' "$CONFIRM_JSON" | jq -r '.data.status // empty')"
if [[ "$CONFIRM_OK" != "true" || "$CONFIRM_STATUS" != "confirmed" ]]; then
  echo "[FAIL] confirm did not return confirmed"
  exit 1
fi

echo "[OK] confirmed"

echo
echo "[5] Re-try reserve same slot (must fail slot_taken)"
RESERVE_2_PAYLOAD="$(jq -n \
  --arg doctor_id "$DOCTOR_ID" \
  --arg consultorio_id "$CONSULTORIO_USED" \
  --arg start_at "$SLOT_START" \
  --arg end_at "$SLOT_END" \
  '{
    doctor_id: $doctor_id,
    consultorio_id: $consultorio_id,
    start_at: $start_at,
    end_at: $end_at,
    visit_kind: "presencial",
    patient_type: "follow_up",
    booker_is_patient: true,
    booker: {name: "Paciente QA 2", phone: "+5215512000000", email: "qa2.publico@example.com"},
    patient: {name: "Paciente QA 2", phone: "+5215512000000", email: "qa2.publico@example.com", dob: "1992-02-02", gender: "F"},
    payment_mode: "none"
  }')"

RESERVE_2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
  -H 'Content-Type: application/json' \
  -d "$RESERVE_2_PAYLOAD")"

echo "$RESERVE_2_JSON" | jq .

RESERVE_2_OK="$(printf '%s' "$RESERVE_2_JSON" | jq -r '.ok // false')"
RESERVE_2_ERROR="$(printf '%s' "$RESERVE_2_JSON" | jq -r '.error // empty')"
if [[ "$RESERVE_2_OK" != "false" || "$RESERVE_2_ERROR" != "slot_taken" ]]; then
  echo "[FAIL] expected slot_taken on second reserve"
  exit 1
fi

echo
echo "[OK] QA flow completed successfully"
