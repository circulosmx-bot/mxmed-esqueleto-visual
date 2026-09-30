#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="h08bp01_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^h08bp01_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/h08bp01-qa-XXXXXXXX")"
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
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_documents (
 id BIGINT UNSIGNED PRIMARY KEY, document_uuid VARCHAR(64) NOT NULL UNIQUE, title VARCHAR(255), document_type VARCHAR(64) NOT NULL,
 summary VARCHAR(512), event_datetime DATETIME NOT NULL, printable TINYINT NOT NULL DEFAULT 1, payload_json JSON NOT NULL,
 encounter_id VARCHAR(64), encounter_ref_id BIGINT UNSIGNED, appointment_id VARCHAR(64), hospital_stay_id VARCHAR(64), status VARCHAR(20) NOT NULL,
 patient_id VARCHAR(64) NOT NULL, created_at DATETIME NOT NULL, generated_at DATETIME NULL
) ENGINE=InnoDB;
CREATE TABLE clinical_document_revisions (
 revision_id BIGINT UNSIGNED PRIMARY KEY, original_document_id BIGINT UNSIGNED NOT NULL,
 supersedes_document_id BIGINT UNSIGNED NULL, new_document_id BIGINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active');
SQL
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("h08bp01-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port"
for _ in {1..40}; do if curl -fsS -o /dev/null "$base/modules/clinical/README.md" 2>/dev/null; then break; fi; sleep 0.1; done
H08BP01_QA_BASE="$base" H08BP01_QA_DB="$qa_db" python3 "$root_dir/modules/clinical/qa/h08b_p01_reader_gate.py"
