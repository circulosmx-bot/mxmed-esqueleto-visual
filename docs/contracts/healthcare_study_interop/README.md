# B3 INTEROP01 · Referencias de estudios y publicación de resultados

Esta base es interna. No expone una ruta HTTP de envío, aceptación ni liberación de resultados y no concede permisos de expediente al proveedor. La orden clínica se crea, imprime y descarga sin organización, referencia u orden de servicio.

## Autoridades

| Autoridad | Identidad y alcance |
|---|---|
| `healthcare_study_referrals` | Una transmisión digital de la versión exacta de una orden clínica a una organización y sucursal. Guarda ID, UUID y `version` de `clinical_documents` y clave idempotente por médico. |
| `healthcare_study_referral_items` | Subconjunto de `order_item_id` de esa versión, con `study_type_id` y snapshot descriptivo. Sólo órdenes con `order_payload_version=2` son elegibles. |
| `healthcare_study_referral_events` | Eventos `SENT`, `ACCEPTED`, `DECLINED`, `CANCELED` de sólo adición. Estado: `SENT → ACCEPTED|DECLINED|CANCELED`; los terminales no transicionan en INTEROP01. |
| `healthcare_provider_service_orders` | Orden operativa distinta de `clinical_documents`. Orígenes representables: `PHYSICIAN_REFERRAL`, `PATIENT_REQUEST`, `PROVIDER_FRONT_DESK`; sólo el primero tiene puente escritor. Una referencia aceptada produce una orden en `PENDING_COLLECTION`. `IN_PROCESS`, `READY`, `DELIVERED`, `CANCELED` quedan reservados para operación futura. |
| `healthcare_provider_service_order_items` | Copia exacta de los ítems referidos. Claves foráneas compuestas impiden mezclar otra referencia o cambiar el estudio canónico. |
| `healthcare_provider_result_sources` | Un vínculo inmutable entre versión de resultado clínico, UUID de liberación del proveedor, orden de servicio, referencia, organización, sucursal y paciente. El resultado médico sigue siendo `clinical_documents`. |

## Envío y elegibilidad

`HealthcareStudyInteropService::sendReferral()` requiere relación médico–paciente activa; orden emitida `generated`/`signed`, no anulada ni reemplazada al envío; ítems exactos V2 con identidad canónica; organización y sucursal activas y verificadas; y, por cada estudio seleccionado, catálogo activo, maestro activo y oferta local `ON_SITE` activa y verificada. Los modos a domicilio y móvil esperan reglas de región/servicio futuras. La ubicación debe pertenecer a la organización. Es validación de destino explícito, sin ranking ni semántica de recomendación. Una orden admite múltiples referencias a diferentes proveedores y subconjuntos de ítems.

La solicitud semántica ordena los `order_item_id` y se resume con SHA-256. La unicidad `(doctor_id,idempotency_key)` da el mismo referido ante reintento idéntico y conflicto ante distinta solicitud, incluida la carrera concurrente. Aceptación vuelve a comprobar organización, sucursal, catálogo, maestro y ofertas, bloquea la referencia y `UNIQUE(referral_id)` garantiza una sola orden de servicio.

`referralRead()` devuelve sólo identidad de referencia, orden exacta, destino, estado e ítems referidos. No entrega expediente, consultas, recetas ni documentos ajenos. El uso futuro por personal proveedor requiere una capa de autorización operativa propia.

## Resultado liberado

`publishReleasedResult()` es un servicio interno. Requiere `ProviderReleasedResultAuthority::assertReleased()`; ningún rol ordinario de proveedor obtiene esa capacidad por defecto. No existe endpoint público de liberación. La prueba desechable usa una atestación simulada. Un adaptador futuro deberá verificar la autoridad clínica real antes de invocarlo.

El contrato incluye UUID estable de liberación, orden de servicio, referencia, orden clínica exacta, paciente, organización, sucursal, 1..N `related_order_item_ids`, tipo/título, fecha de liberación, profesional opcional y artefacto privado finalizado. El servicio contrasta todas las identidades contra las autoridades persistidas, comprueba cada ítem contra la orden de servicio y valida el contenido contra la versión exacta de la orden clínica. Publica con `mxmed_build_clinical_document()`, `clinical_study_validate_result_payload()` y `mxmed_persist_clinical_document_in_transaction()`, más el manifiesto `clinical_document_binaries` existente. No atribuye falsamente una participación de médico. No crea una segunda tabla de resultados clínicos.

La fuente privada finalizada ya debe existir bajo `clinical/` y superar tamaño/hash. La autoridad futura que libere resultados es responsable del ciclo de vida previo de ese artefacto. El UUID de liberación es único: reintento idéntico devuelve el mismo documento; contenido diferente produce conflicto; publicación concurrente deja un resultado canónico. Las correcciones posteriores deben crear una nueva versión mediante el linaje de enmiendas clínicas; jamás sobrescribir el documento publicado.

## Sucesores y canales existentes

La referencia nunca cambia de V1 a V2. Un resultado liberado para una referencia V1 conserva `related_order_document_id`/UUID e ítems de V1 en el payload canónico. OR02B expone `result_source_order_document_id` de V1 y `order_lineage_head_document_id` de V2 por separado; `related_order_document_id` en el DTO conserva el significado de cabecera visual para OR02C. La cobertura de V2 no hereda ítems de V1. El servicio interno puede recibir el resultado de una referencia V1 aceptada después de la sucesión; el escritor manual existente sigue rechazando altas nuevas contra órdenes reemplazadas.

La carga manual RES02A, los pedidos sin proveedor y la orden portable no dependen de estas tablas. La migración es aditiva y no modifica el esquema de la orden clínica.
