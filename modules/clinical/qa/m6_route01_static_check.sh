#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
LIB="$ROOT/api/_lib/clinical_m6_cutover.php"
ROUTER="$ROOT/api/clinical/index.php"
PURE="$ROOT/modules/clinical/qa/m6_route01_selector_pure_test.php"

php -l "$LIB" >/dev/null
php -l "$ROUTER" >/dev/null
php -l "$PURE" >/dev/null
bash -n "$0"

grep -q 'function clinical_m6_v1_route_enabled_for_pair' "$LIB"
grep -q 'if (!\$masterEnabled)' "$LIB"
grep -q "clinical_m6_cohort_mode() === 'off'" "$LIB"
grep -q 'return clinical_m6_cohort_authorized(\$doctorId, \$patientId);' "$LIB"

grep -q 'function clinical_m6_patient_route_uses_v1' "$ROUTER"
grep -q 'function clinical_m6_encounter_route_uses_v1' "$ROUTER"
grep -q 'function clinical_m6_document_route_uses_v1' "$ROUTER"
grep -q 'function clinical_m6_document_patient_id' "$ROUTER"
grep -q 'hash_equals(\$storedDoctorId, \$sessionDoctorId)' "$ROUTER"

# The raw master flag remains only in the route wrapper plus the accepted
# startup legacy bootstrap and debug/M5 seed behavior.
test "$(grep -c 'clinical_encounter_integrity_v1_enabled()' "$ROUTER")" = "3"
grep -q 'if (!\$isTimelineRoute && !clinical_encounter_integrity_v1_enabled()' "$ROUTER"
grep -q '&& !clinical_m6_write_window_blocks_writes())' "$ROUTER"

active_route="$(sed -n "/patients\/{patient_id}\/encounters\/active/,/no active encounter/p" "$ROUTER")"
grep -q 'clinical_m6_patient_route_uses_v1' <<< "$active_route"
grep -q '\$active = \$useV1' <<< "$active_route"

test "$(grep -c 'clinical_m6_patient_route_uses_v1(\$context, \$patientId)' "$ROUTER")" = "2"
test "$(grep -c 'if (\$useV1)' "$ROUTER")" -ge 4

test "$(grep -c 'clinical_m6_encounter_route_uses_v1' "$ROUTER")" -ge 9
grep -q 'if (!\$useV1)' "$ROUTER"

document_amendment="$(sed -n '/CLIN-REFORM-PHASE2-IMPL01B canonical route/,/if ((\$segments\[0\] ?? .*) === .documents./p' "$ROUTER")"
grep -q 'clinical_m6_document_route_uses_v1' <<< "$document_amendment"
grep -q 'clinical_m6_send_v1_route_unavailable' <<< "$document_amendment"

selection_helpers="$(sed -n '/function clinical_m6_encounter_route_uses_v1/,/function clinical_encounters_create/p' "$ROUTER")"
! grep -Eiq '\b(INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b' <<< "$selection_helpers"
! grep -q 'clinical_encounters_ensure_schema' <<< "$selection_helpers"
! grep -q 'clinical_encounter_integrity_assert_schema_ready' <<< "$selection_helpers"

php "$PURE"
echo 'M6_ROUTE01_STATIC_CHECK=PASS'
