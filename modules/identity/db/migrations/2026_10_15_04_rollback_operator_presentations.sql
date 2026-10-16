-- OP-IMP07: rehearsal-only, never normal-local or existing QA environments.
CREATE TEMPORARY TABLE op07_disposable_guard(n INT CHECK(n=1));
INSERT INTO op07_disposable_guard VALUES(IF(DATABASE() REGEXP '^mxmed_op01_[a-f0-9]{10}$' OR DATABASE()='mxmed_gate4d_preview_opimp07',1,0));
DROP TEMPORARY TABLE op07_disposable_guard;
-- Run snapshot recovery first. Never discard populated assignment/alias history.
CREATE TEMPORARY TABLE op07_empty_guard(n BIGINT CHECK(n=0));
INSERT INTO op07_empty_guard SELECT COUNT(*) FROM auth_profile_operator_presentations;
DROP TEMPORARY TABLE op07_empty_guard;
DROP TRIGGER IF EXISTS op07_assignment_identity_guard;
DROP TABLE auth_profile_operator_presentations;
ALTER TABLE auth_profile_operator_invitations DROP INDEX uq_op07_invitation_profile;
ALTER TABLE auth_account_memberships DROP INDEX uq_op07_creator_profile, DROP INDEX uq_op07_membership_profile;
