-- GOV03B-FIX01. NOT AUTHORIZED FOR NORMAL-LOCAL. Apply only to an owned disposable DB.
-- Existing evidence is deliberately not upgraded/backfilled into trusted v2 evidence.
ALTER TABLE auth_governance_cases ADD COLUMN security_context JSON NULL;
ALTER TABLE auth_staff_mfa_factors ADD COLUMN enrollment_context JSON NULL;
ALTER TABLE auth_staff_mfa_challenges ADD COLUMN reauthentication_context JSON NULL;
ALTER TABLE auth_external_trust_anchor_evidence
  ADD COLUMN contract_version SMALLINT UNSIGNED NULL,
  ADD COLUMN operation VARCHAR(40) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN profile_doctor_id VARCHAR(64) NULL,
  ADD COLUMN membership_id VARCHAR(64) NULL,
  ADD COLUMN membership_version CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NULL,
  ADD COLUMN requester_account_id VARCHAR(64) NULL,
  ADD CONSTRAINT fk_gov03b_trust_profile FOREIGN KEY (profile_doctor_id) REFERENCES profiles_doctors(doctor_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_gov03b_trust_membership FOREIGN KEY (membership_id) REFERENCES auth_account_memberships(membership_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT fk_gov03b_trust_requester FOREIGN KEY (requester_account_id) REFERENCES auth_accounts(account_id) ON UPDATE RESTRICT ON DELETE RESTRICT,
  ADD CONSTRAINT ck_gov03b_trust_version CHECK (contract_version IS NULL OR contract_version=2),
  ADD CONSTRAINT ck_gov03b_trust_operation CHECK (operation IS NULL OR operation IN ('FIRST_DIRECTOR_BOOTSTRAP','INITIAL_OWNER','OWNER_REPLACEMENT','OWNER_REMOVAL','MFA_RECOVERY')),
  ADD CONSTRAINT ck_gov03b_trust_membership_version CHECK (membership_version IS NULL OR membership_version REGEXP '^[a-f0-9]{64}$');
