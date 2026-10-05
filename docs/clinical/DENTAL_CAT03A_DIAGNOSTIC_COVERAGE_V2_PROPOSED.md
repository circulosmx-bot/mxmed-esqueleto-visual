# DENTAL-CAT03A — cobertura diagnóstica dental y maxilofacial V2

**AUDIT_ONLY · PROPOSED · DIRECTOR_REVIEW_REQUIRED.** Fecha de revisión: 2026-10-05. Ninguna fila, alias, navegación, API, escritor, lector, esquema o ruta se modifica en esta fase. Las propuestas requieren ratificación clínica del Director.

## Resumen para decisión

| Dato | Resultado |
|---|---|
| CURRENT_DENTAL_ACTIVE_COUNT | 7: `dental_cbct`, `dental_cephalometric_xray`, `dental_clinical_photographs`, `dental_intraoral_scan`, `dental_panoramic_xray`, `dental_study_model`, `tmj_comparative_xray` |
| DENTAL_CONCEPTS_REVIEWED | 20 filas en la [matriz de implementación](DENTAL_CAT03A_IMPLEMENTATION_MATRIX.csv), incluidos conceptos actuales, candidatos, parámetros, artefactos y límites |
| TRUE_MISSING_P1_COUNT | 2: `dental_periapical_xray`, `dental_bitewing_xray` |
| TRUE_MISSING_P2_COUNT | 2: `dental_occlusal_xray`, `dental_full_periapical_series` |
| PERIAPICAL_DECISION | Nueva identidad canónica; selector `TOOTH_LOCATION`, 1–8 dientes FDI, denticiones permanente/temporal/mixta; una orden puede nombrar varias piezas |
| BITEWING_DECISION | Nueva identidad canónica; `REGION_LOCATION`, posterior derecho/izquierdo/bilateral en `BOTH_ARCHES` |
| OCCLUSAL_DECISION | Nueva identidad canónica P2; `ARCH_LOCATION` maxilar o mandibular |
| FULL_MOUTH_SERIES_DECISION | Servicio diagnóstico orderable como serie, con identidad propuesta propia; exige protocolo de adquisición versionado antes de activarse |
| CBCT_STATUS | Identidad actual suficiente para diente/cuadrante/arco/región; CBCT de ATM requiere extensión puntual de política V2 y `coverage` |
| CEPHALOMETRIC_STATUS | La identidad actual es **lateral**; AP/PA no cabe en ella por alias silencioso. Trazado/análisis es interpretación distinta |
| ORTHODONTIC_RECORDS_DECISION | Preset versionado futuro que compone consumidores válidos; no estudio canónico único |
| INTRAORAL_SCAN_DECISION | Registro digital; conservar fila actual por compatibilidad, no multiplicar identidades de estudio |
| CLINICAL_PHOTOGRAPHY_DECISION | Documentación clínica; conservar fila actual, no crear nuevas identidades diagnósticas |
| DENTAL_MINIMUM_SAFE_IDENTITIES | `dental_periapical_xray`, `dental_bitewing_xray` tras ratificación y política V2 por estudio |
| DENTAL_MINIMUM_SAFE_PRESETS | Ninguno activable en esta fase |
| PARAMETER_CONTRACT_REQUIRED | **Sí**, para serie completa, CBCT de ATM y futuros AP/PA/trazado. Las dos identidades P1 usan la estructura V2 existente y solo requieren políticas restrictivas nuevas. |

La revisión contrasta el catálogo activo de 280 estudios con la [autoridad V2](../../assets/data/clinical/dental-location-authority-v2.json), el [contrato CAT02](DENTAL_CAT02_DENTAL_STUDY_ORDER_V1_IMPLEMENTED.md), las [matrices ODONTOGRAM01](DENTAL_ODONTOGRAM01_CONSUMER_MAPPING.csv), la [implementación ODONTOGRAM02](DENTAL_ODONTOGRAM02_UNIFIED_SELECTOR_V2_IMPLEMENTED.md), la [auditoría COVERAGE01](STUDY_COVERAGE01_GAP_MATRIX.csv), HIER03, SEARCH02, el enrutamiento vigente, la impresión de órdenes y el vínculo de resultados por `order_item_id`. La autoridad de ubicación permanece `contract_version: 2`, FDI/ISO 3950, con `TOOTH_LOCATION`, `QUADRANT_LOCATION`, `ARCH_LOCATION`, `REGION_LOCATION` y `TMJ_LOCATION`. No se propone otro odontograma.

