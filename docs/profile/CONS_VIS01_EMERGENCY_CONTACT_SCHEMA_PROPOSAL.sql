-- CONS-VIS01 proposal only. Do not add this file to automatic migrations.
-- Applying it to normal-local or production requires separate Director approval.
CREATE TABLE `physician_emergency_contacts` (
  `doctor_id` VARCHAR(64) NOT NULL,
  `emergency_phones_json` JSON NOT NULL,
  `version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`doctor_id`),
  CONSTRAINT `chk_physician_emergency_phones_array`
    CHECK (JSON_TYPE(`emergency_phones_json`) = 'ARRAY' AND JSON_LENGTH(`emergency_phones_json`) <= 2),
  CONSTRAINT `fk_physician_emergency_contacts_doctor`
    FOREIGN KEY (`doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`)
    ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Deliberately no INSERT/SELECT backfill from consultorios.urgencias_json.
-- Legacy values require an explicit reconciliation decision before cutover.
