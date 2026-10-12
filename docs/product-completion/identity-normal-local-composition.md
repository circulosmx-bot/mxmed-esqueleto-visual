# ID-LOCAL03A: explicit normal-local Identity foundation

This adds composition and session-store infrastructure only. It does not activate
login on 8091, repair Identity pages, install HTTPS, migrate `mxmed`, provision
accounts, or bridge clinical authority. Those remain subsequent activities.

## Selection and isolation

The process must have `APP_ENV=local` and
`MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT=1`. `MXMED_ENVIRONMENT` must be unset or
exactly `local`. `APP_ENV` takes precedence for existing productive/preview
selection. Legacy `MXMED_ENVIRONMENT=local` alone cannot enable normal-local.
The new flag accepts only absent/`0` (off) or `1` (on); other values fail closed.
With normal-local enabled, `MXMED_PREVIEW_EXPLICIT` must be absent or `0`.
Both modes enabled, unknown environments, and normal-local enablement in
staging/production are rejected. Existing explicit Gate preview selection and
its `mxmed_gate4d_preview_*` guard are retained.

Normal-local permits **only the exact database name `mxmed`**. Every other name,
including Gate, staging and production names, is rejected. This is not an
invitation to use productive data or credentials on loopback.

## Configuration contract

| Variable | Normal-local rule |
|---|---|
| `APP_ENV` | Required, exactly `local` |
| `MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT` | Required, exactly `1` |
| `MXMED_ENVIRONMENT` | Unset or `local` |
| `MXMED_PREVIEW_EXPLICIT` | Unset or `0` |
| `MXMED_DB_NAME` | Required, exactly `mxmed` |
| `MXMED_DB_HOST` | Default and only value: `127.0.0.1` |
| `MXMED_DB_PORT` | Default `3306`; integer 1–65535 |
| `MXMED_DB_USER` | Required; use the local database account |
| `MXMED_DB_PASS` | Local password; defaults to empty, no other password alias |
| `MXMED_NORMAL_LOCAL_VALKEY_HOST` | Default and only value: `127.0.0.1` |
| `MXMED_NORMAL_LOCAL_VALKEY_PORT` | Required, no default; 1–65535 except reserved 6379/6384 |
| `MXMED_NORMAL_LOCAL_SESSION_PREFIX` | Default and only value: `mxmed:local:session:` |
| `MXMED_NORMAL_LOCAL_IDENTITY_PEPPER` | Required, at least 32 bytes; newly generated local-only secret |
| `MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN` | Required, exactly `https://localhost:8443` |
| `MXMED_NORMAL_LOCAL_NOTIFICATION_FILE` | Explicit `/tmp/` file in an existing private directory outside the repo; no Gate path |

Keep secrets in an untracked, owner-readable file **outside the checkout**, or
inject them through the launching process. There is no committed secret, dotenv
loader, secret-generation fallback, production default, or preview/productive
pepper alias. Never reuse productive secrets. Do not log the environment.
The configuration class does not compare against remote secrets; their provenance
is an operator responsibility.

The notification directory must have no group/other permissions; any existing
file must also be private and must not be a symlink. The existing local preview
notification adapter is permitted by D04-04 of
`authentication-productization-contract-v1.md`. No SES client is composed.
The sink is private local development material, not an application log or public
HTTP resource. It is not written during composition.

## Composition and readiness

`IdentityHttpComposition::normalLocal(NormalLocalIdentityConfiguration)` uses
real PDO repositories, credential authentication, account-state checks, canonical
SessionService, and existing membership/capability authorization. It does not
call `preview()` and cannot select a disposable database through runtime config.

Readiness checks read `information_schema.COLUMNS` only. Required tables/columns
cover `auth_accounts`, `auth_account_credentials`, `auth_account_memberships`,
`auth_rate_limit_buckets`, and the two membership parents `profiles_doctors` and
`medical_groups`. Missing objects raise `normal_local_schema_missing:<table>`;
metadata failure raises `normal_local_schema_unavailable`. No DDL or seed occurs.
This is a minimum runtime-dependency guard, not a migration compatibility checker;
ID-LOCAL02B's full schema comparison/rehearsal remains required.

Database connection failure raises `normal_local_database_unavailable`. Store
PING failure raises `normal_local_session_store_unavailable`. These are internal,
non-secret diagnostic codes. Existing HTTP handling continues to return generic
503 `TEMPORARILY_UNAVAILABLE`, never connection details.

The minimum login/current-session schema does not provision consents or one-time
tokens. Registration/verification/recovery require their existing migrations
before later activation. Instantiating their services neither reads those tables
nor substitutes another store. They are not declared ready by the login guard.

## Valkey contract

Start a dedicated loopback Valkey independently, on the explicitly chosen port.
This change does not start or reconfigure any existing daemon. No AWS endpoint,
DNS name, non-loopback address, productive default port, or Gate default port is
accepted. There is no fallback to preview or in-memory storage.

The dedicated RESP client retains its connection for WATCH/MULTI/EXEC. Only
`mxmed:local:session:<digest>` and its account-index keys are accepted. The client
cannot access Gate/staging/production keys; the adapter requires this client for
the new prefix. Normal-local factory use also requires its explicit mode flag.

Records live in Valkey across PHP requests/processes. Persistence here means
server-side request-independent state, not a promise of surviving Valkey restart;
Valkey durability configuration is external. Existing opaque tokens, HMAC
digests, 3600s idle/43200s absolute lifetimes, touch, atomic max-five enforcement,
credential-version/account-state checks, rotation and logout remain canonical.
Secure cookie policy is unchanged. HTTPS front-door setup belongs to ID-LOCAL03B.

## Reproducible isolated QA

Run from the repository root:

```sh
VALKEY_SERVER_BINARY=/absolute/path/to/valkey-server \
  python3 modules/identity/tests/run_normal_local_identity_qa.py
php modules/identity/tests/C2ProductiveIdentityCompositionTest.php
php modules/identity/tests/C3ProductiveSessionContractTest.php
php modules/identity/tests/run_gate4c_session_authorization_test.php
```

The runner owns one Valkey on a dynamically allocated loopback port and stops
only that process. It uses the local MySQL root account with no password to create
randomly named `mxmed_idlocal03a_<hex>` and
`mxmed_gate4d_preview_idlocal03a_<hex>` databases, then drops only databases it
successfully created. It never runs writes against `mxmed`. Adapt test-only
connection setup if the local administrator differs; do not loosen runtime guards.

Assembly is exercised through a private reflection seam against the disposable
schema; public runtime configuration still accepts only `mxmed`. Tests cover
E01–E10, S01–S10, C01–C10, actual credential authentication, independent composition
instances, concurrent PHP workers/max-five, rotation and disabled accounts. The
existing Gate4D test runs as a subprocess against its own disposable preview
schema. Missing-schema tests prove there is no automatic schema creation; empty
account/membership counts prove composition does not provision authority.

No test authenticates a Director or invokes the application on 8091/8092.
