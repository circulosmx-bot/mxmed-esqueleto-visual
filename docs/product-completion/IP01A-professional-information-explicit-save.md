# IP01A — Canonical professional information and explicit save

## Authority and convention audit

Previously, formation lists wrote `chips:cert/cursos/dipl/miem` on add/remove;
diseases and treatments wrote `chips:enf/trt` on add/remove/order confirmation.
The generic change/blur handler wrote services to `dp:srv1..4`, and the unlabeled
summary used a generated `dp:dp_auto_*` key. None was doctor-scoped backend data.

The new tables follow existing profile/signature extensions: VARCHAR(64) doctor
ownership referencing `profiles_doctors`, InnoDB/utf8mb4, DATETIME timestamps,
unique owner, restrictive foreign keys. Item categories use VARCHAR plus CHECK,
not ENUM. Array position defines the unique (doctor,type,sort_order) key.
A stable doctor-row lock serializes first creation and complete replacement.
Existing physician self-edit profile persistence does not emit a canonical audit
event; this phase follows that convention. It adds no reviewer authority or
credential audit events. Creation/update timestamps remain available.

## Schema and API

Apply `modules/profiles/db/2026_09_14_professional_information.sql` before enabling
the client. The migration only creates empty tables; there is no data backfill,
runtime DDL, or browser migration. It was applied to the local review database.

`GET /api/profiles/professional-information.php` returns the current complete
professional_information and a session/doctor-bound CSRF token. Empty canonical
state is empty, regardless of browser values. `PUT` accepts exactly:

- public_professional_summary: plain text
- items: all seven type arrays, in their desired order

Categories: CERTIFICATION, COURSE, DIPLOMA, MEMBERSHIP, SERVICE, DISEASE,
TREATMENT. Formation/service values retain the existing 50-character limit;
disease/treatment values retain 40; at most four services. Values are trimmed,
non-empty, UTF-8, and may repeat as in the previous UI. Ordered SERVICE items
compact empty visual service slots. The summary had no prior maxlength; TEXT's
65535-byte storage capacity is explicitly validated (not a new claim of unlimited
storage). The complete HTTP body is bounded at 2 MiB. Unknown keys/types, invalid
values and caller-specified ownership are rejected. No credentials enter this API.

The service validates the entire draft, locks the canonical doctor row, upserts
the summary and replaces the small ordered collections within one transaction.
Any failure rolls back the whole save. GET reads summary and items in one query
so readers cannot observe an intermediate replacement. Missing schema fails
closed; no localStorage fallback is used.

## Client and public reads

Transient in-memory lists and current controls are compared with a normalized
baseline. Add/remove/order only update the draft. The existing single save tray,
4000 ms reminder, navigation modal and lifecycle controller are reused with an
active-editor adapter; no second tray or modal exists. Save freezes editing during
the request and only establishes a baseline after success. Failure preserves all
controls and prevents navigation. Native beforeunload also covers professional
changes. Legacy subsection navigation remains an in-tab scroll.

The public repository and existing DTO now populate bio_long, education
(courses/diplomas), certifications, professional_associations, services and
conditions_treated (diseases/treatments). Existing rendering groups consume these
fields; no public layout or new section is introduced. Verified credentials remain
with their existing authority. All legacy professional localStorage reads/writes
and the fixture service hydration were removed; legacy browser values are ignored.

## Validation

`ProfessionalInformationPhysicalTest.php` requires an explicitly synthetic
`ip01a_test_*` database and verifies full roundtrip, independent connection,
doctor isolation, limits/types, duplicate/order semantics and rollback after a
trigger-induced insert failure. HTTP tests use synthetic sessions to verify CSRF,
ownership, authentication and independent session reads. Browser QA exercises
real grouped API saves only against that synthetic database, with other mutation
APIs blocked, plus reload, revert, discard/continue/save, failure and responsive
1440/1366/820/390 layouts. No Leticia content was migrated or written.

The committed browser test requires `IP01A_TEST_URL` and seeded synthetic rows
from the physical test (restore three COURSE items before testing removal).
`ProfessionalInformationTestRouter.php` refuses non-`ip01a_test_*` databases;
`IP01A_READONLY_RUNTIME=http://127.0.0.1:<port>` supplies unrelated read-only UI
fixtures while all unrelated mutations are refused. Set the document root to the
checkout and use a separate session directory. The test leaves synthetic saved
data in its disposable database; it never imports legacy browser values.

Reproducible sequence: create an empty `ip01a_test_*` database, run
`IP01A_TEST_DSN=... php modules/profiles/tests/ProfessionalInformationPhysicalTest.php`,
start the guarded test router with that database, then run
`IP01A_TEST_URL=http://127.0.0.1:<test-port> python3 modules/profiles/tests/ProfessionalInformationHttpTest.py`
and `IP01A_TEST_URL=... node modules/profiles/tests/ProfessionalInformationBrowserTest.mjs`.
The HTTP test seeds all seven complete synthetic collections, including courses.
The browser test verifies legacy subsection aliases without losing drafts.

The shared modal close helper now attempts closure immediately and retries on
`shown.bs.modal` if opening is still in progress. This avoids a stale transition
flag holding discard/save navigation open; the existing Personal navigation
regression (with current tab aliases and icon-child hit targets) also passed.
