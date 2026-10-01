#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="tax03c_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/tax03c-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" <<'SQL'
INSERT INTO patients_patients(patient_id,display_name) VALUES('p_tax03c_open','QA open'),('p_tax03c_plain','QA plain');
INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES('link_tax03c_open','d_tax03c','p_tax03c_open','active'),('link_tax03c_plain','d_tax03c','p_tax03c_plain','active');
INSERT INTO clinical_encounters(doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES('d_tax03c','p_tax03c_open',UTC_TIMESTAMP(),'open','u_tax03c');
SQL
encounter_id="$(mysql -N "$qa_db" -e "SELECT encounter_id FROM clinical_encounters WHERE patient_id='p_tax03c_open' LIMIT 1")"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("tax03c-owner");session_start();$_SESSION["doctor_id"]="d_tax03c";$_SESSION["user_id"]="u_tax03c";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
TAX03C_QA_BASE="http://127.0.0.1:$port" TAX03C_QA_DB="$qa_db" TAX03C_QA_ENCOUNTER="$encounter_id" \
  python3 "$root_dir/modules/clinical/qa/tax03c_http_gate.py"
