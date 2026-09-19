#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
BARRIER="$ROOT/modules/clinical/qa/m5_concurrency_barrier.php"
CONTROL="$ROOT/modules/clinical/qa/m5_barrier_release.sh"
INTEGRITY="$ROOT/api/_lib/clinical_encounter_integrity.php"

php -l "$BARRIER" >/dev/null
php -l "$ROOT/modules/clinical/qa/m5_concurrency_barrier_pure_test.php" >/dev/null
bash -n "$CONTROL"

! grep -Eq "^require_once .*modules/clinical/qa/m5_concurrency_barrier\\.php" "$INTEGRITY"
grep -q 'function clinical_m5_qa_barrier_reach_if_enabled' "$INTEGRITY"
grep -q "getenv('MXMED_CLINICAL_M5_QA_MODE')" "$INTEGRITY"
grep -q "throw new RuntimeException('M5_QA_BARRIER_IMPLEMENTATION_NOT_AVAILABLE')" "$INTEGRITY"
grep -q 'require_once $implementationFile' "$INTEGRITY"
grep -q 'clinical_m5_qa_barrier_reach_if_enabled' "$INTEGRITY"
grep -q 'MXMED_CLINICAL_M5_QA_MODE' "$BARRIER"
grep -q 'MXMED_CLINICAL_M5_QA_ENVIRONMENT_ID' "$BARRIER"
grep -q 'M5_QA_BARRIER_ENVIRONMENT_DENIED' "$BARRIER"
grep -q "\['prod', 'production', 'staging'\]" "$BARRIER"
grep -q 'arrived_A' "$BARRIER"
grep -q 'arrived_B' "$BARRIER"
grep -q "DIRECTORY_SEPARATOR . 'release'" "$BARRIER"
grep -q 'is_file($arrivedA) && is_file($arrivedB) && is_file($release)' "$BARRIER"
! grep -Eiq 'clinical_(encounters|documents|observations)|patients_' "$BARRIER"

for point in \
  T04_CONCURRENT_START_BEFORE_INSERT \
  T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT \
  T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK; do
  grep -q "$point" "$BARRIER"
  grep -q "$point" "$INTEGRITY"
  grep -q "$point" "$CONTROL"
done

start_block="$(sed -n '/public function start(/,/public function replayStart/p' "$INTEGRITY")"
finalize_block="$(sed -n '/public function finalizeWithNoteFactory(/,/public function void(/p' "$INTEGRITY")"
void_block="$(sed -n '/public function void(/,/public function appendEncounterAmendment(/p' "$INTEGRITY")"
grep -q 'T04_CONCURRENT_START_BEFORE_INSERT' <<< "$start_block"
grep -q 'T11_CONCURRENT_FINALIZE_BEFORE_TERMINAL_LOCK_OR_COMMIT' <<< "$finalize_block"
grep -q 'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK' <<< "$finalize_block"
grep -q 'T26_FINALIZE_VOID_RACE_BEFORE_TERMINAL_LOCK' <<< "$void_block"

php "$ROOT/modules/clinical/qa/m5_concurrency_barrier_pure_test.php"
echo 'M5_PREP01_STATIC_QA_RESULT=PASS'
