#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov04c_qa_$(openssl rand -hex 6)"
qa_tmp="$(mktemp -d /tmp/prov04c-qa-XXXXXXXX)"
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_tmp"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_06_healthcare_organization_team.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/migrations/2026_10_01_06_healthcare_organization_team.sql"
PROV04C_QA_DB="$qa_db" PROV04C_QA_TMP="$qa_tmp" php "$root_dir/modules/agenda/qa/prov04c_disposable_gate.php"
