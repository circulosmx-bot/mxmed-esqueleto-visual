#!/usr/bin/env bash
set -euo pipefail
repo_root="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="ordprint02a_qa_$(openssl rand -hex 5)"
qa_root="$(mktemp -d /tmp/ordprint02a-qa-XXXXXXXX)"
http_pid=""
cleanup(){
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_root"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysqldump --no-data --set-gtid-purged=OFF --single-transaction mxmed_director_review_lon07c | mysql "$qa_db"
mysql "$qa_db" <<'SQL'
INSERT INTO patients_patients(patient_id,display_name,birthdate) VALUES
 ('p_ordprint','Paciente Original QA','1985-02-03'),('p_other','Paciente Ajeno QA','1992-06-07');
INSERT INTO patients_doctor_links(link_id,doctor_id,patient_id,status) VALUES
 ('link_ordprint','d_ordprint','p_ordprint','active'),('link_other','d_other','p_other','active');
INSERT INTO profiles_doctors(doctor_id,display_name,prefix,professional_designation,specialty_primary,professional_license)
 VALUES('d_ordprint','Médica Original QA','Dra.','Médica','Medicina interna','QA-123'),
       ('d_other','Médico Ajeno QA','Dr.','Médico','Medicina general','QA-999');
INSERT INTO consultorios(doctor_id,consultorio_id,titulo,calle,num_ext,municipio,estado)
 VALUES('d_ordprint','c_ordprint','Consultorio Original QA','Calle QA','12','Ciudad QA','Estado QA');
INSERT INTO agenda_appointments(appointment_id,doctor_id,consultorio_id,patient_id,start_at,end_at,modality,status)
 VALUES('a_ordprint','d_ordprint','c_ordprint','p_ordprint','2026-10-01 13:00:00','2026-10-01 13:30:00','presencial','confirmed');
INSERT INTO clinical_encounters(doctor_id,patient_id,appointment_id,encounter_dt,status,opened_by_user_id)
 VALUES('d_ordprint','p_ordprint','a_ordprint','2026-10-01 13:00:00','open','u_ordprint');
SQL
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("ordprint-owner");session_start();$_SESSION["doctor_id"]="d_ordprint";$_SESSION["user_id"]="u_ordprint";session_write_close();'
php -d "session.save_path=$qa_root/sessions" -r 'session_id("ordprint-other");session_start();$_SESSION["doctor_id"]="d_other";$_SESSION["user_id"]="u_other";session_write_close();'
port_hex="$(openssl rand -hex 2)"; qa_port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
 MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=off \
 php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$qa_port" -t "$repo_root" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$qa_port/index.html" 2>/dev/null; then break; fi; sleep 0.1; done
ORDPRINT_QA_BASE="http://127.0.0.1:$qa_port" ORDPRINT_QA_DB="$qa_db" \
 python3 "$repo_root/modules/clinical/qa/ordprint02a_disposable_gate.py"
