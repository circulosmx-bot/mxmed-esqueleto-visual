-- MEAS01: auditable invalidation; no observation is deleted or backfilled as invalid.
DELIMITER $$
DROP PROCEDURE IF EXISTS meas01_observation_invalidation_migrate$$
CREATE PROCEDURE meas01_observation_invalidation_migrate()
BEGIN
  DECLARE n INT DEFAULT 0;
  DECLARE shape VARCHAR(100);
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations') THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MEAS01_MIGRATION_DRIFT: clinical_observations missing';
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidated_at';
  IF n=0 THEN ALTER TABLE clinical_observations ADD COLUMN invalidated_at DATETIME NULL;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',COALESCE(CHARACTER_MAXIMUM_LENGTH,0),'|',IS_NULLABLE) INTO shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidated_at';
    IF shape<>'datetime|0|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MEAS01_MIGRATION_DRIFT: invalidated_at'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidated_by_user_id';
  IF n=0 THEN ALTER TABLE clinical_observations ADD COLUMN invalidated_by_user_id VARCHAR(64) NULL;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',COALESCE(CHARACTER_MAXIMUM_LENGTH,0),'|',IS_NULLABLE) INTO shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidated_by_user_id';
    IF shape<>'varchar|64|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MEAS01_MIGRATION_DRIFT: invalidated_by_user_id'; END IF;
  END IF;
  SELECT COUNT(*) INTO n FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidation_reason';
  IF n=0 THEN ALTER TABLE clinical_observations ADD COLUMN invalidation_reason VARCHAR(1000) NULL;
  ELSE
    SELECT CONCAT(DATA_TYPE,'|',COALESCE(CHARACTER_MAXIMUM_LENGTH,0),'|',IS_NULLABLE) INTO shape FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND COLUMN_NAME='invalidation_reason';
    IF shape<>'varchar|1000|YES' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='MEAS01_MIGRATION_DRIFT: invalidation_reason'; END IF;
  END IF;
  IF NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='clinical_observations' AND CONSTRAINT_NAME='chk_observation_invalidation_v1') THEN
    ALTER TABLE clinical_observations ADD CONSTRAINT chk_observation_invalidation_v1 CHECK (
      (invalidated_at IS NULL AND invalidated_by_user_id IS NULL AND invalidation_reason IS NULL) OR
      (invalidated_at IS NOT NULL AND invalidated_by_user_id IS NOT NULL AND invalidation_reason IS NOT NULL
        AND CHAR_LENGTH(TRIM(invalidated_by_user_id))>0 AND CHAR_LENGTH(TRIM(invalidation_reason))>0));
  END IF;
END$$
CALL meas01_observation_invalidation_migrate()$$
DROP PROCEDURE meas01_observation_invalidation_migrate$$
DELIMITER ;
