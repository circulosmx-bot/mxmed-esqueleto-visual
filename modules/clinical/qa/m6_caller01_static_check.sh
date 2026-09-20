#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
APP="$ROOT/assets/js/app.js"
BRIDGE="$ROOT/modules/agenda/services/ClinicalEncounterBridge.php"
ROUTER="$ROOT/api/clinical/index.php"
KEY_TEST="$ROOT/modules/clinical/qa/m6_caller01_command_key_pure_test.js"
BRIDGE_TEST="$ROOT/modules/clinical/qa/m6_caller01_agenda_bridge_pure_test.php"
M6_PLAN="$ROOT/docs/clinical/EXPEDIENTE_ENCOUNTER_M6_CUTOVER_PLAN.md"
LIVING_PLAN="$ROOT/docs/clinical/PLAN_REFORMA_EXPEDIENTE_CLINICO.md"

node --check "$APP"
node --check "$KEY_TEST"
php -l "$BRIDGE" >/dev/null
php -l "$BRIDGE_TEST" >/dev/null
bash -n "$0"

grep -q 'M6_CALLER01_COMMAND_KEY_HELPER_START' "$APP"
grep -q "'Idempotency-Key': key" "$APP"
grep -q 'encounter-start:' "$APP"
grep -q 'START:' "$APP"
grep -q 'IDEMPOTENCY_KEY_REUSED' "$APP"
grep -q 'INVALID_APPOINTMENT_SCOPE' "$APP"
grep -q 'SCHEMA_NOT_READY' "$APP"
grep -q 'unauthorized' "$APP"
grep -q 'not_found' "$APP"

save_section="$(sed -n '/const saveClinicalDocument = async/,/const getClinicalDocument = async/p' "$APP")"
grep -q '/api/clinical/index.php/encounters/${encodeURIComponent(encounterKey)}/documents' <<<"$save_section"
grep -q 'ACTIVE_ENCOUNTER_REQUIRED' <<<"$save_section"
grep -q "'Idempotency-Key': key" <<<"$save_section"
grep -q 'DOCUMENT_CONTEXT_MISMATCH' <<<"$save_section"
grep -q 'ENCOUNTER_CLOSED' <<<"$save_section"
grep -q 'ENCOUNTER_VOIDED' <<<"$save_section"
grep -q 'V1_MULTIPART_STORAGE_NOT_READY' <<<"$save_section"
! grep -q '/doctors/' <<<"$save_section"
! grep -q 'FormData' <<<"$save_section"

grep -q 'clinical_m6_cutover.php' "$BRIDGE"
grep -q 'M6_CALLER01_C14_COHORT_BRIDGE_BLOCK' "$BRIDGE"
grep -q 'clinical_m6_legacy_write_block_required' "$BRIDGE"
grep -q 'M6_AGENDA_CLINICAL_BRIDGE_BLOCKED' "$BRIDGE"
! grep -Eq 'X-User-Id|X-Doctor-Id|Cookie:|PHPSESSID|Authorization:' "$BRIDGE"

guard_line="$(grep -n -m1 'M6_CALLER01_C14_COHORT_BRIDGE_BLOCK' "$BRIDGE" | cut -d: -f1)"
get_line="$(grep -n -m1 'encounterExistsForAppointment' "$BRIDGE" | cut -d: -f1)"
post_line="$(grep -n -m1 "httpJson('POST'" "$BRIDGE" | cut -d: -f1)"
test "$guard_line" -lt "$get_line"
test "$guard_line" -lt "$post_line"

! grep -q 'clinical_m6_cohort_authorized' "$ROUTER"
git -C "$ROOT" diff --quiet 0baeefb8eff98f9de80429d0ad9d164190508fbc -- assets/js/app.js
git -C "$ROOT" diff --quiet 0baeefb8eff98f9de80429d0ad9d164190508fbc -- modules/agenda/services/ClinicalEncounterBridge.php

for file in "$M6_PLAN" "$LIVING_PLAN"; do
    grep -q 'PHASE_2_M6_GUARD01=ACCEPTED' "$file"
    grep -q 'M6_GUARD01_ACCEPTED_HEAD=bcb2606ba7a95818673b402a9e006ecc0431ff73' "$file"
    grep -q 'PHASE_2_M6_CALLER01=ACCEPTED' "$file"
    grep -q 'M6_CALLER01_ACCEPTED_HEAD=0baeefb8eff98f9de80429d0ad9d164190508fbc' "$file"
    grep -q 'C02_ADAPTED=true' "$file"
    grep -q 'C03_ADAPTED=true' "$file"
    grep -q 'C14_RESOLVED=true' "$file"
    grep -q 'UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0' "$file"
    grep -q 'CANDIDATE_UNCONTROLLED_LEGACY_CLINICAL_WRITER_COUNT=0' "$file"
    grep -q 'MULTIPART_ACTIVE_CALLER_BLOCKER=true' "$file"
    grep -q 'M6_COHORT_RUNTIME_ROUTING_ACTIVE=false' "$file"
    grep -q 'M6_GO_NO_GO=NO_GO_BLOCKED' "$file"
done

echo 'M6_CALLER01_STATIC_CHECK=PASS'
