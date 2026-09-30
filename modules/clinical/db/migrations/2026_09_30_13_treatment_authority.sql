-- TRT04. Forward-only canonical treatment authority. No legacy backfill.
CREATE TABLE IF NOT EXISTS clinical_performer_authorizations (
  authorization_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  doctor_id VARCHAR(64) NOT NULL,
  account_id VARCHAR(64) NOT NULL,
  capability VARCHAR(48) NOT NULL,
  clinical_role_label VARCHAR(80) NOT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'ACTIVE',
  granted_by_account_id VARCHAR(64) NOT NULL,
  granted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revoked_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  active_grant_key VARCHAR(180) GENERATED ALWAYS AS
    (CASE WHEN status='ACTIVE' THEN CONCAT(doctor_id,'|',account_id,'|',capability) ELSE NULL END) STORED,
  UNIQUE KEY uq_treatment_active_grant (active_grant_key),
  KEY idx_treatment_grant_lookup (doctor_id,account_id,capability,status),
  CONSTRAINT fk_treatment_grant_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_grant_account FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_grant_actor FOREIGN KEY (granted_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_treatment_grant_capability CHECK (capability IN ('TREATMENT_PERFORMER','TREATMENT_RESPONSIBLE_PROVIDER')),
  CONSTRAINT ck_treatment_grant_role CHECK (CHAR_LENGTH(TRIM(clinical_role_label))>0),
  CONSTRAINT ck_treatment_grant_status CHECK ((status='ACTIVE' AND revoked_at IS NULL) OR (status='REVOKED' AND revoked_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_treatment_plans (
  plan_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  plan_uuid CHAR(36) NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  responsible_provider_account_id VARCHAR(64) NOT NULL,
  responsible_provider_name_snapshot VARCHAR(190) NOT NULL,
  responsible_provider_role_snapshot VARCHAR(80) NOT NULL,
  title VARCHAR(255) NOT NULL,
  treatment_type VARCHAR(100) NULL,
  goals TEXT NULL,
  source_encounter_id BIGINT UNSIGNED NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'PLANNED',
  planned_start_date DATE NULL,
  started_at DATETIME NULL,
  completed_at DATETIME NULL,
  canceled_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by_account_id VARCHAR(64) NOT NULL,
  updated_by_account_id VARCHAR(64) NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  UNIQUE KEY uq_treatment_plan_uuid (plan_uuid),
  KEY idx_treatment_plan_scope_status (doctor_id,patient_id,status),
  KEY idx_treatment_plan_patient_provider (patient_id,responsible_provider_account_id),
  CONSTRAINT fk_treatment_plan_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_provider FOREIGN KEY (responsible_provider_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_creator FOREIGN KEY (created_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_updater FOREIGN KEY (updated_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_encounter FOREIGN KEY (source_encounter_id) REFERENCES clinical_encounters(encounter_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_treatment_plan_status CHECK (status IN ('PLANNED','ACTIVE','PAUSED','COMPLETED','CANCELED')),
  CONSTRAINT ck_treatment_plan_version CHECK (row_version>=1),
  CONSTRAINT ck_treatment_plan_title CHECK (CHAR_LENGTH(TRIM(title))>0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_treatment_plan_audit_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  plan_id BIGINT UNSIGNED NOT NULL,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  operation VARCHAR(24) NOT NULL,
  previous_status VARCHAR(16) NULL,
  new_status VARCHAR(16) NOT NULL,
  plan_row_version BIGINT UNSIGNED NOT NULL,
  reason VARCHAR(1000) NULL,
  idempotency_request_id BIGINT UNSIGNED NULL,
  occurred_at DATETIME NOT NULL,
  UNIQUE KEY uq_treatment_plan_audit_version (plan_id,plan_row_version),
  KEY idx_treatment_plan_audit_scope (doctor_id,patient_id,plan_id,event_id),
  CONSTRAINT fk_treatment_plan_audit_plan FOREIGN KEY (plan_id) REFERENCES clinical_treatment_plans(plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_plan_audit_actor FOREIGN KEY (actor_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_treatment_plan_audit_operation CHECK (operation IN ('CREATE','ACTIVATE','PAUSE','REACTIVATE','COMPLETE','CANCEL'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS clinical_treatment_sessions (
  session_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  session_uuid CHAR(36) NOT NULL,
  version_number INT UNSIGNED NOT NULL DEFAULT 1,
  lineage_root_id BIGINT UNSIGNED NULL,
  replaces_session_id BIGINT UNSIGNED NULL,
  doctor_id VARCHAR(64) NOT NULL,
  patient_id VARCHAR(64) NOT NULL,
  performed_by_account_id VARCHAR(64) NOT NULL,
  performed_by_name_snapshot VARCHAR(190) NOT NULL,
  performed_by_role_snapshot VARCHAR(80) NOT NULL,
  treatment_plan_id BIGINT UNSIGNED NULL,
  encounter_ref_id BIGINT UNSIGNED NULL,
  encounter_scope VARCHAR(16) NOT NULL,
  appointment_id VARCHAR(64) NULL,
  consultorio_id VARCHAR(64) NULL,
  place_label_snapshot VARCHAR(190) NULL,
  performed_at DATETIME NULL,
  performed_timezone VARCHAR(100) NULL,
  performed_utc_offset_minutes SMALLINT NULL,
  title VARCHAR(255) NOT NULL,
  procedure_items JSON NOT NULL,
  note TEXT NULL,
  outcome TEXT NULL,
  complications TEXT NULL,
  status VARCHAR(16) NOT NULL DEFAULT 'DRAFT',
  completed_at DATETIME NULL,
  voided_at DATETIME NULL,
  void_reason VARCHAR(1000) NULL,
  voided_by_account_id VARCHAR(64) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  created_by_account_id VARCHAR(64) NOT NULL,
  updated_by_account_id VARCHAR(64) NOT NULL,
  row_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
  correction_reason VARCHAR(1000) NULL,
  correction_actor_account_id VARCHAR(64) NULL,
  correction_at DATETIME NULL,
  UNIQUE KEY uq_treatment_session_version (session_uuid,version_number),
  UNIQUE KEY uq_treatment_session_successor (replaces_session_id),
  KEY idx_treatment_session_scope_time (doctor_id,patient_id,performed_at,session_id),
  KEY idx_treatment_session_plan_time (treatment_plan_id,performed_at),
  KEY idx_treatment_session_encounter (encounter_ref_id),
  KEY idx_treatment_session_appointment (appointment_id),
  KEY idx_treatment_session_lineage (lineage_root_id,version_number),
  CONSTRAINT fk_treatment_session_doctor FOREIGN KEY (doctor_id) REFERENCES profiles_doctors(doctor_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_patient FOREIGN KEY (patient_id) REFERENCES patients_patients(patient_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_performer FOREIGN KEY (performed_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_plan FOREIGN KEY (treatment_plan_id) REFERENCES clinical_treatment_plans(plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_encounter FOREIGN KEY (encounter_ref_id) REFERENCES clinical_encounters(encounter_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_root FOREIGN KEY (lineage_root_id) REFERENCES clinical_treatment_sessions(session_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_previous FOREIGN KEY (replaces_session_id) REFERENCES clinical_treatment_sessions(session_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_void_actor FOREIGN KEY (voided_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_creator FOREIGN KEY (created_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_updater FOREIGN KEY (updated_by_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT fk_treatment_session_correction_actor FOREIGN KEY (correction_actor_account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
  CONSTRAINT ck_treatment_session_scope CHECK ((encounter_scope='ENCOUNTER' AND encounter_ref_id IS NOT NULL) OR (encounter_scope='STANDALONE' AND encounter_ref_id IS NULL)),
  CONSTRAINT ck_treatment_session_status CHECK (status IN ('DRAFT','COMPLETED','VOIDED')),
  CONSTRAINT ck_treatment_session_version CHECK (version_number>=1 AND row_version>=1),
  CONSTRAINT ck_treatment_session_items CHECK (JSON_TYPE(procedure_items)='ARRAY'),
  CONSTRAINT ck_treatment_session_title CHECK (CHAR_LENGTH(TRIM(title))>0),
  CONSTRAINT ck_treatment_session_completed CHECK (status<>'COMPLETED' OR (completed_at IS NOT NULL AND performed_at IS NOT NULL AND performed_timezone IS NOT NULL AND performed_utc_offset_minutes IS NOT NULL AND JSON_LENGTH(procedure_items)>0)),
  CONSTRAINT ck_treatment_session_void CHECK (status<>'VOIDED' OR (voided_at IS NOT NULL AND voided_by_account_id IS NOT NULL AND CHAR_LENGTH(TRIM(void_reason))>0)),
  CONSTRAINT ck_treatment_session_lineage CHECK ((version_number=1 AND replaces_session_id IS NULL) OR (version_number>1 AND replaces_session_id IS NOT NULL AND lineage_root_id IS NOT NULL AND correction_actor_account_id IS NOT NULL AND correction_at IS NOT NULL AND CHAR_LENGTH(TRIM(correction_reason))>0))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Existing clinical command ledger: extend in place, preserving old rows/operations.
DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_trt04_extend_idempotency$$
CREATE PROCEDURE mxmed_trt04_extend_idempotency()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND COLUMN_NAME='treatment_plan_id') THEN
    ALTER TABLE clinical_idempotency_requests ADD COLUMN treatment_plan_id BIGINT UNSIGNED NULL, ADD COLUMN treatment_plan_event_id BIGINT UNSIGNED NULL, ADD COLUMN treatment_session_id BIGINT UNSIGNED NULL, ADD COLUMN treatment_result_json JSON NULL;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_committed_result_v1') THEN
    ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_committed_result_v1;
  END IF;
  IF EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_operation_v1') THEN
    ALTER TABLE clinical_idempotency_requests DROP CONSTRAINT chk_idempotency_operation_v1;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_operation_trt04') THEN
    ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_operation_trt04 CHECK (operation_type IN ('CREATE_OBSERVATION','CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT','CREATE_ENCOUNTER_AMENDMENT','CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT','CREATE_TREATMENT_PLAN','TRANSITION_TREATMENT_PLAN','CREATE_TREATMENT_SESSION','COMPLETE_TREATMENT_SESSION','VOID_TREATMENT_SESSION','CORRECT_TREATMENT_SESSION'));
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='chk_idempotency_committed_result_trt04') THEN
    ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT chk_idempotency_committed_result_trt04 CHECK (
      (committed_at IS NULL AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL AND treatment_result_json IS NULL)
      OR (committed_at IS NOT NULL AND (
        (operation_type='CREATE_OBSERVATION' AND observation_id IS NOT NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
        OR (operation_type IN ('CREATE_ENCOUNTER_DOCUMENT','CREATE_POST_ENCOUNTER_RESULT') AND observation_id IS NULL AND document_id IS NOT NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
        OR (operation_type='CREATE_ENCOUNTER_AMENDMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NOT NULL AND document_revision_id IS NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
        OR (operation_type='CREATE_DOCUMENT_AMENDMENT_OR_REPLACEMENT' AND observation_id IS NULL AND document_id IS NULL AND encounter_amendment_id IS NULL AND document_revision_id IS NOT NULL AND treatment_plan_id IS NULL AND treatment_plan_event_id IS NULL AND treatment_session_id IS NULL)
        OR (operation_type='CREATE_TREATMENT_PLAN' AND treatment_plan_id IS NOT NULL AND treatment_result_json IS NOT NULL)
        OR (operation_type='TRANSITION_TREATMENT_PLAN' AND treatment_plan_event_id IS NOT NULL AND treatment_result_json IS NOT NULL)
        OR (operation_type IN ('CREATE_TREATMENT_SESSION','COMPLETE_TREATMENT_SESSION','VOID_TREATMENT_SESSION','CORRECT_TREATMENT_SESSION') AND treatment_session_id IS NOT NULL AND treatment_result_json IS NOT NULL)
      )));
  END IF;
END$$
CALL mxmed_trt04_extend_idempotency()$$
DROP PROCEDURE mxmed_trt04_extend_idempotency$$
DELIMITER ;

DELIMITER $$
DROP PROCEDURE IF EXISTS mxmed_trt04_result_fks$$
CREATE PROCEDURE mxmed_trt04_result_fks()
BEGIN
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_treatment_plan') THEN
    ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_treatment_plan FOREIGN KEY (treatment_plan_id) REFERENCES clinical_treatment_plans(plan_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_treatment_event') THEN
    ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_treatment_event FOREIGN KEY (treatment_plan_event_id) REFERENCES clinical_treatment_plan_audit_events(event_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_idempotency_requests' AND CONSTRAINT_NAME='fk_idempotency_treatment_session') THEN
    ALTER TABLE clinical_idempotency_requests ADD CONSTRAINT fk_idempotency_treatment_session FOREIGN KEY (treatment_session_id) REFERENCES clinical_treatment_sessions(session_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_treatment_plan_audit_events' AND CONSTRAINT_NAME='fk_treatment_plan_audit_request') THEN
    ALTER TABLE clinical_treatment_plan_audit_events ADD CONSTRAINT fk_treatment_plan_audit_request FOREIGN KEY (idempotency_request_id) REFERENCES clinical_idempotency_requests(request_id) ON DELETE RESTRICT ON UPDATE RESTRICT;
  END IF;
END$$
CALL mxmed_trt04_result_fks()$$
DROP PROCEDURE mxmed_trt04_result_fks$$
DELIMITER ;

-- Immutability of plan audit and terminal session clinical content is defended in SQL.
DELIMITER $$
DROP TRIGGER IF EXISTS trg_treatment_grant_no_delete$$
CREATE TRIGGER trg_treatment_grant_no_delete BEFORE DELETE ON clinical_performer_authorizations FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TREATMENT_GRANT_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_treatment_grant_revoke_only$$
CREATE TRIGGER trg_treatment_grant_revoke_only BEFORE UPDATE ON clinical_performer_authorizations FOR EACH ROW
BEGIN
  IF OLD.status<>'ACTIVE' OR NEW.status<>'REVOKED' OR NEW.revoked_at IS NULL
    OR NEW.doctor_id<>OLD.doctor_id OR NEW.account_id<>OLD.account_id
    OR NEW.capability<>OLD.capability OR NEW.clinical_role_label<>OLD.clinical_role_label OR NEW.granted_by_account_id<>OLD.granted_by_account_id
    OR NEW.granted_at<>OLD.granted_at OR NEW.created_at<>OLD.created_at THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TREATMENT_GRANT_REVOKE_ONLY';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_treatment_plan_no_delete$$
CREATE TRIGGER trg_treatment_plan_no_delete BEFORE DELETE ON clinical_treatment_plans FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TREATMENT_PLAN_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_treatment_plan_terminal_guard$$
CREATE TRIGGER trg_treatment_plan_terminal_guard BEFORE UPDATE ON clinical_treatment_plans FOR EACH ROW
BEGIN
  IF OLD.status IN ('COMPLETED','CANCELED') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TERMINAL_TREATMENT_PLAN_IMMUTABLE';
  END IF;
END$$
DROP TRIGGER IF EXISTS trg_treatment_plan_audit_no_update$$
CREATE TRIGGER trg_treatment_plan_audit_no_update BEFORE UPDATE ON clinical_treatment_plan_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_TREATMENT_PLAN_AUDIT'$$
DROP TRIGGER IF EXISTS trg_treatment_plan_audit_no_delete$$
CREATE TRIGGER trg_treatment_plan_audit_no_delete BEFORE DELETE ON clinical_treatment_plan_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='IMMUTABLE_TREATMENT_PLAN_AUDIT'$$
DROP TRIGGER IF EXISTS trg_treatment_session_no_delete$$
CREATE TRIGGER trg_treatment_session_no_delete BEFORE DELETE ON clinical_treatment_sessions FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TREATMENT_SESSION_DELETE_FORBIDDEN'$$
DROP TRIGGER IF EXISTS trg_treatment_session_terminal_guard$$
CREATE TRIGGER trg_treatment_session_terminal_guard BEFORE UPDATE ON clinical_treatment_sessions FOR EACH ROW
BEGIN
  IF OLD.status='VOIDED' OR ((OLD.status='COMPLETED' OR (OLD.status='DRAFT' AND NEW.status='VOIDED')) AND (NEW.status<>'VOIDED'
    OR NOT (NEW.session_uuid <=> OLD.session_uuid)
    OR NOT (NEW.version_number <=> OLD.version_number)
    OR NOT (NEW.lineage_root_id <=> OLD.lineage_root_id)
    OR NOT (NEW.replaces_session_id <=> OLD.replaces_session_id)
    OR NOT (NEW.doctor_id <=> OLD.doctor_id)
    OR NOT (NEW.patient_id <=> OLD.patient_id)
    OR NOT (NEW.performed_by_account_id <=> OLD.performed_by_account_id)
    OR NOT (NEW.performed_by_name_snapshot <=> OLD.performed_by_name_snapshot)
    OR NOT (NEW.performed_by_role_snapshot <=> OLD.performed_by_role_snapshot)
    OR NOT (NEW.treatment_plan_id <=> OLD.treatment_plan_id)
    OR NOT (NEW.encounter_ref_id <=> OLD.encounter_ref_id)
    OR NOT (NEW.encounter_scope <=> OLD.encounter_scope)
    OR NOT (NEW.appointment_id <=> OLD.appointment_id)
    OR NOT (NEW.consultorio_id <=> OLD.consultorio_id)
    OR NOT (NEW.place_label_snapshot <=> OLD.place_label_snapshot)
    OR NOT (NEW.performed_at <=> OLD.performed_at)
    OR NOT (NEW.performed_timezone <=> OLD.performed_timezone)
    OR NOT (NEW.performed_utc_offset_minutes <=> OLD.performed_utc_offset_minutes)
    OR NOT (NEW.title <=> OLD.title)
    OR NOT (NEW.procedure_items <=> OLD.procedure_items)
    OR NOT (NEW.note <=> OLD.note)
    OR NOT (NEW.outcome <=> OLD.outcome)
    OR NOT (NEW.complications <=> OLD.complications)
    OR NOT (NEW.completed_at <=> OLD.completed_at)
    OR NOT (NEW.created_at <=> OLD.created_at)
    OR NOT (NEW.created_by_account_id <=> OLD.created_by_account_id)
    OR NOT (NEW.correction_reason <=> OLD.correction_reason)
    OR NOT (NEW.correction_actor_account_id <=> OLD.correction_actor_account_id)
    OR NOT (NEW.correction_at <=> OLD.correction_at))) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='TERMINAL_TREATMENT_SESSION_IMMUTABLE';
  END IF;
END$$
DELIMITER ;
