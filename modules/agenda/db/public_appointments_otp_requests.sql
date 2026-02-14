-- MXMed Agenda Publica P2-A
-- Tabla para solicitudes de cita publica con verificacion OTP (6 digitos, 10 min).
-- Alcance: request/verify sin proveedor SMS real. Sender abstracto en backend.

CREATE TABLE IF NOT EXISTS `agenda_public_otp_requests` (
  `id` VARCHAR(36) NOT NULL,
  `doctor_id` INT NOT NULL,
  `consultorio_id` INT NOT NULL,
  `start_at` DATETIME NOT NULL,
  `end_at` DATETIME NOT NULL,
  `patient_name` VARCHAR(191) NOT NULL,
  `patient_phone` VARCHAR(32) DEFAULT NULL,
  `patient_email` VARCHAR(191) DEFAULT NULL,
  `otp_hash` VARCHAR(255) NOT NULL,
  `otp_last4` CHAR(4) DEFAULT NULL,
  `status` ENUM('pending_verification','verified','expired','failed') NOT NULL DEFAULT 'pending_verification',
  `attempts` INT NOT NULL DEFAULT 0,
  `expires_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL,
  `verified_at` DATETIME DEFAULT NULL,
  `meta_json` JSON DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_public_otp_slot` (`doctor_id`, `consultorio_id`, `start_at`),
  KEY `idx_public_otp_expires` (`expires_at`),
  KEY `idx_public_otp_email` (`patient_email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
