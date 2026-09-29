#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="flow_r1_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^flow_r1_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/flow-r1-qa-XXXXXXXX")"
http_pid=""
cleanup(){
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  rm -rf "$qa_root"
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY, birthdate DATE NULL) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, UNIQUE KEY uq_pair (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64), patient_id VARCHAR(64) NOT NULL, appointment_id VARCHAR(64), encounter_dt DATETIME NOT NULL, encounter_type VARCHAR(32) NOT NULL DEFAULT 'outpatient', status VARCHAR(16) NOT NULL DEFAULT 'open', opened_by_user_id VARCHAR(64), closed_at DATETIME, closed_by_user_id VARCHAR(64), auto_note_uuid_final CHAR(36), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;

INSERT INTO patients_patients VALUES ('p_plan02ux_review','1990-01-01');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('1','p_plan02ux_review','active');

SQL
mysql "$qa_db" < "$root_dir/modules/agenda/db/ready_schema.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/consultorios_schema.sql"
mysql "$qa_db" -e "ALTER TABLE consultorios MODIFY COLUMN logo_url LONGTEXT DEFAULT NULL, MODIFY COLUMN foto_url LONGTEXT DEFAULT NULL"
mysql "$qa_db" < "$root_dir/modules/agenda/db/availability_catalog_schema.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/availability_overrides_min.sql"
mysql "$qa_db" < "$root_dir/modules/agenda/db/agenda_settings_schema.sql"
MXMED_DB_NAME="$qa_db" php -r 'require $argv[1];$pdo=new PDO("mysql:host=localhost;dbname=".getenv("MXMED_DB_NAME"),"root","");mxmed_ensure_clinical_docs_schema($pdo);' "$root_dir/api/_lib/clinical_documents.php"
mysql "$qa_db" -e 'ALTER TABLE clinical_documents ADD COLUMN appointment_id VARCHAR(64) NULL'
for migration in \
  2026_09_18_01_encounter_lifecycle_integrity.sql \
  2026_09_18_02_encounter_structured_content.sql \
  2026_09_18_03_encounter_command_idempotency.sql \
  2026_09_18_04_encounter_document_integrity.sql \
  2026_09_19_05_clinical_binary_storage.sql \
  2026_09_21_06_longitudinal_antecedents.sql \
  2026_09_21_09_longitudinal_tasks.sql; do
  mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/$migration"
done
mysql "$qa_db" <<'SQL'
INSERT INTO consultorios (doctor_id,consultorio_id,titulo) VALUES ('1','1','Consultorio de prueba');
INSERT INTO agenda_settings (doctor_id,consultorio_id,appointment_duration_min) VALUES ('1','1',30);
INSERT INTO consultorio_schedule (doctor_id,consultorio_id,weekday,start_time,end_time) VALUES
 ('1','1',1,'09:00:00','12:00:00'),('1','1',2,'09:00:00','12:00:00'),('1','1',3,'09:00:00','12:00:00'),('1','1',4,'09:00:00','12:00:00'),('1','1',5,'09:00:00','12:00:00'),('1','1',6,'09:00:00','12:00:00'),('1','1',7,'09:00:00','12:00:00');
INSERT INTO clinical_encounters (encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES (1016,'1','p_plan02ux_review',UTC_TIMESTAMP(),'open','review-user');
SQL
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_22_10_observation_time_authority.sql"; done
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_24_11_observation_invalidation.sql"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("step3-head-neck-qa");session_start();$_SESSION["doctor_id"]="1";$_SESSION["user_id"]="review-user";session_write_close();'
window_path="$qa_root/write-window.json"
MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" php -r 'require $argv[1];clinical_m6_write_window_initialize_file(getenv("MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH"));' "$root_dir/api/_lib/clinical_m6_write_window.php"
port_hex="$(openssl rand -hex 2)";port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_LON06A_WRITE_ENABLED=1 MXMED_CLINICAL_STAGING_TTL_SECONDS=600 MXMED_CLINICAL_PRIVATE_STORAGE_ROOT="$qa_root/private" MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=allowlist MXMED_CLINICAL_M6_COHORT_PAIRS='1|p_plan02ux_review' \
  MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port"
for _ in {1..40}; do if curl -fsS -o /dev/null "$base/modules/clinical/README.md" 2>/dev/null; then break; fi;sleep 0.1;done
FLOW_R1_QA_BASE="$base" FLOW_R1_QA_DB="$qa_db" FLOW_R1_QA_ROOT="$qa_root" python3 "${FLOW_R1_QA_SCRIPT:-$root_dir/modules/clinical/qa/consultation_flow_r1_browser.py}"
