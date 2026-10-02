# OR05-NAVMAP01 — Specialty navigation matrix V1 (PROPOSED)

**Audit snapshot:** 2026-10-02; source HEAD `9efa63adcdea68c70aa9f8f5cd03c7f18af9d48c`. This is a navigation proposal for Director review. No clinical catalog, API, writer, authorization rule, schema, or UI behavior was changed by this audit.

## Authority and limits

- Study authority: the 183 active rows of `mxmed_director_review_lon07c.clinical_study_types`, reconciled key-for-key with the 183 canonical entries of `modules/clinical/catalog/tax03b_source_curation.json`. There are 183 rows total, all active, IDs 1–183; 23 studies have aliases (32 aliases in all). The review database has zero `profiles_doctors` rows. This audit therefore cannot measure deployed clinician-label frequencies or find stored free-text variants.
- Specialty authority is **not** a global specialty-ID taxonomy. `profiles_doctors.specialty_primary` and `specialty_secondary_json` are text; verified specialty credentials have per-doctor `credential_id` and free-text `professional_area_label`. `primary_specialty_credential_id` identifies the chosen verified credential for that doctor, not a global specialty concept. The UI suggests 31 medical labels for each of three medical title values, 10 dental labels, and 38 other-profession labels; a legacy compatibility selector carries 71 labels. Their union has 124 distinct labels. This report also inventories 10 professional-title values (134 finite classification entries total). Arbitrary persisted text is unbounded and needs a deterministic fallback.
- The 20 `public_classifications_by_specialty` phrases are UI suggestions for public discoverability, not clinical specialty identities. They are inventoried separately below and excluded from the 134 count.
- `ui:<slug>` and profile keys in this report are **proposal identifiers** derived from UI labels. They are not persisted specialty IDs. A title is singular; recognized specialties can coexist in primary/secondary slots. No profile changes order eligibility.

## External terminology guidance

