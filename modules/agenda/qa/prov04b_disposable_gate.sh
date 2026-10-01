#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov04b_qa_$(openssl rand -hex 6)"
qa_tmp="$(mktemp -d /tmp/prov04b-qa-XXXXXXXX)"
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_tmp"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
if [[ -z "$(mysql -N "$qa_db" -e "SHOW TABLES LIKE 'internal_operator_grants'")" ]]; then
  mysql "$qa_db" < "$root_dir/modules/identity/db/migrations/2026_09_08_07_create_internal_operator_grants.sql"
fi
if [[ -z "$(mysql -N "$qa_db" -e "SHOW TABLES LIKE 'internal_staff'")" ]]; then
  mysql "$qa_db" < "$root_dir/modules/identity/db/migrations/2026_09_10_01_internal_governance.sql"
fi
mysql "$qa_db" < "$root_dir/modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_05_healthcare_organization_claims.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_05_healthcare_organization_claims.sql"
PROV04B_QA_DB="$qa_db" PROV04B_QA_TMP="$qa_tmp" php "$root_dir/modules/agenda/qa/prov04b_disposable_gate.php"
