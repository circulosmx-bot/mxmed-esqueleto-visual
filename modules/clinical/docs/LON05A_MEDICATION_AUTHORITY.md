# LON05A medication episode and reconciliation authority

This chapter adds backend authority only. The default writer gate is closed; `MXMED_LON05A_WRITE_ENABLED=1` is required for mutation. It does not activate the working system, connect to the working `mxmed` database, or introduce a medication UI.

`ACTIVE_CONFIRMED` means a clinician explicitly confirmed current use. `REPORTED_BY_PATIENT` preserves an unconfirmed patient report. `PRESCRIBED_NOT_CONFIRMED_ACTIVE` preserves prescription evidence without asserting current use. `DISCONTINUED` and `COMPLETED` are terminal for one episode. Restart after a terminal state requires a new explicit episode; name similarity never merges episodes. No encounter, prescription, passage of time, or free-text mention invokes this authority automatically.

The schema leaves an optional external-source field for later attributable authority, but this API does not accept external or legacy-import provenance without a verified source contract.

The patient-scoped API is under `/api/clinical/index.php/patients/{patient_id}/longitudinal`:

* `GET /medications`, `GET /medications/{id}`, `GET /medications/history`, `GET /medications/{id}/history` return scoped episode state and immutable audit. An empty list remains `UNREVIEWED`, not clinically confirmed none.
* `POST /medications` explicitly creates an `ACTIVE_CONFIRMED` clinician entry. `POST /medications/patient-reported` creates `REPORTED_BY_PATIENT`. `POST /medications/from-prescription` requires a same-doctor/patient, generated or signed prescription document with canonical encounter attribution and an explicit `initial_state` of `PRESCRIBED_NOT_CONFIRMED_ACTIVE` or `ACTIVE_CONFIRMED`.
* `PATCH /medications/{id}` updates the full stated regimen with `expected_version` and a reason. `POST /medications/{id}/confirm-active|discontinue|complete` performs the named explicit transition, also with version and reason.
* `GET /medication-reconciliations` and `GET /medication-reconciliations/{id}` return scoped review history and item decisions. `POST /medication-reconciliations` accepts `expected_list_version`, optional note, and 1–100 explicit decisions (`ADD`, `CONFIRM_ACTIVE`, `UPDATE_REGIMEN`, `DISCONTINUE`, `COMPLETE`, `KEEP_UNCONFIRMED`, `UNRESOLVED`). Changed episodes require their own `expected_version`. The entire review, decisions, episode changes, audit, list-version increment, and idempotency receipt share one transaction.

Every mutation requires `Idempotency-Key`, the doctor/patient link, the clinical write window, and—where applicable—`row_version`. Direct episode changes also increment the aggregate list version, so a reconciliation based on a stale list cannot overwrite them. The immutable medication ledger records before/after state, actor, operation, reason, timestamp, and reconciliation link. Reconciliation items preserve decisions that leave an episode unresolved or unconfirmed.

The migration is additive and rerunnable. No legacy data is backfilled. The accepted LON03A longitudinal idempotency table is reused; LON05A uses its own immutable medication audit because the shared LON03A audit entity CHECK is closed. Run `bash modules/clinical/qa/lon05a_disposable_gate.sh` and `bash modules/clinical/qa/lon05a_disposable_http_gate.sh`; each creates and drops only a uniquely named disposable database.
