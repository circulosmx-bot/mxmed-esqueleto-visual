# Step 2 visual R4

Historical R4 start, before subsequent accepted work:
`96b3f791f156229e763b4c45782c2fbbb4541790`.

Current R4 refresh starts at clean local/remote commit
`8e3ebc53a0e587e0eb78617e3a197da670d90af4`. It retains the accepted subtitle
trigger, nine-type fixture, modal, chips, references and automatic draft recovery.
Only the requested writer −3px/current-values +2px deltas are applied relative to
this current source. The Step 2 capture allocation absorbs the lift without clipping
the writer or moving the footer; existing spacing declarations are updated in place.
Other Consultation steps keep the current shell allocation.

## Presentation

The single history trigger belongs to the Step 2 subtitle group. Other steps retain the original subtitle flow. Desktop subtitle and button centers align; smaller screens place the button directly below the subtitle, retaining the former trigger's vertical space. The capture band moves up 3px and the current-values band moves down 2px. The desktop capture viewport gains the same 3px removed from its top margin, preserving the existing footer allocation and preventing clipping at its top edge. Current chip sizing, actions, metadata and adaptive columns retain the accepted behavior.

Historical cards use three desktop columns, two tablet columns and one phone column. `Ya agregado` comes from the existing canonical current observations, including invalidation checks. Both close controls use the existing native dialog. Reuse, readback, idempotency, filtering and time authority remain unchanged.

## Director fixture

The external guarded loopback Director router may inject `step2_vitals_r4_review.js` only for `review_step2_visual=r4`, `review_patient=plan02ux`, `review_encounter=open`. The script repeats these restrictions. It supplies all nine canonical measurement types and units, including pain in canonical `score` units. Examples are synthetic visual fixtures, never reference ranges or demographic defaults.

Simulated reuse intercepts the existing canonical command and current reader, retaining its session-local state and duplicate prevention. All other clinical and agenda writes are blocked. No fixtures are written to the clinical database. Production pages do not load this script. An optional `review_current_values=0..9` controls the synthetic initial current rows.

## Reference authority audit

`VALIDATED_VITAL_REFERENCE_AUTHORITY_EXISTS=true`: the subsequently accepted
VITALREF01 authority is `modules/clinical/vitals/reference_registry.php`, version
`VITALREF01.v1`, resolved by
`api/_lib/clinical_vital_references.php::clinical_vital_references_resolve()`.
The authenticated, physician/patient-scoped contract is
`GET /api/clinical/index.php/patients/{patient_id}/vital-references`.
Its response contains `registry_version`, `patient_context` and sourced `items`.
It uses canonical birthdate only; no sex/gender defaults or demographic overrides
are implemented. Pediatric HR/RR, pediatric temperature and child BP percentile
authority remain deferred/incomplete. Anthropometrics have no individual reference.
See [the clinical map and limits](../vitals/VITALREF01_REVIEW.md).

Existing separately authorized VITALREF02 placeholders and VITALREF03 explicit-entry
anchors remain intact. They never populate a clinical input during render/reload.
This R4 refresh adds no reference values, demographic prefill, medical normals,
acceptance UI or backend contract. Synthetic historical values remain isolated in
the guarded Director fixture.

## Verification

Run from the repository:

```sh
STEP2_VITALS_START_SOURCE_HEAD=8e3ebc53a0e587e0eb78617e3a197da670d90af4 \
  STEP2_VITALS_ARTIFACTS=/tmp/mxmed-step2-visual-r4-current python3 modules/clinical/qa/step2_vitals_r4_browser.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
bash modules/clinical/qa/step2_prior_reuse_r3a_disposable_gate.sh
```

The WebKit gate requires the explicit current accepted starting commit, compares
all seven steps' shell geometry, derives the canonical catalog from PHP, checks all
five requested sizes, all nine modal types, three successive reuses, failures, retry,
filtering, metadata and navigation. The existing isolated writer gate checks void
restoration and SERVER_AT_SAVE; the disposable database gate verifies canonical
reuse and time authority separately from visual fixtures. Delivery artifacts contain
captures, measured coordinates, test logs and served-source hashes under
`/Users/circulodigital/.codex/artifacts/step2-visual-r4-current`.

The current guarded Director runtime uses port 18148. Its R4 review URL includes
`review_step2_visual=r4&review_current_values=4&review_vitalref=adult` to show the
full synthetic history and permit several session-only reuses. The obsolete 18143
runtime remains stopped. Delivery records the final committed source, HTTP status,
served asset hashes and unchanged canonical observation hash.
