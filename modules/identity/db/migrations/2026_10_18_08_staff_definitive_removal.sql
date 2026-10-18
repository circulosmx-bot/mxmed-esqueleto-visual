-- UXFUNC01: prepared migration; installed only in disposable synthetic infrastructure.
-- Historical staff row/account/audit remain. No automatic reactivation or re-admission.
ALTER TABLE internal_staff MODIFY status ENUM('ACTIVE','SUSPENDED','REMOVED') NOT NULL,
 ADD CONSTRAINT ck_staff_removed_advisor CHECK(status<>'REMOVED' OR governance_class='ADVISOR');
DELIMITER $$
CREATE TRIGGER internal_staff_removed_terminal BEFORE UPDATE ON internal_staff FOR EACH ROW
BEGIN
 IF OLD.status='REMOVED' AND (NEW.status<>'REMOVED' OR NEW.governance_class<>OLD.governance_class)
 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='staff_removal_is_terminal'; END IF;
END$$
DELIMITER ;