- [CONACEM council catalog](https://conacem.org.mx/catalogo-consejos) and the recognized [National Anesthesiology Council 2026 certification call](https://consejoanestesia.org/?page=convocatoria-agosto-2026): terminology comparison only; MXMED retains its own labels. The council names pediatric anesthesiology, neuroanesthesiology, algology, and palliative care; the first three are absent as exact finite MXMED UI labels. CONACEM’s older indexed certification-list URL currently returns 404, so it is not used as a live source.
- [LOINC orderable grouper concepts](https://loinc.org/kb/users-guide/orderable-grouper-concepts), [LOINC laboratory classes](https://loinc.org/kb/users-guide/classes/laboratory-classes), and [LOINC scope](https://loinc.org/kb/users-guide/introduction/scope-of-loinc): groupers and order concepts are distinct from result/observation codes. No LOINC mapping is asserted for an MXMED study in this report.
- [Mayo Clinic Laboratories test catalog](https://test.backend.mayocliniclabs.com/test-catalog/index.html) and [specialty test-selection guides](https://news.mayocliniclabs.com/test-selection-guides/): specialty browsing coexists with a global test menu; used as UX guidance, not as MXMED authority.
- [Mexican government dental-specialty examples](https://www.gob.mx/defensa/acciones-y-programas/especialidades-en-odontologia) and [SEP specialty credential process](https://www.gob.mx/tramites/ficha/registro-de-diploma-de-especialidad-y-expedicion-de-cedula-profesional-para-estudios-hechos-en-mexico/SEP1215): terminology/credential guidance, not an MXMED dental taxonomy.

## Canonical category inventory

| Category | Active studies | Normal selectable destination now? |
|---|---:|---|
| `LABORATORIO` | 81 | Yes |
| `IMAGEN` | 34 | Yes |
| `CARDIOVASCULAR` | 13 | Yes |
| `OFTALMOLOGIA` | 0 | No — empty |
| `NEUROFISIOLOGIA` | 9 | Yes |
| `FUNCION_PULMONAR` | 9 | Yes |
| `AUDIOLOGIA` | 5 | Yes |
| `DENTAL` | 0 | No — empty |
| `PATOLOGIA` | 2 | Yes |
| `ENDOSCOPIA` | 13 | Yes |
| `SUENO` | 5 | Yes |
| `GENETICA` | 12 | Yes |
| `OTROS` | 0 | No — empty |

Empty destinations: `OFTALMOLOGIA`, `DENTAL`, `OTROS`. Do not show them as active shortcuts or lower links until their catalog counts become positive. No catalog rows are currently disabled.

## Primary navigation groupers (stable for all profiles)

| Proposed label | Canonical membership | Active count |
|---|---|---:|
| LABORATORIO | `LABORATORIO`, `GENETICA`, `PATOLOGIA` | 95 |
| IMAGENOLOGÍA | `IMAGEN` plus six exact cardiovascular imaging keys `echo_tte`, `echo_tes`, `stress_echo`, `carotid_doppler`, `lower_ext_art_doppler`, `lower_ext_venous_doppler` | 40 |
| ESTUDIOS FUNCIONALES | `CARDIOVASCULAR`, `NEUROFISIOLOGIA`, `FUNCION_PULMONAR`, `SUENO`, `AUDIOLOGIA` | 41 |
| PROCEDIMIENTOS DIAGNÓSTICOS | `ENDOSCOPIA` | 13 |

The four primary routes cover every active study at least once. Six cardiovascular imaging studies intentionally have two primary navigation routes; they retain one canonical study identity. The `PROCEDIMIENTOS DIAGNÓSTICOS` route only contains existing `ENDOSCOPIA` studies. It is **not** a therapy/procedure writer.

## Default quick access and deterministic rules

- Row 1: Cardiología → `CARDIOVASCULAR` (13); Neurofisiología → `NEUROFISIOLOGIA` (9); Función pulmonar → `FUNCION_PULMONAR` (9); **Citología** → `PATOLOGIA` (2). The Director’s proposed “Patología” label would imply a broader menu than the two current cervical-cytology entries, so the V1 navigation label is narrowed without changing the canonical category.
- Row 2: Endoscopía → `ENDOSCOPIA` (13); Sueño → `SUENO` (5); Audiología → `AUDIOLOGIA` (5); Genética → `GENETICA` (12). All eight currently have active content.
- Lower text access now: **Todos los estudios**. Proposed Oftalmología, Dental, and Otros lower links would be empty now and are omitted. Global search stays unfiltered; no specialty ranking boost in V1. A quick destination never repeats as a lower link.
- Profile precedence: exact normalized label → explicitly modeled parent (e.g., pediatric variant) → professional-title family → general fallback. When a valid `primary_specialty_credential_id` is present, its verified active credential label drives the first profile. Otherwise use `specialty_primary` if recognized. Ordered secondary labels may fill unused quick slots only when they map to active destinations; do not alphabetize. Unknown labels use family/general fallback and never suppress full catalog. Runtime authorization remains independent of this navigation proposal.
- The current profile is not an order-indication claim. Quick access can omit a category; the full catalog and universal search cannot. Mixed-category and custom-study flows remain available under the existing TAX03C contract.

## Professional-title classifications (10)

| Internal UI key | Physician-facing title | Family | Primary title? | Coexists as another title? | Quick access 1–8 | Lower links | Promoted studies | Fallback / gap |
|---|---|---|---|---|---|---|---|---|
| `medico_cirujano` | Médico Cirujano | Médica | Yes, singular title | No | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | `general_med`; — |
| `medico_general` | Médico General | Médica | Yes, singular title | No | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | `general_med`; — |
| `medico_cirujano_partero` | Médico Cirujano y Partero | Médica | Yes, singular title | No | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | `general_med`; — |
| `cirujano_dentista` | Cirujano Dentista | Dental | Yes, singular title | No | — | Todos los estudios | — | `dental`; sin estudios dentales activos |
| `lic_nutricion` | Licenciado en Nutrición | Otra profesión | Yes, singular title | No | — | Todos los estudios | — | `nutrition`; sin afinidad fuerte en catálogo |
| `lic_psicologia` | Licenciado en Psicología | Otra profesión | Yes, singular title | No | — | Todos los estudios | — | `psychology`; sin afinidad fuerte en catálogo |
| `lic_fisioterapia` | Licenciado en Fisioterapia | Otra profesión | Yes, singular title | No | Neurofisiología | Todos los estudios | — | `physio`; — |
| `lic_rehabilitacion` | Licenciado en Rehabilitación | Otra profesión | Yes, singular title | No | Neurofisiología | Todos los estudios | — | `physio`; — |
| `qfb` | Químico Farmacobiólogo | Otra profesión | Yes, singular title | No | — | Todos los estudios | — | `qfb`; sin afinidad fuerte en catálogo |
| `enfermeria` | Enfermería | Otra profesión | Yes, singular title | No | — | Todos los estudios | — | `nursing`; sin afinidad fuerte en catálogo |

## Director review matrix — every finite specialty label (124)

Each row gives proposed profile and quick-access order. **Lower** is `Todos los estudios` for every row; other lower links are omitted because they would duplicate promoted destinations or point to empty categories. `Primary`/`secondary` refer to free-text profile slots, not catalog IDs. Parent values are UI title keys where the current UI supplies one, otherwise `sin relación formal`; pediatric parent suggestions are proposals. `Source` distinguishes current title-dependent UI from the legacy compatibility selector.

### Médica (65)

| Proposed key | Specialty / classification | UI parent | Primary / coexists | Profile key | Quick access 1–8 | Lower links | Promoted study keys | Deemphasized quick groupers | Fallback / gap | Source |
|---|---|---|---|---|---|---|---|---|---|---|
| `ui:alergologia` | Alergología | sin relación formal | sí / sí | `allergy` | Función pulmonar | Todos los estudios | `spirometry` | Cardiología, Neurofisiología, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:anatomia-patologica` | Anatomía Patológica | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `pathology` | Citología, Genética | Todos los estudios | `cyto_pap`, `cyto_liquid_based` | Cardiología, Neurofisiología, Función pulmonar, Endoscopía, Sueño, Audiología | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:anestesiologia` | Anestesiología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `anesthesia` | Función pulmonar, Cardiología | Todos los estudios | `capnography`, `ecg_12lead` | Neurofisiología, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:angiologia-y-cirugia-vascular` | Angiología y Cirugía Vascular | sin relación formal | sí / sí | `vascular` | Cardiología | Todos los estudios | `carotid_doppler`, `lower_ext_art_doppler`, `lower_ext_venous_doppler`, `ankle_brachial_index` | Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:analisis-clinicos` | Análisis Clínicos | sin relación formal | sí / sí | `clinical_lab` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:audiologia` | Audiología | sin relación formal | sí / sí | `audiology` | Audiología, Neurofisiología | Todos los estudios | `audiometry_tonal`, `tympanometry`, `vng` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Genética | exacto | legacy-esp |
| `ui:cardiologia` | Cardiología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `cardio` | Cardiología, Función pulmonar, Sueño | Todos los estudios | `ecg_12lead`, `echo_tte`, `holter`, `abpm_mapa` | Neurofisiología, Citología, Endoscopía, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:cirugia-bariatrica` | Cirugía Bariátrica | sin relación formal | sí / sí | `gi` | Endoscopía | Todos los estudios | `egd_eda_base`, `colonoscopy_base`, `us_abdomen` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:cirugia-cabeza-y-cuello` | Cirugía Cabeza y Cuello | sin relación formal | sí / sí | `head_neck_surgery` | Endoscopía | Todos los estudios | `laryngoscopy_base` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:cirugia-cardiovascular` | Cirugía Cardiovascular | sin relación formal | sí / sí | `cardio` | Cardiología, Función pulmonar, Sueño | Todos los estudios | `ecg_12lead`, `echo_tte`, `holter`, `abpm_mapa` | Neurofisiología, Citología, Endoscopía, Audiología, Genética | exacto | legacy-esp |
| `ui:cirugia-de-columna` | Cirugía de Columna | sin relación formal | sí / sí | `surgery_narrow` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:cirugia-de-mano` | Cirugía de Mano | sin relación formal | sí / sí | `surgery_narrow` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:cirugia-de-pie` | Cirugía de Pie | sin relación formal | sí / sí | `surgery_narrow` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:cirugia-gastrointestinal` | Cirugía Gastrointestinal | sin relación formal | sí / sí | `gi` | Endoscopía | Todos los estudios | `egd_eda_base`, `colonoscopy_base`, `us_abdomen` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:cirugia-general` | Cirugía General | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `surgery` | Endoscopía | Todos los estudios | `ct_abdomen_pelvis` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:cirugia-laparoscopica` | Cirugía Laparoscópica | sin relación formal | sí / sí | `surgery` | Endoscopía | Todos los estudios | `ct_abdomen_pelvis` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:cirugia-oncologica-pediatrica` | Cirugía Oncológica Pediátrica | Pediatría | sí / sí | `peds_onc` | Genética | Todos los estudios | `cbc`, `somatic_tumor_ngs` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología | exacto | legacy-esp |
| `ui:cirugia-pediatrica` | Cirugía Pediátrica | Pediatría | sí / sí | `peds_surgery` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:cirugia-plastica` | Cirugía Plástica | sin relación formal | sí / sí | `surgery_narrow` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:cirugia-toracica` | Cirugía Torácica | sin relación formal | sí / sí | `pulm` | Función pulmonar, Sueño, Endoscopía | Todos los estudios | `spirometry`, `full_pft`, `ct_chest`, `bronchoscopy_base` | Cardiología, Neurofisiología, Citología, Audiología, Genética | exacto | legacy-esp |
| `ui:coloproctologia` | Coloproctología | sin relación formal | sí / sí | `gi` | Endoscopía | Todos los estudios | `egd_eda_base`, `colonoscopy_base`, `us_abdomen` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:colposcopia` | Colposcopía | sin relación formal | sí / sí | `colposcopy` | Citología | Todos los estudios | `cyto_pap`, `cyto_liquid_based` | Cardiología, Neurofisiología, Función pulmonar, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:cuidados-paliativos` | Cuidados Paliativos | sin relación formal | sí / sí | `palliative` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:dermatologia` | Dermatología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `derm` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:diabetologia` | Diabetología | sin relación formal | sí / sí | `endocrine` | — | Todos los estudios | `hba1c`, `tsh`, `ft4` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:endocrinologia` | Endocrinología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `endocrine` | — | Todos los estudios | `hba1c`, `tsh`, `ft4` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:estudios-de-diagnostico` | Estudios de Diagnóstico | sin relación formal | sí / sí | `imaging` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:gastroenterologia` | Gastroenterología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `gi` | Endoscopía | Todos los estudios | `egd_eda_base`, `colonoscopy_base`, `us_abdomen` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:geriatria` | Geriatría | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `geriatric` | — | Todos los estudios | `dexa`, `cbc`, `creatinine` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:ginecologia-y-obstetricia` | Ginecología y Obstetricia | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `obgyn` | Citología, Genética | Todos los estudios | `bhcg`, `us_obstetric_study`, `nipt`, `cyto_pap` | Cardiología, Neurofisiología, Función pulmonar, Endoscopía, Sueño, Audiología | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:hematologia` | Hematología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `heme` | — | Todos los estudios | `cbc`, `ferritin`, `aptt` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:infectologia` | Infectología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `infect` | — | Todos los estudios | `blood_culture`, `urine_culture`, `hiv_ag_ac` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:medicina-critica` | Medicina Crítica | sin relación formal | sí / sí | `critical` | Función pulmonar, Cardiología | Todos los estudios | `capnography`, `blood_culture`, `ecg_12lead` | Neurofisiología, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:medicina-de-rehabilitacion` | Medicina de Rehabilitación | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `rehab` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:medicina-del-deporte` | Medicina del Deporte | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `rehab` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:medicina-del-trabajo` | Medicina del Trabajo | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `publichealth` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:medicina-estetica` | Medicina Estética | sin relación formal | sí / sí | `derm` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:medicina-familiar` | Medicina Familiar | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `general_med` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | — | familia/general | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:medicina-fisica-y-rehabilitacion` | Medicina Física y Rehabilitación | sin relación formal | sí / sí | `rehab` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:medicina-general` | Medicina General | sin relación formal | sí / sí | `general_med` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | — | familia/general | legacy-esp |
| `ui:medicina-integrada` | Medicina Integrada | sin relación formal | sí / sí | `general_med` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | — | familia/general | legacy-esp |
| `ui:medicina-interna` | Medicina Interna | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `general_med` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | — | familia/general | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:medicina-nuclear` | Medicina Nuclear | sin relación formal | sí / sí | `nuclear` | — | Todos los estudios | `nm_bone_scan`, `nm_thyroid_uptake`, `pet_ct` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:nefrologia` | Nefrología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `nephro` | — | Todos los estudios | `creatinine`, `microalbumin`, `us_renal` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:nefrologia-pediatrica` | Nefrología Pediátrica | Pediatría | sí / sí | `peds_nephro` | — | Todos los estudios | `us_renal`, `creatinine`, `urinalysis` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:neumologia` | Neumología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `pulm` | Función pulmonar, Sueño, Endoscopía | Todos los estudios | `spirometry`, `full_pft`, `ct_chest`, `bronchoscopy_base` | Cardiología, Neurofisiología, Citología, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:neumologia-pediatrica` | Neumología Pediátrica | Pediatría | sí / sí | `peds_pulm` | Función pulmonar, Sueño | Todos los estudios | `spirometry`, `full_pft`, `overnight_oximetry` | Cardiología, Neurofisiología, Citología, Endoscopía, Audiología, Genética | exacto | legacy-esp |
| `ui:neurocirugia` | Neurocirugía | sin relación formal | sí / sí | `neuro` | Neurofisiología, Sueño | Todos los estudios | `eeg_routine`, `emg_ncs`, `mr_brain` | Cardiología, Función pulmonar, Citología, Endoscopía, Audiología, Genética | exacto | legacy-esp |
| `ui:neurologia` | Neurología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `neuro` | Neurofisiología, Sueño | Todos los estudios | `eeg_routine`, `emg_ncs`, `mr_brain` | Cardiología, Función pulmonar, Citología, Endoscopía, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:neurologia-pediatrica` | Neurología Pediátrica | Pediatría | sí / sí | `peds_neuro` | Neurofisiología, Sueño | Todos los estudios | `eeg_routine`, `video_eeg`, `mr_brain` | Cardiología, Función pulmonar, Citología, Endoscopía, Audiología, Genética | exacto | legacy-esp |
| `ui:oftalmologia` | Oftalmología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `ophthal` | — | Todos los estudios | `evoked_visual` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:oncologia` | Oncología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `oncology` | Genética | Todos los estudios | `hereditary_cancer_germline`, `pet_ct`, `somatic_tumor_ngs` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:ortopedia-y-traumatologia` | Ortopedia y Traumatología | sin relación formal | sí / sí | `ortho` | — | Todos los estudios | `dexa` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:otorrinolaringologia` | Otorrinolaringología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `ent` | Audiología, Endoscopía, Sueño | Todos los estudios | `audiometry_tonal`, `tympanometry`, `laryngoscopy_base` | Cardiología, Neurofisiología, Función pulmonar, Citología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:patologia` | Patología | sin relación formal | sí / sí | `pathology` | Citología, Genética | Todos los estudios | `cyto_pap`, `cyto_liquid_based` | Cardiología, Neurofisiología, Función pulmonar, Endoscopía, Sueño, Audiología | exacto | legacy-esp |
| `ui:patologia-clinica` | Patología Clínica | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `clinical_lab` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:pediatria` | Pediatría | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `peds` | Audiología, Función pulmonar, Neurofisiología | Todos los estudios | `otoacoustic_emissions`, `cbc`, `us_abdomen` | Cardiología, Citología, Endoscopía, Sueño, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:proctologia` | Proctología | sin relación formal | sí / sí | `gi` | Endoscopía | Todos los estudios | `egd_eda_base`, `colonoscopy_base`, `us_abdomen` | Cardiología, Neurofisiología, Función pulmonar, Citología, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:psiquiatria` | Psiquiatría | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `psychiatry` | Sueño | Todos los estudios | `psg_diagnostic` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:radiologia-e-imagen` | Radiología e Imagen | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `imaging` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:reumatologia` | Reumatología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `rheum` | — | Todos los estudios | `ana`, `anti_ccp`, `rf` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |
| `ui:salud-publica` | Salud Pública | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `publichealth` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:traumatologia-y-ortopedia` | Traumatología y Ortopedia | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `ortho` | — | Todos los estudios | `dexa` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:urgencias-medico-quirurgicas` | Urgencias Médico Quirúrgicas | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `general_med` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | Todos los estudios | — | — | familia/general | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero |
| `ui:urologia` | Urología | medico_cirujano / medico_general / medico_cirujano_partero | sí / sí | `urology` | — | Todos los estudios | `urinalysis`, `us_renal`, `ct_uro` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:medico_cirujano, UI:medico_general, UI:medico_cirujano_partero, legacy-esp |

### Dental (15)

| Proposed key | Specialty / classification | UI parent | Primary / coexists | Profile key | Quick access 1–8 | Lower links | Promoted study keys | Deemphasized quick groupers | Fallback / gap | Source |
|---|---|---|---|---|---|---|---|---|---|---|
| `ui:cirugia-maxilofacial` | Cirugía Maxilofacial | sin relación formal | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | legacy-esp |
| `ui:cirugia-oral-y-maxilofacial` | Cirugía Oral y Maxilofacial | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:dentista` | Dentista | sin relación formal | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | legacy-esp |
| `ui:endodoncia` | Endodoncia | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista, legacy-esp |
| `ui:implantologia` | Implantología | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:implantologia-dental` | Implantología Dental | sin relación formal | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | legacy-esp |
| `ui:odontologia` | Odontología | sin relación formal | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | legacy-esp |
| `ui:odontologia-estetica` | Odontología Estética | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:odontopediatria` | Odontopediatría | cirujano_dentista | sí / sí | `peds_dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin estudios dentales activos | UI:cirujano_dentista, legacy-esp |
| `ui:ortodoncia` | Ortodoncia | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista, legacy-esp |
| `ui:ortopedia-dental` | Ortopedia Dental | sin relación formal | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | legacy-esp |
| `ui:patologia-bucal` | Patología Bucal | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:periodoncia` | Periodoncia | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:protesis-bucal` | Prótesis Bucal | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |
| `ui:rehabilitacion-oral` | Rehabilitación Oral | cirujano_dentista | sí / sí | `dental` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin estudios dentales activos | UI:cirujano_dentista |

### Otra profesión / indeterminada (44)

| Proposed key | Specialty / classification | UI parent | Primary / coexists | Profile key | Quick access 1–8 | Lower links | Promoted study keys | Deemphasized quick groupers | Fallback / gap | Source |
|---|---|---|---|---|---|---|---|---|---|---|
| `ui:banco-de-sangre` | Banco de Sangre | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:enfermeria-comunitaria` | Enfermería Comunitaria | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-en-cuidados-intensivos` | Enfermería en Cuidados Intensivos | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-general` | Enfermería General | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-geriatrica` | Enfermería Geriátrica | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-obstetrica` | Enfermería Obstétrica | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-pediatrica` | Enfermería Pediátrica | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:enfermeria-quirurgica` | Enfermería Quirúrgica | enfermeria | sí / sí | `nursing` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | familia/general; sin afinidad fuerte en catálogo | UI:enfermeria |
| `ui:farmacia-clinica` | Farmacia Clínica | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:fisioterapia-deportiva` | Fisioterapia Deportiva | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:fisioterapia-geriatrica` | Fisioterapia Geriátrica | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:fisioterapia-neurologica` | Fisioterapia Neurológica | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:fisioterapia-ortopedica` | Fisioterapia Ortopédica | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:fisioterapia-pediatrica` | Fisioterapia Pediátrica | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:hematologia-de-laboratorio` | Hematología de Laboratorio | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:inmunologia` | Inmunología | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:kinesiologia` | Kinesiología | sin relación formal | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:microbiologia` | Microbiología | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:neuropsicologia` | Neuropsicología | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:nutricion-bariatrica` | Nutrición Bariátrica | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-clinica` | Nutrición Clínica | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-deportiva` | Nutrición Deportiva | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-en-diabetes` | Nutrición en Diabetes | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-geriatrica` | Nutrición Geriátrica | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-oncologica` | Nutrición Oncológica | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-pediatrica` | Nutrición Pediátrica | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutricion-renal` | Nutrición Renal | lic_nutricion | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_nutricion |
| `ui:nutriologia` | Nutriología | sin relación formal | sí / sí | `nutrition` | — | Todos los estudios | `hba1c`, `chol_total`, `triglycerides` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:optometria` | Optometría | sin relación formal | sí / sí | `ophthal` | — | Todos los estudios | `evoked_visual` | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | legacy-esp |
| `ui:otra-especificar` | Otra (especificar) | sin relación formal | sí / sí | `other` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | general; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:podologia` | Podología | sin relación formal | sí / sí | `podiatry` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:psicologia` | Psicología | sin relación formal | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | legacy-esp |
| `ui:psicologia-clinica` | Psicología Clínica | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:psicologia-educativa` | Psicología Educativa | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:psicologia-infantil` | Psicología Infantil | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:psicologia-organizacional` | Psicología Organizacional | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:psicoterapia` | Psicoterapia | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:quimica-clinica` | Química Clínica | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |
| `ui:rehabilitacion-fisica` | Rehabilitación Física | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:rehabilitacion-postquirurgica` | Rehabilitación Postquirúrgica | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:terapia-de-pareja` | Terapia de Pareja | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:terapia-familiar` | Terapia Familiar | lic_psicologia | sí / sí | `psychology` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:lic_psicologia |
| `ui:terapia-manual` | Terapia Manual | lic_fisioterapia / lic_rehabilitacion | sí / sí | `physio` | Neurofisiología | Todos los estudios | `emg_ncs`, `dexa` | Cardiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto | UI:lic_fisioterapia, UI:lic_rehabilitacion |
| `ui:toxicologia` | Toxicología | qfb | sí / sí | `qfb` | — | Todos los estudios | — | Cardiología, Neurofisiología, Función pulmonar, Citología, Endoscopía, Sueño, Audiología, Genética | exacto; sin afinidad fuerte en catálogo | UI:qfb |

### Public discoverability suggestions (20, not specialties)

| Parent specialty | Suggested public classification | Specialty profile authority? |
|---|---|---|
| Anestesiología | Clínica del dolor | No |
| Anestesiología | Cuidados paliativos | No |
| Anestesiología | Manejo del dolor crónico | No |
| Anestesiología | Medicina perioperatoria | No |
| Medicina Interna | Control de diabetes | No |
| Medicina Interna | Hipertensión | No |
| Medicina Interna | Medicina preventiva | No |
| Medicina Interna | Enfermedades crónicas | No |
| Pediatría | Crecimiento y desarrollo | No |
| Pediatría | Vacunación | No |
| Pediatría | Pediatría preventiva | No |
| Cirugía Oral y Maxilofacial | Cirugía de terceros molares | No |
| Cirugía Oral y Maxilofacial | Implantes dentales | No |
| Cirugía Oral y Maxilofacial | Cirugía maxilofacial | No |
| Ortodoncia | Brackets | No |
| Ortodoncia | Alineadores | No |
| Ortodoncia | Ortopedia maxilar | No |
| Nutrición Clínica | Control de peso | No |
| Nutrición Clínica | Nutrición metabólica | No |
| Nutrición Clínica | Nutrición para diabetes | No |

These phrases include services or conditions (for example, brackets, hypertension, vaccination). They are not additional orderable-study categories and do not become primary specialties.

## Study-level audit — all 183 active canonical identities

`Primary` contains navigation groupers; `Quick` is the one canonical quick-category destination where present; `Affinity` lists proposed navigation profile keys when a strong, explicit study-level promotion was identified. Blank affinity means universal catalog access with no specialty-specific ranking. Aliases are the current `aliases_json` values. `Catalog` is `canonical/active` for every row; custom orders are a separate writer path, not rows here.

### `LABORATORIO` (81)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `1` / `glucose` | Glucosa | — | LABORATORIO | — | — | canonical/active |
| `2` / `hba1c` | HbA1c | — | LABORATORIO | — | `endocrine`, `nutrition` | canonical/active |
| `3` / `fructosamine` | Fructosamina | — | LABORATORIO | — | — | canonical/active |
| `4` / `ogtt` | Curva de glucosa (OGTT) | OGTT | LABORATORIO | — | — | canonical/active |
| `5` / `urea` | Urea | — | LABORATORIO | — | — | canonical/active |
| `6` / `creatinine` | Creatinina | — | LABORATORIO | — | `geriatric`, `nephro`, `peds_nephro` | canonical/active |
| `7` / `bun` | Nitrógeno ureico (BUN) | — | LABORATORIO | — | — | canonical/active |
| `8` / `uric_acid` | Ácido úrico | — | LABORATORIO | — | — | canonical/active |
| `9` / `sodium` | Sodio | — | LABORATORIO | — | — | canonical/active |
| `10` / `potassium` | Potasio | — | LABORATORIO | — | — | canonical/active |
| `11` / `chloride` | Cloro | — | LABORATORIO | — | — | canonical/active |
| `12` / `calcium` | Calcio | — | LABORATORIO | — | — | canonical/active |
| `13` / `magnesium` | Magnesio | — | LABORATORIO | — | — | canonical/active |
| `14` / `phosphorus` | Fósforo | — | LABORATORIO | — | — | canonical/active |
| `15` / `chol_total` | Colesterol total | — | LABORATORIO | — | `nutrition` | canonical/active |
| `16` / `hdl` | HDL | — | LABORATORIO | — | — | canonical/active |
| `17` / `ldl` | LDL | — | LABORATORIO | — | — | canonical/active |
| `18` / `triglycerides` | Triglicéridos | — | LABORATORIO | — | `nutrition` | canonical/active |
| `19` / `non_hdl` | Colesterol no-HDL | — | LABORATORIO | — | — | canonical/active |
| `20` / `ast` | AST (TGO) | — | LABORATORIO | — | — | canonical/active |
| `21` / `alt` | ALT (TGP) | — | LABORATORIO | — | — | canonical/active |
| `22` / `alp` | Fosfatasa alcalina (ALP) | — | LABORATORIO | — | — | canonical/active |
| `23` / `ggt` | GGT | — | LABORATORIO | — | — | canonical/active |
| `24` / `bilirubin_total` | Bilirrubina total | — | LABORATORIO | — | — | canonical/active |
| `25` / `bilirubin_direct` | Bilirrubina directa | — | LABORATORIO | — | — | canonical/active |
| `26` / `bilirubin_indirect` | Bilirrubina indirecta | — | LABORATORIO | — | — | canonical/active |
| `27` / `albumin` | Albúmina | — | LABORATORIO | — | — | canonical/active |
| `28` / `total_protein` | Proteínas totales | — | LABORATORIO | — | — | canonical/active |
| `29` / `amylase` | Amilasa | — | LABORATORIO | — | — | canonical/active |
| `30` / `lipase` | Lipasa | — | LABORATORIO | — | — | canonical/active |
| `31` / `cbc` | Biometría hemática (BH / CBC) | BH / CBC, Biometría hemática, CBC | LABORATORIO | — | `geriatric`, `heme`, `peds`, `peds_onc` | canonical/active |
| `32` / `platelets` | Plaquetas | — | LABORATORIO | — | — | canonical/active |
| `33` / `iron` | Hierro sérico | — | LABORATORIO | — | — | canonical/active |
| `34` / `tibc` | CTFH (TIBC) | — | LABORATORIO | — | — | canonical/active |
| `35` / `transferrin_sat` | % Saturación transferrina | — | LABORATORIO | — | — | canonical/active |
| `36` / `ferritin` | Ferritina | — | LABORATORIO | — | `heme` | canonical/active |
| `37` / `aptt` | TTPa (aPTT) | — | LABORATORIO | — | `heme` | canonical/active |
| `38` / `fibrinogen` | Fibrinógeno | — | LABORATORIO | — | — | canonical/active |
| `39` / `d_dimer` | Dímero D | — | LABORATORIO | — | — | canonical/active |
| `40` / `tsh` | TSH | — | LABORATORIO | — | `endocrine` | canonical/active |
| `41` / `ft4` | T4 libre | — | LABORATORIO | — | `endocrine` | canonical/active |
| `42` / `ft3` | T3 libre | — | LABORATORIO | — | — | canonical/active |
| `43` / `anti_tpo` | Anti-TPO | — | LABORATORIO | — | — | canonical/active |
| `44` / `anti_tg` | Anti-tiroglobulina | — | LABORATORIO | — | — | canonical/active |
| `45` / `bhcg` | β-hCG | — | LABORATORIO | — | `obgyn` | canonical/active |
| `46` / `lh` | LH | — | LABORATORIO | — | — | canonical/active |
| `47` / `fsh` | FSH | — | LABORATORIO | — | — | canonical/active |
| `48` / `prolactin` | Prolactina | — | LABORATORIO | — | — | canonical/active |
| `49` / `estradiol` | Estradiol | — | LABORATORIO | — | — | canonical/active |
| `50` / `progesterone` | Progesterona | — | LABORATORIO | — | — | canonical/active |
| `51` / `testosterone_total` | Testosterona total | — | LABORATORIO | — | — | canonical/active |
| `52` / `testosterone_free` | Testosterona libre | — | LABORATORIO | — | — | canonical/active |
| `53` / `crp_hs` | PCR ultrasensible | — | LABORATORIO | — | — | canonical/active |
| `54` / `esr` | VSG | — | LABORATORIO | — | — | canonical/active |
| `55` / `ana` | ANA | — | LABORATORIO | — | `rheum` | canonical/active |
| `56` / `ena` | ENA | — | LABORATORIO | — | — | canonical/active |
| `57` / `anca` | ANCA | — | LABORATORIO | — | — | canonical/active |
| `58` / `rf` | Factor reumatoide | — | LABORATORIO | — | `rheum` | canonical/active |
| `59` / `anti_ccp` | Anti-CCP | — | LABORATORIO | — | `rheum` | canonical/active |
| `60` / `c3` | C3 | — | LABORATORIO | — | — | canonical/active |
| `61` / `c4` | C4 | — | LABORATORIO | — | — | canonical/active |
| `62` / `vitamin_d` | Vitamina D | — | LABORATORIO | — | — | canonical/active |
| `63` / `vitamin_b12` | Vitamina B12 | — | LABORATORIO | — | — | canonical/active |
| `64` / `folate` | Ácido fólico | — | LABORATORIO | — | — | canonical/active |
| `65` / `igg` | IgG | — | LABORATORIO | — | — | canonical/active |
| `66` / `iga` | IgA | — | LABORATORIO | — | — | canonical/active |
| `67` / `igm` | IgM | — | LABORATORIO | — | — | canonical/active |
| `68` / `hiv_ag_ac` | VIH Ag/Ac | — | LABORATORIO | — | `infect` | canonical/active |
| `69` / `hbsag` | HBsAg | — | LABORATORIO | — | — | canonical/active |
| `70` / `anti_hbs` | Anti-HBs | — | LABORATORIO | — | — | canonical/active |
| `71` / `anti_hbc` | Anti-HBc | — | LABORATORIO | — | — | canonical/active |
| `72` / `hcv_ab` | VHC (anticuerpos) | — | LABORATORIO | — | — | canonical/active |
| `73` / `urine_culture` | Urocultivo | — | LABORATORIO | — | `infect` | canonical/active |
| `74` / `blood_culture` | Hemocultivo | — | LABORATORIO | — | `critical`, `infect` | canonical/active |
| `75` / `stool_culture` | Coprocultivo | — | LABORATORIO | — | — | canonical/active |
| `76` / `throat_swab` | Exudado faríngeo | — | LABORATORIO | — | — | canonical/active |
| `77` / `vaginal_swab` | Exudado vaginal | — | LABORATORIO | — | — | canonical/active |
| `78` / `urinalysis` | EGO (examen general de orina) | EGO, examen general de orina | LABORATORIO | — | `peds_nephro`, `urology` | canonical/active |
| `79` / `microalbumin` | Microalbuminuria | — | LABORATORIO | — | `nephro` | canonical/active |
| `80` / `fecal_occult_blood` | Sangre oculta en heces | — | LABORATORIO | — | — | canonical/active |
| `81` / `stool_ova_parasites` | Coproparasitoscópico | — | LABORATORIO | — | — | canonical/active |

### `IMAGEN` (34)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `82` / `rx_chest` | RX Tórax | — | IMAGENOLOGÍA | — | — | canonical/active |
| `83` / `rx_abdomen` | RX Abdomen | — | IMAGENOLOGÍA | — | — | canonical/active |
| `84` / `rx_pelvis` | RX Pelvis | — | IMAGENOLOGÍA | — | — | canonical/active |
| `85` / `rx_cspine` | RX Columna cervical | — | IMAGENOLOGÍA | — | — | canonical/active |
| `86` / `rx_lspine` | RX Columna lumbar | — | IMAGENOLOGÍA | — | — | canonical/active |
| `87` / `rx_shoulder` | RX Hombro | — | IMAGENOLOGÍA | — | — | canonical/active |
| `88` / `rx_knee` | RX Rodilla | — | IMAGENOLOGÍA | — | — | canonical/active |
| `89` / `rx_ankle` | RX Tobillo | — | IMAGENOLOGÍA | — | — | canonical/active |
| `90` / `rx_hand` | RX Mano | — | IMAGENOLOGÍA | — | — | canonical/active |
| `91` / `us_abdomen` | US Abdomen | — | IMAGENOLOGÍA | — | `gi`, `peds` | canonical/active |
| `92` / `us_renal` | US Renal | — | IMAGENOLOGÍA | — | `nephro`, `peds_nephro`, `urology` | canonical/active |
| `93` / `us_pelvic` | US Pélvico | — | IMAGENOLOGÍA | — | — | canonical/active |
| `94` / `us_obstetric_study` | US Obstétrico | — | IMAGENOLOGÍA | — | `obgyn` | canonical/active |
| `95` / `us_thyroid` | US Tiroides | — | IMAGENOLOGÍA | — | — | canonical/active |
| `96` / `us_soft_tissue` | US Partes blandas | — | IMAGENOLOGÍA | — | — | canonical/active |
| `97` / `us_testicular` | US Testicular | — | IMAGENOLOGÍA | — | — | canonical/active |
| `98` / `ct_head` | TAC Cráneo | — | IMAGENOLOGÍA | — | — | canonical/active |
| `99` / `ct_chest` | TAC Tórax | — | IMAGENOLOGÍA | — | `pulm` | canonical/active |
| `100` / `ct_abdomen_pelvis` | TAC Abdomen y pelvis | — | IMAGENOLOGÍA | — | `surgery` | canonical/active |
| `101` / `ct_uro` | UroTAC (vías urinarias) | — | IMAGENOLOGÍA | — | `urology` | canonical/active |
| `102` / `mr_brain` | RM Cerebro | — | IMAGENOLOGÍA | — | `neuro`, `peds_neuro` | canonical/active |
| `103` / `mr_knee` | RM Rodilla | — | IMAGENOLOGÍA | — | — | canonical/active |
| `104` / `mr_shoulder` | RM Hombro | — | IMAGENOLOGÍA | — | — | canonical/active |
| `105` / `mr_abdomen` | RM Abdomen | — | IMAGENOLOGÍA | — | — | canonical/active |
| `106` / `mammo` | Mamografía | — | IMAGENOLOGÍA | — | — | canonical/active |
| `107` / `breast_us` | US Mama | — | IMAGENOLOGÍA | — | — | canonical/active |
| `108` / `nm_bone_scan` | Gammagrama óseo | — | IMAGENOLOGÍA | — | `nuclear` | canonical/active |
| `109` / `nm_thyroid_uptake` | Captación tiroidea | — | IMAGENOLOGÍA | — | `nuclear` | canonical/active |
| `110` / `pet_ct` | PET-CT | — | IMAGENOLOGÍA | — | `nuclear`, `oncology` | canonical/active |
| `111` / `dexa` | Densitometría ósea (DEXA) | — | IMAGENOLOGÍA | — | `geriatric`, `ortho`, `physio`, `rehab` | canonical/active |
| `112` / `fluoro_hsg` | Histerosalpingografía (HSG) | — | IMAGENOLOGÍA | — | — | canonical/active |
| `113` / `fluoro_vcug` | Cistouretrografía miccional (VCUG) | — | IMAGENOLOGÍA | — | — | canonical/active |
| `114` / `fluoro_ugi` | Tránsito esófago-gastro-duodenal (UGI) | — | IMAGENOLOGÍA | — | — | canonical/active |
| `115` / `fluoro_barium_enema` | Enema baritado | — | IMAGENOLOGÍA | — | — | canonical/active |

### `CARDIOVASCULAR` (13)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `116` / `ecg_12lead` | ECG 12 derivaciones | — | ESTUDIOS FUNCIONALES | Cardiología | `anesthesia`, `cardio`, `critical` | canonical/active |
| `117` / `ecg_rhythm_strip` | Tira de ritmo | — | ESTUDIOS FUNCIONALES | Cardiología | — | canonical/active |
| `118` / `holter` | Holter / monitorización ECG ambulatoria | — | ESTUDIOS FUNCIONALES | Cardiología | `cardio` | canonical/active |
| `119` / `abpm_mapa` | MAPA (presión arterial ambulatoria / ABPM) | MAPA 24 h (presión arterial ambulatoria), ABPM | ESTUDIOS FUNCIONALES | Cardiología | `cardio` | canonical/active |
| `120` / `echo_tte` | Ecocardiograma transtorácico (ETT) | ETT | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | `cardio` | canonical/active |
| `121` / `echo_tes` | Ecocardiograma transesofágico (ETE) | ETE | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | — | canonical/active |
| `122` / `stress_test` | Prueba de esfuerzo (ECG de esfuerzo) | — | ESTUDIOS FUNCIONALES | Cardiología | — | canonical/active |
| `123` / `stress_echo` | Ecocardiograma de estrés | — | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | — | canonical/active |
| `124` / `tilt_table` | Prueba de mesa basculante (Tilt table) | Mesa inclinada (Tilt test) | ESTUDIOS FUNCIONALES | Cardiología | — | canonical/active |
| `125` / `carotid_doppler` | Doppler carotídeo | — | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | `vascular` | canonical/active |
| `126` / `lower_ext_art_doppler` | Doppler arterial de miembros inferiores | — | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | `vascular` | canonical/active |
| `127` / `lower_ext_venous_doppler` | Doppler venoso de miembros inferiores | — | ESTUDIOS FUNCIONALES, IMAGENOLOGÍA | Cardiología | `vascular` | canonical/active |
| `183` / `ankle_brachial_index` | Índice tobillo-brazo (ITB/ABI) | ITB, ABI | ESTUDIOS FUNCIONALES | Cardiología | `vascular` | canonical/active |

### `NEUROFISIOLOGIA` (9)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `169` / `eeg_routine` | EEG rutinario | — | ESTUDIOS FUNCIONALES | Neurofisiología | `neuro`, `peds_neuro` | canonical/active |
| `170` / `eeg_sleep_deprived` | EEG con privación de sueño | — | ESTUDIOS FUNCIONALES | Neurofisiología | — | canonical/active |
| `171` / `video_eeg` | Video-EEG (prolongado) | — | ESTUDIOS FUNCIONALES | Neurofisiología | `peds_neuro` | canonical/active |
| `172` / `emg_ncs` | EMG + Velocidades de conducción nerviosa (VCN) | EMG + VCN (estudio completo) | ESTUDIOS FUNCIONALES | Neurofisiología | `neuro`, `physio`, `rehab` | canonical/active |
| `173` / `repetitive_nerve_stimulation` | Estimulación repetitiva | — | ESTUDIOS FUNCIONALES | Neurofisiología | — | canonical/active |
| `174` / `sfemg` | EMG de fibra única (SFEMG) | — | ESTUDIOS FUNCIONALES | Neurofisiología | — | canonical/active |
| `175` / `evoked_visual` | Potenciales evocados visuales (PEV) | — | ESTUDIOS FUNCIONALES | Neurofisiología | `ophthal` | canonical/active |
| `176` / `evoked_auditory_baep` | Potenciales auditivos de tronco (PEAT/BAEP) | — | ESTUDIOS FUNCIONALES | Neurofisiología | — | canonical/active |
| `177` / `evoked_ssep` | Potenciales somatosensoriales (PESS/SSEP) | — | ESTUDIOS FUNCIONALES | Neurofisiología | — | canonical/active |

### `FUNCION_PULMONAR` (9)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `155` / `spirometry` | Espirometría | — | ESTUDIOS FUNCIONALES | Función pulmonar | `allergy`, `peds_pulm`, `pulm` | canonical/active |
| `156` / `dlco` | DLCO (difusión de CO) | — | ESTUDIOS FUNCIONALES | Función pulmonar | — | canonical/active |
| `157` / `plethysmography` | Volúmenes pulmonares (pletismografía corporal) | — | ESTUDIOS FUNCIONALES | Función pulmonar | — | canonical/active |
| `158` / `full_pft` | Pruebas funcionales respiratorias completas (PFR completo) | PFR completo | ESTUDIOS FUNCIONALES | Función pulmonar | `peds_pulm`, `pulm` | canonical/active |
| `159` / `feno` | FeNO (óxido nítrico exhalado) | — | ESTUDIOS FUNCIONALES | Función pulmonar | — | canonical/active |
| `160` / `six_min_walk` | Caminata 6 minutos (6MWT) | 6MWT | ESTUDIOS FUNCIONALES | Función pulmonar | — | canonical/active |
| `161` / `cpet` | Prueba de esfuerzo cardiopulmonar (CPET) | — | ESTUDIOS FUNCIONALES | Función pulmonar | — | canonical/active |
| `162` / `overnight_oximetry` | Oximetría nocturna | — | ESTUDIOS FUNCIONALES | Función pulmonar | `peds_pulm` | canonical/active |
| `163` / `capnography` | Capnografía | — | ESTUDIOS FUNCIONALES | Función pulmonar | `anesthesia`, `critical` | canonical/active |

### `AUDIOLOGIA` (5)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `178` / `audiometry_tonal` | Audiometría tonal | — | ESTUDIOS FUNCIONALES | Audiología | `audiology`, `ent` | canonical/active |
| `179` / `audiometry_speech` | Audiometría verbal | — | ESTUDIOS FUNCIONALES | Audiología | — | canonical/active |
| `180` / `tympanometry` | Timpanometría (impedanciometría) | impedanciometría | ESTUDIOS FUNCIONALES | Audiología | `audiology`, `ent` | canonical/active |
| `181` / `otoacoustic_emissions` | Emisiones otoacústicas (OEA) | OEA | ESTUDIOS FUNCIONALES | Audiología | `peds` | canonical/active |
| `182` / `vng` | Videonistagmografía (VNG) | — | ESTUDIOS FUNCIONALES | Audiología | `audiology` | canonical/active |

### `PATOLOGIA` (2)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `141` / `cyto_pap` | Papanicolaou (convencional) | — | LABORATORIO | Citología | `colposcopy`, `obgyn`, `pathology` | canonical/active |
| `142` / `cyto_liquid_based` | Citología en base líquida | — | LABORATORIO | Citología | `colposcopy`, `pathology` | canonical/active |

### `ENDOSCOPIA` (13)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `128` / `egd_eda_base` | EGD/EDA (endoscopia alta) | EGD, EDA | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | `gi` | canonical/active |
| `129` / `colonoscopy_base` | Colonoscopia | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | `gi` | canonical/active |
| `130` / `flex_sig_base` | Sigmoidoscopia flexible | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `131` / `anoscopy_base` | Anoscopia | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `132` / `proctoscopy_base` | Proctoscopia | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `133` / `ercp_cpre_base` | CPRE / ERCP | CPRE, ERCP | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `134` / `eus_use_base` | EUS/USE (ultrasonido endoscópico) | EUS, USE | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `135` / `capsule_base` | Cápsula endoscópica | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `136` / `enteroscopy_base` | Enteroscopia | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `137` / `bronchoscopy_base` | Broncoscopía | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | `pulm` | canonical/active |
| `138` / `ebus_base` | EBUS (ultrasonido endobronquial) | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |
| `139` / `laryngoscopy_base` | Laringoscopía flexible | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | `ent`, `head_neck_surgery` | canonical/active |
| `140` / `pleuroscopy_base` | Pleuroscopía / Toracoscopía médica | — | PROCEDIMIENTOS DIAGNÓSTICOS | Endoscopía | — | canonical/active |

### `SUENO` (5)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `164` / `psg_diagnostic` | Polisomnografía (PSG) diagnóstica | PSG diagnóstica | ESTUDIOS FUNCIONALES | Sueño | `psychiatry` | canonical/active |
| `165` / `psg_titration` | PSG con titulación (CPAP/BiPAP) | — | ESTUDIOS FUNCIONALES | Sueño | — | canonical/active |
| `166` / `hsat` | Estudio domiciliario de apnea (HSAT) | HSAT (apnea del sueño en casa) | ESTUDIOS FUNCIONALES | Sueño | — | canonical/active |
| `167` / `mslt` | MSLT (latencias múltiples del sueño) | — | ESTUDIOS FUNCIONALES | Sueño | — | canonical/active |
| `168` / `mwt` | MWT (mantenimiento de la vigilia) | — | ESTUDIOS FUNCIONALES | Sueño | — | canonical/active |

### `GENETICA` (12)

| ID / key | Display name | Aliases | Primary | Quick | Affinity profile keys | Catalog |
|---|---|---|---|---|---|---|
| `143` / `karyotype` | Cariotipo | — | LABORATORIO | Genética | — | canonical/active |
| `144` / `cma_microarray` | Microarreglo cromosómico (CMA / Microarray) | CMA, Microarray | LABORATORIO | Genética | — | canonical/active |
| `145` / `wes` | Exoma clínico (WES) | WES | LABORATORIO | Genética | — | canonical/active |
| `146` / `wgs` | Genoma clínico (WGS) | WGS | LABORATORIO | Genética | — | canonical/active |
| `147` / `nipt` | NIPT (tamiz prenatal no invasivo) | NIPT | LABORATORIO | Genética | `obgyn` | canonical/active |
| `148` / `carrier_screening` | Tamiz de portadores (Carrier screening) | — | LABORATORIO | Genética | — | canonical/active |
| `149` / `hereditary_cancer_germline` | Panel de cáncer hereditario (multigénico) | — | LABORATORIO | Genética | `oncology` | canonical/active |
| `150` / `brca1_2` | BRCA1/BRCA2 (germinal) | — | LABORATORIO | Genética | — | canonical/active |
| `151` / `lynch` | Síndrome de Lynch (germinal) | — | LABORATORIO | Genética | — | canonical/active |
| `152` / `thrombophilia` | Panel de trombofilia (genético) | — | LABORATORIO | Genética | — | canonical/active |
| `153` / `pgx` | Panel farmacogenómico (PGx) | PGx | LABORATORIO | Genética | — | canonical/active |
| `154` / `somatic_tumor_ngs` | Panel tumoral (NGS) — somático | — | LABORATORIO | Genética | `oncology`, `peds_onc` | canonical/active |

## Gaps, normalization, and implementation recommendation

- **Dental:** 15 finite dental labels plus one dental professional title are present, but `DENTAL` has zero studies. Orthodontic, oral/maxillofacial, prosthodontic, periodontal, endodontic, and pediatric dental profiles therefore show no weak quick shortcut. Imaging remains reachable from the stable top route and all 183 studies through full catalog. No dental eligibility rule is inferred.
- **Ophthalmology/optometry:** `OFTALMOLOGIA` has zero studies; `evoked_visual` is only an adjacent neurophysiology study. **Pathology:** two cervical cytology entries only; no broad surgical histopathology set. **Urology:** imaging/urine tests exist, but no dedicated urologic endoscopy. **Pediatrics:** some general tests may be used, but no pediatric-specific canonical panels are established. These are catalog curation gaps, not reasons to fabricate items.
- **Name resolution candidates requiring Director review:** `Traumatología y Ortopedia` / `Ortopedia y Traumatología`; `Medicina de Rehabilitación` / `Medicina Física y Rehabilitación`; `Cirugía Oral y Maxilofacial` / `Cirugía Maxilofacial`; `Implantología` / `Implantología Dental`. `Patología Clínica` / `Análisis Clínicos`, `Nutriología` / `Nutrición Clínica`, and `Odontopediatría` / “Odontología Pediátrica” require confirmation rather than automatic equivalence. Gendered public titles and spelling variants should resolve through reviewed aliases, not string heuristics alone.
- **Therapy/procedure boundary:** `ercp_cpre_base`, `bronchoscopy_base`, `colonoscopy_base`, `pleuroscopy_base`, and other endoscopy names can be associated with therapeutic or biopsy maneuvers in practice; their current catalog rows specify only base study names. `psg_titration` includes PAP titration yet is currently a sleep-study row. Do not treat this proposed diagnostic navigation as a therapy order, and do not activate the separate deferred therapy/procedure band from this audit.
- **NAVMAP02 V1 authority:** versioned application configuration, reviewed and committed beside the clinical catalog read model; no new table is needed for V1. Version `1` should contain explicit label aliases, parent/family fallback keys, 12 navigation groupers, profile quick-order arrays (0–8), optional promoted `study_type_key` arrays, and a full-catalog escape-hatch assertion. Hydrate only active study keys at runtime, omit empty shortcuts, and leave TAX03C global search order unchanged. Make config changes reviewable by source diff. Never attach navigation-profile version to historical clinical orders.
- **Required gates before implementation:** assert every configured study key exists and active, every current finite label resolves, no quick/lower duplicates, all four primary routes cover every active study, no full-catalog suppression, no permission change, and deterministic precedence for a verified primary credential and multiple secondaries. New arbitrary profile text resolves to family/general fallback.

## Coverage assertions for this snapshot

- Finite classification entries: **134** = 124 specialty labels + 10 professional-title keys. Medical: **68** including 3 titles; dental: **16** including 1 title; other/indeterminate: **50** including 6 titles. The 20 public marketing suggestions are separate.
- Specialty labels with an explicit profile template: **97** (including five pediatric descendants with their own profile); labels actually inheriting a parent profile: **0**; labels using family/general fallback: **27**. These counts are disjoint and sum to 124. No enumerated label is unresolved; arbitrary free text is necessarily open-ended and falls back deterministically.
- Active studies reviewed: **183**; unmapped active studies: **0**. Navigation groupers: **12** (4 primary + 8 quick). Study-to-grouper relations: **257**. Specialty-label-to-quick-grouper relations: **104**. Specialty-label-to-promoted-study relations: **166**. Counts exclude 10 title fallback rows and 20 marketing suggestions to avoid double counting.
- Every finite specialty row and title row retains `Todos los estudios`; every study retains exact canonical identity. The audit cannot assert that all possible free-text specialty values have been individually enumerated, because no bounded specialty authority exists and the review DB has zero profile rows.
