-- IDENTITY-QA02B: durable evidence for one governed initial profile-owner transition.
-- This migration creates no approval, step-up proof, membership, role, or authority.
CREATE TABLE IF NOT EXISTS `auth_profile_ownership_approvals` (
  `approval_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `decision_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `approver_account_id` VARCHAR(64) NOT NULL,
  `executor_account_id` VARCHAR(64) NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `membership_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NOT NULL,
  `expected_role` VARCHAR(32) NOT NULL,
  `expected_scope` VARCHAR(64) NOT NULL,
  `new_role` VARCHAR(32) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'APPROVED',
  `approved_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL DEFAULT NULL,
  `consumed_operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`approval_reference`),
  UNIQUE KEY `ux_auth_ownership_approval_operation` (`operation_id`),
  UNIQUE KEY `ux_auth_ownership_approval_decision` (`decision_reference`),
  UNIQUE KEY `ux_auth_ownership_approval_consumption` (`consumed_operation_id`),
  KEY `idx_auth_ownership_approval_target` (`target_account_id`, `membership_id`, `profile_doctor_id`, `status`),
  KEY `idx_auth_ownership_approval_expiry` (`status`, `expires_at`),
  CONSTRAINT `fk_auth_ownership_approval_approver` FOREIGN KEY (`approver_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_approval_executor` FOREIGN KEY (`executor_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_approval_target` FOREIGN KEY (`target_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_approval_membership` FOREIGN KEY (`membership_id`) REFERENCES `auth_account_memberships` (`membership_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_approval_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_ownership_approval_roles` CHECK (`expected_role` IN ('administrator','collaborator') AND `new_role` = 'owner'),
  CONSTRAINT `ck_auth_ownership_approval_scope` CHECK (`expected_scope` IN ('profile','profile_doctor')),
  CONSTRAINT `ck_auth_ownership_approval_independent` CHECK (`approver_account_id` <> `target_account_id`),
  CONSTRAINT `ck_auth_ownership_approval_window` CHECK (`expires_at` > `approved_at`),
  CONSTRAINT `ck_auth_ownership_approval_state` CHECK (
    (`status` = 'APPROVED' AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
    OR (`status` = 'CONSUMED' AND `consumed_at` IS NOT NULL AND `consumed_operation_id` = `operation_id`)
    OR (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_profile_ownership_step_up_proofs` (
  `step_up_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `proof_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `approval_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `account_id` VARCHAR(64) NOT NULL,
  `session_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL,
  `membership_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NOT NULL,
  `expected_role` VARCHAR(32) NOT NULL,
  `expected_scope` VARCHAR(64) NOT NULL,
  `new_role` VARCHAR(32) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'VERIFIED',
  `verified_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL DEFAULT NULL,
  `consumed_operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`step_up_id`),
  UNIQUE KEY `ux_auth_ownership_step_up_hash` (`proof_hash`),
  UNIQUE KEY `ux_auth_ownership_step_up_operation` (`operation_id`),
  UNIQUE KEY `ux_auth_ownership_step_up_consumption` (`consumed_operation_id`),
  KEY `idx_auth_ownership_step_up_account` (`account_id`, `session_id`, `status`),
  KEY `idx_auth_ownership_step_up_expiry` (`status`, `expires_at`),
  CONSTRAINT `fk_auth_ownership_step_up_approval` FOREIGN KEY (`approval_reference`) REFERENCES `auth_profile_ownership_approvals` (`approval_reference`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_step_up_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_step_up_membership` FOREIGN KEY (`membership_id`) REFERENCES `auth_account_memberships` (`membership_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_step_up_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_ownership_step_up_hash` CHECK (`proof_hash` REGEXP '^[a-f0-9]{64}$'),
  CONSTRAINT `ck_auth_ownership_step_up_version` CHECK (`credential_version` >= 1),
  CONSTRAINT `ck_auth_ownership_step_up_roles` CHECK (`expected_role` IN ('administrator','collaborator') AND `new_role` = 'owner'),
  CONSTRAINT `ck_auth_ownership_step_up_scope` CHECK (`expected_scope` IN ('profile','profile_doctor')),
  CONSTRAINT `ck_auth_ownership_step_up_window` CHECK (`expires_at` > `verified_at`),
  CONSTRAINT `ck_auth_ownership_step_up_state` CHECK (
    (`status` = 'VERIFIED' AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
    OR (`status` = 'CONSUMED' AND `consumed_at` IS NOT NULL AND `consumed_operation_id` = `operation_id`)
    OR (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
