# AUDIT-MP01H — Static postvalidation and activation readiness

Status: implementation-ready as a dormant readiness contract. This document
does not authorize or perform database execution, productive composition,
staging activation, production cutover, or publication.

## Static conclusion

- The canonical event universe is 28/28 and the policy registry is 28/28.
- MP01E owns the first 13 events and MP01F owns the remaining 15 events.
- There are zero unscoped events and zero duplicate scoped events.
- Operation and source-module mappings cover all 28 events.
- MP01C–MP01G remain byte-identical under the 105-path regression lock.
- Writer, producer and read components remain dormant.
- Static subsystem readiness and the activation plan are complete.
- Productive activation and production cutover are not ready.

The required future read invariant is:

```text
SELF_SUBJECT_SCOPE_PAGINATION_COMPATIBLE=
REQUIRED_BEFORE_PRODUCTIVE_READ_WIRING
```

Subject ownership must constrain the repository query before or within keyset
pagination. A globally paged result followed by subject rejection is not an
acceptable productive self-timeline adapter.

## Readiness matrix

The authoritative executable matrix is
`Platform\Audit\Readiness\AuditMp01HReadiness::readinessMatrix()`.
Its 18 rows use only the ratified status vocabulary:

- `PERSISTENCE_SCHEMA`: `IMPLEMENTED_DORMANT`
- `D11_MIGRATION`: `REQUIRES_DB_EXECUTION`
- `DB_PRIVILEGES`: `REQUIRES_SECRET_OR_PRIVILEGE`
- `DB_SECRETS`: `REQUIRES_SECRET_OR_PRIVILEGE`
- `WRITER_RUNTIME_BINDING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `REQUEST_CONTEXT_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `IDENTITY_PRODUCER_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `SESSION_PRODUCER_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `MP01F_PRODUCER_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `AUDIT_READ_REPOSITORY_ADAPTER`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `SELF_SUBJECT_RESOLVER_ADAPTER`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `AUDIT_READ_ROUTE_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `AUDIT_OF_READ_WIRING`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `STAGING_E2E`: `REQUIRES_STAGING_E2E`
- `ROLLBACK_READINESS`: `STATIC_READY`
- `OBSERVABILITY`: `REQUIRES_RUNTIME_IMPLEMENTATION`
- `PRODUCTION_CUTOVER`: `REQUIRES_DIRECTOR_AUTHORIZATION`
- `POST_CUTOVER_MONITORING`: `REQUIRES_RUNTIME_IMPLEMENTATION`

Every executable row records current evidence, missing prerequisite, future
execution class, authorization boundary and rollback boundary.

## Exact activation order

No step may depend on a later step.

1. A0 — freeze the final static baseline.
2. A1 — provision and verify runtime secrets and least privileges.
3. A2 — execute D11 once under separate database authorization.
4. A3 — verify persistence postconditions.
5. A4 — bind the canonical writer.
6. A5 — bind trusted request, correlation and actor context.
7. A6 — wire identity and session producers.
8. A7 — wire MP01F producers.
9. A8 — bind read repository and self-subject adapters.
10. A9 — wire bounded audit read routes.
11. A10 — wire audit-of-read emission.
12. A11 — execute staging E2E.
13. A12 — complete the authorized staging observation window.
14. A13 — record production go/no-go.
15. A14 — perform separately authorized production cutover.
16. A15 — execute post-cutover monitoring.

## Secret and privilege readiness

Never place secret values in evidence. Future provisioning must cover:

- audit IP HMAC through `AuditSecretProvider`;
- auth-identifier HMAC through `AuthIdentifierAuditSecretProvider`;
- a versioned provider boundary for the audit-read cursor HMAC;
- separate database credentials for migration, writer and read principals.

The canonical event chain currently uses the published SHA-256 hash version;
no keyed sealing provider is published. A hash-version change requires a new
explicit policy.

Least privilege remains separated:

- migration: certified DDL/trigger/metadata scope with physical grant precheck;
- writer: history INSERT/SELECT, stream-head INSERT/SELECT, and EXECUTE only on
  `audit_mp01c_lock_stream_head_v1` and
  `audit_mp01c_advance_stream_head_cas_v1`, with no direct stream-head UPDATE or
  `FOR UPDATE`, DELETE, or LOCK TABLES;
- reader: bounded SELECT only, with no write privilege.

## Staging evidence required

The future staging matrix includes identity success and denial, session
lifecycle, profile claim, ownership/role, step-up success/failure, all four
self-timeline target shapes, cross-account denial, scoped-read allow/deny,
cursor pagination, minimization, and audit-of-read after activation.

Break-glass and eligible sensitive-admin success remain
`DEFERRED_PHYSICAL_FLOW_ABSENT` while no authoritative productive flow exists.
Unknown sensitive-admin catalog entries must still be rejected.

## Rollback policy

Rollback is layered:

- migration: stop and use an approved forward-fix or backup boundary;
- writer: disable the binding, preserving appended history;
- producers: disable bindings, preserving emitted history;
- read route: disable the route and adapters;
- audit-of-read: disable emission independently of authorized reads;
- staging/production: runtime disable and code rollback first.

Schema reversal always requires separate authority. Deletion of valid
append-only audit history is never a normal rollback mechanism.

## Observability prerequisites

Before cutover, provide signals for write attempts/success/failure, producer
counts by type, policy and authorization denials, self-subject denials, read
latency, cursor errors, database errors and chain verification failures.
Published operational authority does not define numerical alert thresholds,
so each remains `TO_BE_SET_BEFORE_CUTOVER`.

## Authorization boundaries

Separate authorization is required for repository implementation, publication,
D11 execution, secret changes, privilege changes, staging activation,
production activation, cutover and rollback. MP01H consumes none of them.

## Historical harness note

The installed MP01B migration contract has 49 semantic assertions that pass in
the full repository. Its two overlay-packaging assertions intentionally expect
an isolated eight-file root and are not cross-phase runtime contracts. MP01H
validates MP01B installed semantics through the manifest, physical hashes and
the published regression baseline rather than treating the full product tree
as an eight-file candidate overlay.
