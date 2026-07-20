-- Gate 4A: canonical human accounts only. No password, token, MFA or session data.
CREATE TABLE IF NOT EXISTS `auth_accounts` (
  `account_id` VARCHAR(64) NOT NULL,
  `email_address` VARCHAR(190) NOT NULL,
  `email_normalized` VARCHAR(190) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending_verification',
  `email_verified_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  UNIQUE KEY `ux_auth_accounts_email_normalized` (`email_normalized`),
  KEY `idx_auth_accounts_status` (`status`),
  CONSTRAINT `ck_auth_accounts_status` CHECK (`status` IN ('pending_verification','active','blocked','disabled')),
  CONSTRAINT `ck_auth_accounts_email_verified_state` CHECK (
    (`status` = 'pending_verification' AND `email_verified_at` IS NULL)
    OR (`status` <> 'pending_verification')
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
