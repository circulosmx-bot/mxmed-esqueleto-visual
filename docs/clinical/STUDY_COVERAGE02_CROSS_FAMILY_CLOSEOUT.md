# STUDY-COVERAGE02 — Cross-family catalog closeout

Baseline: `6edd6e485ac861121f3a9207476946da97bb98a5` on `ux/consultation-step2-vitals-r1`. The Director review database and runtime at `127.0.0.1:18148` contain 289 active canonical studies. This closeout added no catalog row or study identity. The review database was read only; creation and result tests used an isolated database copied from its schema and active catalog rows, then deleted.

## Catalog accounting

The active database keys, search authority keys, and routing keys each total 289 and agree exactly. Every active key has at least one nonempty featured-navigation leaf, exactly one root family, a valid routing group, and a matching search authority entry. No featured leaf points to an inactive key. Family totals: Laboratory 154, Imaging 55, Pathology 16, Functional studies 37, Diagnostic procedures 16, Dental 11. Some studies appear in more than one leaf *inside* their family for intentional discovery; no key spans root families. Normalized canonical names and exact canonical/abbreviation/equivalent-alias identities have no cross-study collisions. A review of 32 highly similar name pairs found clinically distinct analytes, specimens, methods, sides, or anatomical targets rather than semantic duplicates.

The real catalog reader found every study for each distinct configured canonical name, database alias, abbreviation, equivalent alias, common name, and discovery term: 765 distinct per-study query pairs, zero misses. Four phrases initially failed because the short-token policy rejected them: `Serie de 14 imágenes`, `Serie de 16 imágenes`, `Serie de 18 imágenes`, and `ER PR HER2 Ki67`. The exact normalized forms were added to `short_query_exceptions` in `study_search_authority_v1.json`; this does not change catalog aliases or identities. The existing search gate also replaced its obsolete global `252` check with a count derived from the current routing authority. Live family-wide search and cross-family rescue passed at all four viewports; rescue results remained separated from same-family results and the old global-search control stayed hidden.

Search examples checked in the live runtime include `hem`, `gli`, `glu`, `hba`, `a1c`, `psa`, `ant`, `pro`, `tac`, `rmn`, `eco`, `hol`, and `mapa`. The current catalog gate checked 21 representative terms across the families, including dental (`periapical`, `bitewing`, `cbct`), procedures (`colpos`, `cisto`, `histero`), and functional (`baep`, `manometria`, `phmetria`) terms. The current six-family browser gate visited a final selector in every root family and the Dental special path at 1440×900, 1366×768 with compact/expanded sidebar, and 390×844. Headings, search, the prepared-order area, representative rows, and horizontal overflow were checked. On mobile, the prepared-order aside exists but is hidden by the responsive layout until its mobile presentation is invoked.

## Ordering and parameter boundaries

The disposable authenticated gate created one batch with six independently routed operational orders: true Laboratory panels (`panel_quimica_6`, `arterial_blood_gas`), pathology biopsy, hip radiography, two location-distinct dental occlusal items, diagnostic colposcopy, and esophageal manometry. All orders share one batch UUID and retain unique order and item IDs. A malformed sixth order produced HTTP 422 with zero writes. A database trigger forced the second order to fail and verified rollback of the first order and idempotency records; retry after removing the trigger succeeded. Replaying the successful composition returned the same orders without new writes. Identical Dental location duplicates were rejected; maxillary and mandibular locations coexisted.

Versioned authority references resolve to active keys: specimen 55, pathology 11, imaging 55, functional 37, and Dental location policy 5. Dental location authority remains V2 with FDI/ISO 3950 and permanent, primary, and mixed dentitions. The disposable order snapshots retained selected pathology/imaging/functional parameter versions and human-readable labels. Six existing contract gates passed. The two true Laboratory panels remain canonical orderable studies, while the three QS3 renal, QS3 lipids, and basic hepatic presets remain composed of canonical component keys and have no catalog identities. The disposable portable HTML and PDF for all six orders contained Spanish study labels; parameter displays used readable labels. Seven result documents linked to exact source order-item IDs, including separate maxillary and mandibular results.

The diagnostic procedure scope authority marks colposcopy, hysteroscopy, and cystoscopy diagnostic only. Referral matching still requires a verified, active offering for the exact `study_type_id` at the location; routing group alone does not establish provider capability. Parameter-level capability remains `UNVERIFIED` where it has not been verified. No therapy capability was introduced.

## Historical count-gate audit

Thirteen historical absolute-global-count assertions were identified. One current runtime gate was repaired: `study_search02_r1_live.py` now derives the expected active count from routing configuration. The remaining twelve are old-epoch fixtures or wrappers and are not part of current canonical QA:

| Gate | Old total | Classification |
| --- | ---: | --- |
| `path_cat02b_disposable_gate.py` | 263 | STALE_GLOBAL_COUNT_ASSERTION |
| `img_cat02b_disposable_gate.py` | 278 | STALE_GLOBAL_COUNT_ASSERTION |
| `img_cat02b_disposable_gate.sh` source/after | 263 or 278 / 278 | STALE_GLOBAL_COUNT_ASSERTION (two checks) |
| `lab_cat05c_disposable_http.py` | 280 | STALE_GLOBAL_COUNT_ASSERTION |
| `dental_odontogram02_disposable_http.py` | 280 | STALE_GLOBAL_COUNT_ASSERTION |
| `dental_cat03c_disposable_http.py` | 284 | STALE_GLOBAL_COUNT_ASSERTION |
| `proc_cat02b_disposable_http.py` | 287 | STALE_GLOBAL_COUNT_ASSERTION |
| `urine_fluids_cat03c_browser.py` | 232 | STALE_GLOBAL_COUNT_ASSERTION |
| `ord_comp01_disposable_gate.py` catalog/routing | 232 | STALE_GLOBAL_COUNT_ASSERTION (two checks) |
| `study_nav_featured02_browser.py` | 252 | STALE_GLOBAL_COUNT_ASSERTION |

The `252` checks in `study_search02_gate.php` are **VALID_FIXED_BASELINE** checks for its historical matrix and expected query set, not current-global-count checks. Other numeric literals such as Dental study ID `284` and sidebar width `232` are not catalog totals. The old CAT03B fixture and optional ORD-COMP browser wrapper remain legacy QA debt; neither blocks the new current-authority gates. The current gate tests all 289 keys without hardcoded historical totals.

## Commands and evidence

- `php modules/clinical/qa/study_coverage02_catalog_gate.php` — 289 accounted, 765 configured term checks plus 21 representative searches, zero misses, authority versions/keys valid.
- `STUDY_SEARCH02_R1_SESSION=director-lon07c-review python3 modules/clinical/qa/study_search02_r1_live.py` — family search and rescue passed at all four viewports.
- `STUDY_COVERAGE02_SESSION=director-lon07c-review python3 modules/clinical/qa/study_coverage02_live_browser.py` — six families passed at all four viewports, read only.
- `bash modules/clinical/qa/study_coverage02_disposable.sh` — authenticated mixed composition, rollback, idempotency, six HTML/PDF orders, seven exact result links; disposable DB removed by trap.
- Dental, functional, imaging, Laboratory panel, pathology, and Dental odontogram contract gates passed. Specimen gates that require their own disposable DB were not run against the Director database.

The current 289-study catalog is accepted as a credible minimum-safe Mexico diagnostic baseline. This does not claim an exhaustive inventory of every diagnostic test offered in Mexico.
