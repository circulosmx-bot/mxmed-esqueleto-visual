# LAB-CAT02A — navegación de laboratorio V1 y pruebas comunes

**IMPLEMENTED, alcance mínimo.** Base: `019b3ab1d8633707704c5af3817ad77a59118fdf`. La auditoría [LAB-CAT01](LAB_CAT01_LABORATORY_CATALOG_V1_PROPOSED.md) queda **PARTIALLY_IMPLEMENTED**. Esta fase no implementa el resto de sus propuestas.

## Autoridad y comportamiento

`assets/js/clinical/lab-cat02a-navigation-v1.js` es la autoridad declarativa `LAB_NAVIGATION_PROFILE_VERSION=1`: grupo, rótulo, icono, categoría, claves canónicas y prioridades por especialidad. El servidor y `clinical_study_types` conservan la identidad clínica. Los grupos son solo rutas de descubrimiento y una clave puede figurar en varias rutas. `vis06-modules.js` muestra primero cuatro accesos prioritarios, luego primarios/secundarios compactos y una entrada diferenciada de **Perfiles y paneles**. Solo renderiza grupos con al menos un estudio activo. `Trasplante` tiene cero estudios activos asignados y no aparece; su lugar en configuración permite activarlo cuando se curen estudios apropiados.

Los grupos primarios son Hematología, Química clínica, Coagulación, Endocrinología y hormonas, Inmunología, Microbiología, Biología molecular / PCR y Orina y otros fluidos. Los secundarios son Materia fecal, Autoinmunidad, Marcadores tumorales, Serologías / Hepatitis, Monitoreo de fármacos, Trasplante y Otros. **Perfiles y paneles** es especial y contiene solo ocho estudios canónicos existentes verdaderamente ordenables: `cbc`, `urinalysis`, `stool_ova_parasites`, `thrombophilia`, `hereditary_cancer_germline`, `somatic_tumor_ngs`, `pgx` y `carrier_screening`. Ninguno de los 19 presets propuestos se activa.

Al elegir grupo se reutiliza **TAX03C** con sus claves; la búsqueda permanece dentro del grupo. El modal permite pasar a **Todos los estudios de laboratorio** (las categorías activas LABORATORIO, GENETICA y PATOLOGIA) o al catálogo global. El acceso global original **Todos los estudios** también permanece fuera de Laboratorio. La selección sobrevive a esos cambios de alcance, por lo que se pueden mezclar laboratorios de varios grupos con imagen u otros estudios y mantener el estudio personalizado. La deduplicación sigue usando `study_type_id`; no se crea un selector ni un writer de laboratorio nuevo.

Las trece prioridades son exactamente las de LAB-CAT01: general, medicina interna, endocrinología, hematología, infectología, nefrología, oncología, cardiología, gastroenterología, reumatología, ginecología/obstetricia, pediatría y neumología. El Simulador de Clasificación actualiza la pantalla abierta sin recarga. Ninguna especialidad oculta estudios del catálogo de laboratorio completo ni prohíbe ordenar. DENTAL V1 conserva su ruta exclusiva anterior.

### Conteos activos de grupos después de migración

| Grupo | Estudios activos asignados | Renderizado |
| --- | ---: | --- |
| Hematología | 11 | Sí |
| Química clínica | 34 | Sí |
| Coagulación | 6 | Sí |
| Endocrinología y hormonas | 16 | Sí |
| Inmunología | 10 | Sí |
| Microbiología | 5 | Sí |
| Biología molecular / PCR | 12 | Sí |
| Orina y otros fluidos | 4 | Sí |
| Materia fecal | 5 | Sí |
| Autoinmunidad | 4 | Sí |
| Marcadores tumorales | 4 | Sí |
| Serologías / Hepatitis | 4 | Sí |
| Monitoreo de fármacos | 1 | Sí |
| Trasplante | 0 | No |
| Otros | 2 | Sí |
| Perfiles y paneles | 8 | Sí |

Las sumas por grupo exceden el número de identidades porque se permite pertenencia múltiple. El escape **Todos los estudios de laboratorio** contiene **107 identidades activas**: 93 LABORATORIO, 12 GENETICA y 2 PATOLOGIA. El catálogo global contiene 202 estudios activos.

### Mapeo de rutas a claves canónicas

