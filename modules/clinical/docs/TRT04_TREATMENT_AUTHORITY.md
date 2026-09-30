# TRT04 canonical treatment authority

Apply `db/migrations/2026_09_30_13_treatment_authority.sql` after the identity, patient, encounter, Agenda, and clinical idempotency migrations. It is forward-only and does not reinterpret legacy procedure documents. The disposable gate is `modules/clinical/qa/trt04_disposable_gate.sh`; it applies the migration to a fresh fixture, reapplies it with populated clinical data, and exercises service and HTTP routes.

## Authorities

- `clinical_treatment_plans` and `clinical_treatment_sessions` are the only treatment domain authorities.
- `clinical_performer_authorizations` stores doctor-scoped `TREATMENT_PERFORMER` and `TREATMENT_RESPONSIBLE_PROVIDER` grants plus a clinical role label for historical display. This label is not a credential or license authority. A grant is usable only while the account and doctor-profile membership are active. Revocation is retained; a new grant gets a new row.
- `clinical_treatment_plan_audit_events` is append-only lifecycle infrastructure. It is written in the same transaction as each plan creation or transition.
- `clinical_idempotency_requests` is extended in place with treatment operations, typed result references, and response snapshots. Existing operation rows are retained.

Only a trusted administrative provisioning flow should issue or revoke clinical grants. TRT04 does not expose an HTTP grant-management route.

## HTTP routes

All routes are under `/api/clinical/index.php?route=patients/{patient_id}/treatments/...`. The doctor scope and actor account come from the server session; request bodies cannot set them. Writes require `Content-Type: application/json`. The six idempotent commands require `Idempotency-Key`. State changes require `expected_version` in the JSON body.

| Method | Suffix | Purpose |
| --- | --- | --- |
| GET / POST | `plans` | List / create plan |
| GET | `plans/{id}` | Read plan |
| POST | `plans/{id}/transition` | Change plan status |
| GET / POST | `sessions` | List latest session versions / create draft |
| GET / PATCH | `sessions/{id}` | Read session / edit draft |
| POST | `sessions/{id}/complete` | Complete draft |
| POST | `sessions/{id}/void` | Void latest draft or completed version |
| POST | `sessions/{id}/correct` | Create a completed successor version |
| GET | `sessions/standalone-history` | Read eligible latest completed standalone sessions |

`POST plans/{id}/transition` takes `status`, `expected_version`, and optional `reason`. Session completion takes ordered `procedure_items` and `performed_local`, `performed_timezone`, `performed_utc_offset_minutes`; the stored `performed_at` is UTC. A correction takes `correction_reason` and any corrected clinical fields. A void takes a nonempty `reason`.

The standalone-history reader is preparation for a later Historial integration; TRT04 adds no UI rows or filters.
