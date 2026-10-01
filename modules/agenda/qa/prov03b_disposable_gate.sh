#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov03b_qa_$(openssl rand -hex 6)"
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_07_23_03_create_medical_groups.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_01_add_medical_groups_organization_type.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_02_lock_medical_groups_organization_type.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" < "$root_dir/modules/catalog/db/2026_07_21_01_create_catalog_cp_colonias.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_03_healthcare_organization_locations_offerings.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_03_healthcare_organization_locations_offerings.sql"
PROV03B_QA_DB="$qa_db" php "$root_dir/modules/agenda/qa/prov03b_disposable_gate.php"
