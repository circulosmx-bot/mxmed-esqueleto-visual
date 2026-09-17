# DB (FUTURO)
Aquí vivirán schema/migraciones del módulo clinical.
# Encounter ownership (CLIN-ATTR01)

`clinical_encounters.doctor_id` is the canonical owner of an encounter. New
encounters require an authenticated doctor context and an active doctor–patient
link; an appointment, when supplied, must belong to that doctor and patient.
The column stays nullable for legacy rows. The additive migration at
`migrations/2026_09_17_clinical_encounter_doctor_attribution.sql` backfills only
matching appointment-linked encounters. A legacy `NULL` is unattributed: do not
infer its doctor from patient linkage or user/operator IDs, and exclude it from
future per-doctor archive metrics until ownership is verified.
