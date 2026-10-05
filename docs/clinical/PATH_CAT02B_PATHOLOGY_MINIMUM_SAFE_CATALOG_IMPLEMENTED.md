# PATH-CAT02B — Catálogo mínimo seguro de patología

**Estado: MINIMUM_SAFE_CATALOG_IMPLEMENTED.** Base de partida: `69e386f81e4f38b842a2a2b79fc84deb9d875a42`. La activación añadió exactamente 11 identidades a las 252 existentes: 263 estudios activos, 16 en `PATOLOGIA`. La migración de datos `2026_10_04_23_pathology_minimum_safe_catalog.sql` se ejecutó dos veces en una base desechable y una vez en la base de revisión; no altera esquema ni snapshots previos.

## Identidades y navegación

| Subfamilia visible | Nuevas identidades |
| --- | --- |
| Histopatología | `histopath_biopsy`, `histopath_resection` |
| Citología | `cyto_fna`, `cyto_bronchial_brushing`, `cyto_bronchial_washing` |
| Inmunohistoquímica | `ihc_single_marker`, `ihc_breast_profile` |
| Tinciones especiales | `histochemical_special_stain` |
| Inmunofluorescencia | `if_renal`, `if_skin` |
| Revisión externa | `pathology_outside_review` |

Las cinco identidades citológicas previas siguen en sus hojas y conservan su significado. HIER03 muestra sólo las seis subfamilias pobladas; FEATURED02 muestra directamente cada hoja pequeña, sin grupo COMUNES ni acción forzada de catálogo completo. Las 11 claves usan el grupo existente `PATHOLOGY_CYTOLOGY`; el lote emite órdenes independientes para otros grupos de servicio.

## Solicitud y resultados

El compositor lee `pathology_order_parameters_v1.json`, presenta los campos controlados por identidad y permite entre 1 y 10 muestras donde la regla lo autoriza. La autoridad de servidor PATH-CAT02A valida cada emisión. Guarda la versión 1, los valores estructurados y `pathology_order_parameters_label` en cada ítem; el rótulo persistido se usa en el detalle y en la orden portátil HTML/PDF sin reinterpretarlo con autoridades futuras. Los resultados PDF privados mantienen el vínculo a la versión fuente y a `related_order_item_ids`, sin introducir hallazgos sinóticos. La toma de muestra y el procesamiento del proveedor siguen fuera de la orden médica.

La búsqueda SEARCH02 incluye los términos aprobados de histopatología, biopsia, citología, Papanicolau, PAAF/BAAF, IHQ, inmunohistoquímica, PAS/Grocott/Masson, segunda opinión, laminillas y bloques. La búsqueda de patología recorre la familia completa y deja TAC, HbA1c y Holter en sus familias correspondientes como coincidencias externas.

## Verificación

- `path_cat02a_contract_gate.php`: autoridad, 11 casos válidos, rechazos, snapshot inmutable y regresión de otras familias.
- `path_cat02b_disposable_gate.sh`: emisión autenticada de las 11 claves; 3 órdenes por ruta; HTML/PDF portátil; resultado ligado al ítem exacto; 10 rechazos sin escrituras; 1, 2 y 10 muestras ordenadas; 11 rechazadas.
- `study_search02_gate.php`: 47 expectativas históricas SEARCH02 y regresiones de laboratorio, imagen, funcionales, procedimientos y dental.
- Runtime médico `127.0.0.1:18148`: seis subfamilias, nueve hojas pobladas, búsqueda en familia y rescate entre familias, panel de biopsia y varias muestras. Las medidas 1440×900, 1366×768 con barra compacta, 1366×768 con barra expandida y 390×844 pasaron sin error JS, escritura de QA ni desbordamiento horizontal.

Permanecen diferidos `frozen_section`, `cyto_sputum`, `her2_ish`, `bone_marrow_histology` y `generic_tissue_PCR`; no se crean claves ni hojas vacías para ellos.
