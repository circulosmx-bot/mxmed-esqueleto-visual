# PATH-CAT02A — Contrato versionado de parámetros de orden de patología

**Estado: CONTRACT_IMPLEMENTED_PENDING_CATALOG.** Esta fase implementa la autoridad y su validación de servidor, sin activar nuevos estudios de patología ni cambiar esquema, rutas, búsqueda, navegación o interfaz.

## Autoridades y compatibilidad

- Autoridad: `modules/clinical/catalog/pathology_order_parameters_v1.json`, `version=1`, `contract=pathology_order_parameters`.
- Campo de ítem: `pathology_order_parameters`. Las 11 reglas están asociadas exclusivamente a las futuras claves aceptadas en [PATH-CAT01](PATH_CAT01_PATHOLOGY_DOMAIN_MODEL_PROPOSED.md). Hasta que esas claves se publiquen en el catálogo, ninguna puede emitirse en una orden real.
- CAT03B: `specimen_collection_requirements.version=1` y su archivo/validador permanecen intactos. Es una autoridad paralela de instrucciones de recogida para los estudios actuales; los parámetros de patología describen material previsto, sitio y método solicitado. No se duplica ni se convierte un evento de toma en la orden.
- Los cinco estudios citológicos actuales siguen con sus snapshots y requisitos originales. Ningún snapshot histórico se reescribe. La normalización de órdenes mantiene `order_payload_version=2` y añade el campo sólo cuando una futura identidad lo requiera.
- La validación exige clave de estudio activa del catálogo antes de crear el snapshot. Un estudio libre, una clave actual de otra familia o cualquiera de las cinco citologías actuales rechazan `pathology_order_parameters` si se envía.

## Modelo de material, sitio y contexto

| Campo | Valores/regla |
| --- | --- |
| `material_key` | `TISSUE`, `CYTOLOGY_SPECIMEN`, `PARAFFIN_BLOCK`, `GLASS_SLIDE`. Son clases **previstas**, no el material recibido. El tipo admisible depende de la identidad. |
| `anatomic_site_text` | Descripción clínica delimitada a 120 caracteres. Obligatoria en biopsia, resección, PAAF, IHQ, perfil mamario, tinción y piel IF; opcional cuando la identidad ya fija bronquio/riñón y en revisión externa. |
| `anatomic_site_key` | Identificador controlado opcional de 13 sitios generales. `OTHER_ANATOMICAL_SITE` exige texto. `BRONCHUS`, `BREAST`, `KIDNEY` y `SKIN` se fijan según la identidad y no pueden contradicirse. El texto conserva la precisión clínica sin crear cientos de claves de órgano. |
| `laterality` | `LEFT`, `RIGHT`, `BILATERAL`, `MIDLINE`, `NOT_APPLICABLE`, `UNSPECIFIED_IF_ALLOWED`; opcional cuando no aplica o se desconoce. En mama, riñón, pulmón y bronquio se rechaza `MIDLINE`/`NOT_APPLICABLE`. `UNSPECIFIED_IF_ALLOWED` exige `laterality_unspecified_reason` (máximo 120 caracteres). No se inventa por defecto. PATH-CAT02B deberá pedirla según el sitio/contexto clínico y no inferirla de la descripción libre. |
| `specimen_context` | Fijado por identidad: `BIOPSY`, `RESECTION`, `CYTOLOGY`, `OUTSIDE_MATERIAL`, `IHC`, `SPECIAL_STAIN`, `IMMUNOFLUORESCENCE`. El cliente puede omitirlo; el snapshot incorpora el valor fijo. |
| `description` | Descripción clínica opcional de material, hasta 190 caracteres; requerida para pieza de resección. No es descripción macroscópica real. |

`specimens` es un arreglo ordenado de 1 a 10 materiales previstos. Permite, por ejemplo, lesiones A y B en dos entradas de una misma orden histopatológica. Rechaza entradas exactamente duplicadas y clases incompatibles con la identidad. El arreglo no registra contenedores, bloques o laminillas efectivamente recibidos. La interfaz de PATH-CAT02B deberá conservar cada entrada durante la composición y resolver su deduplicación actual por clave de estudio; esta fase no cambia el compositor.

## Reglas por identidad futura

