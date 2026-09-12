# Verified physician credentials (CRD02)

Apply `2026_09_12_create_profiles_doctor_credentials.sql` after the existing
`profiles_doctors` and `internal_staff` authorities. The migration is repeatable
and does not backfill legacy values. SQL stays outside the application package,
following the existing packaging boundary.

`VerifiedDoctorCredentialService` is an internal callable service, not a new
HTTP write API. Supply a backend-created `TrustedAuditContext` with the active
internal staff account and session. The service creates its canonical writer
with a joined adapter on the repository's exact PDO connection, owns the outer
transaction, and rejects nested invocation. No mutation commits without audit.

Provisioning defaults to `PENDING_REVIEW`; initial `VERIFIED` provisioning is
also supported with complete data. `verify(id, review, context)` requires fresh
source provenance and may complete the area/institution. The verifier and time
are assigned from the trusted actor and server clock. Only internal provisioning
may omit a source reference. Synthetic provenance requires explicit constructor
opt-in and still requires an active internal actor. Licenses remain strings.

The private HTTP projection uses the existing server-owned physician session
scope. Legacy open requests and identity headers alone receive no credential
records. Public consumers and physician PATCH allowlists remain unchanged.
The primary reference has an ownership FK; eligibility must be checked through
`assertEligiblePrimarySpecialty` before any future authorized selection flow.
CRD02 intentionally adds no primary selection mutation or audit event.

## Disposable QA

Run only against a disposable local container with an empty root password:

```sh
MXMED_CRD02_DISPOSABLE=1 MXMED_CRD02_DISPOSABLE_PORT=3317 php modules/profiles/tests/DoctorCredentialPhysicalTest.php
node modules/profiles/tests/DoctorCredentialHydrationTest.mjs
```

The physical test refuses an existing `mxmed` database, creates synthetic data,
uses the existing canonical audit SQL/routines, tests real rollback and HTTP,
and drops its database in `finally`. The browser test serves repository sources
with synthetic responses in an isolated browser profile and accepts no writes.
Set `MXMED_CRD02_SCHEMA_ONLY=1` for migration-only compatibility checks.

Verified: MySQL 8.4 full integration and MariaDB 11.4 migration. The unchanged
canonical R2 CAS routine uses `REGEXP_LIKE`, which prevents full audit integration
on MariaDB 11.4 (error 1305). This pre-existing audit portability limitation is
outside CRD02; no replacement routine or parallel writer is introduced.
