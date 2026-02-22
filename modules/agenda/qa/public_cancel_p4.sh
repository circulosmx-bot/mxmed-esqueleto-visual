#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONSULTORIO_ID="${CONSULTORIO_ID:-}"

if ! command -v jq >/dev/null 2>&1; then
  echo "[FAIL] jq is required"
  exit 1
fi

echo "============================================================"
echo "QA Agenda Publica P4 (cancel_token + liberar slot)"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "============================================================"

availability_url="$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=next&days=1&limit_per_day=1"
if [[ -n "$CONSULTORIO_ID" ]]; then
  availability_url+="&consultorio_id=$CONSULTORIO_ID"
fi

echo
echo "[1] Fetch available slot"
AVAIL_JSON="$(curl -sS "$availability_url")"
echo "$AVAIL_JSON" | jq .

SLOT_START="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].start_at // empty')"
SLOT_END="$(printf '%s' "$AVAIL_JSON" | jq -r '.data.days[0].slots[0].end_at // empty')"
CONSULTORIO_USED="$(printf '%s' "$AVAIL_JSON" | jq -r '.meta.consultorio_id_used // empty')"

if [[ -z "$SLOT_START" || -z "$SLOT_END" ]]; then
  echo "[FAIL] no slot available"
  exit 1
fi
if [[ -z "$CONSULTORIO_USED" && -n "$CONSULTORIO_ID" ]]; then
  CONSULTORIO_USED="$CONSULTORIO_ID"
fi

echo "[OK] slot=$SLOT_START -> $SLOT_END"

build_reserve_payload() {
  local patient_suffix="$1"
  jq -n \
    --arg doctor_id "$DOCTOR_ID" \
    --arg consultorio_id "$CONSULTORIO_USED" \
    --arg start_at "$SLOT_START" \
    --arg end_at "$SLOT_END" \
    --arg suffix "$patient_suffix" \
    '{
      doctor_id: $doctor_id,
      consultorio_id: $consultorio_id,
      start_at: $start_at,
      end_at: $end_at,
      visit_kind: "presencial",
      patient_type: "first_time",
      booker_is_patient: true,
      booker: {name: ("Paciente " + $suffix), phone: "+5215512345678", email: ("qa." + $suffix + "@example.com")},
      patient: {
        name: ("Paciente " + $suffix),
        phone: "+5215512345678",
        email: ("qa." + $suffix + "@example.com"),
        dob: "1990-01-01",
        gender: "M",
        reason: "QA cancel token"
      },
      payment_mode: "none"
    }'
}

echo
echo "[2] Reserve slot (expect cancel_token)"
RESERVE_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
  -H 'Content-Type: application/json' \
  -d "$(build_reserve_payload p4a)")"
echo "$RESERVE_JSON" | jq .

RESERVE_OK="$(printf '%s' "$RESERVE_JSON" | jq -r '.ok // false')"
APPOINTMENT_ID="$(printf '%s' "$RESERVE_JSON" | jq -r '.data.appointment_id // empty')"
CANCEL_TOKEN="$(printf '%s' "$RESERVE_JSON" | jq -r '.data.cancel_token // empty')"
if [[ "$RESERVE_OK" != "true" || -z "$APPOINTMENT_ID" || -z "$CANCEL_TOKEN" ]]; then
  echo "[FAIL] reserve failed or missing cancel_token"
  exit 1
fi
echo "[OK] appointment_id=$APPOINTMENT_ID cancel_token=$CANCEL_TOKEN"

echo
echo "[3] OTP request + confirm"
OTP_REQ_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/otp/request" \
  -H 'Content-Type: application/json' \
  -H 'X-MXMED-QA-Mode: 1' \
  -d "{\"doctor_id\":\"$DOCTOR_ID\",\"contact_type\":\"sms\",\"contact_value\":\"+5215512345678\"}")"
echo "$OTP_REQ_JSON" | jq .

