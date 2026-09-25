# VIS29-R5 — Navigation repair and prior-value reuse audit

Baseline: `3e425df0169c0b1ef7d80f521fda6830ee65febb`.

The Stepper and the shared Anterior/Siguiente footer use the existing M7
`transitionToSection` path. Empty captures already navigated. Reproduction found
that selecting **Usar valor** creates a dirty, incomplete measurement draft with
no `Origen`; the accepted safe-save guard keeps the physician on Mediciones.
The visible message after trying to leave asked the physician to register or
cancel, but **Cancelar captura** then opened a second native confirmation.
Dismissing that confirmation retained the draft and kept navigation blocked.

The explicit cancel action now clears a prepared prior value without a second
confirmation. It clears the reuse highlight/draft through the existing
`fillMeasurement(null)` path. An ordinary new capture still uses its accepted
replacement guard, including its confirmation. A prepared prior value now has
an immediate status message explaining how to register it or cancel before
changing steps. Stepper/footer handlers and the clinical save path were not
replaced. Invalid and partial pending measurements continue to block step
changes; eligible valid new measurements still use the accepted safe-save path.

## Why direct one-click registration remains unavailable

The canonical `clinical_observation_validate` and the database source constraint
accept exactly `direct_measurement`, `patient_report`, and `import`.
`direct_measurement` requires a remeasurement; `patient_report` requires a
patient report; `import` would misdescribe reuse from a prior MXMed observation.
`provenance_json` is stored but has no accepted schema or source enum for
physician reaffirmation/reuse and cannot supply the missing source meaning.
The POST route requires a valid source and the existing create idempotency key.

Accordingly **Usar valor** keeps the review/edit form path. The physician must
select a truthful source before explicitly adding a new current observation.
No direct write, provenance enum, source enum or backend writer was added in
this visual block. A separate clinical authority decision is required for the
specific meaning "physician explicitly reaffirmed this prior MXMed value in the
current encounter" before a direct **Usar en esta consulta** button can write.

Focused browser QA is recorded in `vis29-r5-navigation.json` and
`vis29-r5-regression.json` under the Director review artifact directory. The
navigation matrix covers 1440, 1366, 820 and 390 widths, step clicks, footer
next/previous, terminal step, pending-prior blocking, explicit cancel and
horizontal overflow. The functional regression covers new measurement safe
save, failure/conflict guards, editing, reuse and idempotency with intercepted
writes against the synthetic review runtime.
