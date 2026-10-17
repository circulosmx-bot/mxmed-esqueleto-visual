# Interacciones clínicas — consulta, documentos y dispositivo

## Ancla y alcance

Inspección de fuente 2026-10-17, baseline
`1b8dfccb68a7613b2182507a840454631dbf012a`, worktree
`mxmed-step2-vitals-r1`; no ejecución de HTTP, SQL, QA ni emisión/firma real.
Sucesor acotado de [interconexiones históricas](../MAPA_INTERCONEXIONES_CLINICAS_MXMED.md).
El [inventario clínico](FUNCIONES_CLINICAS_MXMED.md) define capacidades y cinco ejes.
[Interacciones generales](INTERACCIONES_MXMED.md) conserva los otros journeys.
Los siguientes tres recorridos están **trazados en fuente**; sus tramos con evidencia
aislada no equivalen a un recorrido completo activo en normal-local.

<a id="f05"></a>

## F05 — paciente, consulta, registros y documentos

| Paso / sincronía | Conexión real y responsable | Autoridad, transacción, resultado y fallo |
|---|---|---|
| 1. Elegir paciente — síncrono HTTP | UI `#p-expediente` / búsqueda de app.js / `[data-rx-global-query]` → GET [PAPI](../../api/patients/index.php) `/doctors/{D}/patients/search` → [SearchDoctorPatientsController](../../modules/patients/controllers/SearchDoctorPatientsController.php) → `PatientsRepository::searchPatientsByDoctorId()` | Resolver Identity/capability `patients`, D exacto y vínculo activo; contactos públicos de respuesta enmascarados. Lectura vacía no crea paciente. 401/403/scope no permite obtener expediente. PAT-01. |
| 2. Abrir contexto — eventos UI y HTTP | [vis01-navigation](../../assets/js/clinical/vis01-navigation.js), [vis02-patient-header](../../assets/js/clinical/vis02-patient-header.js), `#m7-workspace`/`[data-m7-start]` → GET [CAPI](../../api/clinical/index.php) `/patients/{P}/encounters/active` | Paciente activo del DOM es destino, no autoridad. Resolver A por request y D/P; UI conserva epoch y descarta respuestas de otro paciente. Cambiar paciente consulta guard de cambios CLIN-18. |
| 3. Iniciar o continuar — comando transaccional | [m7-workspace.js](../../assets/js/clinical/m7-workspace.js) y [vis04-consultation.js](../../assets/js/clinical/vis04-consultation.js) → POST `/patients/{P}/encounters` con Idempotency-Key → [ClinicalEncounterIntegrityRepository/Service](../../api/_lib/clinical_encounter_integrity.php) | Scope cita/P/D, master/cohorte M6 y schema íntegro. `clinical_encounter_start_requests` liga clave/hash/resultado al E; lock/índice del encounter abierto serializa carreras. Repetición exacta devuelve mismo E; conflicto pide continuar el existente. Crear cita no equivale a documentar atención. |
| 4. Capturar motivo/exploración/assessment/plan — HTTP CAS | Editor `[data-m7-section]`, `#m7-section-text` → PUT `/encounters/{E}/sections/{type}` → `ClinicalEncounterIntegrityService` + [ClinicalEncounterSectionsRepository](../../api/_lib/clinical_encounter_sections.php) | E abierto del mismo D/P; schema y row_version; write de sección/actor bajo límite transaccional. Conflicto 409 conserva texto local y propone releer/resolver, no pisa. Diagnóstico de consulta no pasa a problema longitudinal sin promoción explícita. CLIN-04/11. |
| 5. Medir/corregir — HTTP y validación técnica | [m7-ws03.js](../../assets/js/clinical/m7-ws03.js) → POST/PATCH `/encounters/{E}/observations[/{id}]` → [ClinicalObservationsRepository](../../api/_lib/clinical_observations.php); `reuse`/`void` explícitos | Código/unidad/valor, hora efectiva/procedencia, versión e idempotencia donde corresponde. Invalida conservando historia. Read [ClinicalMeasurementTrends](../../api/_lib/clinical_measurement_trends.php) sólo proyecta series comparables; registry de referencias no interpreta diagnóstico. CLIN-05/14. |
| 6. Consultar/actualizar longitudinal — HTTP separado | `#lon03b-antecedents`, `#lon04b-problems`, `#lon05b-medications`, `#lon06b-tasks` → rutas `/patients/{P}/longitudinal/*` → servicios `ClinicalLongitudinal*` | Ámbito D/P, versiones/razones y `clinical_longitudinal_idempotency`; cada mutación guarda maestro y audit/resultado en su transacción. Reportado por paciente, prescrito, confirmado, revisado y resuelto son estados diferentes. Una cita vinculada no actualiza automáticamente tarea/medicación. |
| 7. Producir orden/resultado/consentimiento/receta — HTTP y pasos manuales | `#t-estudios .vis06-intent-card`/`.ordcomp-host` dinámicos / `[data-doc-tool="result"]` / paso Documentos / `#t-tratamiento` → compositor original y escritor de familia, F06 | Orden/resultado conserva ítem fuente y propia identidad; consentimiento requiere roles/evidencia; Rx usa capability `prescriptions`, contexto de seguridad read-only y creator del draft. No usar generic documents para saltar un writer especializado. Firma/emitir permanece con gate profesional denegado. |
| 8. Finalizar y leer historia — comando + readers | `[data-m7-section="finalize"]` → POST `/encounters/{E}/finalize`; después `#lon01-history`, timeline/encounter/viewer → GET `/patients/{P}/longitudinal-history`, `/timeline`, `/encounters/{E}` | `clinical_encounter_final_notes` enlaza nota final; E cerrado no admite reescritura. Amendment/versionado es explícito. LON/timeline leen maestro/datos de consulta; no duplican identidad paciente. Caso o documento no permiten otro D. CLIN-06/08/09. |

