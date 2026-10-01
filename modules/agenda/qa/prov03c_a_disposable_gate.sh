#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov03c_qa_$(openssl rand -hex 6)"
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_07_23_03_create_medical_groups.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_01_add_medical_groups_organization_type.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_02_lock_medical_groups_organization_type.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_03_healthcare_organization_locations_offerings.sql"
mysql "$qa_db" -e "INSERT INTO medical_groups(group_id,organization_type_key,canonical_name,display_name,status,source) VALUES('org_existing','LABORATORY','org_existing','Existing organization','pending','operator_created'); INSERT INTO healthcare_organization_locations(location_uuid,group_id,branch_name) VALUES('00000000-0000-4000-8000-000000000004','org_existing','Existing branch'); INSERT INTO healthcare_organization_location_study_offerings(location_id,study_type_id) SELECT l.location_id,MIN(s.study_type_id) FROM healthcare_organization_locations l CROSS JOIN clinical_study_types s WHERE l.group_id='org_existing' GROUP BY l.location_id;"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_04_provider_eligibility_service_areas.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_04_provider_eligibility_service_areas.sql"
mysql "$qa_db" < "$root_dir/modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql"
PROV03C_QA_DB="$qa_db" php "$root_dir/modules/agenda/qa/prov03c_a_disposable_gate.php"
