-- OP-IMP01 recovery ONLY AFTER disabling composition and archiving audit/history.
-- Refuse destructive recovery of populated history. For populated
-- deployments retain tables and disable consumers; never restore the legacy writer.
CREATE TEMPORARY TABLE op01_empty_recovery_guard(n BIGINT CHECK(n=0));
INSERT INTO op01_empty_recovery_guard(n)
 SELECT COUNT(*) FROM auth_profile_operator_invitations
 UNION ALL SELECT COUNT(*) FROM auth_profile_operator_grant_revisions
 UNION ALL SELECT COUNT(*) FROM auth_profile_operator_grants
 UNION ALL SELECT COUNT(*) FROM auth_profile_operator_commands;
DROP TEMPORARY TABLE op01_empty_recovery_guard;
DROP TABLE IF EXISTS auth_profile_operator_commands;
DROP TABLE IF EXISTS auth_profile_operator_grants;
DROP TABLE IF EXISTS auth_profile_operator_grant_revisions;
DROP TABLE IF EXISTS auth_profile_operator_invitations;
