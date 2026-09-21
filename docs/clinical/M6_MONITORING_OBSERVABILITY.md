# M6 monitoring observability

This repository provides an optional, file-backed M6 observability authority. It is inert until
`MXMED_CLINICAL_M6_OBSERVABILITY_ROOT` points to a private absolute directory outside the web document root.
It does not activate the M6 feature gate or write window and never mutates either authority.

Initialize the shared directory with the same OS account/group used by the HTTP workers:

```sh
php scripts/clinical-m6-monitor.php init /private/shared/mxmed-m6-monitoring
```

The initializer requires directories without world permissions. Event files use mode `0660`; immutable
aggregate baselines and snapshots use `0440`. The active NDJSON file rotates at 16 MiB by default and retains
one previous segment. Aggregate evidence defaults to 14 days. Operators may set
`MXMED_CLINICAL_M6_OBSERVABILITY_MAX_BYTES` and `MXMED_CLINICAL_M6_OBSERVABILITY_RETENTION_DAYS` within the
validated bounds.

Runtime events contain an opaque request correlation ID, selected clinical authority, safe operation and
error labels, HTTP outcome, duration, effective gate fingerprint/generation, and write-window status. They
exclude clinical contents, raw patient or doctor identifiers, tokens, credentials, raw allowlists, paths,
filenames, and binary data. A telemetry write failure is non-fatal to clinical persistence and is reported to
the PHP error log as `M6_MONITORING_VISIBILITY_LOSS`; rollout decisions must treat loss of visibility as a hold.

Operator commands are read-only except for writing monitoring evidence into the configured private directory:

```sh
php scripts/clinical-m6-monitor.php heartbeat
php scripts/clinical-m6-monitor.php status --minutes 30
php scripts/clinical-m6-monitor.php status --since 2026-09-20T12:00:00Z
php scripts/clinical-m6-monitor.php baseline before-cohort --minutes 30
php scripts/clinical-m6-monitor.php compare before-cohort --minutes 30
php scripts/clinical-m6-monitor.php snapshot cohort-1 --minutes 30
php scripts/clinical-m6-monitor.php decision HOLD VISIBILITY_REVIEW
```

Set `MXMED_CLINICAL_M6_WORKER_GENERATION` to the deployment generation expected after worker reload. Status
reports that value, a non-identifying process hash, the effective configuration fingerprint, monitoring
self-health, route/status/error and latency aggregates, storage failures, write-window state, rule results,
and the optional `MXMED_CLINICAL_SCHEMA_READINESS_EVIDENCE` artifact reference. Thresholds live in
`modules/clinical/monitoring/m6_monitoring_rules.json`; the latency threshold remains unset until accepted by
the Director. Recorded decisions are evidence only and cannot change the feature gate.
