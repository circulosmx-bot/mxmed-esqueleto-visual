-- Gate 4A: versioned consent evidence; document bodies are not duplicated here.
CREATE TABLE IF NOT EXISTS `auth_account_consents` (
  `consent_id` VARCHAR(64) NOT NULL,
  `account_id` VARCHAR(64) NOT NULL,
  `document_type` VARCHAR(32) NOT NULL,
  `document_version` VARCHAR(64) NOT NULL,
  `accepted_at` DATETIME NOT NULL,
  `metadata_json` TEXT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`consent_id`),
  UNIQUE KEY `ux_auth_account_consents_account_document_version` (`account_id`, `document_type`, `document_version`),
  KEY `idx_auth_account_consents_account_accepted` (`account_id`, `accepted_at`),
  CONSTRAINT `fk_auth_account_consents_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_account_consents_document_type` CHECK (`document_type` IN ('terms','privacy_notice'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
