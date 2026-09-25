#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="fup01_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^fup01_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/fup01-qa-XXXXXXXX")"
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
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(16) NOT NULL, closed_at DATETIME NULL) ENGINE=InnoDB;
CREATE TABLE agenda_appointments (appointment_id VARCHAR(64) NOT NULL PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, consultorio_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NULL, start_at DATETIME NOT NULL, end_at DATETIME NOT NULL, modality VARCHAR(32) NULL, status VARCHAR(32) NULL) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_a','p_b','active');
INSERT INTO clinical_encounters VALUES (101,'d_a','p_a','open',NULL);
INSERT INTO agenda_appointments VALUES
 ('a_valid','d_a','north','p_a','2026-10-01 16:30:00','2026-10-01 17:00:00','in_person','confirmed'),
 ('a_foreign','d_a','north','p_b','2026-10-01 18:00:00','2026-10-01 18:30:00','in_person','confirmed'),
 ('a_stale','d_a','north','p_a','2026-10-02 10:00:00','2026-10-02 10:30:00','in_person','canceled'),
 ('a_past','d_a','north','p_a','2020-01-01 10:00:00','2020-01-01 10:30:00','in_person','confirmed');
SQL
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_06_longitudinal_antecedents.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_09_longitudinal_tasks.sql"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("fup01-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
window_path="$qa_root/write-window.json"
MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" php -r 'require $argv[1];clinical_m6_write_window_initialize_file(getenv("MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH"));' "$root_dir/api/_lib/clinical_m6_write_window.php"
port_hex="$(openssl rand -hex 2)";port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_LON06A_WRITE_ENABLED=1 \
  MXMED_CLINICAL_WRITE_WINDOW_CONTROL=FILE MXMED_CLINICAL_WRITE_WINDOW_STATE_PATH="$window_path" \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port"
for _ in {1..40}; do if curl -fsS -o /dev/null "$base/modules/clinical/README.md" 2>/dev/null; then break; fi;sleep 0.1;done
FUP01_QA_BASE="$base" FUP01_QA_DB="$qa_db" FUP01_QA_ROOT="$root_dir" python3 "$root_dir/modules/clinical/qa/fup01_disposable_gate.py"