**Frontera actual:** el recorrido de lectura/borrador está conectado en fuente y tiene
evidencia M5/longitudinal histórica aislada. La autoridad profesional por operación
no está concluida en todos los writers: la contención V bloquea firma/emisión y el
contrato CLIN-AUTH02B1 identifica deuda adicional. No afirmar «consulta médica completa
autorizada» a partir de una sesión/membresía válidas. [Gate profesional](../../api/_lib/clinical_professional_authority.php).

**Auditoría:** longitudinal usa sus tablas de audit en la misma transacción; encounters
conservan actor/fechas/versión, amendments y revisiones. No atribuir un evento
`CanonicalAuditWriter` a cada captura por analogía con Identity/Media.

**Recuperación:** una respuesta perdida se resuelve con clave/lectura durable del
writer, no reinsertando filas; conflictos conservan borrador. M6 selecciona writer
V1 o legacy permitido y nunca autoriza fallback de escritura para un par ya retirado.
[M6 cutover](../../api/_lib/clinical_m6_cutover.php), [write window](../../api/_lib/clinical_m6_write_window.php).

<a id="f06"></a>
<a id="f06-documento-borrador-a-historia"></a>

## F06 — documento, borrador a historia

### F06A — plantilla y Recetas V2

1. **Entrada original:** shell `#t-tratamiento` o acceso global
   `[data-quick-prescription-panel]` → [rec-ux01](../../assets/js/clinical/rec-ux01.js)
   → [prescription-editor-host](../../assets/js/clinical/prescription-editor-host.js)
   monta el mismo editor `#ne_rx_capture`. Paso «Receta» de Consulta conserva
   patient/encounter/return context, sin abrir una consulta implícita.
2. **Plantilla opcional:** `#ne_rx_use_template` → GET CAPI
   `/doctors/{D}/prescription-templates` →
   [clinical_prescription_templates.php](../../api/_lib/clinical_prescription_templates.php).
   Reutiliza sólo contenido de medicación; paciente, firma, autoridad y contexto se
   obtienen de sus fuentes actuales. Create/update/archive/duplicate/delete son
   operaciones distintas con D servidor y CAS; aplicar no emite.
3. **Contexto de seguridad:** GET `/patients/{P}/prescription-context` →
   [ClinicalPrescriptionContext](../../api/_lib/clinical_prescription_context.php)
   → lectores longitudinales de alergias/medicación/problemas y E abierto.
   No inventa diagnóstico ni modifica los maestros consultados. Sin resultado
   disponible muestra indisponibilidad; no inventa un «sin alergias».
4. **Borrador:** `#ne_rx_save_draft` → POST
   `/doctors/{D}/patients/{P}/prescriptions` intent `save_draft`, Idempotency-Key,
   `draft_ref`/`expected_version` →
   [clinical_prescription_v2_write()](../../api/_lib/clinical_prescription_v2.php).
   Valida contexto, ownership del draft, regímenes estructurados y contrato.
   `ClinicalEncounterIntegrityService::idempotentCreate()` coordina ledger, callback
   escritor y resultado; estado generado se ajusta al draft contractual dentro del
   escritor. Error revierte transacción, no reporta guardado por un click.
