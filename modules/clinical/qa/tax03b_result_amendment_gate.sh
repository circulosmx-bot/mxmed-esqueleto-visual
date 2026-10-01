#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="tax03b_amend_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/tax03b-amend-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
TAX03B_AMEND_DB="$qa_db" php "$root_dir/modules/clinical/qa/tax03b_result_amendment_fixture.php" > "$qa_root/fixture.json"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("tax03b-amend");session_start();$_SESSION["doctor_id"]="d_tax03b_amend";$_SESSION["user_id"]="u_tax03b_amend";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
TAX03B_AMEND_BASE="http://127.0.0.1:$port" TAX03B_AMEND_FIXTURE="$qa_root/fixture.json" TAX03B_AMEND_DB="$qa_db" python3 "$root_dir/modules/clinical/qa/tax03b_result_amendment_http.py"
