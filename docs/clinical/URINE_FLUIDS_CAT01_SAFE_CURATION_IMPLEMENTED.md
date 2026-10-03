# URINE-FLUIDS-CAT01 — curación segura aplicada con ORD-COMP01

Fecha: 2026-10-03. Baseline: `b156e92deec3294648510bd773af7afad472d615`.

## Decisión y límites

La clasificación siguiente es una **decisión de modelado de MXMED basada en las fuentes**, no una recomendación clínica ni una afirmación de que una prueba diferida no sea ordenable en un laboratorio real. El catálogo registra órdenes atómicas y resultados documentales. No modela todavía componentes analíticos, manejo de muestras ni colecciones temporizadas.

Los perfiles ACR/PCR se incorporan como una sola orden de perfil cada uno, igual que los paneles ya soportados. No se agregan los cocientes calculados como órdenes aisladas. `microalbumin` permanece con su significado histórico; no se le atribuye silenciosamente creatinina o ACR. Las nuevas identidades no aceptan sustitución por un analito sérico. Los catálogos de referencia confirman identidades de orden; sus requisitos logísticos no se convierten aquí en un subsistema de recepción de especímenes.

**59 candidatos revisados; 6 añadidos (4 seguros + 2 perfiles atómicos soportados); 3 existentes conservados; 50 diferidos.** Catálogo activo: 202 → 208.

## Claves añadidas

| Clave | Nombre | Grupo operativo |
| --- | --- | --- |
| `urine_albumin_creatinine_panel` | Albúmina y creatinina urinarias con relación (muestra aislada) | `CLINICAL_LAB` |
| `urine_protein_creatinine_panel` | Proteínas y creatinina urinarias con relación (muestra aislada) | `CLINICAL_LAB` |
| `urine_osmolality` | Osmolalidad urinaria | `CLINICAL_LAB` |
| `csf_cell_count` | Recuento celular y diferencial en LCR | `CLINICAL_LAB` |
| `synovial_crystals` | Cristales en líquido sinovial | `CLINICAL_LAB` |
| `semen_analysis` | Espermatobioscopía básica | `CLINICAL_LAB` |

Migración de datos: `modules/clinical/db/migrations/2026_10_03_19_urine_fluids_catalog.sql`. Inserciones idempotentes sin cambios de tabla, sin modificar claves existentes ni reactivar pruebas antiguas. Se verificó en base desechable antes de aplicar únicamente las seis filas de catálogo a `mxmed_director_review_lon07c`; no se crearon órdenes/resultados/pacientes de QA allí.

## Auditoría completa

Cada fila tiene exactamente una clasificación. Las referencias `PREP` apoyan la necesidad de una definición específica de muestra; no se usan para declarar una identidad exacta no verificada como segura.

