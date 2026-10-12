# ID-LOCAL03B: shared Identity UI and local HTTPS

## Scope and current readiness

The five Identity pages now call `IdentityUiBootstrap::boot()`. It loads the
canonical composition, which registers Identity autoload before constructing
Clock/CSRF services. UI uses `composition->csrf()->issuePreAuth()`; the existing
API validates with `composition->csrf()->validPreAuth()`. There is no separate
UI pepper, origin, expiry or CSRF algorithm. The shared service retains preauth
purpose, HMAC-SHA256, origin binding and the 900-second lifetime.

Bootstrap sets no-store, nosniff and no-referrer. On configuration/schema/store
failure it returns HTTP 503 with a fixed Spanish unavailable document and a
restrictive CSP. No exception, SQL, path or secret is rendered. Success-page HTML
and the client form controller remain unchanged.

The live `mxmed` schema remains incomplete intentionally. The HTTPS home page
can work while Identity pages and current-session return controlled 503. This is
not a completed login environment. No live migrations, accounts, memberships,
clinical authority changes, or prescription changes belong to this phase.

## Tooling and TLS

No repository general-purpose TLS proxy was found. The workstation provides
Python 3 with standard-library SSL and OpenSSL/LibreSSL. The repository-contained
proxy uses those tools; no system package installation is needed. The existing
compiled user-space Valkey binary can be copied to the private runtime directory
for stability. It is not downloaded or installed by the startup script.

A dedicated CA and leaf are prepared under:

```text
~/Library/Application Support/MXMED/local-identity-tls/
  ca.crt
  ca.key
  localhost.crt
  localhost.key
```

The CA is named `MXMED Normal Local Web Development CA`. The leaf has
`DNS:localhost` and `IP:127.0.0.1` SANs, serverAuth EKU, CA:false, SHA-256 and a
365-day lifetime. Keys/configuration live outside Git under owner-only directories.
Preparation never reuses the clinical-document `qa-ca` or modifies macOS trust.
Existing material is not automatically rotated; partial material requires review.

Topology:

```text
https://localhost:8443
  → Python TLS listener 127.0.0.1:8443
  → PHP http://127.0.0.1:8091
```

The proxy forwards paths, query strings and bodies to the fixed backend. It
preserves Origin, Referer, Cookie and CSRF headers, and supplies canonical Host
`localhost:8443`. It never substitutes an allowed Origin. It rejects other Hosts,
absolute request targets, ambiguous Content-Length and incoming chunked bodies.
Hop-by-hop and client-supplied forwarding headers are stripped. Each Set-Cookie is
forwarded independently. No access logging of URLs/tokens occurs. TLS >=1.2.

No redirect is added on 8091: it remains the backend/developer health origin.
Identity browser use is through HTTPS; no Secure-cookie exception is added.
The proxy is a loopback development service, not production deployment tooling.

## Prepare and start

From the intended checkout:

```sh
python3 scripts/normal-local-identity.py prepare --valkey-binary /absolute/path/to/existing/valkey-server
python3 scripts/normal-local-identity.py start --replace-dev-servers
```

`prepare` creates dedicated certificates and private runtime configuration once.
Review the local DB connection settings in the private JSON before starting if
the workstation uses something other than local root/no password. The file
contains a newly generated local-only pepper; do not display, copy into Git or
reuse it in staging/production.

```text
~/Library/Application Support/MXMED/local-identity-runtime/environment.json
```

Config retains ID-LOCAL03A guards: APP_ENV=local, explicit normal-local flag,
exact database mxmed, exact origin https://localhost:8443, Valkey 127.0.0.1:6385,
namespace mxmed:local:session:. Notifications use an owner-only
`/tmp/mxmed-normal-local-identity-<uid>/` directory.

The parent orchestrator keeps `dev-servers.sh` unchanged. It launches PHP 8091
and 8092 with the existing script's flags; 8092 also receives MXMED_API_BASE for
8091. Processes are independently detached and recorded in private state.
`--replace-dev-servers` is the explicit one-time restart needed to load the new
environment into existing PHP processes. It checks that PHP ports belong to the
current checkout before using the existing stop command. An occupied 8443 owned
by another process is a blocker; the URL is never silently changed.

Subsequent commands:

```sh
python3 scripts/normal-local-identity.py start
python3 scripts/normal-local-identity.py status
python3 scripts/normal-local-identity.py stop-proxy
python3 scripts/normal-local-identity.py stop
```

Stop commands act only on recorded processes whose command still matches. They
do not remove certificates, secrets or database objects. Valkey has no snapshot
or AOF in this development launcher: sessions persist across PHP requests, but
are intentionally discarded on Valkey restart. No fixture is loaded into that
normal-local store. The main review services remain running after verification.

## Manual Safari trust: required before browser acceptance

The script does not install trust. The Director can run this command in Terminal
to import **only the public CA certificate** into the user login keychain, with
SSL trust constrained to localhost, and complete macOS authentication if asked:

```sh
security add-trusted-cert -r trustRoot -p ssl -s localhost \
  -k "$HOME/Library/Keychains/login.keychain-db" \
  "$HOME/Library/Application Support/MXMED/local-identity-tls/ca.crt"
```

Then verify native trust:

```sh
security verify-cert \
  -c "$HOME/Library/Application Support/MXMED/local-identity-tls/localhost.crt" \
  -p ssl -s localhost
```

Open `https://localhost:8443/` in Safari and confirm there is no certificate
warning. Do not bypass TLS warnings or import either private key. Identity pages
will still show controlled unavailability until the separately authorized schema
phase. Automated explicit-CA verification is not physical Safari verification.

## QA

Live checks use the dedicated CA and hostname verification, never insecure TLS:

```sh
python3 modules/identity/tests/normal_local_https_runtime_test.py --restart-owned-proxy
```

This reads home/assets/current-session and all five pages, verifies loopback/SAN,
and optionally stops/restarts only the owned proxy while checking source and
Identity metadata stay unchanged. It does not authenticate to live mxmed.

Disposable UI/API checks and ID-LOCAL03A regression:

```sh
IDLOCAL_UI_QA=1 \
VALKEY_SERVER_BINARY="$HOME/Library/Application Support/MXMED/local-identity-runtime/valkey-server" \
python3 modules/identity/tests/run_normal_local_identity_qa.py
php modules/identity/tests/C2ProductiveIdentityCompositionTest.php
php modules/identity/tests/C3ProductiveSessionContractTest.php
```

The opt-in UI test runs only inside the existing random Gate fixture database.
It starts temporary PHP/TLS listeners, renders all five successful pages, submits
the actual UI CSRF token to the real API, verifies wrong-origin/token rejection,
and uses the fixture account to prove a Secure cookie survives proxy transport
and authenticates current-session. It logs out and cleans up owned resources.
Separate checks exercise expiry and preview/normal-local token isolation using
canonical CSRF services. No Director session or credential is created.

Physical Safari remains AWAITING_DIRECTOR until the Director completes trust and
browser verification. Schema application belongs to a later authorized phase;
Identity-to-clinical authority remains ID-LOCAL04.
