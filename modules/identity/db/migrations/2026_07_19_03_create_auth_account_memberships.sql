-- Gate 4A: account -> membership -> canonical profile or existing organization.
-- Parent authorities are profiles_doctors.doctor_id and medical_groups.group_id.
CREATE TABLE IF NOT EXISTS `auth_account_memberships` (
  `membership_id` VARCHAR(64) NOT NULL,
  `account_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NULL DEFAULT NULL,
  `entity_group_id` VARCHAR(64) NULL DEFAULT NULL,
  `role_code` VARCHAR(32) NOT NULL,
  `scope_code` VARCHAR(64) NOT NULL,
  `status` VARCHAR(32) NOT NULL DEFAULT 'pending',
  `assignment_source` VARCHAR(64) NOT NULL DEFAULT 'manual_review',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `revoked_at` DATETIME NULL DEFAULT NULL,
  `active_identity_key` VARCHAR(320) GENERATED ALWAYS AS (
    CASE WHEN `status` IN ('pending','active','suspended') THEN
      CONCAT(`account_id`, ':', COALESCE(`profile_doctor_id`, ''), ':', COALESCE(`entity_group_id`, ''), ':', `role_code`, ':', `scope_code`)
    ELSE NULL END
  ) STORED,
  PRIMARY KEY (`membership_id`),
  UNIQUE KEY `ux_auth_account_memberships_active_identity` (`active_identity_key`),
  KEY `idx_auth_account_memberships_account_status` (`account_id`, `status`),
  KEY `idx_auth_account_memberships_profile_status` (`profile_doctor_id`, `status`),
  KEY `idx_auth_account_memberships_group_status` (`entity_group_id`, `status`),
  CONSTRAINT `fk_auth_account_memberships_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_account_memberships_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_account_memberships_group` FOREIGN KEY (`entity_group_id`) REFERENCES `medical_groups` (`group_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_account_memberships_target` CHECK (
    (`profile_doctor_id` IS NOT NULL AND `entity_group_id` IS NULL)
    OR (`profile_doctor_id` IS NULL AND `entity_group_id` IS NOT NULL)
  ),
  CONSTRAINT `ck_auth_account_memberships_role` CHECK (`role_code` IN ('owner','administrator','collaborator')),
  CONSTRAINT `ck_auth_account_memberships_status` CHECK (`status` IN ('pending','active','suspended','revoked')),
  CONSTRAINT `ck_auth_account_memberships_scope` CHECK (CHAR_LENGTH(TRIM(`scope_code`)) > 0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
