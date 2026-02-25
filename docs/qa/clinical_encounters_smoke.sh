#!/usr/bin/env bash
set -euo pipefail

BASE_API="${BASE_API:-http://127.0.0.1:8091}"
PATIENT_ID="${PATIENT_ID:-p_0c874aa9cbad}"
APPT_ID="${APPT_ID:-fe61cdd67e97dcfde3a70c02}"
BASE_API="${BASE_API%/}"
API_ROOT="$BASE_API/api/clinical/index.php"

issues=0
NEW_DOC_UUID=""
ENCOUNTER_KEY=""
ENCOUNTER_KEY_ENC=""

log() { echo "[INFO] $*"; }
pass() { echo "[PASS] $*"; }
fail() { echo "[FAIL] $*"; issues=$((issues+1)); }

http_get() {
  local url="$1"
  curl -sS "$url"
}

http_post_json() {
  local url="$1"
  local body="$2"
  curl -sS -X POST "$url" -H 'Content-Type: application/json' -H 'Accept: application/json' -d "$body"
}

json_has_contract() {
  python3 -c '
import json,sys
try:
    obj=json.load(sys.stdin)
except Exception:
    sys.exit(1)
for k in ("ok","error","message","data","meta"):
    if k not in obj:
        sys.exit(2)
sys.exit(0)
'
}

json_timeline_has_encounter() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
ok=any(isinstance(it,dict) and it.get('item_type')=='encounter' for it in items)
sys.exit(0 if ok else 1)
"
}

json_extract_encounter_key() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
k=((obj.get('data') or {}).get('encounter_key') or '').strip()
if not k:
    sys.exit(1)
print(k)
"
}

urlencode() {
  local raw="$1"
  python3 - << 'PY' "$raw"
import sys,urllib.parse
print(urllib.parse.quote(sys.argv[1], safe=''))
PY
}

json_assert_ok_true() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
sys.exit(0 if obj.get('ok') is True else 1)
"
}

json_extract_document_uuid() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
u=((obj.get('data') or {}).get('document_uuid') or '').strip()
if not u:
    sys.exit(1)
print(u)
"
}

json_encounter_contains_uuid() {
  local uuid="$1"
  python3 -c "
import json,sys
uuid=sys.argv[1]
obj=json.load(sys.stdin)
docs=((obj.get('data') or {}).get('documents') or [])
ok=any(isinstance(d,dict) and (d.get('document_uuid') or '').strip()==uuid for d in docs)
sys.exit(0 if ok else 1)
" "$uuid"
}

json_timeline_encounter_contains_uuid() {
  local encounter_key="$1"
  local uuid="$2"
  python3 -c "
import json,sys
enc_key=sys.argv[1]
uuid=sys.argv[2]
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
ok=False
for it in items:
    if not isinstance(it,dict):
        continue
    if it.get('item_type')!='encounter':
        continue
    if (it.get('encounter_key') or '').strip()!=enc_key:
        continue
    docs=(((it.get('clinical') or {}).get('documents')) or [])
    ok=any(isinstance(d,dict) and (d.get('document_uuid') or '').strip()==uuid for d in docs)
    break
sys.exit(0 if ok else 1)
" "$encounter_key" "$uuid"
}

log "BASE_API=$BASE_API"
log "PATIENT_ID=$PATIENT_ID"
log "APPT_ID=$APPT_ID"

log "Step 1: timeline contract + encounter existence"
if timeline_resp=$(http_get "$API_ROOT/patients/$PATIENT_ID/timeline?include=agenda,clinical&limit=10"); then
  if printf '%s' "$timeline_resp" | json_has_contract; then
    pass "timeline contract keys ok/error/message/data/meta"
  else
    fail "timeline contract keys missing or invalid JSON"
  fi
  if printf '%s' "$timeline_resp" | json_timeline_has_encounter; then
    pass "timeline includes at least one encounter item"
  else
    fail "timeline has no encounter items"
  fi
else
  fail "timeline request failed"
  timeline_resp='{}'
fi

