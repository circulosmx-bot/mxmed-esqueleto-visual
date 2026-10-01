#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="prov03cb_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/prov03cb-qa-XXXXXXXX)"
http_pid=""
cleanup() {
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_root"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" < "$root_dir/modules/profiles/db/2026_06_19_create_subscription_plan_lifecycle.sql"
if [[ -z "$(mysql -N "$qa_db" -e "SHOW COLUMNS FROM subscription_plans LIKE 'product_family'")" ]]; then
  mysql "$qa_db" < "$root_dir/modules/subscriptions/db/2026_10_01_01_provider_organization_commercial_entitlement.sql"
fi
PROV03CB_QA_DB="$qa_db" php "$root_dir/modules/clinical/qa/prov03c_b_fixture.php" > "$qa_root/fixture.json"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("prov03cb-owner");session_start();$_SESSION["doctor_id"]="d_match";$_SESSION["user_id"]="u_match";session_write_close();'
php -d "session.save_path=$qa_root/sessions" -r 'session_id("prov03cb-other");session_start();$_SESSION["doctor_id"]="d_other";$_SESSION["user_id"]="u_other";session_write_close();'
port_hex="$(openssl rand -hex 2)"; qa_port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
  MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$qa_port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$qa_port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
PROV03CB_QA_BASE="http://127.0.0.1:$qa_port" PROV03CB_QA_DB="$qa_db" \
  PROV03CB_FIXTURE="$qa_root/fixture.json" python3 "$root_dir/modules/clinical/qa/prov03c_b_http_gate.py"