5. **Preview:** `#ne_rx_preview` → POST `.../prescriptions/preview` → normalizador
   servidor + `clinical_prescription_v2_render()` → `#ne_rx_preview_html`.
   [Presentación](../../api/_lib/clinical_professional_presentation.php) congela facts
   aprobados y modo; preview no crea emisión. Texto de régimens lo genera el
   contrato estructurado, no HTML recibido del navegador.
6. **Firmar:** dibujo/canonical registered image/QR → binding ligado a D/P/DOC,
   creator, documento+versión+fingerprint, rol y digest del artefacto.
   POST intent `apply_signature` y QR médico consultan
   [clinical_professional_authority_decision()](../../api/_lib/clinical_professional_authority.php).
   En este baseline **deniega** aunque haya imagen registrada o binding C4 en otro
   servicio. No tomar old isolated fixture PASS como autorización normal actual.
7. **Emitir:** `#ne_rx_emit_signed` → intent `finalize`; vuelve a comprobar V,
   firma y versión. Sólo el writer canónico persiste snapshot/rendered_text/status y
   completa reissue pendiente. El navegador no concede entitlements/autoridad ni
   puede convertir `signed=true` en firma verificada.
8. **Historia, ver e imprimir:** `.../prescriptions/history` →
   [clinical_prescription_history_fetch()](../../api/_lib/clinical_prescription_history.php)
   y lifecycle projection → lista. [Viewer](../../modules/clinical/ui/viewer.php)
   / [document.php](../../modules/clinical/ui/document.php) leen documento autorizado
   guardado. El HTML histórico permanece su snapshot; el stylesheet compartido
   actual puede cambiar paginación/aspecto y requiere revisión de compatibilidad.
9. **Anular/reemitir:** [rec-ux01](../../assets/js/clinical/rec-ux01.js) dialog
   `[data-rx-lifecycle-form]` → POST `.../prescriptions/{uuid}/void|reissue` →
   [clinical_prescription_lifecycle](../../api/_lib/clinical_prescription_lifecycle.php).
   Locks, motivo y tabla lifecycle generan transición idempotente y borrador sucesor;
   emisión del reemplazo completa linaje y anula original en su límite transaccional.
   V hoy bloquea void/reissue. No se borra ni sobreescribe el contenido del original.

### F06B — familias legales, resultados y adjuntos

- Consentimiento informado, multimedia, responsiva, certificado, nota, documento
  libre, alta, informe e interconsulta usan los formularios originales y el
  dispatcher POST CAPI `/doctors/{D}/patients/{P}/documents`. Cada `document_type`
  selecciona su writer canónico; DOC-01..09 detallan archivos/preview/roles.
- Consentimiento granted necesita firmas doctor y paciente/representante actuales;
  firma remota y anexos de identificación se reclaman para ese mismo documento.
  Un QR consumido en otro documento no se reutiliza. La representación conserva
  nombre/carácter/relación/fundamento; no asigna permiso clínico a ese firmante.
- Órdenes STUDY-02 se agrupan por routing operacional. El lote valida todos los
  grupos/ítems/parámetros antes de escribir. Ledger lote+child hashes y documentos
  quedan en una sola transacción; error del segundo grupo revierte el primero.
  Presets no fabrican otra identidad de estudio. Resultados enlazan documento e
  ítem exactos, con su propia identidad y política de recepción tardía.
- Adjuntos usan [ClinicalMultipartDocumentService](../../api/_lib/clinical_multipart_document_service.php):
  staging opaco → coordinación durable `clinical_binary_uploads` → final create-only
  fuera del webroot → maestro/manifest `clinical_document_binaries`/ledger → commit.
  La operación guarda estado de recuperación si FS/SQL divergen; ante resultado de
  commit ambiguo no elimina ciegamente un archivo posiblemente confirmado.
- GET `/documents/{DOC}/binary/{variant}` →
  [ClinicalPrivateBinaryRetrieval](../../api/_lib/clinical_private_binary_retrieval.php)
  comprueba D/P/E, manifest/hash/tamaño y sirve stream con headers privados.
  Ruta privada/hash no acreditan cifrado de aplicación; no hay URL pública general.
