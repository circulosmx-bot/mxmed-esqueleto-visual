# LAB-CAT01 — Laboratorio: catálogo V1 propuesto

**Estado: PROPOSED · AUDIT ONLY · NOT IMPLEMENTED.** Fecha: 2026-10-02. Rama: `ux/consultation-step2-vitals-r1`. Baseline: `09d514d2727b5b5ab968041c87784d6a69a3630f`.

## Alcance y autoridad

Se inspeccionaron visualmente ambas caras de la solicitud de **Laboratorio clínico del campestre** suministrada por Dirección (archivos WhatsApp de 15:50:55 y 15:51:05). Se giraron copias temporales para leerlas; los archivos originales no se alteraron. La primera hoja contiene Hematología, Coagulación, Química clínica, Determinaciones urinarias, Endocrinología, Marcadores tumorales, materia fecal, Otras pruebas y Perfiles. La segunda continúa Perfiles y añade Inmunología, Serología de hepatitis, Fármacos, Autoinmunidad, Biología molecular, Microbiología y campos de servicio. Los componentes de panel impresos en tipografía diminuta se registran como incertidumbre cuando no se pueden leer con confianza; no se infieren blancos ocultos. Los nombres escritos aquí son transcripción de conceptos de una **hoja comercial particular**, no validación clínica ni instrucción de uso.

La autoridad actual de identidad es la tabla `clinical_study_types`, protegida por `modules/clinical/db/migrations/2026_09_30_14_study_type_authority.sql`; las filas provienen de `2026_09_30_15_initial_curated_study_catalog.sql` y el añadido dental `2026_10_02_17_dental_cat02_catalog.sql`. Se cotejó la base local de revisión **solo con SELECT**: 81 activos LABORATORIO, 12 GENETICA y 2 PATOLOGIA relevantes, total **95**; 190 activos de todas las categorías. `modules/clinical/catalog/tax03b_source_curation.json` conserva procedencia de la semilla. `api/_lib/clinical_study_catalog_read.php` es el lector activo de categoría y alias; TAX03C consume esa proyección. `assets/js/clinical/or05-specialty-navigation-v1.js` contiene la navegación vigente; `assets/js/review/classification-simulator.js` es la herramienta de simulación local. No se encontró autoridad estructurada activa para specimen/sample o tiempo de recolección en las migraciones/lector clínicos auditados. La simulación no es autoridad de identidad.

**Regla:** identidad canónica del estudio ≠ grupo de navegación ≠ componente de resultado ≠ preset de orden. Los códigos propuestos son candidatos de revisión, no IDs nuevos. Una coincidencia de nombre no prueba equivalencia de método, espécimen, analito o población; las filas NEAR_EQUIVALENT deben curarse antes de cualquier migración. `NOT_PRESENT_IN_SOURCE_FORM` significa ausencia en estas hojas y nunca recomienda eliminar un estudio.

### Conteo reproducible de esta propuesta

| Métrica | Valor |
| --- | ---: |
| Conceptos fuente, incluidos campos administrativos y textos inciertos | 318 |
| Clasificación A/B/C/D/E/F/G/H/I/J | 162 / 9 / 20 / 12 / 24 / 19 / 7 / 16 / 37 / 12 |
| Exact/alias/cercano/faltante/no canónico | 44 / 36 / 6 / 123 / 109 |
| Activos relevantes usados/vistos en hoja | 78 |
| Activos relevantes ausentes de hoja | 17 |
| Candidatos nuevos prueba/panel único | 117 / 6 |
| Presets / paneles multiparámetro evidenciados / perfiles-páneles sin composición curada | 19 / 9 / 11 |
| Relaciones estudio activo → grupo propuesto | 106 |

El conteo de **faltantes** es de renglones fuente, mientras el de **candidatos nuevos** deduplica por clave propuesta. En una hoja con perfiles repetidos, dos renglones pueden referir la misma identidad. Una coincidencia cercana no se convierte automáticamente en una alta; el ejemplo de 25-OH vitamina D queda para decisión explícita.

## Referencias externas consultadas como guía

