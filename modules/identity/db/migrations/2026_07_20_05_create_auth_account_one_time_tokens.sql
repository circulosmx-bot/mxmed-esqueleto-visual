-- Gate 4B: one-time token hashes only; clear tokens exist only in notification memory.
CREATE TABLE IF NOT EXISTS `auth_account_one_time_tokens` (
  `token_id` VARCHAR(64) NOT NULL,
  `account_id` VARCHAR(64) NOT NULL,
  `purpose` VARCHAR(32) NOT NULL,
  `token_hash` CHAR(64) NOT NULL,
  `issued_at` DATETIME NOT NULL,
  `expires_at` DATETIME NOT NULL,
  `consumed_at` DATETIME NULL DEFAULT NULL,
  `invalidated_at` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`token_id`),
  UNIQUE KEY `ux_auth_tokens_purpose_hash` (`purpose`, `token_hash`),
  KEY `idx_auth_tokens_account_purpose_state` (`account_id`, `purpose`, `consumed_at`, `invalidated_at`),
  KEY `idx_auth_tokens_expiry` (`expires_at`),
  CONSTRAINT `fk_auth_tokens_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_tokens_purpose` CHECK (`purpose` IN ('email_verification','password_recovery')),
  CONSTRAINT `ck_auth_tokens_expiry` CHECK (`expires_at` > `issued_at`),
  CONSTRAINT `ck_auth_tokens_hash_format` CHECK (CHAR_LENGTH(`token_hash`) = 64)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