Las claves son referencias a `clinical_study_types`, no identidades nuevas para el grupo. La configuración versionada anterior es la autoridad ejecutable.

| Ruta | Categoría → claves canónicas |
| --- | --- |
| Hematología | LABORATORIO → `cbc`, `esr`, `ferritin`, `iron`, `lab_coombs_directo`, `lab_coombs_indirecto`, `lab_frotis_sanguineo`, `lab_reticulocitos`, `platelets`, `tibc`, `transferrin_sat` |
| Química clínica | LABORATORIO → `albumin`, `alp`, `alt`, `amylase`, `ast`, `bilirubin_direct`, `bilirubin_indirect`, `bilirubin_total`, `bun`, `calcium`, `chloride`, `chol_total`, `creatinine`, `folate`, `fructosamine`, `ggt`, `glucose`, `hdl`, `lab_amonio`, `lab_ck_mb`, `lab_cpk`, `ldl`, `lipase`, `magnesium`, `non_hdl`, `phosphorus`, `potassium`, `sodium`, `total_protein`, `triglycerides`, `urea`, `uric_acid`, `vitamin_b12`, `vitamin_d` |
| Coagulación | GENETICA → `thrombophilia`<br>LABORATORIO → `aptt`, `d_dimer`, `fibrinogen`, `lab_tiempo_de_protrombina`, `lab_tiempo_de_trombina` |
| Endocrinología y hormonas | GENETICA → `nipt`<br>LABORATORIO → `anti_tg`, `anti_tpo`, `bhcg`, `estradiol`, `fsh`, `ft3`, `ft4`, `hba1c`, `lh`, `ogtt`, `progesterone`, `prolactin`, `testosterone_free`, `testosterone_total`, `tsh` |
| Inmunología | LABORATORIO → `c3`, `c4`, `crp_hs`, `esr`, `hiv_ag_ac`, `iga`, `igg`, `igm`, `rf`, `uric_acid` |
| Microbiología | LABORATORIO → `blood_culture`, `stool_culture`, `throat_swab`, `urine_culture`, `vaginal_swab` |
| Biología molecular / PCR | GENETICA → `brca1_2`, `carrier_screening`, `cma_microarray`, `hereditary_cancer_germline`, `karyotype`, `lynch`, `nipt`, `pgx`, `somatic_tumor_ngs`, `thrombophilia`, `wes`, `wgs` |
| Orina y otros fluidos | LABORATORIO → `albumin`, `microalbumin`, `urinalysis`, `urine_culture` |
| Materia fecal | LABORATORIO → `fecal_occult_blood`, `lab_ag_helicobacter_pylori`, `lab_calprotectina_cuantificada`, `stool_culture`, `stool_ova_parasites` |
| Autoinmunidad | LABORATORIO → `ana`, `anca`, `anti_ccp`, `ena` |
| Marcadores tumorales | GENETICA → `brca1_2`, `hereditary_cancer_germline`, `somatic_tumor_ngs`<br>LABORATORIO → `bhcg` |
| Serologías / Hepatitis | LABORATORIO → `anti_hbc`, `anti_hbs`, `hbsag`, `hcv_ab` |
| Monitoreo de fármacos | LABORATORIO → `lab_litio` |
| Trasplante | Sin claves activas curadas |
| Otros | PATOLOGIA → `cyto_liquid_based`, `cyto_pap` |
| Perfiles y paneles | GENETICA → `carrier_screening`, `hereditary_cancer_germline`, `pgx`, `somatic_tumor_ngs`, `thrombophilia`<br>LABORATORIO → `cbc`, `stool_ova_parasites`, `urinalysis` |

**Todos los estudios de laboratorio** consulta todas las identidades activas de `LABORATORIO`, `GENETICA` y `PATOLOGIA` sin filtrar por las listas anteriores.

## Las 26 brechas comunes de LAB-CAT01: decisión antes de alta

Solo se migran las filas `SAFE_UNAMBIGUOUS`. Las claves elegidas proceden del inventario LAB-CAT01; el rótulo se puede hacer más explícito sin alterar la identidad.

