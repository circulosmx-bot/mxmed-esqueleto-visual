#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="b3_interop01_$(openssl rand -hex 5)"
qa_tmp="$(mktemp -d /tmp/b3-interop01-XXXXXXXX)"
cleanup() {
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_tmp"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" -e 'ALTER TABLE clinical_study_types AUTO_INCREMENT=1'
mysql "$qa_db" < "$repo_root/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
table_count="$(mysql -N "$qa_db" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('healthcare_study_referrals','healthcare_study_referral_items','healthcare_study_referral_events','healthcare_provider_service_orders','healthcare_provider_service_order_items','healthcare_provider_result_sources')")"
if [[ "$table_count" == 0 ]]; then
  mysql "$qa_db" < "$repo_root/modules/clinical/db/migrations/2026_10_01_16_b3_interop_foundation.sql"
elif [[ "$table_count" != 6 ]]; then
  echo 'INTEROP_SCHEMA_PARTIAL' >&2
  exit 1
fi
INTEROP_QA_DB="$qa_db" INTEROP_QA_PRIVATE="$qa_tmp/private" php "$repo_root/modules/clinical/qa/b3_interop01_disposable_gate.php"
echo 'DISPOSABLE_DATABASE_REMOVED=true (cleanup trap)'
