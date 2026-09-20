#!/usr/bin/env bash
set -euo pipefail

repo_root="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
adapter="$repo_root/api/_lib/clinical_private_binary_storage.php"
router="$repo_root/api/clinical/index.php"
baseline="4c125344b97009c236e243b86c4290844c229ed6"

test -f "$adapter"

# Repository-only: runtime entry points must not load the adapter.
if rg -n 'clinical_private_binary_storage' \
  "$router" \
  "$repo_root/api/clinical-documents.php" \
  "$repo_root/api/evolution-note-generate.php" >/dev/null; then
  echo 'MULTI03A_STATIC_QA=FAIL runtime wiring detected' >&2
  exit 1
fi

# No DB client, repository storage default, public locator construction or generic final delete.
if rg -n 'PDO|mysqli|mysql:|/storage|https?://|public[_ -]?url' "$adapter" >/dev/null; then
  echo 'MULTI03A_STATIC_QA=FAIL forbidden adapter dependency or locator' >&2
  exit 1
fi
if rg -n 'public function delete\s*\(' "$adapter" >/dev/null; then
  echo 'MULTI03A_STATIC_QA=FAIL unrestricted delete API detected' >&2
  exit 1
fi

grep -Fq 'public function deleteUncommitted(' "$adapter"
grep -Fq 'public function quarantine(' "$adapter"
grep -Fq 'public function finalizeCreateOnly(' "$adapter"
grep -Fq 'public function openReadStream(' "$adapter"
grep -Fq 'public function inventory(' "$adapter"
grep -Fq 'final class ClinicalBinaryReconciliation' "$adapter"
grep -Fq 'random_bytes(' "$adapter"
grep -Fq 'finfo(FILEINFO_MIME_TYPE)' "$adapter"
grep -Fq "'staging_retained' => true" "$adapter"
grep -Fq 'V1_MULTIPART_STORAGE_NOT_READY' "$router"

finalize_source="$(sed -n '/public function finalizeCreateOnly(/,/public function deleteUncommitted(/p' "$adapter")"
if grep -Fq 'unlink($stagingPath)' <<<"$finalize_source"; then
  echo 'MULTI03A_STATIC_QA=FAIL finalization removes staging' >&2
  exit 1
fi

# Protected runtime, legacy upload implementation and schema migration remain byte-for-byte unchanged.
protected_paths=(
  api/clinical-documents.php
  api/evolution-note-generate.php
  api/_lib/clinical_encounter_integrity.php
  modules/clinical/db/migrations/2026_09_19_05_clinical_binary_storage.sql
)
if ! git -C "$repo_root" diff --quiet "$baseline" -- "${protected_paths[@]}"; then
  echo 'MULTI03A_STATIC_QA=FAIL protected runtime or migration changed' >&2
  git -C "$repo_root" diff --name-only "$baseline" -- "${protected_paths[@]}" >&2
  exit 1
fi

echo 'MULTI03A_STATIC_QA=PASS'
echo 'MULTI03A_RUNTIME_WIRING_ACTIVE=false'
echo 'ANY_DATABASE_CONNECTED=false'
echo 'GENERAL_FINAL_BINARY_DELETE_API=false'
echo 'V1_MULTIPART_503_PRESERVED=true'
echo 'FINALIZATION_PRESERVES_STAGING=true'
echo 'MULTI03A_STATIC_QA_MIGRATION05_PATH_CORRECT=true'

# MULTI04B permits only the bounded GET insertion and read-only storage construction.
bash "$repo_root/modules/clinical/qa/multi04b_static_check.sh"
