-- LON07A: additive, conservative authority for observation effective time.
-- Existing rows receive UNKNOWN_LEGACY; no historical effective_at is rewritten.
DELIMITER $$
DROP PROCEDURE IF EXISTS lon07a_observation_time_authority_migrate$$
CREATE PROCEDURE lon07a_observation_time_authority_migrate()
BEGIN
  DECLARE n INT DEFAULT 0;
  SELECT COUNT(*) INTO n FROM information_schema.TABLES
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations';
  IF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON07A_MIGRATION_DRIFT: clinical_observations missing'; END IF;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='effective_at_authority';
  IF n=0 THEN
    ALTER TABLE clinical_observations
      ADD COLUMN effective_at_authority VARCHAR(32) NOT NULL DEFAULT 'UNKNOWN_LEGACY' AFTER effective_at;
  ELSE
    SELECT COUNT(*) INTO n FROM information_schema.COLUMNS
      WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations'
        AND COLUMN_NAME='effective_at_authority' AND DATA_TYPE='varchar'
        AND CHARACTER_MAXIMUM_LENGTH=32 AND IS_NULLABLE='NO' AND COLUMN_DEFAULT='UNKNOWN_LEGACY';
    IF n<>1 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='LON07A_MIGRATION_DRIFT: effective_at_authority'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations'
      AND CONSTRAINT_NAME='chk_observation_time_authority_v1' AND CONSTRAINT_TYPE='CHECK';
  IF n=0 THEN
    ALTER TABLE clinical_observations ADD CONSTRAINT chk_observation_time_authority_v1
      CHECK (effective_at_authority IN ('EXPLICIT_EFFECTIVE_TIME','CAPTURE_TIME_FALLBACK','UNKNOWN_LEGACY'));
  END IF;
END$$
CALL lon07a_observation_time_authority_migrate()$$
DROP PROCEDURE lon07a_observation_time_authority_migrate$$
DELIMITER ;
