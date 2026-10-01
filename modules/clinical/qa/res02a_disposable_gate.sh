#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="res02a_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/res02a-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" <<'SQL'
INSERT INTO patients_patients(patient_id,display_name) VALUES('p_res02a_open','QA open'),('p_res02a_plain','QA plain'),('p_res02a_other','QA other');
INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES('link_res02a_open','d_res02a','p_res02a_open','active'),('link_res02a_plain','d_res02a','p_res02a_plain','active'),('link_res02a_other','d_res02a','p_res02a_other','active');
INSERT INTO clinical_encounters(doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES('d_res02a','p_res02a_open',UTC_TIMESTAMP(),'open','u_res02a');
SQL
encounter_id="$(mysql -N "$qa_db" -e "SELECT encounter_id FROM clinical_encounters WHERE patient_id='p_res02a_open' LIMIT 1")"
mkdir -p "$qa_root/sessions" "$qa_root/private"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("res02a-owner");session_start();$_SESSION["doctor_id"]="d_res02a";$_SESSION["user_id"]="u_res02a";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off MXMED_CLINICAL_PRIVATE_STORAGE_ROOT="$qa_root/private" MXMED_CLINICAL_STAGING_TTL_SECONDS=300 \
  php -d upload_max_filesize=32M -d post_max_size=40M -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..50}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
RES02A_QA_BASE="http://127.0.0.1:$port" RES02A_QA_DB="$qa_db" RES02A_QA_ENCOUNTER="$encounter_id" python3 "$root_dir/modules/clinical/qa/res02a_http_gate.py"
