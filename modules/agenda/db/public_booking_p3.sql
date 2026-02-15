-- MXMed Agenda Publica P3
-- Reserve + confirm OTP + anti doble-booking.
-- Idempotente (se puede correr varias veces sin romper).

DELIMITER $$

DROP PROCEDURE IF EXISTS mxmed_public_booking_p3_migrate $$
CREATE PROCEDURE mxmed_public_booking_p3_migrate()
BEGIN
  DECLARE cnt INT DEFAULT 0;

  -- 1) DROP INDEX uniq_appointments_slot si existe (legacy)
  SELECT COUNT(*) INTO cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'agenda_appointments'
    AND INDEX_NAME = 'uniq_appointments_slot';

  IF cnt > 0 THEN
    SET @sql_drop_idx = 'ALTER TABLE `agenda_appointments` DROP INDEX `uniq_appointments_slot`';
    PREPARE stmt FROM @sql_drop_idx;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  -- 2) Agregar active_slot_key + uniq_active_slot si NO existe la columna
  SELECT COUNT(*) INTO cnt
  FROM INFORMATION_SCHEMA.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'agenda_appointments'
    AND COLUMN_NAME = 'active_slot_key';

  IF cnt = 0 THEN
    SET @sql_add_slot_key = '
      ALTER TABLE `agenda_appointments`
      ADD COLUMN `active_slot_key` VARCHAR(255) GENERATED ALWAYS AS (
        CASE
          WHEN `status` IN (''pending_otp'',''confirmed'',''pending'',''scheduled'')
          THEN CONCAT(`doctor_id`,''|'',`consultorio_id`,''|'',DATE_FORMAT(`start_at`,''%Y-%m-%d %H:%i:%s''))
          ELSE NULL
        END
      ) STORED,
      ADD UNIQUE KEY `uniq_active_slot` (`active_slot_key`)
    ';
    PREPARE stmt FROM @sql_add_slot_key;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  -- 3) Si la columna ya existía pero falta el índice uniq_active_slot, lo agregamos
  SELECT COUNT(*) INTO cnt
  FROM INFORMATION_SCHEMA.STATISTICS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'agenda_appointments'
    AND INDEX_NAME = 'uniq_active_slot';

  IF cnt = 0 THEN
    SET @sql_add_uniq = 'ALTER TABLE `agenda_appointments` ADD UNIQUE KEY `uniq_active_slot` (`active_slot_key`)';
    PREPARE stmt FROM @sql_add_uniq;
    EXECUTE stmt;
    DEALLOCATE PREPARE stmt;
  END IF;

  -- 4) Tabla de flows públicos (ya es idempotente)
  CREATE TABLE IF NOT EXISTS `agenda_public_appointment_flows` (
    `flow_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `appointment_id` VARCHAR(64) NOT NULL,
    `doctor_id` VARCHAR(64) NOT NULL,
    `consultorio_id` VARCHAR(64) NOT NULL,
    `start_at` DATETIME NOT NULL,
    `end_at` DATETIME NOT NULL,
    `status` VARCHAR(32) NOT NULL DEFAULT 'pending_otp',
    `otp_id` BIGINT UNSIGNED DEFAULT NULL,
    `otp_channel` VARCHAR(16) DEFAULT NULL,
    `otp_external_id` VARCHAR(64) DEFAULT NULL,
    `otp_verified_at` DATETIME DEFAULT NULL,
    `expires_at` DATETIME NOT NULL,
    `cancel_token` VARCHAR(64) NOT NULL,
    `payload_json` JSON DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`flow_id`),
    UNIQUE KEY `uniq_public_flow_appointment` (`appointment_id`),
    KEY `idx_public_flow_status_expires` (`status`, `expires_at`),
    KEY `idx_public_flow_slot` (`doctor_id`, `consultorio_id`, `start_at`)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
END $$

DELIMITER ;

CALL mxmed_public_booking_p3_migrate();

DROP PROCEDURE IF EXISTS mxmed_public_booking_p3_migrate;
