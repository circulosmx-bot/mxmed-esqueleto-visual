# STUDY-NAV-FEATURED01 — estudios comunes y catálogo desplegable

**Estado de implementación: PARTIALLY_IMPLEMENTED en STUDY-NAV-FEATURED02.** Se implementaron las 49 hojas y las 152 relaciones de grupos. Los candidatos `LOW` de radiografía, neurofisiología y endoscopia digestiva no se promueven a “COMUNES”; `panels` permanece sin destacados por investigación insuficiente. Véase [FEATURED02](STUDY_NAV_FEATURED02_IMPLEMENTED.md). La matriz original sigue siendo evidencia de auditoría, no configuración operativa.

**AUDIT_ONLY · PROPOSED · DIRECTOR_REVIEW_REQUIRED**  
Fuente: HEAD `55dc59f50d49c823d6d9a6982ac7355f41c53be6`, 2026-10-04. Ninguna configuración ni conducta de runtime cambió. La imagen integrada del Director se usó solo para la intención visual de pantalla de familia y pantalla final; las identidades, rótulos y rutas provienen del producto real.

## Autoridad y cobertura

La BD de revisión `mxmed_director_review_lon07c.clinical_study_types` tiene **232** identidades activas. La matriz deriva hojas y `parts` de `study-navigation-hierarchy-v2.js`, `lab-cat02a-navigation-v1.js` y los cuatro agrupadores dentales de `or05-specialty-navigation-v1.js`, e intersecta categoría y claves con esos 232 registros. Son **5 familias médicas**, **45 hojas médicas** y **4 hojas dentales**. Las 232 claves están presentes en al menos una hoja; hay rutas secundarias deliberadas, sin identidad nueva. El escape de búsqueda global sigue cubriendo todo el catálogo. `study_order_routing_v1.json`, alias y `study_specimen_requirements_v1.json` permanecen como autoridades independientes y sin cambios.

Artefactos: [matriz de 49 hojas](STUDY_NAV_FEATURED01_LEAF_MATRIX.csv) y [152 relaciones explícitas de 38 grupos](STUDY_NAV_FEATURED01_GROUP_MEMBERSHIP_MATRIX.csv). Ambas matrices son propuestas de revisión; **no son configuración de runtime**. Las filas de membresía de la hoja de orina reproducen sus siete grupos aceptados, incluida la reutilización intencional de algunos análisis de líquido corporal en el grupo sinovial. Solo un acordeón se abre a la vez; no se propone una fila canónica repetida simultáneamente en una lista visible.

## Contrato visual propuesto

**Pantalla de familia:** acción para volver, icono existente, título grande de la familia y tarjetas inmediatamente debajo. Eliminar el `hierBreadcrumb` visible que actualmente antepone “Tipos de estudio / …” al título. Afecta las cinco pantallas médicas raíz: Laboratorio, Imagenología, Patología y biopsias, Estudios funcionales, Procedimientos diagnósticos; también las pantallas intermedias Inmunología y serología, Genética y diagnóstico molecular, y Ultrasonido. La navegación dental conserva sus cuatro accesos propios; hoy usa “Generar nueva orden” como título de la pantalla dental y no muestra ese breadcrumb, por lo que requeriría una decisión de título separada.

**Selector final:** acción para volver y un solo encabezado `{FAMILIA} / {HOJA}`. Si la ruta tiene tres niveles, mostrar raíz + hoja; incluir el nivel intermedio solo cuando dos hojas finales quedarían indistinguibles. El criterio se determina con IDs HIER03, no con truncamiento por píxeles. Siguen búsqueda local, vínculo de búsqueda global y panel de órdenes preparadas a la derecha. Quitar el título genérico “Seleccionar estudios”, el rótulo interno “CATÁLOGO DE ESTUDIOS” y el nombre de hoja repetido. El conteo “N estudios disponibles” puede mantenerse como estado accesible y como respuesta a una búsqueda, sin ocupar una segunda línea de orientación en reposo. “Estudios principales” de la referencia no existe en el selector actual y no se introduciría.

El rótulo actual **“MÁS SOLICITADOS” requiere cambio propuesto a “COMUNES”**: no existe telemetría de solicitudes de MXMED que demuestre frecuencia de ordenamiento. Los candidatos se basan en uso clínico de referencia, guías u oferta comprobable; ninguno se presenta como ranking estadístico. Una sola lista estable por hoja es preferible a 49 variantes por especialidad. HIER03 podrá seguir ordenando tarjetas de familias según perfil sin alterar estas listas. El panel derecho conserva exactamente el modelo visual ORD-COMP02-R3.

