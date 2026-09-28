# Step 2 visual R4

Starting authority: clean local/remote commit `96b3f791f156229e763b4c45782c2fbbb4541790`.

## Presentation

The single history trigger belongs to the Step 2 subtitle group. Other steps retain the original subtitle flow. Desktop subtitle and button centers align; smaller screens place the button directly below the subtitle, retaining the former trigger's vertical space. The capture band moves up 3px and the current-values band moves down 2px. The desktop capture viewport gains the same 3px removed from its top margin, preserving the existing footer allocation and preventing clipping at its top edge. Current chip sizing, actions, metadata and adaptive columns retain the accepted behavior.

Historical cards use three desktop columns, two tablet columns and one phone column. `Ya agregado` comes from the existing canonical current observations, including invalidation checks. Both close controls use the existing native dialog. Reuse, readback, idempotency, filtering and time authority remain unchanged.

## Director fixture

The external guarded loopback Director router may inject `step2_vitals_r4_review.js` only for `review_step2_visual=r4`, `review_patient=plan02ux`, `review_encounter=open`. The script repeats these restrictions. It supplies all nine canonical measurement types and units, including pain in canonical `score` units. Examples are synthetic visual fixtures, never reference ranges or demographic defaults.

Simulated reuse intercepts the existing canonical command and current reader, retaining its session-local state and duplicate prevention. All other clinical and agenda writes are blocked. No fixtures are written to the clinical database. Production pages do not load this script. An optional `review_current_values=0..9` controls the synthetic initial current rows.

## Reference authority audit

`VALIDATED_VITAL_REFERENCE_AUTHORITY_EXISTS=false`: no validated age/sex vital reference contract was found in the repository. The authority `api/_lib/clinical_observations.php::clinical_observation_catalog()` defines nine codes, canonical units and numeric/pressure kinds, with structural validation only. The longitudinal chart explicitly describes readings without reference ranges (`index.html`, `lon07c-chart`). Searches across API, modules, assets and docs for reference/normal ranges, clinical normals, percentiles and demographic reference contracts found no such authority.

No production demographic prefill, medical normals table or future suggestion UI is implemented. Reference cues require a separately approved, validated source and authorization.

## Verification

Run from the repository:

```sh
STEP2_VITALS_ARTIFACTS=/tmp/mxmed-step2-vitals-r4 python3 modules/clinical/qa/step2_vitals_r4_browser.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
bash modules/clinical/qa/step2_prior_reuse_r3a_disposable_gate.sh
```

The WebKit gate compares shell geometry at all five sizes to the starting commit, derives the canonical catalog from PHP, checks all five requested sizes, all nine modal types, three successive reuses, failures, retry, filtering, metadata and navigation. The existing isolated writer gate checks void restoration and SERVER_AT_SAVE; the disposable database gate verifies canonical reuse and time authority separately from visual fixtures. Delivery artifacts contain captures, measured coordinates, test logs and the refreshed runtime's served-source hashes.

If another Director review occupies port 18143, start the guarded Step 2 router on a free loopback port and set `STEP2_VITALS_REVIEW_BASE` to its `index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide` URL when running the visual gate. The delivery runtime artifact records the actual port and committed source. This keeps independent reviews separate without changing fixtures or clinical storage.
