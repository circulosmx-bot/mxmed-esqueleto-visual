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

json_timeline_appt_encounters_have_links_appointment_id() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
for it in items:
    if not isinstance(it,dict):
        continue
    if it.get('item_type')!='encounter':
        continue
    key=(it.get('encounter_key') or '').strip()
    if not key.startswith('appt:') or '#enc:' not in key:
        continue
    links=it.get('links') or {}
    appt=(links.get('appointment_id') or '').strip() if isinstance(links,dict) else ''
    if not appt:
        sys.exit(1)
sys.exit(0)
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

json_extract_active_case_id() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
d=obj.get('data') or {}
try:
    cid=int(d.get('case_id') or 0)
except Exception:
    cid=0
if cid<=0:
    sys.exit(1)
print(cid)
"
}

json_extract_case_appt_refs_csv() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
items=obj.get('data') or []
refs=[]
if isinstance(items,list):
    for it in items:
        if not isinstance(it,dict):
            continue
        if (it.get('item_type') or '').strip()!='appointment':
            continue
        ref=(it.get('item_ref') or '').strip()
        if ref.startswith('appt:'):
            refs.append(ref)
seen=[]
for r in refs:
    if r not in seen:
        seen.append(r)
print(','.join(seen))
"
}

json_timeline_validate_refs_in_case() {
  local refs_csv="$1"
  python3 -c "
import json,sys
refs=set([r for r in sys.argv[1].split(',') if r])
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
matched=0
for it in items:
    if not isinstance(it,dict) or it.get('item_type')!='encounter':
        continue
    links=it.get('links') if isinstance(it.get('links'),dict) else {}
    appt=(links.get('appointment_id') or '').strip()
    if not appt:
        continue
    ref='appt:'+appt
    if ref not in refs:
        continue
    matched += 1
    if it.get('is_in_active_case') is not True:
        sys.exit(1)
sys.exit(0 if matched>0 else 2)
" "$refs_csv"
}

json_timeline_pick_out_of_case_encounter() {
  python3 -c "
import json,sys
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
for it in items:
    if not isinstance(it,dict) or it.get('item_type')!='encounter':
        continue
    links=it.get('links') if isinstance(it.get('links'),dict) else {}
    appt=(links.get('appointment_id') or '').strip()
    key=(it.get('encounter_key') or '').strip()
    if not key or not appt:
        continue
    if it.get('is_in_active_case') is True:
        continue
    print(key + '|' + appt)
    sys.exit(0)
sys.exit(1)
"
}

json_timeline_encounter_is_in_case() {
  local encounter_key="$1"
  python3 -c "
import json,sys
target=sys.argv[1]
obj=json.load(sys.stdin)
items=((obj.get('data') or {}).get('items') or [])
for it in items:
    if not isinstance(it,dict) or it.get('item_type')!='encounter':
        continue
    if (it.get('encounter_key') or '').strip()!=target:
        continue
    sys.exit(0 if it.get('is_in_active_case') is True else 1)
sys.exit(2)
" "$encounter_key"
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
  if printf '%s' "$timeline_resp" | json_timeline_appt_encounters_have_links_appointment_id; then
    pass "timeline encounter appt:*#enc:* items include links.appointment_id"
  else
    fail "timeline encounter appt:*#enc:* missing links.appointment_id"
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

log "Step 7: active case items consistency against timeline + add-to-case reflect"
ACTIVE_CASE_ID=""
APPT_REFS_CSV=""
if active_case_resp=$(http_get "$API_ROOT/patients/$PATIENT_ID/cases/active"); then
  if printf '%s' "$active_case_resp" | json_assert_ok_true; then
    if ACTIVE_CASE_ID=$(printf '%s' "$active_case_resp" | json_extract_active_case_id); then
      pass "active case found: case_id=$ACTIVE_CASE_ID"
    else
      fail "active case is null/missing case_id"
    fi
  else
    fail "GET active case returned ok!=true"
  fi
else
  fail "GET active case request failed"
fi

if [[ -n "$ACTIVE_CASE_ID" ]]; then
  if case_items_resp=$(http_get "$API_ROOT/cases/$ACTIVE_CASE_ID/items?limit=200"); then
    if printf '%s' "$case_items_resp" | json_assert_ok_true; then
      APPT_REFS_CSV=$(printf '%s' "$case_items_resp" | json_extract_case_appt_refs_csv || true)
      pass "case items loaded (appointment refs: ${APPT_REFS_CSV:-<none>})"
    else
      fail "GET case items returned ok!=true"
    fi
  else
    fail "GET case items request failed"
  fi
fi

if [[ -n "$ACTIVE_CASE_ID" && -n "$APPT_REFS_CSV" ]]; then
  if timeline_case_resp=$(http_get "$API_ROOT/patients/$PATIENT_ID/timeline?include=agenda,clinical&limit=20"); then
    if printf '%s' "$timeline_case_resp" | json_timeline_validate_refs_in_case "$APPT_REFS_CSV"; then
      pass "timeline encounter items in case refs are marked is_in_active_case=true"
    else
      rc=$?
      if [[ "$rc" -eq 2 ]]; then
        fail "timeline has no encounter items matching active case appointment refs"
      else
        fail "timeline membership mismatch for active case appointment refs"
      fi
    fi
    if pick=$(printf '%s' "$timeline_case_resp" | json_timeline_pick_out_of_case_encounter); then
      PICK_ENC_KEY="${pick%%|*}"
      PICK_APPT_ID="${pick#*|}"
      add_payload="{\"item_type\":\"appointment\",\"item_ref\":\"appt:$PICK_APPT_ID\"}"
      if add_resp=$(http_post_json "$API_ROOT/cases/$ACTIVE_CASE_ID/items" "$add_payload"); then
        if printf '%s' "$add_resp" | json_assert_ok_true; then
          if timeline_after_add=$(http_get "$API_ROOT/patients/$PATIENT_ID/timeline?include=agenda,clinical&limit=20"); then
            if printf '%s' "$timeline_after_add" | json_timeline_encounter_is_in_case "$PICK_ENC_KEY"; then
              pass "add-to-case reflected in timeline for encounter $PICK_ENC_KEY"
            else
              fail "add-to-case not reflected in timeline for encounter $PICK_ENC_KEY"
            fi
          else
            fail "timeline fetch after add-to-case failed"
          fi
        else
          fail "POST case item returned ok!=true"
        fi
      else
        fail "POST case item request failed"
      fi
    else
      fail "no out-of-case encounter candidate found for add-to-case check"
    fi
  else
    fail "timeline request for case consistency failed"
  fi
elif [[ -n "$ACTIVE_CASE_ID" ]]; then
  fail "active case has no appointment refs; cannot validate encounter membership"
fi

echo
if [[ "$issues" -eq 0 ]]; then
  echo "RESULT: PASS"
  exit 0
fi

echo "RESULT: FAIL ($issues issues)"
exit 1
