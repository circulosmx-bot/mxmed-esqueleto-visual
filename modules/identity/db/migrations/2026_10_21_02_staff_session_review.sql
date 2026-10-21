-- STAFF-AUTH-UX08: isolated installation only. Preserve all historical intents.
ALTER TABLE auth_staff_review_intents
 ADD COLUMN session_case_version INT UNSIGNED NULL,
 ADD COLUMN session_review_id VARCHAR(64) NULL;
DROP TRIGGER staff_intent_immutable;
DELIMITER $$
CREATE TRIGGER staff_intent_immutable BEFORE UPDATE ON auth_staff_review_intents FOR EACH ROW
BEGIN
 IF NOT(OLD.intent_id<=>NEW.intent_id) OR NOT(OLD.actor_account_id<=>NEW.actor_account_id)
 OR NOT(OLD.case_id<=>NEW.case_id) OR NOT(OLD.set_id<=>NEW.set_id) OR NOT(OLD.action<=>NEW.action)
 OR NOT(OLD.reason<=>NEW.reason) OR NOT(OLD.session_id<=>NEW.session_id)
 OR NOT(OLD.credential_version<=>NEW.credential_version) OR NOT(OLD.expires_at<=>NEW.expires_at)
 OR NOT(OLD.session_case_version<=>NEW.session_case_version) OR NOT(OLD.session_review_id<=>NEW.session_review_id)
 OR OLD.consumed_at IS NOT NULL OR NEW.consumed_at IS NULL
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='immutable_staff_intent'; END IF;
END$$
DELIMITER ;