## Propuesta de destacados por hoja grande

Las claves, orden y evidencia de cada candidato figuran individualmente en las columnas `proposed_featured_1..6`, `proposed_featured_*_evidence` y `proposed_featured_*_confidence` de la matriz. Esta tabla resume las **36 plazas propuestas** en diez hojas. **MEDIUM/LOW son propuestas para revisión clínica, no aprobación automática.**

| Hoja | Propuestos (claves canónicas, en orden) | Lectura de evidencia y límite |
| --- | --- | --- |
| Química clínica | `glucose`, `creatinine`, `urea`, `chol_total`, `hdl`, `triglycerides` | Panel metabólico y pruebas lipídicas de referencia; oferta mexicana de química sanguínea. MEDIUM: no hay frecuencia local. |
| Hematología | `cbc`, `ferritin`, `iron` | CBC habitual; pruebas de hierro para anemia. MEDIUM. |
| Endocrinología | `tsh`, `ft4`, `hba1c` | Flujos tiroideo y glucémico; MEDIUM. No son seis por obligación. |
| Microbiología | `urine_culture`, `blood_culture`, `throat_swab`, `stool_culture` | Cultivos por muestras clínicamente reconocidas; MEDIUM. Comparar con oferta mexicana antes de ordenar. |
| Orina y otros fluidos | `urinalysis`, `microalbumin`, `urine_culture`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality` | **Conservar las seis aprobadas**; guía/servicio renal mexicano respalda orina, albúmina y creatinina, pero no prueba orden relativo. MEDIUM. |
| Radiografía y fluoroscopía | `rx_chest`, `rx_abdomen`, `rx_knee` | Modalidades clínicas generales; LOW: ACR/RadiologyInfo informa pertinencia por indicación, no frecuencia. Revisión radiológica requerida. |
| Cardiovascular funcional | `ecg_12lead`, `holter`, `abpm_mapa`, `stress_test` | ECG y monitoreo de arritmia de referencia; MEDIUM. Ecocardiograma y Doppler permanecen en Imagenología. |
| Neurofisiología | `eeg_routine`, `emg_ncs` | EEG/EMG son vías habituales de neurofisiología; LOW para el orden relativo. |
| Función pulmonar | `spirometry`, `full_pft`, `dlco` | ATS identifica espirometría, difusión y pletismografía como pruebas principales; MEDIUM para el orden de esta selección. |
| Endoscopia digestiva | `egd_eda_base`, `colonoscopy_base` | ASGE describe indicaciones comunes; LOW para un orden de frecuencia. ERCP se mantiene visible en el catálogo completo, sin promoción rutinaria automática. |

**Investigación insuficiente:** `panels` (7 claves): mezcla CBC/EGO con paneles genéticos y moleculares. La fuente de la taxonomía y el contrato de orden no justifican un ranking universal. Proponer cero destacados por ahora y revisar si debe permanecer como una sola hoja. También requieren aprobación explícita los candidatos LOW de radiografía, neurofisiología y endoscopia, aun cuando la matriz los documenta para discusión.

## Regla de catálogo y grupos

**38 hojas con ≤6 estudios activos:** mostrar sus estudios directamente, sin un segundo acordeón que repita exactamente la misma lista. Esto incluye las cuatro hojas dentales y las hojas pequeñas de genética, imagen, patología y procedimientos. La matriz registra `small_catalog_rule_applies=yes`; `proposed_featured_count=0` significa que no habrá una sección separada de destacados, no que se oculten estudios.

**11 hojas con >6 estudios:** catálogo completo desplegable con los 38 grupos y 152 relaciones de la segunda matriz. Los grupos propuestos separan química por metabolismo/electrolitos/lípidos/hepático/enzimas/micronutrientes; hematología por recuentos/hierro/Coombs; endocrinología por tiroides/glucosa/reproducción; microbiología por cultivos/infección de LCR; radiografía por placa/fluoroscopía; cardiovascular por ECG/ambulatorio/esfuerzo/vascular; neurofisiología por EEG/neuromuscular/potenciales; pulmón por función/ejercicio/monitoreo; endoscopia por alta-biliopancreática/intestino delgado/baja. “Perfiles y paneles” tiene dos grupos provisionales que **no resuelven** su deuda de taxonomía. Orina conserva siete grupos existentes. Ningún grupo está vacío, todas sus claves son activas y no se usa inferencia por texto. Los destacados pueden reaparecer en su grupo cuando el catálogo se expande, como en el patrón actual, con `aria-pressed`/deduplicación de selección por identidad.

**Conflicto de reglas que necesita decisión antes de FEATURED02:** el fallback “Agregar estudio no catalogado” debe estar al final del catálogo completo expandido, mientras la regla de hojas pequeñas elimina ese catálogo. Recomendación: en hojas pequeñas, mostrar el vínculo después de la lista directa dentro de un pequeño disclosure propio; exigir intención explícita y conservar el mismo formulario. Si la regla “solo dentro del catálogo completo” es absoluta, habría que mantener un disclosure no redundante para el fallback. No implementar ninguna variante sin aprobación.

## Riesgos de clasificación

- `panels` mezcla paneles genéticos con CBC y EGO; requiere revisión antes de elegir destacados.
- `tumor` contiene solo `bhcg`, también presente en Endocrinología. No inventar marcadores tumorales faltantes.
- Las cuatro rutas dentales se superponen deliberadamente y algunas identidades siguen siendo categoría `IMAGEN`; no convertirlas en claves nuevas ni en familia médica general.
- `urine` y `microbiology` comparten `urine_culture`; `stool` comparte `stool_culture`. La deduplicación es por `study_type_id` al seleccionar.
- La matriz propuesta de orina conserva grupos que comparten análisis de líquido corporal entre grupos. El acordeón exclusivo evita duplicados simultáneos; una eventual vista con varios grupos abiertos requeriría reevaluar ese invariante.
- `non_hdl`, `transferrin_sat`, `ercp_cpre_base`, `dental_study_model` tienen deuda previa de identidad y `capnography` de clasificación; FEATURED01 no modifica ninguna identidad.

## Evidencia externa y alcance de inferencia

Fuentes primarias consultadas: [MedlinePlus, análisis sanguíneos](https://medlineplus.gov/lab-tests/what-you-need-to-know-about-blood-testing/), [hemograma](https://medlineplus.gov/bloodcounttests.html), [hierro](https://medlineplus.gov/lab-tests/iron-tests/), [lípidos](https://medlineplus.gov/lab-tests/cholesterol-levels/), [tiroides](https://medlineplus.gov/thyroidtests.html), [pruebas de cultivo](https://medlineplus.gov/lab-tests/bacteria-culture-test/) y [HbA1c](https://medlineplus.gov/lab-tests/hemoglobin-a1c-hba1c-test/); [IMSS, guía de enfermedad renal](https://www.imss.gob.mx/sites/all/statics/guiasclinicas/335GER.pdf) y [servicios renales](https://www.imss.gob.mx/node/111671); [Salud Digna, oferta empresarial](https://empresas.salud-digna.org/afiliados); [RadiologyInfo/ACR](https://www.radiologyinfo.org/en/home); [AHA, pruebas de arritmia](https://www.heart.org/en/health-topics/arrhythmia/symptoms-diagnosis--monitoring-of-arrhythmia/common-tests-for-arrhythmia); [ATS, función pulmonar](https://site.thoracic.org/advocacy-patients/patient-resources/pulmonary-function-tests); [ASGE, uso de endoscopia](https://www.asge.org/home/resources/publications/guidelines/appropriate-use-of-gi-endoscopy); [AASM, diagnóstico del sueño](https://aasm.org/resources/clinicalguidelines/diagnostic-testing-osa.pdf); [ACOG, citología cervical](https://www.acog.org/womens-health/faqs/cervical-cancer-screening); [ADA, radiografías dentales](https://www.ada.org/resources/ada-library/oral-health-topics/x-rays-radiographs). Estas fuentes describen indicaciones, pruebas frecuentes u oferta; **no miden solicitudes de MXMED**. Las fuentes mexicanas se usaron para química clínica y orina/renal; no se extrapolan como cobertura mexicana comprobada para todas las especialidades.

## Siguiente fase propuesta: STUDY-NAV-FEATURED02

1. Resolver con Dirección el fallback en hojas pequeñas, la hoja mixta `panels`, el rótulo “COMUNES” y las propuestas LOW.
2. A: simplificar encabezados de familia y hoja, sin tocar navegación ni shell.
3. B: introducir configuración versionada de destacados aprobados, sin ranking estadístico implícito.
4. C: introducir grupos aprobados con claves explícitas; conservar grupos de orina.
5. D: validar las 232 identidades, cero grupo vacío y cero fila duplicada simultánea.
6. E: conservar orden de familias por especialidad HIER03; destacados estables por hoja salvo evidencia posterior.
7. F: QA de búsqueda, accesibilidad, dental, escritorio 1440×900 y 1366×768 con sidebar compacta/expandida, móvil 390×844, órdenes preparadas y modales.

**FEATURED01 termina en auditoría.** No cambia catálogo, frontend, backend, esquema, lector, escritor ni routing.
