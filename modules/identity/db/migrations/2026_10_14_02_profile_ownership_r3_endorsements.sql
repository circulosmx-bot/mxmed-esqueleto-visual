-- IDENTITY-QA02E: proposed R3 dual-approval evidence. Prepared only; do not apply without separate authorization.
CREATE TABLE IF NOT EXISTS `auth_profile_ownership_approval_endorsements` (
  `endorsement_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `approval_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `endorsement_slot` TINYINT UNSIGNED NOT NULL,
  `operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `approver_account_id` VARCHAR(64) NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `membership_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NOT NULL,
  `capability` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `staff_session_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL,
  `mfa_evidence_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'APPROVED',
  `approved_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL DEFAULT NULL,
  `consumed_operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NULL DEFAULT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`endorsement_id`),
  UNIQUE KEY `ux_auth_ownership_endorsement_slot` (`approval_reference`,`endorsement_slot`),
  UNIQUE KEY `ux_auth_ownership_endorsement_approver` (`approval_reference`,`approver_account_id`),
  UNIQUE KEY `ux_auth_ownership_endorsement_mfa` (`mfa_evidence_reference`),
  KEY `idx_auth_ownership_endorsement_operation` (`operation_id`,`status`),
  CONSTRAINT `fk_auth_ownership_endorsement_approval` FOREIGN KEY (`approval_reference`) REFERENCES `auth_profile_ownership_approvals` (`approval_reference`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_endorsement_approver` FOREIGN KEY (`approver_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_endorsement_target` FOREIGN KEY (`target_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_endorsement_membership` FOREIGN KEY (`membership_id`) REFERENCES `auth_account_memberships` (`membership_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_ownership_endorsement_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_ownership_endorsement_slot` CHECK (`endorsement_slot` IN (1,2)),
  CONSTRAINT `ck_auth_ownership_endorsement_capability` CHECK (`capability` = 'profile_initial_ownership_approve'),
  CONSTRAINT `ck_auth_ownership_endorsement_independent` CHECK (`approver_account_id` <> `target_account_id`),
  CONSTRAINT `ck_auth_ownership_endorsement_version` CHECK (`credential_version` >= 1),
  CONSTRAINT `ck_auth_ownership_endorsement_window` CHECK (`expires_at` > `approved_at`),
  CONSTRAINT `ck_auth_ownership_endorsement_state` CHECK (
    (`status` = 'APPROVED' AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
    OR (`status` = 'CONSUMED' AND `consumed_at` IS NOT NULL AND `consumed_operation_id` = `operation_id`)
    OR (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL AND `consumed_operation_id` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
