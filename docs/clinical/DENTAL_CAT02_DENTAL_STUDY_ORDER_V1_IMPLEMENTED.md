# DENTAL-CAT02 — solicitud de estudios odontológicos V1

**Estado: IMPLEMENTED.** Alcance: ubicación clínica de estudios en órdenes del médico. La autoridad de producto es la [auditoría CAT01](DENTAL_CAT01_DENTAL_DIAGNOSTIC_CATALOG_V1_PROPOSED.md) y las dos hojas fuente auditadas allí. La designación dental usa [FDI / ISO 3950](https://www.iso.org/standard/68292.html); `contract_version: 1` es la versión del contrato MXMED, no el año de edición de ISO.

## Estudios canónicos incorporados

La migración idempotente `2026_10_02_17_dental_cat02_catalog.sql` añade siete identidades a `clinical_study_types`; conserva los estudios anteriores y no altera tablas. Los nombres alternativos son alias de búsqueda.

| Clave | Nombre | Categoría | Parámetros de solicitud |
| --- | --- | --- | --- |
| `dental_cbct` | Tomografía dental y maxilofacial de haz cónico | `IMAGEN` | Cobertura obligatoria; piezas o región si es localizada; FOV opcional. |
| `dental_panoramic_xray` | Radiografía panorámica dental | `IMAGEN` | Sin selector de piezas. |
| `dental_cephalometric_xray` | Radiografía cefalométrica lateral | `IMAGEN` | Sin selector de piezas. |
| `tmj_comparative_xray` | Radiografía comparativa de articulaciones temporomandibulares | `IMAGEN` | Vista lateral o posteroanterior. |
| `dental_intraoral_scan` | Escaneo intraoral dental | `DENTAL` | Arco superior, inferior o ambos. |
| `dental_clinical_photographs` | Fotografías clínicas odontológicas | `DENTAL` | Intraorales, extraorales o ambas. |
| `dental_study_model` | Modelo de estudio dental | `DENTAL` | Arco opcional. |

## Ubicación y validación

El objeto `dental_location` se guarda dentro del snapshot inmutable de cada `order_item`, junto con `dental_location_label` calculado por el servidor al emitir la orden. Su autoridad compartida de 52 piezas y nombres en español es `assets/data/clinical/dental-fdi-iso3950-v1.json`: 32 permanentes de cuadrantes 1–4 y 20 temporales de cuadrantes 5–8. El servidor valida una lista cerrada de códigos, dentición, duplicados, claves permitidas, compatibilidad con el estudio, longitud de región y formato de FOV. Una solicitud sin campos dentales sigue siendo válida para los estudios existentes.

El contrato identifica `numbering_system: FDI_ISO_3950` y `contract_version: 1`. Para CBCT admite `dentition_mode` (`PERMANENT`, `DECIDUOUS`, `MIXED`), `coverage` (`LOCALIZED`, `MAXILLARY_ARCH`, `MANDIBULAR_ARCH`, `BOTH_ARCHES`, `MAXILLOFACIAL`), `selected_teeth`, `anatomical_region` y `fov_cm` opcional. El servidor deriva `arch` de las piezas o de la cobertura cuando es posible. `LOCALIZED` exige una o más piezas o una región textual; las coberturas amplias prohíben piezas y región localizada para evitar un foco ambiguo. El FOV describe una preferencia técnica de 1×1 a 30×30 cm, con un decimal opcional por dimensión; no cambia la identidad del estudio ni garantiza capacidad del proveedor. Los otros estudios admiten únicamente los parámetros indicados en la tabla.

La interfaz ofrece arcadas gráficas superior e inferior, orientación explícita del paciente, códigos visibles, nombres accesibles, selección reversible por clic/teclado, estado `aria-pressed`, foco visible, resumen textual y “Limpiar selección”. Los modos Permanente, Temporal y Mixta cambian la vista sin borrar piezas elegidas; el modo efectivo del snapshot refleja ambas denticiones cuando se combinan. Las coberturas amplias se eligen directamente sin marcar cada pieza. Se evaluaron atajos de cuadrante, pero se omitieron en V1 para mantener el compositor compacto; la región localizada puede expresarse con piezas o texto.

El compositor TAX03C conserva búsqueda, selección universal, estudios personalizados y órdenes de categorías mixtas. El selector dental aparece solo al configurar CBCT; TMJ, escaneo, fotografía y modelo muestran sus controles propios. La validación del cliente previene órdenes incompletas y la del servidor protege la autoridad final. Una sustitución de orden que conserva `order_item_id` no puede cambiar `dental_location` ni su etiqueta histórica. La versión imprimible muestra el contexto clínico legible de cada estudio sin exponer claves internas. El vínculo de resultados sigue usando `related_order_item_ids` sin identidad dental nueva.

## Navegación y revisión

OR05 incorpora cuatro agrupadores de navegación, no categorías ni recomendaciones: Radiología dental 2D, Cone Beam / CBCT, Registros ortodóncicos y Escaneo y modelos. Dentista, Ortodoncia, Implantología, Endodoncia, Periodoncia, Cirugía Oral y Maxilofacial y Odontopediatría reciben atajos ordenados según contenido realmente implementado. El catálogo universal permanece accesible para todos. El simulador local de clasificación permite revisión sin cambiar el perfil profesional.

**DENTAL-NAV01:** `DENTAL_FAMILY_DEFAULT_NAVIGATION=DENTAL_ONLY`. Las clasificaciones dentales explícitas del mapa OR05 muestran únicamente agrupadores dentales como tarjetas principales; los médicos y otras profesiones conservan su navegación previa. El conjunto dental de búsqueda se deriva de las relaciones de claves canónicas de esos cuatro agrupadores, incluso cuando el estudio pertenece a `IMAGEN`. La búsqueda y los filtros del compositor quedan dentro de ese conjunto hasta usar “Buscar en todo el catálogo”; allí se identifica “Catálogo general” y se ofrece “Volver a estudios dentales”. La selección previa permanece al cambiar de alcance, por lo que una orden mixta y un estudio personalizado siguen siendo posibles. Ocultar estudios de la navegación dental predeterminada es una decisión de interfaz, no una restricción clínica ni de permisos. Solo aparecen agrupadores que contienen al menos un estudio activo. Patología Bucal sigue sin atajo propio: la citología cervical no es patología bucal.

La QA usa base de datos desechable para catálogo/contrato/orden/HTML/PDF y Playwright Chromium para selector, teclado, navegación y tamaños 1440×900, 1366×768 y 390×844. La migración también se aplicó a la BD de revisión `mxmed_director_review_lon07c`; las pruebas de emisión no escriben en esa BD. El gate HTTP heredado de TAX03A con fixture mínimo carece de `profiles_doctors.display_name` y falla antes de la lógica CAT02; el gate CAT02 con esquema completo cubre el mismo flujo de orden portátil. Esa deuda de fixture permanece separada.

## Fuera de CAT02

Permanecen pendientes la equivalencia de Carpal con `rx_hand`, la identidad/protocolo de las proyecciones craneofaciales, el dominio clínico de odontograma completo, los cuatro presets ortodóncicos, el catálogo de patología bucal, entrega DICOM/STL, opciones de envío del proveedor, servicio profesional de interpretación por radiólogo bucal, capacidades FOV por proveedor y visualización avanzada de resultados dentales. Ninguno de esos conceptos se registra como estudio de CAT02.
