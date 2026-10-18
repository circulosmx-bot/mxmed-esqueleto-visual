-- ASE-INT03B-IMP01. Explicit isolated rehearsal / separately authorized deployment only.
-- Apply after the selected installed prerequisite package; never replay ACT03 after this.
-- ACT02 with correction rows -> historical ACT03 remains a blocked upgrade path.
-- No seeds, backfill, role changes, dropped history or disabled constraints.
ALTER TABLE auth_governance_cases
 DROP CHECK ck_auth_governance_case_operation,
 ADD CONSTRAINT ck_auth_governance_case_operation CHECK (operation IN (
  'FIRST_DIRECTOR_BOOTSTRAP',
  'INITIAL_OWNER',
  'INTERNAL_ADVISOR_MANAGEMENT_GRANT',
  'INTERNAL_ADVISOR_MANAGEMENT_REVOKE',
  'INTERNAL_CAPABILITY_DELEGATE',
  'INTERNAL_CAPABILITY_DELEGATION_REVOKE',
  'INTERNAL_MASTER_APPOINT',
  'INTERNAL_MASTER_REMOVE',
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