| Fuente | Decisión | Clave / motivo |
| --- | --- | --- |
| Coombs directo | SAFE_UNAMBIGUOUS | `lab_coombs_directo` |
| Coombs indirecto | SAFE_UNAMBIGUOUS | `lab_coombs_indirecto` |
| Grupo sanguíneo y Rh | TRUE_MULTIPLEX | Diferentes componentes de tipificación; curar orden y resultados. |
| Frotis sanguíneo | SAFE_UNAMBIGUOUS | `lab_frotis_sanguineo` |
| Reticulocitos | SAFE_UNAMBIGUOUS | `lab_reticulocitos` |
| Anticoagulante lúpico | UNCERTAIN | Algoritmo/método y componentes no definidos por la hoja. |
| Tiempo de sangrado | UNCERTAIN | Método de realización no especificado; no se infiere servicio universal. |
| Tiempo de protrombina | SAFE_UNAMBIGUOUS | `lab_tiempo_de_protrombina` |
| Tiempo de trombina | SAFE_UNAMBIGUOUS | `lab_tiempo_de_trombina` |
| Amonio | SAFE_UNAMBIGUOUS | `lab_amonio`; requisitos de toma del prestador quedan fuera del catálogo universal. |
| Litio | SAFE_UNAMBIGUOUS | `lab_litio`; hora de última dosis/toma se difiere al contrato de recolección. |
| Colinesterasa | UNCERTAIN | Plasma/suero y eritrocitos no son intercambiables sin precisar método. |
| CPK | SAFE_UNAMBIGUOUS | `lab_cpk`, creatina cinasa total. |
| CK-MB | SAFE_UNAMBIGUOUS | `lab_ck_mb`, isoenzima MB. |
| Proporción albúmina/creatinina (micción única) | REQUIRES_PARAMETER_MODEL | Conservar toma puntual y relación como orden/resultado explícitos. |
| Cortisol en orina de 24 h | REQUIRES_PARAMETER_MODEL | Duración y muestra indispensables. |
| Calcio en orina de 24 h | REQUIRES_PARAMETER_MODEL | Duración y muestra indispensables. |
| Depuración de creatinina en orina de 24 h | REQUIRES_PARAMETER_MODEL | Toma y cálculo no se simplifican a creatinina aislada. |
| Proteínas en orina | REQUIRES_PARAMETER_MODEL | Hoja distingue micción única y 24 h. |
| Coprológico | UNCERTAIN | Composición del examen varía por prestador. |
| Calprotectina cuantificada | SAFE_UNAMBIGUOUS | `lab_calprotectina_cuantificada`, muestra fecal explícita. |
| Ag. Helicobacter pylori | SAFE_UNAMBIGUOUS | `lab_ag_helicobacter_pylori`, antígeno fecal explícito. |
| Clostridioides difficile toxina A, B y Ag. GDH | TRUE_MULTIPLEX | Confirmar ensayo/algoritmo y componentes. |
| Panel Cryptosporidium y Giardia lamblia | TRUE_MULTIPLEX | Confirmar método y servicio único. |
| Panel Rotavirus | TRUE_MULTIPLEX | Hoja incluye otros blancos; confirmar ensayo. |
| Espermo cultivo | REQUIRES_PARAMETER_MODEL | El modelo híbrido de cultivos necesita muestra/sitio validados. |

**Resumen:** 12 altas, 14 diferidos. De los seis paneles nuevos de LAB-CAT01 (gasometría venosa, C. difficile A/B/GDH, Cryptosporidium/Giardia, Rotavirus/Astrovirus/Adenovirus, panel respiratorio y panel gastrointestinal), **cero se migran**. Los cuatro paneles comunes aparecen en la tabla; gasometría venosa, respiratorio y gastrointestinal se difieren por composición, método o muestra pendiente. Tampoco se migran en bloque las 37 brechas importantes ni las 60 avanzadas; las 12 fuentes inciertas siguen sin implementación.

## Alias aplicados

La migración `2026_10_02_18_lab_cat02a_common_catalog.sql` añade 30 alias de la hoja a identidades preexistentes y 19 alias a las 12 identidades nuevas. Cada alias se cotejó con todos los nombres/alias activos antes de la migración; no hay colisiones de propietario canónico. `aliases_json` es búsqueda/presentación, no clave. La migración es idempotente; ejecutarla dos veces en base desechable conservó 202 activos y no repitió alias.

