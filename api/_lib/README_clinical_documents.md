# Clinical documents backend

Purpose
- Document-centric storage of clinical notes, prescriptions, orders, and related artifacts.

Tables
- clinical_documents: main immutable document record (payload_json, rendered_text, summary).
- clinical_document_participants: users linked to a document (role, participation_type).
- hospital_stays: inpatient episodes (if enabled in this repo).

Statuses
- draft
- generated
- signed
- voided

Key context fields
- patient_id: required identifier for patient scope.
- encounter_id: optional for visit-level context.
- hospital_stay_id: optional for inpatient episode context.
- care_setting: consulta | urgencias | hospitalizacion.

Content fields
- payload_json: structured raw data captured from UI or integrations.
- rendered_text: final clinical text for printing/viewing (NOM-004).
- summary: short string used by timeline widgets/cards.

Endpoints
- POST api/evolution-note-generate.php
- GET api/clinical-documents.php?action=list&patient_id=...&limit=...

Frontend note
- patient_id must be URL-encoded (encodeURIComponent), especially when it contains "|" ("%7C").
