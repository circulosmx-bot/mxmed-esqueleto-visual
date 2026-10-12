# DEV-WIDGET03 normal-local subscription scenarios

The Director-approved target is doctor `1`. This is a local review entitlement
scenario, not a purchase, payment, renewal, account or clinical authentication.

## Storage and read path

`~/Library/Application Support/MXMED/local-identity-runtime/doctor-1-review-scenario.json`
is a private 0600 JSON record in the existing private runtime directory. A sibling
lock serializes reads/writes; replacement is atomic. It records fixed entity,
plan, timestamps, canonical fingerprint and nullable future actor account id.
`real` removes the record. No database migration or database write is performed.

`CurrentSubscriptionReadModelService` first resolves the real subscription, then
applies the scenario to doctor/1 only when the explicit local environment and
actual loopback mxmed connection match. Contracted plan/dates stay real;
`local_review` distinguishes real plan, selected scenario, applied state and
conflict. Capabilities are recalculated by the existing capability authority.
The fingerprint includes the canonical subscription snapshot and effective
plan/active/grace interpretation. A mismatch falls back to real capabilities,
reports conflict, and prevents reselection until reset to Real. It never restores
or edits contractual rows. Storage failure fails the local request closed.

## Boundary

Required: APP_ENV=local, MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT=1,
MXMED_DB_NAME=mxmed, MXMED_DB_HOST=127.0.0.1, no Gate preview and no conflicting
environment. HTTP additionally requires loopback peer and localhost:8443 Host.
The endpoint requires canonical Origin, or same-origin fetch metadata plus
canonical Referer for GET. Mutations require canonical Origin and a short-lived
CSRF token from the existing CsrfTokenService with a separate derived purpose
secret. Query parameters and unexpected body fields (including doctor ids) are
rejected. POST/PUT allow real/standard/optimum/professional; DELETE resets.

There is deliberately no account-level authorization yet. This boundary trusts
the explicitly enabled loopback development runtime, including local processes;
it does not claim to protect against another local process. The endpoint's
`reviewActor()` hook is where canonical Identity session + Director/local-review
permission must be required later. No fake Identity actor/session is generated.
The store already supports the future actor account id.

## UI

Open https://localhost:8443/ . The right rail displays doctor 1, real plan,
scenario, server-returned capabilities, and canonical read-only administration
status. Choose Real to restore canonical truth. Existing browser-only Plan QA
and role switcher are disabled on this canonical origin; persisted QA plan
values are ignored there. No new user/advisor management enum exists.
Public profile pages do not load this widget. Staging/production return no
widget data. Profile administration reuses PublicProfileRepository's existing
membership-derived read, exposed as a public read method; it never writes roles.

## Validation performed

- PHP syntax, JS syntax, NormalLocalReviewScenarioTest and existing capability test.
- Live TLS-verified API: real/standard = false,false,false;
  optimum/professional = true,true,true (patients, clinical_record, prescriptions).
- Separate PHP/API requests, browser reload persistence, reset to Real.
- Production, staging, preview, wrong database rejected by endpoint; wrong
  doctor, unsupported plan, missing CSRF and external origin rejected.
- Stale reference and simulated stale local-record fingerprint reject/fall back;
  no real subscription was mutated to manufacture this conflict test.
- Before/after CHECKSUM TABLE EXTENDED matched for profile_subscriptions,
  auth_account_memberships, auth_accounts, auth_account_credentials and
  patients_doctor_links. Final scenario Real.
- Chromium 1366x768 and 1440x900: visible, selectable, server-updated, reload
  persisted, no horizontal overflow; rail does not overlap left navigation.
  Dedicated local certificate SPKI was pinned for the isolated browser process;
  API verification used the dedicated CA. No trust-store configuration changed.

Run the nonmutating guard regression with:
`php modules/subscriptions/tests/NormalLocalReviewScenarioTest.php`.
Clinical authority remains separate and still requires the planned Identity bridge.

Packaging manifest includes the shared helper and referenced static assets; the
local-only endpoint is deliberately excluded from the deployment artifact.
The legacy packaging regression is not a current-HEAD release gate: it checks
out fixed commit 29dcb2d and overlays today's manifest, then fails on the existing
clinical_m6_observability.php entry absent from that old commit. This failure
predates these additions; no packaging PASS is claimed.
