#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="vitalref_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^vitalref_qa_[0-9a-f]{12}$ ]]
qa_root="$(mktemp -d "${TMPDIR:-/tmp}/vitalref-qa-XXXXXXXX")"
http_pid=""
cleanup(){
  if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi
  rm -rf "$qa_root"
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
}
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY, birthdate DATE NULL, sex VARCHAR(32) NULL);
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64), patient_id VARCHAR(64), status VARCHAR(32));
INSERT INTO patients_patients VALUES ('adult','1990-01-01','unspecified'),('child',DATE_SUB(CURDATE(),INTERVAL 8 YEAR),'unspecified'),('teen',DATE_SUB(DATE_SUB(CURDATE(),INTERVAL 13 YEAR),INTERVAL 1 MONTH),'unspecified'),('missing',NULL,'unspecified'),('future',DATE_ADD(CURDATE(),INTERVAL 1 YEAR),'unspecified'),('other','1990-01-01','unspecified'),('inactive','1990-01-01','unspecified');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','adult','active'),('d_a','child','active'),('d_a','teen','active'),('d_a','missing','active'),('d_a','future','active'),('d_b','other','active'),('d_a','inactive','inactive');
SQL
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("vitalref-qa");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
port_hex="$(openssl rand -hex 2)";port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
 php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" >"$qa_root/server.log" 2>&1 &
http_pid=$!
base="http://127.0.0.1:$port"
for _ in {1..40}; do if curl -fsS -o /dev/null "$base/modules/clinical/README.md" 2>/dev/null; then break; fi;sleep 0.1;done
VITALREF_QA_BASE="$base" VITALREF_QA_DB="$qa_db" python3 "$root_dir/modules/clinical/qa/vitalref01_http_gate.py"
