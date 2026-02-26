#!/usr/bin/env bash
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "$0")" && pwd)"
ROOT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"

BASE_API="${BASE_API:-http://127.0.0.1:8091}"
BASE_API="${BASE_API%/}"
API_ROOT="$BASE_API/api/clinical/index.php"
APPT_ID="${1:-${APPT_ID:-fe61cdd67e97dcfde3a70c02}}"

issues=0

pass() { echo "[PASS] $*"; }
fail() { echo "[FAIL] $*"; issues=$((issues+1)); }
info() { echo "[INFO] $*"; }

urlencode() {
  local raw="$1"
  python3 - <<'PY' "$raw"
import sys,urllib.parse
print(urllib.parse.quote(sys.argv[1], safe=''))
PY
}

extract_resolver_encounter_id() {
  python3 -c '
import json,sys
import re
obj=json.load(sys.stdin)
if obj.get("ok") is not True:
    sys.exit(2)
data=obj.get("data") or {}
try:
    eid=int(data.get("encounter_id") or 0)
except Exception:
    eid=0
if eid<=0:
    key=(data.get("encounter_key") or "").strip()
    m=re.search(r"#enc:(\d+)$", key)
    if m:
        try:
            eid=int(m.group(1))
        except Exception:
            eid=0
if eid<=0:
    sys.exit(1)
print(eid)
'
}

fetch_db_latest_encounter_id() {
  local appt_id="$1"
  php -r '
$root = $argv[1];
$appt = $argv[2];
require_once $root . "/api/_lib/db.php";
$pdo = mxmed_pdo();
$stmt = $pdo->prepare("SELECT encounter_id FROM clinical_encounters WHERE appointment_id = :appointment_id ORDER BY encounter_dt DESC, encounter_id DESC LIMIT 1");
$stmt->execute([":appointment_id" => $appt]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if (!is_array($row) || !isset($row["encounter_id"])) {
    exit(2);
}
$eid = (int)$row["encounter_id"];
if ($eid <= 0) {
    exit(3);
}
echo (string)$eid;
' "$ROOT_DIR" "$appt_id"
}

info "BASE_API=$BASE_API"
info "APPT_ID=$APPT_ID"

ENC_KEY="appt:$APPT_ID"
ENC_KEY_ENC="$(urlencode "$ENC_KEY")"
RESOLVER_URL="$API_ROOT/encounters/$ENC_KEY_ENC"

info "Resolver URL: $RESOLVER_URL"
if resolver_body="$(curl -sS "$RESOLVER_URL")"; then
  if resolver_id="$(printf '%s' "$resolver_body" | extract_resolver_encounter_id)"; then
    pass "Resolver returned encounter_id=$resolver_id"
  else
    rc=$?
    if [[ "$rc" -eq 2 ]]; then
      fail "Resolver returned ok!=true"
    else
      fail "Resolver response missing valid encounter_id"
    fi
    resolver_id=""
  fi
else
  fail "Resolver request failed"
  resolver_id=""
fi

if db_id="$(fetch_db_latest_encounter_id "$APPT_ID")"; then
  pass "DB latest encounter_id=$db_id"
else
  rc=$?
  if [[ "$rc" -eq 2 ]]; then
    fail "DB has no encounter rows for appointment_id=$APPT_ID"
  else
    fail "DB query failed for latest encounter"
  fi
  db_id=""
fi

if [[ -n "$resolver_id" && -n "$db_id" ]]; then
  if [[ "$resolver_id" == "$db_id" ]]; then
    pass "Determinism: resolver matches DB latest encounter"
  else
    fail "Determinism mismatch: resolver=$resolver_id db_latest=$db_id"
  fi
fi

echo
if [[ "$issues" -eq 0 ]]; then
  echo "RESULT: PASS"
  exit 0
fi

echo "RESULT: FAIL ($issues issues)"
exit 1