| Clave | Material/sitio/otros parámetros |
| --- | --- |
| `histopath_biopsy` | `TISSUE`; texto de sitio obligatorio; contexto `BIOPSY`; 1–10 muestras previstas. |
| `histopath_resection` | `TISSUE`; texto de sitio y descripción de pieza obligatorios; contexto `RESECTION`; 1–10. |
| `cyto_fna` | `CYTOLOGY_SPECIMEN`; sitio obligatorio; contexto `CYTOLOGY`; la aspiración realizada no se infiere. |
| `cyto_bronchial_brushing` | `CYTOLOGY_SPECIMEN`; sitio fijo `BRONCHUS`; una muestra. El método de cepillado viene de la identidad. |
| `cyto_bronchial_washing` | `CYTOLOGY_SPECIMEN`; sitio fijo `BRONCHUS`; una muestra. El método de lavado viene de la identidad. No se fusiona con cepillado ni con lavado broncoalveolar químico. |
| `ihc_single_marker` | `PARAFFIN_BLOCK`; sitio obligatorio; `marker_key` obligatorio. Una laminilla ya teñida no se presume apta para nuevo IHQ. |
| `ihc_breast_profile` | `PARAFFIN_BLOCK`; sitio fijo `BREAST`, texto de sitio obligatorio; `profile_version=1` obligatorio. |
| `histochemical_special_stain` | `TISSUE` o `PARAFFIN_BLOCK`; sitio obligatorio; `stain_key` obligatorio. |
| `if_renal` | `TISSUE`; sitio fijo `KIDNEY`; aviso fijo de coordinación de manejo/transporte antes de la toma, sin pedir conjugados ni procesamiento. |
| `if_skin` | `TISSUE`; sitio fijo `SKIN`, texto de sitio obligatorio; aviso fijo de coordinación de manejo/transporte; IF cutánea separada de renal. |
| `pathology_outside_review` | `PARAFFIN_BLOCK` o `GLASS_SLIDE`, 1–10 materiales previstos; `source_institution_name` y `prior_report_status` obligatorios. Sitio opcional si aún se desconoce. |

La autoridad IHQ V1 contiene cuatro claves: `ER`, `PR`, `HER2`, `KI67`; `other_allowed=false`. El perfil mamario V1 fija exactamente esas cuatro componentes y no implica HER2 por ISH. La autoridad de tinciones V1 contiene `PAS`, `GROCOTT`, `MASSON`; `other_allowed=false`. Los valores fuera de lista se rechazan, sin convertir texto libre en nueva clave. El conjunto y el panel siguen la evidencia clínica mexicana revisada en PATH-CAT01: [Médica Sur](https://medicasur.com.mx/es/ms/Anatomia_Patologica_2019) enumera PAS, Masson y Grocott, y [Chopo](https://www.chopo.com.mx/abraza-la-vida) describe ER/PR/HER2/Ki-67 en el perfil mamario.

## Payload y snapshot

Ejemplo futuro, **todavía no emitible con el catálogo actual**:

```json
{
  "study_type_key": "histopath_biopsy",
  "pathology_order_parameters": {
    "version": 1,
    "specimens": [
      {"material_key": "TISSUE", "anatomic_site_key": "BREAST", "anatomic_site_text": "Lesión A", "laterality": "RIGHT"},
      {"material_key": "TISSUE", "anatomic_site_key": "BREAST", "anatomic_site_text": "Lesión B", "laterality": "RIGHT"}
    ]
  }
}
```

El servidor rechaza versión distinta de 1, claves desconocidas en cualquier nivel, material/sitio/contexto incompatible, sitio o parámetro obligatorio ausente, valor controlado desconocido y entradas duplicadas. El snapshot del `order_item` almacena el objeto normalizado, la versión de autoridad y `pathology_order_parameters_label` generado en ese momento. La comparación de reemplazo considera **ambos** campos: conservar `order_item_id` impide cambiar parámetros o su etiqueta histórica. Una versión futura de esta autoridad no recalcula snapshots antiguos.

Un resultado futuro puede seguir usando el documento clínico `result` y PDF privado con versión exacta de orden fuente e IDs de ítem. Este contrato no altera resultados, lectura, linaje, ni crea esquema sinótico. La orden portátil y los lectores de PATH-CAT02B deberán proyectar la etiqueta persistida con escape HTML y autorización existentes; este cambio aún no activa contenido patológico nuevo.

## Revisión externa y límite operativo

`prior_report_status=PROVIDED|PENDING` es una declaración del médico; `PENDING` permite solicitar sin informe previo. No se acepta en V1 un UUID de informe anterior dentro de este objeto porque el snapshot del ítem no tiene contexto de paciente para comprobar propiedad/acceso. Cuando una fase posterior habilite la referencia, deberá verificar documento clínico privado del **mismo paciente** en el escritor antes de persistirla. No se crea subida ni medio público.

Identificadores de accesión, número real de contenedores/casetes, IDs reales de bloque/laminilla, hora de fijación, macroscopia, preparación efectiva y timestamps de laboratorio quedan fuera de la orden del médico. Los textos se delimitan y rechazan controles; el código no agrega registro público de sitio, diagnóstico o institución. En PATH-CAT02B, el panel contextual compacto aparecerá sólo al seleccionar una de las nuevas identidades autorizadas y mostrará sitio/material, lateralidad cuando aplique, marcador/tinción o perfil, y los datos de revisión externa. Búsqueda, rutas y navegación siguen sin cambios en esta fase.

## Verificación

`php modules/clinical/qa/path_cat02a_contract_gate.php` usa una base SQLite **en memoria** con claves futuras sólo para validar el escritor y los snapshots. Cubre las 11 reglas, selección de dos sitios, ambos métodos bronquiales, valores controlados, errores por campos faltantes/desconocidos, inmutabilidad de reemplazo y regresión de Laboratorio, Imagen, Cardiovascular/funcional, Dental, Procedimientos y las cinco citologías activas. La base de revisión se consulta sólo para comprobar el conteo activo; no se modifica. No hay migración ni cambio en el catálogo real.
