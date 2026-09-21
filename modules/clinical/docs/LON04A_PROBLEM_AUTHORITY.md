# LON04A longitudinal problem authority

The source adds a patient-scoped backend only. `MXMED_LON04A_WRITE_ENABLED=1` is required for mutations; the default is fail-closed. No working database or feature gate is activated by this chapter.

`ACTIVE` means a currently present, clinically relevant longitudinal condition; stable or controlled chronic disease can remain active. `INACTIVE` means a valid quiescent, remitting, episodic or recurrent history item that currently needs no active management. `RESOLVED` means the clinician explicitly determined the condition concluded or is no longer present. No status is inferred from encounter content, diagnosis matching, prescriptions, time, or lack of mentions.

Allowed explicit transitions: `ACTIVE -> INACTIVE` (`mark-inactive`), `ACTIVE|INACTIVE -> RESOLVED` (`resolve`), `INACTIVE -> ACTIVE` (`activate`), and `RESOLVED -> ACTIVE` (`reactivate`). `RESOLVED -> INACTIVE` is rejected. The same `problem_id` survives every transition; prior resolution and reactivation remain in immutable audit. A distinct problem may always be created explicitly, even when label/code matches an existing one. Creating a problem never asserts an empty problem list means “no active problems”; the read response remains `UNREVIEWED` because there is no problem-list review authority in LON04A.

Routes under `/api/clinical/index.php/patients/{patient_id}/longitudinal/problems`:

* `GET /`, `GET /{problem_id}`, `GET /history`, `GET /{problem_id}/history` are scoped reads.
* `POST /` creates an explicit ACTIVE problem with `label`, optional paired `code_system`/`code_value`, optional `onset_date`, and `provenance` of `EXPLICIT_LONGITUDINAL_ENTRY` or `PATIENT_REPORTED`.
* `POST /promotions` explicitly creates a new ACTIVE problem from a nonempty assessment section. It requires `source_encounter_id` and `source_section_id`, each belonging to the same authorized doctor/patient. The clinician supplies and attests the problem identity; the command does not scrape, infer, match or auto-promote assessment text.
* `PATCH /{problem_id}` edits identity with `expected_version` and `reason`.
* `POST /{problem_id}/resolve|mark-inactive|activate|reactivate` performs the named explicit transition with `expected_version` and `reason`. Optional same-scope assessment evidence can be attached to the audit event, including a reactivation.

Every mutation requires an `Idempotency-Key`, checks the clinical write window, compares `row_version` where applicable, and commits current state, immutable audit and the LON03A longitudinal idempotency receipt atomically. LON03A's shared audit table has a closed entity-type CHECK, so LON04A uses a new immutable problem audit ledger rather than altering LON03A's accepted table. The migration is additive and rerunnable. No encounter-owned writer or legacy backfill is introduced.

Run `bash modules/clinical/qa/lon04a_disposable_gate.sh`; it creates and drops only a uniquely named disposable MySQL database.
