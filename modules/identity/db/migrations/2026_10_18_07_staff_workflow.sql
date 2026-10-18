-- Isolated consolidation; no production installation authorized.
CREATE TABLE auth_staff_document_sets (
 set_id VARCHAR(64) PRIMARY KEY,
 case_id VARCHAR(64) NOT NULL,
 case_version INT UNSIGNED NOT NULL,
 sequence_number INT UNSIGNED NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 manifest JSON NOT NULL,
 manifest_hash CHAR(64) NOT NULL,
 media_watermark INT UNSIGNED NOT NULL,
 legal_reference VARCHAR(128) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_staff_set(case_id,sequence_number),
 FOREIGN KEY(case_id,case_version) REFERENCES auth_staff_admission_versions(case_id,version),
 FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id)
) ENGINE=InnoDB;
CREATE TABLE auth_staff_review_intents (
 intent_id VARCHAR(64) PRIMARY KEY,
 actor_account_id VARCHAR(64) NOT NULL,
 case_id VARCHAR(64) NOT NULL,
 set_id VARCHAR(64) NOT NULL,
 action VARCHAR(24) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 session_id VARCHAR(128) NOT NULL,
 credential_version INT UNSIGNED NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 FOREIGN KEY(set_id) REFERENCES auth_staff_document_sets(set_id),
 FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 CHECK(action IN ('READ','CORRECTIONS','ACCEPT','REJECT','ADMIT'))
) ENGINE=InnoDB;
CREATE TABLE auth_staff_identity_reviews (
 review_id VARCHAR(64) PRIMARY KEY,
 case_id VARCHAR(64) NOT NULL,
 set_id VARCHAR(64) NOT NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 reviewer_credential_version INT UNSIGNED NOT NULL,
 applicant_credential_version INT UNSIGNED NOT NULL,
 decision VARCHAR(24) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 UNIQUE KEY uq_staff_review_intent(review_id),
 FOREIGN KEY(set_id) REFERENCES auth_staff_document_sets(set_id),
 FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 CHECK(decision IN ('CORRECTIONS','ACCEPT','REJECT'))
) ENGINE=InnoDB;
CREATE TABLE auth_staff_admission_completions (
 case_id VARCHAR(64) PRIMARY KEY,
 account_id VARCHAR(64) NOT NULL UNIQUE,
 intent_id VARCHAR(64) NOT NULL UNIQUE,
 review_id VARCHAR(64) NOT NULL,
 actor_account_id VARCHAR(64) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(case_id) REFERENCES auth_staff_admission_cases(case_id),
 FOREIGN KEY(account_id) REFERENCES internal_staff(account_id),
 FOREIGN KEY(review_id) REFERENCES auth_staff_identity_reviews(review_id)
) ENGINE=InnoDB;
DELIMITER $$
CREATE TRIGGER staff_sets_update BEFORE UPDATE ON auth_staff_document_sets FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
CREATE TRIGGER staff_sets_delete BEFORE DELETE ON auth_staff_document_sets FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
CREATE TRIGGER staff_reviews_update BEFORE UPDATE ON auth_staff_identity_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
CREATE TRIGGER staff_reviews_delete BEFORE DELETE ON auth_staff_identity_reviews FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
CREATE TRIGGER staff_completions_update BEFORE UPDATE ON auth_staff_admission_completions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
CREATE TRIGGER staff_completions_delete BEFORE DELETE ON auth_staff_admission_completions FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_workflow'; END$$
DELIMITER ;

-- Preserve all 17 historical operations, append only the staff review purpose.
ALTER TABLE auth_governance_cases
 DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK (operation IN (
  'FIRST_DIRECTOR_BOOTSTRAP',
  'INITIAL_OWNER',
  'INTERNAL_ADVISOR_MANAGEMENT_GRANT',
  'INTERNAL_ADVISOR_MANAGEMENT_REVOKE',
  'INTERNAL_CAPABILITY_DELEGATE',
  'INTERNAL_CAPABILITY_DELEGATION_REVOKE',
  'INTERNAL_MASTER_APPOINT',
  'INTERNAL_MASTER_REMOVE',
  'MFA_RECOVERY',
  'OWNER_REMOVAL',
  'OWNER_REPLACEMENT',
  'PHYSICIAN_AUTHORITY_CHANGE',
  'PHYSICIAN_ENROLLMENT_REVIEW',
  'PHYSICIAN_EVIDENCE_REVIEW',
  'PROFESSIONAL_SOURCE_PROVISION',
  'PUBLIC_PROFILE_CORRECTION_APPLY',
  'PUBLIC_PROFILE_CORRECTION_REVIEW',
  'STAFF_ADMISSION_REVIEW'
 ));

DELIMITER $$
CREATE TRIGGER staff_intent_immutable BEFORE UPDATE ON auth_staff_review_intents FOR EACH ROW
BEGIN
 IF NOT(OLD.intent_id<=>NEW.intent_id) OR NOT(OLD.actor_account_id<=>NEW.actor_account_id)
 OR NOT(OLD.case_id<=>NEW.case_id) OR NOT(OLD.set_id<=>NEW.set_id) OR NOT(OLD.action<=>NEW.action)
 OR NOT(OLD.reason<=>NEW.reason) OR NOT(OLD.session_id<=>NEW.session_id)
 OR NOT(OLD.credential_version<=>NEW.credential_version) OR NOT(OLD.expires_at<=>NEW.expires_at)
 OR OLD.consumed_at IS NOT NULL OR NEW.consumed_at IS NULL
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_intent'; END IF;
END$$
DELIMITER ;
