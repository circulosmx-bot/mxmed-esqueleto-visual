# MEAS01 — Current-open observation invalidation

Baseline: `2378fd317c214bbb977e977990d7edcbe91bbee5`.

The existing observation model supported create and PATCH only. Encounter VOID
provides the reusable convention (UTC timestamp, authenticated actor, required
reason); it cannot invalidate an individual observation. Encounter amendments
are restricted to closed encounters and are not a parallel writer for this flow.

## Command

`POST /api/clinical/index.php/encounters/{encoded_key}/observations/{id}/void`

```json
{"patient_id":"current-patient-id","row_version":1}
```

The route retains existing doctor authentication, patient scope, cohort eligibility,
and write-window admission. Patient context is mandatory and checked against the
encounter. The repository locks the encounter before updating the observation,
using the same lock order as PATCH/finalization. It rechecks doctor, patient and
OPEN state. Observation ID, encounter ID, current version and active state must
all match. Stale or repeated commands return `VERSION_CONFLICT` (409); terminal
encounters return `ENCOUNTER_TERMINAL` (409); patient context mismatch returns 403.
There is no automatic retry, hard DELETE, restore, or terminal-history writer.

The confirmed UI action uses the canonical reason `Captura errónea`. Existing
conventions require a reason but do not require a new free-text interaction.
The authenticated user, UTC invalidation time and reason are stored together;
`row_version` increases once. Original value, measurement time, provenance,
recording time and recording actor remain unchanged. PATCH cannot update an
invalidated record. Idempotent create replay cannot make it active again.

## Migration and reads

Apply `modules/clinical/db/migrations/2026_09_24_11_observation_invalidation.sql`
before deploying the changed read/write code. It adds nullable `invalidated_at`,
`invalidated_by_user_id`, and `invalidation_reason` plus a consistency constraint.
All existing observations remain active. Migration is rerunnable; schema shape
checks reject column drift. Canonical readiness checks require these fields and
the constraint. No feature-gate configuration is changed.

`GET /api/clinical/index.php?route=encounters/{key}` returns active rows in
`data.observations` and complete invalidated rows in `data.invalidated_observations`
under the same existing encounter authorization.

`GET /api/clinical/index.php?route=patients/{patient}/longitudinal/measurements&view=history`
also retains invalidated observations, including actor/time/reason and
`ineligibility_reason=INVALIDATED`. The longitudinal history UI labels them
**Invalidada**. They are excluded before ranking from `prior`, `series`, `latest`
and `points` reads, so older eligible observations remain usable.

The delete confirmation explains audit retention and defaults focus to Cancelar.
No request occurs before confirmation. On success the UI rereads the canonical
encounter. An unrelated capture remains intact. Failed/conflicting commands leave
the row visible and explain that its state must be reviewed.

## Verification

- `php tests/clinical/vis29-prior-measurements.php`: in-memory prior fallback and audit projection.
- `php modules/clinical/qa/encounter_integrity_pure_test.php`: existing integrity regression.
- `tests/clinical/meas01-invalidation.py`: real HTTP and browser test against an
  isolated synthetic review clone, never a working database. Requires
  `MEAS01_QA_DB` beginning `mxmed_meas01_qa_`, `MEAS01_QA_BASE` on loopback, and
  `MEAS01_QA_ARTIFACTS`. It uses the existing synthetic `review-patient` fixtures,
  authenticated review session and a separate wrong-doctor QA session.

The implementation was verified in a disposable clone of the existing synthetic
Director review database. Only the additive migration was applied to the Director
review database; measurement mutation tests ran against the disposable clone.
The working MXMed database and existing feature gates were not changed.

Known baseline QA limitation: `encounter_integrity_static_check.sh` stops at its
search for `clinical_v1_multipart_document_write_allowed` in the router. That
symbol is already absent from baseline `2378fd317c214bbb977e977990d7edcbe91bbee5`;
this unrelated document-route static assertion was not changed by MEAS01.
