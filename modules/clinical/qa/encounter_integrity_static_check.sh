#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
MIGRATIONS="$ROOT/modules/clinical/db/migrations"

php_files=(
  "$ROOT/api/_lib/clinical_idempotency.php"
  "$ROOT/api/_lib/clinical_encounter_sections.php"
  "$ROOT/api/_lib/clinical_observations.php"
  "$ROOT/api/_lib/clinical_encounter_integrity.php"
  "$ROOT/api/_lib/clinical_documents.php"
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
grep -Eq 'open_guard TINYINT.*GENERATED ALWAYS|ADD COLUMN open_guard TINYINT GENERATED ALWAYS' "$MIGRATIONS/2026_09_18_01_encounter_lifecycle_integrity.sql"
grep -q 'UNIQUE KEY uq_clinical_encounter_one_open (doctor_id,patient_id,open_guard)' "$MIGRATIONS/2026_09_18_01_encounter_lifecycle_integrity.sql"
! grep -Eiq 'open_guard[^;]*(concat|doctor_id.*:.*patient_id)' "$MIGRATIONS/2026_09_18_01_encounter_lifecycle_integrity.sql"
! grep -Eiq 'UPDATE[[:space:]]+clinical_encounters[[:space:]]+SET[[:space:]]+doctor_id' "$MIGRATIONS/2026_09_18_0"*_encounter_*.sql
! grep -Eiq "completed[^;]*(open)|status[^;]*=[^;]*'open'[^;]*completed" "$MIGRATIONS/2026_09_18_0"*_encounter_*.sql
grep -q 'clinical_encounter_sections' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_observations' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_encounter_amendments' "$MIGRATIONS/2026_09_18_02_encounter_structured_content.sql"
grep -q 'clinical_encounter_start_requests' "$MIGRATIONS/2026_09_18_03_encounter_command_idempotency.sql"
grep -q 'clinical_idempotency_requests' "$MIGRATIONS/2026_09_18_03_encounter_command_idempotency.sql"
grep -q 'clinical_encounter_final_notes' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
grep -q 'clinical_document_revisions' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
grep -q 'encounter_ref_id' "$MIGRATIONS/2026_09_18_04_encounter_document_integrity.sql"
test "$(grep -h -c 'ON DELETE RESTRICT' "$MIGRATIONS"/2026_09_18_0*_encounter_*.sql | awk '{s+=$1} END {print s+0}')" -ge 10

# Every migration is a guarded procedure and explicitly fails closed on drift.
test "$(grep -l 'information_schema' "$MIGRATIONS"/2026_09_18_0*_encounter_*.sql | wc -l | tr -d ' ')" = "4"
test "$(grep -l 'MIGRATION_DRIFT' "$MIGRATIONS"/2026_09_18_0*_encounter_*.sql | wc -l | tr -d ' ')" = "4"

ROUTER="$ROOT/api/clinical/index.php"
grep -q "encounters/{encounter_key}/finalize" "$ROUTER"
grep -q "encounters/{encounter_key}/void" "$ROUTER"
grep -q "encounters/{encounter_key}/sections/{type}" "$ROUTER"
grep -q "encounters/{encounter_key}/observations" "$ROUTER"
grep -q "encounters/{encounter_key}/amendments" "$ROUTER"
grep -q 'clinical_document_operation_policy' "$ROUTER"
grep -q 'CREATE_OBSERVATION' "$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q 'CREATE_ENCOUNTER_AMENDMENT' "$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q "CREATE_POST_ENCOUNTER_RESULT" "$ROUTER"
grep -q 'idempotentCreate' "$ROUTER"
grep -q 'auto_note_uuid_final=:document_uuid' "$ROOT/api/_lib/clinical_encounter_integrity.php"

# IMPL01B: one gated, append-only document amendment command owns document,
# lineage and durable idempotency completion in the existing transaction.
grep -q "documents/{document_id_or_uuid}/amendments" "$ROUTER"
grep -q "CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT" "$ROUTER"
grep -q "'document_revision_id'" "$ROUTER"
grep -q 'clinical_v1_document_amendment_insert' "$ROUTER"
grep -q 'clinical_document_revisions' "$ROUTER"
grep -q 'mxmed_build_clinical_document' "$ROUTER"
grep -q 'mxmed_persist_clinical_document_in_transaction' "$ROUTER"
grep -q 'clinical_v1_multipart_document_write_allowed' "$ROUTER"
amendment_impl="$(sed -n '/function clinical_v1_document_amendment_insert/,/function clinical_case_items_list_fetch/p' "$ROUTER")"
grep -q 'FOR UPDATE' <<< "$amendment_impl"
grep -q 'createDocumentRevision' <<< "$amendment_impl"
grep -q 'mxmed_build_clinical_document' <<< "$amendment_impl"
grep -q 'mxmed_persist_clinical_document_in_transaction' <<< "$amendment_impl"
! grep -Eiq 'UPDATE[[:space:]]+clinical_documents' <<< "$amendment_impl"
amendment_route="$(sed -n '/CLIN-REFORM-PHASE2-IMPL01B canonical route/,/^[[:space:]]*if ((\$segments\[0\]/p' "$ROUTER")"
grep -q 'clinical_encounter_integrity_v1_enabled' <<< "$amendment_route"
grep -q 'clinical_v1_document_amendment_insert' <<< "$amendment_route"
grep -q 'idempotentCreate' <<< "$amendment_route"
grep -q 'DOCUMENT_REVISION_TRANSACTION_REQUIRED' "$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q 'DOCUMENT_ALREADY_SUPERSEDED' <<< "$amendment_impl"
grep -q 'IDEMPOTENCY_KEY_REUSED' "$ROOT/api/_lib/clinical_idempotency.php"
grep -q "\['_idempotency_replay' => true\]" "$ROOT/api/_lib/clinical_idempotency.php"
grep -q 'beginTransaction' "$ROOT/api/_lib/clinical_idempotency.php"
grep -q 'requests->complete' "$ROOT/api/_lib/clinical_idempotency.php"

LIFECYCLE="$MIGRATIONS/2026_09_18_01_encounter_lifecycle_integrity.sql"
INTEGRITY="$ROOT/api/_lib/clinical_encounter_integrity.php"
grep -q 'FIRST_CLOSE_IMMUTABLE' "$LIFECYCLE"
grep -q 'FIRST_VOID_IMMUTABLE' "$LIFECYCLE"
grep -q 'first_close_immutable' "$INTEGRITY"
grep -q 'first_void_immutable' "$INTEGRITY"
normalized_lifecycle="$(tr '\n\r\t' '   ' < "$LIFECYCLE")"
grep -Eq 'CREATE[[:space:]]+TRIGGER[[:space:]]+IF[[:space:]]+NOT[[:space:]]+EXISTS[[:space:]]+trg_clinical_encounters_v1_before_insert' <<< "$normalized_lifecycle"
grep -Eq 'CREATE[[:space:]]+TRIGGER[[:space:]]+IF[[:space:]]+NOT[[:space:]]+EXISTS[[:space:]]+trg_clinical_encounters_v1_before_update' <<< "$normalized_lifecycle"
! grep -Eiq "SET[[:space:]]+@[[:alnum:]_]+[[:space:]]*=.*CREATE[[:space:]]+TRIGGER" <<< "$normalized_lifecycle"

critical_checks=(
  chk_clinical_encounter_lifecycle_v1
  chk_encounter_section_type_v1 chk_encounter_section_version_v1
  chk_observation_version_v1 chk_observation_source_v1 chk_observation_bp_v1
  chk_encounter_amendment_reason_v1 chk_encounter_start_commit_v1
  chk_idempotency_context_v1 chk_idempotency_operation_v1 chk_idempotency_committed_result_v1
  chk_document_revision_reason_v1
)
for check_name in "${critical_checks[@]}"; do
  grep -q "$check_name" "$INTEGRITY"
  grep -q "$check_name" "$MIGRATIONS"/2026_09_18_0*_encounter_*.sql
done

grep -q 'clinical_canonical_document_class' "$INTEGRITY"
grep -q 'DOCUMENT_CLASS_MISMATCH' "$INTEGRITY"
grep -q "'order', 'orders', 'lab_order', 'imaging_order', 'orden_estudio'" "$INTEGRITY"
grep -q "'lab_result', 'lab_pdf'" "$INTEGRITY"
grep -q "default => 'ENCOUNTER_DOCUMENT'" "$INTEGRITY"
grep -q 'mxmed_build_clinical_document' "$ROUTER"
grep -q 'mxmed_persist_clinical_document_in_transaction' "$ROUTER"
grep -q 'V1_MULTIPART_STORAGE_NOT_READY' "$ROUTER"
! sed -n '/function clinical_v1_document_insert/,/function clinical_v1_document_fetch/p' "$ROUTER" | grep -q "status='signed'"
! sed -n '/function clinical_v1_document_insert/,/function clinical_v1_document_fetch/p' "$ROUTER" | grep -q 'clinical_store_uploaded_file'
! sed -n '/function clinical_v1_document_insert/,/function clinical_v1_document_fetch/p' "$ROUTER" | grep -q 'mxmed_ensure_clinical_docs_schema'
grep -q "getenv('MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1') ?: ''" "$INTEGRITY"

# The three encounter GET branches use readiness inspection when V1 is enabled.
test "$(grep -c 'clinical_encounter_integrity_assert_schema_ready' "$ROUTER")" -ge 4
grep -q "doctor_id,patient_id,open_guard" "$ROOT/api/_lib/clinical_encounter_integrity.php"

php "$ROOT/modules/clinical/qa/encounter_integrity_pure_test.php"
echo 'STATIC_QA_RESULT=PASS'
