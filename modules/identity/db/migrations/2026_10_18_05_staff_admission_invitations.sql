-- ASE-ADM02-IMP02. Apply after 2026_10_18_04 on an explicitly authorized target only.
-- No historical migration edits, backfill, staff writes or account creation.
CREATE TABLE auth_staff_admission_invitations (
 invitation_id VARCHAR(64) NOT NULL PRIMARY KEY,
 case_id VARCHAR(64) NOT NULL,
 case_version INT UNSIGNED NOT NULL,
 creator_account_id VARCHAR(64) NOT NULL,
 creator_credential_version INT UNSIGNED NOT NULL,
 purpose VARCHAR(40) NOT NULL,
 recipient_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 recipient_key_id VARCHAR(128) NOT NULL,
 token_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE,
 validity_reference VARCHAR(128) NOT NULL,
 issued_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 last_observed_at DATETIME(6) NOT NULL,
 state VARCHAR(16) NOT NULL,
 delivery_state VARCHAR(24) NOT NULL DEFAULT 'DELIVERY_PENDING',
 consumed_account_id VARCHAR(64) NULL,
 consumed_credential_version INT UNSIGNED NULL,
 consumed_at DATETIME(6) NULL,
 active_case VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN state='ACTIVE' THEN case_id ELSE NULL END) STORED,
 UNIQUE KEY uq_staff_invitation_active_case(active_case),
 CONSTRAINT fk_staff_invitation_version FOREIGN KEY(case_id,case_version) REFERENCES auth_staff_admission_versions(case_id,version),
 CONSTRAINT fk_staff_invitation_creator FOREIGN KEY(creator_account_id) REFERENCES auth_accounts(account_id),
 CONSTRAINT fk_staff_invitation_consumer FOREIGN KEY(consumed_account_id) REFERENCES auth_accounts(account_id),
 CONSTRAINT ck_staff_invitation_purpose CHECK(purpose='STAFF_ADMISSION_ACCOUNT_BINDING'),
 CONSTRAINT ck_staff_invitation_state CHECK(state IN ('ACTIVE','REVOKED','REPLACED','EXPIRED','CONSUMED')),
 CONSTRAINT ck_staff_invitation_delivery CHECK(delivery_state IN ('DELIVERY_PENDING','DELIVERY_CONFIRMED')),
 CONSTRAINT ck_staff_invitation_time CHECK(expires_at>issued_at AND last_observed_at>=issued_at),
 CONSTRAINT ck_staff_invitation_credential CHECK(creator_credential_version>=1),
 CONSTRAINT ck_staff_invitation_consumption CHECK(
  (state='CONSUMED' AND consumed_account_id IS NOT NULL AND consumed_credential_version IS NOT NULL AND consumed_credential_version>=1 AND consumed_at IS NOT NULL)
  OR (state<>'CONSUMED' AND consumed_account_id IS NULL AND consumed_credential_version IS NULL AND consumed_at IS NULL))
) ENGINE=InnoDB;
CREATE TABLE auth_staff_admission_invitation_operations (
 actor_account_id VARCHAR(64) NOT NULL,
 operation_id CHAR(36) NOT NULL,
 case_id VARCHAR(64) NOT NULL,
 invitation_id VARCHAR(64) NOT NULL,
 case_version INT UNSIGNED NOT NULL,
 command VARCHAR(16) NOT NULL,
 request_digest CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 recorded_at DATETIME(6) NOT NULL,
 PRIMARY KEY(actor_account_id,operation_id),
 CONSTRAINT fk_staff_invitation_operation_actor FOREIGN KEY(actor_account_id) REFERENCES auth_accounts(account_id),
 CONSTRAINT fk_staff_invitation_operation_invitation FOREIGN KEY(invitation_id) REFERENCES auth_staff_admission_invitations(invitation_id),
 CONSTRAINT fk_staff_invitation_operation_version FOREIGN KEY(case_id,case_version) REFERENCES auth_staff_admission_versions(case_id,version),
 CONSTRAINT ck_staff_invitation_operation CHECK(command IN ('issue','reissue','revoke','accept'))
) ENGINE=InnoDB;
DELIMITER $$
CREATE TRIGGER auth_staff_invitation_receipt_no_update BEFORE UPDATE ON auth_staff_admission_invitation_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_invitation_receipt'; END$$
CREATE TRIGGER auth_staff_invitation_receipt_no_delete BEFORE DELETE ON auth_staff_admission_invitation_operations FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_invitation_receipt'; END$$
CREATE TRIGGER auth_staff_invitation_transition BEFORE UPDATE ON auth_staff_admission_invitations FOR EACH ROW
BEGIN
 IF NOT(NEW.invitation_id <=> OLD.invitation_id) OR NOT(NEW.case_id <=> OLD.case_id) OR NOT(NEW.case_version <=> OLD.case_version)
 OR NOT(NEW.creator_account_id <=> OLD.creator_account_id) OR NOT(NEW.creator_credential_version <=> OLD.creator_credential_version)
 OR NOT(NEW.purpose <=> OLD.purpose) OR NOT(NEW.recipient_digest <=> OLD.recipient_digest) OR NOT(NEW.recipient_key_id <=> OLD.recipient_key_id)
 OR NOT(NEW.token_digest <=> OLD.token_digest) OR NOT(NEW.validity_reference <=> OLD.validity_reference)
 OR NOT(NEW.issued_at <=> OLD.issued_at) OR NOT(NEW.expires_at <=> OLD.expires_at) OR NEW.last_observed_at<OLD.last_observed_at
 OR (OLD.state<>'ACTIVE' AND NEW.state<>OLD.state)
 OR (OLD.state='CONSUMED' AND (NOT(NEW.consumed_account_id <=> OLD.consumed_account_id) OR NOT(NEW.consumed_credential_version <=> OLD.consumed_credential_version) OR NOT(NEW.consumed_at <=> OLD.consumed_at)))
 OR (OLD.delivery_state='DELIVERY_CONFIRMED' AND NEW.delivery_state<>OLD.delivery_state)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_invitation_intent'; END IF;
END$$
DELIMITER ;
