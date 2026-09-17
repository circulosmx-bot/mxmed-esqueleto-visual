-- CLIN-ATTR01: encounter ownership is explicit for new rows and nullable for legacy rows.
-- These guarded DDL statements allow the migration to be rerun after runtime schema repair.
SET @attr_has_doctor_id = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_encounters' AND COLUMN_NAME = 'doctor_id'
);
SET @attr_column_sql = IF(@attr_has_doctor_id = 0,
  'ALTER TABLE clinical_encounters ADD COLUMN doctor_id VARCHAR(64) NULL AFTER patient_id',
  'DO 0');
PREPARE attr_column_stmt FROM @attr_column_sql;
EXECUTE attr_column_stmt;
DEALLOCATE PREPARE attr_column_stmt;

SET @attr_has_index = (
  SELECT COUNT(*) FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'clinical_encounters'
    AND INDEX_NAME = 'idx_clinical_encounters_doctor_patient_status_dt'
);
SET @attr_index_sql = IF(@attr_has_index = 0,
  'ALTER TABLE clinical_encounters ADD KEY idx_clinical_encounters_doctor_patient_status_dt (doctor_id, patient_id, status, encounter_dt)',
  'DO 0');
PREPARE attr_index_stmt FROM @attr_index_sql;
EXECUTE attr_index_stmt;
DEALLOCATE PREPARE attr_index_stmt;

-- Appointment ownership is evidence only when its patient matches the encounter.
-- Appointmentless, missing, mismatched, or ambiguous legacy links remain unattributed.
UPDATE clinical_encounters e
JOIN (
  SELECT appointment_id, MIN(doctor_id) AS doctor_id, MIN(patient_id) AS patient_id
  FROM agenda_appointments
  WHERE appointment_id IS NOT NULL AND TRIM(appointment_id) <> ''
    AND doctor_id IS NOT NULL AND TRIM(doctor_id) <> ''
  GROUP BY appointment_id
  HAVING COUNT(*) = 1
) a ON a.appointment_id = e.appointment_id AND a.patient_id = e.patient_id
SET e.doctor_id = a.doctor_id
WHERE e.doctor_id IS NULL;
