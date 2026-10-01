-- PROV04F-PREP: bounded exact-email invitee checks; no email or account result is stored.
CREATE TABLE IF NOT EXISTS healthcare_organization_invitee_resolution_attempts (
  attempt_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  group_id VARCHAR(64) NOT NULL,
  owner_account_id VARCHAR(64) NOT NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (attempt_id),
  KEY idx_provider_invitee_attempt_window (group_id,owner_account_id,created_at),
  CONSTRAINT fk_provider_invitee_attempt_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_provider_invitee_attempt_owner FOREIGN KEY (owner_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