**Alias añadidos a existentes:** `% de saturación de transferrina`→`transferrin_sat`; `Acs. anti-VHC`→`hcv_ab`; `Acs. anti-antígeno de superficie`→`anti_hbs`; `Acs. anti-peroxidasa`→`anti_tpo`; `Acs. anti-péptido cíclicos citrulinados`→`anti_ccp`; `Acs. anti-tiroglobulina`→`anti_tg`; `Ag. de superficie del VHB`→`hbsag`; `Alanino aminotransferasa`→`alt`; `Aspartato aminotransferasa`→`ast`; `Capacidad de fijación total de hierro (transferrina)`→`tibc`; `Citometría hemática`→`cbc`; `Colesterol HDL`→`hdl`; `Colesterol LDL`→`ldl`; `Complemento C3`→`c3`; `Complemento C4`→`c4`; `Curva de tolerancia glucosa`→`ogtt`; `Examen general de orina`→`urinalysis`; `Fosfatasa alcalina`→`alp`; `Gamaglutamil transpeptidasa`→`ggt`; `Hemoglobina glicosilada`→`hba1c`; `Hormona estimulante de tiroides`→`tsh`; `Hormona folículo estimulante`→`fsh`; `Hormona luteinizante`→`lh`; `Microalbúmina`→`microalbumin`; `Proteína C reactiva H.S.`→`crp_hs`; `Sangre oculta en heces (FOB)`→`fecal_occult_blood`; `Tiempo de tromboplastina parcial activado`→`aptt`; `Tiroxina libre`→`ft4`; `Triyodotironina libre`→`ft3`; `Velocidad de sed. globular`→`esr`.

**Alias de las altas:** se registran junto con su `INSERT` en la migración; incluyen `DAT`, `IAT`, `TP`, `PT`, `TT`, `CPK`, `CK total`, `CK-MB` y los nombres largos de cada prueba. `Acs. VIH 4ta. Generación`, `anti-HBc IgM`, vitamina D 25-OH y cualquier coincidencia cercana de LAB-CAT01 quedan sin alias nuevo hasta resolver alcance.

## Fronteras conservadas y deuda LAB-CAT02B

Sin cambios de esquema clínico, producto, `clinical_documents`, `order_items`, escritores, lector de órdenes/resultados, cobertura, OR03, OR04, salida portátil o integración B3. La migración solo añade catálogo y alias. Se preservan `urine_culture`, `stool_culture` y `blood_culture`; no se sustituyen por un cultivo genérico. Los exudados adicionales permanecen diferidos hasta contratar estudio + método + sitio/muestra. No se crean parámetros de muestra ni recolección en CAT02A.

**LAB-CAT02B:** 19 presets versionados y composición curada; paneles multiplex nuevos, incluidos los seis identificados; 12 parámetros de muestra/sitio y 24 parámetros de toma; cobertura de brechas especializadas y ambiguas; resultados por analito y referencias; integración del laboratorio prestador solo cuando B3 se reanude por decisión separada.

## Verificación

- Migración dos veces en base desechable: 202 activos, 12 nuevos, sin aliases duplicados; aplicada después al catálogo de revisión (190→202 activos).
- `lab_cat02a_browser.py` contra archivos servidos por `http://127.0.0.1:18148/`: 13 prioridades y Simulador en vivo; 15 grupos renderizados, ninguno vacío; búsqueda dentro del grupo, laboratorio completo, catálogo global, orden mixta y personalizada; 1440×900, 1366×768 y 390×844 sin desborde horizontal; Ortodoncia, Implantología y Endodoncia sin regresión.
- `lab_cat02a_disposable_gate.sh`: orden real emitida con laboratorio nuevo, laboratorio existente, imagen y estudio personalizado; cuatro `order_item_id` distintos; modelo portable, HTML y PDF autenticados; no sesión y doctor ajeno rechazados.
- API autenticada del runtime: búsquedas representativas de cinco alias resuelven a una sola identidad; 202 claves canónicas únicas y ningún nombre/alias normalizado pertenece a dos claves.

El runtime de Dirección usa el worktree clínico y se mantiene en `http://127.0.0.1:18148/`. Su cabecera de procedencia configurada externamente puede mostrar un SHA anterior hasta que se reinicie el proceso; los archivos HTTP de LAB-CAT02A y la lectura de base verificados arriba sí corresponden al checkout actual.
