#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="labcat02a_order_qa_$(openssl rand -hex 5)"
qa_root="$(mktemp -d /tmp/labcat02a-order-qa-XXXXXXXX)"
http_pid=""
cleanup(){
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_root"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" < "$repo_root/modules/clinical/db/migrations/2026_09_30_15_initial_curated_study_catalog.sql"
mysql "$qa_db" < "$repo_root/modules/clinical/db/migrations/2026_10_02_17_dental_cat02_catalog.sql"
mysql "$qa_db" < "$repo_root/modules/clinical/db/migrations/2026_10_02_18_lab_cat02a_common_catalog.sql"
mysql "$qa_db" <<'SQL'
INSERT INTO patients_patients(patient_id,display_name,birthdate) VALUES
 ('p_labcat02a_order','Paciente Laboratorio QA','1985-02-03'),('p_labcat02a_foreign','Paciente Ajeno QA','1992-06-07');
INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES
 ('link_labcat02a_order','d_labcat02a_order','p_labcat02a_order','active'),('link_labcat02a_foreign','d_labcat02a_foreign','p_labcat02a_foreign','active');
INSERT INTO profiles_doctors(doctor_id,display_name,prefix,professional_designation,specialty_primary,professional_license)
 VALUES('d_labcat02a_order','Médico Laboratorio QA','Dr.','Médico General','Medicina General','QA-123'),
       ('d_labcat02a_foreign','Médico Ajeno QA','Dr.','Médico General','Medicina General','QA-999');
SQL
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("labcat02a-owner");session_start();$_SESSION["doctor_id"]="d_labcat02a_order";$_SESSION["user_id"]="u_labcat02a_order";session_write_close();'
php -d "session.save_path=$qa_root/sessions" -r 'session_id("labcat02a-foreign");session_start();$_SESSION["doctor_id"]="d_labcat02a_foreign";$_SESSION["user_id"]="u_labcat02a_foreign";session_write_close();'
port_hex="$(openssl rand -hex 2)"; qa_port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
 MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off \
 php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$qa_port" -t "$repo_root" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$qa_port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
LAB_CAT02A_QA_BASE="http://127.0.0.1:$qa_port" LAB_CAT02A_QA_DB="$qa_db" python3 "$repo_root/modules/clinical/qa/lab_cat02a_disposable_gate.py"
