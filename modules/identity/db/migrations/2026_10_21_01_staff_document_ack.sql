-- STAFF-DOC-VIS02: additive; install only on explicitly authorized disposable targets.
ALTER TABLE auth_staff_review_intents DROP CHECK auth_staff_review_intents_chk_1,
 ADD CONSTRAINT ck_staff_review_action CHECK(action IN ('READ','ACK','CORRECTIONS','ACCEPT','REJECT','ADMIT'));
CREATE TABLE auth_staff_document_acknowledgements (
 acknowledgement_id VARCHAR(64) PRIMARY KEY,
 case_id VARCHAR(64) NOT NULL,
 case_version INT UNSIGNED NOT NULL,
 set_id VARCHAR(64) NOT NULL,
 reviewer_account_id VARCHAR(64) NOT NULL,
 reviewer_credential_version INT UNSIGNED NOT NULL,
 reviewer_role VARCHAR(24) NOT NULL,
 audit_event_id CHAR(36) NOT NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 FOREIGN KEY(acknowledgement_id) REFERENCES auth_staff_review_intents(intent_id),
 FOREIGN KEY(case_id,case_version) REFERENCES auth_staff_admission_versions(case_id,version),
 FOREIGN KEY(set_id) REFERENCES auth_staff_document_sets(set_id),
 FOREIGN KEY(reviewer_account_id) REFERENCES auth_accounts(account_id),
 CHECK(reviewer_role='DIRECTOR'),
 CHECK(reviewer_credential_version>=1),
 INDEX(case_id,set_id,created_at)
) ENGINE=InnoDB;
ALTER TABLE auth_staff_admission_invitations ADD COLUMN delivered_at DATETIME(6) NULL;
DELIMITER $$
CREATE TRIGGER auth_staff_document_ack_no_update BEFORE UPDATE ON auth_staff_document_acknowledgements FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_document_ack'; END$$
CREATE TRIGGER auth_staff_document_ack_no_delete BEFORE DELETE ON auth_staff_document_acknowledgements FOR EACH ROW
BEGIN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_document_ack'; END$$
CREATE TRIGGER auth_staff_delivery_time_immutable BEFORE UPDATE ON auth_staff_admission_invitations FOR EACH ROW
BEGIN
 IF (OLD.delivered_at IS NOT NULL AND NOT(NEW.delivered_at <=> OLD.delivered_at))
 OR (NEW.delivered_at IS NOT NULL AND NEW.delivery_state<>'DELIVERY_CONFIRMED')
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_delivery_time'; END IF;
END$$
DELIMITER ;
