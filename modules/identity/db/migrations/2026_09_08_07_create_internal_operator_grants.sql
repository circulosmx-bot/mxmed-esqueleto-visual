-- Prerequisite: canonical auth_accounts migration. No grants are seeded.
CREATE TABLE internal_operator_grants (
    grant_id CHAR(36) NOT NULL PRIMARY KEY,
    account_id VARCHAR(64) NOT NULL,
    capability VARCHAR(128) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    status ENUM('ACTIVE','REVOKED') NOT NULL,
    active_slot TINYINT GENERATED ALWAYS AS (CASE WHEN status='ACTIVE' THEN 1 ELSE NULL END) STORED,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    revoked_at DATETIME NULL,
    UNIQUE KEY uniq_internal_operator_active (account_id, capability, active_slot),
    CONSTRAINT fk_internal_operator_account FOREIGN KEY (account_id)
        REFERENCES auth_accounts(account_id) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT chk_internal_operator_capability CHECK
        (capability REGEXP '^[A-Za-z0-9_.:-]{1,128}$' AND capability NOT IN ('all','admin.everything','support.all')),
    CONSTRAINT chk_internal_operator_revoked CHECK
        ((status='ACTIVE' AND revoked_at IS NULL) OR (status='REVOKED' AND revoked_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