## Evidencia mexicana y alcance

La [matriz de fuentes](DENTAL_CAT03A_MEXICO_SOURCE_MATRIX.csv) registra 16 fuentes directas y su límite. Los centros de Hidalgo, Chiapas, Querétaro, Guanajuato, Estado de México y proveedores regionales coinciden en distinguir periapical, aleta de mordida y oclusal; las series se ofrecen con **14, 16 o 18** imágenes, y algunas incluyen aletas de mordida [S01–S05]. La enseñanza odontológica de UNAM también separa estas tres proyecciones [S06] y describe variación de serie según paciente/protocolo [S16]. Estos listados muestran oferta, no frecuencia de solicitud ni indicación individual para radiación.

## Las siete identidades existentes

| Clave | Decisión de auditoría | Límite |
|---|---|---|
| `dental_cbct` | KEEP + PARAMETER_MAPPING_FIX futura | FDI, cuadrante, arco y región funcionan. Su política no admite `TMJ_REGION`; `coverage` tampoco tiene caso TMJ. No crear CBCT por pieza/FOV. |
| `dental_panoramic_xray` | KEEP; SEARCH_TERM_GAP menor | `Ortopantomografía` ya está como alias, `OPG` en autoridad de búsqueda. `Panorex` solo discovery tras validar uso local; no duplicar panorámica. |
| `dental_cephalometric_xray` | KEEP | El nombre lateral tiene significado histórico. AP/PA exige identidad o contrato de proyección prospectivo; no reetiquetar resultados laterales. |
| `tmj_comparative_xray` | KEEP | `TMJ_LOCATION` y lateralidad ya funcionan; `projection=LATERAL|PA` existente. No confundir con CBCT ni RM. |
| `dental_intraoral_scan` | KEEP + DUPLICATE_RISK | Su producto es registro/mesh digital; la fila y órdenes históricas siguen legibles. Revisión futura del consumidor de registros antes de moverla. |
| `dental_clinical_photographs` | KEEP + DUPLICATE_RISK | Medio de documentación, con alcance intra/extraoral actual. Evitar “estudios” nuevos por fondo o formato. |
| `dental_study_model` | KEEP + DUPLICATE_RISK | Modelo/artefacto físico o digital; no identidad diferente por yeso, STL o impresión. |

La navegación dental actual es **DENTAL_ONLY** para perfiles odontológicos: grupos `Radiología dental 2D`, `Cone Beam / CBCT`, `Registros ortodóncicos` y `Escaneo y modelos` basados en claves activas, con búsqueda dental acotada y salida explícita al catálogo general. HIER03 oculta grupos vacíos y deduplica claves en la lista. El grupo de enrutamiento existente es `DENTAL_DIAGNOSTICS` aun cuando la categoría canónica del estudio sea `IMAGEN` o `DENTAL`.

## Imágenes intraorales: P1 y P2

**Periapical P1.** La identidad distingue la adquisición periapical de la panorámica y la mordida. La política futura permite `SINGLE_TOOTH` o `MULTIPLE_TEETH` FDI, `minSelection=1`, `maxSelection=8`, denticiones `PERMANENT|PRIMARY|MIXED`. Ocho es un límite práctico de producto para pedido localizado, no el número de imágenes garantizado. Una solicitud puede abarcar varios dientes; el proveedor decide exposiciones concretas. El máximo no convierte la solicitud en serie completa. La indicación clínica individual y la selección de dosis siguen en el profesional/proveedor. Evidencia S01, S02, S04, S06.

**Bitewing P1.** La imagen muestra coronas posteriores de ambas arcadas; pedir dientes separados ocultaría la intención. La política futura restringe `REGION` o `BILATERAL_REGION`, `region_key=POSTERIOR`, `arch_key=BOTH_ARCHES`, `side_key=LEFT|RIGHT|BILATERAL`, un valor estructurado por ítem (`minSelection=maxSelection=1`). `PERMANENT|PRIMARY|MIXED` expresa dentición sin inferirla de edad. No se infiere el número de exposiciones del lado pedido. La autoridad V2 ya acepta `POSTERIOR`, `BOTH_ARCHES` y esas lateralidades; no hay nuevo vocabulario de región. Evidencia S01, S04, S06, S09.

