# DENTAL-CAT03B — contrato de política y protocolo dental

**IMPLEMENTED · CONTRACT_ONLY · NO_NEW_CANONICAL_STUDIES.** Autoridad de producto: [DENTAL-CAT03A](DENTAL_CAT03A_DIAGNOSTIC_COVERAGE_V2_PROPOSED.md). La autoridad FDI y el selector dental siguen en `DENTAL_LOCATION_V2`, versión **2**. El catálogo activo conserva **280** estudios, de los que **7** son las identidades dentales auditadas. Las cuatro claves futuras no tienen filas, alias ni navegación activos.

## Autoridades y vigencia

| Archivo | Versión | Uso |
|---|---:|---|
| [`dental-location-authority-v2.json`](../../assets/data/clinical/dental-location-authority-v2.json) | 2 | FDI, tipos de ubicación y política activa de los siete estudios; CBCT añade `TMJ_REGION`. |
| [`dental-study-location-policies-v1.json`](../../assets/data/clinical/dental-study-location-policies-v1.json) | 1 | Restricciones por clave para periapical, bitewing, oclusal y serie futura; sobreposición para CBCT existente. |
| [`dental-acquisition-protocols-v1.json`](../../assets/data/clinical/dental-acquisition-protocols-v1.json) | 1 | Variantes de intención de adquisición de la serie completa: 14, 16 y 18 imágenes nominales. |

El servidor carga la política nueva por clave exacta y, para los estudios actuales sin sobreposición, usa la política V2 previa. Una clave futura solo puede pasar por el escritor si existe como fila **activa** del catálogo; CAT03B no las creó. No se agregó Dental Location V3 ni se reescribió una orden V1/V2 histórica. `dental_study_policy_version` puede enviarse como 1 para comprobar la versión; si se omite, el servidor la fija al emitir un ítem V2 cubierto por la política. Una versión distinta se rechaza. El cliente no aporta etiquetas ni semántica del protocolo; se derivan de la autoridad.

## Políticas por estudio

| Clave | Ubicación V2 | Dentición | Cardinalidad | Rechazos característicos |
|---|---|---|---|---|
| `dental_periapical_xray` | `TOOTH_LOCATION`, `SINGLE_TOOTH|MULTIPLE_TEETH` | `PERMANENT|PRIMARY|MIXED` | 1–8 códigos FDI distintos | Cero, nueve, código ajeno a dentición o otro tipo de ubicación. Varias piezas son intención anatómica, **no** promesa de una imagen por pieza. |
| `dental_bitewing_xray` | `REGION_LOCATION`, `REGION|BILATERAL_REGION` | `PERMANENT|PRIMARY|MIXED` | Una región por ítem | Solo `region_key=POSTERIOR`, `arch_key=BOTH_ARCHES`, `side_key=LEFT|RIGHT|BILATERAL`. El lado bilateral exige `BILATERAL_REGION`; no permite dientes, anterior ni una sola arcada. |
| `dental_occlusal_xray` | `ARCH_LOCATION`, `ARCH` | `PERMANENT|PRIMARY|MIXED` | Una arcada por ítem | Solo `MAXILLARY|MANDIBULAR`; `BOTH_ARCHES` se rechaza. Solicitar las dos arcadas requiere dos ítems, sin claves canónicas duplicadas. |
| `dental_full_periapical_series` | `NO_LOCATION` | Ver protocolo abajo | 0 | Se rechaza `dental_location`; se requiere un protocolo válido. No se eligen dientes de uno en uno. |
| `dental_cbct` existente | Diente, cuadrante, arcada, región o **ATM** | Las reglas FDI existentes se preservan | Según ubicación V2 | ATM usa únicamente `TMJ_LOCATION`/`TMJ_REGION`, `tmj_side=LEFT|RIGHT|BILATERAL`, `coverage=TMJ`. FDI, arco o cuadrante no caben en ese objeto discriminado. |

La representación bitewing ya existe en V2: `region_key=POSTERIOR`, `arch_key=BOTH_ARCHES` y lado explícito. No se crearon claves `POSTERIOR_RIGHT` ni un nuevo tipo de ubicación. El validador del servidor aplica campos obligatorios, valores permitidos, exclusión de campos extra y consonancia entre tipo/modo. La validación genérica FDI existente sigue comprobando 32 dientes permanentes, 20 temporales, selección mixta, duplicados y orden canónico de códigos.

El compositor actual deduplica estudios por clave canónica en un lote. Por ello, la solicitud oclusal de ambas arcadas como dos ítems **aún no puede emitirse en el mismo lote**. CAT03C debe resolver la deduplicación por `(clave, arcada)` o un flujo explícito de órdenes separadas antes de exponer esa combinación; CAT03B no cambia la regla general de duplicados ni habilita la clave futura.

## Protocolo de serie completa

La entrada futura es `dental_acquisition_protocol={contract_version:1,protocol_key:FULL_MOUTH_14|FULL_MOUTH_16|FULL_MOUTH_18,dentition_mode:PERMANENT}`. El servidor la valida contra la autoridad y copia al snapshot `protocol_key`, versión, etiqueta en español, cantidad nominal, alcance `FULL_MOUTH`, composición, inclusión no garantizada de aletas, semántica de cantidad y requisito de confirmación del proveedor. No se acepta un entero libre como protocolo, ni 13/15/17, ni un campo desconocido.

