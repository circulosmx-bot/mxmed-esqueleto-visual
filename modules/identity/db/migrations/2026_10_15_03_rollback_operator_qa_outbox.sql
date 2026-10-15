-- OP-IMP02 recovery is prepared only. Preserve populated delivery history.
CREATE TEMPORARY TABLE op02_empty_outbox_guard(n BIGINT CHECK(n=0));
INSERT INTO op02_empty_outbox_guard SELECT COUNT(*) FROM auth_operator_invitation_outbox;
DROP TEMPORARY TABLE op02_empty_outbox_guard;
DROP TABLE auth_operator_invitation_outbox;
