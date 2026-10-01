-- PROV03A: organization type is immutable after creation, including SQL writers
-- that do not use MedicalGroupsRepository.
DELIMITER $$
DROP TRIGGER IF EXISTS trg_medical_groups_type_immutable$$
CREATE TRIGGER trg_medical_groups_type_immutable
BEFORE UPDATE ON medical_groups FOR EACH ROW
BEGIN
  IF NOT (NEW.organization_type_key <=> OLD.organization_type_key) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ORGANIZATION_TYPE_IMMUTABLE';
  END IF;
END$$
DELIMITER ;
