#!/usr/bin/env bash
set -euo pipefail

# QA OTP Publico P2
# Requiere endpoint:
#  - POST /api/agenda/index.php/public/otp/request
#  - POST /api/agenda/index.php/public/otp/verify
#
# Uso:
#   BASE_URL=http://127.0.0.1:8090 bash modules/agenda/qa/public_otp_p2.sh
#
# Para debug_code reproducible:
#   enviar header X-MXMed-QA-Mode: 1
#   o levantar servidor con MXMED_QA_MODE=1

BASE_URL="${BASE_URL:-http://127.0.0.1:8090}"
DOCTOR_ID="${DOCTOR_ID:-1}"
CONTACT_TYPE="${CONTACT_TYPE:-sms}"
CONTACT_VALUE="${CONTACT_VALUE:-+52 55 1234-5678}"
QA_HEADER="${QA_HEADER:-1}"

has_jq=0
if command -v jq >/dev/null 2>&1; then
  has_jq=1
fi

echo "============================================================"
echo "QA Public OTP P2"
echo "BASE_URL=$BASE_URL"
echo "DOCTOR_ID=$DOCTOR_ID"
echo "CONTACT_TYPE=$CONTACT_TYPE"
echo "============================================================"

echo
echo "[1] Request OTP"
REQ_HEADERS=(-H 'Content-Type: application/json')
if [[ "$QA_HEADER" == "1" ]]; then
  REQ_HEADERS+=(-H 'X-MXMed-QA-Mode: 1')
fi

REQ_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/otp/request" "${REQ_HEADERS[@]}" -d "{\"doctor_id\":\"$DOCTOR_ID\",\"contact_type\":\"$CONTACT_TYPE\",\"contact_value\":\"$CONTACT_VALUE\"}")"
echo "$REQ_JSON" | head -c 1200; echo

if [[ $has_jq -eq 1 ]]; then
  REQ_OK="$(printf '%s' "$REQ_JSON" | jq -r '.ok // false')"
  OTP_ID="$(printf '%s' "$REQ_JSON" | jq -r '.data.otp_id // empty')"
  DEBUG_CODE="$(printf '%s' "$REQ_JSON" | jq -r '.meta.debug_code // empty')"
else
  REQ_OK="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"ok":\(true\|false\).*/\1/p' | head -n1)"
  OTP_ID="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"otp_id":"\{0,1\}\([^",}]*\)"\{0,1\}.*/\1/p' | head -n1)"
  DEBUG_CODE="$(printf '%s' "$REQ_JSON" | sed -n 's/.*"debug_code":"\([0-9]\{6\}\)".*/\1/p' | head -n1)"
fi

if [[ "$REQ_OK" != "true" || -z "$OTP_ID" ]]; then
  echo "[FAIL] OTP request failed"
  exit 1
fi

echo "[OK] otp_id=$OTP_ID"

if [[ -z "$DEBUG_CODE" ]]; then
  echo "[FAIL] debug_code missing. Enable MXMED_QA_MODE=1 in server env or send X-MXMed-QA-Mode: 1."
  exit 1
fi

echo "[OK] debug_code=$DEBUG_CODE"

echo
echo "[2] Verify OTP"
VERIFY_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/otp/verify" -H 'Content-Type: application/json' -d "{\"otp_id\":\"$OTP_ID\",\"code\":\"$DEBUG_CODE\"}")"
echo "$VERIFY_JSON" | head -c 1200; echo

if [[ $has_jq -eq 1 ]]; then
  VER_OK="$(printf '%s' "$VERIFY_JSON" | jq -r '.ok // false')"
  VERIFIED="$(printf '%s' "$VERIFY_JSON" | jq -r '.data.verified // false')"
else
  VER_OK="$(printf '%s' "$VERIFY_JSON" | sed -n 's/.*"ok":\(true\|false\).*/\1/p' | head -n1)"
  VERIFIED="$(printf '%s' "$VERIFY_JSON" | sed -n 's/.*"verified":\(true\|false\).*/\1/p' | head -n1)"
fi

if [[ "$VER_OK" != "true" || "$VERIFIED" != "true" ]]; then
  echo "[FAIL] OTP verify failed"
  exit 1
fi

echo "[OK] OTP verified"

# Optional idempotency check

echo
echo "[3] Verify again (idempotent)"
VERIFY2_JSON="$(curl -sS -X POST "$BASE_URL/api/agenda/index.php/public/otp/verify" -H 'Content-Type: application/json' -d "{\"otp_id\":\"$OTP_ID\",\"code\":\"$DEBUG_CODE\"}")"
echo "$VERIFY2_JSON" | head -c 1200; echo

echo "[OK] QA completed"
exit 0