- [LOINC: clases de laboratorio](https://loinc.org/kb/users-guide/classes/laboratory-classes) para comprobar que química, microbiología, hematología, etc. pueden organizar observaciones; no se importó su taxonomía como IDs MXMED.
- [LOINC: conceptos agrupadores de órdenes](https://loinc.org/kb/users-guide/orderable-grouper-concepts) y [LOINC Groups](https://loinc.org/kb/users-guide/additional-content-in-the-loinc-distribution/loinc-groups) para distinguir nivel agrupador de código reportable y advertir que esos grupos requieren validación local antes de uso clínico.
- [Mayo Clinic Laboratories: Liver Profile](https://www.mayocliniclabs.com/test-catalog/overview/113633) como ejemplo de orden de perfil con resultados individuales; los componentes **de la hoja** de esta auditoría no deben sustituirse por la configuración de Mayo.
- [Mayo Clinic Laboratories: Respiratory Panel PCR](https://www.mayocliniclabs.com/test-catalog/Overview/609409) como ejemplo de un solo ensayo multiplex con múltiples blancos, muestra y requisitos de toma diferenciados.
- [ARUP: gastrointestinal parasite panels](https://ltd.aruplab.com/api/ltd/pdf/313) como directorio independiente que muestra ensayos agrupados por método y patógeno; tampoco determina la composición local de la hoja.

Estas referencias validan el **método de modelado**, no autorizan códigos clínicos, equivalencias automáticas, recomendaciones diagnósticas ni importación de paquetes de proveedor.

## LAB_NAVIGATION_GROUPERS_V1

**Primarios (8):** Hematología, Química clínica, Coagulación, Endocrinología y hormonas, Inmunología, Microbiología, Biología molecular / PCR, Orina y otros fluidos. **Secundarios (7):** Materia fecal, Autoinmunidad, Marcadores tumorales, Serologías / Hepatitis, Monitoreo de fármacos, Trasplante, Otros. **Especial (1):** Perfiles y paneles. Se acepta la hipótesis de Dirección con dos precisiones: *Electrolitos y minerales* puede ser subgrupo de Química clínica, y *Genética/molecular no infecciosa* debe ser una ruta visible bajo Biología molecular / PCR (o secundaria dentro de ella), sin duplicar identidades GENETICA. Materia fecal atraviesa química, microbiología y molecular: es acceso por muestra, no categoría canónica. Inmunología, Autoinmunidad y Serologías pueden compartir el mismo estudio mediante relaciones múltiples. `Otros` es acceso de respaldo; se deben colocar las pruebas identificables en su grupo concreto.

La pantalla de 1366×768 debería mostrar ocho grupos en una o dos filas, siete secundarios compactos y acceso a Perfiles y paneles; la lista individual aparece después de escoger grupo/buscar. No se propone una lista gigante al abrir Laboratorio ni se cambia OR05 ahora. Una prueba puede tener varios grupos mediante tabla de relaciones, sin crear nuevos `clinical_study_types`. La tabla de catálogo más abajo documenta **106 relaciones** de los 95 activos relevantes; las altas candidatas se relacionarían durante su propia revisión.

### Prioridad por especialidad, sin exclusión

| Perfil de navegación | Grupos iniciales sugeridos |
| --- | --- |
| Médico General | Química clínica; Hematología; Orina y otros fluidos; Perfiles y paneles |
| Medicina Interna | Química clínica; Hematología; Inmunología; Microbiología |
| Endocrinología | Endocrinología y hormonas; Química clínica; Perfiles y paneles; Orina y otros fluidos |
| Hematología | Hematología; Coagulación; Biología molecular / PCR; Inmunología |
| Infectología | Microbiología; Biología molecular / PCR; Serologías / Hepatitis; Inmunología |
| Nefrología | Química clínica; Orina y otros fluidos; Hematología; Perfiles y paneles |
| Oncología | Hematología; Química clínica; Marcadores tumorales; Biología molecular / PCR |
| Cardiología | Química clínica; Hematología; Coagulación; Marcadores tumorales |
| Gastroenterología | Química clínica; Materia fecal; Serologías / Hepatitis; Microbiología |
| Reumatología | Autoinmunidad; Inmunología; Hematología; Química clínica |
| Ginecología/Obstetricia | Endocrinología y hormonas; Microbiología; Serologías / Hepatitis; Química clínica |
| Pediatría | Hematología; Química clínica; Microbiología; Orina y otros fluidos |
| Neumología | Microbiología; Biología molecular / PCR; Hematología; Química clínica |

Estos órdenes son **preferencia de descubrimiento**, no indicación clínica. Puede priorizar, puede ocultar grupos de baja prioridad de la primera pantalla, **no** puede ocultarlos del catálogo completo ni prohibir ordenar. El perfil dental exclusivo previo no debe extrapolarse a Laboratorio.

## Recomendaciones de modelado

**Cultivos: modelo híbrido.** Conservar `urine_culture`, `stool_culture` y `blood_culture` porque tienen lenguaje de orden y flujo microbiológico diferenciados. Proponer una familia genérica para cultivo de otros materiales con `specimen_type`, `body_site`, `collection_method` y `provider_method` cuando el servicio sea portable. `Espermo cultivo`, herida y fluidos merecen decisión explícita según resultado/flujo; no se multiplican claves por cada sitio sin necesidad. La solicitud de organismo y antibiograma son resultados/servicios posteriores, no automáticamente una clave por microorganismo.

**Exudados: estudio + sitio/muestra.** Mantener `throat_swab` y `vaginal_swab` existentes. Para nasal, vulvar y uretral proponer estudio(s) según **método** (cultivo, microscopía, PCR) y sitio en parámetros; la palabra de sitio sola no define el ensayo. No romper compatibilidad de las dos claves actuales. Las filas correspondientes aparecen D y no se cuentan como faltantes canónicos.

**Molecular: separar blanco simple, carga viral, genotipo y multiplex.** Un panel respiratorio o gastrointestinal ejecutado como una orden/ensayo conserva una clave canónica y reporta componentes; no se descompone en preset. Los paneles comerciales Athena/INMUNOBLOT, VPH28 y ETS necesitan catálogo técnico del proveedor para definir blancos/método. `Mycobacterium tuberculosis en ...` no precisa método en la hoja, por lo que se bloquea cualquier equivalencia a PCR/cultivo.

**Preset/perfil:** contrato futuro `preset_key`, `display_name`, `version`, componentes ordenados de `study_type_key`/parámetros y procedencia; al seleccionar, cada estudio recibe su propio `order_item_id`. Los componentes impresos por este laboratorio son una **configuración de proveedor**, no receta universal. QS3/4/6, Electrolitos 3/6 y los perfiles impresos son buena evidencia para un primer borrador, pero su composición se valida antes de producción. `Litiasis` es incierto: incluye muestra suero/orina y análisis de lito, no un preset fijo probado.

**Panel multiplex:** contrato futuro de `study_type_key`, método/alcance de orden y `result_component_keys` versionados; varios resultados bajo un solo item de orden. CBC, EGO y los paneles PCR/antígeno definidos se modelan así donde el servicio sea realmente uno. Resultados como fórmula roja/blanca, relación albúmina/globulina, HOMA-IR y blancos de un panel no se convierten automáticamente en estudios solicitables independientes. Si un laboratorio vende alguno por separado, necesita prueba de ordenabilidad individual.

**Muestra/colección:** proponer contrato acotado `specimen_kind`, `anatomic_site`, `collection_method`, `collection_duration_hours`, `timepoint`, `dose_grams`, `fasting_state`, `sample_count` y `provider_requirements_version`, opcionales y validados por estudio. Orina única/24 h, curva de 2/3/5 h, 50/75/100 g y número de muestras son contexto de orden/recolección; el analito conserva identidad. No aplicar el mismo parámetro a pruebas que no lo admiten. Proveedor define recipiente, transporte, estabilidad y rechazo en su configuración de servicio, nunca en una lista clínica universal derivada de esta hoja.

## MINIMUM_SAFE_LAB_V1 y futuro

**Mínimo seguro recomendado:** primero grupos de navegación y alias validados; después una tanda pequeña de faltantes comunes con identidad clara (Coombs directo/indirecto, grupo/Rh, frotis, reticulocitos, TP, tiempo de trombina, CPK, CK-MB, amonio, relación ACR, proteína en orina, pruebas fecales comunes, troponina/BNP y ensayos hormonales/serológicos frecuentes). Resolver 25-OH vitamina D contra `vitamin_d` antes de alta. Para cultivos y paneles multiplex, aceptar únicamente composiciones/muestras verificadas. Incorporar contrato de preset y parámetros de colección **solo** cuando los escritores/lectores preserven IDs y trazabilidad; LAB-CAT01 no lo implementa. Este recorte mejora búsqueda sin abrir un subsistema de laboratorio completo.

**Modelo futuro:** autoridad de muestra y requisitos por proveedor; tiempo/ayuno/toma seriada; presets versionados; paneles con componentes versionados; resultados de analitos estructurados, unidades, rangos por método/población, valores críticos, validación de resultado y trazabilidad del laboratorio emisor. Mantener separado B3 y la autoridad de resultados existente; no se cambia ningún contrato aquí.

## Tabla 1 — todos los conceptos de las dos hojas

A=prueba, B=panel multiplex, C=preset, D=muestra/sitio, E=toma/tiempo, F=componente de resultado, G=configuración de proveedor, H=administración, I=alias de estudio existente, J=fuente incierta. `SOURCE_UNCERTAIN` incluye lectura o composición no confiable. `EXISTING_ALIAS` designa una correspondencia nominal; no reescribe aliases_json. Para los perfiles se repite cada componente impreso como renglón con la sección de origen, conservando la evidencia de la hoja.

| SOURCE_SECTION | SOURCE_TERM | NORMALIZED_NAME | CLASSIFICATION | EXISTING_CANONICAL_KEY | EXISTING_MATCH_STATUS | PROPOSED_CANONICAL_KEY | NAVIGATION_GROUPER | SPECIMEN_OR_PARAMETER | PANEL_OR_PRESET_MODEL | ALIASES | CATALOG_GAP_SEVERITY | NOTES |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Hematología | Citometría hemática | Biometría hemática (BH / CBC) | CANONICAL_MULTIPLEX_PANEL | cbc | EXISTING_ALIAS | — | Hematología | — | multiplex sujeto a definición | BH / CBC; Biometría hemática; CBC | — | — |
| Hematología | Fórmula roja | Fórmula roja | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Hematología | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Hematología | Fórmula blanca | Fórmula blanca | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Hematología | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Hematología | Plaquetas | Plaquetas | CANONICAL_LAB_TEST | platelets | EXACT_EXISTING_MATCH | — | Hematología | — | — | — | — | — |
| Hematología | Coombs directo | Coombs directo | CANONICAL_LAB_TEST | — | MISSING | lab_coombs_directo | Hematología | — | — | — | CRITICAL_COMMON | — |
| Hematología | Coombs indirecto | Coombs indirecto | CANONICAL_LAB_TEST | — | MISSING | lab_coombs_indirecto | Hematología | — | — | — | CRITICAL_COMMON | — |
| Hematología | Grupo sanguíneo y Rh | Grupo sanguíneo y Rh | CANONICAL_LAB_TEST | — | MISSING | lab_grupo_sanguineo_y_rh | Hematología | — | — | — | CRITICAL_COMMON | — |
| Hematología | Frotis sanguíneo | Frotis sanguíneo | CANONICAL_LAB_TEST | — | MISSING | lab_frotis_sanguineo | Hematología | — | — | — | CRITICAL_COMMON | — |
| Hematología | Reticulocitos | Reticulocitos | CANONICAL_LAB_TEST | — | MISSING | lab_reticulocitos | Hematología | — | — | — | CRITICAL_COMMON | — |
| Hematología | Velocidad de sed. globular | VSG | ALIAS_OF_EXISTING_TEST | esr | EXISTING_ALIAS | — | Hematología | — | — | — | — | — |
| Coagulación | Anticoagulante lúpico | Anticoagulante lúpico | CANONICAL_LAB_TEST | — | MISSING | lab_anticoagulante_lupico | Coagulación | — | — | — | CRITICAL_COMMON | — |
| Coagulación | Fibrinógeno | Fibrinógeno | CANONICAL_LAB_TEST | fibrinogen | EXACT_EXISTING_MATCH | — | Coagulación | — | — | — | — | — |
| Coagulación | Dímero D | Dímero D | CANONICAL_LAB_TEST | d_dimer | EXACT_EXISTING_MATCH | — | Coagulación | — | — | — | — | — |
| Coagulación | Tiempo de sangrado | Tiempo de sangrado | CANONICAL_LAB_TEST | — | MISSING | lab_tiempo_de_sangrado | Coagulación | — | — | — | CRITICAL_COMMON | — |
| Coagulación | Tiempo de protrombina | Tiempo de protrombina | CANONICAL_LAB_TEST | — | MISSING | lab_tiempo_de_protrombina | Coagulación | — | — | — | CRITICAL_COMMON | — |
| Coagulación | Tiempo de tromboplastina parcial activado | TTPa (aPTT) | ALIAS_OF_EXISTING_TEST | aptt | EXISTING_ALIAS | — | Coagulación | — | — | — | — | — |
| Coagulación | Tiempo de trombina | Tiempo de trombina | CANONICAL_LAB_TEST | — | MISSING | lab_tiempo_de_trombina | Coagulación | — | — | — | CRITICAL_COMMON | — |
| Química clínica | QS3 | Química sanguínea de 3 elementos | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | Glucosa | Glucosa | CANONICAL_LAB_TEST | glucose | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Urea | Urea | CANONICAL_LAB_TEST | urea | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Creatinina | Creatinina | CANONICAL_LAB_TEST | creatinine | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | QS4 | Química sanguínea de 4 elementos | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | Ácido úrico | Ácido úrico | CANONICAL_LAB_TEST | uric_acid | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | QS6 | Química sanguínea de 6 elementos | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | Colesterol | Colesterol total | ALIAS_OF_EXISTING_TEST | chol_total | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Química clínica | Triglicéridos | Triglicéridos | CANONICAL_LAB_TEST | triglycerides | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Amilasa | Amilasa | CANONICAL_LAB_TEST | amylase | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Lipasa | Lipasa | CANONICAL_LAB_TEST | lipase | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Amonio | Amonio | CANONICAL_LAB_TEST | — | MISSING | lab_amonio | Química clínica | — | — | — | CRITICAL_COMMON | — |
| Química clínica | Litio | Litio | CANONICAL_LAB_TEST | — | MISSING | lab_litio | Química clínica | — | — | — | CRITICAL_COMMON | — |
| Química clínica | Colinesterasa | Colinesterasa | CANONICAL_LAB_TEST | — | MISSING | lab_colinesterasa | Química clínica | — | — | — | CRITICAL_COMMON | — |
| Química clínica | CPK | Creatina cinasa total | CANONICAL_LAB_TEST | — | MISSING | lab_cpk | Química clínica | — | — | — | CRITICAL_COMMON | — |
| Química clínica | CK-MB | Creatina cinasa MB | CANONICAL_LAB_TEST | — | MISSING | lab_ck_mb | Química clínica | — | — | — | CRITICAL_COMMON | — |
| Química clínica | Electrolitos 3 | Electrolitos 3 | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | Sodio | Sodio | CANONICAL_LAB_TEST | sodium | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Potasio | Potasio | CANONICAL_LAB_TEST | potassium | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Cloro | Cloro | CANONICAL_LAB_TEST | chloride | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Electrolitos 6 | Electrolitos 6 | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | ES3 | Electrolitos de 3 elementos | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | preset | — | — | Composición local impresa; no crear estudio padre genérico. |
| Química clínica | Calcio | Calcio | CANONICAL_LAB_TEST | calcium | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Fósforo | Fósforo | CANONICAL_LAB_TEST | phosphorus | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Química clínica | Magnesio | Magnesio | CANONICAL_LAB_TEST | magnesium | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Determinaciones urinarias | Examen general de orina | EGO (examen general de orina) | CANONICAL_MULTIPLEX_PANEL | urinalysis | EXISTING_ALIAS | — | Orina y otros fluidos | — | multiplex sujeto a definición | EGO; examen general de orina | — | — |
| Determinaciones urinarias | Proporción albúmina/creatinina (micción única) | Proporción albúmina/creatinina (micción única) | CANONICAL_LAB_TEST | — | MISSING | lab_proporcion_albumina_creatinina_miccion_unica | Orina y otros fluidos | — | — | — | CRITICAL_COMMON | Relación ACR es resultado propio; microalbuminuria sola no equivale. |
| Determinaciones urinarias | Cortisol en orina de 24 h | Cortisol en orina de 24 h | CANONICAL_LAB_TEST | — | MISSING | lab_cortisol_en_orina_de_24_h | Orina y otros fluidos | 24 h / orina | — | — | CRITICAL_COMMON | — |
| Determinaciones urinarias | Calcio en orina de 24 h | Calcio en orina de 24 h | CANONICAL_LAB_TEST | — | MISSING | lab_calcio_en_orina_de_24_h | Orina y otros fluidos | 24 h / orina | — | — | CRITICAL_COMMON | — |
| Determinaciones urinarias | Depuración de creatinina en orina de 24 h | Depuración de creatinina en orina de 24 h | CANONICAL_LAB_TEST | — | MISSING | lab_depuracion_de_creatinina_en_orina_de_24_h | Orina y otros fluidos | 24 h / orina | — | — | CRITICAL_COMMON | — |
| Determinaciones urinarias | Peso | Peso | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | — | — | — | — | — |
| Determinaciones urinarias | Altura | Altura | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | — | — | — | — | — |
| Determinaciones urinarias | Microalbúmina | Microalbuminuria | ALIAS_OF_EXISTING_TEST | microalbumin | EXISTING_ALIAS | — | Orina y otros fluidos | — | — | — | — | — |
| Determinaciones urinarias | Una micción (microalbúmina) | Una micción (microalbúmina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones urinarias | 24 h (microalbúmina) | 24 h (microalbúmina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | 24 h / orina | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones urinarias | Proteínas en orina | Proteínas en orina | CANONICAL_LAB_TEST | — | MISSING | lab_proteinas_en_orina | Orina y otros fluidos | — | — | — | CRITICAL_COMMON | — |
| Determinaciones urinarias | Una micción (proteínas en orina) | Una micción (proteínas en orina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones urinarias | 24 h (proteínas en orina) | 24 h (proteínas en orina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | 24 h / orina | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones urinarias | Albúmina en orina | Albúmina en orina | ALIAS_OF_EXISTING_TEST | albumin | NEAR_EQUIVALENT | — | Orina y otros fluidos | — | — | — | — | Cobertura parecida, pero no asumir identidad de analito, isótopo o método. |
| Determinaciones urinarias | Una micción (albúmina en orina) | Una micción (albúmina en orina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones urinarias | 24 h (albúmina en orina) | 24 h (albúmina en orina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | 24 h / orina | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Curva de tolerancia glucosa | Curva de glucosa (OGTT) | ALIAS_OF_EXISTING_TEST | ogtt | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | OGTT | — | — |
| Endocrinología / pruebas dinámicas | 2 h (curva glucosa) | 2 h (curva glucosa) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 3 h (curva glucosa) | 3 h (curva glucosa) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 5 h (curva glucosa) | 5 h (curva glucosa) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Curva de resistencia insulina | Curva de resistencia insulina | CANONICAL_LAB_TEST | — | MISSING | lab_curva_de_resistencia_insulina | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Endocrinología / pruebas dinámicas | 2 h (curva insulina) | 2 h (curva insulina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 3 h (curva insulina) | 3 h (curva insulina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 5 h (curva insulina) | 5 h (curva insulina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Tamiz glucosa | Tamiz glucosa | CANONICAL_LAB_TEST | — | MISSING | lab_tamiz_glucosa | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Endocrinología / pruebas dinámicas | 1 h (tamiz glucosa) | 1 h (tamiz glucosa) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 2 h (tamiz glucosa) | 2 h (tamiz glucosa) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Tamiz glucosa / insulina | Tamiz glucosa / insulina | CANONICAL_LAB_TEST | — | MISSING | lab_tamiz_glucosa_insulina | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Endocrinología / pruebas dinámicas | 2 h (tamiz glucosa/insulina) | 2 h (tamiz glucosa/insulina) | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Carga oral de glucosa | Carga oral de glucosa | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | dosis 50/75/100 g; estado de ayuno | — | — | — | Condición del reto, asociada a OGTT/tamiz; no estudio por sí misma. |
| Endocrinología / pruebas dinámicas | 50 g | 50 g | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 75 g | 75 g | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | 100 g | 100 g | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Con desayuno | Con desayuno | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Endocrinología / pruebas dinámicas | Hemoglobina glicosilada | HbA1c | ALIAS_OF_EXISTING_TEST | hba1c | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Endocrinología / hormonas | H. adrenocorticotrópica | Hormona adrenocorticotrópica (ACTH) | CANONICAL_LAB_TEST | — | MISSING | lab_h_adrenocorticotropica | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | Dehidroepiandrosterona sulfato | Dehidroepiandrosterona sulfato | CANONICAL_LAB_TEST | — | MISSING | lab_dehidroepiandrosterona_sulfato | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | Fracción β cuantificada | β-hCG | ALIAS_OF_EXISTING_TEST | bhcg | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Endocrinología / hormonas | Parathormona molécula intacta | Parathormona molécula intacta | CANONICAL_LAB_TEST | — | MISSING | lab_parathormona_molecula_intacta | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | Índice HOMA-IR | Índice HOMA-IR | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Endocrinología y hormonas | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Endocrinología / hormonas | Cortisol | Cortisol | CANONICAL_LAB_TEST | — | MISSING | lab_cortisol | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | Anti-Mülleriana | Anti-Mülleriana | CANONICAL_LAB_TEST | — | MISSING | lab_anti_mulleriana | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | H. de crecimiento | H. de crecimiento | CANONICAL_LAB_TEST | — | MISSING | lab_h_de_crecimiento | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Endocrinología / hormonas | Insulina | Insulina | CANONICAL_LAB_TEST | — | MISSING | lab_insulina | Endocrinología y hormonas | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | Alfafetoproteína | Alfafetoproteína | CANONICAL_LAB_TEST | — | MISSING | lab_alfafetoproteina | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | Antígeno carcinoembrionario | Antígeno carcinoembrionario | CANONICAL_LAB_TEST | — | MISSING | lab_antigeno_carcinoembrionario | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | β 2 microglobulina | β 2 microglobulina | CANONICAL_LAB_TEST | — | MISSING | lab_2_microglobulina | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | Antígeno prostático específico total | Antígeno prostático específico total | CANONICAL_LAB_TEST | — | MISSING | lab_antigeno_prostatico_especifico_total | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | Antígeno prostático libre | Antígeno prostático libre | CANONICAL_LAB_TEST | — | MISSING | lab_antigeno_prostatico_libre | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | CA 19-9 | CA 19-9 | CANONICAL_LAB_TEST | — | MISSING | lab_ca_19_9 | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | CA 125 | CA 125 | CANONICAL_LAB_TEST | — | MISSING | lab_ca_125 | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | CA 15-3 | CA 15-3 | CANONICAL_LAB_TEST | — | MISSING | lab_ca_15_3 | Marcadores tumorales | — | — | — | IMPORTANT_SPECIALTY | — |
| Marcadores tumorales | Fracción β cuantificada (marcador) | Fracción β cuantificada (marcador) | ALIAS_OF_EXISTING_TEST | bhcg | NEAR_EQUIVALENT | — | Marcadores tumorales | — | — | — | — | Cobertura parecida, pero no asumir identidad de analito, isótopo o método. |
| Otras pruebas / primera hoja | Gasometría venosa | Gasometría venosa | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_gasometria_venosa | Química clínica | — | multiplex sujeto a definición | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Péptido natriurético cerebral tipo B | Péptido natriurético cerebral tipo B | CANONICAL_LAB_TEST | — | MISSING | lab_peptido_natriuretico_cerebral_tipo_b | Química clínica | — | — | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Troponina I alta sensibilidad | Troponina I alta sensibilidad | CANONICAL_LAB_TEST | — | MISSING | lab_troponina_i_alta_sensibilidad | Química clínica | — | — | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Homocisteína | Homocisteína | CANONICAL_LAB_TEST | — | MISSING | lab_homocisteina | Química clínica | — | — | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Ácido fólico | Ácido fólico | CANONICAL_LAB_TEST | folate | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Otras pruebas / primera hoja | Vitamina B12 | Vitamina B12 | CANONICAL_LAB_TEST | vitamin_b12 | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Otras pruebas / primera hoja | Vitamina D 25 hidroxi | Vitamina D 25 hidroxi | CANONICAL_LAB_TEST | vitamin_d | NEAR_EQUIVALENT | — | Química clínica | — | — | — | — | Vitamina D del catálogo es genérica; confirmar 25-OH antes de afirmar equivalencia. |
| Otras pruebas / primera hoja | Procalcitonina | Procalcitonina | CANONICAL_LAB_TEST | — | MISSING | lab_procalcitonina | Química clínica | — | — | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Espermatobioscopía directa | Espermatobioscopía directa | CANONICAL_LAB_TEST | — | MISSING | lab_espermatobioscopia_directa | Orina y otros fluidos | — | — | — | ADVANCED_SPECIALTY | — |
| Otras pruebas / primera hoja | Eosinófilos en moco nasal | Eosinófilos en moco nasal | CANONICAL_LAB_TEST | — | MISSING | lab_eosinofilos_en_moco_nasal | Orina y otros fluidos | — | — | — | ADVANCED_SPECIALTY | — |
| Determinaciones en materia fecal | Coprológico | Coprológico | CANONICAL_LAB_TEST | — | MISSING | lab_coprologico | Materia fecal | — | — | — | CRITICAL_COMMON | — |
| Determinaciones en materia fecal | Coproparasitoscópico | Coproparasitoscópico | CANONICAL_MULTIPLEX_PANEL | stool_ova_parasites | EXACT_EXISTING_MATCH | — | Materia fecal | — | multiplex sujeto a definición | — | — | — |
| Determinaciones en materia fecal | 1mta. | 1mta. | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Materia fecal | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones en materia fecal | 2mta. | 2mta. | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Materia fecal | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones en materia fecal | 3mta. | 3mta. | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Materia fecal | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Determinaciones en materia fecal | Sangre oculta en heces (FOB) | Sangre oculta en heces | ALIAS_OF_EXISTING_TEST | fecal_occult_blood | EXISTING_ALIAS | — | Materia fecal | — | — | — | — | — |
| Determinaciones en materia fecal | Calprotectina cuantificada | Calprotectina cuantificada | CANONICAL_LAB_TEST | — | MISSING | lab_calprotectina_cuantificada | Materia fecal | — | — | — | CRITICAL_COMMON | — |
| Determinaciones en materia fecal | Ag. Helicobacter pylori | Ag. Helicobacter pylori | CANONICAL_LAB_TEST | — | MISSING | lab_ag_helicobacter_pylori | Materia fecal | — | — | — | CRITICAL_COMMON | — |
| Determinaciones en materia fecal | Clostridioides difficile toxina A, B y Ag. GDH | Clostridioides difficile toxina A, B y Ag. GDH | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_clostridioides_difficile_toxina_a_b_y_ag_gdh | Materia fecal | — | multiplex sujeto a definición | — | CRITICAL_COMMON | — |
| Determinaciones en materia fecal | Panel Cryptosporidium y Giardia lamblia | Panel Cryptosporidium y Giardia lamblia | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_panel_cryptosporidium_y_giardia_lamblia | Materia fecal | — | multiplex sujeto a definición | — | CRITICAL_COMMON | — |
| Determinaciones en materia fecal | Panel Rotavirus | Panel Rotavirus | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_panel_rotavirus | Materia fecal | — | multiplex sujeto a definición | — | CRITICAL_COMMON | — |
| Perfiles / andrológico | Andrológico | Andrológico | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / andrológico | Hormona luteinizante | LH | ALIAS_OF_EXISTING_TEST | lh | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / andrológico | Hormona folículo estimulante | FSH | ALIAS_OF_EXISTING_TEST | fsh | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / andrológico | Prolactina | Prolactina | CANONICAL_LAB_TEST | prolactin | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / andrológico | Testosterona total | Testosterona total | CANONICAL_LAB_TEST | testosterone_total | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / andrológico | Testosterona libre | Testosterona libre | CANONICAL_LAB_TEST | testosterone_free | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / andrológico | Globulina transportadora de hormonas sexuales | Globulina transportadora de hormonas sexuales | CANONICAL_LAB_TEST | — | MISSING | lab_globulina_transportadora_de_hormonas_sexuales | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / celíaco | Celíaco | Celíaco | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / celíaco | Acs. anti-gliadina IgA e IgG | Acs. anti-gliadina IgA e IgG | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Inmunología | — | — | — | UNCERTAIN | Lectura/alcance o composición requiere curación; no activar clave. |
| Perfiles / celíaco | Acs. anti-transglutaminasa IgA e IgG | Acs. anti-transglutaminasa IgA e IgG | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_transglutaminasa_iga_e_igg | Inmunología | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / celíaco | Acs. anti-endomisio IgA e IgG por I.F.I. | Acs. anti-endomisio IgA e IgG por I.F.I. | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_endomisio_iga_e_igg_por_i_f_i | Inmunología | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / cinética de hierro | Cinética de hierro | Cinética de hierro | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / cinética de hierro | Hierro sérico | Hierro sérico | CANONICAL_LAB_TEST | iron | EXACT_EXISTING_MATCH | — | Hematología | — | — | — | — | — |
| Perfiles / cinética de hierro | Ferritina | Ferritina | CANONICAL_LAB_TEST | ferritin | EXACT_EXISTING_MATCH | — | Hematología | — | — | — | — | — |
| Perfiles / cinética de hierro | Capacidad libre de fijación de hierro | Capacidad libre de fijación de hierro | CANONICAL_LAB_TEST | — | MISSING | lab_capacidad_libre_de_fijacion_de_hierro | Hematología | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / cinética de hierro | Capacidad de fijación total de hierro (transferrina) | CTFH (TIBC) | ALIAS_OF_EXISTING_TEST | tibc | EXISTING_ALIAS | — | Hematología | — | — | — | — | — |
| Perfiles / cinética de hierro | % de saturación de transferrina | % Saturación transferrina | ALIAS_OF_EXISTING_TEST | transferrin_sat | EXISTING_ALIAS | — | Hematología | — | — | — | — | — |
| Perfiles / ginecológico | Ginecológico | Ginecológico | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / ginecológico | Hormona luteinizante | LH | ALIAS_OF_EXISTING_TEST | lh | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / ginecológico | Hormona folículo estimulante | FSH | ALIAS_OF_EXISTING_TEST | fsh | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / ginecológico | Estradiol | Estradiol | CANONICAL_LAB_TEST | estradiol | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / ginecológico | Progesterona | Progesterona | CANONICAL_LAB_TEST | progesterone | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / ginecológico | Testosterona total | Testosterona total | CANONICAL_LAB_TEST | testosterone_total | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / ginecológico | Prolactina | Prolactina | CANONICAL_LAB_TEST | prolactin | EXACT_EXISTING_MATCH | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / hepático | Hepático | Hepático | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / hepático | Aspartato aminotransferasa | AST (TGO) | ALIAS_OF_EXISTING_TEST | ast | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Alanino aminotransferasa | ALT (TGP) | ALIAS_OF_EXISTING_TEST | alt | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Gamaglutamil transpeptidasa | GGT | ALIAS_OF_EXISTING_TEST | ggt | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Fosfatasa alcalina | Fosfatasa alcalina (ALP) | ALIAS_OF_EXISTING_TEST | alp | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Deshidrogenasa láctica | Deshidrogenasa láctica | CANONICAL_LAB_TEST | — | MISSING | lab_deshidrogenasa_lactica | Química clínica | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / hepático | Bilirrubina directa | Bilirrubina directa | CANONICAL_LAB_TEST | bilirubin_direct | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Bilirrubina indirecta | Bilirrubina indirecta | CANONICAL_LAB_TEST | bilirubin_indirect | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Bilirrubina total | Bilirrubina total | CANONICAL_LAB_TEST | bilirubin_total | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Proteínas totales | Proteínas totales | CANONICAL_LAB_TEST | total_protein | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Albúmina | Albúmina | CANONICAL_LAB_TEST | albumin | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / hepático | Globulina | Globulina | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Perfiles / hepático | Relación albúmina/globulina | Relación albúmina/globulina | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Química clínica | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Perfiles / lípidos | Lípidos | Lípidos | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / lípidos | Colesterol total | Colesterol total | CANONICAL_LAB_TEST | chol_total | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / lípidos | Colesterol HDL | HDL | ALIAS_OF_EXISTING_TEST | hdl | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / lípidos | Colesterol LDL | LDL | ALIAS_OF_EXISTING_TEST | ldl | EXISTING_ALIAS | — | Química clínica | — | — | — | — | — |
| Perfiles / lípidos | Triglicéridos | Triglicéridos | CANONICAL_LAB_TEST | triglycerides | EXACT_EXISTING_MATCH | — | Química clínica | — | — | — | — | — |
| Perfiles / litiasis | Litiasis | Litiasis | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / litiasis | Suero (litiasis) | Suero (litiasis) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Perfiles / litiasis | Orina (litiasis) | Orina (litiasis) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Orina y otros fluidos | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Perfiles / litiasis | Análisis de lito renal | Análisis de lito renal | CANONICAL_LAB_TEST | — | MISSING | lab_analisis_de_lito_renal | Orina y otros fluidos | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / reumático | Reumático | Reumático | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / reumático | VSG | VSG | CANONICAL_LAB_TEST | esr | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Perfiles / reumático | Proteína C reactiva H.S. | PCR ultrasensible | ALIAS_OF_EXISTING_TEST | crp_hs | EXISTING_ALIAS | — | Inmunología | — | — | — | — | — |
| Perfiles / reumático | Ácido úrico | Ácido úrico | CANONICAL_LAB_TEST | uric_acid | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Perfiles / reumático | Factor reumatoide | Factor reumatoide | CANONICAL_LAB_TEST | rf | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Perfiles / reumático | Antiestreptolisinas | Antiestreptolisinas | CANONICAL_LAB_TEST | — | MISSING | lab_antiestreptolisinas | Inmunología | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / tiroideo | Tiroideo | Tiroideo | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / tiroideo | Hormona estimulante de tiroides | TSH | ALIAS_OF_EXISTING_TEST | tsh | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / tiroideo | Triyodotironina total | Triyodotironina total | CANONICAL_LAB_TEST | — | MISSING | lab_triyodotironina_total | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / tiroideo | Triyodotironina libre | T3 libre | ALIAS_OF_EXISTING_TEST | ft3 | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / tiroideo | Tiroxina total | Tiroxina total | CANONICAL_LAB_TEST | — | MISSING | lab_tiroxina_total | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / tiroideo | Tiroxina libre | T4 libre | ALIAS_OF_EXISTING_TEST | ft4 | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / tiroideo | Capacidad de unión de tiroxina | Capacidad de unión de tiroxina | CANONICAL_LAB_TEST | — | MISSING | lab_capacidad_de_union_de_tiroxina | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / otras pruebas tiroideas | Otras pruebas tiroideas | Otras pruebas tiroideas | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / otras pruebas tiroideas | Acs. anti-peroxidasa | Anti-TPO | ALIAS_OF_EXISTING_TEST | anti_tpo | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / otras pruebas tiroideas | Acs. anti-tiroglobulina | Anti-tiroglobulina | ALIAS_OF_EXISTING_TEST | anti_tg | EXISTING_ALIAS | — | Endocrinología y hormonas | — | — | — | — | — |
| Perfiles / otras pruebas tiroideas | Tiroglobulina | Tiroglobulina | CANONICAL_LAB_TEST | — | MISSING | lab_tiroglobulina | Endocrinología y hormonas | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | TORCH | TORCH | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Perfiles y paneles | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Perfiles / TORCH | Acs. anti-citomegalovirus | Acs. anti-citomegalovirus | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_citomegalovirus | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgM (CMV) | IgM (CMV) | CANONICAL_LAB_TEST | — | MISSING | lab_igm_cmv | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgG (CMV) | IgG (CMV) | CANONICAL_LAB_TEST | — | MISSING | lab_igg_cmv | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | Acs. anti-Toxoplasma gondii | Acs. anti-Toxoplasma gondii | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_toxoplasma_gondii | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgM (Toxoplasma) | IgM (Toxoplasma) | CANONICAL_LAB_TEST | — | MISSING | lab_igm_toxoplasma | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgG (Toxoplasma) | IgG (Toxoplasma) | CANONICAL_LAB_TEST | — | MISSING | lab_igg_toxoplasma | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | Acs. anti-rubeola | Acs. anti-rubeola | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_rubeola | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgM (rubeola) | IgM (rubeola) | CANONICAL_LAB_TEST | — | MISSING | lab_igm_rubeola | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgG (rubeola) | IgG (rubeola) | CANONICAL_LAB_TEST | — | MISSING | lab_igg_rubeola | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | Acs. anti-herpes I | Acs. anti-herpes I | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_herpes_i | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgM (herpes I) | IgM (herpes I) | CANONICAL_LAB_TEST | — | MISSING | lab_igm_herpes_i | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgG (herpes I) | IgG (herpes I) | CANONICAL_LAB_TEST | — | MISSING | lab_igg_herpes_i | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | Acs. anti-herpes II | Acs. anti-herpes II | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_herpes_ii | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgM (herpes II) | IgM (herpes II) | CANONICAL_LAB_TEST | — | MISSING | lab_igm_herpes_ii | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Perfiles / TORCH | IgG (herpes II) | IgG (herpes II) | CANONICAL_LAB_TEST | — | MISSING | lab_igg_herpes_ii | Serologías / Hepatitis | — | — | — | ADVANCED_SPECIALTY | — |
| Inmunología | Acs. VIH 4ta. Generación | Acs. VIH 4ta. Generación | CANONICAL_LAB_TEST | hiv_ag_ac | NEAR_EQUIVALENT | — | Inmunología | — | — | — | — | VIH Ag/Ac compatible, pero confirmar antígeno/anticuerpo de cuarta generación. |
| Inmunología | V.D.R.L. | VDRL (prueba no treponémica) | CANONICAL_LAB_TEST | — | MISSING | lab_v_d_r_l | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | Acs. anti-Treponema pallidum IgG e IgM (FUSERIBLOT) | Acs. anti-Treponema pallidum IgG e IgM (FUSERIBLOT) | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Inmunología | — | — | — | UNCERTAIN | Lectura/alcance o composición requiere curación; no activar clave. |
| Inmunología | Complemento C3 | C3 | ALIAS_OF_EXISTING_TEST | c3 | EXISTING_ALIAS | — | Inmunología | — | — | — | — | — |
| Inmunología | Complemento C4 | C4 | ALIAS_OF_EXISTING_TEST | c4 | EXISTING_ALIAS | — | Inmunología | — | — | — | — | — |
| Inmunología | Inmunoglobulinas | Inmunoglobulinas | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Inmunología | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Inmunología | IgA | IgA | CANONICAL_LAB_TEST | iga | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Inmunología | IgG | IgG | CANONICAL_LAB_TEST | igg | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Inmunología | IgM | IgM | CANONICAL_LAB_TEST | igm | EXACT_EXISTING_MATCH | — | Inmunología | — | — | — | — | — |
| Inmunología | IgE | IgE | CANONICAL_LAB_TEST | — | MISSING | lab_ige | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | Acs. Helicobacter pylori IgG | Acs. Helicobacter pylori IgG | CANONICAL_LAB_TEST | — | MISSING | lab_acs_helicobacter_pylori_igg | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | Brucella (Rosa de Bengala) | Brucella (Rosa de Bengala) | CANONICAL_LAB_TEST | — | MISSING | lab_brucella_rosa_de_bengala | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | Perfil virus Epstein-Barr | Perfil virus Epstein-Barr | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Inmunología | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Inmunología | VCA IgM | VCA IgM | CANONICAL_LAB_TEST | — | MISSING | lab_vca_igm | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | VCA IgG | VCA IgG | CANONICAL_LAB_TEST | — | MISSING | lab_vca_igg | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Inmunología | EBNA IgG | EBNA IgG | CANONICAL_LAB_TEST | — | MISSING | lab_ebna_igg | Inmunología | — | — | — | IMPORTANT_SPECIALTY | — |
| Serología de hepatitis | Acs. VHA IgM | Acs. VHA IgM | CANONICAL_LAB_TEST | — | MISSING | lab_acs_vha_igm | Serologías / Hepatitis | — | — | — | IMPORTANT_SPECIALTY | — |
| Serología de hepatitis | Acs. VHA IgG | Acs. VHA IgG | CANONICAL_LAB_TEST | — | MISSING | lab_acs_vha_igg | Serologías / Hepatitis | — | — | — | IMPORTANT_SPECIALTY | — |
| Serología de hepatitis | Ag. de superficie del VHB | HBsAg | ALIAS_OF_EXISTING_TEST | hbsag | EXISTING_ALIAS | — | Serologías / Hepatitis | — | — | — | — | — |
| Serología de hepatitis | Acs. anti-antígeno de superficie | Anti-HBs | ALIAS_OF_EXISTING_TEST | anti_hbs | EXISTING_ALIAS | — | Serologías / Hepatitis | — | — | — | — | — |
| Serología de hepatitis | Acs. anti-VHC | VHC (anticuerpos) | ALIAS_OF_EXISTING_TEST | hcv_ab | EXISTING_ALIAS | — | Serologías / Hepatitis | — | — | — | — | — |
| Serología de hepatitis | Acs. anti-core del VHB IgM | Acs. anti-core del VHB IgM | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_core_del_vhb_igm | Serologías / Hepatitis | — | — | — | IMPORTANT_SPECIALTY | — |
| Serología de hepatitis | Acs. anti-core del VHB IgG | Acs. anti-core del VHB IgG | ALIAS_OF_EXISTING_TEST | anti_hbc | NEAR_EQUIVALENT | — | Serologías / Hepatitis | — | — | — | — | Cobertura parecida, pero no asumir identidad de analito, isótopo o método. |
| Fármacos | Ácido valproico | Ácido valproico | CANONICAL_LAB_TEST | — | MISSING | lab_acido_valproico | Monitoreo de fármacos | — | — | — | IMPORTANT_SPECIALTY | — |
| Fármacos | Carbamazepina | Carbamazepina | CANONICAL_LAB_TEST | — | MISSING | lab_carbamazepina | Monitoreo de fármacos | — | — | — | IMPORTANT_SPECIALTY | — |
| Fármacos | Fenitoína | Fenitoína | CANONICAL_LAB_TEST | — | MISSING | lab_fenitoina | Monitoreo de fármacos | — | — | — | IMPORTANT_SPECIALTY | — |
| Fármacos | Digoxina | Digoxina | CANONICAL_LAB_TEST | — | MISSING | lab_digoxina | Monitoreo de fármacos | — | — | — | IMPORTANT_SPECIALTY | — |
| Fármacos | Fenobarbital | Fenobarbital | CANONICAL_LAB_TEST | — | MISSING | lab_fenobarbital | Monitoreo de fármacos | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-nucleares I.F.I. | ANA | ALIAS_OF_EXISTING_TEST | ana | EXISTING_ALIAS | — | Autoinmunidad | — | — | — | — | — |
| Autoinmunidad | Acs. anti-DNA doble cadena I.F.I. | Acs. anti-DNA doble cadena I.F.I. | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_dna_doble_cadena_i_f_i | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-citoplasma de neutrófilo I.F.I. (p-ANCA, c-ANCA) | ANCA | ALIAS_OF_EXISTING_TEST | anca | EXISTING_ALIAS | — | Autoinmunidad | — | — | — | — | — |
| Autoinmunidad | Acs. anti-músculo liso I.F.I. (ASMA) | Acs. anti-músculo liso I.F.I. (ASMA) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_musculo_liso_i_f_i_asma | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-mitocondriales I.F.I. (AMA) | Acs. anti-mitocondriales I.F.I. (AMA) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_mitocondriales_i_f_i_ama | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-células parietales I.F.I. (APCA) | Acs. anti-células parietales I.F.I. (APCA) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_celulas_parietales_i_f_i_apca | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-microsomales de hígado y riñón I.F.I. (LKM1) | Acs. anti-microsomales de hígado y riñón I.F.I. (LKM1) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_microsomales_de_higado_y_rinon_i_f_i_lkm1 | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-péptido cíclicos citrulinados | Anti-CCP | ALIAS_OF_EXISTING_TEST | anti_ccp | EXISTING_ALIAS | — | Autoinmunidad | — | — | — | — | — |
| Autoinmunidad | Acs. anti-β2 glicoproteínas (IgA, IgG, IgM) | Acs. anti-β2 glicoproteínas (IgA, IgG, IgM) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_2_glicoproteinas_iga_igg_igm | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Acs. anti-cardiolipinas (IgA, IgG, IgM) | Acs. anti-cardiolipinas (IgA, IgG, IgM) | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_cardiolipinas_iga_igg_igm | Autoinmunidad | — | — | — | IMPORTANT_SPECIALTY | — |
| Autoinmunidad | Panel vasculitis Athena | Panel vasculitis Athena | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Autoinmunidad | Panel antinucleares Athena | Panel antinucleares Athena | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Autoinmunidad | Panel hepatitis autoinmune INMUNOBLOT | Panel hepatitis autoinmune INMUNOBLOT | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Autoinmunidad | Panel anti-nucleares INMUNOBLOT | Panel anti-nucleares INMUNOBLOT | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Autoinmunidad | Panel miopatías INMUNOBLOT | Panel miopatías INMUNOBLOT | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Autoinmunidad | Panel esclerodermia INMUNOBLOT | Panel esclerodermia INMUNOBLOT | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Biología molecular / pre-trasplante | Pruebas cruzadas linfocitarias por CDC | Pruebas cruzadas linfocitarias por CDC | CANONICAL_LAB_TEST | — | MISSING | lab_pruebas_cruzadas_linfocitarias_por_cdc | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / pre-trasplante | Citotoxicidad dependiente del complemento linfocitos T y B | Citotoxicidad dependiente del complemento linfocitos T y B | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Trasplante | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Biología molecular / pre-trasplante | Pruebas cruzadas linfocitarias por CF | Pruebas cruzadas linfocitarias por CF | CANONICAL_LAB_TEST | — | MISSING | lab_pruebas_cruzadas_linfocitarias_por_cf | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / pre-trasplante | Citometría de flujo con linfocitos T y B | Citometría de flujo con linfocitos T y B | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Trasplante | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Biología molecular / pre-trasplante | Tipificación HLA clase I y clase II | Tipificación HLA clase I y clase II | CANONICAL_LAB_TEST | — | MISSING | lab_tipificacion_hla_clase_i_y_clase_ii | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / pre-trasplante | A, B, C, DRB1, DRB3,4,5, DQA1, DQB1, DPA1, DPB1 | A, B, C, DRB1, DRB3,4,5, DQA1, DQB1, DPA1, DPB1 | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Trasplante | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Biología molecular / pre-trasplante | PRA single antigen | PRA single antigen | CANONICAL_LAB_TEST | — | MISSING | lab_pra_single_antigen | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / pre-trasplante | Acs. anti-MICA | Acs. anti-MICA | CANONICAL_LAB_TEST | — | MISSING | lab_acs_anti_mica | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Panel infeccioso viral | Panel infeccioso viral | ORDER_PRESET_OR_PROFILE | — | NOT_A_CANONICAL_TEST | — | Trasplante | — | preset | — | — | Preset candidato; composición detallada abajo. |
| Biología molecular / post-trasplante | Carga viral citomegalovirus (CMV) | Carga viral citomegalovirus (CMV) | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_citomegalovirus_cmv | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Carga viral Epstein-Barr (EBV) | Carga viral Epstein-Barr (EBV) | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_epstein_barr_ebv | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Carga viral virus BK | Carga viral virus BK | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_virus_bk | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Carga viral adenovirus | Carga viral adenovirus | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_adenovirus | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Tacrolimus | Tacrolimus | CANONICAL_LAB_TEST | — | MISSING | lab_tacrolimus | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Ciclosporina | Ciclosporina | CANONICAL_LAB_TEST | — | MISSING | lab_ciclosporina | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / post-trasplante | Sirolimus | Sirolimus | CANONICAL_LAB_TEST | — | MISSING | lab_sirolimus | Trasplante | — | — | — | ADVANCED_SPECIALTY | — |
| Virus respiratorios PCR | Panel patógenos respiratorios | Panel patógenos respiratorios | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_panel_patogenos_respiratorios | Biología molecular / PCR | — | multiplex sujeto a definición | — | ADVANCED_SPECIALTY | — |
| Virus respiratorios PCR | SARS CoV-2 | SARS CoV-2 | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Virus respiratorios PCR | Influenza A y B | Influenza A y B | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Virus respiratorios PCR | Virus sincitial respiratorio | Virus sincitial respiratorio | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | — | Componente de resultado o descriptor de panel; evaluar si también se vende por separado. |
| Virus respiratorios PCR | Panel virus dengue, zika y chikungunya | Panel virus dengue, zika y chikungunya | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | UNCERTAIN | Lectura/alcance o composición requiere curación; no activar clave. |
| Hematología y coagulación / molecular | Panel trombofilia | Panel trombofilia | SOURCE_UNCERTAIN | thrombophilia | NEAR_EQUIVALENT | — | Biología molecular / PCR | — | — | — | UNCERTAIN | Fuente mezcla variantes moleculares; GENETICA/thrombophilia es más amplia. Confirmar panel antes de reutilizar. |
| Hematología y coagulación / molecular | Mutación JAK2 V617F | Mutación JAK2 V617F | CANONICAL_LAB_TEST | — | MISSING | lab_mutacion_jak2_v617f | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Hematología y coagulación / molecular | BCR/ABL1 (cromosoma Philadelphia) | BCR/ABL1 (cromosoma Philadelphia) | CANONICAL_LAB_TEST | — | MISSING | lab_bcr_abl1_cromosoma_philadelphia | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Antígeno HLA-B27 | HLA-B27 (antígeno) | CANONICAL_LAB_TEST | — | MISSING | lab_antigeno_hla_b27 | Inmunología | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Carga viral hepatitis B (VHB) | Carga viral hepatitis B (VHB) | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_hepatitis_b_vhb | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Carga viral hepatitis C (VHC) | Carga viral hepatitis C (VHC) | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_hepatitis_c_vhc | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Carga viral inmunodeficiencia (VIH) | Carga viral inmunodeficiencia (VIH) | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_inmunodeficiencia_vih | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Carga viral parvovirus B-19 | Carga viral parvovirus B-19 | CANONICAL_LAB_TEST | — | MISSING | lab_carga_viral_parvovirus_b_19 | Biología molecular / PCR | — | — | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Panel virus papiloma humano (VPH 28) | Panel de VPH de 28 tipos (composición pendiente) | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | UNCERTAIN | Indica 28 blancos pero falta composición legible/método; no activar panel. |
| Biología molecular / otras pruebas | Mycobacterium tuberculosis en (muestra) | Mycobacterium tuberculosis en (muestra) | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | muestra/sitio | — | — | UNCERTAIN | El renglón no declara cultivo, baciloscopía o PCR; requiere aclarar método. |
| Biología molecular / otras pruebas | Panel gastrointestinal | Panel gastrointestinal | CANONICAL_MULTIPLEX_PANEL | — | MISSING | lab_panel_gastrointestinal | Biología molecular / PCR | — | multiplex sujeto a definición | — | ADVANCED_SPECIALTY | — |
| Biología molecular / otras pruebas | Panel ETS vaginal / uretral | Panel ETS vaginal / uretral | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | — | — | UNCERTAIN | Lectura/alcance o composición requiere curación; no activar clave. |
| Microbiología / cultivos | Urocultivo | Urocultivo | CANONICAL_LAB_TEST | urine_culture | EXACT_EXISTING_MATCH | — | Microbiología | — | — | — | — | — |
| Microbiología / cultivos | Coprocultivo | Coprocultivo | CANONICAL_LAB_TEST | stool_culture | EXACT_EXISTING_MATCH | — | Microbiología | — | — | — | — | — |
| Microbiología / cultivos | Expectoración (cultivo) | Expectoración (cultivo) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / cultivos | Espermo cultivo | Espermo cultivo | CANONICAL_LAB_TEST | — | MISSING | lab_espermo_cultivo | Microbiología | — | — | — | CRITICAL_COMMON | — |
| Microbiología / cultivos | Hemocultivo | Hemocultivo | CANONICAL_LAB_TEST | blood_culture | EXACT_EXISTING_MATCH | — | Microbiología | — | — | — | — | — |
| Microbiología / cultivos | Líquidos corporales (cultivo) | Líquidos corporales (cultivo) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / cultivos | Secreción de herida (cultivo) | Secreción de herida (cultivo) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / exudados | Faríngeo | Faríngeo | SPECIMEN_OR_SAMPLE_PARAMETER | throat_swab | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / exudados | Nasal | Nasal | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / exudados | Vaginal | Vaginal | SPECIMEN_OR_SAMPLE_PARAMETER | vaginal_swab | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / exudados | Vulvar | Vulvar | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / exudados | Uretral | Uretral | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / buscar en | Orina (buscar en) | Orina (buscar en) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / buscar en | Expectoración (buscar en) | Expectoración (buscar en) | SPECIMEN_OR_SAMPLE_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | muestra/sitio | — | — | — | Sitio/material a estructurar; si hay estudio actual de exudado, conservarlo por compatibilidad. |
| Microbiología / buscar en | No. muestras | No. muestras | COLLECTION_OR_TIMING_PARAMETER | — | NOT_A_CANONICAL_TEST | — | Microbiología | seriación/dosis | — | — | — | Parámetro de toma, duración, dosis o seriación; no duplicar analito. |
| Microbiología / buscar en | Chlamydia T. | Chlamydia T. | CANONICAL_LAB_TEST | — | MISSING | lab_chlamydia_t | Microbiología | — | — | — | ADVANCED_SPECIALTY | — |
| Microbiología / buscar en | Ureaplasma | Ureaplasma | CANONICAL_LAB_TEST | — | MISSING | lab_ureaplasma | Microbiología | — | — | — | ADVANCED_SPECIALTY | — |
| Microbiología / buscar en | Mycoplasma | Mycoplasma | CANONICAL_LAB_TEST | — | MISSING | lab_mycoplasma | Microbiología | — | — | — | ADVANCED_SPECIALTY | — |
| Microbiología / buscar en | Otros cultivos | Otros cultivos | PROVIDER_SPECIFIC_CONFIGURATION | — | NOT_A_CANONICAL_TEST | — | Microbiología | — | — | — | PROVIDER_SPECIFIC | Composición, método o servicio dependen del proveedor; no hacer identidad universal aún. |
| Microbiología / buscar en | Citoquímico de | Citoquímico de | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Microbiología | — | — | — | UNCERTAIN | Campo sin material indicado; no inferir líquido. |
| Administración y logística | Paciente | Paciente | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Edad | Edad | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Sexo | Sexo | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Dr. / Dra. | Dr. / Dra. | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Fecha | Fecha | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Diagnóstico | Diagnóstico | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Matriz OKABE: horario | Matriz OKABE: horario | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Sucursal SAN COSME: horario | Sucursal SAN COSME: horario | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Contacto / sucursal | Contacto / sucursal | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Otros estudios solicitados | Otros estudios solicitados | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Previo cita para endocrinología | Previo cita para endocrinología | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Peso (campo) | Peso (campo) | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Altura (campo) | Altura (campo) | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Administración y logística | Firma/sello del laboratorio | Firma/sello del laboratorio | ADMIN_OR_LOGISTICS | — | NOT_A_CANONICAL_TEST | — | Otros | — | — | — | — | — |
| Hematología y coagulación / molecular | Panel trombofilia: lista genética en letra pequeña; Factor V Leiden G1691A, protrombina G20210A y otros blancos parcialmente legibles | Panel trombofilia: lista genética en letra pequeña; Factor V Leiden G1691A, protrombina G20210A y otros blancos parcialmente legibles | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | composición requiere curación | — | UNCERTAIN | No se infieren blancos invisibles; verificar hoja de mayor resolución o catálogo proveedor. |
| Biología molecular / otras pruebas | Panel gastrointestinal: lista de agentes en letra pequeña; algunos nombres no se leen con confianza | Panel gastrointestinal: lista de agentes en letra pequeña; algunos nombres no se leen con confianza | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | composición requiere curación | — | UNCERTAIN | No se infieren blancos invisibles; verificar hoja de mayor resolución o catálogo proveedor. |
| Biología molecular / otras pruebas | Panel ETS vaginal / uretral: lista de agentes en letra pequeña; algunos nombres no se leen con confianza | Panel ETS vaginal / uretral: lista de agentes en letra pequeña; algunos nombres no se leen con confianza | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Biología molecular / PCR | — | composición requiere curación | — | UNCERTAIN | No se infieren blancos invisibles; verificar hoja de mayor resolución o catálogo proveedor. |
| Autoinmunidad | Paneles Athena / INMUNOBLOT: subcomponentes en letra pequeña no legibles con confianza | Paneles Athena / INMUNOBLOT: subcomponentes en letra pequeña no legibles con confianza | SOURCE_UNCERTAIN | — | NOT_A_CANONICAL_TEST | — | Autoinmunidad | — | composición requiere curación | — | UNCERTAIN | No se infieren blancos invisibles; verificar hoja de mayor resolución o catálogo proveedor. |
| Determinaciones en materia fecal | Toxina A de C. difficile | Toxina A de C. difficile | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Toxina B de C. difficile | Toxina B de C. difficile | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Antígeno GDH de C. difficile | Antígeno GDH de C. difficile | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Cryptosporidium (panel) | Cryptosporidium (panel) | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Giardia lamblia (panel) | Giardia lamblia (panel) | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Rotavirus (panel) | Rotavirus (panel) | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Astrovirus (panel) | Astrovirus (panel) | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |
| Determinaciones en materia fecal | Adenovirus (panel) | Adenovirus (panel) | RESULT_COMPONENT_NOT_ORDERABLE_ALONE | — | NOT_A_CANONICAL_TEST | — | Materia fecal | heces | componente de panel | — | — | Blanco impreso dentro del ensayo combinado; no crear orden independiente por esta sola evidencia. |

## Tabla 2 — todo el catálogo activo relevante (ambas direcciones)

| CANONICAL_KEY | DISPLAY_NAME | CATEGORY | ALIASES | FOUND_IN_SOURCE_FORM | SOURCE_EQUIVALENT | PROPOSED_NAV_GROUPERS | NOTES |
| --- | --- | --- | --- | --- | --- | --- | --- |
| brca1_2 | BRCA1/BRCA2 (germinal) | GENETICA | — | false | — | Biología molecular / PCR; Marcadores tumorales | Fuente ausente: conservar |
| karyotype | Cariotipo | GENETICA | — | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| wes | Exoma clínico (WES) | GENETICA | WES | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| wgs | Genoma clínico (WGS) | GENETICA | WGS | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| cma_microarray | Microarreglo cromosómico (CMA / Microarray) | GENETICA | CMA; Microarray | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| nipt | NIPT (tamiz prenatal no invasivo) | GENETICA | NIPT | false | — | Biología molecular / PCR; Endocrinología y hormonas | Fuente ausente: conservar |
| hereditary_cancer_germline | Panel de cáncer hereditario (multigénico) | GENETICA | — | false | — | Biología molecular / PCR; Marcadores tumorales | Fuente ausente: conservar |
| thrombophilia | Panel de trombofilia (genético) | GENETICA | — | true | Panel trombofilia | Biología molecular / PCR; Coagulación | Alcance/método requiere validación |
| pgx | Panel farmacogenómico (PGx) | GENETICA | PGx | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| somatic_tumor_ngs | Panel tumoral (NGS) — somático | GENETICA | — | false | — | Biología molecular / PCR; Marcadores tumorales | Fuente ausente: conservar |
| lynch | Síndrome de Lynch (germinal) | GENETICA | — | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| carrier_screening | Tamiz de portadores (Carrier screening) | GENETICA | — | false | — | Biología molecular / PCR | Fuente ausente: conservar |
| transferrin_sat | % Saturación transferrina | LABORATORIO | — | true | % de saturación de transferrina | Hematología | — |
| folate | Ácido fólico | LABORATORIO | — | true | Ácido fólico | Química clínica | — |
| uric_acid | Ácido úrico | LABORATORIO | — | true | Ácido úrico | Inmunología; Química clínica | — |
| albumin | Albúmina | LABORATORIO | — | true | Albúmina en orina; Albúmina | Orina y otros fluidos; Química clínica | Alcance/método requiere validación |
| alt | ALT (TGP) | LABORATORIO | — | true | Alanino aminotransferasa | Química clínica | — |
| amylase | Amilasa | LABORATORIO | — | true | Amilasa | Química clínica | — |
| ana | ANA | LABORATORIO | — | true | Acs. anti-nucleares I.F.I. | Autoinmunidad | — |
| anca | ANCA | LABORATORIO | — | true | Acs. anti-citoplasma de neutrófilo I.F.I. (p-ANCA, c-ANCA) | Autoinmunidad | — |
| anti_ccp | Anti-CCP | LABORATORIO | — | true | Acs. anti-péptido cíclicos citrulinados | Autoinmunidad | — |
| anti_hbc | Anti-HBc | LABORATORIO | — | true | Acs. anti-core del VHB IgG | Serologías / Hepatitis | Alcance/método requiere validación |
| anti_hbs | Anti-HBs | LABORATORIO | — | true | Acs. anti-antígeno de superficie | Serologías / Hepatitis | — |
| anti_tg | Anti-tiroglobulina | LABORATORIO | — | true | Acs. anti-tiroglobulina | Endocrinología y hormonas | — |
| anti_tpo | Anti-TPO | LABORATORIO | — | true | Acs. anti-peroxidasa | Endocrinología y hormonas | — |
| ast | AST (TGO) | LABORATORIO | — | true | Aspartato aminotransferasa | Química clínica | — |
| bilirubin_direct | Bilirrubina directa | LABORATORIO | — | true | Bilirrubina directa | Química clínica | — |
| bilirubin_indirect | Bilirrubina indirecta | LABORATORIO | — | true | Bilirrubina indirecta | Química clínica | — |
| bilirubin_total | Bilirrubina total | LABORATORIO | — | true | Bilirrubina total | Química clínica | — |
| cbc | Biometría hemática (BH / CBC) | LABORATORIO | BH / CBC; Biometría hemática; CBC | true | Citometría hemática | Hematología | — |
| c3 | C3 | LABORATORIO | — | true | Complemento C3 | Inmunología | — |
| c4 | C4 | LABORATORIO | — | true | Complemento C4 | Inmunología | — |
| calcium | Calcio | LABORATORIO | — | true | Calcio | Química clínica | — |
| chloride | Cloro | LABORATORIO | — | true | Cloro | Química clínica | — |
| non_hdl | Colesterol no-HDL | LABORATORIO | — | false | — | Química clínica | Fuente ausente: conservar |
| chol_total | Colesterol total | LABORATORIO | — | true | Colesterol; Colesterol total | Química clínica | — |
| stool_culture | Coprocultivo | LABORATORIO | — | true | Coprocultivo | Materia fecal; Microbiología | — |
| stool_ova_parasites | Coproparasitoscópico | LABORATORIO | — | true | Coproparasitoscópico | Materia fecal | — |
| creatinine | Creatinina | LABORATORIO | — | true | Creatinina | Química clínica | — |
| tibc | CTFH (TIBC) | LABORATORIO | — | true | Capacidad de fijación total de hierro (transferrina) | Hematología | — |
| ogtt | Curva de glucosa (OGTT) | LABORATORIO | OGTT | true | Curva de tolerancia glucosa | Endocrinología y hormonas | — |
| d_dimer | Dímero D | LABORATORIO | — | true | Dímero D | Coagulación | — |
| urinalysis | EGO (examen general de orina) | LABORATORIO | EGO; examen general de orina | true | Examen general de orina | Orina y otros fluidos | — |
| ena | ENA | LABORATORIO | — | false | — | Autoinmunidad | Fuente ausente: conservar |
| estradiol | Estradiol | LABORATORIO | — | true | Estradiol | Endocrinología y hormonas | — |
| throat_swab | Exudado faríngeo | LABORATORIO | — | true | Faríngeo | Microbiología | — |
| vaginal_swab | Exudado vaginal | LABORATORIO | — | true | Vaginal | Microbiología | — |
| rf | Factor reumatoide | LABORATORIO | — | true | Factor reumatoide | Inmunología | — |
| ferritin | Ferritina | LABORATORIO | — | true | Ferritina | Hematología | — |
| fibrinogen | Fibrinógeno | LABORATORIO | — | true | Fibrinógeno | Coagulación | — |
| alp | Fosfatasa alcalina (ALP) | LABORATORIO | — | true | Fosfatasa alcalina | Química clínica | — |
| phosphorus | Fósforo | LABORATORIO | — | true | Fósforo | Química clínica | — |
| fructosamine | Fructosamina | LABORATORIO | — | false | — | Química clínica | Fuente ausente: conservar |
| fsh | FSH | LABORATORIO | — | true | Hormona folículo estimulante | Endocrinología y hormonas | — |
| ggt | GGT | LABORATORIO | — | true | Gamaglutamil transpeptidasa | Química clínica | — |
| glucose | Glucosa | LABORATORIO | — | true | Glucosa | Química clínica | — |
| hba1c | HbA1c | LABORATORIO | — | true | Hemoglobina glicosilada | Endocrinología y hormonas | — |
| hbsag | HBsAg | LABORATORIO | — | true | Ag. de superficie del VHB | Serologías / Hepatitis | — |
| hdl | HDL | LABORATORIO | — | true | Colesterol HDL | Química clínica | — |
| blood_culture | Hemocultivo | LABORATORIO | — | true | Hemocultivo | Microbiología | — |
| iron | Hierro sérico | LABORATORIO | — | true | Hierro sérico | Hematología | — |
| iga | IgA | LABORATORIO | — | true | IgA | Inmunología | — |
| igg | IgG | LABORATORIO | — | true | IgG | Inmunología | — |
| igm | IgM | LABORATORIO | — | true | IgM | Inmunología | — |
| ldl | LDL | LABORATORIO | — | true | Colesterol LDL | Química clínica | — |
| lh | LH | LABORATORIO | — | true | Hormona luteinizante | Endocrinología y hormonas | — |
| lipase | Lipasa | LABORATORIO | — | true | Lipasa | Química clínica | — |
| magnesium | Magnesio | LABORATORIO | — | true | Magnesio | Química clínica | — |
| microalbumin | Microalbuminuria | LABORATORIO | — | true | Microalbúmina | Orina y otros fluidos | — |
| bun | Nitrógeno ureico (BUN) | LABORATORIO | — | false | — | Química clínica | Fuente ausente: conservar |
| crp_hs | PCR ultrasensible | LABORATORIO | — | true | Proteína C reactiva H.S. | Inmunología | — |
| platelets | Plaquetas | LABORATORIO | — | true | Plaquetas | Hematología | — |
| potassium | Potasio | LABORATORIO | — | true | Potasio | Química clínica | — |
| progesterone | Progesterona | LABORATORIO | — | true | Progesterona | Endocrinología y hormonas | — |
| prolactin | Prolactina | LABORATORIO | — | true | Prolactina | Endocrinología y hormonas | — |
| total_protein | Proteínas totales | LABORATORIO | — | true | Proteínas totales | Química clínica | — |
| fecal_occult_blood | Sangre oculta en heces | LABORATORIO | — | true | Sangre oculta en heces (FOB) | Materia fecal | — |
| sodium | Sodio | LABORATORIO | — | true | Sodio | Química clínica | — |
| ft3 | T3 libre | LABORATORIO | — | true | Triyodotironina libre | Endocrinología y hormonas | — |
| ft4 | T4 libre | LABORATORIO | — | true | Tiroxina libre | Endocrinología y hormonas | — |
| testosterone_free | Testosterona libre | LABORATORIO | — | true | Testosterona libre | Endocrinología y hormonas | — |
| testosterone_total | Testosterona total | LABORATORIO | — | true | Testosterona total | Endocrinología y hormonas | — |
| triglycerides | Triglicéridos | LABORATORIO | — | true | Triglicéridos | Química clínica | — |
| tsh | TSH | LABORATORIO | — | true | Hormona estimulante de tiroides | Endocrinología y hormonas | — |
| aptt | TTPa (aPTT) | LABORATORIO | — | true | Tiempo de tromboplastina parcial activado | Coagulación | — |
| urea | Urea | LABORATORIO | — | true | Urea | Química clínica | — |
| urine_culture | Urocultivo | LABORATORIO | — | true | Urocultivo | Microbiología; Orina y otros fluidos | — |
| hcv_ab | VHC (anticuerpos) | LABORATORIO | — | true | Acs. anti-VHC | Serologías / Hepatitis | — |
| hiv_ag_ac | VIH Ag/Ac | LABORATORIO | — | true | Acs. VIH 4ta. Generación | Inmunología | Alcance/método requiere validación |
| vitamin_b12 | Vitamina B12 | LABORATORIO | — | true | Vitamina B12 | Química clínica | — |
| vitamin_d | Vitamina D | LABORATORIO | — | true | Vitamina D 25 hidroxi | Química clínica | Alcance/método requiere validación |
| esr | VSG | LABORATORIO | — | true | Velocidad de sed. globular; VSG | Hematología; Inmunología | — |
| bhcg | β-hCG | LABORATORIO | — | true | Fracción β cuantificada; Fracción β cuantificada (marcador) | Endocrinología y hormonas; Marcadores tumorales | Alcance/método requiere validación |
| cyto_liquid_based | Citología en base líquida | PATOLOGIA | — | false | — | Otros | Fuente ausente: conservar |
| cyto_pap | Papanicolaou (convencional) | PATOLOGIA | — | false | — | Otros | Fuente ausente: conservar |

## Tabla 3 — perfiles y paneles

| SOURCE_PROFILE_OR_PANEL | TYPE | COMPONENTS | SOURCE_SUPPORT | CURRENT_MXMED_COVERAGE | MISSING_COMPONENTS | NOTES |
| --- | --- | --- | --- | --- | --- | --- |
| QS3 | ORDER_PRESET | Glucosa; Urea; Creatinina | Título impreso; composición parcial o local | Glucosa → glucose; Urea → urea; Creatinina → creatinine | — | Preset conserva IDs individuales |
| QS4 | ORDER_PRESET | QS3; Ácido úrico | Título impreso; composición parcial o local | Ácido úrico → uric_acid | QS3 | Preset conserva IDs individuales |
| QS6 | ORDER_PRESET | QS4; Colesterol; Triglicéridos | Título impreso; composición parcial o local | Colesterol → chol_total; Triglicéridos → triglycerides | QS4 | Preset conserva IDs individuales |
| Electrolitos 3 | ORDER_PRESET | Sodio; Potasio; Cloro | Título impreso; composición parcial o local | Sodio → sodium; Potasio → potassium; Cloro → chloride | — | Preset conserva IDs individuales |
| Electrolitos 6 | ORDER_PRESET | ES3; Calcio; Fósforo; Magnesio | Título impreso; composición parcial o local | Calcio → calcium; Fósforo → phosphorus; Magnesio → magnesium | ES3 | Preset conserva IDs individuales |
| Andrológico | ORDER_PRESET | Hormona luteinizante; Hormona folículo estimulante; Prolactina; Testosterona total; Testosterona libre; Globulina transportadora de hormonas sexuales | Componentes impresos | Hormona luteinizante → lh; Hormona folículo estimulante → fsh; Prolactina → prolactin; Testosterona total → testosterone_total; Testosterona libre → testosterone_free | Globulina transportadora de hormonas sexuales | Preset conserva IDs individuales |
| Celíaco | ORDER_PRESET | Acs. anti-gliadina IgA e IgG; Acs. anti-transglutaminasa IgA e IgG; Acs. anti-endomisio IgA e IgG por I.F.I. | Componentes impresos | — | Acs. anti-gliadina IgA e IgG; Acs. anti-transglutaminasa IgA e IgG; Acs. anti-endomisio IgA e IgG por I.F.I. | Preset conserva IDs individuales |
| Cinética de hierro | ORDER_PRESET | Hierro sérico; Ferritina; Capacidad libre de fijación de hierro; Capacidad de fijación total de hierro (transferrina); % de saturación de transferrina | Componentes impresos | Hierro sérico → iron; Ferritina → ferritin; Capacidad de fijación total de hierro (transferrina) → tibc; % de saturación de transferrina → transferrin_sat | Capacidad libre de fijación de hierro | Preset conserva IDs individuales |
| Ginecológico | ORDER_PRESET | Hormona luteinizante; Hormona folículo estimulante; Estradiol; Progesterona; Testosterona total; Prolactina | Componentes impresos | Hormona luteinizante → lh; Hormona folículo estimulante → fsh; Estradiol → estradiol; Progesterona → progesterone; Testosterona total → testosterone_total; Prolactina → prolactin | — | Preset conserva IDs individuales |
| Hepático | ORDER_PRESET | Aspartato aminotransferasa; Alanino aminotransferasa; Gamaglutamil transpeptidasa; Fosfatasa alcalina; Deshidrogenasa láctica; Bilirrubina directa; Bilirrubina indirecta; Bilirrubina total; Proteínas totales; Albúmina; Globulina; Relación albúmina/globulina | Componentes impresos | Aspartato aminotransferasa → ast; Alanino aminotransferasa → alt; Gamaglutamil transpeptidasa → ggt; Fosfatasa alcalina → alp; Bilirrubina directa → bilirubin_direct; Bilirrubina indirecta → bilirubin_indirect; Bilirrubina total → bilirubin_total; Proteínas totales → total_protein; Albúmina → albumin | Deshidrogenasa láctica; Globulina; Relación albúmina/globulina | Preset conserva IDs individuales |
| Lípidos | ORDER_PRESET | Colesterol total; Colesterol HDL; Colesterol LDL; Triglicéridos | Componentes impresos | Colesterol total → chol_total; Colesterol HDL → hdl; Colesterol LDL → ldl; Triglicéridos → triglycerides | — | Preset conserva IDs individuales |
| Litiasis | UNCERTAIN | Suero; Orina; Análisis de lito renal | Título impreso; composición parcial o local | — | Suero; Orina; Análisis de lito renal | No activar configuración universal sin revisión de composición |
| Reumático | ORDER_PRESET | VSG; Proteína C reactiva H.S.; Ácido úrico; Factor reumatoide; Antiestreptolisinas | Componentes impresos | VSG → esr; Proteína C reactiva H.S. → crp_hs; Ácido úrico → uric_acid; Factor reumatoide → rf | Antiestreptolisinas | Preset conserva IDs individuales |
| Tiroideo | ORDER_PRESET | Hormona estimulante de tiroides; Triyodotironina total; Triyodotironina libre; Tiroxina total; Tiroxina libre; Capacidad de unión de tiroxina | Componentes impresos | Hormona estimulante de tiroides → tsh; Triyodotironina libre → ft3; Tiroxina libre → ft4 | Triyodotironina total; Tiroxina total; Capacidad de unión de tiroxina | Preset conserva IDs individuales |
| Otras pruebas tiroideas | ORDER_PRESET | Acs. anti-peroxidasa; Acs. anti-tiroglobulina; Tiroglobulina | Componentes impresos | Acs. anti-peroxidasa → anti_tpo; Acs. anti-tiroglobulina → anti_tg | Tiroglobulina | Preset conserva IDs individuales |
| TORCH | ORDER_PRESET | CMV IgM/IgG; Toxoplasma gondii IgM/IgG; Rubéola IgM/IgG; Herpes I IgM/IgG; Herpes II IgM/IgG | Componentes impresos | — | CMV IgM/IgG; Toxoplasma gondii IgM/IgG; Rubéola IgM/IgG; Herpes I IgM/IgG; Herpes II IgM/IgG | Preset conserva IDs individuales |
| Perfil virus Epstein-Barr | ORDER_PRESET | VCA IgM; VCA IgG; EBNA IgG | Título impreso; composición parcial o local | — | VCA IgM; VCA IgG; EBNA IgG | Preset conserva IDs individuales |
| Panel infeccioso viral | ORDER_PRESET | Carga viral CMV; EBV; BK | Componentes impresos | — | Carga viral CMV; EBV; BK | Preset conserva IDs individuales |
| Inmunoglobulinas | ORDER_PRESET | IgA; IgG; IgM; IgE | Componentes impresos | IgA → iga; IgG → igg; IgM → igm | IgE | Preset conserva IDs individuales |
| Gasometría venosa | CANONICAL_MULTIPLEX_PANEL | Mediciones ácido-base y gases; componentes no impresos | Título impreso; composición parcial o local | — | Mediciones ácido-base y gases; componentes no impresos | Una orden, varios componentes de resultado |
| Citometría hemática | CANONICAL_MULTIPLEX_PANEL | Fórmula roja; Fórmula blanca; Plaquetas | Componentes impresos | Plaquetas → platelets | Fórmula roja; Fórmula blanca | Una orden, varios componentes de resultado |
| Examen general de orina | CANONICAL_MULTIPLEX_PANEL | Resultados macroquímicos y microscópicos; subcomponentes no impresos | Título impreso; composición parcial o local | — | Resultados macroquímicos y microscópicos; subcomponentes no impresos | Una orden, varios componentes de resultado |
| Coproparasitoscópico | CANONICAL_MULTIPLEX_PANEL | Búsqueda de parásitos; 1–3 muestras como parámetro, no tres órdenes | Título impreso; composición parcial o local | — | Búsqueda de parásitos; 1–3 muestras como parámetro, no tres órdenes | Una orden, varios componentes de resultado |
| Clostridioides difficile toxina A, B y Ag. GDH | CANONICAL_MULTIPLEX_PANEL | Toxina A; Toxina B; antígeno GDH | Componentes impresos | — | Toxina A; Toxina B; antígeno GDH | Una orden, varios componentes de resultado |
| Panel Cryptosporidium y Giardia lamblia | CANONICAL_MULTIPLEX_PANEL | Cryptosporidium; Giardia lamblia | Componentes impresos | — | Cryptosporidium; Giardia lamblia | Una orden, varios componentes de resultado |
| Panel Rotavirus | CANONICAL_MULTIPLEX_PANEL | Rotavirus; Astrovirus; Adenovirus (letra pequeña visible) | Título impreso; composición parcial o local | — | Rotavirus; Astrovirus; Adenovirus (letra pequeña visible) | Una orden, varios componentes de resultado |
| Panel patógenos respiratorios | CANONICAL_MULTIPLEX_PANEL | SARS CoV-2; Influenza A y B; Virus sincitial respiratorio; otros blancos según ensayo | Componentes impresos | — | SARS CoV-2; Influenza A y B; Virus sincitial respiratorio; otros blancos según ensayo | Una orden, varios componentes de resultado |
| Panel gastrointestinal | CANONICAL_MULTIPLEX_PANEL | Patógenos gastrointestinales; lista exacta de pequeños blancos requiere curación | Título impreso; composición parcial o local | — | Patógenos gastrointestinales; lista exacta de pequeños blancos requiere curación | Una orden, varios componentes de resultado |
| Panel virus dengue, zika y chikungunya | UNCERTAIN | Dengue; Zika; Chikungunya | Título impreso; composición parcial o local | — | Dengue; Zika; Chikungunya | No activar configuración universal sin revisión de composición |
| Panel virus papiloma humano (VPH 28) | UNCERTAIN | VPH: 28 tipos declarados; genotipos no impresos claramente | Título impreso; composición parcial o local | — | VPH: 28 tipos declarados; genotipos no impresos claramente | No activar configuración universal sin revisión de composición |
| Panel ETS vaginal / uretral | UNCERTAIN | Patógenos de ETS; lista fina no confiable en imagen | Título impreso; composición parcial o local | — | Patógenos de ETS; lista fina no confiable en imagen | No activar configuración universal sin revisión de composición |
| Panel trombofilia | UNCERTAIN | Variantes genéticas en letra pequeña; no equiparar automáticamente al panel GENETICA existente | Título impreso; composición parcial o local | — | Variantes genéticas en letra pequeña; no equiparar automáticamente al panel GENETICA existente | No activar configuración universal sin revisión de composición |
| Panel vasculitis Athena | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| Panel antinucleares Athena | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| Panel hepatitis autoinmune INMUNOBLOT | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| Panel anti-nucleares INMUNOBLOT | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| Panel miopatías INMUNOBLOT | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| Panel esclerodermia INMUNOBLOT | UNCERTAIN | Composición de proveedor en letra pequeña | Título impreso; composición parcial o local | — | Composición de proveedor en letra pequeña | No activar configuración universal sin revisión de composición |
| ES3 | ORDER_PRESET | Sodio; Potasio; Cloro (remite a Electrolitos 3) | Título impreso; composición parcial o local | Sodio → sodium; Potasio → potassium | Cloro (remite a Electrolitos 3) | Preset conserva IDs individuales |

## Candidatos de alta canónica (no implementados)

| STUDY_TYPE_KEY_PROPOSED | DISPLAY_NAME_ES | ALIASES_PROPOSED | CATEGORY | TECHNICAL_DESCRIPTION | SPECIMEN_OR_REQUEST_PARAMETER | SOURCE_EVIDENCE | GAP_SEVERITY |
| --- | --- | --- | --- | --- | --- | --- | --- |
| lab_2_microglobulina | β 2 microglobulina | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — β 2 microglobulina | IMPORTANT_SPECIALTY |
| lab_acido_valproico | Ácido valproico | — | LABORATORIO | Medición de concentración del fármaco nombrado; registrar matriz y horario de dosis/toma. | Por definir | Fármacos — Ácido valproico | IMPORTANT_SPECIALTY |
| lab_acs_anti_2_glicoproteinas_iga_igg_igm | Acs. anti-β2 glicoproteínas (IgA, IgG, IgM) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-β2 glicoproteínas (IgA, IgG, IgM) | IMPORTANT_SPECIALTY |
| lab_acs_anti_cardiolipinas_iga_igg_igm | Acs. anti-cardiolipinas (IgA, IgG, IgM) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-cardiolipinas (IgA, IgG, IgM) | IMPORTANT_SPECIALTY |
| lab_acs_anti_celulas_parietales_i_f_i_apca | Acs. anti-células parietales I.F.I. (APCA) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-células parietales I.F.I. (APCA) | IMPORTANT_SPECIALTY |
| lab_acs_anti_citomegalovirus | Acs. anti-citomegalovirus | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — Acs. anti-citomegalovirus | ADVANCED_SPECIALTY |
| lab_acs_anti_core_del_vhb_igm | Acs. anti-core del VHB IgM | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Serología de hepatitis — Acs. anti-core del VHB IgM | IMPORTANT_SPECIALTY |
| lab_acs_anti_dna_doble_cadena_i_f_i | Acs. anti-DNA doble cadena I.F.I. | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-DNA doble cadena I.F.I. | IMPORTANT_SPECIALTY |
| lab_acs_anti_endomisio_iga_e_igg_por_i_f_i | Acs. anti-endomisio IgA e IgG por I.F.I. | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Perfiles / celíaco — Acs. anti-endomisio IgA e IgG por I.F.I. | ADVANCED_SPECIALTY |
| lab_acs_anti_herpes_i | Acs. anti-herpes I | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — Acs. anti-herpes I | ADVANCED_SPECIALTY |
| lab_acs_anti_herpes_ii | Acs. anti-herpes II | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — Acs. anti-herpes II | ADVANCED_SPECIALTY |
| lab_acs_anti_mica | Acs. anti-MICA | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / pre-trasplante — Acs. anti-MICA | ADVANCED_SPECIALTY |
| lab_acs_anti_microsomales_de_higado_y_rinon_i_f_i_lkm1 | Acs. anti-microsomales de hígado y riñón I.F.I. (LKM1) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-microsomales de hígado y riñón I.F.I. (LKM1) | IMPORTANT_SPECIALTY |
| lab_acs_anti_mitocondriales_i_f_i_ama | Acs. anti-mitocondriales I.F.I. (AMA) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-mitocondriales I.F.I. (AMA) | IMPORTANT_SPECIALTY |
| lab_acs_anti_musculo_liso_i_f_i_asma | Acs. anti-músculo liso I.F.I. (ASMA) | — | LABORATORIO | Ensayo de autoanticuerpo especificado; conservar isotipo y método cuando se imprimen. | Por definir | Autoinmunidad — Acs. anti-músculo liso I.F.I. (ASMA) | IMPORTANT_SPECIALTY |
| lab_acs_anti_rubeola | Acs. anti-rubeola | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — Acs. anti-rubeola | ADVANCED_SPECIALTY |
| lab_acs_anti_toxoplasma_gondii | Acs. anti-Toxoplasma gondii | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — Acs. anti-Toxoplasma gondii | ADVANCED_SPECIALTY |
| lab_acs_anti_transglutaminasa_iga_e_igg | Acs. anti-transglutaminasa IgA e IgG | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Perfiles / celíaco — Acs. anti-transglutaminasa IgA e IgG | ADVANCED_SPECIALTY |
| lab_acs_helicobacter_pylori_igg | Acs. Helicobacter pylori IgG | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — Acs. Helicobacter pylori IgG | IMPORTANT_SPECIALTY |
| lab_acs_vha_igg | Acs. VHA IgG | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Serología de hepatitis — Acs. VHA IgG | IMPORTANT_SPECIALTY |
| lab_acs_vha_igm | Acs. VHA IgM | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Serología de hepatitis — Acs. VHA IgM | IMPORTANT_SPECIALTY |
| lab_ag_helicobacter_pylori | Ag. Helicobacter pylori | — | LABORATORIO | Ensayo fecal nombrado; conservar patógeno/analito y método de detección. | Heces | Determinaciones en materia fecal — Ag. Helicobacter pylori | CRITICAL_COMMON |
| lab_alfafetoproteina | Alfafetoproteína | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — Alfafetoproteína | IMPORTANT_SPECIALTY |
| lab_amonio | Amonio | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Química clínica — Amonio | CRITICAL_COMMON |
| lab_analisis_de_lito_renal | Análisis de lito renal | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Por definir | Perfiles / litiasis — Análisis de lito renal | ADVANCED_SPECIALTY |
| lab_anti_mulleriana | Anti-Mülleriana | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — Anti-Mülleriana | IMPORTANT_SPECIALTY |
| lab_anticoagulante_lupico | Anticoagulante lúpico | — | LABORATORIO | Ensayo hemostático nombrado; especificar método y unidad de informe. | Por definir | Coagulación — Anticoagulante lúpico | CRITICAL_COMMON |
| lab_antiestreptolisinas | Antiestreptolisinas | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Perfiles / reumático — Antiestreptolisinas | ADVANCED_SPECIALTY |
| lab_antigeno_carcinoembrionario | Antígeno carcinoembrionario | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — Antígeno carcinoembrionario | IMPORTANT_SPECIALTY |
| lab_antigeno_hla_b27 | HLA-B27 (antígeno) | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / otras pruebas — Antígeno HLA-B27 | ADVANCED_SPECIALTY |
| lab_antigeno_prostatico_especifico_total | Antígeno prostático específico total | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — Antígeno prostático específico total | IMPORTANT_SPECIALTY |
| lab_antigeno_prostatico_libre | Antígeno prostático libre | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — Antígeno prostático libre | IMPORTANT_SPECIALTY |
| lab_bcr_abl1_cromosoma_philadelphia | BCR/ABL1 (cromosoma Philadelphia) | — | GENETICA | Detección molecular del blanco indicado; confirmar método, blancos y material. | Sitio/material por definir | Hematología y coagulación / molecular — BCR/ABL1 (cromosoma Philadelphia) | ADVANCED_SPECIALTY |
| lab_brucella_rosa_de_bengala | Brucella (Rosa de Bengala) | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — Brucella (Rosa de Bengala) | IMPORTANT_SPECIALTY |
| lab_ca_125 | CA 125 | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — CA 125 | IMPORTANT_SPECIALTY |
| lab_ca_15_3 | CA 15-3 | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — CA 15-3 | IMPORTANT_SPECIALTY |
| lab_ca_19_9 | CA 19-9 | — | LABORATORIO | Determinación del marcador nombrado; analito independiente del perfil. | Por definir | Marcadores tumorales — CA 19-9 | IMPORTANT_SPECIALTY |
| lab_calcio_en_orina_de_24_h | Calcio en orina de 24 h | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Orina | Determinaciones urinarias — Calcio en orina de 24 h | CRITICAL_COMMON |
| lab_calprotectina_cuantificada | Calprotectina cuantificada | — | LABORATORIO | Ensayo fecal nombrado; conservar patógeno/analito y método de detección. | Heces | Determinaciones en materia fecal — Calprotectina cuantificada | CRITICAL_COMMON |
| lab_capacidad_de_union_de_tiroxina | Capacidad de unión de tiroxina | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Perfiles / tiroideo — Capacidad de unión de tiroxina | ADVANCED_SPECIALTY |
| lab_capacidad_libre_de_fijacion_de_hierro | Capacidad libre de fijación de hierro | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Perfiles / cinética de hierro — Capacidad libre de fijación de hierro | ADVANCED_SPECIALTY |
| lab_carbamazepina | Carbamazepina | — | LABORATORIO | Medición de concentración del fármaco nombrado; registrar matriz y horario de dosis/toma. | Por definir | Fármacos — Carbamazepina | IMPORTANT_SPECIALTY |
| lab_carga_viral_adenovirus | Carga viral adenovirus | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Por definir | Biología molecular / post-trasplante — Carga viral adenovirus | ADVANCED_SPECIALTY |
| lab_carga_viral_citomegalovirus_cmv | Carga viral citomegalovirus (CMV) | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Por definir | Biología molecular / post-trasplante — Carga viral citomegalovirus (CMV) | ADVANCED_SPECIALTY |
| lab_carga_viral_epstein_barr_ebv | Carga viral Epstein-Barr (EBV) | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Por definir | Biología molecular / post-trasplante — Carga viral Epstein-Barr (EBV) | ADVANCED_SPECIALTY |
| lab_carga_viral_hepatitis_b_vhb | Carga viral hepatitis B (VHB) | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Sitio/material por definir | Biología molecular / otras pruebas — Carga viral hepatitis B (VHB) | ADVANCED_SPECIALTY |
| lab_carga_viral_hepatitis_c_vhc | Carga viral hepatitis C (VHC) | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Sitio/material por definir | Biología molecular / otras pruebas — Carga viral hepatitis C (VHC) | ADVANCED_SPECIALTY |
| lab_carga_viral_inmunodeficiencia_vih | Carga viral inmunodeficiencia (VIH) | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Sitio/material por definir | Biología molecular / otras pruebas — Carga viral inmunodeficiencia (VIH) | ADVANCED_SPECIALTY |
| lab_carga_viral_parvovirus_b_19 | Carga viral parvovirus B-19 | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Sitio/material por definir | Biología molecular / otras pruebas — Carga viral parvovirus B-19 | ADVANCED_SPECIALTY |
| lab_carga_viral_virus_bk | Carga viral virus BK | — | LABORATORIO | Cuantificación de ácido nucleico viral del agente indicado; confirmar matriz y plataforma. | Por definir | Biología molecular / post-trasplante — Carga viral virus BK | ADVANCED_SPECIALTY |
| lab_chlamydia_t | Chlamydia T. | — | LABORATORIO | Aislamiento/detección microbiológica en la muestra indicada; precisar método y sitio. | Sitio/material por definir | Microbiología / buscar en — Chlamydia T. | ADVANCED_SPECIALTY |
| lab_ciclosporina | Ciclosporina | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Orina | Biología molecular / post-trasplante — Ciclosporina | ADVANCED_SPECIALTY |
| lab_ck_mb | Creatina cinasa MB | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Química clínica — CK-MB | CRITICAL_COMMON |
| lab_clostridioides_difficile_toxina_a_b_y_ag_gdh | Clostridioides difficile toxina A, B y Ag. GDH | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Heces | Determinaciones en materia fecal — Clostridioides difficile toxina A, B y Ag. GDH | CRITICAL_COMMON |
| lab_colinesterasa | Colinesterasa | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Química clínica — Colinesterasa | CRITICAL_COMMON |
| lab_coombs_directo | Coombs directo | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Hematología — Coombs directo | CRITICAL_COMMON |
| lab_coombs_indirecto | Coombs indirecto | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Hematología — Coombs indirecto | CRITICAL_COMMON |
| lab_coprologico | Coprológico | — | LABORATORIO | Ensayo fecal nombrado; conservar patógeno/analito y método de detección. | Heces | Determinaciones en materia fecal — Coprológico | CRITICAL_COMMON |
| lab_cortisol | Cortisol | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — Cortisol | IMPORTANT_SPECIALTY |
| lab_cortisol_en_orina_de_24_h | Cortisol en orina de 24 h | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Orina | Determinaciones urinarias — Cortisol en orina de 24 h | CRITICAL_COMMON |
| lab_cpk | Creatina cinasa total | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Química clínica — CPK | CRITICAL_COMMON |
| lab_curva_de_resistencia_insulina | Curva de resistencia insulina | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / pruebas dinámicas — Curva de resistencia insulina | ADVANCED_SPECIALTY |
| lab_dehidroepiandrosterona_sulfato | Dehidroepiandrosterona sulfato | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — Dehidroepiandrosterona sulfato | IMPORTANT_SPECIALTY |
| lab_depuracion_de_creatinina_en_orina_de_24_h | Depuración de creatinina en orina de 24 h | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Orina | Determinaciones urinarias — Depuración de creatinina en orina de 24 h | CRITICAL_COMMON |
| lab_deshidrogenasa_lactica | Deshidrogenasa láctica | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Perfiles / hepático — Deshidrogenasa láctica | ADVANCED_SPECIALTY |
| lab_digoxina | Digoxina | — | LABORATORIO | Medición de concentración del fármaco nombrado; registrar matriz y horario de dosis/toma. | Por definir | Fármacos — Digoxina | IMPORTANT_SPECIALTY |
| lab_ebna_igg | EBNA IgG | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — EBNA IgG | IMPORTANT_SPECIALTY |
| lab_eosinofilos_en_moco_nasal | Eosinófilos en moco nasal | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Por definir | Otras pruebas / primera hoja — Eosinófilos en moco nasal | ADVANCED_SPECIALTY |
| lab_espermatobioscopia_directa | Espermatobioscopía directa | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Por definir | Otras pruebas / primera hoja — Espermatobioscopía directa | ADVANCED_SPECIALTY |
| lab_espermo_cultivo | Espermo cultivo | — | LABORATORIO | Aislamiento/detección microbiológica en la muestra indicada; precisar método y sitio. | Sitio/material por definir | Microbiología / cultivos — Espermo cultivo | CRITICAL_COMMON |
| lab_fenitoina | Fenitoína | — | LABORATORIO | Medición de concentración del fármaco nombrado; registrar matriz y horario de dosis/toma. | Por definir | Fármacos — Fenitoína | IMPORTANT_SPECIALTY |
| lab_fenobarbital | Fenobarbital | — | LABORATORIO | Medición de concentración del fármaco nombrado; registrar matriz y horario de dosis/toma. | Por definir | Fármacos — Fenobarbital | IMPORTANT_SPECIALTY |
| lab_frotis_sanguineo | Frotis sanguíneo | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Hematología — Frotis sanguíneo | CRITICAL_COMMON |
| lab_gasometria_venosa | Gasometría venosa | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Por definir | Otras pruebas / primera hoja — Gasometría venosa | ADVANCED_SPECIALTY |
| lab_globulina_transportadora_de_hormonas_sexuales | Globulina transportadora de hormonas sexuales | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Perfiles / andrológico — Globulina transportadora de hormonas sexuales | ADVANCED_SPECIALTY |
| lab_grupo_sanguineo_y_rh | Grupo sanguíneo y Rh | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Hematología — Grupo sanguíneo y Rh | CRITICAL_COMMON |
| lab_h_adrenocorticotropica | Hormona adrenocorticotrópica (ACTH) | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — H. adrenocorticotrópica | IMPORTANT_SPECIALTY |
| lab_h_de_crecimiento | H. de crecimiento | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — H. de crecimiento | IMPORTANT_SPECIALTY |
| lab_homocisteina | Homocisteína | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Otras pruebas / primera hoja — Homocisteína | ADVANCED_SPECIALTY |
| lab_ige | IgE | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — IgE | IMPORTANT_SPECIALTY |
| lab_igg_cmv | IgG (CMV) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgG (CMV) | ADVANCED_SPECIALTY |
| lab_igg_herpes_i | IgG (herpes I) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgG (herpes I) | ADVANCED_SPECIALTY |
| lab_igg_herpes_ii | IgG (herpes II) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgG (herpes II) | ADVANCED_SPECIALTY |
| lab_igg_rubeola | IgG (rubeola) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgG (rubeola) | ADVANCED_SPECIALTY |
| lab_igg_toxoplasma | IgG (Toxoplasma) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgG (Toxoplasma) | ADVANCED_SPECIALTY |
| lab_igm_cmv | IgM (CMV) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgM (CMV) | ADVANCED_SPECIALTY |
| lab_igm_herpes_i | IgM (herpes I) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgM (herpes I) | ADVANCED_SPECIALTY |
| lab_igm_herpes_ii | IgM (herpes II) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgM (herpes II) | ADVANCED_SPECIALTY |
| lab_igm_rubeola | IgM (rubeola) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgM (rubeola) | ADVANCED_SPECIALTY |
| lab_igm_toxoplasma | IgM (Toxoplasma) | — | LABORATORIO | Ensayo inmunológico del antígeno o anticuerpo especificado; conservar clase y agente. | Por definir | Perfiles / TORCH — IgM (Toxoplasma) | ADVANCED_SPECIALTY |
| lab_insulina | Insulina | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — Insulina | IMPORTANT_SPECIALTY |
| lab_litio | Litio | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Química clínica — Litio | CRITICAL_COMMON |
| lab_mutacion_jak2_v617f | Mutación JAK2 V617F | — | GENETICA | Detección molecular del blanco indicado; confirmar método, blancos y material. | Sitio/material por definir | Hematología y coagulación / molecular — Mutación JAK2 V617F | ADVANCED_SPECIALTY |
| lab_mycoplasma | Mycoplasma | — | LABORATORIO | Aislamiento/detección microbiológica en la muestra indicada; precisar método y sitio. | Sitio/material por definir | Microbiología / buscar en — Mycoplasma | ADVANCED_SPECIALTY |
| lab_panel_cryptosporidium_y_giardia_lamblia | Panel Cryptosporidium y Giardia lamblia | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Heces | Determinaciones en materia fecal — Panel Cryptosporidium y Giardia lamblia | CRITICAL_COMMON |
| lab_panel_gastrointestinal | Panel gastrointestinal | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Sitio/material por definir | Biología molecular / otras pruebas — Panel gastrointestinal | ADVANCED_SPECIALTY |
| lab_panel_patogenos_respiratorios | Panel patógenos respiratorios | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Sitio/material por definir | Virus respiratorios PCR — Panel patógenos respiratorios | ADVANCED_SPECIALTY |
| lab_panel_rotavirus | Panel Rotavirus | — | LABORATORIO | Un servicio de laboratorio con varios blancos/componentes reportados; validar composición y método exactos. | Heces | Determinaciones en materia fecal — Panel Rotavirus | CRITICAL_COMMON |
| lab_parathormona_molecula_intacta | Parathormona molécula intacta | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / hormonas — Parathormona molécula intacta | IMPORTANT_SPECIALTY |
| lab_peptido_natriuretico_cerebral_tipo_b | Péptido natriurético cerebral tipo B | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Otras pruebas / primera hoja — Péptido natriurético cerebral tipo B | ADVANCED_SPECIALTY |
| lab_pra_single_antigen | PRA single antigen | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / pre-trasplante — PRA single antigen | ADVANCED_SPECIALTY |
| lab_procalcitonina | Procalcitonina | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Otras pruebas / primera hoja — Procalcitonina | ADVANCED_SPECIALTY |
| lab_proporcion_albumina_creatinina_miccion_unica | Proporción albúmina/creatinina (micción única) | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Por definir | Determinaciones urinarias — Proporción albúmina/creatinina (micción única) | CRITICAL_COMMON |
| lab_proteinas_en_orina | Proteínas en orina | — | LABORATORIO | Determinación en orina/fluido; duración y muestra se registran como parámetros. | Orina | Determinaciones urinarias — Proteínas en orina | CRITICAL_COMMON |
| lab_pruebas_cruzadas_linfocitarias_por_cdc | Pruebas cruzadas linfocitarias por CDC | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / pre-trasplante — Pruebas cruzadas linfocitarias por CDC | ADVANCED_SPECIALTY |
| lab_pruebas_cruzadas_linfocitarias_por_cf | Pruebas cruzadas linfocitarias por CF | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / pre-trasplante — Pruebas cruzadas linfocitarias por CF | ADVANCED_SPECIALTY |
| lab_reticulocitos | Reticulocitos | — | LABORATORIO | Ensayo hematológico nombrado; conservar resultado e interpretación como componentes. | Por definir | Hematología — Reticulocitos | CRITICAL_COMMON |
| lab_sirolimus | Sirolimus | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / post-trasplante — Sirolimus | ADVANCED_SPECIALTY |
| lab_tacrolimus | Tacrolimus | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / post-trasplante — Tacrolimus | ADVANCED_SPECIALTY |
| lab_tamiz_glucosa | Tamiz glucosa | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / pruebas dinámicas — Tamiz glucosa | ADVANCED_SPECIALTY |
| lab_tamiz_glucosa_insulina | Tamiz glucosa / insulina | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Endocrinología / pruebas dinámicas — Tamiz glucosa / insulina | ADVANCED_SPECIALTY |
| lab_tiempo_de_protrombina | Tiempo de protrombina | — | LABORATORIO | Ensayo hemostático nombrado; especificar método y unidad de informe. | Por definir | Coagulación — Tiempo de protrombina | CRITICAL_COMMON |
| lab_tiempo_de_sangrado | Tiempo de sangrado | — | LABORATORIO | Ensayo hemostático nombrado; especificar método y unidad de informe. | Por definir | Coagulación — Tiempo de sangrado | CRITICAL_COMMON |
| lab_tiempo_de_trombina | Tiempo de trombina | — | LABORATORIO | Ensayo hemostático nombrado; especificar método y unidad de informe. | Por definir | Coagulación — Tiempo de trombina | CRITICAL_COMMON |
| lab_tipificacion_hla_clase_i_y_clase_ii | Tipificación HLA clase I y clase II | — | GENETICA | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Biología molecular / pre-trasplante — Tipificación HLA clase I y clase II | ADVANCED_SPECIALTY |
| lab_tiroglobulina | Tiroglobulina | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Perfiles / otras pruebas tiroideas — Tiroglobulina | ADVANCED_SPECIALTY |
| lab_tiroxina_total | Tiroxina total | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Perfiles / tiroideo — Tiroxina total | ADVANCED_SPECIALTY |
| lab_triyodotironina_total | Triyodotironina total | — | LABORATORIO | Determinación hormonal o prueba dinámica nombrada; definir condiciones de toma. | Por definir | Perfiles / tiroideo — Triyodotironina total | ADVANCED_SPECIALTY |
| lab_troponina_i_alta_sensibilidad | Troponina I alta sensibilidad | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Otras pruebas / primera hoja — Troponina I alta sensibilidad | ADVANCED_SPECIALTY |
| lab_ureaplasma | Ureaplasma | — | LABORATORIO | Aislamiento/detección microbiológica en la muestra indicada; precisar método y sitio. | Sitio/material por definir | Microbiología / buscar en — Ureaplasma | ADVANCED_SPECIALTY |
| lab_v_d_r_l | VDRL (prueba no treponémica) | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — V.D.R.L. | IMPORTANT_SPECIALTY |
| lab_vca_igg | VCA IgG | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — VCA IgG | IMPORTANT_SPECIALTY |
| lab_vca_igm | VCA IgM | — | LABORATORIO | Determinación del analito nombrado en química clínica; confirmar matriz y método. | Por definir | Inmunología — VCA IgM | IMPORTANT_SPECIALTY |

## Alias propuestos o por confirmar

| SOURCE_OR_COMMON_ALIAS | CANONICAL_OR_PROPOSED_TARGET | STATUS | RULE |
| --- | --- | --- | --- |
| % de saturación de transferrina | transferrin_sat | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| AFP | lab_alfafetoproteina | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. VIH 4ta. Generación | hiv_ag_ac | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-VHC | hcv_ab | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-antígeno de superficie | anti_hbs | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-citoplasma de neutrófilo I.F.I. (p-ANCA, c-ANCA) | anca | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-core del VHB IgG | anti_hbc | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-nucleares I.F.I. | ana | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-peroxidasa | anti_tpo | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-péptido cíclicos citrulinados | anti_ccp | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Acs. anti-tiroglobulina | anti_tg | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Ag. de superficie del VHB | hbsag | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Alanino aminotransferasa | alt | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Albúmina en orina | albumin | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Aspartato aminotransferasa | ast | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| BH | cbc | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| BNP | lab_peptido_natriuretico_cerebral_tipo_b | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| CBC | cbc | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| CEA | lab_antigeno_carcinoembrionario | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Capacidad de fijación total de hierro (transferrina) | tibc | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Citometría hemática | cbc | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Colesterol | chol_total | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Colesterol HDL | hdl | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Colesterol LDL | ldl | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Complemento C3 | c3 | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Complemento C4 | c4 | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Curva de tolerancia glucosa | ogtt | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| EGO | urinalysis | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Examen general de orina | urinalysis | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Fosfatasa alcalina | alp | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Fracción β cuantificada | bhcg | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Fracción β cuantificada (marcador) | bhcg | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Gamaglutamil transpeptidasa | ggt | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| HOMA-IR | derived_homa_ir | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Hemoglobina glicosilada | hba1c | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Hormona estimulante de tiroides | tsh | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Hormona folículo estimulante | fsh | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Hormona luteinizante | lh | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Microalbúmina | microalbumin | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| PCR ultrasensible | crp_hs | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| PSA total | lab_antigeno_prostatico_especifico_total | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Panel trombofilia | thrombophilia | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Proteína C reactiva H.S. | crp_hs | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| QS3 | preset_qs3 | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| QS4 | preset_qs4 | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| QS6 | preset_qs6 | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Sangre oculta en heces (FOB) | fecal_occult_blood | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| TP | lab_tiempo_de_protrombina | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| TTPa | aptt | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Tiempo de tromboplastina parcial activado | aptt | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Tiroxina libre | ft4 | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Triyodotironina libre | ft3 | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| VDRL | lab_v_d_r_l | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| VSG | esr | PROPOSED_ALIAS_ONLY | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Velocidad de sed. globular | esr | EXISTING_ALIAS | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |
| Vitamina D 25 hidroxi | vitamin_d | NEAR_EQUIVALENT | No crear nueva identidad a partir de abreviatura; validar alcance de método/analito. |


## Riesgos de curación antes de cualquier implementación

1. **Equivalencia parcial:** `vitamin_d` no codifica explícitamente 25-OH; `anti_hbc` no indica IgM/IgG; exudados existentes podrían incluir un método no declarado; `thrombophilia` genético no garantiza los blancos exactos del proveedor. Ninguna coincidencia cercana autoriza fusionar, desactivar o renombrar identidades.
2. **Muestra y método:** títulos “exudado”, “líquidos corporales” y “Mycobacterium tuberculosis en” no precisan el ensayo. El futuro contrato debe almacenar el estudio y la muestra por separado, con requisitos aceptados del prestador.
3. **Listas de paneles diminutas:** los renglones de los paneles comerciales/moleculares no permiten aprobar composición completa. Requieren hoja de alta resolución o ficha técnica antes de un seed.
4. **No hay eliminación:** 17 estudios activos relevantes no aparecen en estas hojas y siguen válidos; el formulario de un laboratorio no es un catálogo universal.
5. **Sin efecto de ejecución:** este documento no modifica fuente clínica, esquema, base, API, interfaz, writers, datos ni comportamiento de OR05/Classification Simulator.
