#!/usr/bin/env bash
set -euo pipefail

# Only a fresh, uniquely named disposable database is ever used here.
qa_db="lon03a_qa_$(openssl rand -hex 6)"
if [[ ! "$qa_db" =~ ^lon03a_qa_[0-9a-f]{12}$ ]]; then exit 2; fi
http_root="$(mktemp -d "${TMPDIR:-/tmp}/lon03a-http-XXXXXXXX")"
http_pid=""
cleanup() {
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  rm -rf "$http_root"
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, UNIQUE KEY uq_pair (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_encounter_sections (section_id BIGINT UNSIGNED PRIMARY KEY, encounter_id BIGINT UNSIGNED NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_documents (id BIGINT UNSIGNED PRIMARY KEY, encounter_id VARCHAR(128), patient_id VARCHAR(128) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_record_entries (entry_id BIGINT UNSIGNED PRIMARY KEY, patient_id VARCHAR(64) NOT NULL, payload_json JSON) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active');
INSERT INTO clinical_encounters VALUES (101,'d_a','p_a'),(102,'d_b','p_b');
INSERT INTO clinical_encounter_sections VALUES (201,101),(202,102);
INSERT INTO clinical_documents VALUES (301,'101','p_a'),(302,'102','p_b');
INSERT INTO clinical_record_entries VALUES (401,'p_a','{"legacy":"preserved"}');
SQL
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
migration="$root_dir/modules/clinical/db/migrations/2026_09_21_06_longitudinal_antecedents.sql"
mysql "$qa_db" < "$migration"
mysql "$qa_db" < "$migration"
LON03A_QA_DB="$qa_db" MXMED_LON03A_WRITE_ENABLED=1 php "$root_dir/modules/clinical/qa/lon03a_repository_gate.php"

# HTTP smoke uses only the same disposable database and a synthetic QA session.
mkdir "$http_root/sessions"
php -d "session.save_path=$http_root/sessions" -r 'session_id("lon03a-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
port_hex="$(openssl rand -hex 2)"
port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_LON03A_WRITE_ENABLED=1 \
  php -d "session.save_path=$http_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$http_root/server.log" 2>&1 &
http_pid=$!
url="http://127.0.0.1:$port/api/clinical/index.php/patients/p_a/longitudinal/antecedents"
for _ in {1..30}; do
  if curl -fsS -o "$http_root/read.json" -b 'PHPSESSID=lon03a-qa' "$url" 2>/dev/null; then break; fi
  sleep 0.1
done
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["knowledge_state_by_category"]["VACCINATION"]??null)!=="UNKNOWN")exit(1);' "$http_root/read.json"
curl -fsS -o "$http_root/write.json" -b 'PHPSESSID=lon03a-qa' -H 'Content-Type: application/json' -H 'Idempotency-Key: http-create-1' \
  -d '{"category":"VACCINATION","content":"Synthetic HTTP fact","state":"CURRENT","provenance":"PATIENT_REPORTED"}' "$url"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["item"]["content"]??null)!=="Synthetic HTTP fact")exit(1);' "$http_root/write.json"
status=$(curl -sS -o "$http_root/foreign.json" -w '%{http_code}' -b 'PHPSESSID=lon03a-qa' \
  "http://127.0.0.1:$port/api/clinical/index.php/patients/p_b/longitudinal/antecedents")
[[ "$status" == 404 ]]
echo 'LON03A_DISPOSABLE_HTTP_GATE=PASS'
