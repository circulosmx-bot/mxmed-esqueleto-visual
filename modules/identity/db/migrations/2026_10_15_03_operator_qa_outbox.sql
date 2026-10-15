-- OP-IMP02. Prepared only. Applied solely to the new isolated synthetic QA DB.
CREATE TABLE IF NOT EXISTS auth_operator_invitation_outbox (
 invitation_id VARCHAR(64) PRIMARY KEY,
 recipient VARCHAR(190) NOT NULL,
 ciphertext BLOB NULL,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 opened_at DATETIME(6) NULL,
 retired_at DATETIME(6) NULL,
 FOREIGN KEY(invitation_id) REFERENCES auth_profile_operator_invitations(invitation_id),
 CHECK ((ciphertext IS NOT NULL AND opened_at IS NULL AND retired_at IS NULL) OR (ciphertext IS NULL AND (opened_at IS NOT NULL OR retired_at IS NOT NULL)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
