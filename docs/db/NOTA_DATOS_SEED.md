## Datos de BD después de QA (I.5)

- Se eliminó todo lo generado por `QA_MODE=ready` (patient_id='demo'), usado exclusivamente para pruebas temporales, en:
  - `agenda_appointments`
  - `agenda_appointment_events`
  - `agenda_patient_flags`

- Se conservaron 2 registros de ejemplo creados desde el “panel” (no-QA), considerados datos canónicos de referencia, identificables por:
  - `actor_role=operator`, `actor_id=u_1`, `channel_origin=panel`
  - `patient_id` pseudonimizado (`p_...`)
  - `doctor_id=d_1`, `consultorio_id=c_1`

Objetivo: mantener ejemplos realistas y persistentes para validaciones futuras y para correcta comprensión por IA, sin contaminar la base con datos de QA.
