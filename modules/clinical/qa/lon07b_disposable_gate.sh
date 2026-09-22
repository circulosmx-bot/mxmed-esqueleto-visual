#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="lon07b_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^lon07b_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/lon07b-qa-XXXXXXXX")"
http_pid=""
cleanup(){
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  rm -rf "$qa_root"
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, UNIQUE KEY uq_pair (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64), patient_id VARCHAR(64) NOT NULL, appointment_id VARCHAR(64), encounter_dt DATETIME NOT NULL, encounter_type VARCHAR(32) NOT NULL DEFAULT 'outpatient', status VARCHAR(16) NOT NULL DEFAULT 'open', opened_by_user_id VARCHAR(64), closed_at DATETIME, closed_by_user_id VARCHAR(64), auto_note_uuid_final CHAR(36), created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
CREATE TABLE agenda_appointments (appointment_id VARCHAR(64) NOT NULL PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NULL, status VARCHAR(32) NULL, start_at DATETIME NOT NULL) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b'),('p_empty');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active'),('d_a','p_empty','active');
INSERT INTO agenda_appointments VALUES ('a_a','d_a','p_a','confirmed',UTC_TIMESTAMP());
SQL
MXMED_DB_NAME="$qa_db" php -r 'require $argv[1];$pdo=new PDO("mysql:host=localhost;dbname=".getenv("MXMED_DB_NAME"),"root","");mxmed_ensure_clinical_docs_schema($pdo);' "$root_dir/api/_lib/clinical_documents.php"
mysql "$qa_db" -e 'ALTER TABLE clinical_documents ADD COLUMN appointment_id VARCHAR(64) NULL'
for migration in \
  2026_09_18_01_encounter_lifecycle_integrity.sql \
  2026_09_18_02_encounter_structured_content.sql \
  2026_09_18_03_encounter_command_idempotency.sql \
  2026_09_18_04_encounter_document_integrity.sql \
  2026_09_21_06_longitudinal_antecedents.sql; do
  mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/$migration"
done
mysql "$qa_db" <<'SQL'
INSERT INTO clinical_encounters (doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES ('d_a','p_a',UTC_TIMESTAMP(),'open','u_a'),('d_b','p_b',UTC_TIMESTAMP(),'open','u_b');
INSERT INTO clinical_encounter_sections (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,created_by_user_id,updated_by_user_id)
  VALUES (1,'plan',1,'{"follow_up":"Control explícito pendiente"}','Control en dos semanas','u_a','u_a');
INSERT INTO clinical_observations (encounter_id,code,value_numeric,unit,effective_at,recorded_at,recorded_by_user_id,source,provenance_json)
  VALUES (1,'weight',71.5,'kg','2024-01-02 03:04:05','2024-01-02 04:04:05','u_a','direct_measurement','{}');
SQL
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_22_10_observation_time_authority.sql"; done
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("lon07b-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
window_path="$qa_root/write-window.json"
MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" php -r 'require $argv[1];clinical_m6_write_window_initialize_file(getenv("MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH"));' "$root_dir/api/_lib/clinical_m6_write_window.php"
port_hex="$(openssl rand -hex 2)";port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 MXMED_CLINICAL_M6_COHORT_MODE=allowlist MXMED_CLINICAL_M6_COHORT_PAIRS='d_a|p_a' \
  MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port"
for _ in {1..40}; do if curl -fsS -o /dev/null "$base/modules/clinical/README.md" 2>/dev/null; then break; fi;sleep 0.1;done
LON07B_QA_BASE="$base" LON07B_QA_DB="$qa_db" python3 "$root_dir/modules/clinical/qa/lon07b_http_gate.py"