OTP_ID="$(printf '%s' "$OTP_REQ_JSON" | jq -r '.data.otp_id // empty')"
DEBUG_CODE="$(printf '%s' "$OTP_REQ_JSON" | jq -r '.meta.debug_code // empty')"
if [[ -z "$OTP_ID" || -z "$DEBUG_CODE" ]]; then
  echo "[FAIL] otp request failed"
  exit 1
fi

CONFIRM_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/confirm" \
  -H 'Content-Type: application/json' \
  -d "{\"appointment_id\":\"$APPOINTMENT_ID\",\"otp_id\":$OTP_ID,\"code\":\"$DEBUG_CODE\"}")"
echo "$CONFIRM_JSON" | jq .
CONFIRM_OK="$(printf '%s' "$CONFIRM_JSON" | jq -r '.ok // false')"
if [[ "$CONFIRM_OK" != "true" ]]; then
  echo "[FAIL] confirm failed"
  exit 1
fi

echo
echo "[4] Cancel with cancel_token"
CANCEL_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/cancel" \
  -H 'Content-Type: application/json' \
  -d "{\"cancel_token\":\"$CANCEL_TOKEN\",\"reason\":\"QA cancel\"}")"
echo "$CANCEL_JSON" | jq .

CANCEL_OK="$(printf '%s' "$CANCEL_JSON" | jq -r '.ok // false')"
CANCEL_STATUS="$(printf '%s' "$CANCEL_JSON" | jq -r '.data.status // empty')"
if [[ "$CANCEL_OK" != "true" || "$CANCEL_STATUS" != "canceled" ]]; then
  echo "[FAIL] cancel failed"
  exit 1
fi

echo
echo "[5] Reserve same slot again (must be allowed)"
RESERVE2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
  -H 'Content-Type: application/json' \
  -d "$(build_reserve_payload p4b)")"
echo "$RESERVE2_JSON" | jq .

RESERVE2_OK="$(printf '%s' "$RESERVE2_JSON" | jq -r '.ok // false')"
RESERVE2_CANCEL_TOKEN="$(printf '%s' "$RESERVE2_JSON" | jq -r '.data.cancel_token // empty')"
if [[ "$RESERVE2_OK" != "true" ]]; then
  echo "[FAIL] second reserve should be allowed after cancel"
  exit 1
fi

echo
echo "[6] Cancel again same token (idempotent)"
CANCEL2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/cancel" \
  -H 'Content-Type: application/json' \
  -d "{\"cancel_token\":\"$CANCEL_TOKEN\",\"reason\":\"QA second cancel\"}")"
echo "$CANCEL2_JSON" | jq .

CANCEL2_OK="$(printf '%s' "$CANCEL2_JSON" | jq -r '.ok // false')"
if [[ "$CANCEL2_OK" != "true" ]]; then
  echo "[FAIL] second cancel should be idempotent"
  exit 1
fi

echo
echo "[7] invalid_token"
INVALID_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/cancel" \
  -H 'Content-Type: application/json' \
  -d '{"cancel_token":"invalid-token-qa-p4"}')"
echo "$INVALID_JSON" | jq .

INVALID_OK="$(printf '%s' "$INVALID_JSON" | jq -r '.ok // false')"
INVALID_ERROR="$(printf '%s' "$INVALID_JSON" | jq -r '.error // empty')"
if [[ "$INVALID_OK" != "false" || "$INVALID_ERROR" != "invalid_token" ]]; then
  echo "[FAIL] expected invalid_token"
  exit 1
fi

if [[ -n "$RESERVE2_CANCEL_TOKEN" ]]; then
  echo
  echo "[cleanup] cancel second reserve to avoid leaving pending_otp"
  CLEAN_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/cancel" \
    -H 'Content-Type: application/json' \
    -d "{\"cancel_token\":\"$RESERVE2_CANCEL_TOKEN\",\"reason\":\"QA cleanup\"}")"
  echo "$CLEAN_JSON" | jq .
fi

echo
echo "[PASS] QA P4 completed"
