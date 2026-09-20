#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
LIB="$ROOT/api/_lib/clinical_m6_cutover.php"
TEST="$ROOT/modules/clinical/qa/m6_cutover_control_pure_test.php"
ROUTER="$ROOT/api/clinical/index.php"
MIGRATIONS="$ROOT/modules/clinical/db/migrations"

php -l "$LIB" >/dev/null
php -l "$TEST" >/dev/null
bash -n "$0"

grep -q "MXMED_CLINICAL_M6_COHORT_MODE" "$LIB"
grep -q "MXMED_CLINICAL_M6_COHORT_PAIRS" "$LIB"
grep -q "MXMED_CLINICAL_M6_EMERGENCY_OFF" "$LIB"
grep -q "function clinical_m6_cohort_authorized" "$LIB"
grep -q "function clinical_m6_patient_in_any_cohort" "$LIB"
grep -q "M6_COHORT_CONFIG_INVALID" "$LIB"
grep -q "hash_equals" "$LIB"

! grep -Eq '\$_(SERVER|GET|POST|COOKIE|REQUEST)' "$LIB"
! grep -Eiq '\b(PDO|SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b' "$LIB"
! grep -q "clinical_m6_cutover.php" "$ROUTER"
! grep -q "MXMED_CLINICAL_M6_COHORT" "$ROUTER"
! grep -R -q "MXMED_CLINICAL_M6_COHORT" "$MIGRATIONS"

echo "M6_COHORT_STATIC_CHECK=PASS"
