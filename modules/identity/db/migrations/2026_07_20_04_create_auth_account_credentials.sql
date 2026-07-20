-- Gate 4B: Argon2id credential material only; no plaintext passwords, sessions or MFA.
CREATE TABLE IF NOT EXISTS `auth_account_credentials` (
  `account_id` VARCHAR(64) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `password_changed_at` DATETIME NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`account_id`),
  CONSTRAINT `fk_auth_account_credentials_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_account_credentials_version` CHECK (`credential_version` >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
