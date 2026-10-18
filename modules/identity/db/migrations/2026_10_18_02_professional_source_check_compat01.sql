-- ACT03-CHECK-COMPAT01, step 11: cumulative ACT02 + ACT03 CHECK. No Master extension.
-- Before the versioned objects successor on new ACT03 installations.
-- Already-installed ACT03: CHECK-only repair after physical schema/history verification.
-- Never apply over the 17-operation Master schema; preserve it and skip this step.
-- Not idempotent tracking: inspect schema and completed migration evidence before any DDL.
-- Defense against accidental downgrade even when this SQL is invoked outside the planned route.
-- Unknown/partial schemas still require full physical preflight; this guard is not a migration tracker.
CREATE TEMPORARY TABLE act03_compat01_no_master_downgrade (ok TINYINT NOT NULL CHECK(ok=1));
INSERT INTO act03_compat01_no_master_downgrade
 SELECT IF(@@SESSION.foreign_key_checks=1
  AND (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
       WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='auth_governance_cases'
       AND CONSTRAINT_NAME='ck_auth_governance_case_operation' AND ENFORCED='YES')=1
  AND NOT EXISTS (SELECT 1 FROM information_schema.CHECK_CONSTRAINTS
       WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_auth_governance_case_operation'
       AND CHECK_CLAUSE LIKE '%INTERNAL%'),1,0);
DROP TEMPORARY TABLE act03_compat01_no_master_downgrade;
ALTER TABLE auth_governance_cases
 DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK (operation IN (
  'FIRST_DIRECTOR_BOOTSTRAP',
  'INITIAL_OWNER',
  'MFA_RECOVERY',
  'OWNER_REMOVAL',
  'OWNER_REPLACEMENT',
  'PHYSICIAN_AUTHORITY_CHANGE',
  'PHYSICIAN_ENROLLMENT_REVIEW',
  'PHYSICIAN_EVIDENCE_REVIEW',
  'PROFESSIONAL_SOURCE_PROVISION',
  'PUBLIC_PROFILE_CORRECTION_APPLY',
  'PUBLIC_PROFILE_CORRECTION_REVIEW'
 ));
