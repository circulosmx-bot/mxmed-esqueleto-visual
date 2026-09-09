# Canonical MP01C R2 authority (MR11.5Q2)

This directory is one logical bootstrap unit, `mxmed.audit.mp01c.r2.v1`.
The manifest binds both SQL definitions and their exact privilege package.
It does not activate a global bootstrap or provision accounts.

## Recovery and semantic authority

The source and SHA-256 values in the manifest identify the accepted external
A1-G1 R2 offline authority and privilege delta recovered from local Downloads.
Their checksums matched their original authority manifests. The subsequent
20260823T045029Z completion record and PP-325 (commit d0f6a37) confirm the final
R2 installation, restricted definer and 17/17 privileges with zero extras.
These are recovered authority artifacts, not the simplified SQL test fixtures.
The procedure signatures and bodies are preserved; only the installation
principal and packaging comments/delimiters differ. No runtime code changes.

## Installation contract for resumed MR11.5Q

1. Verify every package SHA-256 against `AuditMp01CR2RoutineManifest.json`.
2. Require schema `mxmed`, InnoDB stream heads with primary key `stream_key`
   varchar(191), `last_sequence_number` bigint, `last_event_hash` char(64),
   nullable `hash_version` varchar(32), nullable `updated_at` datetime(6),
   and utf8mb4_unicode_ci text collation. Require the canonical audit events
   table and accepted append-only schema/trigger authority for writer operation.
   R2 comes **after** the head alignment, not before it.
3. Separately provision a restricted definer and runtime writer. Bind
   `{{RESTRICTED_DEFINER}}` and `{{WRITER}}` to explicitly validated, SQL-quoted
   account user/host pairs. Reject missing bindings, identical principals,
   master/application definer and implicit CURRENT_USER fallback. Never take
   SQL fragments from untrusted input. Account/secret/host provisioning belongs
   to MR11.5Q; this package intentionally contains no credentials.
4. Install lock then CAS SQL, using DELIMITER-aware execution. Apply the exact
   privilege SQL after both routines exist. Do not silently replace an existing
   differing routine: the future installer must adjudicate it before mutation.
5. Mechanically verify SQL SECURITY DEFINER, signatures, and exactly the
   manifest's 17 semantic privileges. Remove no existing grants implicitly;
   unexpected grants block readiness. An installer identity is not a definer.
   The accepted local identity `'mxmed_audit_head_definer_local'@'127.0.0.1'`
   is provenance only, not an RDS host choice.

Writer: SELECT/INSERT events and heads, EXECUTE both routines (6 privileges).
Definer: SELECT five head columns, UPDATE four head columns excluding key,
EXECUTE both routines (11 privileges). No direct writer UPDATE, table-wide
head UPDATE, SUPER, GRANT OPTION, extra schema/global privileges or roles.

Lock returns the four adapter columns and retains FOR UPDATE until the caller
commits/rolls back. CAS preserves accepted validation, next-sequence rule,
NULL-safe version/time comparisons, timestamp conversion and SQLSTATE 45000.
Neither routine commits the caller transaction. RDS trigger policy from Q1
remains unchanged; this package does not execute SET GLOBAL.

## Disposable validation

Run `python3 scripts/db/r2-authority-test.py` from any directory with Docker,
PHP PDO MySQL and Python 3 available. It creates only a new MySQL 8.4 container,
uses synthetic identities/data, binds a random loopback port, and removes the
container at exit. No application connection config or persistent DB is used.
Root provisions the disposable schema; the real adapter/writer/identity
producer run as the six-privilege writer with the eleven-privilege definer.
Tests cover exact grants, denied operations, lock release on commit/rollback,
CAS success/staleness/invalid sequence, real producer hash reconstruction and
rollback after insertion and after CAS. This is local semantic evidence,
not physical staging/RDS validation. Global bootstrap remains deferred.

Q2 regression evidence: MP01B exact eight-file migration bundle, MP01C static,
D01-D10 and focused closure, A1-G1 datetime mapping, MP01E identity/session
producer, MP01H postvalidation, and Q1 disposable trigger-policy tests pass.
The supplemental historical MP01H static activation-readiness test fails
`hidden_productive_wiring_zero` in both clean baseline 8a98d91 and clean
candidate exports; it predates accepted productive activation and is not a
Q2 regression. Running it over the working directory also scans ignored local
copies and fails its single-authority check. No historical test was rewritten.
