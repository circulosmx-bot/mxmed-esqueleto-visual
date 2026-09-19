#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
MIGRATIONS="$ROOT/modules/clinical/db/migrations"

php_files=(
  "$ROOT/api/_lib/clinical_idempotency.php"
  "$ROOT/api/_lib/clinical_encounter_sections.php"
  "$ROOT/api/_lib/clinical_observations.php"
  "$ROOT/api/_lib/clinical_encounter_integrity.php"
  "$ROOT/api/clinical/index.php"
  "$ROOT/modules/clinical/qa/encounter_integrity_pure_test.php"
)

for file in "${php_files[@]}"; do
  php -l "$file" >/dev/null
done

bash -n "$ROOT/modules/clinical/qa/encounter_integrity_static_check.sh"

test "$(find "$MIGRATIONS" -maxdepth 1 -name '2026_09_18_0*_encounter_*.sql' | wc -l | tr -d ' ')" = "4"
grep -q 'MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1' "$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q 'SCHEMA_NOT_READY' "$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q 'open_guard' "$MIGRATIONS/2026_09_18_01_encounter_lifecycle_integrity.sql"
grep -q 'clinical_encounter_sections' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_observations' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_encounter_amendments' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_encounter_start_requests' "$MIGRATIONS/2026_09_18_03_encounter_command_idempotency.sql"
grep -q 'clinical_idempotency_requests' "$MIGRATIONS/2026_09_18_03_encounter_command_idempotency.sql"
grep -q 'clinical_encounter_final_notes' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
grep -q 'clinical_document_revisions' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
grep -q 'encounter_ref_id' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
test "$(grep -h -c 'ON DELETE RESTRICT' "$MIGRATIONS"/2026_09_18_0*_encounter_*.sql | awk '{s+=$1} END {print s+0}')" -ge 10

php "$ROOT/modules/clinical/qa/encounter_integrity_pure_test.php"
echo 'STATIC_QA_RESULT=PASS'
