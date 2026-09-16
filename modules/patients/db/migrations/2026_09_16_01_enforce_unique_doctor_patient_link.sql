-- Restore the doctor/patient pair invariant already declared by ready_schema.sql.
-- Apply once after confirming no duplicate pairs and no existing unique pair key.
ALTER TABLE `patients_doctor_links`
  ADD UNIQUE KEY `uq_links_doctor_patient` (`doctor_id`, `patient_id`);