| Variante | Cantidad nominal | Evidencia CAT03A | Composición |
|---|---:|---|---|
| `FULL_MOUTH_14` | 14 | S01, S04, S05, S16 | Protocolo concreto del proveedor por confirmar. |
| `FULL_MOUTH_16` | 16 | S02, S03, S05 | Protocolo concreto del proveedor por confirmar; S03 menciona aletas, pero no demuestra que todo servicio de 16 las incluya. |
| `FULL_MOUTH_18` | 18 | S01, S04, S05 | Protocolo concreto del proveedor por confirmar. |

Las tres variantes quedan restringidas a `PERMANENT` en este contrato inicial. La fuente S16 describe 14 imágenes en adultos y una serie pediátrica diferente de 10; no hay autoridad suficiente para declarar equivalencia 14/16/18 en dentición temporal o mixta. Esta restricción es de **protocolo**, no una inferencia de dentición por edad. El número representa la adquisición **solicitada**; no es el conteo final de imágenes útiles. La presencia de bitewings es `NOT_GUARANTEED`. Cualquier variante futura que garantice una composición concreta necesitará clave/versión propia y evidencia, sin reinterpretar estos snapshots.

Para interoperabilidad futura, el proveedor debe coincidir con la clave canónica `dental_full_periapical_series` **y** anunciar compatibilidad con la variante nominal seleccionada; además debe confirmar su composición, incluida la presencia de aletas, antes de sugerirlo como capaz de cumplir la solicitud. Ninguna capacidad de proveedor se activó en CAT03B. Para CBCT de ATM, la capacidad futura debe declarar CBCT **y** ATM; CBCT dental genérico o panorámica no lo implican. La orden sigue portable si no hay proveedor coincidente.

## Snapshots, resultado e impresión

El escritor conserva `dental_location` V2 y `dental_location_label` en el `order_item`. Para un ítem regido por la nueva política V2 añade `dental_location_authority_version=2` y `dental_study_policy_version=1`. La serie, aunque carece de ubicación, guarda ambas versiones como contexto del contrato y añade `dental_acquisition_protocol` y su `dental_acquisition_protocol_label`. Una orden CBCT V1 nueva o histórica conserva su objeto V1, sin etiqueta de autoridad V2 inventada. La sustitución con el mismo `order_item_id` exige igualdad exacta de estos campos y conserva el snapshot emitido.

La impresión HTML/PDF de la orden portátil lee **las etiquetas guardadas**. La ubicación ATM muestra `Región: ATM bilateral` (o derecha/izquierda); la serie muestra `Protocolo: Serie de 18 imágenes intraorales` según el ítem. No consulta la autoridad viva para recalcular una orden anterior ni imprime claves internas. Se mantiene el resultado canónico vinculado a la versión exacta de orden y a `related_order_item_ids`: la serie podrá tener varios archivos/imágenes bajo **un** ítem, sin crear un ítem por imagen del protocolo. No se cambió la autoridad del resultado ni la lectura de versiones sucesoras.

## Contrato para DENTAL-CAT03C

- Periapical: “Seleccione la pieza o piezas”; `DentalOdontogram V2` solo en modo de dientes, resumen de FDI y límite 8.
- Bitewing: opciones compactas “Posterior derecha”, “Posterior izquierda”, “Bilateral”, usando región V2; no obligar a marcar cada diente. La UI debe incluir dentición explícita para cumplir el contrato.
- Oclusal: “Seleccione la arcada”; Maxilar o Mandíbula, con dentición explícita.
- Serie: selector de protocolo 14/16/18 con etiqueta y aviso de confirmación de composición por proveedor; sin odontograma completo.
- CBCT: la UI existente ya ofrece modo ATM derecha/izquierda/bilateral mediante el selector V2 y calcula `coverage=TMJ`; el FOV numérico sigue **opcional**.

CAT03C activará las cuatro filas, búsqueda, navegación y rutas `DENTAL_DIAGNOSTICS` solo después de aprobación. Las hojas vacías no se muestran ahora. Un bloque de “comunes” requeriría evidencia de demanda. Ninguna capacidad de proveedor ni resultado específico se creó aquí.

## Verificación

- `php modules/clinical/qa/dental_cat03b_contract_gate.php`: positivos y rechazos para periapical 1/8/mixto, bitewing por lado, oclusal por arcada, serie 14/16/18, CBCT ATM, cobertura previa de CBCT, versión de política, snapshot y sustitución sin reinterpretación.
- `php modules/clinical/qa/dental_odontogram02_contract_gate.php`: FDI, V1 y los siete estudios previos, con la única expectativa aditiva de CBCT ATM.
- `bash modules/clinical/qa/dental_odontogram02_disposable_http.sh`: base y archivos desechables; emisión autenticada CBCT ATM, snapshot, impresión HTML, rechazos sin escrituras, siete estudios y orden mixta laboratorio/patología/imagen/funcional/procedimiento.
- `python3 modules/clinical/qa/dental_odontogram02_browser.py`: selector/adapter CBCT ATM y regresión del odontograma a 1440×900, 1366×768 y 390×844, sin escrituras en la revisión real.

**Resultado de producto:** `DENTAL_LOCATION_AUTHORITY_VERSION=2`, `DENTAL_STUDY_POLICY_AUTHORITY_VERSION=1`, `DENTAL_ACQUISITION_PROTOCOL_AUTHORITY_VERSION=1`, `SCHEMA_CHANGED=false`, `ROUTING_CONFIG_CHANGED=false`, `CATALOG_ROWS_CHANGED=false`, `NEXT_PROPOSED_TASK=DENTAL-CAT03C-IMPLEMENT`.
