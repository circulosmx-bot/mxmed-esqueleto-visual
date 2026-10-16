-- OP-IMP07: rehearsal-only, never normal-local or existing QA environments.
CREATE TEMPORARY TABLE op07_disposable_guard(n INT CHECK(n=1));
INSERT INTO op07_disposable_guard VALUES(IF(DATABASE() REGEXP '^mxmed_op01_[a-f0-9]{10}$' OR DATABASE()='mxmed_gate4d_preview_opimp07',1,0));
DROP TEMPORARY TABLE op07_disposable_guard;
-- Empty-only recovery. Populated snapshots must be retained; disable consumers instead.
CREATE TEMPORARY TABLE op07_empty_guard(n BIGINT CHECK(n=0));
INSERT INTO op07_empty_guard SELECT COUNT(*) FROM agenda_appointment_creator_snapshots;
DROP TEMPORARY TABLE op07_empty_guard;
DROP TRIGGER IF EXISTS op07_snapshot_insert_binding;
DROP TRIGGER IF EXISTS op07_original_creator_guard;
DROP TRIGGER IF EXISTS op07_snapshot_no_delete;
DROP TRIGGER IF EXISTS op07_snapshot_no_update;
DROP TABLE agenda_appointment_creator_snapshots;
ALTER TABLE agenda_appointments DROP INDEX uq_op07_appointment_profile;