- Historial/replace/replicate genéricos conservan tipo, linaje y scope. Prescription,
  documento libre/alta y generaciones profesionales no pueden usar wrappers legacy
  para saltarse writer, V o M6. `api/clinical-documents.php` es compatibilidad protegida.

**Verificación:** fuente trazada completa en este alcance; pruebas antiguas de
render/binding/lifecycle se localizan mediante E-RX/E-PRESENT/E-M5 del inventario.
No ejecución nueva; firma/emisión profesional **inactive** por gate, alcance de
autorización por operación pendiente. El renderer y UX existentes no deben ser
reconstruidos para resolver esa integración.

<a id="f11"></a>

## F11 — notificación o handoff y expiración segura

### F11A — firma registrada en dispositivo (SIG03B)

```mermaid
sequenceDiagram
  actor Desktop as Cuenta en aplicación original
  participant API as signature-handoff.php API
  participant DB as MySQL + audit canónico
  actor Mobile as Dispositivo con bearer
  participant Store as PrivateMediaStoragePort
  Desktop->>API: POST JSON vacío + CSRF propio
  API->>API: Resolver scope servidor y validar base URL
  API->>DB: Lock perfil, invalidar pendientes, INSERT hash bearer (300s), audit
  API-->>Desktop: id + URL con fragmento bearer (sin imagen)
  Mobile->>API: POST device API token; validar
  Mobile->>API: POST token + PNG
  API->>DB: Revalidar hash/TTL/purpose/creator bajo lock
  API->>Store: PNG normalizado immutable
  API->>DB: Upsert firma + handoff COMPLETED + audit, commit
  Desktop->>API: GET id con scope propio
  API-->>Desktop: COMPLETED / EXPIRED
```

La página es [signature-handoff.php](../../signature-handoff.php), la API del dibujo
es [signature-handoff-device.php](../../api/media/signature-handoff-device.php) y el
coordinador [SignatureHandoffService](../../modules/signatures/SignatureHandoffService.php).
El móvil no inicia sesión como el desktop: el bearer tiene un propósito, doctor y
creator persistidos, hash SHA256 y 300s; no accede a administración ni expediente.
Sólo POST device, sin query/actor recibido; desktop status/cancel comprueba dueño.
Nueva creación invalida la anterior; completado no vuelve a guardar; expirado se
deniega410. Save usa SQL+audit transaccional y storage inmutable con compensación
best effort. Limpiar antiguos pending ocurre oportunísticamente en create y conserva
audit. **No se ejecutó ninguna limpieza durante el mapa.**

`MXMED_SIGNATURE_HANDOFF_BASE_URL` define URL; fallback HTTP loopback existe para QA,
no para smartphone físico. Tener un PNG vigente no satisface binding profesional ni
firma de una versión documental. No hay email/SMS en este flujo: QR/link visible es
el handoff manual, no una entrega externa.

### F11B — QR documental y captura clínica

| Tramo | Productor → consumidor | Tiempo, scope y recuperación |
|---|---|---|
| Crear QR de receta | Desktop `#ne_rx_remote_signature` → POST CAPI `/prescription-qr-sessions` → [clinical_prescription_qr_route()](../../api/_lib/clinical_prescription_qr.php) | A+creator/P/D/DOC/version y V médico; V actual rechaza creación. Si permitido por futura integración, invalida QR anterior y congela review HTML/hash/fingerprint/rol en transacción. |
| Construir destino | API devuelve path `/public/note-capture.html?mode=prescription-signature&token=…`; app.js `new URL(data.mobile_url,location.origin).href` produce URL de QR | En origen desktop `https://localhost:8443`, la construcción **usaría localhost** si esa ruta se alcanza; no se leyó un token ni QR real actual. Smartphone localhost apunta al smartphone. Config del handoff SIG03B no cambia automáticamente este otro constructor. Requiere diagnóstico/decisión de exposición aparte. |
| Revisar y firmar | Móvil GET `/{token}/mobile-context`, POST `/review`, `/signature` → [note-capture.html](../../public/note-capture.html) y handler específico | Bearer documental, no cookie de sesión desktop para móvil; revisión obligatoria, TTL900s para Rx, checksum/versión actual; expired410/stale409 y nuevo QR, no bypass. Médico vuelve a requerir V usando actor persistido. |
| Aplicar/consumir | Desktop GET `/status`, writer `apply_signature` reclama artefacto con el binding de ese draft/version | `uploaded` no significa emitido. Claim transaccional evita reutilización; cambio de contenido invalida. Desktop necesita Identity y scope vigentes; documento no hereda autoridad del teléfono. |
| Otras familias | Nueve `*-qr-sessions` en dispatcher; consentimiento/identidad firmante además `/note-capture-tokens` | Reglas concretas de médico/paciente/representante y tabla de cada handler. Gate profesional común antes de transición; no extrapolar todos los roles desde Rx. Tokens clínicos de este mecanismo no usan necesariamente el mismo almacenamiento hash de SIG03B. |
| Captura multipágina | POST `/mobile-capture-sessions` → [ClinicalMobileCapture](../../api/_lib/clinical_mobile_capture.php) → `/pages`, `/remove`, `/reorder`, `/finalize`, `/cancel`; upload por token | Sesión1h; cada página min900s/fin sesión. Desktop canónico o continuation bearer acotado, open encounter y clasificación. Página token se consume una vez; reorder exige conjunto completo, finalize idempotente. Sólo desktop puede cancelar. |
| Archivos de captura | Móvil file → optimizer/privado staging → thumbnail/manifest → finalize todos documentos/páginas | Locks orden sesión→página/token→encounter. Error antes de commit confirmado cuarentena; commit ambiguo conserva objetos para reconciliar. Expira/cancela sólo sesiones/artifacts transitorios según contrato, historia durable intacta. |

