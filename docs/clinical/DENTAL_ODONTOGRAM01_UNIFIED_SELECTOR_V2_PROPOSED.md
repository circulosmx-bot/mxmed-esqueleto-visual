# DENTAL-ODONTOGRAM01 — selector unificado V2

**IMPLEMENTED · DENTAL-ODONTOGRAM02**
Base auditada: `2d73ffbca0da47a30f0dd7ad2fe32263294bf481`. DENTAL-ODONTOGRAM01 fue una propuesta sin cambios de producción; la implementación aprobada y su QA se documentan en [DENTAL_ODONTOGRAM02_UNIFIED_SELECTOR_V2_IMPLEMENTED.md](DENTAL_ODONTOGRAM02_UNIFIED_SELECTOR_V2_IMPLEMENTED.md).

## Hallazgo en el código actual

- `assets/data/clinical/dental-fdi-iso3950-v1.json` es la única lista FDI actual: 32 piezas permanentes, 20 temporales, cuadrante, posición, arco, lado y nombre español. El servidor la lee en `api/_lib/clinical_dental_location.php`; el navegador la lee en `assets/js/clinical/dental-location-v1.js`. La autoridad se identifica con `FDI_ISO_3950`, `contract_version: 1`.
- El selector V1 ya dibuja siluetas, dos arcadas, etiquetas FDI, controles Permanente/Temporal/Mixta, `aria-pressed`, resumen y botón de limpieza. La dentición mixta muestra 52 controles en arcadas permanentes y temporales separadas. En móvil sus controles pueden medir menos de los ~44 px propuestos para V2; no hay navegación con flechas. La ubicación dental está acoplada a la configuración de estudios: las piezas se admiten solo en CBCT localizado; panorámica y cefalometría rechazan ubicación; ATM guarda vista lateral/PA, sin lado; escaneo guarda arco; fotografía guarda alcance; modelo admite arco opcional.
- El servidor normaliza `dental_location` y `dental_location_label` dentro de cada `order_item` emitido; la sustitución conserva la ubicación del mismo `order_item_id`. No hay presencia dentaria persistida ni autoridad V2. `clinical_treatment_sessions.procedure_items` solo admite secuencia, `type_key`, título y nota; los planes/sesiones no tienen ubicación dental tipada. La búsqueda identifica el estudio, y `DENTAL_DIAGNOSTICS` sigue siendo el grupo de enrutamiento de los siete estudios.

**Decisión propuesta:** un componente `DentalOdontogram` con una autoridad de ubicación, validador y serializador compartidos. Estudios, procedimientos y planificación serán consumidores configurados de ese mismo contrato; ningún consumidor definirá una numeración propia. La interfaz espacial toma la referencia del Director como concepto de dos arcadas y selección inmediata; no incorpora su numeración Universal ni su imagen.

## Identidad, dentición y orden espacial

