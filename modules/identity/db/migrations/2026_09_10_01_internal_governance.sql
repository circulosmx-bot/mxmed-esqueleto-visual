-- IW01. Apply to the canonical identity database BEFORE deploying the resolver.
-- No Director, Master, operational grant, or delegation is seeded.
CREATE TABLE IF NOT EXISTS internal_staff (
 account_id VARCHAR(64) NOT NULL PRIMARY KEY,
 governance_class ENUM('DIRECTOR','MASTER_ADMIN','ADVISOR') NOT NULL,
 status ENUM('ACTIVE','SUSPENDED') NOT NULL,
 director_slot TINYINT GENERATED ALWAYS AS (CASE WHEN governance_class='DIRECTOR' THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
 UNIQUE KEY uniq_internal_director (director_slot),
 FOREIGN KEY (account_id) REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE IF NOT EXISTS internal_capability_delegations (
 delegation_id CHAR(36) NOT NULL PRIMARY KEY,
 master_account_id VARCHAR(64) NOT NULL,
 capability VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
 authorized_by_account_id VARCHAR(64) NOT NULL,
 status ENUM('ACTIVE','REVOKED') NOT NULL,
 active_slot TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
 created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
 revoked_at DATETIME(6) NULL,
 UNIQUE KEY uniq_internal_delegation (master_account_id,capability,active_slot),
 FOREIGN KEY (master_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 FOREIGN KEY (authorized_by_account_id) REFERENCES internal_staff(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
 CHECK (capability REGEXP '^[A-Za-z0-9_.:-]{1,128}$' AND capability NOT IN ('all','admin.everything','support.all')),
 CHECK ((status='ACTIVE' AND revoked_at IS NULL) OR (status='REVOKED' AND revoked_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Preserve all historical holders, including revoked grants, without reactivating any grant.
-- Re-running never changes an existing staff status/class. Not a runtime fallback.
INSERT INTO internal_staff(account_id,governance_class,status)
 SELECT DISTINCT g.account_id,'ADVISOR','ACTIVE' FROM internal_operator_grants g
 JOIN auth_accounts a ON a.account_id=g.account_id
 LEFT JOIN internal_staff s ON s.account_id=g.account_id WHERE s.account_id IS NULL;
