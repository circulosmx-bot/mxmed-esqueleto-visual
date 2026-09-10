# IW01 internal governance foundation

Director, Master Administrator and Advisor are governance classes, not operational permission bundles. The Director is the root governance authority but must hold each explicit operational capability to use a product module. No global administrator bypass exists.

`InternalGovernanceService` resolves the actor through the canonical HTTP session resolver and references `auth_accounts`. It registers an internal relationship for an existing account; it creates no credentials or session system. This backend service has no HTTP endpoint. A future adapter must enforce method and CSRF validation before calling it.

Only the Director appoints/removes Masters and establishes/revokes per-capability delegations. A Master requires an active internal relationship, `internal_advisors_manage`, and an explicit Director delegation for each operational capability they grant or revoke. Masters can manage Advisors only: no self-management, Master management, Director modification or recursive delegation. Demotion revokes management grants and delegations, retaining operational grants and history.

`internal_operator_grants` remains the sole operational assignment authority. `internal_capability_delegations` records authority to administer a capability, which never implies authority to use it. The system-defined `InternalCapabilityCatalog` contains the six existing Media Review capabilities and the single advisor-management capability. Unknown keys, wildcards and arbitrary database strings cannot create executable authority.

Internal suspension changes only `internal_staff.status`. Both generic capability resolution and governance mutations require ACTIVE internal status. Canonical accounts, credentials, sessions, physician memberships and historical records remain intact. Reactivation respects revoked grants. Media Review remains the first consumer of explicit capabilities; its own authentication, capability names, publication and replacement behavior are unchanged.

## Migration and deployment prerequisite

Apply `modules/identity/db/migrations/2026_09_10_01_internal_governance.sql` to the canonical identity database after the existing account and operator-grant migrations, before deploying the changed capability resolver. Deployment without this prerequisite fails closed. The migration backfills every existing grant holder, including historical/revoked holders, as an ACTIVE Advisor only when no staff row exists. It does not modify grants, account status, or existing staff state. Inactive accounts and revoked grants remain denied. There is no runtime missing-row fallback.

The migration seeds no Director, Master or delegation. A separately authorized productive Director bootstrap is required before productive governance administration. A unique generated slot permits at most one Director. No productive account was assigned in IW01; all exercised assignments are disposable synthetic fixtures.

Governance mutations lock actor and target account/staff rows in sorted order, recheck authority, mutate and append `INTERNAL_GOVERNANCE_CHANGED` through the existing canonical writer on the same PDO transaction. Audit failure rolls the mutation back. The canonical audit tables/routines and identity tables must be available to that connection with existing canonical audit configuration. Actor, target, operation, capability, staff state, time and correlation are retained. There is no parallel audit store or read-event audit expansion.

## Deferred product authority

Future Call Center authorization is ACTIVE_INTERNAL_USER + CALL_CENTER_OPERATIONAL_CAPABILITY + DOCTOR_HAS_CALL_CENTER_SERVICE_ACTIVE. There is no advisor-to-doctor assignment. Future V1 permits finding enabled doctors, viewing availability, creating appointments and adding patients to the waitlist. Cancellation, rescheduling, schedule/block changes and service activation remain excluded. No Call Center keys, entitlement, endpoint or schema is implemented.

Generic OWN/TEAM/GLOBAL scope persistence is deferred until an implemented module needs it. The existing authorization boundary is the extension point; current grants remain compatible.

IW01 ends at this backend foundation. IW02 and all frontend implementation are deferred. The next phase requires a separate Director-led visual definition and approval.

## Validation

`node modules/media/tests/OwnerMediaReviewHttpTest.mjs --keep` runs the governance database test in a disposable local database, then existing MR12A HTTP/browser, corrected-upload, SOURCE/ZIP, archive and capacity-16 coverage. `InternalGovernanceTest.php` proves fresh/backfill migration, canonical identity, Director/Master/Advisor boundaries, unknown-capability rejection, suspension/reactivation, membership preservation and atomic audit rollback. Identity/session and canonical audit contract regression tests also pass.

`AuditMp01HStaticReadinessTest.php` is unchanged. In clean exported source trees, both baseline 77376d0d1aec9e9e4178037ad0e087914b4e6695 and IW01 reproduce the known `hidden_productive_wiring_zero` failure. Local generated application copies must be excluded when comparing this recursive static source scan.
