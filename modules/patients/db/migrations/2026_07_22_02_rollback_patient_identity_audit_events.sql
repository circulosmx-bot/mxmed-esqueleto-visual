-- Declarative Gate 8G rollback. This file is not executed by the gate.
DROP TRIGGER IF EXISTS reject_patient_identity_audit_events_update;
DROP TRIGGER IF EXISTS reject_patient_identity_audit_events_delete;
DROP TABLE IF EXISTS patient_identity_audit_events;
