#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="or02a_qa_$(openssl rand -hex 6)"
qa_root="$(mktemp -d /tmp/or02a-qa-XXXXXXXX)"
http_pid=""
cleanup(){ if [[ -n "$http_pid" ]]; then kill "$http_pid" 2>/dev/null || true; wait "$http_pid" 2>/dev/null || true; fi; rm -rf "$qa_root"; mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,doctor_id VARCHAR(64) NOT NULL,patient_id VARCHAR(64) NOT NULL,status VARCHAR(32) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,doctor_id VARCHAR(64),patient_id VARCHAR(64) NOT NULL,appointment_id VARCHAR(64),encounter_dt DATETIME NOT NULL,status VARCHAR(16) NOT NULL DEFAULT 'open',updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_oropen001'),('p_ornone001');
INSERT INTO patients_doctor_links(doctor_id,patient_id,status) VALUES ('d_a','p_oropen001','active'),('d_a','p_ornone001','active');
INSERT INTO clinical_encounters(doctor_id,patient_id,appointment_id,encounter_dt,status) VALUES ('d_a','p_oropen001','ambient-appointment',UTC_TIMESTAMP(),'open');
SQL
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' php -r 'require $argv[1];$pdo=new PDO("mysql:host=localhost;dbname=".getenv("MXMED_DB_NAME"),"root","");mxmed_ensure_clinical_docs_schema($pdo);' "$root_dir/api/_lib/clinical_documents.php"
mysql "$qa_db" -e "ALTER TABLE clinical_documents ADD encounter_ref_id BIGINT UNSIGNED NULL, ADD appointment_id VARCHAR(64) NULL"
mkdir "$qa_root/sessions"
php -d "session.save_path=$qa_root/sessions" -r 'session_id("or02a-owner");session_start();$_SESSION["doctor_id"]="d_a";$_SESSION["user_id"]="u_a";session_write_close();'
port_hex="$(openssl rand -hex 2)"; port=$((18000 + 16#$port_hex % 20000))
MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' MXMED_CLINICAL_M6_COHORT_MODE=off php -d "session.save_path=$qa_root/sessions" -S "127.0.0.1:$port" -t "$root_dir" > "$qa_root/server.log" 2>&1 & http_pid=$!
for _ in {1..40}; do if curl -fsS -o /dev/null "http://127.0.0.1:$port/index.html" 2>/dev/null; then break; fi;sleep 0.1;done
OR02A_QA_DB="$qa_db" OR02A_QA_BASE="http://127.0.0.1:$port" python3 - <<'PY'
import json,os,subprocess,urllib.request,urllib.error
base=os.environ['OR02A_QA_BASE']+'/api/clinical/index.php/doctors/d_a/patients/'
db=os.environ['OR02A_QA_DB']
def sql(query):return subprocess.check_output(['mysql','-N',db,'-e',query],text=True).strip()
pre=sql("SELECT encounter_id,status,appointment_id,updated_at FROM clinical_encounters WHERE patient_id='p_oropen001'")
for patient in ['p_oropen001','p_ornone001']:
 body={'document_type':'lab_order','patient_id':patient,'title':'Orden OR02A QA','event_datetime':'2026-09-30 12:00:00','payload':{'source':'estudios_host_solicitar','requested_studies':['HbA1c']}}
 boundary='or02a-qa-boundary'
 parts=[]
 for key,value in body.items():
  if key=='payload':value=json.dumps(value)
  parts.append(f'--{boundary}\r\nContent-Disposition: form-data; name=\"{key}\"\r\n\r\n{value}\r\n')
 data=(''.join(parts)+f'--{boundary}--\r\n').encode()
 req=urllib.request.Request(base+patient+'/documents',data=data,headers={'Accept':'application/json','Content-Type':'multipart/form-data; boundary='+boundary,'Cookie':'PHPSESSID=or02a-owner'},method='POST')
 try:
  response=urllib.request.urlopen(req)
 except urllib.error.HTTPError as error:
  print('ERROR',patient,error.code,error.read().decode()[:900]);raise
 with response:
  assert response.status==201,(patient,response.status);assert json.load(response)['ok']
rows=sql("SELECT patient_id,COALESCE(encounter_id,'NULL'),COALESCE(CAST(encounter_ref_id AS CHAR),'NULL'),COALESCE(appointment_id,'NULL') FROM clinical_documents ORDER BY id").splitlines()
assert rows==['p_oropen001\tNULL\tNULL\tNULL','p_ornone001\tNULL\tNULL\tNULL'],rows
assert sql("SELECT encounter_id,status,appointment_id,updated_at FROM clinical_encounters WHERE patient_id='p_oropen001'")==pre
print('DISPOSABLE_DB_OPEN_AND_NO_OPEN=PASS; ENCOUNTER_AND_APPOINTMENT_NULL=PASS; OPEN_ENCOUNTER_UNCHANGED=PASS')
PY
