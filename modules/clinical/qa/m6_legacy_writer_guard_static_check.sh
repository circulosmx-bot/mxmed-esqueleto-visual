#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
LIB="$ROOT/api/_lib/clinical_m6_cutover.php"
ROUTER="$ROOT/api/clinical/index.php"
STANDALONE="$ROOT/api/clinical-documents.php"
EVOLUTION="$ROOT/api/evolution-note-generate.php"
TEST="$ROOT/modules/clinical/qa/m6_legacy_writer_guard_pure_test.php"
MIGRATIONS="$ROOT/modules/clinical/db/migrations"

php -l "$LIB" >/dev/null
php -l "$ROUTER" >/dev/null
php -l "$STANDALONE" >/dev/null
php -l "$EVOLUTION" >/dev/null
php -l "$TEST" >/dev/null
bash -n "$0"

grep -q "function clinical_m6_assert_legacy_write_allowed" "$LIB"
grep -q "M6_LEGACY_WRITE_BLOCKED" "$LIB"
grep -q "return 409;" "$LIB"

grep -q "clinical_m6_cutover.php" "$ROUTER"
grep -q "clinical_m6_cutover.php" "$STANDALONE"
grep -q "clinical_m6_cutover.php" "$EVOLUTION"

for marker in \
    M6_GUARD_C04_C05_C20_CREATE \
    M6_GUARD_C04_C05_C20_MULTIPART \
    M6_GUARD_C21_NOTE_CAPTURE_UPLOAD \
    M6_GUARD_C11_C20_REPLICATE \
    M6_GUARD_C05_C20_REPLACE \
    M6_GUARD_C12_C20_PATCH; do
    grep -q "$marker" "$ROUTER"
done
grep -q "M6_GUARD_C16_STANDALONE_SAVE" "$STANDALONE"
grep -q "M6_GUARD_C17_EVOLUTION_CREATE" "$EVOLUTION"

grep -q "clinical_m6_assert_legacy_write_allowed" "$STANDALONE"
grep -q "clinical_m6_assert_legacy_write_allowed" "$EVOLUTION"
grep -q "clinical_m6_send_legacy_write_blocked" "$ROUTER"
grep -q "'error' => 'M6_LEGACY_WRITE_BLOCKED'" "$ROUTER"
grep -q "'error' => 'M6_LEGACY_WRITE_BLOCKED'" "$STANDALONE"
grep -q "'error' => 'M6_LEGACY_WRITE_BLOCKED'" "$EVOLUTION"

assert_before_next() {
    local file="$1"
    local marker="$2"
    local mutation="$3"
    local guard_line
    local mutation_line
    guard_line="$(grep -n -m1 -F "$marker" "$file" | cut -d: -f1)"
    mutation_line="$(awk -v start="$guard_line" -v needle="$mutation" 'NR > start && index($0, needle) { print NR; exit }' "$file")"
    test -n "$guard_line"
    test -n "$mutation_line"
    test "$guard_line" -lt "$mutation_line"
}

assert_before_next "$ROUTER" "M6_GUARD_C04_C05_C20_CREATE" '\$pdo->beginTransaction()'
assert_before_next "$ROUTER" "M6_GUARD_C04_C05_C20_MULTIPART" 'clinical_store_uploaded_file('
assert_before_next "$ROUTER" "M6_GUARD_C11_C20_REPLICATE" 'INSERT INTO clinical_documents'
assert_before_next "$ROUTER" "M6_GUARD_C05_C20_REPLACE" '\$pdo->beginTransaction()'
assert_before_next "$ROUTER" "M6_GUARD_C12_C20_PATCH" 'UPDATE clinical_documents'
assert_before_next "$STANDALONE" "M6_GUARD_C16_STANDALONE_SAVE" 'INSERT INTO clinical_documents'
assert_before_next "$EVOLUTION" "M6_GUARD_C17_EVOLUTION_CREATE" 'INSERT INTO clinical_documents'

test "$(grep -c "clinical_m6_assert_legacy_write_allowed" "$ROUTER")" -ge 5
test "$(grep -c "clinical_m6_assert_legacy_write_allowed" "$STANDALONE")" -eq 1
test "$(grep -c "clinical_m6_assert_legacy_write_allowed" "$EVOLUTION")" -eq 1

! grep -Eq '\$_(SERVER|GET|POST|COOKIE|REQUEST)' "$LIB"
! grep -Eiq '\b(PDO|SELECT|INSERT|UPDATE|DELETE|REPLACE|CREATE|ALTER|DROP|TRUNCATE)\b' "$LIB"
! grep -q "clinical_m6_cohort_authorized" "$ROUTER"
! grep -q "MXMED_CLINICAL_M6_COHORT" "$ROUTER"
! grep -R -q "MXMED_CLINICAL_M6_COHORT" "$MIGRATIONS"

echo "M6_LEGACY_WRITER_GUARD_STATIC_CHECK=PASS"
