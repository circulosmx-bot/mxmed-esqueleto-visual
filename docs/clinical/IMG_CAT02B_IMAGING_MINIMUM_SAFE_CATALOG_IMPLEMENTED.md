# IMG-CAT02B — Minimum safe imaging catalog

**Status:** `MINIMUM_SAFE_CATALOG_IMPLEMENTED`
**Source baseline:** `71ec4f9e1a473be31c5049f8a687055e828e6a02`
**Authority:** IMG-CAT01 implementation/parameter matrices and IMG-CAT02A parameter contract V1.

The active canonical catalog grew from **263 to 278** studies. General imaging grew from **34 to 49** identities; the four dental imaging identities remain separate. The migration adds exactly the following 15 keys and is idempotent. Display names follow the approved IMG-CAT01 implementation matrix.

| Key | Display name | Imaging leaf | Required V1 intent |
| --- | --- | --- | --- |
| `rx_hip` | Radiografía de cadera | Radiografía | Laterality |
| `ct_sinuses` | TAC de senos paranasales | Tomografía | Contrast |
| `mr_lumbar_spine` | Resonancia magnética de columna lumbar | Resonancia magnética | Contrast |
| `rx_foot` | Radiografía de pie | Radiografía | Laterality |
| `rx_wrist` | Radiografía de muñeca | Radiografía | Laterality |
| `rx_elbow` | Radiografía de codo | Radiografía | Laterality |
| `mr_cervical_spine` | Resonancia magnética de columna cervical | Resonancia magnética | Contrast |
| `ct_neck` | TAC de cuello | Tomografía | Contrast |
| `cta_head_neck` | Angio-TC de cabeza y cuello | Tomografía | Fixed IV contrast and explicit head/neck territory |
| `mra_brain` | Angio-RM cerebral | Resonancia magnética | Contrast and fixed head territory |
| `mr_pelvis` | Resonancia magnética de pelvis | Resonancia magnética | Contrast |
| `breast_tomosynthesis` | Tomosíntesis mamaria | Imagen mamaria | Laterality, purpose and mammography relation |
| `rx_tspine` | Radiografía de columna torácica | Radiografía | Parameter object V1; standard view preset available |
| `nm_renal_scan` | Gammagrama renal | Medicina nuclear y PET | Physician protocol |
| `nm_myocardial_perfusion` | Perfusión miocárdica nuclear | Medicina nuclear y PET | Fixed stress/rest protocol |

## Ordering and presentation

All 15 use `imaging_order_parameters_v1.json` **version 1** with `ACTIVE_REQUIRED` rules. The composer renders a compact inline panel from that authority. Fixed values are shown as context, and only per-key allowed options appear. The frontend checks completeness before review; the existing canonical writer validates the final payload. The other 34 general imaging keys still allow omitted imaging parameters. No new clinical schema or routing group was created.

The existing HIER03 leaves, FEATURED02 small/large-leaf rules and family-wide SEARCH02-R1 search expose the new keys. The 15 curated search entries add Spanish common terms and specific abbreviations without changing the canonical keys. The Imaging leaves omit the redundant global-search link. All new orders route to `GENERAL_IMAGING`; cardiovascular cross-routes remain `CARDIOVASCULAR_DIAGNOSTICS`, and dental images remain `DENTAL_DIAGNOSTICS`. Mixed composition creates separate order documents by routing group.

At issuance the order item stores authority version, selected values and a Spanish label. The portable HTML/PDF reader prints the stored label, not a label recomputed from current configuration. Exact source order version and `order_item_id` continue to govern result linkage. No result authority or historical document was rewritten.

## QA evidence

The disposable database gate clones the production-shaped review schema and study types, applies the migration twice, then runs authenticated **real HTTP** requests against the same PHP runtime code on an isolated local port. It issued all 15 identities together plus an independent laboratory order; verified all 15 immutable item snapshots and independent routing; generated real portable HTML and PDFs for hip radiography, neck CT and breast tomosynthesis; and linked a result to the exact order item. Nine invalid parameter cases returned 422 with zero document writes. The disposable database and files are removed by the gate.

The live 18148 browser gate verified all 15 in the existing navigation, family search, selection and parameter panels; `hba`, `psa`, `ihq` and `hol` cross-family rescue; absence of the stale global-search link; and 1440×900, 1366×768 compact/expanded and 390×844 layouts. The live browser gate blocked write requests. IMG-CAT02A and SEARCH02 regression gates pass with the new catalog count.

## Deferred imaging coverage

The remaining P3/advanced candidates in IMG-CAT01 are still deferred. Existing CT/MR regions, breast imaging and nuclear studies do not imply coverage of unapproved regions, tracers or provider capabilities. Provider location capability and pricing remain independent matching concerns; activating a study identity does not assert that every provider can perform it. No DICOM workflow was added.
