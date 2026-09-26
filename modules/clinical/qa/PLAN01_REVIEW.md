# PLAN01 disposable Director review

The Director PHP router intercepted longitudinal task routes and returned its own
404 for creation. Appointment list/detail responses were fixtures, including an
appointment absent from the canonical database. Neither successful list loading
nor those detail responses demonstrated canonical persistence.

## Router setup

Use only the verified local synthetic database `mxmed_director_review_lon07c`.
Before any fixture route handlers in the local PHP development router:

```php
require_once $repo . '/modules/clinical/qa/plan01_review_routes.php';
if (plan01_review_canonical_route((string) $path)) return false;
```

Remove the old task/appointment fixture handlers and the separate Agenda database
override. The helper checks the effective configuration (including any local
config file), local server and loopback client. It opts this review request into
the existing longitudinal write gate. Canonical session, ownership, write-window,
appointment eligibility and idempotency checks still execute. Product entry points
do not include this helper; global defaults are unchanged.

## Disposable storage alignment

The review database contained a minimal appointment table. Preserve it as
`plan01_legacy_appointments`, then apply the existing Agenda definitions:

- `modules/agenda/db/ready_schema.sql`
- `modules/agenda/db/availability_catalog_schema.sql`
- `modules/agenda/db/availability_overrides_min.sql`
- `modules/agenda/db/consultorios_schema.sql`

Copy the synthetic appointment identities into the canonical table with coherent
doctor, patient, consultorio, start/end and modality. Restore `fk_task_appointment`
to the canonical `agenda_appointments` target after the rename, using its existing
`ON DELETE RESTRICT ON UPDATE RESTRICT` definition. MySQL follows a renamed table;
leaving this foreign key pointing at the backup would reject valid new links.
This is disposable existing-schema setup, not a product migration.

Retain the established synthetic schedule/override fixtures: doctor 1,
consultorio 1, Monday–Saturday 09:00–12:00, 30 minutes, and the September 26
10:00–10:30 closure. September 28 remains six available slots. Materialize
`review-fup01-appointment` for `review-patient`, doctor 1, consultorio 1,
October 2 16:30–17:00, confirmed. Preserve existing tasks; seed the formerly
display-only review tasks through `ClinicalLongitudinalTasks::mutate` with stable
idempotency keys. Never substitute a fabricated POST success.

## Reuse inputs for PLAN02

| Component | Existing UI / caller | Authority and context | Result / retry |
| --- | --- | --- | --- |
| Order | Consultation → Órdenes y resultados; `m7-ws04.js` | POST `clinical/index.php/encounters/{key}/documents`; existing document route and `ClinicalEncounterIntegrityService::idempotentCreate`; current patient/doctor/open encounter; `document_type=order`, title, summary, event_datetime, payload | document ID/UUID and actual status; retained attempt key, server replay |
| Appointment | Patient → Agenda via existing navigation handoff → available slot → Nueva cita; `app.js` / `AgendaApiClient.createAppointment` | POST `agenda/index.php/appointments`; `AppointmentWriteController` / `AppointmentWriteRepository`; doctor, patient, consultorio, start/end, modality, channel and actor | appointment ID; read detail for lifecycle status (response `created` describes operation); in-flight UI guard and occupied-slot validation, not exact-response idempotency |
| Follow-up | Consultation → Plan → Crear seguimiento; `lon06b-tasks.js` | POST `clinical/index.php/patients/{patient}/longitudinal/tasks`; `ClinicalLongitudinalTasks::mutate/read`; title, FOLLOW_UP, optional due/appointment, source encounter; session doctor ownership | task ID, OPEN; Idempotency-Key and authoritative readback; modal preserves Plan |

Use existing safe navigation and patient handoff when entering Agenda; calling
`showPanel` alone skips that handoff. Each action retains its separate result.
Order creation is a free-text title/indication record, not a study catalog or
automatic printable/signed artifact. Follow-ups support source encounter and
appointment relationships, but no canonical relationship to specific orders.
Same-encounter membership does not supply that relationship. PLAN02 must also
reconcile the booking lifecycle status with follow-up appointment eligibility;
do not relabel a tentative appointment as confirmed.

No integrated Plan UI, new domain writer or product schema is introduced here.

## Executed validation (synthetic only)

All four follow-up variants were created through the browser, retried with the
original keys, read by ID, reloaded and displayed in patient tasks and Agenda.
The appointment-only variant retained a null deadline. Wrong-patient/doctor
references returned `409 FOREIGN_APPOINTMENT`; a canceled appointment returned
`409 INELIGIBLE_APPOINTMENT`. Cancel wrote nothing, and an intentionally failed
request preserved the form. The modal fit 1440, 1366, 820 and 390 pixel viewports.
Plan narrative and an unsaved draft were preserved. Resolve/Cancel used new QA
tasks only; four Agenda groups and attention badge arithmetic remained intact.

The existing order form created document 14, state `generated`, attached to
patient `review-patient` and encounter 1001; canonical readback and keyed replay
passed. No binary or signature was generated by this form.

Agenda correctly handed off the Director patient through its leave-with-open-
consultation confirmation, but creation rejected `review-patient` because that
fixture ID does not satisfy the existing canonical booking-ID format. This is
another review identity limitation, not a reason to relax product validation.
The independent booking test explicitly selected a separate seeded synthetic
patient `p_plan01_booking` through the registered-patient UI. It booked September
30, 09:00–09:30, doctor/consultorio 1. Detail readback returned `tentative`, not
`confirmed`; replaying the booking returned `409 collision`, with one appointment
and five remaining slots. September 28 still had six slots. The patient record
was not duplicated, no encounter was created, and encounter 1001 remained open
with its original appointment reference. This separate booking does not claim
an appointment for `review-patient` or a new end-to-end integrated Plan flow.

PLAN02 has specific gaps: direct follow-up/order linkage, structured studies or
printable output if required, lifecycle eligibility of newly tentative bookings,
and reconciliation beyond sequential collision protection (the existing slot
unique key excludes tentative rows). No concurrent booking guarantee is claimed.
