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
echo "QA Agenda Publica P5 (expire pending_otp)"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "============================================================"

availability_url="$BASE_URL/api/agenda/index.php/public/availability?doctor_id=$DOCTOR_ID&mode=next&days=1&limit_per_day=20"
if [[ -n "$CONSULTORIO_ID" ]]; then
  availability_url+="&consultorio_id=$CONSULTORIO_ID"
fi

AVAIL_JSON="$(curl -sS "$availability_url")"
if [[ "$(printf '%s' "$AVAIL_JSON" | jq -r '.ok // false')" != "true" ]]; then
  echo "$AVAIL_JSON" | jq .
  echo "[FAIL] availability failed"
  exit 1
fi

SLOTS="$(printf '%s' "$AVAIL_JSON" | jq -c '.data.days[0].slots[]?')"
if [[ -z "$SLOTS" ]]; then
  echo "$AVAIL_JSON" | jq .
  echo "[FAIL] no slots"
  exit 1
fi
CONSULTORIO_USED="$(printf '%s' "$AVAIL_JSON" | jq -r '.meta.consultorio_id_used // empty')"
if [[ -z "$CONSULTORIO_USED" && -n "$CONSULTORIO_ID" ]]; then
  CONSULTORIO_USED="$CONSULTORIO_ID"
fi

build_reserve_payload() {
  local start_at="$1"
  local end_at="$2"
  jq -n \
    --arg doctor_id "$DOCTOR_ID" \
    --arg consultorio_id "$CONSULTORIO_USED" \
    --arg start_at "$start_at" \
    --arg end_at "$end_at" \
    '{
      doctor_id: $doctor_id,
      consultorio_id: $consultorio_id,
      start_at: $start_at,
      end_at: $end_at,
      visit_kind: "presencial",
      patient_type: "first_time",
      booker_is_patient: true,
      booker: {name: "Paciente P5", phone: "+5215511111111", email: "p5@example.com"},
      patient: {name: "Paciente P5", phone: "+5215511111111", email: "p5@example.com", dob: "1990-01-01", gender: "M"},
      payment_mode: "none"
    }'
}

APPOINTMENT_ID=""
CANCEL_TOKEN=""
SLOT_START=""
SLOT_END=""

while IFS= read -r SLOT; do
  SLOT_START="$(printf '%s' "$SLOT" | jq -r '.start_at')"
  SLOT_END="$(printf '%s' "$SLOT" | jq -r '.end_at')"

  RESERVE_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
    -H 'Content-Type: application/json' \
    -d "$(build_reserve_payload "$SLOT_START" "$SLOT_END")")"

  if [[ "$(printf '%s' "$RESERVE_JSON" | jq -r '.ok // false')" == "true" ]]; then
    APPOINTMENT_ID="$(printf '%s' "$RESERVE_JSON" | jq -r '.data.appointment_id // empty')"
    CANCEL_TOKEN="$(printf '%s' "$RESERVE_JSON" | jq -r '.data.cancel_token // empty')"
    break
  fi
done <<< "$SLOTS"

if [[ -z "$APPOINTMENT_ID" || -z "$CANCEL_TOKEN" ]]; then
  echo "[FAIL] could not reserve any candidate slot"
  exit 1
fi

echo "[OK] reserved appointment_id=$APPOINTMENT_ID slot=$SLOT_START -> $SLOT_END"

EXPIRE_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/maintenance/expire" \
  -H 'Content-Type: application/json' \
  -H 'X-QA-Mode: 1' \
  -d "{\"limit\":50,\"force\":true,\"appointment_id\":\"$APPOINTMENT_ID\"}")"

echo "$EXPIRE_JSON" | jq .

EXPIRE_OK="$(printf '%s' "$EXPIRE_JSON" | jq -r '.ok // false')"
FLOWS_EXPIRED="$(printf '%s' "$EXPIRE_JSON" | jq -r '.data.flows_expired // 0')"
APPTS_CANCELED="$(printf '%s' "$EXPIRE_JSON" | jq -r '.data.appointments_canceled // 0')"
if [[ "$EXPIRE_OK" != "true" || "$FLOWS_EXPIRED" -lt 1 || "$APPTS_CANCELED" -lt 1 ]]; then
  echo "[FAIL] expire did not process expected counts"
  exit 1
fi

echo "[OK] expire processed flows_expired=$FLOWS_EXPIRED appointments_canceled=$APPTS_CANCELED"

RESERVE2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/reserve" \
  -H 'Content-Type: application/json' \
  -d "$(build_reserve_payload "$SLOT_START" "$SLOT_END")")"

echo "$RESERVE2_JSON" | jq .
RESERVE2_OK="$(printf '%s' "$RESERVE2_JSON" | jq -r '.ok // false')"
RESERVE2_CANCEL_TOKEN="$(printf '%s' "$RESERVE2_JSON" | jq -r '.data.cancel_token // empty')"
if [[ "$RESERVE2_OK" != "true" ]]; then
  echo "[FAIL] slot should be reusable after expire"
  exit 1
fi

echo "[OK] slot reused after expire"

EXPIRE2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/maintenance/expire" \
  -H 'Content-Type: application/json' \
  -d '{"limit":50}')"

echo "$EXPIRE2_JSON" | jq .
EXPIRE2_OK="$(printf '%s' "$EXPIRE2_JSON" | jq -r '.ok // false')"
FLOWS2="$(printf '%s' "$EXPIRE2_JSON" | jq -r '.data.flows_expired // 0')"
APPTS2="$(printf '%s' "$EXPIRE2_JSON" | jq -r '.data.appointments_canceled // 0')"
if [[ "$EXPIRE2_OK" != "true" || "$FLOWS2" -ne 0 || "$APPTS2" -ne 0 ]]; then
  echo "[FAIL] idempotent expire should return zero changes"
  exit 1
fi

echo "[OK] idempotent second expire"

if [[ -n "$RESERVE2_CANCEL_TOKEN" ]]; then
  CLEAN_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/appointments/cancel" \
    -H 'Content-Type: application/json' \
    -d "{\"cancel_token\":\"$RESERVE2_CANCEL_TOKEN\",\"reason\":\"QA cleanup p5\"}")"
  echo "$CLEAN_JSON" | jq .
fi

echo "[PASS] QA P5 completed"
