-- CONS-IMP02B recovery rollback.
-- Before dropping columns after application use, export local_parking,
-- accessible and emergency_phone rows. Prefer rolling application code back
-- while retaining additive columns until recovery data has been preserved.

ALTER TABLE `doctor_contact_points`
  DROP FOREIGN KEY `fk_dcp_doctor`,
  DROP INDEX `uq_dcp_active_emergency_position`,
  DROP CONSTRAINT `chk_dcp_emergency_scope`,
  DROP COLUMN `active_emergency_position`,
  DROP COLUMN `emergency_position`;

ALTER TABLE `consultorios`
  DROP CONSTRAINT `chk_consultorios_accessible`,
  DROP CONSTRAINT `chk_consultorios_local_parking`,
  DROP COLUMN `accessible`,
  DROP COLUMN `local_parking`;

-- consultorios.urgencias_json is not changed by either direction.
