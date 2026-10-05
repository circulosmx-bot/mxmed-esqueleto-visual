# FUNC-CAT02B — Functional Studies quality and GI physiology activation

**Status:** `MINIMUM_SAFE_MEXICO_COVERAGE_ACCEPTED` after the disposable and live gates below. Source baseline: `8f5e540fba920d89e61269e0b6d14fc6693db71d`.

## Catalog and domain boundary

Exactly two approved P2 identities were activated: `esophageal_manometry` (Manometría esofágica) and `esophageal_ph_monitoring` (Monitoreo de pH esofágico). The active catalog moved from 287 to 289 and the Functional family from 35 to 37. P1 missing count is zero. The new `FUNCION_DIGESTIVA` category, `FUNCTIONAL_GI_PHYSIOLOGY` order route, and small direct “Motilidad gastrointestinal” leaf contain these two identities only. Both selected together produce one operational order with two immutable order items; other routes remain separate in the same batch. No existing study was relocated or rerouted. Endoscopy remains a distinct procedure domain.

The idempotent migration is `2026_10_05_28_func_cat02b_gi_physiology.sql`. It extends the catalog category check and inserts only these two rows. It was applied to `mxmed_director_review_lon07c` after an isolated clone passed 289 active rows on first and second application.

## Physician intent and snapshots

Functional parameter authority V1 remains the single bounded authority with 37 rules. The two GI rules are now `ACTIVE_REQUIRED`: manometry accepts conventional or high-resolution technique; pH monitoring accepts pH only or pH plus impedance, requires exactly 24 hours and an on/off antisecretory treatment choice. The controls use Spanish labels and do not expose enum values. Current optional controls cover Holter and video EEG duration; paired region/side for EMG and SSEP; side for auditory and visual evoked potentials and approved audiology studies; spirometry protocol; and VNG caloric intent. The optional parameters may still be omitted. The order writer validates each field per study and stores the versioned values plus a Spanish label on the exact item. Portable HTML/PDF reads that stored label; historical orders and results are not reinterpreted.

## Search and navigation

The search authority has 289 identities. Additive terms cover ECG/EKG, qualified Holter de presión, ergometry, EEG, EMG, PEATC/ABR, pulmonary function, speech audiometry, otoemissions, vestibular caloric discovery and GI phrases. Bare “Holter” does not return MAPA; the qualified pressure phrase does. `PFT` remains a discovery term for both complete PFT and spirometry, with complete PFT ranked first; it is not an exact alias selecting a single study. `PFP` and the full pulmonary-function phrase map to complete PFT. BAEP remains a single neurophysiology identity and route, and is found from the Audiology leaf through family-wide search. Functional searches also retain cross-family rescue for imaging and diagnostic procedures.

## Provider matching boundary

The existing verified exact `study_type_id` offering remains necessary. Manometry does not imply pH monitoring, or vice versa. The current provider offering schema cannot express technique, duration, impedance or other functional parameter capabilities. For matched parameterized items the read-only match response now includes `coverage.parameter_capability_status=UNVERIFIED` and `coverage.parameter_unverified_order_item_ids`, alongside the existing exact-study coverage classification. Thus a study offering is never silently represented as a verified technical variant. No automatic parameter-compatible referral or dispatch is claimed. A future capability authority and verified provider declarations are required before asserting technique compatibility.

## QA

- `bash modules/clinical/qa/func_cat02b_disposable_http.sh`: isolated MySQL clone, real authenticated PHP endpoints, all listed positive and negative parameter cases, zero writes on rejection, optional omission, two-item GI grouping, mixed-route batch, idempotent replay, exact result-item linkage, real portable HTML and five Chrome-generated PDFs, search, and provider exact-offering/parameter-disclosure fixtures — passed.
- `php modules/clinical/qa/func_cat02a_contract_gate.php` and `php modules/clinical/qa/study_search02_gate.php` — passed. The latter retains its historical 252-row fixture while accepting explicitly additive search terms and the new 289-entry authority.
- Existing IMG-CAT02A, PATH-CAT02A, LAB-CAT05B and DENTAL-CAT03B contract gates — passed.
- `python3 modules/clinical/qa/func_cat02b_live_browser.py` against the actual port 18148 runtime — passed at 1440×900, 1366×768 compact, 1366×768 expanded and 390×844. Checked GI direct leaf, BAEP discovery from Audiology, functional controls, prepared order grouping, cross-family rescue, no JavaScript page errors, no HTTP catalog errors, no writes and no horizontal overflow.

The 35 original Functional identities and five original operational routes remain intact. Advanced concepts remain deferred: ambulatory EEG where distinct intent is uncertain, anorectal manometry, vHIT, posturography, extended GI physiology, therapeutic PAP setup and event monitors.
