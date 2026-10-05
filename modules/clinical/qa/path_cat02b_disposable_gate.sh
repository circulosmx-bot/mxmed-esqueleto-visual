#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="pathcat02b_qa_$(openssl rand -hex 4)"
qa_root="$(mktemp -d /tmp/pathcat02b-XXXXXXXX)"
server_pid=""
cleanup(){ [[ -z "$server_pid" ]] || { kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; }; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; rm -rf "$qa_root"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysqldump --no-create-info --skip-triggers --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c clinical_study_types | mysql "$qa_db"
mysql "$qa_db" <<'SQL'
INSERT INTO patients_patients(patient_id,display_name,birthdate) VALUES ('p_pathcat02b','Paciente Patología QA','1985-02-03');
INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES ('link_pathcat02b','d_pathcat02b','p_pathcat02b','active');
INSERT INTO profiles_doctors(doctor_id,display_name,prefix,professional_designation,specialty_primary,professional_license) VALUES('d_pathcat02b','Médico Patología QA','Dr.','Médico General','Medicina General','QA-PATH');
SQL
mkdir -m 700 "$qa_root/sessions" "$qa_root/private"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("pathcat02b-owner");session_start();$_SESSION["doctor_id"]="d_pathcat02b";$_SESSION["user_id"]="u_pathcat02b";session_write_close();'
qa_port=$((18000 + 16#$(openssl rand -hex 2) % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_PRIVATE_STORAGE_ROOT="$qa_root/private" MXMED_CLINICAL_STAGING_TTL_SECONDS=300 MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$qa_port" -t "$repo_root" > "$qa_root/server.log" 2>&1 & server_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$qa_port/index.html" 2>/dev/null; then break; fi; sleep .1; done
PATHCAT02B_QA_BASE="http://127.0.0.1:$qa_port" PATHCAT02B_QA_DB="$qa_db" python3 "$repo_root/modules/clinical/qa/path_cat02b_disposable_gate.py"