Conservar FDI/ISO 3950 como identidad de dos dígitos. [ISO 3950:2016](https://www.iso.org/standard/68292.html) define la designación de dientes y áreas de la cavidad oral mediante dos dígitos. La [lámina educativa de FDI](https://www.fdiworlddental.org/sites/default/files/2021-09/NOHP_slides_day1.pdf) confirma el orden de ambos arcos. `PRIMARY` es la nueva clave visible para la dentición temporal; el lector V1 seguirá interpretando `DECIDUOUS` sin modificarlo.

| Vista frontal orientada por el **paciente** | Derecha del paciente → línea media → izquierda del paciente |
|---|---|
| Maxilar permanente | `18 17 16 15 14 13 12 11 | 21 22 23 24 25 26 27 28` |
| Mandíbula permanente | `48 47 46 45 44 43 42 41 | 31 32 33 34 35 36 37 38` |
| Maxilar temporal | `55 54 53 52 51 | 61 62 63 64 65` |
| Mandíbula temporal | `85 84 83 82 81 | 71 72 73 74 75` |

`PERMANENT`, `PRIMARY` y `MIXED` son los tres modos V2. Temporal tiene 20 posiciones; no se dibujan premolares ni terceros molares temporales. Edad puede sugerir un modo inicial, jamás imponerlo ni declarar erupción/presencia. El odontólogo siempre puede cambiarlo. No se muestra numeración Universal secundaria en V2: añadirla ahora aumenta densidad y exige una tabla de equivalencia/QA sin necesidad del flujo mexicano actual.

### Dentición mixta

Usar **dos carriles alineados por arcada**: permanente y temporal aparecen simultáneamente, cada uno con su código FDI, silueta, etiqueta de dentición y área táctil propia. En sitios de transición se agrupan visualmente los candidatos cercanos bajo una misma zona espacial, sin superponer botones ni convertir una pieza primaria en su sucesora. Primeros molares permanentes y otras posiciones sin predecesor temporal conservan un sitio propio. El usuario puede seleccionar `55` y `15` si el caso lo exige; la interfaz no deduce cuál está presente ni erupcionado. Un pequeño resumen separa códigos permanentes y temporales. En móvil, los carriles mantienen sus blancos táctiles y se desplazan horizontalmente dentro de la arcada con indicadores de dirección; no reducen todas las piezas hasta hacerlas difíciles de pulsar.

El modo es **estado del selector**, elegido por el profesional. El snapshot de cada orden/procedimiento conserva el modo con el que se seleccionó la ubicación. V2 no necesita una ficha persistente de presencia dentaria ni escribe una presunción en el paciente. Una futura ficha odontológica podrá informar disponibilidad, con procedencia clínica propia, sin reinterpretar snapshots existentes.

## Contrato único de ubicación V2

Proponer `dental_location_authority_v2` como versión **aditiva** de lectura/validación. El payload V1 permanece aceptado y se presenta mediante su lector V1. Un nuevo `dental_location` V2 es una **unión discriminada**; se guardan únicamente campos pertinentes. Los ejemplos son esquema de diseño, no código ejecutable:

```json
{
  "contract_version": 2,
  "numbering_system": "FDI_ISO_3950",
  "dentition_mode": "MIXED",
  "location_type": "TOOTH_LOCATION",
  "selection_mode": "MULTIPLE_TEETH",
  "tooth_fdi_codes": ["15", "55"]
}
```

| `location_type` | `selection_mode` | Campos pertinentes y validación |
|---|---|---|
| `TOOTH_LOCATION` | `SINGLE_TOOTH`, `MULTIPLE_TEETH` | `tooth_fdi_codes`: lista única de códigos FDI válidos, ordenada canónicamente por código; longitud 1 para simple, entre mínimo/máximo del consumidor para múltiple. `dentition_mode` obligatorio. |
| `QUADRANT_LOCATION` | `QUADRANT` | `quadrant_key`: `UPPER_RIGHT`, `UPPER_LEFT`, `LOWER_LEFT`, `LOWER_RIGHT`, con `dentition_mode`. Guarda la **intención de cuadrante**, sin expandir a dientes. Las posiciones corresponden a cuadrantes FDI 1–4 permanentes y 5–8 temporales; `MIXED` cubre el sitio espacial sin afirmar presencia. |
| `ARCH_LOCATION` | `ARCH` | `arch_key`: `MAXILLARY`, `MANDIBULAR`, `BOTH_ARCHES` cuando el consumidor lo admita. No se infiere un arco solicitado a partir de una lista de piezas. |
| `REGION_LOCATION` | `REGION`, `BILATERAL_REGION` | `region_key`: `ANTERIOR`, `POSTERIOR`, `MAXILLOFACIAL`, `OTHER_SPECIFIED`; `arch_key` cuando corresponda; `side_key`: `LEFT`, `RIGHT`, `MIDLINE` o `BILATERAL` según región. `OTHER_SPECIFIED` exige `region_detail` acotado; `BILATERAL_REGION` exige `BILATERAL`. Superior/inferior y derecha/izquierda son composición de arco + lado, no ocho claves adicionales. |
| `TMJ_LOCATION` | `TMJ_REGION` | `tmj_side`: `LEFT`, `RIGHT`, `BILATERAL`. ATM no es pieza, cuadrante ni arcada. Vista/proyección radiográfica permanece como parámetro del **estudio**, separado de ubicación. |

Campos ajenos al tipo se rechazan. `numbering_system` se exige para piezas FDI; no se inserta en ATM. `dentition_mode` es obligatorio para pieza/cuadrante y opcional como contexto de visualización para arco/región. `SINGLE_TOOTH` exige exactamente un código; múltiple se trata como conjunto, sin significado de orden de clic. No se almacenan listas derivadas de dientes para cuadrante/arco/región: si una vista necesita sombrearlos, los calcula solo para presentación y nunca para sustituir la intención estructurada. La selección se valida contra la configuración del consumidor antes de emitir y se repite en el servidor. En una sustitución, el mismo ítem conserva el snapshot exacto; cambiar la ubicación requiere un ítem/sucesor con identidad histórica nueva según el contrato existente.

La capacidad del proveedor continúa asociada a la identidad canónica del estudio. Cobertura, precio o capacidad por sitio dental podrán examinar el snapshot en un contrato futuro; jamás crean un estudio por cada diente. La búsqueda permanece independiente del odontograma.

### Persistencia y compatibilidad

V1 sigue siendo legible **sin backfill**: `contract_version:1`, `DECIDUOUS`, `selected_teeth`, `coverage`, `arch`, `anatomical_region`, `projection`, `photograph_scope` y `fov_cm` conservan su significado original. El lector puede construir una **proyección visual efímera** para dientes V1; no sobrescribe ni convierte el payload. `LOCALIZED` con dientes puede visualizarse como foco de piezas; `LOCALIZED` con texto queda como región histórica no clasificada. `MAXILLARY_ARCH`, `MANDIBULAR_ARCH` y `BOTH_ARCHES` muestran su cobertura histórica; `MAXILLOFACIAL` conserva su propia etiqueta. ATM V1 solo tiene proyección, por lo que la lateralidad es **desconocida**, nunca bilateral por inferencia. Foto, escaneo y modelo retienen sus parámetros V1. La compatibilidad debe probar lectura, impresión y resultados vinculados a la versión exacta, sin reescribir órdenes.

## Configuración por consumidor

`DentalOdontogram` recibe una política versionada del consumidor: `allowed_location_modes`, `min_selection`, `max_selection`, `dentition_modes_allowed`, regiones/lados permitidos, `required`, `value` y `onChange`. El validador del servidor usa la **misma autoridad y política**, sin confiar en los límites del cliente. El componente no conoce claves de estudio ni procedimiento; un adaptador resuelve política y ubica el snapshot en el ítem. La matriz CSV adjunta distingue los siete estudios actuales de casos futuros. Periapical y bitewing son **casos hipotéticos**, sin identidad activa en el catálogo; no se habilitan por este diseño. Procedimientos dentales tampoco tienen catálogo canónico actual en `clinical_treatment_sessions`.

Propuesta de API conceptual:

```js
DentalOdontogram.mount(host, {
  authorityVersion: 2,
  dentitionMode: 'MIXED',
  allowedDentitionModes: ['PERMANENT', 'PRIMARY', 'MIXED'],
  allowedLocationModes: ['SINGLE_TOOTH', 'MULTIPLE_TEETH'],
  minSelection: 1,
  maxSelection: 4,
  allowedRegions: [],
  required: true,
  value: null,
  onChange: (locationSnapshot) => {}
})
```

Superficies (`MESIAL`, `DISTAL`, `OCCLUSAL`, `BUCCAL`, `LINGUAL`, `PALATAL`, `INCISAL`) se difieren como extensión tipada del **ítem de procedimiento**, con validación por tipo de pieza, no del selector de ubicación base. No se modelan aquí caries, ausencias, restauraciones, coronas, implantes o endodoncia realizada. **Este componente no es una ficha clínica odontológica.**

## Presentación, accesibilidad y QA futura

Maxilar arriba, mandíbula abajo; derecha del paciente indicada claramente. Usar siluetas SVG simples, número FDI visible, etiqueta de dentición en modo mixto y estado seleccionado turquesa con borde, marca y `aria-pressed`; blanco/neutro para no seleccionado, énfasis de borde en hover, patrón/etiqueta para deshabilitado. No usar solo color. El resumen estructurado se coloca cerca del gráfico: piezas, cuadrante, arcada, región o ATM según el tipo; “Limpiar selección” aparece solo si hay valor. Atajos de arcada/cuadrante se muestran exclusivamente cuando la política los autoriza.

En 1440×900 y 1366×768 caben las arcadas en la zona de trabajo sin scroll excesivo; en 390×844 la arcada se desplaza dentro de su contenedor manteniendo controles alrededor de 44×44 CSS px y sin overflow de documento. Flechas recorren piezas en orden espacial; Tab entra al grupo/control siguiente; Espacio/Enter selecciona; foco visible y no oculto; el lector anuncia, por ejemplo, “Pieza 16, primer molar superior derecho, no seleccionada”. [WCAG 2.2](https://www.w3.org/WAI/standards-guidelines/wcag/new-in-22/) pide foco visible/no oculto y tamaños o separaciones suficientes; ~44 px es la **meta del producto**, no el mínimo normativo de 24 px.

La [matriz de modos](DENTAL_ODONTOGRAM01_LOCATION_MODE_MATRIX.csv) cubre permanentes, temporales, mixtas, cuadrante, arcada, región, región bilateral y ATM, junto con rechazos. La [matriz de consumidores](DENTAL_ODONTOGRAM01_CONSUMER_MAPPING.csv) contiene los siete estudios actuales y cinco usos de procedimiento propuestos. Implementar primero la autoridad/validador/lector V2 y su QA, después el componente y los adaptadores; reanudar DENTAL-CAT03A solo tras ratificación del Director y un contrato de ubicación V2 operativo.
