-- PROV04B: an account claim is separate from organization review, provider
-- verification, commercial entitlement and clinical access. No rows are seeded.
CREATE TABLE IF NOT EXISTS healthcare_organization_claims (
  claim_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  claimant_account_id VARCHAR(64) NOT NULL,
  group_id VARCHAR(64) NOT NULL,
  requested_role ENUM('owner') NOT NULL,
  status ENUM('PENDING','APPROVED','REJECTED','CANCELED') NOT NULL DEFAULT 'PENDING',
  evidence_type ENUM('NONE','BUSINESS_EMAIL','PHONE','REPRESENTATIVE_DOCUMENT','OTHER') NOT NULL DEFAULT 'NONE',
  submission_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  request_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  submitted_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  reviewed_by_account_id VARCHAR(64) NULL,
  reviewed_at DATETIME(6) NULL,
  decision_reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
  pending_identity_key VARCHAR(320) GENERATED ALWAYS AS
    (CASE WHEN status='PENDING' THEN CONCAT(claimant_account_id,':',group_id) ELSE NULL END) STORED,
  PRIMARY KEY (claim_uuid),
  UNIQUE KEY uq_healthcare_claim_submission (claimant_account_id,submission_key),
  UNIQUE KEY uq_healthcare_claim_pending_identity (pending_identity_key),
  KEY idx_healthcare_claim_group_status (group_id,status),
  CONSTRAINT fk_healthcare_claim_account FOREIGN KEY (claimant_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_healthcare_claim_group FOREIGN KEY (group_id)
    REFERENCES medical_groups(group_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_healthcare_claim_reviewer FOREIGN KEY (reviewed_by_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT ck_healthcare_claim_review CHECK (
    (status IN ('PENDING','CANCELED') AND reviewed_by_account_id IS NULL AND reviewed_at IS NULL)
    OR (status IN ('APPROVED','REJECTED') AND reviewed_by_account_id IS NOT NULL AND reviewed_at IS NOT NULL)
  ),
  CONSTRAINT ck_healthcare_claim_request_hash CHECK (request_hash REGEXP '^[0-9a-f]{64}$')
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS healthcare_organization_claim_events (
  event_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  claim_uuid CHAR(36) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  actor_account_id VARCHAR(64) NOT NULL,
  action ENUM('SUBMITTED','APPROVED','REJECTED','CANCELED') NOT NULL,
  previous_status ENUM('PENDING','APPROVED','REJECTED','CANCELED') NULL,
  new_status ENUM('PENDING','APPROVED','REJECTED','CANCELED') NOT NULL,
  reason_code VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  correlation_key VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NULL,
  created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
  PRIMARY KEY (event_id),
  KEY idx_healthcare_claim_event_history (claim_uuid,event_id),
  CONSTRAINT fk_healthcare_claim_event_claim FOREIGN KEY (claim_uuid)
    REFERENCES healthcare_organization_claims(claim_uuid) ON UPDATE RESTRICT ON DELETE RESTRICT,
  CONSTRAINT fk_healthcare_claim_event_actor FOREIGN KEY (actor_account_id)
    REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
