#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
MIGRATION="$ROOT/modules/clinical/db/migrations/2026_09_19_05_clinical_binary_storage.sql"
READINESS="$ROOT/api/_lib/clinical_multipart_storage_schema.php"
ROUTER="$ROOT/api/clinical/index.php"
INTEGRITY="$ROOT/api/_lib/clinical_encounter_integrity.php"
PLAN="$ROOT/docs/clinical/PLAN_REFORMA_EXPEDIENTE_CLINICO.md"

test -f "$MIGRATION"
test -f "$READINESS"
grep -Fq 'CLIN-REFORM-PHASE2-M6-MULTI02A' "$MIGRATION"
grep -Fq 'REPOSITORY ARTIFACT ONLY' "$MIGRATION"
grep -Fq 'DO NOT EXECUTE ON WORKING DB IN THIS CHAPTER' "$MIGRATION"
grep -Fq 'CREATE TABLE clinical_binary_uploads' "$MIGRATION"
grep -Fq 'CREATE TABLE clinical_document_binaries' "$MIGRATION"
grep -Fq 'uq_binary_upload_staging_key' "$MIGRATION"
grep -Fq 'uq_document_binary_uuid' "$MIGRATION"
grep -Fq 'uq_document_binary_storage_key' "$MIGRATION"
grep -Fq 'uq_document_binary_variant' "$MIGRATION"
grep -Fq 'chk_binary_upload_state_v1' "$MIGRATION"
grep -Fq 'chk_document_binary_role_v1' "$MIGRATION"
grep -Fq 'ON UPDATE RESTRICT ON DELETE RESTRICT' "$MIGRATION"
! grep -Eiq '\b(public_)?url\b' "$MIGRATION"
! grep -Eiq 'ON[[:space:]]+DELETE[[:space:]]+CASCADE' "$MIGRATION"
! grep -Eiq '(INSERT[[:space:]]+INTO|UPDATE[[:space:]]+clinical_|DELETE[[:space:]]+FROM)[[:space:]]*(clinical_binary_uploads|clinical_document_binaries)' "$MIGRATION"

grep -Fq 'function clinical_multipart_storage_assert_schema_ready(PDO $pdo)' "$READINESS"
grep -Fq 'information_schema.TABLES' "$READINESS"
grep -Fq 'information_schema.COLUMNS' "$READINESS"
grep -Fq 'information_schema.STATISTICS' "$READINESS"
grep -Fq 'information_schema.REFERENTIAL_CONSTRAINTS' "$READINESS"
grep -Fq 'information_schema.CHECK_CONSTRAINTS' "$READINESS"
! grep -Eiq '\b(CREATE|ALTER|DROP|TRUNCATE)[[:space:]]+(TABLE|INDEX|PROCEDURE)|\b(INSERT|REPLACE)[[:space:]]+INTO|\bUPDATE[[:space:]].*[[:space:]]SET\b|\bDELETE[[:space:]]+FROM\b' "$READINESS"

grep -Fq 'V1_MULTIPART_STORAGE_NOT_READY' "$ROUTER"
grep -Fq 'return !$isMultipart && !$hasFile;' "$INTEGRITY"
! grep -Fq 'clinical_multipart_storage_schema.php' "$ROUTER"
! grep -Fq 'clinical_multipart_storage_assert_schema_ready' "$ROUTER"
grep -Fq '2026_09_19_05_clinical_binary_storage.sql' "$PLAN"
grep -Fq '2026_09_17_clinical_encounter_doctor_attribution.sql' "$PLAN"

echo 'MULTI02A_STATIC_PASS'