**Oclusal P2.** Es proyección intraoral distinta y se vende por arcada [S01, S02, S06]. `ARCH_LOCATION` con `arch_key=MAXILLARY|MANDIBULAR`, una arcada por ítem, denticiones tres modos, `minSelection=maxSelection=1`. Solicitar ambas arcadas requiere dos ítems de la misma clave, no duplicados canónicos maxilar/mandibular.

**Serie completa P2.** Los proveedores la solicitan como producto propio y los protocolos 14/16/18 no son equivalentes; uno puede incorporar aletas [S01–S05, S16]. Se propone **una identidad** `dental_full_periapical_series` para una adquisición de boca completa con un **protocolo versionado** que fija composición y salida. No se modela como 14–18 ítems periapicales ni se rellena el odontograma con 32/52 dientes ficticios. `NO_LOCATION`, `min=max=0`, dentición no inferida; el protocolo debe registrar `series_protocol_version`, variante exacta y si incluye bitewings. Mientras el contrato no esté ratificado, la identidad queda propuesta, no sembrable. La clasificación COVERAGE01 `TRUE_MISSING_PANEL` se refina a `TRUE_MISSING_CANONICAL` con protocolo de adquisición, porque el resultado es un set de imágenes del mismo examen y no un preset de órdenes separadas.

## Extraorales, cefalometría y ATM

La panorámica actual cubre `panorámica`, `ortopantomografía` y OPG sin ubicación FDI. La cefalometría lateral actual cubre solo adquisición lateral. AP y PA figuran por separado en catálogos mexicanos [S02, S05, S09]; antes de añadirlas hay que resolver si una nueva identidad frontal con `projection=AP|PA` evita colisión con radiografía craneofacial general. No se convierte `dental_cephalometric_xray` en genérica porque eso cambiaría el significado de órdenes históricas. La proyección es un parámetro del futuro examen frontal, no un estudio por orientación. `Lateral Full` requiere validación de alcance/protocolo.

El trazado/análisis cefalométrico se oferta separado de la placa [S02, S09–S11] y los métodos Steiner, Ricketts, Downs, McNamara y otros describen un **método de interpretación**, no cinco identidades. Se propone una interpretación futura vinculada a la imagen fuente, con método controlado y resultado PDF/mediciones. Falta autoridad de vínculo, autor e interpretación; por eso `UNCERTAIN` y fuera del mínimo seguro.

La radiografía comparativa de ATM ya existe con vista lateral/PA y lado. CBCT de ATM conserva la identidad CBCT pero requiere `TMJ_REGION` autorizado y `coverage=TMJ` en el contrato, en adaptador y validador, más capacidad explícita del proveedor; hoy el validador responde `DENTAL_V2_MODE_FORBIDDEN` o conflicto de cobertura. La resonancia magnética de ATM es otra modalidad, documentada por un hospital mexicano [S13], y necesita revisión con el catálogo general de imagen antes de crear `mr_tmj`; no se infiere de la radiografía ATM. La página hospitalaria S14 habla de “tomografía ATM” sin probar por sí sola tecnología CBCT. La proyección panorámica no se interpreta automáticamente como estudio ATM dedicado.

Para CBCT común, ubicación expresa diente, cuadrante, arco o región; `coverage` expresa alcance clínico (`LOCALIZED`, `MAXILLARY_ARCH`, `MANDIBULAR_ARCH`, `BOTH_ARCHES`, `MAXILLOFACIAL`). `fov_cm` sigue técnico y opcional. Tamaños comerciales del escáner no generan identidades. Una ubicación TMJ es el único valor aditivo puntual identificado; no altera FDI ni los otros tipos V2. La selección de modalidad se decide clínicamente, no por equivalencia comercial.

## Límite entre estudio, registro y servicio

`dental_intraoral_scan` produce un registro digital y posible malla; `dental_study_model` es artefacto/modelo; `dental_clinical_photographs` es documentación clínica [S02, S07, S08, S11]. Ya son filas activas y el audit no las elimina ni modifica. Recomendar una futura separación de consumidores evita crear otra identidad por cada formato/uso. STL, DICOM, visor, copia impresa o entrega electrónica son medios de salida. Alineadores, férulas y fabricación son procedimientos/servicios, no imágenes diagnósticas. No se obtuvo evidencia suficiente para promover ultrasonido dental u otras técnicas de nicho a P1/P2; quedan `ADVANCED_DEFER`, con revisión de colisión en imagenología general.

