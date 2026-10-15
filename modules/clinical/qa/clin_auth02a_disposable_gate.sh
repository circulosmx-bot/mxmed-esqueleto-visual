#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="clin_auth02a_qa_$(openssl rand -hex 6)"
qa_dir="$(mktemp -d "${TMPDIR:-/tmp}/clin-auth02a-qa-XXXXXXXX")"
cleanup() {
  mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"
  find "$qa_dir" -type f -delete
  rmdir "$qa_dir"
}
trap cleanup EXIT

mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (
  patient_id VARCHAR(64) PRIMARY KEY,
  status VARCHAR(20) NOT NULL DEFAULT 'active'
) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (
  link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE auth_account_memberships (
  membership_id VARCHAR(64) PRIMARY KEY,
  account_id VARCHAR(64) NOT NULL,
  profile_doctor_id VARCHAR(64) NULL,
  role_code VARCHAR(32) NOT NULL,
  scope_code VARCHAR(32) NOT NULL,
  status VARCHAR(32) NOT NULL
) ENGINE=InnoDB;
CREATE TABLE clinical_record_entries (
  entry_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  entry_date DATETIME NULL,
  note_type VARCHAR(64) NOT NULL,
  status VARCHAR(32) NOT NULL,
  payload_json JSON NULL,
  subjective TEXT NULL,
  objective TEXT NULL,
  assessment TEXT NULL,
  plan TEXT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL
) ENGINE=InnoDB;
CREATE TABLE clinical_documents (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  document_uuid VARCHAR(64) NOT NULL UNIQUE,
  patient_id VARCHAR(64) NOT NULL,
  created_by_user_id VARCHAR(64) NOT NULL,
  encounter_id VARCHAR(64) NULL,
  encounter_ref_id BIGINT UNSIGNED NULL,
  appointment_id VARCHAR(64) NULL
) ENGINE=InnoDB;
CREATE TABLE clinical_cases (
  case_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  patient_id VARCHAR(64) NOT NULL,
  title VARCHAR(180) NOT NULL,
  status VARCHAR(16) NOT NULL,
  created_at DATETIME NOT NULL,
  updated_at DATETIME NOT NULL,
  KEY idx_clinical_cases_patient_status (patient_id,status),
  CONSTRAINT fk_clinical_cases_patient FOREIGN KEY (patient_id)
    REFERENCES patients_patients(patient_id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;
CREATE TABLE clinical_case_items (
  case_id BIGINT UNSIGNED NOT NULL,
  item_type VARCHAR(24) NOT NULL,
  item_ref VARCHAR(190) NOT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY(case_id,item_type,item_ref),
  CONSTRAINT fk_clinical_case_items_case FOREIGN KEY(case_id)
    REFERENCES clinical_cases(case_id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;
INSERT INTO patients_patients(patient_id) VALUES ('p_authorized'),('p_foreign');
INSERT INTO patients_doctor_links(doctor_id,patient_id,status)
  VALUES ('d_a','p_authorized','active'),('d_b','p_foreign','active');
INSERT INTO auth_account_memberships(membership_id,account_id,profile_doctor_id,role_code,scope_code,status)
  VALUES ('m_a','u_a','d_a','collaborator','profile','active'),
         ('m_b','u_b','d_b','collaborator','profile','active');
INSERT INTO clinical_documents(document_uuid,patient_id,created_by_user_id)
  VALUES ('synthetic-document','p_authorized','u_a'),('foreign-document','p_foreign','u_b');
INSERT INTO clinical_record_entries(patient_id,entry_date,note_type,status,payload_json,created_at,updated_at)
  VALUES ('p_authorized',NOW(),'historia_clinica','draft','{"safe":"synthetic"}',NOW(),NOW()),
         ('p_authorized',NOW(),'exploracion_fisica','draft','{"safe":"synthetic"}',NOW(),NOW());
INSERT INTO clinical_cases(patient_id,title,status,created_at,updated_at)
  VALUES ('p_authorized','Synthetic authorized case','active',NOW(),NOW()),
         ('p_foreign','Synthetic foreign case','active',NOW(),NOW());
INSERT INTO clinical_case_items(case_id,item_type,item_ref,created_at)
  VALUES (1,'document','synthetic-document',NOW()),(2,'document','foreign-document',NOW());
SQL

request() {
  MXMED_DB_HOST=localhost MXMED_DB_NAME="$qa_db" MXMED_DB_USER=root MXMED_DB_PASS='' \
  MXMED_CLINICAL_ENCOUNTER_INTEGRITY_V1=1 \
  php "$root_dir/modules/clinical/qa/clin_auth02a_cli_request.php" "$1" "$2" "$3" "${4:-}"
}
assert_status() {
  local expected="$1" name="$2" response="$3"
  RESPONSE="$response" EXPECTED="$expected" NAME="$name" php -r '
    $x=json_decode((string)getenv("RESPONSE"),true,512,JSON_THROW_ON_ERROR);
    if((int)($x["status"]??0)!==(int)getenv("EXPECTED")) throw new RuntimeException(getenv("NAME"));
    echo getenv("NAME")."=PASS\n";'
}

assert_status 401 AUTH02A_01 "$(request GET /patients/p_authorized/history anonymous)"
assert_status 401 AUTH02A_02 "$(request PUT /patients/p_authorized/history anonymous)"
assert_status 401 AUTH02A_03 "$(request GET /patients/p_authorized/physical-exam anonymous)"
assert_status 401 AUTH02A_04 "$(request PUT /patients/p_authorized/physical-exam anonymous)"
assert_status 401 AUTH02A_05_CASE_LIST "$(request GET /patients/p_authorized/cases anonymous)"
assert_status 401 AUTH02A_05_CASE_ACTIVE "$(request GET /patients/p_authorized/cases/active anonymous)"
assert_status 401 AUTH02A_05_CASE_PATCH "$(request PATCH /cases/1 anonymous)"
assert_status 401 AUTH02A_05_CASE_ACTIVATE "$(request POST /cases/1/activate anonymous)"
assert_status 401 AUTH02A_05_CASE_ITEMS "$(request GET /cases/1/items anonymous)"
assert_status 404 AUTH02A_06 "$(request GET /patients/p_foreign/history authenticated)"
assert_status 404 AUTH02A_07 "$(request GET /patients/p_foreign/cases authenticated)"
assert_status 200 AUTH02A_08_HISTORY "$(request GET /patients/p_authorized/history authenticated)"
assert_status 200 AUTH02A_08_PHYSICAL "$(request GET /patients/p_authorized/physical-exam authenticated)"
assert_status 200 AUTH02A_08_CASE_ACTIVE "$(request GET /patients/p_authorized/cases/active authenticated)"
assert_status 200 AUTH02A_08_CASE_ITEMS "$(request GET /cases/1/items authenticated)"
assert_status 403 AUTH02A_ORIGIN_HISTORY "$(request PUT /patients/p_authorized/history authenticated https://evil.example)"
assert_status 403 AUTH02A_ORIGIN_CASE "$(request POST /patients/p_authorized/cases authenticated https://evil.example)"
assert_status 403 AUTH02A_ORIGIN_DOCUMENT "$(request POST /doctors/d_a/patients/p_authorized/documents authenticated https://evil.example)"
assert_status 403 AUTH02A_ORIGIN_PRESCRIPTION "$(request POST /doctors/d_a/patients/p_authorized/prescriptions authenticated https://evil.example)"
assert_status 403 AUTH02A_ORIGIN_UNTRUSTED_LOCAL "$(request PUT /patients/p_authorized/history authenticated http://localhost:6666)"
assert_status 400 AUTH02A_ORIGIN_CANONICAL_LOCAL "$(request PUT /patients/p_authorized/history authenticated https://localhost:8443)"
assert_status 403 AUTH02A_QR_CERTIFICADO "$(request POST /certificado-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_NOTA "$(request POST /nota-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_FREE_DOCUMENT "$(request POST /free-document-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_PRESCRIPTION "$(request POST /prescription-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_ALTA "$(request POST /alta-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_INFORME "$(request POST /informe-qr-sessions authenticated)"
assert_status 403 AUTH02A_QR_INTERCONSULTA "$(request POST /interconsulta-qr-sessions authenticated)"
qr_table_count="$(mysql -N "$qa_db" -e "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME LIKE 'clinical%qr_sessions'")"
[[ "$qr_table_count" == "0" ]]
echo 'AUTH02A_QR_DENIAL_ZERO_SCHEMA_TOUCHES=PASS'

echo 'CLIN_AUTH02A_DISPOSABLE_HTTP_GATE=PASS'
