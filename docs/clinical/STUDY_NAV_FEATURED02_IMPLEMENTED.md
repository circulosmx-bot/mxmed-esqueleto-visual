# STUDY-NAV-FEATURED02 — navegación y selección compartidas

Implementado sobre `90c1e950a49ee170404ac32557bf7e752ebda897` (2026-10-04). La imagen integrada del Director fijó la intención visual de familia y selector; el shell existente y las matrices FEATURED01 fijaron las rutas, identidades y textos. La configuración operativa versionada es [`study_featured_navigation_v1.json`](../../modules/clinical/catalog/study_featured_navigation_v1.json), derivada de las [49 hojas](STUDY_NAV_FEATURED01_LEAF_MATRIX.csv) y las [152 membresías](STUDY_NAV_FEATURED01_GROUP_MEMBERSHIP_MATRIX.csv). No se cambió el catálogo canónico, la búsqueda, el routing de órdenes ni sus escritores.

## Contrato visible

- Las cinco pantallas de familias médicas y tres intermedias conservan volver, icono y título, seguidos directamente por sus tarjetas. Se ocultó la ruta redundante “Tipos de estudio / …”.
- Las 49 hojas finales presentan el encabezado semántico `{FAMILIA RAÍZ} / {HOJA FINAL}` de la matriz. El nivel intermedio se omite cuando no resuelve ninguna ambigüedad. El encabezado sustituye “Seleccionar estudios”; se eliminaron “CATÁLOGO DE ESTUDIOS”, el subtítulo de hoja repetido y el conteo de estudios en reposo. La búsqueda conserva su estado accesible cuando el usuario escribe y mantiene “Buscar en todo el catálogo”.
- Las 38 hojas con hasta seis estudios activos muestran todos directamente, sin “COMUNES” ni acordeón. Al final aparece el vínculo existente “¿No encuentras el estudio? Agregar estudio no catalogado”.
- Las 11 hojas grandes usan “COMUNES” solo si hay candidatos con confianza suficiente y un “Ver catálogo completo” inicialmente cerrado. Sus grupos usan exclusivamente claves canónicas declaradas; los grupos sin estudios activos no se renderizan. El vínculo de estudio no catalogado aparece al final del catálogo expandido. Al expandirlo se oculta temporalmente la lista de “COMUNES” para evitar filas canónicas visibles duplicadas; los estudios siguen en sus grupos. Un grupo abierto a la vez conserva la regla aceptada de orina.
- El panel “ÓRDENES EN PREPARACIÓN”, deduplicación por identidad, borrador, navegación interna, revisión, parámetros dentales y de muestra, y emisión conservan sus contratos previos.

## Hojas grandes: destacados y acordeones exactos

| Hoja | COMUNES, en orden | Grupos del catálogo completo, en orden |
| --- | --- | --- |
| `chemistry` | `glucose`, `creatinine`, `urea`, `chol_total`, `hdl`, `triglycerides` | Metabolismo y función renal; Electrolitos y minerales; Lípidos; Función hepática y bilirrubinas; Enzimas pancreáticas y musculares; Vitaminas y micronutrientes |
| `endocrine` | `tsh`, `ft4`, `hba1c` | Tiroides; Metabolismo de glucosa; Hormonas reproductivas y embarazo |
| `hematology` | `cbc`, `ferritin`, `iron` | Biometría y recuentos; Hierro y reservas; Pruebas de antiglobulina |
| `microbiology` | `urine_culture`, `blood_culture`, `throat_swab`, `stool_culture` | Cultivos por muestra; Infección de LCR |
| `panels` | ninguno | Paneles generales; Paneles genéticos y moleculares |
| `urine` | `urinalysis`, `microalbumin`, `urine_culture`, `urine_albumin_creatinine_panel`, `urine_protein_creatinine_panel`, `urine_osmolality` | Estudios generales y renales; Electrolitos y minerales urinarios; Microbiología urinaria; Semen; Líquido cefalorraquídeo (LCR); Líquidos serosos; Líquido sinovial |
| `radiography` | ninguno | Radiografías convencionales; Fluoroscopía |
| `cardiovascular` | `ecg_12lead`, `holter`, `abpm_mapa`, `stress_test` | Electrocardiografía; Monitoreo ambulatorio; Esfuerzo y respuesta autonómica; Evaluación vascular funcional |
| `neurophysiology` | ninguno | Electroencefalografía; Electrodiagnóstico neuromuscular; Potenciales evocados |
| `pulmonary` | `spirometry`, `full_pft`, `dlco` | Espirometría y función integral; Ejercicio y capacidad funcional; Monitoreo respiratorio |
| `digestive_endoscopy` | ninguno | Endoscopia alta y biliopancreática; Intestino delgado y cápsula; Endoscopia baja y anorectal |

Son **29** relaciones destacadas, **38** grupos y **152** membresías exactas. `radiography`, `neurophysiology` y `digestive_endoscopy` conservan sus candidatos LOW solo en la matriz de auditoría, sin destacarlos. `panels` mezcla estudios generales, genéticos y moleculares; sus siete identidades quedan accesibles en dos grupos, sin inventar un orden de frecuencia. FEATURED01 queda **PARTIALLY_IMPLEMENTED** por estas omisiones deliberadas. La pertenencia completa por clave se consulta en el JSON versionado y la matriz de miembros.

## Verificación

- [`study_nav_featured02_browser.py`](../../modules/clinical/qa/study_nav_featured02_browser.py) coteja las 49 hojas con las matrices y con los **232** estudios activos de la BD de revisión mediante consultas de solo lectura. Abre las 49 hojas a 1440×900, siete rutas críticas a 1366×768 y cinco a 390×844 en Chromium con API clínica de escritura bloqueada. Comprueba encabezados, estudios directos, destacados, grupos exactos, ausencia de grupos vacíos y desbordamiento, fallback y selección.
- [`study_nav_featured02_live.py`](../../modules/clinical/qa/study_nav_featured02_live.py) verifica Química Clínica en el runtime existente `http://127.0.0.1:18148/`, con la sesión local autorizada y escrituras API bloqueadas: 1440×900, 1366×768, sidebar compacta/expandida y 390×844. Capturas temporales en `/tmp/study_nav_featured02_live_*` muestran la jerarquía y los controles sin copiar el shell ilustrativo.
- `ord_comp01_browser.py` vuelve a cubrir borradores, teclado, FDI dental, datos de muestra, idempotencia y modal; su espera de visibilidad se actualizó para el acordeón nativo. JavaScript pasó `node --check`. No se efectuaron escrituras clínicas.

La QA visual comprobó que el título largo se mantiene legible, el selector móvil se apila y no aparece desplazamiento horizontal. El contenido puede requerir desplazamiento vertical normal en móvil; el contrato no fija altura de tarjetas ni oculta estudios.