Los paquetes de registros ortodóncicos mezclan radiografías, trazado, fotografía y modelos/escaneo en variantes comerciales [S07, S08, S10]. Una composición MXMED podría tener `preset_key=dental_ortho_radiographic_baseline`, `version=1`, componentes diagnósticos `dental_panoramic_xray` + `dental_cephalometric_xray`, consumidor `study_order_composer`, deduplicación por clave dentro del borrador y enrutamiento de cada componente a `DENTAL_DIAGNOSTICS`. El nombre **no** promete un “estudio ortodóncico completo”; foto/modelo/scan requieren consumidores de registros/artefactos propios. Trazado se añade únicamente después del contrato de interpretación. Un futuro preset de registros completos necesita composición entre consumidores, procedencia y revisión de paquetes locales; por ello `DENTAL_MINIMUM_SAFE_PRESETS=NONE`.

## Búsqueda, navegación, resultado y proveedor

Los alias equivalentes propuestos para P1/P2 están en la [matriz](DENTAL_CAT03A_IMPLEMENTATION_MATRIX.csv). `Bitewing`, `aleta de mordida`, `interproximal` y `radiografía interproximal` conducen a una clave; `periapicales` a otra; `oclusal` a otra. `Panorex` se deja discovery solo si se confirma uso local, no alias formal. `ATM` es término de descubrimiento ambiguo entre radiografía, CBCT y RM; mostrar modalidad al elegir. `modelo de estudio` y `escaneo intraoral` llevan a las filas actuales, con la advertencia de límite de dominio. Ninguna clave o alias se cambia aquí.

La navegación futura puede mostrar una hoja **Radiografía intraoral** con periapical/bitewing/oclusal/serie y conservar **Radiografía panorámica**, **Cefalometría**, **CBCT / Tomografía dental** y **ATM** solo si tienen claves activas. Una hoja pequeña lista sus estudios directamente; no inventar un bloque “Más solicitados” sin datos de demanda. Las propuestas nuevas heredan `DENTAL_DIAGNOSTICS`; cada capacidad del proveedor se coteja exactamente (`PERIAPICAL_XRAY`, `BITEWING_XRAY`, `OCCLUSAL_XRAY`, `FULL_MOUTH_INTRAORAL_SERIES`, `PANORAMIC_XRAY`, `CEPHALOMETRIC_LATERAL_XRAY`, `CEPHALOMETRIC_ANALYSIS`, `CBCT_DENTAL`, `CBCT_TMJ_EXPLICIT`). Tener panorámica no prueba CBCT ni viceversa. Tener CBCT dental genérico no prueba protocolo ATM. Sin proveedor compatible, la orden portable sigue válida y el paciente mantiene elección.

La salida V1 para las nuevas imágenes es archivo de imagen o set de imágenes más informe/PDF cuando exista; no exige DICOM. La orden imprimible conserva el texto legible de ubicación/parámetros en su snapshot y la historia de resultado debe conservar `result_source_order_document_id`, UUID, versión exacta y `related_order_item_ids`. La cabecera de linaje sucesor es navegación separada: nunca cubre automáticamente ítems de una versión anterior. Serie y trazado necesitan contratos propios antes de publicar resultados; no se inventa un resultado con ubicaciones o cobertura no confirmadas.

## Contrato faltante y siguiente tarea

1. Para las P1, añadir políticas por clave al mismo V2: modos, dentición, cardinalidad y restricciones de región/arcada indicadas arriba. El esquema `dental_location` ya representa ambos casos. La implementación posterior debe probar rechazo de otros modos y preservar V1 histórico.
2. Para serie completa, definir autoridad versionada del protocolo de adquisición, variantes 14/16/18, inclusión de bitewings, resultado set y cambios futuros sin reinterpretar órdenes emitidas.
3. Para CBCT ATM, permitir `TMJ_REGION` solo en `dental_cbct` y agregar el mapeo `coverage=TMJ` en autoridad/validador/adaptador; revisar si la vista boca abierta/cerrada pertenece a protocolo de proveedor. No modificar las políticas de los siete estudios durante esta auditoría.
4. AP/PA y trazado quedan en compuerta posterior: resolver identidad frontal, vínculo al resultado fuente y método de interpretación antes de habilitarlos.

**PARAMETER_CONTRACT_REQUIRED=true. NEXT_PROPOSED_TASK=`DENTAL-CAT03B-CONTRACT`.** La autoridad de implementación de las dos P1 está descrita, pero la expansión P2 completa no está lista para una siembra directa. Ningún cambio de producto o de contrato se autoriza por este documento.
