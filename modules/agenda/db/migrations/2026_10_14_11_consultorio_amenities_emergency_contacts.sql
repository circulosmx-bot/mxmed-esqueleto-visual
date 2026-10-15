-- CONS-IMP02B: additive consultorio amenities and physician emergency contacts.
-- Existing consultorio.urgencias_json values are deliberately preserved and are
-- never adopted automatically into doctor_contact_points.

ALTER TABLE `consultorios`
  ADD COLUMN `local_parking` TINYINT(1) NULL DEFAULT NULL
    AFTER `urgencias_json`,
  ADD COLUMN `accessible` TINYINT(1) NULL DEFAULT NULL
    AFTER `local_parking`,
  ADD CONSTRAINT `chk_consultorios_local_parking`
    CHECK (`local_parking` IS NULL OR `local_parking` IN (0, 1)),
  ADD CONSTRAINT `chk_consultorios_accessible`
    CHECK (`accessible` IS NULL OR `accessible` IN (0, 1));

ALTER TABLE `doctor_contact_points`
  ADD COLUMN `emergency_position` TINYINT UNSIGNED NULL DEFAULT NULL
    AFTER `sort_order`,
  ADD COLUMN `active_emergency_position` TINYINT
    GENERATED ALWAYS AS (
      CASE
        WHEN `deleted_at` IS NULL AND `type` = 'emergency_phone'
          THEN `emergency_position`
        ELSE NULL
      END
    ) STORED
    AFTER `active_normalized_value`,
  ADD CONSTRAINT `chk_dcp_emergency_scope`
    CHECK (
      (`type` = 'emergency_phone'
        AND `consultorio_id` IS NULL
        AND `emergency_position` IN (1, 2))
      OR
      (`type` <> 'emergency_phone' AND `emergency_position` IS NULL)
    ),
  ADD UNIQUE KEY `uq_dcp_active_emergency_position`
    (`doctor_id`, `active_emergency_position`),
  ADD CONSTRAINT `fk_dcp_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`)
    ON UPDATE RESTRICT ON DELETE RESTRICT;
