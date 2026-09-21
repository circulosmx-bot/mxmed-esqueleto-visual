#!/usr/bin/env bash
set -euo pipefail
root_dir="$(cd "$(dirname "$0")/../../.." && pwd)"
qa_db="lon05a_qa_$(openssl rand -hex 6)"
[[ "$qa_db" =~ ^lon05a_qa_[0-9a-f]{12}$ ]]
cleanup() { mysql -e "DROP DATABASE IF EXISTS \`$qa_db\`"; }
trap cleanup EXIT
mysql -e "CREATE DATABASE \`$qa_db\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
mysql "$qa_db" <<'SQL'
CREATE TABLE patients_patients (patient_id VARCHAR(64) PRIMARY KEY) ENGINE=InnoDB;
CREATE TABLE patients_doctor_links (link_id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL, status VARCHAR(32) NOT NULL, UNIQUE KEY uq_pair (doctor_id,patient_id)) ENGINE=InnoDB;
CREATE TABLE clinical_encounters (encounter_id BIGINT UNSIGNED PRIMARY KEY, doctor_id VARCHAR(64) NOT NULL, patient_id VARCHAR(64) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_encounter_sections (section_id BIGINT UNSIGNED PRIMARY KEY, encounter_id BIGINT UNSIGNED NOT NULL, section_type VARCHAR(40) NOT NULL, narrative_text TEXT DEFAULT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_documents (id BIGINT UNSIGNED PRIMARY KEY, encounter_ref_id BIGINT UNSIGNED, patient_id VARCHAR(64) NOT NULL, document_type VARCHAR(64) NOT NULL, status VARCHAR(20) NOT NULL) ENGINE=InnoDB;
CREATE TABLE clinical_record_entries (entry_id BIGINT UNSIGNED PRIMARY KEY, patient_id VARCHAR(64) NOT NULL, payload_json JSON) ENGINE=InnoDB;
INSERT INTO patients_patients VALUES ('p_a'),('p_b');
INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('d_a','p_a','active'),('d_b','p_b','active');
INSERT INTO clinical_encounters VALUES (101,'d_a','p_a'),(102,'d_b','p_b');
INSERT INTO clinical_encounter_sections VALUES (201,101,'assessment','Synthetic assessment');
INSERT INTO clinical_documents VALUES (301,101,'p_a','prescription','generated'),(302,102,'p_b','prescription','generated'),(303,101,'p_a','note','generated');
INSERT INTO clinical_record_entries VALUES (401,'p_a','{"legacy_medication":"preserved"}');
SQL
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_06_longitudinal_antecedents.sql"
mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_07_longitudinal_problems.sql"
for _ in 1 2; do mysql "$qa_db" < "$root_dir/modules/clinical/db/migrations/2026_09_21_08_longitudinal_medications.sql"; done
LON05A_QA_DB="$qa_db" MXMED_LON05A_WRITE_ENABLED=1 php "$root_dir/modules/clinical/qa/lon05a_repository_gate.php"
echo 'LON05A_DISPOSABLE_MIGRATION_QA=PASS'
