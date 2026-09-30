#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="mxmed_trt04_qa_$$_$RANDOM"
qa_tmp="$(mktemp -d)"
server_pid=''
cleanup() {
  if [[ -n "$server_pid" ]]; then kill "$server_pid" 2>/dev/null || true; wait "$server_pid" 2>/dev/null || true; fi
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  rm -rf "$qa_tmp"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE profiles_doctors (doctor_id VARCHAR(64) PRIMARY KEY,display_name VARCHAR(190)) ENGINE=InnoDB;
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE auth_accounts (account_id VARCHAR(64) PRIMARY KEY,email_address VARCHAR(190) NOT NULL,status VARCHAR(32) NOT NULL) ENGINE=InnoDB;
CREATE TABLE auth_account_memberships (membership_id VARCHAR(64) PRIMARY KEY,account_id VARCHAR(64) NOT NULL,profile_doctor_id VARCHAR(64),role_code VARCHAR(32) NOT NULL,status VARCHAR(32) NOT NULL) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (doctor_id VARCHAR(64) NOT NULL,patient_id VARCHAR(64) NOT NULL,status VARCHAR(32) NOT NULL,PRIMARY KEY (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED PRIMARY KEY,doctor_id VARCHAR(64),patient_id VARCHAR(64)) ENGINE=InnoDB;
CREATE TABLE clinical_documents (id BIGINT UNSIGNED PRIMARY KEY,patient_id VARCHAR(64),document_type VARCHAR(64)) ENGINE=InnoDB;
CREATE TABLE agenda_appointments (appointment_id VARCHAR(64) PRIMARY KEY,doctor_id VARCHAR(64),patient_id VARCHAR(64)) ENGINE=InnoDB;
CREATE TABLE consultorios (doctor_id VARCHAR(64),consultorio_id VARCHAR(64),PRIMARY KEY (doctor_id,consultorio_id)) ENGINE=InnoDB;
CREATE TABLE clinical_idempotency_requests (request_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,operation_type VARCHAR(64) NOT NULL,doctor_id VARCHAR(64) NOT NULL,context_type VARCHAR(16) NOT NULL,context_id VARCHAR(128) NOT NULL,idempotency_key VARCHAR(128) NOT NULL,canonicalization_version SMALLINT UNSIGNED NOT NULL,request_hash CHAR(64) NOT NULL,actor_user_id VARCHAR(64) NOT NULL,observation_id BIGINT UNSIGNED NULL,document_id BIGINT UNSIGNED NULL,encounter_amendment_id BIGINT UNSIGNED NULL,document_revision_id BIGINT UNSIGNED NULL,created_at DATETIME NOT NULL,committed_at DATETIME NULL,UNIQUE KEY uq_clinical_command_idempotency (operation_type,doctor_id,context_type,context_id,idempotency_key),CONSTRAINT chk_idempotency_operation_v1 CHECK (operation_type IN ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT')),CONSTRAINT chk_idempotency_committed_result_v1 CHECK (committed_at IS NULL OR observation_id IS NOT NULL OR document_id IS NOT NULL OR encounter_amendment_id IS NOT NULL OR document_revision_id IS NOT NULL)) ENGINE=InnoDB;
INSERT INTO profiles_doctors VALUES ('d1','Doctora Uno'),('d2','Doctor Dos');
INSERT INTO patients_patients VALUES ('p1'),('p2');
INSERT INTO auth_accounts VALUES ('actor','actor@example.test','active'),('provider','provider@example.test','active'),('performer','performer@example.test','active'),('other','other@example.test','active'),('outsider','outsider@example.test','active');
INSERT INTO auth_account_memberships VALUES ('m1','actor','d1','owner','active'),('m2','provider','d1','collaborator','active'),('m3','performer','d1','collaborator','active'),('m4','other','d1','collaborator','active'),('m5','outsider','d2','collaborator','active');
INSERT INTO patients_doctor_links VALUES ('d1','p1','active'),('d2','p1','active');
INSERT INTO clinical_encounters VALUES (1,'d1','p1'),(2,'d2','p1'),(3,'d1','p2');
INSERT INTO clinical_documents VALUES (1,'p1','procedure');
INSERT INTO agenda_appointments VALUES ('a1','d1','p1'),('a2','d2','p1');
INSERT INTO consultorios VALUES ('d1','c1'),('d2','c2');
INSERT INTO clinical_idempotency_requests (operation_type,doctor_id,context_type,context_id,idempotency_key,canonicalization_version,request_hash,actor_user_id,observation_id,created_at,committed_at) VALUES ('CREATE_OBSERVATION','d1','PATIENT','p1','legacy',1,REPEAT('a',64),'actor',1,UTC_TIMESTAMP(),UTC_TIMESTAMP());
SQL
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_13_treatment_authority.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_13_treatment_authority.sql"
[[ "$(mysql --batch --skip-column-names "$qa_db" -e 'SELECT COUNT(*) FROM clinical_treatment_sessions')" == '0' ]]
mysql "$qa_db" <<'SQL'
INSERT INTO clinical_performer_authorizations (doctor_id,account_id,capability,clinical_role_label,granted_by_account_id) VALUES ('d1','provider','TREATMENT_RESPONSIBLE_PROVIDER','Fisioterapeuta','actor'),('d1','performer','TREATMENT_PERFORMER','Fisioterapeuta','actor'),('d2','outsider','TREATMENT_PERFORMER','Enfermería','outsider');
SQL
TRT04_QA_DB="$qa_db" TRT04_QA_ROOT="$root_dir" php "$root_dir/modules/clinical/qa/trt04_service_gate.php"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_30_13_treatment_authority.sql"
mysql "$qa_db" -e "INSERT INTO clinical_performer_authorizations (doctor_id,account_id,capability,clinical_role_label,granted_by_account_id) VALUES ('d1','performer','TREATMENT_PERFORMER','Fisioterapeuta','actor')"
port="$(php -r '$s=stream_socket_server("tcp://127.0.0.1:0",$n,$e);$a=stream_socket_get_name($s,false);echo substr($a,strrpos($a,":")+1);fclose($s);')"
session_id="trt04qa$$-$RANDOM"
php -d "session.save_path=$qa_tmp" -r 'session_id($argv[1]);session_start();$_SESSION["doctor_id"]="d1";$_SESSION["user_id"]="actor";session_write_close();' "$session_id"
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' php -d "session.save_path=$qa_tmp" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_tmp/server.log" 2>&1 &
server_pid=$!
url="http://127.0.0.1:$port/api/clinical/index.php?route=patients/p1/treatments/plans"
for _ in {1..30}; do
  if [[ "$(curl -s -o /dev/null -w '%{http_code}' "$url" || true)" == '401' ]]; then break; fi
  sleep 0.1
done
unauth="$(curl -s -o /dev/null -w '%{http_code}' "$url")"
[[ "$unauth" == '401' ]] || { cat "$qa_tmp/server.log"; exit 1; }
auth="$(curl -sS -b "PHPSESSID=$session_id" "$url")"
TRT04_RESPONSE="$auth" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);if(($j["ok"]??false)!==true||count($j["data"]??[])<2)exit(1);'
created="$(curl -sS -b "PHPSESSID=$session_id" -H 'Content-Type: application/json' -H 'Idempotency-Key: trt04-http-plan' -d '{"title":"Plan HTTP","responsible_provider_account_id":"provider"}' "$url")"
TRT04_RESPONSE="$created" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);if(($j["ok"]??false)!==true||($j["data"]["record"]["title"]??null)!=="Plan HTTP")exit(1);'
replayed="$(curl -sS -b "PHPSESSID=$session_id" -H 'Content-Type: application/json' -H 'Idempotency-Key: trt04-http-plan' -d '{"title":"Plan HTTP","responsible_provider_account_id":"provider"}' "$url")"
TRT04_RESPONSE="$replayed" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);if(($j["data"]["idempotency_replay"]??false)!==true)exit(1);'
session_url="http://127.0.0.1:$port/api/clinical/index.php?route=patients/p1/treatments/sessions"
draft="$(curl -sS -b "PHPSESSID=$session_id" -H 'Content-Type: application/json' -H 'Idempotency-Key: trt04-http-draft' -d '{"title":"Sesión HTTP","encounter_scope":"STANDALONE","performed_by_account_id":"performer"}' "$session_url")"
session_row_id="$(TRT04_RESPONSE="$draft" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);if(($j["ok"]??false)!==true||($j["data"]["record"]["status"]??null)!=="DRAFT")exit(1);echo $j["data"]["record"]["session_id"];')"
completed="$(curl -sS -b "PHPSESSID=$session_id" -H 'Content-Type: application/json' -H 'Idempotency-Key: trt04-http-complete' -d '{"expected_version":1,"procedure_items":[{"sequence":1,"type_key":"therapy","title":"Terapia"}],"performed_local":"2026-09-29 14:30:00","performed_timezone":"America/Mexico_City","performed_utc_offset_minutes":-360}' "${session_url}/${session_row_id}/complete")"
TRT04_RESPONSE="$completed" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);if(($j["ok"]??false)!==true||($j["data"]["record"]["status"]??null)!=="COMPLETED")exit(1);'
history="$(curl -sS -b "PHPSESSID=$session_id" "$session_url/standalone-history")"
TRT04_RESPONSE="$history" TRT04_SESSION_ROW_ID="$session_row_id" php -r '$j=json_decode(getenv("TRT04_RESPONSE"),true);$id=getenv("TRT04_SESSION_ROW_ID");$found=false;foreach($j["data"]??[] as $row){if((string)$row["session_id"]===$id)$found=true;}if(!$found)exit(1);'
echo 'TRT04_HTTP_QA_PASS=7'
