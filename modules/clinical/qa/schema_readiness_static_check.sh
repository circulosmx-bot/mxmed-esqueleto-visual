#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../../.." && pwd)"
LIB="$ROOT/api/_lib/clinical_schema_readiness.php"
CLI="$ROOT/scripts/clinical-schema-readiness.php"
AUTH="$ROOT/modules/clinical/schema/readiness_authority.json"

php -l "$LIB" >/dev/null
php -l "$CLI" >/dev/null
python3 -m json.tool "$AUTH" >/dev/null

grep -Fq "['prestate', 'poststate']" "$LIB"
grep -Fq 'information_schema.COLUMNS' "$LIB"
grep -Fq 'information_schema.STATISTICS' "$LIB"
grep -Fq 'information_schema.REFERENTIAL_CONSTRAINTS' "$LIB"
grep -Fq 'information_schema.CHECK_CONSTRAINTS' "$LIB"
grep -Fq 'information_schema.TRIGGERS' "$LIB"
grep -Fq "namedRows('ROUTINES'" "$LIB"
grep -Fq 'clinical_multipart_storage_required_schema' "$LIB"
grep -Fq 'MXMED_SCHEMA_READINESS_DB' "$CLI"

python3 - "$AUTH" <<'PY'
import json,sys
a=json.load(open(sys.argv[1]))
assert a['migration_set']==['2026_09_18_01','2026_09_18_02','2026_09_18_03','2026_09_18_04','2026_09_19_05']
assert a['unresolved_placeholders']==[]
for table in ['clinical_encounter_sections','clinical_observations','clinical_encounter_amendments',
              'clinical_encounter_start_requests','clinical_idempotency_requests',
              'clinical_encounter_final_notes','clinical_document_revisions',
              'clinical_binary_uploads','clinical_document_binaries']:
    assert table in a['poststate']['objects']
assert {t['name'] for t in a['poststate']['triggers']}=={
    'trg_clinical_encounters_v1_before_insert','trg_clinical_encounters_v1_before_update'}
assert set(a['dependencies'])=={'C04','C05','C21'}
PY

# Comparator source may only contain read-only SQL entry points.
if grep -Eiq -- "->(exec|query|prepare)\([^\n]*(CREATE|ALTER|DROP|TRUNCATE|INSERT|UPDATE|DELETE|REPLACE)[[:space:]]" "$LIB"; then
  echo 'schema comparator contains a mutation SQL entry point' >&2
  exit 1
fi

echo 'SCHEMA_READINESS_STATIC_QA=PASS migrations=5 modes=prestate,poststate readonly=true'