Para captura de imágenes, [clinical_capture_mobile_url()](../../api/_lib/clinical_capture_classification.php)
usa `MXMED_CAPTURE_PUBLIC_ORIGIN` o scheme/host validado de solicitud; su semántica
no es la base de firma registrada ni el constructor relativo de Rx. Ninguno certifica
TLS/ruta accesible desde red móvil. Se conserva el hallazgo original DOC-REC-STD01:
origin desktop conocido no prueba destino QR ni acceso del dispositivo físico.

La emisión email/OTP/notifications de Identity está en el mapa general, con sus
writers/sinks/TTL. No mezclar esos tokens con páginas de firma o suponer una cola
asíncrona donde sólo existe polling del browser.

## Impactos compartidos y recuperación dirigida

| Cambio propuesto | Consumidores impactados y consulta mínima |
|---|---|
| Identity/membresía/sesión | PAT-01/03, CLIN-02..17, readers/document writers y desktop QR; `CanonicalClinicalIdentityResolver`, GallerySessionScope. C4 binding requiere conectar consumidor V; sesión no es credencial médica. |
| Plan/entitlement | `patients` vs `clinical_record` vs `prescriptions`; lectura Rx no debe exigir CR salvo que el endpoint lea expediente; branding/presentación distinto del gate de emitir. |
| Consultorio/perfil/identidad profesional | Snapshot/presentación de documentos, portable order, cabeceras y contexto Agenda; no actualizar snapshots históricos por un cambio público. |
| Datos de contacto | Maestro paciente PAT distinto de teléfonos del médico/consultorio/OTP; contacto de administración no es público ni contacto del firmante por equivalencia de nombres. |
| CSS/renderer | Preview, viewer, print y documentos históricos; `clinical-doc-base.css` compartido puede repaginar snapshot sin alterar HTML. Reusar fuente, evaluar compatibilidad, no regenerar documentos reales. |
| Catálogo/parámetro de estudio | Search → compositor → snapshot → portable/PDF → provider matching → resultado exacto; versionar snapshot; histórico no se reinterpreta con catálogo vivo. |
| FS/SQL y commit | DOC-15/multipágina: reconciliación/quarantine controlada; no borrar archivo tras commit ambiguo ni ejecutar reconcile de producción al leer este mapa. |

Para «¿qué permite firmar?» comenzar por DOC-12/SIG-03 y función V, después C4;
para «¿quién posee paciente vs historia?» PAT-01/03 y CLIN-04/10..14. Para «¿por qué
una prueba vieja no describe hoy?» E-ACT04 y E-STUDY separan cambios posteriores,
contadores históricos y gates profesionales. No declarar esos tests nuevamente
fallidos por leer un cierre antiguo.

Mantenimiento: conservar IDs F05/F06/F11 y links a capacidades; actualizar conexión,
transacción, ejes y evidencia sólo tras cambios autorizados. No añadir conversaciones,
tokens, casos reales ni comandos de activación. Siguiente paso de producto:
**NONE_PENDING_DIRECTOR_REVIEW**.
