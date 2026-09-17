-- Stable roles for the five contact inputs in Expediente > Datos generales.
-- NULL preserves legacy/unclassified rows; no existing contact is rewritten.
ALTER TABLE patients_contacts
  ADD COLUMN contact_role VARCHAR(32) NULL AFTER preferred_contact_method,
  ADD UNIQUE KEY uq_patient_contact_role (patient_id, contact_role);
