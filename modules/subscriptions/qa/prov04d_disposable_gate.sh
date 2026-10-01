#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov04d_qa_$(openssl rand -hex 6)"
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql"
mysql "$qa_db" < "$root_dir/modules/profiles/db/2026_06_20_create_subscription_contract_acceptances.sql"
if [[ -z "$(mysql -N "$qa_db" -e "SHOW COLUMNS FROM subscription_plans LIKE 'product_family'")" ]]; then
  mysql "$qa_db" < "$root_dir/modules/subscriptions/db/2026_10_01_01_provider_organization_commercial_entitlement.sql"
fi
PROV04D_QA_DB="$qa_db" php "$root_dir/modules/subscriptions/qa/prov04d_disposable_gate.php"
