# DENTAL-CAT03C — mínimo seguro de diagnóstico dental

## Alcance y autoridad

Se activaron cuatro identidades canónicas, todas en `IMAGEN` y en el grupo existente `DENTAL_DIAGNOSTICS`: `dental_periapical_xray`, `dental_bitewing_xray`, `dental_occlusal_xray` y `dental_full_periapical_series`. Sus nombres canónicos siguen la matriz CAT03A; los nombres comunes se presentan como ayuda de búsqueda. El catálogo activo pasó de 280 a 284 estudios y el dental de 7 a 11. La migración solo inserta filas nuevas de catálogo; no modifica órdenes históricas ni el esquema clínico.

El contrato de ubicación sigue siendo `DENTAL_LOCATION_V2`. La política por estudio sigue siendo `DENTAL_STUDY_LOCATION_POLICIES_V1` y el protocolo de serie sigue siendo `DENTAL_ACQUISITION_PROTOCOLS_V1`. Periapical usa el odontograma FDI V2 para 1–8 piezas, con dentición permanente, temporal o mixta. Bitewing limita la selección a región posterior de ambas arcadas y lado derecho, izquierdo o bilateral. Oclusal permite maxilar o mandíbula con dentición explícita. La serie completa no lleva piezas: exige seleccionar el protocolo versionado de 14, 16 o 18 imágenes, exclusivamente para dentición permanente y con confirmación del proveedor. CBCT conserva su identidad y ahora expone ATM izquierda, derecha o bilateral en la interfaz.

## Dos arcadas y deduplicación

El botón **Ambas arcadas** expande una solicitud oclusal a dos ítems separados con la misma identidad canónica. Completa solo la arcada faltante; repetirlo no agrega más ítems. Cada ítem recibe su propio `order_item_id`, UUID, ubicación y cobertura de resultado. El compositor y el servidor conservan la deduplicación por estudio para todos los demás estudios. Solo `dental_occlusal_xray` usa la huella semántica `study_type_id + ARCH:MAXILLARY|MANDIBULAR`, calculada a partir de la ubicación estructurada validada. Dos ítems de la misma arcada se rechazan antes de escribir. La clave idempotente del lote conserva dos ítems estables al reintentar.

## Navegación, búsqueda y órdenes portátiles

La navegación dental muestra Radiografía intraoral, Radiografía panorámica, Cefalometría, Cone Beam / CBCT, ATM y Escaneo y modelos cuando tienen estudios activos. La hoja intraoral pequeña lista los cuatro estudios nuevos, sin una sección de «Comunes» no autorizada. La búsqueda familiar desde esa hoja encuentra periapical, bitewing, oclusal, CBCT, ATM, panorámica y cefalometría; la búsqueda entre familias sigue separada. No aparece el enlace redundante «Buscar en todo el catálogo» en las hojas finales.

La orden portable imprime el snapshot clínico, no una interpretación posterior de la autoridad: piezas FDI para periapical, región posterior y lado para bitewing, líneas independientes «Arcada: Maxilar» y «Arcada: Mandíbula» para oclusal, protocolo de serie y ATM para CBCT. La revisión de la composición también distingue las dos arcadas y muestra el protocolo de serie. Los resultados se vinculan por `related_order_document_uuid` y `related_order_item_ids` exactos.

## Verificación

- `dental_cat03c_disposable_http.sh`: base y sesiones desechables, 284/11, búsqueda real, emisión HTTP autenticada, cinco PDFs reales con texto extraído, variantes dentales, rechazo sin escrituras, idempotencia, siete estudios dentales anteriores, cinco familias no dentales y vínculo exacto de resultados para los nuevos tipos y CBCT ATM. PASS.
- `dental_cat03c_browser.py`: controles FDI, región, arcada, expansión de ambas arcadas, duplicados, protocolo y CBCT ATM; 1440×900, 1366×768 compacto y expandido, 390×844, sin desbordamiento horizontal. PASS.
- `dental_cat03c_live_browser.py`: navegador autenticado, solo GET, sobre `127.0.0.1:18148`; navegación dental, búsqueda y selector periapical en las mismas cuatro geometrías. PASS.
- `dental_cat03b_contract_gate.php`, `study_search02_gate.php` y `dental_odontogram02_browser.py`: PASS.

La migración idempotente se aplicó a la base local de revisión después de pasar la QA desechable. No se crearon órdenes de prueba en esa base. No se añadieron grupos de enrutamiento, tablas, contratos externos ni cambios a los snapshots dentales existentes.

Siguen diferidos: trazado cefalométrico, preset de registros ortodóncicos, expansión cefalométrica AP/PA, CBCT/FOV avanzado, series de boca completa temporal/mixta y charting de superficies dentales.
