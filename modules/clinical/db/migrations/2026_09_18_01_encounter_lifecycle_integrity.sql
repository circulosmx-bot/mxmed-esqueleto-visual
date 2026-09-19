-- CLIN-REFORM-PHASE2-IMPL01A — review artifact only. DO NOT EXECUTE in this chapter.
-- This migration deliberately performs no ownership backfill and no completed -> open conversion.
-- Run only after the read-only preflight has been reviewed and ambiguous legacy rows adjudicated.

DELIMITER $$

DROP PROCEDURE IF EXISTS mxmed_preflight_encounter_lifecycle_v1$$
CREATE PROCEDURE mxmed_preflight_encounter_lifecycle_v1()
BEGIN
  IF (SELECT COUNT(*) FROM information_schema.TABLES
      WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_encounters') <> 1 THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SCHEMA_NOT_READY: clinical_encounters missing';
  END IF;

  IF EXISTS (SELECT 1 FROM clinical_encounters
             WHERE LOWER(TRIM(status)) NOT IN ('open', 'closed', 'voided')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'LEGACY_STATE_REQUIRES_ADJUDICATION';
  END IF;

  IF EXISTS (SELECT 1 FROM clinical_encounters
             WHERE LOWER(TRIM(status)) = 'open' AND (doctor_id IS NULL OR TRIM(doctor_id) = '')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'OPEN_ENCOUNTER_DOCTOR_REQUIRES_ADJUDICATION';
  END IF;

  IF EXISTS (
    SELECT 1 FROM clinical_encounters
    WHERE LOWER(TRIM(status)) = 'open'
    GROUP BY doctor_id, patient_id HAVING COUNT(*) > 1
  ) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DUPLICATE_OPEN_ENCOUNTER_REQUIRES_ADJUDICATION';
  END IF;
END$$

CALL mxmed_preflight_encounter_lifecycle_v1()$$
DROP PROCEDURE mxmed_preflight_encounter_lifecycle_v1$$

DELIMITER ;

ALTER TABLE clinical_encounters
  ADD COLUMN voided_at DATETIME NULL AFTER closed_by_user_id,
  ADD COLUMN voided_by_user_id VARCHAR(64) NULL AFTER voided_at,
  ADD COLUMN void_reason VARCHAR(1000) NULL AFTER voided_by_user_id,
  ADD COLUMN open_guard VARCHAR(193)
    GENERATED ALWAYS AS (
      CASE WHEN LOWER(TRIM(status)) = 'open'
           THEN CONCAT(doctor_id, ':', patient_id)
           ELSE NULL END
    ) STORED,
  ADD CONSTRAINT chk_clinical_encounter_lifecycle_v1 CHECK (
    (LOWER(TRIM(status)) = 'open' AND closed_at IS NULL AND closed_by_user_id IS NULL
      AND voided_at IS NULL AND voided_by_user_id IS NULL AND void_reason IS NULL)
    OR
    (LOWER(TRIM(status)) = 'closed' AND closed_at IS NOT NULL AND closed_by_user_id IS NOT NULL
      AND voided_at IS NULL AND voided_by_user_id IS NULL AND void_reason IS NULL)
    OR
    (LOWER(TRIM(status)) = 'voided' AND voided_at IS NOT NULL AND voided_by_user_id IS NOT NULL
      AND CHAR_LENGTH(TRIM(void_reason)) > 0 AND closed_at IS NULL AND closed_by_user_id IS NULL)
  ),
  ADD UNIQUE KEY uq_clinical_encounter_one_open (open_guard),
  ADD KEY idx_clinical_encounter_lifecycle (doctor_id, patient_id, status, encounter_dt);

DELIMITER $$

DROP TRIGGER IF EXISTS trg_clinical_encounters_v1_before_insert$$
CREATE TRIGGER trg_clinical_encounters_v1_before_insert
BEFORE INSERT ON clinical_encounters
FOR EACH ROW
BEGIN
  IF LOWER(TRIM(NEW.status)) <> 'open' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'START_MUST_CREATE_OPEN';
  END IF;
  IF NEW.doctor_id IS NULL OR TRIM(NEW.doctor_id) = '' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'DOCTOR_ID_REQUIRED';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_clinical_encounters_v1_before_update$$
CREATE TRIGGER trg_clinical_encounters_v1_before_update
BEFORE UPDATE ON clinical_encounters
FOR EACH ROW
BEGIN
  IF NOT (NEW.patient_id <=> OLD.patient_id)
     OR NOT (NEW.doctor_id <=> OLD.doctor_id)
     OR NOT (NEW.appointment_id <=> OLD.appointment_id)
     OR NOT (NEW.opened_by_user_id <=> OLD.opened_by_user_id)
     OR NOT (NEW.encounter_dt <=> OLD.encounter_dt)
     OR NOT (NEW.encounter_type <=> OLD.encounter_type) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ENCOUNTER_OWNERSHIP_IMMUTABLE';
  END IF;

  IF LOWER(TRIM(NEW.status)) <> LOWER(TRIM(OLD.status))
     AND NOT (LOWER(TRIM(OLD.status)) = 'open'
              AND LOWER(TRIM(NEW.status)) IN ('closed', 'voided')) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'ENCOUNTER_TRANSITION_FORBIDDEN';
  END IF;

  IF LOWER(TRIM(OLD.status)) = 'closed'
     AND (NOT (NEW.closed_at <=> OLD.closed_at)
          OR NOT (NEW.closed_by_user_id <=> OLD.closed_by_user_id)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FIRST_CLOSE_IMMUTABLE';
  END IF;

  IF LOWER(TRIM(OLD.status)) = 'voided'
     AND (NOT (NEW.voided_at <=> OLD.voided_at)
          OR NOT (NEW.voided_by_user_id <=> OLD.voided_by_user_id)
          OR NOT (NEW.void_reason <=> OLD.void_reason)) THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'FIRST_VOID_IMMUTABLE';
  END IF;
END$$

DELIMITER ;
