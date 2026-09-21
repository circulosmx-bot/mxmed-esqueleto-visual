#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="lon05a_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^lon05a_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/lon05a-http-XXXXXXXX")"
http_pid=""
cleanup() {
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  rm -rf "$qa_root"
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, UNIQUE KEY uq_pair (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_encounter_sections (section_id BIGINT UNSIGNED PRIMARY KEY, encounter_id BIGINT UNSIGNED NOT NULL, section_type VARCHAR(40) NOT NULL, narrative_text TEXT DEFAULT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_documents (id BIGINT UNSIGNED PRIMARY KEY, encounter_ref_id BIGINT UNSIGNED, patient_id VARCHAR(64) NOT NULL, document_type VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active');
INSERT INTO clinical_encounters VALUES (101,'d_a','p_a'),(102,'d_b','p_b');
INSERT INTO clinical_encounter_sections VALUES (201,101,'assessment','Synthetic assessment');
INSERT INTO clinical_documents VALUES (301,101,'p_a','prescription','generated'),(302,102,'p_b','prescription','generated');
SQL
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_06_longitudinal_antecedents.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_07_longitudinal_problems.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_08_longitudinal_medications.sql"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("lon05a-a");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
php -d "session.save_path=$qa_root/sessions" -r 'session_id("lon05a-b");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_b";session_write_close();'
php -d "session.save_path=$qa_root/sessions" -r 'session_id("lon05a-foreign");session_start();$_SESSION["doctor_id"]="d_b";$_SESSION["user_id"]="u_foreign";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_LON05A_WRITE_ENABLED=1 \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port/api/clinical/index.php/patients/p_a/longitudinal"
for _ in {1..40}; do if curl -fsS -o "$qa_root/read.json" -b 'PHPSESSID=lon05a-a' "$base/medications" 2>/dev/null; then break; fi; sleep 0.1; done
LON05A_HTTP_BASE="$base" LON05A_HTTP_DB="$qa_db" python3 "$root_dir/modules/clinical/qa/lon05a_http_gate.py"
echo 'LON05A_DISPOSABLE_HTTP_GATE=PASS'