| Candidato | Clasificación | Disposición / límite | Fuente |
| --- | --- | --- | --- |
| Examen general de orina | `ALIAS_OF_EXISTING` | EXISTING: urinalysis; no nueva identidad | [EGO](https://www.mayoclinic.org/tests-procedures/urinalysis/about/pac-20384907) |
| Microalbuminuria / albúmina urinaria | `ALIAS_OF_EXISTING` | EXISTING: microalbumin; no asumir que incluye creatinina ni cociente | [ALBR](https://www.mayocliniclabs.com/test-catalog/overview/609731) |
| Albúmina + creatinina con relación, muestra aislada | `ORDER_PROFILE_OR_PANEL` | ADDED: urine_albumin_creatinine_panel; perfil atómico, no analitos estructurados | [ALBR](https://www.mayocliniclabs.com/test-catalog/overview/609731) |
| Proteínas totales urinarias aisladas | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: no reutilizar total_protein sérico como orina | [RPTU1](https://www.mayocliniclabs.com/test-catalog/overview/614004) |
| Proteínas + creatinina con relación, muestra aislada | `ORDER_PROFILE_OR_PANEL` | ADDED: urine_protein_creatinine_panel; perfil atómico | [RPTU1](https://www.mayocliniclabs.com/test-catalog/overview/614004) |
| Proteinuria de 24 horas | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: duración y volumen; no crear variante suelta | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Creatinina en orina de 24 horas | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: duración y volumen; no crear variante suelta | [CRCL](https://www.mayocliniclabs.com/test-catalog/overview/615813) |
| Creatinina urinaria aislada | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: espécimen distinto a creatinine vigente | [RPTU1](https://www.mayocliniclabs.com/test-catalog/overview/614004) |
| Depuración de creatinina | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: recolección, volumen, muestra sérica y parámetros del cálculo | [CRCL](https://www.mayocliniclabs.com/test-catalog/overview/615813) |
| Osmolalidad urinaria | `SAFE_CANONICAL_ORDERABLE` | ADDED: urine_osmolality; orden de muestra aislada | [OSMU](https://www.mayocliniclabs.com/test-catalog/Overview/41236) |
| pH urinario | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: fijar muestra aislada vs excreción temporizada; no duplicar variantes | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [SUP24](https://www.mayocliniclabs.com/test-catalog/Overview/616180) |
| Citrato urinario | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: fijar muestra aislada vs excreción temporizada; no duplicar variantes | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [SUP24](https://www.mayocliniclabs.com/test-catalog/Overview/616180) |
| Oxalato urinario | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: fijar muestra aislada vs excreción temporizada; no duplicar variantes | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [SUP24](https://www.mayocliniclabs.com/test-catalog/Overview/616180) |
| Amonio urinario | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: fijar muestra aislada vs excreción temporizada; no duplicar variantes | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [SUP24](https://www.mayocliniclabs.com/test-catalog/Overview/616180) |
| Sodio urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Potasio urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Cloro urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Calcio urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Fósforo urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Magnesio urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Ácido úrico urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Nitrógeno ureico urinario | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: identidad de muestra; no alias del analito sérico | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Perfil de litiasis / supersaturación | `ORDER_PROFILE_OR_PANEL` | DEFERRED: composición y protocolo aislado/24 h deben fijarse antes de sembrar | [SUPRA](https://www.mayocliniclabs.com/test-catalog/Overview/616375), [SUP24](https://www.mayocliniclabs.com/test-catalog/Overview/616180) |
| Urocultivo | `MICROBIOLOGY_ORDERABLE` | EXISTING: urine_culture; no duplicar por ruta renal | [URNS](https://prd1.mayocliniclabs.com/test-catalog/overview/60515) |
| Cociente proteína/creatinina calculado, componente aislado | `CALCULATED_OR_DERIVED_RESULT` | DEFERRED: componente de resultado; no nueva orden independiente | [RATIO](https://www.mayocliniclabs.com/test-catalog/overview/617783) |
| Recuento celular y diferencial de LCR | `SAFE_CANONICAL_ORDERABLE` | ADDED: csf_cell_count; no equivale a citología oncológica | [CSF](https://mlabs.umich.edu/tests/body-fluid-analysis-csf) |
| Glucosa en LCR | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: precisar espécimen, no alias de glucose | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| Proteínas en LCR | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: no alias de total_protein | [CSFP](https://mlabs.umich.edu/tests/protein-csf) |
| Lactato en LCR | `UNCERTAIN` | DEFERRED: no se verificó identidad exacta incorporable en V1 | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Cultivo / Gram de LCR | `MICROBIOLOGY_ORDERABLE` | DEFERRED: contrato de microbiología y muestra, no duplicar por navegación | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |
| Citología de LCR | `PATHOLOGY_CYTOLOGY_ORDERABLE` | DEFERRED: autoridad de patología distinta a recuento celular | [CYTO](https://mlabs.umich.edu/tests/cerebrospinal-fluid-cytology) |
| Glucosa — Pleural | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| LDH — Pleural | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [LDBF](https://www.mayocliniclabs.com/test-catalog/Overview/800056) |
| Cultivo / Gram — Pleural | `MICROBIOLOGY_ORDERABLE` | DEFERRED: fuente y protocolo de microbiología | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |
| Glucosa — Ascítico / peritoneal | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| LDH — Ascítico / peritoneal | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [LDBF](https://www.mayocliniclabs.com/test-catalog/Overview/800056) |
| Cultivo / Gram — Ascítico / peritoneal | `MICROBIOLOGY_ORDERABLE` | DEFERRED: fuente y protocolo de microbiología | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |
| Glucosa — Pericárdico | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| LDH — Pericárdico | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: orden de química con fuente explícita | [LDBF](https://www.mayocliniclabs.com/test-catalog/Overview/800056) |
| Cultivo / Gram — Pericárdico | `MICROBIOLOGY_ORDERABLE` | DEFERRED: fuente y protocolo de microbiología | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |
| Albúmina / gradiente ascítico | `CALCULATED_OR_DERIVED_RESULT` | DEFERRED: muestra pareada y resultado derivado; no sembrar el gradiente | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Proteínas en pleural / ascítico / pericárdico | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: falta el contrato de origen de muestra | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| pH pleural | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: falta definir recolección y manejo compatible, no genérico pH | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Recuento celular en fluidos cavitarios sin fuente | `UNCERTAIN` | DEFERRED: no identidad única sin fuente ni protocolo | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Cristales en líquido sinovial | `SAFE_CANONICAL_ORDERABLE` | ADDED: synovial_crystals; prueba explícita sinovial | [CRYS](https://mlabs.umich.edu/tests/body-fluid-analysis-crystal-exam) |
| Glucosa en líquido sinovial | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: no duplicar glucose | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| Cultivo / Gram sinovial | `MICROBIOLOGY_ORDERABLE` | DEFERRED: microbiología con fuente sinovial | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |
| Glucosa en líquido amniótico | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: química con fuente amniótica explícita | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| Cariotipo en líquido amniótico | `ADVANCED_SPECIALTY` | DEFERRED: genetics/karyotype con contexto prenatal y espécimen; no duplicar | [AMN](https://www.mayocliniclabs.com/test-catalog/overview/35243) |
| PCR infecciosa en amniótico sin agente definido | `MOLECULAR_ORDERABLE` | DEFERRED: definir agente, indicación y fuente antes de identidad | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| Espermatobioscopía básica | `SAFE_CANONICAL_ORDERABLE` | ADDED: semen_analysis; fertilidad básica, excluye postvasectomía y morfología estricta aislada | [FER](https://www.mayocliniclabs.com/test-catalog/overview/81641) |
| Semen postvasectomía | `ADVANCED_SPECIALTY` | DEFERRED: no alias de espermatobioscopía básica | [FER](https://www.mayocliniclabs.com/test-catalog/overview/81641) |
| Cortisol salival | `REQUIRES_COLLECTION_TIMING_PARAMETER` | DEFERRED: hora/protocolo obligatorio | [CORT](https://prd1.mayocliniclabs.com/test-catalog/overview/84225) |
| Cultivo de esputo | `MICROBIOLOGY_ORDERABLE` | DEFERRED: microbiología, calidad y tipo de muestra | [RESP](https://www.mayocliniclabs.com/api/sitecore/TestCatalog/DownloadTestCatalog?testId=614020) |
| Cultivo de lavado broncoalveolar | `MICROBIOLOGY_ORDERABLE` | DEFERRED: microbiología, BAL no es un nuevo duplicado por ruta | [RESP](https://www.mayocliniclabs.com/api/sitecore/TestCatalog/DownloadTestCatalog?testId=614020) |
| Citología de esputo / lavado broncoalveolar | `PATHOLOGY_CYTOLOGY_ORDERABLE` | DEFERRED: definir fuente y orden de patología; no usar citología cervical | [PREP](https://www.mayocliniclabs.com/specimen/collection-and-preparation) |
| LDH en drenaje / otros fluidos cavitarios | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: fuente obligatoria | [LDBF](https://www.mayocliniclabs.com/test-catalog/Overview/800056) |
| Glucosa en drenaje / otros fluidos cavitarios | `SAME_ANALYTE_REQUIRES_SPECIMEN_PARAMETER` | DEFERRED: fuente obligatoria | [GLBF](https://prd1.mayocliniclabs.com/test-catalog/overview/606609) |
| Cultivo de drenajes sin origen definido | `MICROBIOLOGY_ORDERABLE` | DEFERRED: fuente y protocolo no determinados | [CULT](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid) |

## Navegación y destacados

La configuración compartida `modules/clinical/catalog/study_order_routing_v1.json`, sección `catalog.urine`, declara cinco grupos. Se filtran por catálogo activo y nunca se muestran vacíos:

- **Estudios generales y renales**: `urinalysis`, `microalbumin`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality`
- **Microbiología urinaria**: `urine_culture`
- **Líquido cefalorraquídeo (LCR)**: `csf_cell_count`
- **Líquido sinovial**: `synovial_crystals`
- **Semen**: `semen_analysis`

Los seis destacados, en orden curado, son: `urinalysis`, `microalbumin`, `urine_culture`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality`. La elección prioriza examen general, detección renal, cultivo y perfiles renales de muestra aislada; no pretende representar estadísticas locales de solicitudes. Todos aparecen también en el catálogo completo. Proteinuria de 24 h y depuración no se promocionan porque sus parámetros están diferidos.

## Fuentes consultadas

- EGO: [Mayo Clinic — Urinalysis](https://www.mayoclinic.org/tests-procedures/urinalysis/about/pac-20384907).
- ALBR: [Mayo — Albumin, Random, Urine / ALBR](https://www.mayocliniclabs.com/test-catalog/overview/609731).
- RPTU1: [Mayo — Protein/Creatinine Ratio, Random, Urine](https://www.mayocliniclabs.com/test-catalog/overview/614004).
- OSMU: [Mayo — Osmolality, Random, Urine](https://www.mayocliniclabs.com/test-catalog/Overview/41236).
- CRCL: [Mayo — Creatinine Clearance](https://www.mayocliniclabs.com/test-catalog/overview/615813).
- SUP24: [Mayo — Supersaturation Profile, 24 Hour, Urine](https://www.mayocliniclabs.com/test-catalog/Overview/616180).
- SUPRA: [Mayo — Supersaturation Profile, Random, Urine](https://www.mayocliniclabs.com/test-catalog/Overview/616375).
- URNS: [Mayo — Aerobic Urine Culture](https://prd1.mayocliniclabs.com/test-catalog/overview/60515).
- CSF: [Michigan Medicine — CSF Count and Differential](https://mlabs.umich.edu/tests/body-fluid-analysis-csf).
- CRYS: [Michigan Medicine — Synovial Crystal Exam](https://mlabs.umich.edu/tests/body-fluid-analysis-crystal-exam).
- FER: [Mayo — Semen Analysis](https://www.mayocliniclabs.com/test-catalog/overview/81641).
- GLBF: [Mayo — Glucose, Body Fluid](https://prd1.mayocliniclabs.com/test-catalog/overview/606609).
- LDBF: [Mayo — Lactate Dehydrogenase, Body Fluid](https://www.mayocliniclabs.com/test-catalog/Overview/800056).
- CULT: [Michigan Medicine — Aerobic Culture, Sterile Body Fluid](https://mlabs.umich.edu/tests/aerobic-culture-sterile-body-fluid).
- CSFP: [Michigan Medicine — Protein, CSF](https://mlabs.umich.edu/tests/protein-csf).
- CYTO: [Michigan Medicine — Cerebrospinal Fluid Cytology](https://mlabs.umich.edu/tests/cerebrospinal-fluid-cytology).
- CORT: [Mayo — Cortisol, Saliva](https://prd1.mayocliniclabs.com/test-catalog/overview/84225).
- AMN: [Mayo — Chromosome Analysis, Amniotic Fluid](https://www.mayocliniclabs.com/test-catalog/overview/35243).
- RESP: [Mayo — Respiratory Culture test definition](https://www.mayocliniclabs.com/api/sitecore/TestCatalog/DownloadTestCatalog?testId=614020).
- PREP: [Mayo — Collection and Preparation](https://www.mayocliniclabs.com/specimen/collection-and-preparation).
- RATIO: [Mayo — Protein/Creatinine calculation, non-orderable](https://www.mayocliniclabs.com/test-catalog/overview/617783).

## QA

`ord_comp01_disposable_gate.sh`: 208 claves activas únicas, seis destacados activos dentro de la ruta, cero claves sin grupo, escrituras y PDFs válidos. `ord_comp01_browser.py`: cinco grupos no vacíos, catálogo cerrado al entrar, un grupo abierto por vez, estados seleccionados y búsqueda acotada.
