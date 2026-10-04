# URINE-FLUIDS-CAT03A — fixed-scope catalog expansion

Date: 2026-10-03. Source baseline: `5cfb70af98601648a19c4d68a23ab7a41876359b`. This implements only the seven CAT02 fixed-scope tests. The active review catalog moves from **208 to 215** canonical identities; the “Orina y otros fluidos” leaf moves from **9 to 16** active studies. No schema, order-item structure, result linkage, writer, reader, specimen contract, or provider UI changed.

## Added identities

Each row is an atomic canonical `LABORATORIO` orderable routed to the existing `CLINICAL_LAB` group, with `aliases_json=[]`. The display name itself carries the fixed specimen or clinical purpose; it is not a generic analyte whose sample can be changed by a note.

| Key | Physician-facing Spanish name | Full-catalog group | Source order |
| --- | --- | --- | --- |
| `urine_creatinine_spot` | Creatinina en orina (muestra aislada) | Estudios generales y renales | [Mayo RCTUR — Creatinine, Random, Urine](https://www.mayocliniclabs.com/test-catalog/Overview/610603) |
| `urine_sodium_spot` | Sodio en orina (muestra aislada) | Electrolitos y minerales urinarios | [Mayo RNAUR — Sodium, Random, Urine](https://www.mayocliniclabs.com/test-catalog/overview/610785) |
| `urine_potassium_spot` | Potasio en orina (muestra aislada) | Electrolitos y minerales urinarios | [Mayo RKUR — Potassium, Random, Urine](https://prd1.mayocliniclabs.com/test-catalog/overview/610696) |
| `urine_pregnancy_qualitative` | Prueba de embarazo en orina (cualitativa) | Estudios generales y renales | [Labcorp 004036 — Pregnancy Test, Urine](https://www.labcorp.com/tests/004036/pregnancy-test-urine) |
| `csf_glucose` | Glucosa en líquido cefalorraquídeo (LCR) | Líquido cefalorraquídeo (LCR) | [Mayo GLSF — Glucose, Spinal Fluid](https://prd1.mayocliniclabs.com/test-catalog/overview/152) |
| `csf_total_protein` | Proteínas totales en líquido cefalorraquídeo (LCR) | Líquido cefalorraquídeo (LCR) | [Mayo TPSF — Protein, Total, Spinal Fluid](https://www.mayocliniclabs.com/test-catalog/overview/872) |
| `post_vasectomy_semen_check` | Control de semen posvasectomía | Semen | [Mayo POSV — Post Vasectomy Check, Semen](https://www.mayocliniclabs.com/test-catalog/overview/9205) |

The urine pregnancy test has a fixed qualitative **urine** identity and remains separate from serum quantitative `bhcg`. The post-vasectomy check is a distinct clinical order from `semen_analysis`, whose historical name, aliases and meaning remain unchanged. CSF glucose and protein do not stand for CSF IgG, oligoclonal bands, albumin quotient or paired serum/CSF studies.

## Data, navigation and operational fit

`modules/clinical/db/migrations/2026_10_03_20_urine_fluids_cat03a.sql` is an idempotent, data-only insert: seven new rows, no DDL and no update to pre-existing study identity. It was first exercised in a disposable database and then applied to the Director review database. All seven have unique active `study_type_key` and a server-assigned `study_type_id`, which is the exact offering-match identity for a future provider integration.

The existing HIER03 leaf and laboratory navigation point to the seven keys. The full catalog has six nonempty groups: Estudios generales y renales (7), Electrolitos y minerales urinarios (2), Microbiología urinaria (1), Líquido cefalorraquídeo (LCR) (3), Líquido sinovial (1), Semen (2). Pregnancy remains in the broad general group, avoiding a one-item accordion. The featured set remains exactly the prior six, in the same order: `urinalysis`, `microalbumin`, `urine_culture`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality`.

The inline composer derives “16 estudios disponibles” from active leaf membership. Each of the seven is searchable by its Spanish display name and its canonical key. No speculative aliases were added. All seven use `STUDY_ORDER_ROUTING_VERSION=1` and `CLINICAL_LAB`; selecting them together prepares **one** Laboratorio clínico order. Adding an imaging study prepares a second order. Existing exact order-item IDs, result linkage, portable HTML/PDF and provider bridge semantics remain in use.

## Deferred scope

**CAT03B** still owns `specimen_type`, `source_site`, collection duration/volume/start/end, random-versus-timed protocol, container/preservative, and paired specimens. No `urine_creatinine_24h`, `urine_sodium_24h`, `urine_potassium_24h`, or any other timed variant was added. **CAT03C** still owns stone-risk and CSF molecular panels, body-fluid chemistry/lipid profiles, pathology/microbiology expansion and advanced specialty studies. No generic body-fluid chemistry or susceptibility authority was introduced.

## QA evidence

- Disposable catalog gate: 215 unique active studies, 215 routing mappings, seven new keys and no new aliases; same-group order of seven, independent imaging order, exact item IDs, result linked to one new item, portable HTML/PDF, rollback/idempotency/auth and provider-identity regression passed. No review-db patient/order/result QA writes were made.
- Browser fixture at 1440×900, 1366×768 and 390×844: all seven appear in their configured full-catalog groups, each can be added and removed, search resolves the display name, six featured remain unchanged, VIS24 and dental regressions passed.
- Authenticated review runtime `http://127.0.0.1:18148/`: six active groups, 16 studies, six unchanged featured; compact/expanded desktop sidebar and mobile have no horizontal overflow. The runtime test blocks mutating API calls.
