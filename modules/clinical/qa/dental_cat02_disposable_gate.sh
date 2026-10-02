#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="dental_cat02_qa_$(openssl rand -hex 6)"
trap 'mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"' EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_10_02_17_dental_cat02_catalog.sql"; done
DENTAL_CAT02_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/dental_cat02_disposable_gate.php"
