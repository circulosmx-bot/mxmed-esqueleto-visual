#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="lon04a_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^lon04a_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/lon04a-qa-XXXXXXXX")"
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
CREATE TABLE clinical_documents (id BIGINT UNSIGNED PRIMARY KEY, encounter_id VARCHAR(128), patient_id VARCHAR(128) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_record_entries (entry_id BIGINT UNSIGNED PRIMARY KEY, patient_id VARCHAR(64) NOT NULL, payload_json JSON) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active');
INSERT INTO clinical_encounters VALUES (101,'d_a','p_a'),(102,'d_b','p_b');
INSERT INTO clinical_encounter_sections VALUES (201,101,'assessment','Synthetic diagnostic assessment'),(202,102,'assessment','Foreign diagnostic assessment'),(203,101,'plan','Synthetic plan'),(204,101,'assessment',NULL);
INSERT INTO clinical_record_entries VALUES (401,'p_a','{"legacy_diagnosis":"preserved"}');
SQL
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_06_longitudinal_antecedents.sql"
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_07_longitudinal_problems.sql"; done
LON04A_QA_DB="$qa_db" MXMED_LON04A_WRITE_ENABLED=1 php "$root_dir/modules/clinical/qa/lon04a_repository_gate.php"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("lon04a-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_LON04A_WRITE_ENABLED=1 \
  php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port/api/clinical/index.php/patients/p_a/longitudinal/problems"
for _ in {1..40}; do if curl -fsS -o "$qa_root/read.json" -b 'PHPSESSID=lon04a-qa' "$base" 2>/dev/null; then break; fi; sleep 0.1; done
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["knowledge_state"]??null)!=="UNREVIEWED")exit(1);' "$qa_root/read.json"
curl -fsS -o "$qa_root/create.json" -b 'PHPSESSID=lon04a-qa' -H 'Content-Type: application/json' -H 'Idempotency-Key: lon04a-http-create' \
  -d '{"label":"Synthetic HTTP problem","provenance":"EXPLICIT_LONGITUDINAL_ENTRY"}' "$base"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["item"]["status"]??null)!=="ACTIVE")exit(1);' "$qa_root/create.json"
curl -fsS -o "$qa_root/promotion.json" -b 'PHPSESSID=lon04a-qa' -H 'Content-Type: application/json' -H 'Idempotency-Key: lon04a-http-promotion' \
  -d '{"label":"Synthetic promoted assessment","source_encounter_id":101,"source_section_id":201}' "$base/promotions"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["item"]["provenance"]??null)!=="ENCOUNTER_DERIVED_EXPLICIT_PROMOTION")exit(1);' "$qa_root/promotion.json"
http_id="$(php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);echo $r["data"]["item"]["problem_id"];' "$qa_root/create.json")"
curl -fsS -o "$qa_root/resolved.json" -b 'PHPSESSID=lon04a-qa' -H 'Content-Type: application/json' -H 'Idempotency-Key: lon04a-http-resolve' \
  -d '{"expected_version":1,"reason":"Explicit HTTP resolution"}' "$base/$http_id/resolve"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["item"]["status"]??null)!=="RESOLVED")exit(1);' "$qa_root/resolved.json"
curl -fsS -o "$qa_root/reactivated.json" -b 'PHPSESSID=lon04a-qa' -H 'Content-Type: application/json' -H 'Idempotency-Key: lon04a-http-reactivate' \
  -d '{"expected_version":2,"reason":"Explicit HTTP recurrence"}' "$base/$http_id/reactivate"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(($r["data"]["item"]["status"]??null)!=="ACTIVE")exit(1);' "$qa_root/reactivated.json"
curl -fsS -o "$qa_root/history.json" -b 'PHPSESSID=lon04a-qa' "$base/$http_id/history"
php -r '$r=json_decode(file_get_contents($argv[1]),true,512,JSON_THROW_ON_ERROR);if(array_column($r["data"]["events"]??[],"operation")!==["CREATE","RESOLVE","REACTIVATE"])exit(1);' "$qa_root/history.json"
status="$(curl -sS -o "$qa_root/foreign.json" -w '%{http_code}' -b 'PHPSESSID=lon04a-qa' "http://127.0.0.1:$port/api/clinical/index.php/patients/p_b/longitudinal/problems")"
[[ "$status" == 404 ]]
echo 'LON04A_DISPOSABLE_HTTP_GATE=PASS'