log "Step 2: resolve encounter_key from legacy appt key"
LEGACY_APPT_KEY="appt:$APPT_ID"
LEGACY_APPT_KEY_ENC=$(urlencode "$LEGACY_APPT_KEY")
if encounter_resp=$(http_get "$API_ROOT/encounters/$LEGACY_APPT_KEY_ENC"); then
  if printf '%s' "$encounter_resp" | json_assert_ok_true; then
    if ENCOUNTER_KEY=$(printf '%s' "$encounter_resp" | json_extract_encounter_key); then
      ENCOUNTER_KEY_ENC=$(urlencode "$ENCOUNTER_KEY")
      pass "encounter_key resolved: $ENCOUNTER_KEY"
    else
      fail "could not extract encounter_key"
    fi
  else
    fail "GET /encounters/{encoded legacy appt key} returned ok!=true"
  fi
else
  fail "GET /encounters/{encoded legacy appt key} failed"
fi

log "Step 3: GET /encounters/{encounter_key_encoded}"
if [[ -n "$ENCOUNTER_KEY_ENC" ]]; then
  if encounter_key_resp=$(http_get "$API_ROOT/encounters/$ENCOUNTER_KEY_ENC"); then
    if printf '%s' "$encounter_key_resp" | json_assert_ok_true; then
      pass "GET encoded encounter_key ok:true"
    else
      fail "GET encoded encounter_key ok!=true"
    fi
  else
    fail "GET encoded encounter_key request failed"
  fi
else
  fail "skipping step 3 (missing encoded encounter key)"
fi

log "Step 4: POST /encounters/{encounter_key_encoded}/documents"
if [[ -n "$ENCOUNTER_KEY_ENC" ]]; then
  seed_summary="Seed smoke $(date +%s)"
  post_body=$(cat <<JSON
{"document_type":"note","title":"Nota clínica (seed)","summary":"$seed_summary","payload":{"text":"seed qa"}}
JSON
)
  if post_resp=$(http_post_json "$API_ROOT/encounters/$ENCOUNTER_KEY_ENC/documents" "$post_body"); then
    if printf '%s' "$post_resp" | json_assert_ok_true; then
      if NEW_DOC_UUID=$(printf '%s' "$post_resp" | json_extract_document_uuid); then
        pass "document created uuid=$NEW_DOC_UUID"
      else
        fail "post ok:true but missing data.document_uuid"
      fi
    else
      fail "POST encounter document ok!=true"
    fi
  else
    fail "POST encounter document failed"
  fi
else
  fail "skipping step 4 (missing encoded encounter key)"
fi

log "Step 5: GET /encounters/{encounter_key_encoded} contains new document"
if [[ -n "$ENCOUNTER_KEY_ENC" && -n "$NEW_DOC_UUID" ]]; then
  if after_post_enc=$(http_get "$API_ROOT/encounters/$ENCOUNTER_KEY_ENC"); then
    if printf '%s' "$after_post_enc" | json_encounter_contains_uuid "$NEW_DOC_UUID"; then
      pass "encounter documents[] contains new document_uuid"
    else
      fail "encounter documents[] missing new document_uuid"
    fi
  else
    fail "GET encounter after POST failed"
  fi
else
  fail "skipping step 5 (missing encounter key or new uuid)"
fi

log "Step 6: timeline reflects new document in encounter clinical.documents[]"
if [[ -n "$NEW_DOC_UUID" && -n "$ENCOUNTER_KEY" ]]; then
  if timeline_after=$(http_get "$API_ROOT/patients/$PATIENT_ID/timeline?include=agenda,clinical&limit=10"); then
    if printf '%s' "$timeline_after" | json_timeline_encounter_contains_uuid "$ENCOUNTER_KEY" "$NEW_DOC_UUID"; then
      pass "timeline encounter clinical.documents[] contains new document_uuid"
    else
      fail "timeline encounter clinical.documents[] missing new document_uuid"
    fi
  else
    fail "timeline after POST failed"
  fi
else
  fail "skipping step 6 (missing encounter key or new uuid)"
fi

echo
if [[ "$issues" -eq 0 ]]; then
  echo "RESULT: PASS"
  exit 0
fi

echo "RESULT: FAIL ($issues issues)"
exit 1
