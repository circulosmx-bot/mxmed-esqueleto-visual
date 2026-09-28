# Step 2 R3A canonical prior reuse

`POST /api/clinical/index.php/encounters/{encounter_key}/observations/reuse`

Authenticated physician context, existing V1 cohort and doctor/patient authorization,
OPEN current encounter and the existing write window are required. The only command
field is an integer `source_observation_id`; an `Idempotency-Key` is required.

The server locks the current encounter and scoped source, reuses the exact prior-reader
candidate projection, and rejects a second active value of the same code. It creates a
new current observation, copying canonical values, unit, origin and historical
`effective_at`. `recorded_at` is the server incorporation time. The source stays immutable.

Existing `provenance_json` stores `reuse_mode=PRIOR_OBSERVATION`, the direct source
observation and encounter references, original timestamps, `reused_at` and nested
`source_provenance`. Nested provenance preserves ancestor lineage without claiming
that the value was physically measured again. Ordinary creation cannot forge these
server fields; editing retains persisted server lineage. Direct manual capture still
uses SERVER_AT_SAVE and requires Origen.

The existing CREATE_OBSERVATION idempotency operation/storage is shared. The semantic
hash distinguishes reuse from ordinary creation and binds the source ID. Committed
replays return the same row; source changes with the same key conflict. A canonical
void ends the browser's logical reuse command so deliberate later reuse can get a new
key. No schema change or migration is introduced.

## Verification

- `bash modules/clinical/qa/step2_prior_reuse_r3a_disposable_gate.sh`: accepted capture
  regression plus real HTTP/SQL reuse tests in a randomly named disposable database.
  The gate removes its server, sessions and database on exit.
- `python3 modules/clinical/qa/step2_vitals_r1_logic.py`: direct capture failure,
  edit, void, filtering, reload and patient context isolation.
- `python3 modules/clinical/qa/step2_vitals_r3_browser.py`: Director WebKit checks at
  1440×900, 1440×880, 1366×768, 820×1180 and 390×844, including accepted shell comparisons
  against 402fb5f6, modal failure/success/retry, two reuses in one modal, metadata,
  all seven steps, reduced motion, origin alignment, VIS32 and prescription preparation.

The guarded Director router may inject `step2_vitals_r3_review.js` for
`review_patient=plan02ux&review_encounter=open&review_step2_visual=r3` on loopback only.
Its synthetic prior/current values and reuse simulation remain in browser session
storage; clinical and agenda writes are blocked. The fixture is never loaded by the
production HTML and never persists observations to clinical storage. Real persistence
and authority are verified separately in the disposable HTTP/SQL gate.

The two responsive trigger placements expose exactly one visible button at each width,
without adding a row to the shared desktop header. Desktop current chips fit eight
values in the fixed capture area; a ninth uses another row inside that existing area.
