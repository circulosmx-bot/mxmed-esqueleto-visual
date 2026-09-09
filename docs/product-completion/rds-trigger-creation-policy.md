# MR11.5Q1 — RDS_PARAMETER_GROUP_TRUST_FUNCTION_CREATORS_V1

The canonical MySQL 8.4 parameter group permanently sets
`log_bin_trust_function_creators=1` in staging and production. This is an
infrastructure policy, not a migration-time toggle. No AWS deployment or physical
parameter change is performed by this phase.

## Productive inventory

Six unique triggers, eight definitions across four tracked SQL files:

| Source under modules/ | Trigger names | Purpose |
| --- | --- | --- |
| platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql | platform_audit_events_no_update, platform_audit_events_no_delete | Append-only audit protection |
| platform/db/migrations/2026_07_27_01_expand_platform_audit_events_audit_v1.sql | platform_audit_events_no_update, platform_audit_events_no_delete | Canonical MP01B recreation of the same guards |
| platform/db/migrations/2026_07_27_04_guard_platform_audit_events_audit_v1.sql | platform_audit_events_audit_v1_shadow_no_update, platform_audit_events_audit_v1_shadow_no_delete | Append-only shadow audit protection |
| patients/db/migrations/2026_07_22_02_create_patient_identity_audit_events.sql | reject_patient_identity_audit_events_update, reject_patient_identity_audit_events_delete | Append-only patient identity audit protection |

All six remain canonical. No accepted integrity guard is removed. B1 supersedes
the earlier definitions of two names without making the original migration obsolete.
No additional legacy/nonexecuted trigger definition was identified in tracked
non-test SQL. Documentation mentions are not executable definitions.

TEST_ONLY: media test fixtures create failure-injection triggers for atomicity,
rollback and HTTP probes (mr5/mr6/mr7/mr8/mr9/mr10/mr11, lossless and empty-batch
cases). Some interpolate timing/event or trigger names, so literal text counts
are not a count of distinct runtime fixtures. Platform/patient static tests also
assert trigger text without creating triggers. None of these belongs to product
migration authority. The new disposable QA installs exact canonical trigger bodies
on minimal synthetic tables; it does not claim to bootstrap the complete schema.

## AWS behavior and privilege separation

[AWS documents](https://repost.aws/knowledge-center/rds-mysql-functions) that
binary logging can prevent CREATE TRIGGER without SUPER (ERROR 1419), and that
the custom parameter is dynamic and does not itself require reboot. MySQL 8.4
also lists it as a [dynamic global variable](https://dev.mysql.com/doc/refman/8.4/en/dynamic-system-variables.html).
Physical attachment/effective state must still be verified before migration;
this source phase does not assert that an instance has applied it.

The setting relaxes the binary-log safety restriction and can allow unsafe
stored-program behavior. It is not risk-free and does not grant TRIGGER,
CREATE ROUTINE, ALTER ROUTINE or schema DDL privileges to anyone. Only the
migration/bootstrap authority may author these objects. Initial bootstrap may
use the RDS-managed master credential, without granting SUPER.

Future `mxmed_app` grants must exclude SUPER, SYSTEM_USER, CREATE USER,
GRANT OPTION, TRIGGER, CREATE ROUTINE, ALTER ROUTINE, CREATE, ALTER, DROP and INDEX.
Approved DML and narrowly approved EXECUTE remain separate from object-authoring
privileges. The disposable application user proves this separation, not an
already deployed or fully bootstrapped production principal.

Backups, binary logging, ROW format, engine 8.4.9 and family mysql8.4 remain
unchanged. No migration SET GLOBAL, AWS parameter API call, or ON/OFF automation
is introduced. Any future engine upgrade must revalidate this policy.

## Future bootstrap precondition

DataStack deploys RDS with the canonical parameter group → parameter becomes
effective → explicitly invoked migration/bootstrap task reads
`SELECT @@GLOBAL.log_bin_trust_function_creators` and fails closed unless it is 1
→ trigger-bearing migrations → application principal/grants → verification.
The bootstrap consumes the infrastructure setting; it does not own or change it.
No global migration runner, ledger or bootstrap entrypoint is added here.

## Reproducible local validation

Run `python3 scripts/db/trigger-policy-test.py`. It starts isolated MySQL 8.4
containers with binary logging enabled, no published ports and startup trust
values 0 and 1; it never changes host MySQL or runs SET GLOBAL. All containers
are stopped in finally blocks. Synthetic local-only users have no passwords and
are reachable solely inside the disposable container.

The non-SUPER migration identity must get ERROR 1419 with trust=0 and install
all eight canonical definitions with trust=1. The restricted application
identity must fail all eleven trigger/routine/schema/user/grant negative tests.
INSERT succeeds and UPDATE/DELETE fail with canonical SIGNAL messages on all
three protected tables. This reproduces MySQL's error semantics, not all RDS
managed-master permissions: RDS parity remains partial until physical validation.

MP01B's historical contract test expects its exact eight-file artifact bundle;
run it against an isolated copy of those tracked files via its root argument.
Its 51 checks include manifest checksums, B4 guard cardinality and trigger text.
Data tests and the DataFoundationAspect enforce the parameter alongside existing
TLS, charset, collation, logging and event-scheduler settings.
