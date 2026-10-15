-- IDENTITY-GOV03A: additive R3 governance runtime persistence.
-- Creates no staff, grants, memberships, MFA factors, cases, approvals, or trust evidence.
CREATE TABLE IF NOT EXISTS `auth_governance_cases` (
  `case_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `requester_account_id` VARCHAR(64) NOT NULL,
  `beneficiary_account_id` VARCHAR(64) NOT NULL,
  `target_membership_id` VARCHAR(64) NULL,
  `replacement_membership_id` VARCHAR(64) NULL,
  `profile_doctor_id` VARCHAR(64) NULL,
  `reason` VARCHAR(500) NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'OPEN',
  `expires_at` DATETIME(6) NOT NULL,
  `approved_at` DATETIME(6) NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `active_target_key` VARCHAR(300) GENERATED ALWAYS AS (
    CASE WHEN `status` IN ('OPEN','APPROVED') THEN CONCAT(`operation`,':',`beneficiary_account_id`,':',COALESCE(`profile_doctor_id`,'')) ELSE NULL END
  ) STORED,
  PRIMARY KEY (`case_id`),
  UNIQUE KEY `ux_auth_governance_case_operation` (`operation_id`),
  UNIQUE KEY `ux_auth_governance_case_active_target` (`active_target_key`),
  KEY `idx_auth_governance_case_lifecycle` (`status`,`expires_at`),
  CONSTRAINT `fk_auth_governance_case_requester` FOREIGN KEY (`requester_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_case_beneficiary` FOREIGN KEY (`beneficiary_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_case_target_membership` FOREIGN KEY (`target_membership_id`) REFERENCES `auth_account_memberships` (`membership_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_case_replacement_membership` FOREIGN KEY (`replacement_membership_id`) REFERENCES `auth_account_memberships` (`membership_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_case_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_governance_case_operation` CHECK (`operation` IN ('INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','FIRST_DIRECTOR_BOOTSTRAP','MFA_RECOVERY')),
  CONSTRAINT `ck_auth_governance_case_reason` CHECK (CHAR_LENGTH(TRIM(`reason`)) BETWEEN 8 AND 500),
  CONSTRAINT `ck_auth_governance_case_window` CHECK (`expires_at` > `created_at`),
  CONSTRAINT `ck_auth_governance_case_state` CHECK (
    (`status`='OPEN' AND `approved_at` IS NULL AND `consumed_at` IS NULL) OR
    (`status`='APPROVED' AND `approved_at` IS NOT NULL AND `consumed_at` IS NULL) OR
    (`status`='CONSUMED' AND `approved_at` IS NOT NULL AND `consumed_at` IS NOT NULL) OR
    (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_staff_mfa_factors` (
  `factor_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `account_id` VARCHAR(64) NOT NULL,
  `factor_type` VARCHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `secret_ciphertext` VARBINARY(512) NOT NULL,
  `key_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'PENDING',
  `last_totp_time_step` BIGINT UNSIGNED NULL,
  `verified_at` DATETIME(6) NULL,
  `revoked_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `active_account_key` VARCHAR(64) GENERATED ALWAYS AS (CASE WHEN `status`='ACTIVE' THEN `account_id` ELSE NULL END) STORED,
  PRIMARY KEY (`factor_id`),
  UNIQUE KEY `ux_auth_staff_mfa_active_account` (`active_account_key`),
  CONSTRAINT `fk_auth_staff_mfa_factor_account` FOREIGN KEY (`account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_staff_mfa_factor_type` CHECK (`factor_type`='TOTP'),
  CONSTRAINT `ck_auth_staff_mfa_factor_state` CHECK (
    (`status`='PENDING' AND `verified_at` IS NULL AND `revoked_at` IS NULL) OR
    (`status`='ACTIVE' AND `verified_at` IS NOT NULL AND `revoked_at` IS NULL) OR
    (`status`='REVOKED' AND `revoked_at` IS NOT NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_staff_mfa_challenges` (
  `challenge_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_account_id` VARCHAR(64) NOT NULL,
  `operation` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `beneficiary_account_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NULL,
  `nonce_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'PENDING',
  `attempts_count` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `blocked_until` DATETIME(6) NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  `active_actor_case_key` VARCHAR(257) GENERATED ALWAYS AS (CASE WHEN `status`='PENDING' THEN CONCAT(`actor_account_id`,':',`case_id`) ELSE NULL END) STORED,
  PRIMARY KEY (`challenge_id`),
  UNIQUE KEY `ux_auth_staff_mfa_nonce` (`nonce_hash`),
  UNIQUE KEY `ux_auth_staff_mfa_active_actor_case` (`active_actor_case_key`),
  KEY `idx_auth_staff_mfa_challenge_actor` (`actor_account_id`,`status`,`expires_at`),
  CONSTRAINT `fk_auth_staff_mfa_challenge_actor` FOREIGN KEY (`actor_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_challenge_case` FOREIGN KEY (`case_id`) REFERENCES `auth_governance_cases` (`case_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_challenge_target` FOREIGN KEY (`target_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_challenge_beneficiary` FOREIGN KEY (`beneficiary_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_challenge_profile` FOREIGN KEY (`profile_doctor_id`) REFERENCES `profiles_doctors` (`doctor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_staff_mfa_challenge_state` CHECK (
    (`status`='PENDING' AND `consumed_at` IS NULL) OR (`status`='CONSUMED' AND `consumed_at` IS NOT NULL) OR
    (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_staff_mfa_proofs` (
  `proof_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `proof_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `factor_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `challenge_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_account_id` VARCHAR(64) NOT NULL,
  `staff_session_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL,
  `operation` VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `beneficiary_account_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NULL,
  `totp_time_step` BIGINT UNSIGNED NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'VERIFIED',
  `verified_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `consumed_by_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
  PRIMARY KEY (`proof_id`),
  UNIQUE KEY `ux_auth_staff_mfa_proof_hash` (`proof_hash`),
  UNIQUE KEY `ux_auth_staff_mfa_challenge_once` (`challenge_id`),
  UNIQUE KEY `ux_auth_staff_mfa_totp_replay` (`factor_id`,`totp_time_step`),
  KEY `idx_auth_staff_mfa_proof_action` (`actor_account_id`,`operation`,`case_id`,`status`,`expires_at`),
  CONSTRAINT `fk_auth_staff_mfa_proof_factor` FOREIGN KEY (`factor_id`) REFERENCES `auth_staff_mfa_factors` (`factor_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_proof_challenge` FOREIGN KEY (`challenge_id`) REFERENCES `auth_staff_mfa_challenges` (`challenge_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_staff_mfa_proof_case` FOREIGN KEY (`case_id`) REFERENCES `auth_governance_cases` (`case_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_staff_mfa_proof_version` CHECK (`credential_version` >= 1),
  CONSTRAINT `ck_auth_staff_mfa_proof_window` CHECK (`expires_at` > `verified_at`),
  CONSTRAINT `ck_auth_staff_mfa_proof_state` CHECK (
    (`status`='VERIFIED' AND `consumed_at` IS NULL AND `consumed_by_reference` IS NULL) OR
    (`status`='CONSUMED' AND `consumed_at` IS NOT NULL AND `consumed_by_reference` IS NOT NULL) OR
    (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_governance_case_endorsements` (
  `endorsement_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `endorsement_slot` TINYINT UNSIGNED NOT NULL,
  `approver_account_id` VARCHAR(64) NOT NULL,
  `staff_session_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `credential_version` INT UNSIGNED NOT NULL,
  `mfa_proof_id` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'APPROVED',
  `approved_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  PRIMARY KEY (`endorsement_id`),
  UNIQUE KEY `ux_auth_governance_endorsement_slot` (`case_id`,`endorsement_slot`),
  UNIQUE KEY `ux_auth_governance_endorsement_actor` (`case_id`,`approver_account_id`),
  UNIQUE KEY `ux_auth_governance_endorsement_mfa` (`mfa_proof_id`),
  CONSTRAINT `fk_auth_governance_endorsement_case` FOREIGN KEY (`case_id`) REFERENCES `auth_governance_cases` (`case_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_endorsement_actor` FOREIGN KEY (`approver_account_id`) REFERENCES `internal_staff` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_endorsement_mfa` FOREIGN KEY (`mfa_proof_id`) REFERENCES `auth_staff_mfa_proofs` (`proof_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_governance_endorsement_slot` CHECK (`endorsement_slot` IN (1,2)),
  CONSTRAINT `ck_auth_governance_endorsement_state` CHECK (
    (`status`='APPROVED' AND `consumed_at` IS NULL) OR (`status`='CONSUMED' AND `consumed_at` IS NOT NULL) OR
    (`status`='REVOKED' AND `consumed_at` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_external_trust_anchor_evidence` (
  `trust_anchor_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `operation_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `organization_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `environment_name` VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ratifier_session_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `ratifier_one_id` VARCHAR(64) NOT NULL,
  `ratifier_two_id` VARCHAR(64) NOT NULL,
  `approval_one_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `approval_two_reference` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `payload_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `signature_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `status` VARCHAR(16) NOT NULL DEFAULT 'VERIFIED',
  `authorized_at` DATETIME(6) NOT NULL,
  `expires_at` DATETIME(6) NOT NULL,
  `consumed_at` DATETIME(6) NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`trust_anchor_reference`),
  UNIQUE KEY `ux_auth_trust_anchor_operation` (`operation_id`),
  UNIQUE KEY `ux_auth_trust_anchor_signature` (`signature_hash`),
  CONSTRAINT `fk_auth_trust_anchor_target` FOREIGN KEY (`target_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `ck_auth_trust_anchor_ratifiers` CHECK (`ratifier_one_id` <> `ratifier_two_id` AND `ratifier_one_id` <> `target_account_id` AND `ratifier_two_id` <> `target_account_id`),
  CONSTRAINT `ck_auth_trust_anchor_window` CHECK (`expires_at` > `authorized_at`),
  CONSTRAINT `ck_auth_trust_anchor_state` CHECK (
    (`status`='VERIFIED' AND `consumed_at` IS NULL) OR (`status`='CONSUMED' AND `consumed_at` IS NOT NULL) OR
    (`status` IN ('REVOKED','EXPIRED') AND `consumed_at` IS NULL)
  )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `auth_governance_events` (
  `event_id` CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `case_id` VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `event_type` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `actor_account_id` VARCHAR(64) NOT NULL,
  `target_account_id` VARCHAR(64) NOT NULL,
  `profile_doctor_id` VARCHAR(64) NULL,
  `reason_code` VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `evidence_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  `created_at` DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (`event_id`),
  KEY `idx_auth_governance_event_case` (`case_id`,`created_at`),
  CONSTRAINT `fk_auth_governance_event_case` FOREIGN KEY (`case_id`) REFERENCES `auth_governance_cases` (`case_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_event_actor` FOREIGN KEY (`actor_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT `fk_auth_governance_event_target` FOREIGN KEY (`target_account_id`) REFERENCES `auth_accounts` (`account_id`) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TRIGGER IF EXISTS `auth_governance_events_no_update`;
CREATE TRIGGER `auth_governance_events_no_update`
BEFORE UPDATE ON `auth_governance_events`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'governance_event_update_forbidden';

DROP TRIGGER IF EXISTS `auth_governance_events_no_delete`;
CREATE TRIGGER `auth_governance_events_no_delete`
BEFORE DELETE ON `auth_governance_events`
FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'governance_event_delete_forbidden';
