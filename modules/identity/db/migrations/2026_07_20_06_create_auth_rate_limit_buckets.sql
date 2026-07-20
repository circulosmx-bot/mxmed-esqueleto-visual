-- Gate 4B: HMAC-derived rate-limit dimensions; raw IP/email/device values are forbidden.
CREATE TABLE IF NOT EXISTS `auth_rate_limit_buckets` (
  `bucket_id` VARCHAR(128) NOT NULL,
  `operation_code` VARCHAR(64) NOT NULL,
  `dimension_code` VARCHAR(32) NOT NULL,
  `dimension_key_hash` CHAR(64) NOT NULL,
  `window_started_at` DATETIME NOT NULL,
  `attempts_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `blocked_until` DATETIME NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`bucket_id`),
  UNIQUE KEY `ux_auth_rate_limit_bucket_window` (`operation_code`, `dimension_code`, `dimension_key_hash`, `window_started_at`),
  KEY `idx_auth_rate_limit_blocked_until` (`blocked_until`),
  CONSTRAINT `ck_auth_rate_limit_attempts` CHECK (`attempts_count` >= 0),
  CONSTRAINT `ck_auth_rate_limit_dimension_hash` CHECK (CHAR_LENGTH(`dimension_key_hash`) = 64)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
