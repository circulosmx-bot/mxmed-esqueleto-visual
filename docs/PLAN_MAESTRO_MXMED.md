# PLAN MAESTRO MXMED (Documento Vivo)

Fuente principal (base de estado actual): `docs/MAPA_TOTAL_SISTEMA_MXMED.md`.
Este plan maestro NO duplica ese mapa completo; lo usa como referencia operativa y concentra gobernanza transversal: checklist + decisiones + roadmap + fuentes.

> Este documento reemplaza al “Documento Limpio FSD Maestro” como fuente viva de coordinación.
> El FSD queda absorbido por este Plan Maestro; todo hallazgo nuevo de fuentes/PDFs se traduce obligatoriamente a Decision Log + Checklist + Backlog.

## A. Propósito y principios (Etapa 1 = perfil médico)

### Propósito
Establecer una fuente única de ejecución para MXMed que permita decidir, priorizar y validar avances sin perder consistencia entre módulos.

### Prioridad absoluta actual (Etapa 1)
Perfil médico end-to-end:
- Patients
- Agenda
- Clinical API (timeline/encounters/documents)
- Cases
- Clinical UI (historial/encounter/document embed)
- Integración con UI principal

### Principios vinculantes
- Cambios pequeños y reversibles (1 commit por capa).
- No duplicar fuentes de verdad (`docs/clinical/DECISION_FUENTES_DE_VERDAD.md`).
- Contratos y naming canónicos antes de implementar.
- Compatibilidad embebido/standalone obligatoria para UI clínica.
- Toda fuente nueva revisada debe traducirse a: Decision Log + Checklist + Backlog.

## A1. Estado actual (punto de control)
- Fecha: 2026-02-25
- Rama: main
- Últimos commits relevantes: `cc0da8f` (plan maestro), `e7cbe9b` (links.appointment_id preferido), `9d124a8` (seed_encounter + encode encounter_key)
- Objetivo inmediato actual: Integración transversal / Casos (Camino 2)
- Regla: antes de iniciar cualquier trabajo nuevo, revisar esta sección.

## A1.1 Estado operativo actualizado (P15–P16)

- Fecha de actualización: 2026-03-31
- Rama operativa reciente: `mxmed-p16-modal-actividad-clinica`
- Fase actual: **P16 (UX/funcional en Historial + Actividad clínica + Casos clínicos)** sobre base estable de P15.

✅ RESUELTO:
- Estabilidad al abrir Historial de atención en host principal (sin congelamiento operativo reportado en la ruta estable).
- Eliminación de disparos repetitivos/loops de consulta en rutas críticas de historial (control operacional consolidado en la base estable de P15).
- Modal expandido de Historial implementado y utilizable desde el flujo actual.
- Robustecimiento del bridge host↔embed en `index.html`:
  - el shell acepta `postMessage` desde el `contentWindow` raíz del iframe clínico y también desde frames descendientes/anidados;
  - se endurece el enrutamiento de eventos en vistas embebidas/expandibles y se reducen fallas intermitentes de comunicación.
- Homologación visual progresiva de cards del historial (compactación, jerarquía y consistencia de acciones).
- Acciones secundarias homologadas en formato tipo link discreto (en lugar de CTAs primarios tipo botón).
- Limpieza de headers/textos redundantes en bloques clínicos internos de historial.
- Integración a casos clínicos reforzada:
  - búsqueda de `owner_case` acotada al paciente actual en backend (`api/clinical/index.php`), evitando colisiones entre pacientes distintos;
  - conflicto `owner_case_id` coherente con lista visible del paciente
  - estado contextual `Ya integrado` en el caso propietario dentro del modal.
- Selección de caso mejorada en modal de integración:
  - selección por radio
  - selección por click en todo el recuadro de la fila.
- Separación práctica entre timeline clínico y acciones contextuales (sin mezclar lógica clínica con ornamentación UX).

Pendiente dentro de P16 (abierto):
- Convergencia funcional final hacia Historial de atención como eje operativo único:
  - desacople técnico de dependencias residuales de `#t-notas` (Actividad clínica) sin romper launchers/modales ya estabilizados.
- Cerrar consistencia UX final del modal “Integrar a caso clínico” en escenarios borde (conflictos + cambio de caso activo en la misma sesión).
- Homologación completa card↔detalle para todos los tipos en vista Casos clínicos expandida (paridad 100% con Historial general).
- Cierre documental de criterios de aceptación P16 para pasar a fase siguiente sin deuda visual residual.

## A1.2 Estado operativo actualizado (Agenda + Operadores)

- Fecha de actualización: 2026-05-26
- Estado de cierre: **Agenda · Hecho v1 funcional consolidado, con deudas documentadas**
- Criterio operativo: Agenda se considera funcionalmente consolidada en v1 para operación principal, con BLOQ-F3 cerrado funcionalmente y deudas técnicas/documentales explícitas que no bloquean el cierre actual.

### Concluido (v1 funcional consolidado)
- Semana custom operativa.
- Día custom operativa.
- Sincronía operativa Día ↔ Semana consolidada.
- Vista Mes oculta/no operativa.
- Nueva cita refinada.
- Badge `AHORA`.
- Estados de cita operativos en shell:
  - confirmar cita manual privada;
  - reprogramar cita confirmada reinicia confirmación;
  - `no_show` bloqueado para citas futuras;
  - cancelación operativa.
- Waitlist MVP desde “Buscar siguiente cita disponible”.
- Resolver hueco MVP post-cancelación.
- Resolver hueco A1: ranking, máximo 5, estado vacío y colisión.
- Hardening B1: `__all__` no puede ser destino de cita.
- B2-A: `consultorio_scope` compatible.
- BLOQ-F2 backend canónico de bloqueos cerrado (`GET/POST/PATCH /availability/blocks`).
- BLOQ-F3 funcional:
  - lectura backend de bloqueos;
  - bloqueo parcial backend;
  - desbloqueo parcial/conjunto backend;
  - desbloqueo granular de horario (split seguro);
  - desbloqueo de día desde header con detector de cobertura total.
- Strict operator enforcement activo en backend para rutas privadas elegibles.

### Parcial / deuda documentada (no bloqueante para cierre funcional actual)
- Retiro/degradación progresiva de `localStorage` en bloqueos del shell pendiente.
- Auditoría canónica dedicada de bloqueos/desbloqueos (`availability_blocked` / `availability_unblocked`) pendiente.
- Operadores UI híbrida/local vs backend autoritativo.
- Validación strict operator en sesión UI real completa.
- Migración real de `consultorio_scope` en ambientes existentes.
- Eventual encapsulación/retiro progresivo de `__all__`.
- `operator_identity_db_not_ready` -> `503`.
- Subtab completa Waitlist futura.
- UX futura de Resolver hueco.

### No tocar sin QA
- Anclaje de Semana al día actual y sincronía Día ↔ Semana.
- Regla de domingo/feriado en Día: visible, sin inventar disponibilidad ni bloqueos ficticios.
- Waitlist no representa cita confirmada ni garantiza disponibilidad anticipada.
- `__all__` es sentinel transicional de alcance, nunca destino de cita.

## A2. Cierre clínico reciente (Encounter/Historial/Timeline)

### Estado actual (cerrado)
- STEP A: Flujo `Historial -> Ver atención -> Encounter -> Volver` cerrado (`40f80bd`, `e4f5de5`, `7d08e63`).
- STEP B: UX documentos en Encounter (preview + `Ver todos`/`Ver menos`) cerrado (`b704ebd`).
- STEP C: Timeline payload optimizado cerrado (`6927d0f`):
  - `clinical.documents_count`
  - `clinical.documents_preview` (max 3)
  - `clinical.documents` completo solo con `include_docs=1`
- STEP D: Historial muestra `Documentos: N` (+ preview) y modal `Ver todos` cierra correctamente (`9ac8922`, `6283967`).

### Decisiones de arquitectura
- Timeline = **RESUMEN** (sin lista completa de documentos por default).
- Encounter = **DETALLE** (fuente de verdad para listado completo de documentos).
- `include_docs=1` se mantiene para compatibilidad/debug controlado.

### Rutas / contratos relevantes
- UI:
  - `/modules/clinical/ui/historial.php?patient_id=...`
  - `/modules/clinical/ui/encounter.php?encounter_key=...`
- API:
  - `GET /api/clinical/index.php/patients/{patient_id}/timeline?include=agenda,clinical&limit=...`
  - `GET /api/clinical/index.php/patients/{patient_id}/timeline?...&include_docs=1` (compat)
  - `GET /api/clinical/index.php/encounters/{encounter_key}`

### QA mínimo (manual)
- Paciente de prueba: `p_0c874aa9cbad`
- Encounter de prueba: `appt:fe61cdd67e97dcfde3a70c02#enc:2` (en URL por path debe ir URL-encoded).
- Checklist:
  1. En Historial, `Ver atención` abre Encounter y `Volver` regresa al flujo.
  2. En Encounter con volumen alto, documentos inician colapsados (10 de N), `Ver todos` expande, `Ver menos` colapsa.
  3. En Timeline, cards de encounter muestran `Documentos: N` + preview (si N>0).
  4. Modal `Ver todos` del Historial cierra con `X`, botón `Cerrar`, backdrop y `ESC`.

### Retrocesos / reverts (operación segura)
- `git revert 6283967` -> revierte fix de cierre del modal `Ver todos` en Historial.
- `git revert 9ac8922` -> revierte visualización `Documentos: N` + preview en Historial.
- `git revert 6927d0f` -> revierte optimización de payload timeline (`documents_count`/`documents_preview`).
- `git revert b704ebd` -> revierte colapsado de lista de documentos en Encounter.
- `git revert 7d08e63` -> revierte fix de fetch/encoding/base en Encounter.

## Clinical — Checkpoints, contratos y decisiones (Feb 2026)

### A) Checkpoints (tags)
- `mxmed-clinical-checkpoint-encounter-timeline-v1`: checkpoint de integración encounter/timeline (flujo base clínico).
- `mxmed-clinical-uploads-v1`: valida pipeline de uploads clínicos con optimización automática (GD/EXIF + derivados).
- `mxmed-clinical-doc-actions-v1`: valida barra/menú de acciones por tipo en UI de documento y flujo de viewer.
- `mxmed-clinical-doc-replicate-ui-v1`: valida milestone de replicación controlada (API + UI) con redirección al nuevo documento.

### B) Contratos
- Timeline:
  - `clinical.documents_count`
  - `clinical.documents_preview` (<=3)
  - `include_docs=1` opcional para compat/debug.
- Uploads:
  - `payload_json.file.render_mode`
  - `payload_json.file.optimized`
  - `payload_json.file.thumb`
  - Nota explícita: `payload_json.file.original.path` **NO existe** actualmente.
- UI Documento:
  - `document.php` con dropdown **Acciones** por tipo.
  - `viewer.php` con visualización de imagen y modo `fullscreen`.

### C) Decisiones
- No se edita documento generado.
- Replicar = crear documento **nuevo** con `source_document_uuid`.

### D) Estado replicación (implementado)
- Endpoint implementado: `POST /documents/{uuid}/replicate` (QA PASS).
- UI implementada: `document.php` incluye acción **Replicar (crear copia)** en menú **Acciones**; llama al endpoint y redirige a `document.php?uuid=<new_uuid>` preservando parámetros embed (`carry_embed_params`).

### A3 — PDF Viewer Minimalista (v1)

Estado: ✅ QA PASS  
Tag sugerido: mxmed-clinical-pdf-viewer-v1  

#### Backend
- Soporte upload `application/pdf`
- Guarda archivo original en:
  `storage/clinical_uploads/YYYY/MM/{uuid}-orig.pdf`
- `render_mode = pdf`
- Compatible con flujo existente de imágenes
- No se modificó estructura DB

#### UI
- `viewer.php` soporta `render_mode=pdf`
- iframe nativo del navegador
- Layout app-like (100vh, flex vertical)
- Acciones disponibles:
  - Volver
  - Descargar
  - Imprimir
  - Abrir en pestaña
- Alias `doc_uuid` aceptado además de `uuid`

#### QA realizado
- Upload PDF vía multipart POST
- Verificación GET encounter
- Visualización iframe
- Descarga funcional
- Impresión funcional

Este milestone no altera contratos existentes y mantiene compatibilidad total con imágenes.

### A4 — Casos: Single-owner por item (v1)

- Regla: `(item_type + item_ref)` solo puede pertenecer a un caso.
- `POST /cases/{case_id}/items`:
  - `409 conflict` si ya pertenece a otro caso, incluye `data.owner_case_id`
  - idempotente si ya pertenece al mismo caso: `ok true`, `created false`, `message "item already assigned"`
- Sin cambios de DB (enforcement por consulta previa).
- Tag: `mxmed-clinical-cases-single-owner-v1`

### A5 — Diagnóstico → Caso clínico + Bitácora (v1)

1. Concepto y regla:
- El “Caso clínico” es el dueño de la “bitácora de diagnósticos”.
- La “Atención del día” (episodio) solo muestra el diagnóstico principal vigente si está integrado a un caso.

2. Bitácora (modelo conceptual):
- Cada captura de diagnóstico crea una entrada con:
  - texto
  - timestamp del sistema
  - origen (receta / nota / etc.) (opcional v1 pero recomendado)
- El diagnóstico más reciente pasa a ser el “principal”.

3. UX gatillos:
- A) Si el episodio NO está integrado a un caso:
  - Modal bloqueante “Crear caso clínico”
  - Input “Nombre del caso” prellenado con el diagnóstico capturado
  - Acciones:
    - “Crear e integrar”
    - “Ahora no” (cierra sin crear; el diagnóstico se guarda donde se capturó, pero no crea bitácora del caso)
- B) Si el episodio YA está integrado a un caso:
  - Actualiza diagnóstico principal del caso
  - Agrega entrada a bitácora
  - Confirmación ligera (no bloqueante)

4. Visual:
- En cards integradas: badge destacado “Caso: …”
- Línea adicional discreta: “Diagnóstico: …” (si existe)
- En vista del caso: principal + lista de historial con timestamps

5. Alcance v1:
- Inicia con el campo de diagnóstico que ya exista (por ejemplo receta), pero regla aplica a cualquier futura captura de diagnóstico (nota, paciente, etc.).

6. Tag sugerido:
- `mxmed-clinical-dx-case-bitacora-v1`

### A6 — UX Integrar a caso (modal único select/crear) (v2)

- El botón “Integrar a caso clínico” siempre abre un modal único.
- El modal permite:
  - Seleccionar un caso existente (caso activo preseleccionado si existe).
  - Crear un nuevo caso e integrar en el mismo flujo.
- Manejo de `409 conflict`:
  - Si el item ya pertenece a otro caso, mostrar mensaje dentro del modal.
  - Ofrecer botón “Activar caso #X” cuando backend devuelve `owner_case_id`.
- No hubo cambios de backend en esta iteración.
- Tag asociado: `mxmed-clinical-cases-integrate-ui-v2`

### A7 — Catálogo clínico controlado (v1)

- Catálogo controlado a nivel evento:
  - Source of truth finito para `category` + `subtype`, con labels y prioridad.
  - Aplica sobre cada item del timeline; no redefine la jerarquía visual del día.
- Campos nuevos agregados al JSON de `GET /patients/{patient_id}/timeline`:
  - `category`
  - `subtype`
  - `category_label`
  - `subtype_label`
- Reglas UX:
  - Día > Eventos > Chips
  - Cada evento muestra chips compactos de clasificación.
  - Cada día deriva un resumen compacto de categorías presentes (máx 3, ordenado por prioridad).
  - Filtros rápidos por categoría operan client-side sobre eventos; si un día queda sin eventos visibles, se oculta.
- Nota explícita:
  - Sin DB changes.
  - Compatibilidad preservada: no se renombra ni elimina ningún campo existente del timeline.
- Checklist QA propuesto:
  - Confirmar que appointment / encounter / document reciben `category` y `subtype` en el JSON.
  - Confirmar fallback `other/unknown` cuando no hay match.
  - Confirmar chips visibles por evento en Historial.
  - Confirmar resumen diario derivado y ordenado por prioridad.
  - Confirmar que el filtro por categoría oculta eventos y días vacíos sin romper embed ni navegación existente.

### A7.1 — Catálogo clínico controlado (v1.1: grupo/área/fase)

- Objetivo:
  - Extender A7 con una capa compatible de `catalog_group` / `catalog_phase` sin DB changes.
  - Mantener intactos `category`, `subtype`, `category_label` y `subtype_label`.
- Mapping técnico `document_type -> group/phase`:
  - `note` -> `attention` / `null`
  - `nota_evolucion` -> `attention` / `null`
  - `lab_order` -> `studies` / `order`
  - `lab_pdf` -> `studies` / `result`
  - `imaging_order` -> `studies` / `order`
  - `image` -> `media` / `null`
  - `orders` -> `orders` / `null`
- Contrato nuevo por item del timeline:
  - `catalog_group`
  - `catalog_group_label`
  - `catalog_phase`
  - `catalog_phase_label`
  - `catalog_priority`
- Reglas UX:
  - Filtros rápidos por `catalog_group` (Todo + grupos presentes).
  - Chips compactos por evento usando grupo + fase cuando aplique.
  - Modelo A intacto: Día > Eventos, sin romper agrupación por `YYYY-MM-DD`.
- Checklist QA propuesto:
  - Confirmar `php -l` en API, helper compartido y `historial.php`.
  - Confirmar que todos los items del timeline traen `catalog_group`.
  - Confirmar que `lab_order`, `lab_pdf`, `imaging_order`, `image`, `orders`, `note` y `nota_evolucion` caen en el grupo/fase correcto.
  - Confirmar filtros por grupo en Historial y ocultamiento de días vacíos.
  - Confirmar que embed y CTAs existentes siguen operando sin cambios.

### FASE: Bundle Clinical Block v1 (solo lectura + render en viewer)

- Decisión:
  - Reusar el mismo `bundle_id` en `clinical_documents` para admitir un documento lógico `bundle_clinical` sin DB changes.
  - Mantener compatibilidad: el endpoint del bundle sigue devolviendo `items`; solo agrega soporte de lectura para `bundle_clinical`.
- Backend:
  - `GET /bundles/{bundle_id}/documents` incluye `bundle_clinical` cuando exista.
  - El `bundle_clinical` se ordena primero y las imágenes conservan su orden estable actual.
- Viewer:
  - Si el bundle trae `bundle_clinical`, renderiza arriba del visor un bloque “Interpretación del estudio”.
  - Secciones opcionales: `summary`, `interpretation`, `observations`.
  - Si no existe `bundle_clinical`, el layout y la navegación siguen igual.
- Compatibilidad:
  - Sin cambios de auth, agenda, timeline, embed ni encounter.
  - Sin tablas nuevas en esta fase.

### FASE: Timeline Semántico v1.1 (procedimiento + fixture QA + hidratación + tab UI)

- Estado:
  - Cerrada.
  - No reabrir fases cerradas.
- Alcance cerrado:
  - P1: `immunization` clasifica como `procedimiento` en timeline.
  - P2: fixture QA estable para paciente/demo de inmunización.
  - P2.1: hidratación de items timeline desde `clinical_documents` con `id`, `document_type`, `occurred_at`, `title`.
  - P3: tab UI `Procedimientos` en Historial.
- Commits exactos:
  - `69e2f42` `clinical api: timeline classify immunization as procedimiento`
  - `47e0c29` `clinical api: hydrate timeline document items with id type occurred_at title`
  - `eb99d97` `clinical ui: add Procedimientos tab to timeline`
- Fixture QA registrado:
  - DB: `patients_patients.patient_id = p_demo_immun_01`
  - DB: `clinical_documents.id = 89`
  - DB: `clinical_documents.document_uuid = 7dbf2a93-7d0d-4fe7-a612-4bdca0dc4512`
  - Expectativa contractual en timeline para ese documento:
    - `document_type = immunization`
    - `clinical_category = procedimiento`
    - `study_role = null`
    - `occurred_at != null`
    - `title != null`
- Gateway passthrough QA mínimo requerido:
  - Shape mínimo soportado por `POST /api/clinical/index.php/documents` para documento clínico tipo passthrough:
    - `type`
    - `actor.user_id`
    - `context.patient_id`
  - Ejemplo curl QA:
```bash
PATIENT_ID="p_demo_immun_01"
curl -sS -X POST "http://127.0.0.1:8091/api/clinical/index.php/documents" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "immunization",
    "title": "Vacunación influenza",
    "event_datetime": "2026-03-02 10:30:00",
    "summary": "Aplicación de vacuna influenza estacional",
    "actor": {
      "user_id": "qa"
    },
    "context": {
      "patient_id": "'"$PATIENT_ID"'"
    },
    "payload": {
      "vaccine_name": "Influenza tetravalente",
      "dose": "0.5 mL",
      "route": "IM",
      "site": "Deltoides izquierdo",
      "lot": "LOT-12345"
    }
  }' | jq
```
- Evidencia QA:
  - `php -l api/clinical/index.php` OK en P1 y P2.1.
  - `php -l modules/clinical/ui/historial.php` OK en P3.
  - `GET /api/clinical/index.php/patients/p_demo_immun_01/timeline?include=agenda,clinical&limit=50` devuelve item con:
    - `document_type: "immunization"`
    - `clinical_category: "procedimiento"`
    - `study_role: null`
    - `occurred_at: "2026-03-02 05:52:17"`
    - `title: "Immunization"`
  - `GET /modules/clinical/ui/historial.php?patient_id=p_demo_immun_01&embed=1` expone tab `Procedimientos` y el item demo con `data-clinical-category="procedimiento"`.
  - Nota QA filtro `Procedimientos`:
    - El filtro sí funciona; inicialmente no se notaba porque el paciente demo estaba dominado por items `procedimiento`.
    - Para comprobarlo visualmente se creó un documento no-procedimiento (`type = note`) sobre el mismo `patient_id`.
    - En tab `Todo` aparecen la nota y los procedimientos.
    - En tab `Procedimientos` la nota desaparece y quedan sólo los items `procedimiento`.
    - Curl usado para crear la note QA:
```bash
curl -sS -X POST "http://127.0.0.1:8091/api/clinical/index.php/documents" \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "type": "note",
    "title": "Nota QA filtro Procedimientos",
    "actor": {
      "user_id": "qa"
    },
    "context": {
      "patient_id": "p_demo_immun_01"
    },
    "payload": {
      "text": "Nota de control para validar filtro de Procedimientos."
    }
  }' | jq
```
    - Ejemplo de conteo `by_cat` ya con mezcla real:
```json
{
  "consulta": 2,
  "procedimiento": 10
}
```

#### Regla oficial de captura y render: `document_type = immunization`

- Estado:
  - Vigente.
  - No reabrir fases cerradas.
- Lugar de aplicación:
  - Obligatorio.
  - Se guarda en `payload.administration`.
  - Campos oficiales:
    - `place_type` (enum)
    - `place_name` (string según caso)
    - `place_sector` (opcional)
  - Catálogo simple inicial:
    - `consultorio_prop`: no pide nombre.
    - `institucion`: pide `place_name` y acepta `place_sector` (`publica` o `privada`).
    - `otro`: pide `place_name`.
- Fabricante:
  - Opcional.
  - Texto libre.
  - Se guarda en `payload.vaccine.manufacturer`.
  - No se cataloga en esta etapa por impráctico.
- Compatibilidad de payload:
  - Se mantiene soporte al payload plano actual:
    - `vaccine_name`
    - `lot`
    - `dose`
    - `route`
  - Se habilita payload estructurado futuro:
    - `payload.vaccine.product_name`
    - `payload.vaccine.manufacturer`
    - `payload.trace.lot`
    - `payload.schedule.dose_volume`
    - `payload.schedule.route`
    - `payload.schedule.site`
    - `payload.administration.place_type`
    - `payload.administration.place_name`
    - `payload.administration.place_sector`
    - `payload.notes.clinical`
- Prioridad oficial de visualización en timeline:
  - lugar de aplicación
  - nombre de vacuna
  - fabricante
  - lote
  - dosis/vía
  - nota
- Commit exacto de documentación:
  - `docs: define immunization required administration place and manufacturer free-text`

#### Procedimiento Genérico v1 (base para immunization + medication_administration)

- Estado:
  - Vigente.
  - No reabrir fases cerradas.
- Objetivo:
  - Unificar captura/render de procedimientos recurrentes (vacunas, aplicaciones de medicamento, curaciones, etc.).
  - Estandarizar `lugar`, `notas`, `insumo`, `trazabilidad`, sin forzar catálogos gigantes.
- Semántica:
  - `clinical_category = "procedimiento"`
  - `study_role = null`
  - `document_type` específicos se mapean a procedimiento (lista extensible):
    - `immunization` (cerrado)
    - `medication_administration` (nuevo piloto P9)
- Payload: shape genérico (v1):
  - `payload.administration` (obligatorio en procedimientos):
    - `place_type`: enum `["consultorio_prop","institucion","otro"]`
    - `place_name`: string (obligatorio si `institucion` o `otro`)
    - `place_sector`: enum `["publica","privada"]` (opcional; solo `institucion`)
  - `payload.notes` (opcional):
    - `clinical`: string
  - `payload.trace` (opcional):
    - `lot`: string
  - `payload.item` (opcional, genérico para “lo aplicado/realizado”):
    - `kind`: string (ej. `"vaccine" | "medication" | "material" | "procedure"`)
    - `name`: string (nombre humano del insumo o procedimiento)
    - `manufacturer`: string (texto libre, opcional)
    - `dose`: string (texto libre, opcional)
    - `route`: string (texto libre, opcional)
- Compatibilidad:
  - Para inmunización mantener compat con payload plano existente:
    - `vaccine_name`
    - `lot`
    - `dose`
    - `route`
  - Para `medication_administration` se permitirá compat plano opcional futuro (no definir aún si no existe).
- Prioridad de render en timeline (procedimiento):
  - `Aplicada/realizada en: <place>`
  - `Qué: <item.name>` (o `vaccine.product_name` si aplica)
  - `Fabricante` (si existe)
  - `Lote / dosis / vía` (si existen)
  - `Nota clínica` (si existe)
- Reglas de catálogo:
  - Catálogos solo como ayuda de captura en UI (no son truth source).
  - Persistir `catalog_key` cuando aplica (como en vacunas).
  - Siempre guardar `name` final como texto en payload (`product_name` / `item.name`).
- Commit exacto de documentación:
  - `docs: define procedimiento generico v1 contract for future procedures`

#### Cierre P9 — medication_administration (piloto procedimiento)

- Semántica:
  - `document_type = medication_administration`
  - `clinical_category = procedimiento`
  - `study_role = null`
- Commits de soporte (exactos):
  - `dd10eec` `clinical api: classify medication_administration as procedimiento`
  - `2e8d241` `clinical api: include medication_administration payload in timeline preview`
  - `99f82fe` `clinical ui: render medication_administration timeline item from procedimiento payload`
- Fixture / evidencia QA (este entorno):
  - `patient_id: p_demo_immun_01`
  - Documento creado vía gateway:
    - `document_db_id: 95`
    - `document_uuid: fb3e0df0-3694-459d-b19a-8ff9dd7908b6`
  - Payload ejemplo:
    - `item`: Ketorolaco, `30 mg`, `IM`
    - `administration.place_type`: `consultorio_prop`
    - `notes.clinical`: `Aplicación por dolor agudo.`
- UI: qué se ve
  - `Aplicación de medicamento: Ketorolaco`
  - `30 mg · IM`
  - `Aplicada en: Consultorio`
  - `Nota: Aplicación por dolor agudo.`
- Nota de gobernanza:
  - No reabrir fases cerradas.
  - Próximo paso sugerido: `P10 Curación/procedimiento menor` (opcional) o extender catálogo.
- Commit exacto de documentación:
  - `docs: close P9 medication_administration piloto in plan maestro`

#### Cierre P9.1 — procedure ligado a appointment (timeline + UI)

- Estado:
  - Integrado y validado.
  - No reabrir fases cerradas.
- Qué cambió:
  - Timeline backend ahora incluye documentos `procedure` aunque tengan `appointment_id`.
  - Regla aplicada en `api/clinical/index.php`:
    - `clinical_timeline_documents_fetch()` permite `appointment_id IS NULL OR document_type = 'procedure'`.
  - Preview slim de timeline para documentos ahora expone `clinical_document.context.appointment_id` cuando existe.
  - Fuente primaria para `appointment_id` en preview slim:
    - `clinical_document.context.appointment_id`
  - Fallback de compatibilidad:
    - `clinical_document.payload.context.appointment_id` / `links.appointment_id`
  - UI de historial agrega botón `Registrar procedimiento realizado` dentro de `mm-activity-actions` para appointments clasificados como `procedimiento`.
  - El handler `data-action="register-procedure"` ya no hace POST directo:
    - abre `openGenericProcedureModal('other_procedure', defaults)`
    - precarga `appointmentId`, `defaultTitle`, `defaultDatetime`
    - precarga nota `Registrado desde agenda`
    - `defaultDatetime` sale de `start_at` / `event_datetime` y la UI lo convierte a formato `datetime-local` vía `toDatetimeLocalValue`
  - `submitGenericProcedure()` ahora:
    - usa `resolveClinicalActorUserId()` para `actor.user_id`
    - envía `payload.notes` como string simple
    - mantiene `context.appointment_id` desde `genericProcedureAppointmentId`
- Por qué:
  - Los `procedure` creados desde agenda estaban persistidos en `clinical_documents`, pero no aparecían en timeline si tenían `appointment_id`.
  - La UI necesitaba un flujo consistente para pasar de `appointment` programado a `procedure` realizado sin duplicar lógica ni romper embed.
- QA manual (comandos usados):
  - Confirmar que timeline ya incluye `procedure` en tipos únicos:
    - `include=clinical` es suficiente para validar documentos; `agenda` no es necesario en esta comprobación.
```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/patients/p_0c874aa9cbad/timeline?include=clinical&limit=100" \
  | jq -r '[.data.items[] | select(.item_type=="document") | .clinical_document.document_type] | unique'
```
  - Salida validada:
```json
[
  "bundle_clinical",
  "image",
  "immunization",
  "note",
  "procedure"
]
```
  - Confirmar que `procedure` expone `appointment_id` en preview slim:
```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/patients/p_0c874aa9cbad/timeline?include=clinical&limit=100" \
  | jq '[.data.items[] | select(.item_type=="document" and .clinical_document.document_type=="procedure") | {document_uuid: .clinical_document.document_uuid, appointment_id: (.clinical_document.context.appointment_id // .links.appointment_id // .clinical_document.payload.context.appointment_id // null)}]'
```
  - Salida validada:
```json
[
  {
    "document_uuid": "62fcc146-6999-4e0b-87ae-20606be3b4bf",
    "appointment_id": "fe61cdd67e97dcfde3a70c02"
  },
  {
    "document_uuid": "017e66d2-5250-4c32-a09b-b412ec056a48",
    "appointment_id": "8928a5144fed68f1731f44b7"
  }
]
```
  - Confirmar sintaxis:
```bash
php -l api/clinical/index.php
php -l modules/clinical/ui/historial.php
```
- Commits exactos:
  - `a2142d6` `button + POST inicial`
  - `d61042e` `register-procedure abre modal; submit usa actor id + notes string`
  - `8d2cfbc` `timeline incluye procedure con appointment_id + expone appointment_id en slim preview`
  - `e43a27c` `documents allow linking procedure to appointment_id en persistencia/payload`

#### Cierre P9.2 — Cerrar consulta + Nota clínica AUTO (Cierre)

- Estado:
  - Integrado y validado.
  - No reabrir fases cerradas.
- Qué se implementó:
  - `Consulta = Encounter` ahora tiene cierre formal vía `POST /encounters/{encounter_key}/finalize`.
  - El cierre genera o actualiza una `Nota clínica AUTO (Cierre)` idempotente por consulta.
  - La vista `encounter.php` muestra sección `Cierre` con:
    - botón `Cerrar consulta` mientras el encounter está `open`
    - badge `Consulta cerrada` cuando el encounter ya está `closed`
    - acceso a `Ver Nota clínica AUTO (Cierre)`
- Qué cambió:
  - `api/clinical/index.php`
    - `clinical_encounters_ensure_schema()` ahora asegura columnas:
      - `status`
      - `closed_at`
      - `closed_by_user_id`
      - `auto_note_uuid_final`
    - normaliza encounters legacy `completed` a `open` si aún no están cerrados.
    - agrega `clinical_encounter_finalize(...)` y `clinical_encounter_final_note_upsert(...)`.
    - nuevo endpoint:
      - `POST /encounters/{encounter_key}/finalize`
    - `GET /encounters/{encounter_key}` ahora devuelve:
      - `status`
      - `closed_at`
      - `closed_by_user_id`
      - `auto_note_uuid_final`
      - buckets clínicos (`vitals`, `notes`, `prescriptions`, `orders`, `results`, `procedures`)
  - `modules/clinical/ui/encounter.php`
    - mantiene el patrón actual de apertura desde historial
    - muestra agenda real y eventos de agenda dentro de la consulta
    - agrega flujo UI de cierre con confirmación y refresh
    - si hay recetas:
      - una receta: abre directo
      - varias: lista sólo las recetas de esa consulta
- Por qué:
  - la información clínica cambia con el tiempo y la consulta necesitaba un snapshot final explícito, estable y legible.
  - el estado formal `closed` evita depender de inferencias por documentos sueltos.
- Contrato de la nota AUTO final:
  - `document_type = note`
  - `title = Nota clínica AUTO (Cierre)`
  - `event_datetime = datetime original de la consulta`
  - `payload.auto_generated = true`
  - `payload.snapshot_type = encounter_auto_final`
  - `payload.finalized = true`
  - `payload.context.patient_id / appointment_id / encounter_id / encounter_key`
  - `payload.snapshot.counts`
  - `payload.snapshot.documents`
- QA manual (comandos usados):
  - Sintaxis:
```bash
php -l api/clinical/index.php
php -l modules/clinical/ui/encounter.php
```
  - Antes de cerrar, verificar `status=open`:
```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2" \
  | jq '{status: .data.status, closed_at: .data.closed_at, auto_note_uuid_final: .data.auto_note_uuid_final, prescriptions: (.data.prescriptions|length)}'
```
  - Cerrar consulta:
```bash
curl -sS -X POST "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2/finalize" \
  -H "Content-Type: application/json" \
  -d '{"actor":{"user_id":"qa"}}' | jq
```
  - Validar estado cerrado e idempotencia:
```bash
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2" \
  | jq '{status: .data.status, closed_at: .data.closed_at, auto_note_uuid_final: .data.auto_note_uuid_final}'
```
```bash
curl -sS -X POST "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2/finalize" \
  -H "Content-Type: application/json" \
  -d '{"actor":{"user_id":"qa"}}' \
  | jq '{status: .data.status, closed_at: .data.closed_at, auto_note_uuid_final: .data.auto_note_uuid_final, counts: .data.counts}'
```
  - Validar documento final:
```bash
AUTO_UUID=$(curl -sS "http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2" \
  | jq -r '.data.auto_note_uuid_final')

curl -sS "http://127.0.0.1:8091/api/clinical/index.php/documents/$AUTO_UUID" \
  | jq '{title: .data.document.title, event_datetime: .data.document.ui.event_datetime, snapshot_type: .data.document.content.payload.snapshot_type, auto_generated: .data.document.content.payload.auto_generated, finalized: .data.document.content.payload.finalized, context: .data.document.content.payload.context}'
```
  - Validar UI:
```bash
curl -sS "http://127.0.0.1:8092/modules/clinical/ui/encounter.php?encounter_key=appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2&embed=1" \
  | rg -n "Cierre|Consulta cerrada|Nota clínica AUTO \\(Cierre\\)|Cerrar consulta" -n
```
- URLs de prueba:
  - UI:
    - `http://127.0.0.1:8092/modules/clinical/ui/encounter.php?encounter_key=appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2&embed=1`
    - `http://127.0.0.1:8092/modules/clinical/ui/historial.php?patient_id=p_0c874aa9cbad&embed=1`
  - API:
    - `http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2`
    - `http://127.0.0.1:8091/api/clinical/index.php/encounters/appt%3Afe61cdd67e97dcfde3a70c02%23enc%3A2/finalize`
- Commits exactos:
  - `7321ed9` `clinical api: finalize encounters with auto closing note`
  - `32a054e` `clinical ui: add cerrar consulta flow to encounter view`

#### Fase P10 — Integración Clínica en Ambiente A (Iniciar / ON AIR / Cerrar consulta)

- Estado actual (consolidado)
  - Clinical embed funcionando en Pacientes: `#p-expediente > #t-historial-atencion`.
  - Navegación child→parent por `mxmed:embed:navigate`.
  - Encounter: `open` / `active` / `finalize`.
  - Nota clínica AUTO por consulta + nota AUTO final (cierre).
  - Timeline incluye `procedure` aunque tenga `appointment_id`.

- Decisión de arquitectura
  - Parent (Ambiente A) orquesta:
    - resolver `patient_id`
    - consultar encounter activo
    - decidir modo del iframe (`historial` vs `encounter` vs `document`)
    - mostrar indicador ON AIR
    - ejecutar `Cerrar consulta`
  - Child clínico:
    - no conoce el estado global del dashboard
    - sigue reusable: standalone o embed
    - se mantiene compatibilidad del contrato `mxmed:embed:navigate` (no romper)

- Regla operativa
  - `1 consulta activa por médico y por paciente`.
  - Enforcement en backend (idempotencia al crear `open`).
  - UI solo consulta estado y refleja.

- UX target (Ambiente A)
  - Host oficial: tab `Historial de atención` dentro de Pacientes.
  - Comportamiento:
    - Si NO hay activa: mostrar botón `Iniciar consulta`.
    - Si SÍ hay activa: mostrar barra discreta ON AIR (LED verde) + `Consulta activa` (link) + `Cerrar consulta`.
  - `Cerrar consulta` genera nota clínica AUTO final (foto fija del momento).

- Endpoints involucrados (referencia)
  - `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
  - `POST /api/clinical/index.php/patients/{patient_id}/encounters` (idempotente si ya hay `open`)
  - `POST /api/clinical/index.php/encounters/{encounter_key}/finalize`

- Nota sobre Recetas (pendiente)
  - No bloquea P10.
  - Diseñar para que, a futuro, si hay receta emitida en esa consulta, `Cerrar consulta` pueda dejar un vínculo/navegación a la receta asociada sin obligar implementación en esta fase.

- Implementación (commits)
  - `ebf6f4e` `MXMed P10: Ambiente A patients embed adds iniciar/ON AIR/cerrar consulta in parent`
  - `e27efe7` `clinical api: enable patients/{patient_id}/encounters/active route`
  - `453daad` `MXMed P10: parent uses fallback user id for active encounter API calls`
  - `d4f1e0a` `MXMed P10: make ON AIR bar sticky in patients embed host`

- QA (evidencia)
  - Se validó en dashboard (`index.html`) dentro de `Pacientes > Expediente > Historial de atención`.
  - Flujo: `Iniciar consulta -> ON AIR -> Cerrar consulta`.
  - Se confirmó que cerrar genera `Nota clínica AUTO (Cierre)` con:
    - `snapshot_type=encounter_auto_final`
    - `auto_generated=true`
    - `finalized=true`
  - Se confirmó que después del cierre ya no existe consulta activa para ese paciente.

- Checklist de implementación (para la siguiente fase)
  1. Confirmar fuente única de `patient_id` en parent (`#p-expediente[data-patient-id]` como primaria).
  2. Insertar UI ON AIR + `Iniciar consulta` en `index.html` dentro del tab host.
  3. Parent: al abrir tab, consultar `encounters/active` y setear modo del iframe.
  4. Mantener `mxmed:embed:navigate` intacto.
  5. QA: probar flujo desde Pacientes (nuevo/buscar) y luego desde Agenda cuando exista UI.

- Estado: `CERRADA`

#### Cierre P11 — Auto Encounter al abrir expediente

- Estado:
  - Integrado y validado.
  - No rompe contratos previos de embed/navegación.
- Objetivo cumplido:
  - Al abrir expediente desde botón `[data-pid]`, se garantiza encounter activo para el paciente.
- Implementación (frontend host):
  - Archivo: `assets/js/app.js`.
  - Se agregó `ensureActiveEncounter(pid)` y se invoca al abrir expediente.
  - Flujo aplicado:
    1. `GET /api/clinical/index.php/patients/{pid}/encounters/active`
    2. Si `data=null` -> `POST /api/clinical/index.php/patients/{pid}/encounters`
       - payload:
         - `status: "open"`
         - `encounter_dt: "YYYY-MM-DD HH:MM:SS"`
    3. Persistencia en store:
       - `mxmedStore.activePatientId`
       - `mxmedStore.activeEncounterKey`
    4. Eventos emitidos:
       - `encounter:active` con `detail: { patient_id, encounter_key }`
       - `mxmed:encounter-changed` con `detail: { patient_id, encounter_key }`
- Validación real (evidencia):
  - El `POST` devolvió `ok:true`.
  - Se creó `encounter_key: "enc:18"`.
  - `opened_by_user_id: "u_demo_01"`.
  - El header `x-user-id` se envía por shim.
- Nota operativa:
  - P11 consolida la garantía de contexto clínico activo desde el primer ingreso a expediente y reduce estados nulos en la capa host.
- Estado: `CERRADA`

#### Cierre P12 — Clinical Context Propagation (encounter_key fuente única)

- Estado:
  - Integrado y validado.
  - Compatibilidad preservada con flujo embed existente.
- Objetivo cumplido:
  - Se consolida `encounter_key` activo como fuente única en frontend host.
  - Lectura centralizada: `window.mxmedStore.activeEncounterKey`.
  - Visibilidad operacional: dataset del pane de expediente para inspección/debug.
- Implementación (frontend host):
  - Archivo: `assets/js/app.js`.
  - Bridge clínico agregado:
    - `window.getActiveEncounterKey()`
    - `window.setEncounterContextOnPane(encounterKey, patientId)`
  - Suscripción de eventos existentes:
    - `encounter:active`
    - `mxmed:encounter-changed`
  - Sincronización aplicada por evento:
    - `mxmedStore.activeEncounterKey`
    - `data-encounter-key` y `data-active-encounter-key` en pane de expediente
    - actualización de `patient_id` en dataset cuando aplica
  - QA hook:
    - `window.mxmedDebug.getEncounterKey()`
- Integración mínima de consumidores:
  - `nota_evolucion` incorpora `encounter_key` en `context`.
  - Si no existe `encounter_key`, se aborta guardado con warning controlado (sin romper navegación).
- Evidencia:
  - Commit: `55a9369`
  - Tag: `mxmed-p12-context-bridge-v1`
  - Validación manual: `window.mxmedDebug.getEncounterKey()` devolvió `"enc:18"` tras abrir expediente.
- Nota operativa:
  - P12 cierra la propagación de contexto clínico activo para reducir ambigüedad entre módulos y facilitar trazabilidad de acciones clínicas.
- Estado: `CERRADA`

#### Cierre P13 — Load Encounter Payload (por encounter_key)

- Estado:
  - Integrado y validado.
  - Sin cambios de contrato backend ni navegación existente.
- Objetivo cumplido:
  - Al existir `encounter_key` activo, el host precarga detalle de consulta vía endpoint por encounter.
  - Se conserva en memoria el último JSON para inspección/QA.
- Implementación (frontend host):
  - Archivo: `assets/js/app.js` (solo host).
  - Eventos escuchados:
    - `encounter:active`
    - `mxmed:encounter-changed`
  - Endpoint consumido:
    - `GET /api/clinical/index.php/encounters/{encounter_key}`
  - Comportamiento:
    - resuelve key desde `window.getActiveEncounterKey()`
    - carga payload de encounter en background
    - guarda snapshot en `lastEncounterPayload`
  - Hook QA:
    - `window.mxmedDebug.getEncounterPayload()`
- Evidencia:
  - Commit: `4fb31d0`
  - Tag: `mxmed-p13-encounter-payload-v1`
- Validación manual (ejemplo real):
  - `window.mxmedDebug.getEncounterPayload()` devolvió `ok:true` con encounter activo `enc:18`.
  - En Network se observó `GET /api/clinical/index.php/encounters/<encounter_key_url_encoded>` con `200`.
  - En Console se observó log `[P13] Encounter payload loaded ...`.
- Estado: `CERRADA`

## B. Arquitectura universal (actual + futura)

### Núcleo actual (operando)
- Patients: identidad canónica de paciente.
- Agenda: source of truth de citas (`appointment_id` canónico).
- Clinical: encounters/documents/timeline/cases.
- UI principal + paneles embed clinical.

### Núcleo futuro (backlog)
- Hospitals
- Labs
- Pharma
- Insurers
- Reviews
- Notifications
- Billing/Invoice

### Referencias de arquitectura
- `docs/MAPA_TOTAL_SISTEMA_MXMED.md`
- `docs/db/MAPA_DOMINIOS_DATOS.md`
- `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`

## C. Contratos canónicos (IDs / rutas / naming / encoding)

### IDs canónicos
- `patient_id`:
  - Canon: dominio Patients (`patients_patients.patient_id`).
  - Patrón operativo frecuente: `p_<hash>`.
  - Convivencia actual: Clinical tolera UUID v4 en ciertos flujos (deuda controlada; pendiente convergencia total).
  - Referencias: `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`, `docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md`.
- `appointment_id`:
  - Canon: Agenda (source of truth).
  - Referencia: `docs/clinical/DECISION_APPOINTMENT_ID_CANONICO_AGENDA_V1.md`.
- `encounter_key`:
  - Formatos: `enc:{id}` o `appt:{appointment_id}#enc:{encounter_id}` (legacy soportado: `appt:{appointment_id}`).
  - Regla obligatoria: en path HTTP siempre URL-encoded (`rawurlencode`).
  - Referencias: `docs/clinical/encounters.md`.
- `document_uuid`:
  - Identificador externo recomendado de documento clínico.
  - Uso en UI/document viewer y endpoints de documento.
- `case_id`:
  - Identificador numérico de caso clínico activo.
  - Regla de membership v1: `item_ref = appt:{appointment_id}` para vincular item appointment al caso.

### Rutas canónicas clave (Etapa 1)
- `GET /api/patients/index.php/patients/{patient_id}`
- `POST /api/agenda/index.php/appointments`
- `GET /api/clinical/index.php/patients/{patient_id}/timeline`
- `GET /api/clinical/index.php/patients/{patient_id}/encounters`
- `POST /api/clinical/index.php/patients/{patient_id}/encounters`
- `GET /api/clinical/index.php/patients/{patient_id}/cases/active`
- `GET /api/clinical/index.php/cases/{case_id}/items`
- `POST /api/clinical/index.php/cases/{case_id}/items`

### Naming y encoding
- Evitar aliases ambiguos (`appt_id`, `legacy_patient_id`) en contratos nuevos.
- Mantener payload estándar `{ok,error,message,data,meta}` donde aplique.
- En URLs por path con `encounter_key`, codificar siempre `%23` para `#`.

## D. Checklist por módulo (Hecho / En progreso / Pendiente)

| Módulo | Estado | Alcance actual | Evidencia (docs/endpoints/tags/commits) | Pendiente inmediato |
|---|---|---|---|---|
| Patients | En progreso | Fuente canónica de identidad y contactos | `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `api/patients/index.php` | Cerrar convergencia total patient_id en clinical legacy |
| Agenda | Hecho v1 funcional consolidado, con deudas documentadas | Citas, eventos, waitlist y operación principal de Agenda (Semana/Día custom + resolver hueco), incluyendo BLOQ-F3 funcional (lectura/bloqueo/desbloqueo backend de bloqueos) | `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `api/agenda/index.php`; `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | Retiro progresivo de `localStorage` legacy de bloqueos; hardening transaccional de split de desbloqueo granular si aplica; migración real `consultorio_scope` + backfill; consolidación UI Operadores contra backend autoritativo; validación strict operator en sesión UI real |
| Agenda (Flags / Risk) | Hecho (registro) / Pendiente (enforcement) | Flags de riesgo por no_show/late_cancel en flujo write de Agenda | `modules/agenda/repositories/AppointmentWriteRepository.php:473-480`; `modules/agenda/repositories/AppointmentWriteRepository.php:536-543`; `modules/agenda/repositories/PatientFlagsWriteRepository.php:52-96`; `modules/agenda/controllers/AppointmentWriteController.php:93-124`; `docs/qa/availability_no_show_flow_qa.sh:144-163`; `docs/qa/availability_late_cancel_flow_qa.sh:155-182` | Definir política de bloqueo por flags antes de enforcement en create |
| Clinical API (timeline/encounters/documents) | En progreso | Timeline, encounters, documentos y casos en evolución | `docs/clinical/TIMELINE_V1_CONTRACT.md`; `docs/clinical/encounters.md`; tags `mxmed-camino2-step*` | Endurecer contratos cross-módulo y deuda legacy |
| Cases | En progreso | Caso activo y items por caso | `docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md`; endpoints `/cases/*` | Mejorar trazabilidad item_type y overlays de pertenencia |
| Clinical UI (historial/encounter embed) | En progreso | Embed estable + overlay documento + viewer host | tags `mxmed-camino2-step10`..`mxmed-camino2-step31`; `modules/clinical/ui/*.php` | Homologación visual completa + auditoría de fidelidad visual + unificación de componentes/variables globales (pendientes visuales críticos en PDF UX/UI, extracción pendiente). Nota contrato: “Ver atención” se rige por `has_encounter` + `latest_encounter_key`, sin inferencia frontend. |
| QA scripts (smokes) | En progreso | Contrato embed + smoke encounters/docs | `modules/clinical/qa/embed_contract_check.sh`; `docs/qa/clinical_encounters_smoke.sh` | Unificar rutas/ownership y ampliar checks de contrato |
| Legacy wrappers (`clinical-documents`, `evolution-note`) | Deuda controlada | Compatibilidad histórica activa | `api/clinical-documents.php`; `api/evolution-note-generate.php`; `docs/clinical/DECISION_COMPAT_CLINICAL_DOCUMENTS_WRAPPER.md` | Plan de salida gradual sin ruptura |
| Migraciones pendientes (schema v1/v2, encounter_id typing, document_id vs uuid, cursor) | Pendiente | Pendientes de consolidación estructural | `modules/clinical/db/schema_v1.sql`; `modules/clinical/db/schema_v2.sql`; `docs/clinical/TIMELINE_V1_CONTRACT.md` | Definir plan formal de migración por fases y rollback |
| UX/UI (Deuda Visual Controlada) | En progreso | Gobernanza visual transversal para Etapa 1 (criterio de fidelidad, componentes y variables globales) | `00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf` | Auditoría visual posterior al cierre de integración transversal médica (Etapa 1) |
| RBAC (Transversal) | Pendiente | Modelo de roles jerárquicos + delegabilidad + bitácora | 00INDICE MAESTRO DE FUNCIONES.pdf | Diseño conceptual posterior a cierre núcleo médico |
| Dominio Organizacional | Pendiente | Hospital, Lab, Aseguradora, Pharma como entidades aisladas | 00INDICE MAESTRO DE FUNCIONES.pdf | Diseñar modelo org + membership antes de Etapa 2 |
| Dominio Order (Diagnóstico) | Pendiente | Entidad formal para órdenes de estudio con estados y QR | 00INDICE MAESTRO DE FUNCIONES.pdf | Definir contrato antes de implementar laboratorios |
| Facturación Plataforma | Pendiente | Motor de planes, upgrade/downgrade, reclamo de perfil | 00INDICE MAESTRO DE FUNCIONES.pdf | Diseñar capa comercial posterior a Etapa 1 |

## E. Gobierno obligatorio de cambios UI/UX

Toda actividad futura debe aplicar:

- [Protocolo de control de cambios UI/UX y entrega segura](./MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md)
- [Plantilla de actividad segura Backend ↔ API ↔ UI](./MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md)
- [Registro de contratos visuales](./MXMED_REGISTRO_CONTRATOS_VISUALES.md)

La decisión `PP-280` separa autoridad funcional y representación visual, protege 8091 como última UI aprobada y hace obligatoria la clasificación `UI-0` a `UI-3`.

## F. Registro de decisiones (Decision Log)

Formato obligatorio por entrada:
- Fecha (`YYYY-MM-DD`)
- Decisión
- Motivo
- Impacto
- Referencias (docs/endpoint/commit/tag)
- Estado (`vigente`, `en revisión`, `reemplazada`)

| Fecha | Decisión | Motivo | Impacto | Referencias | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | **PP-280**: control seguro de cambios UI/UX y entrega | Una actividad funcional proyectó datos técnicos en la presentación sin decisión visual explícita y requirió recuperación forense | Establece UI-0 a UI-3, protección de 8091, orden secuencial, contratos visuales, matriz Backend ↔ API ↔ UI, gate de frontend diferido, emergency stop y dashboard siempre UI-3; el primer intento queda archivado en 2/22 y el segundo permanece `0/22 NOT_STARTED` | `docs/MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md`; `docs/MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md`; `docs/MXMED_REGISTRO_CONTRATOS_VISUALES.md` | vigente |
| 2026-02-25 | **Ejemplo**: `appointment_id` canónico proviene de Agenda | Eliminar divergencias Agenda/Clinical | Correlación estable appointment↔encounter en UI/API | `docs/clinical/DECISION_APPOINTMENT_ID_CANONICO_AGENDA_V1.md` | vigente |
| 2026-02-25 | RBAC será un módulo transversal independiente del dominio clínico y de agenda | Evitar contaminación de contratos clínicos con lógica de permisos organizacionales | Permite evolución multi-actor sin alterar Clinical core | 00INDICE MAESTRO DE FUNCIONES.pdf | vigente |
| 2026-02-25 | Modelo organizacional (Hospital, Lab, Aseguradora, Pharma) será dominio separado del dominio clínico | Separación clara entre actor organizacional y acto clínico | Facilita expansión futura sin romper núcleo médico | 00INDICE MAESTRO DE FUNCIONES.pdf | vigente |
| 2026-02-25 | El dominio "Order" (órdenes de laboratorio/estudio) será entidad nueva futura y no reutilización de Document | Evitar ambigüedad semántica entre documento clínico y orden diagnóstica | Mantiene integridad del timeline y correlación encounter/document | 00INDICE MAESTRO DE FUNCIONES.pdf | vigente |
| 2026-02-25 | Facturación clínica (CFDI paciente) y facturación plataforma (suscripción) serán dominios distintos | Separación contable y contractual clara | Evita mezcla de responsabilidades y facilita auditoría | 00INDICE MAESTRO DE FUNCIONES.pdf | vigente |
| 2026-02-25 | La arquitectura evolucionará hacia modelo modular con RBAC + dominio organizacional + event-driven sin alterar contratos clínicos actuales | Preparar expansión futura sin deuda estructural | Garantiza estabilidad del núcleo médico en Etapa 1 | 00INDICE MAESTRO DE FUNCIONES.pdf | vigente |
| 2026-02-25 | Biblioteca única de componentes y variables globales obligatoria para UI nueva | Reducir inconsistencia visual y deuda de estilos por módulo | Mejora mantenibilidad UI y evita divergencia entre pantallas clínicas y shell principal | 00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf | vigente |
| 2026-02-25 | Auditoría visual completa post-cierre de integración transversal médica (Etapa 1) | No bloquear núcleo médico con deuda visual, pero cerrarla antes de expansión | Define gate de calidad visual al cierre de Etapa 1 | 00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf | vigente |
| 2026-02-25 | Criterio de aceptación visual: fidelidad al maestro (pixel-fit razonable ±4px) | Establecer criterio objetivo de validación UX/UI sin sobre-optimización temprana | Estandariza QA visual y reduce discusiones subjetivas | 00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf | vigente |
| 2026-02-25 | Pendientes visuales críticos se priorizan después del núcleo médico, antes de expansión organizacional (hospitales/labs/aseguradoras) | Ordenar prioridades entre estabilidad clínica y expansión multi-dominio | Mantiene foco en Etapa 1 y prepara Etapa 2 con base visual consistente | 00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf | vigente |
| 2026-02-25 | Flags grey/black son append-only (no bloquean create) en Etapa 1 (estado actual) | Evitar bloqueo automático sin política de negocio explícita | Auditoría/QA de flags vigente; enforcement se decide aparte | `modules/agenda/README.md:42-47`; `modules/agenda/controllers/AppointmentWriteController.php:93-124`; `docs/qa/availability_no_show_flow_qa.sh:144-163`; `docs/qa/availability_late_cancel_flow_qa.sh:155-182` | vigente |
| 2026-06-16 | Nombres de paciente se validan en frontend y backend | Evitar pacientes genéricos o basura desde Datos Generales, Receta rápida o llamadas directas al API | Datos Generales y Receta rápida bloquean antes de POST; Pacientes protege `display_name` y campos de perfil con HTTP 422 ante nombres inválidos, preservando nombres reales complejos | `c94adb0`; `90e165d`; `docs/db/CONTRATO_ENDPOINTS_PACIENTES.md`; `docs/db/CONTRATO_JSON_PACIENTES.md` | vigente |
| 2026-06-16 | Pacientes y Recetas quedan como accesos top-level separados | Simplificar el sidebar y evitar duplicidad de acciones `Nuevo/Buscar/Recetas` bajo Pacientes | `Pacientes` abre `p-expediente`, `Recetas` abre `p-pac-recetas`, `p-pac-archivo` conserva activo Pacientes; Receta rápida exige paciente y el hub post-guardado queda con 7 acciones sin tarjeta duplicada Recetas | `3336d54`; `d165ab0`; `fb58461`; `cf003b7`; `e8d37f1`; `52ba29e`; `96afc3a` | vigente |
| 2026-02-25 | Naming canónico: no_show => black (deprecar red en docs) | Alinear código, QA y documentación con una sola semántica | Actualización documental pendiente para eliminar ambigüedad red/black | `modules/agenda/repositories/AppointmentWriteRepository.php:538-539`; `modules/agenda/README.md:106` | vigente |
| 2026-04-28 | Ubicación pública de consultorio usará coordenadas confirmadas (`lat/lng`) como fuente principal | Evitar divergencia entre mapa configurado en admin y mapa visible al público | Admin mantiene captura con Leaflet; perfil público futuro renderiza iframe de Google Maps por coordenadas (sin API Key); fallback por dirección solo visual | `modules/agenda/helpers/consultorio_map.php`; `modules/agenda/controllers/ConsultoriosController.php`; `modules/agenda/README.md` | vigente |
| 2026-05-24 | Waitlist no representa cita confirmada ni garantiza disponibilidad anticipada | Evitar falsas expectativas operativas en admisión/agendamiento | Se separa explícitamente flujo de espera vs cita confirmada | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | vigente |
| 2026-05-24 | Waitlist puede aplicar a cualquier consultorio mediante `consultorio_scope="all"` | Formalizar alcance cross-consultorio con semántica canónica | Permite priorización y asignación sin restringir a un solo consultorio desde el alta | `docs/MAPEO_AGENDA_MXMED.md` | vigente |
| 2026-05-24 | `__all__` queda como sentinel transicional y nunca puede ser destino de cita | Preservar compatibilidad de transición sin romper integridad de citas reales | El assign debe resolver siempre a consultorio real; destino sentinel queda bloqueado | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ACTOR_AUTORITATIVO_MXMED.md` | vigente |
| 2026-05-24 | Resolver hueco permite recuperar slots post-cancelación mediante candidatos waitlist | Reducir pérdida operativa por cancelaciones/reprogramaciones | Se habilita recuperación guiada con ranking/candidatos y asignación explícita | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | vigente |
| 2026-05-24 | Agenda puede abrir contexto de paciente/expediente, pero no inicia consulta automáticamente | Mantener frontera entre operación administrativa y acto clínico | Evita arranque automático de consulta y preserva control clínico explícito | `docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md`; `docs/PLAN_MAESTRO_MXMED.md` | vigente |
| _pendiente_ | _agregar nuevas decisiones aquí_ |  |  |  |  |

## G. Mapa de interconexiones (flows principales)

### Flow 1: patient creation -> agenda
1. Agenda recibe creación de cita.
2. Si no hay `patient_id`, Agenda solicita alta a Patients.
3. Patients responde `patient_id` canónico.
4. Agenda crea cita con `appointment_id` y snapshot mínimo.

Refs:
- `docs/db/INTEGRACION_PACIENTES_AGENDA.md`
- `docs/db/INTEGRACION_AGENDA_POST_PATIENTS.md`

### Flow 2: agenda completed -> clinical encounter
1. Cita cambia a `completed/attended`.
2. Agenda dispara bridge a Clinical.
3. Clinical crea/asegura encounter idempotente por `appointment_id`.

Refs:
- `docs/clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md`

### Flow 3: encounter -> document -> timeline -> cases
1. Encounter contiene/deriva documentos clínicos.
2. Timeline unifica agenda + clinical.
3. Cases marca pertenencia por `item_ref=appt:{appointment_id}`.
4. UI embed aplica contexto de caso activo y navegación a episode/document.

Refs:
- `docs/clinical/TIMELINE_V1_CONTRACT.md`
- `docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md`

## H. Backlog por etapas (1a/2a/3a/4a)

### 1a) Consolidación perfil médico (prioridad absoluta)
- `[contract]` cerrar convergencia `patient_id` canónico en flujos clinical legacy.
- `[api]` endurecer correlación appointment↔encounter sin parseos frágiles.
- `[ui]` homologar UI clínica en modo ambiente (mm-btn/mm-badge) sin romper embed.
- `[ui]` Gate UX/UI Etapa 1: auditoría de fidelidad visual + componentes unificados + variables globales.
- `[qa]` ampliar smoke de contratos timeline + cases + overlay.
- `[migration]` plan de migración schema v1/v2 y tipos de IDs.
- `[contract/api/qa]` Definir política y (opcional) enforcement de bloqueo por flags (grey/black): ¿grey bloquea?, ¿black bloquea?, duración (`expires_at`), override por rol; punto técnico para guard en `AppointmentWriteController::createFromPayload` entre payload válido y `checkAvailabilityRange` (`modules/agenda/controllers/AppointmentWriteController.php:93-124`). DoD: decisión escrita + QA contractual + error contract definido.
- `[migration]` migración real de `consultorio_scope` + backfill en ambientes existentes.
- `[ui/api]` retirar progresivamente dependencia legacy de `localStorage` en bloqueos (BLOQ-F3 ya cerrado funcionalmente).
- `[audit]` definir auditoría canónica de bloqueos/desbloqueos (`availability_blocked` / `availability_unblocked`) y su persistencia final.
- `[ui/api]` consolidación Operadores UI vs backend autoritativo.
- `[qa]` validación strict operator en sesión UI real.
- `[contract/api]` definir contrato explícito `operator_identity_db_not_ready` -> `503`.
- `[ui]` subtab completa de Waitlist futura.
- `[ui/ux]` evolución futura de UX de Resolver hueco.
- `[refactor]` encapsulación/retiro progresivo de sentinel `__all__`.
- `[triage]` triaje de `409 patient-id/resolve` no bloqueante.
- `[profiles]` PP-1 completado: contrato funcional inicial de Perfil Publico Medico documentado (`9d6024e`).
- `[profiles]` PP-2B completado: contrato tecnico de payload publico MVP documentado (`4274d6b`).
- `[profiles]` PP-3 completado: contrato de endpoint publico read-only ejecutable documentado (`2b0e9d4`).
- `[profiles]` PP-Decisiones 01 completado: adenda de decisiones de producto para identidad, URL, contacto, agenda, reclamo, SEO/Schema, gating, videoconsulta e IA (`0460db2`).
- `[profiles]` PP-Decisiones 02 completado: adenda de datos comerciales, medios de pago, aseguradoras aceptadas y ecosistema ampliado (`9c81a05`).
- `[profiles]` PP-4B completado: endpoint publico minimo read-only transicional por `doctor_id` implementado (`2398549`).
- `[profiles]` PP-4C completado: QA ampliado del endpoint publico transicional validado (casos 200/400/404, contrato, seguridad y comportamiento conservador) sin cambios de codigo.
- `[profiles]` PP-Decisiones 03 completado: direccion visual/funcional por boceto para perfil gratuito, perfil mejorado por plan y separacion de plataforma/listados publicos.
- `[profiles]` PP-5B completado: primera vista publica SSR transicional implementada en `profiles/doctor.php` con CSS dedicado (`77f3a1a`).
- `[profiles]` PP-5C completado: QA visual/funcional basico de la vista SSR transicional validado (200/400/404, estructura, gating, SEO/SSR y seguridad) sin cambios de codigo.
- `[profiles]` PP-5D completado: micro-ajustes visuales de la ficha gratuita SSR (`4764568`).
- `[profiles]` PP-6A completado: reestructuracion visual progresiva y refinamiento tipo directorio del perfil gratuito (`4e36597`, `b7aee1b`).
- `[profiles]` PP-7A completado: diagnostico de conexion panel privado -> DTO publico (sin cambios de codigo).
- `[profiles]` PP-7B completado: diseno tecnico minimo de identidad profesional canonica (sin implementacion).
- `[profiles]` PP-7C completado: decision tecnica de fuente canonica minima `profiles_doctors` para identidad profesional publica (documental).
- `[profiles]` PP-7D completado: implementacion minima de `profiles_doctors` + seed demo controlado + adaptacion endpoint publico (`d095475`).
- `[profiles]` PP-7E completado: cierre documental de identidad profesional canonica publica y evidencia de QA/consumo SSR.
- `[profiles]` PP-7F completado: diagnostico del panel privado para edicion de identidad publica profesional y mapa panel -> `profiles_doctors` (sin cambios de codigo).
- `[profiles]` PP-7G completado: diseno UX/contrato de seccion "Identidad publica profesional" (campos, gobernanza, prefijos y ruta panel -> DB canonica -> DTO -> SSR).
- `[profiles]` PP-7H1 completado: accesos funcionales de Perfil Medico agregados al dropdown superior del usuario (`ba8938c`).
- `[profiles]` PP-7H1-B completado: homologacion de labels del dropdown respecto al menu lateral (`ced5676`).
- `[profiles]` PP-7H1-C completado: deuda UX controlada de navegacion Perfil Medico (coexistencia temporal lateral + dropdown; sin cierre final de comportamiento de menus).
- `[profiles]` PP-7H2-A completado: endpoint privado minimo `GET/PATCH /api/profiles/private/doctor/{doctor_id}` para identidad publica en `profiles_doctors` (`23de802`).
- `[profiles]` PP-7H2-C completado: congruencia panel legacy + bloque identidad publica + `profiles_doctors` con guardado explicito (commits `14ba4cf`, `8b29b76`, `e7a2009`).
- `[profiles]` UX-Panel-01A completado: decision UX de separacion entre Informacion Verificada e Identidad Publica editable en Datos Personales (base responsive PC/tablet/movil/PWA), sin cambios de codigo.
- `[profiles]` UX-Panel-01B completado: auditoria de autosave/localStorage y politica de guardado progresivo del panel (sin cambios funcionales).
- `[profiles]` UX-Panel-01C2 completado: optimizacion visual de Datos Personales + catalogo frontend transicional de credenciales (commits `ff4c311`, `f55e774`, `3135cd5`).
- `[profiles]` UX-Panel-01C3-D completado: estado transicional documentado para Informacion verificada (badge, microcopy y boton placeholder `Solicitar cambio` sin bloqueo ni backend de solicitudes).
- `[profiles]` UX-Panel-01D2 completado: documentada la separacion transicional de Datos de contacto (`dp-correo`, `dp-whatsapp`) y su alcance no canonico/no publico automatico.
- `[profiles]` UX-Panel-01D3-A completado: modelo canonico futuro de Datos de contacto definido documentalmente, separando seguridad, contacto privado administrativo, contacto publico, contacto operativo y contacto por consultorio; regla base `privado por defecto` y `publico solo con flag/plan/verificacion`.
- `[profiles]` UX-Panel-01D3-B2 completado: documentado el microcopy visual de privacidad/visibilidad en Datos de contacto; datos privados por defecto y no publicos automaticamente.
- `[profiles]` UX-Panel-01D3-C completado: placeholder visual `Uso previsto de estos datos` para categorias Seguridad / Privado administrativo / Publico / Operativo / Consultorio en Datos de contacto; evidencia `404bdff`; alcance solo visual, sin backend, sin `PATCH` y sin publicacion de datos.
- `[profiles]` SYS-Data-01E completado: documentado contrato seguro de identidad publica para `PATCH /api/profiles/private/doctor/{doctor_id}`; evidencia commit `1137739`, sólo guarda `display_name`, `prefix`, `gender`, `gender_label` y `bio_short`, ignora campos verificados/administrativos con `blocked_fields_ignored` y mantiene cédulas/especialidad/status como lectura.
- `[profiles]` SYS-Data-01G completado: documentado contrato canónico futuro de `contact_points`/`doctor_contact_points` para contacto privado, público, operativo, administrativo/plataforma y por consultorio; endpoint separado futuro `GET/PATCH /api/profiles/private/doctor/{doctor_id}/contact-points`, sin extender el `PATCH` de identidad pública, sin usar `profiles_doctors` ni `patients_contacts` para contacto del médico y con regla privado por defecto.
- `[profiles]` SYS-Data-01M completado: documentado schema futuro `doctor_contact_points`; evidencia commit `73ba0d3`, archivo `modules/profiles/db/doctor_contact_points_schema.sql` creado en repositorio pero no ejecutado, sin MySQL, endpoints, UI ni migración automática de `dp-correo`/`dp-whatsapp`.
- `[profiles]` SYS-Data-01Q completado: documentado primer endpoint privado de lectura `GET /api/profiles/private/doctor/{doctor_id}/contact-points`; evidencia commit `274187a`, tabla local `doctor_contact_points` vacía con respuesta `HTTP 200`, `data.items: []` y `meta.count: 0`, sin `POST/PATCH/DELETE`, sin UI, sin migrar `dp-correo`/`dp-whatsapp`, sin perfil público ni duplicar `consultorios`.
- `[profiles]` SYS-Data-01T completado: documentado `POST /api/profiles/private/doctor/{doctor_id}/contact-points`; evidencia commit `f01f6b7`, crea contacto individual con normalización backend, privacidad estricta por defecto, bloqueo de campos públicos/verificados, duplicados activos con `409`, sin `PATCH/DELETE/batch`, sin UI, sin migrar `dp-correo`/`dp-whatsapp` y con registros QA eliminados (`row_count=0`).
- `[profiles]` SYS-Data-01W completado: documentado `PATCH /api/profiles/private/doctor/{doctor_id}/contact-points/{contact_point_id}`; evidencia commit `1f8e505`, actualiza contacto individual con PATCH parcial, normalización backend, bloqueo de campos públicos/verificados, duplicados activos con `409`, `404` para inexistentes, sin `DELETE/batch`, sin UI, sin migrar legacy y con registros QA eliminados (`row_count=0`).
- `[profiles]` SYS-Data-01Z completado: documentado ajuste de DTO privado de `doctor_contact_points`; evidencia commit `98c5ddd`, `deleted_at` deja de exponerse en respuestas `GET/POST/PATCH`, se conserva como campo interno para `deleted_at IS NULL` y soft delete futuro, sin cambiar schema, privacidad, normalización, duplicados, perfil público, Consultorio ni frontend.
- `[profiles]` SYS-Data-02C completado: documentado `DELETE /api/profiles/private/doctor/{doctor_id}/contact-points/{contact_point_id}`; evidencia commit `7b6b33b`, aplica borrado lógico con `deleted_at = NOW()` y `updated_at = NOW()`, `GET` deja de devolver el contacto eliminado, `deleted_at` no se expone en DTO, duplicados activos se liberan vía `active_normalized_value`, sin batch, sin UI, sin legacy y con registros QA eliminados (`row_count=0`).
- `[profiles]` SYS-Data-02H completado: documentado `POST /api/profiles/private/doctor/{doctor_id}/contact-points/import-legacy`; evidencia commit `1676eb6`, importa sólo `dp:dp-correo -> email` y `dp:dp-whatsapp -> whatsapp`, backend impone `source=legacy_dp`, POST general conserva `source=manual`, sin publicar, sin tocar Consultorio/perfil público/localStorage y con registros QA eliminados (`row_count=0`).
- `[profiles]` SYS-Data-02J completado: bloque visual pasivo de importación legacy agregado en Datos Personales > Datos Generales > Datos de contacto; evidencia commit `405d9d3`, ubicado dentro de `#mx-dg-contact-card` después de `#dp-correo`/`#dp-whatsapp` y antes de `.mx-dg-contact-privacy-strip`, con CTA deshabilitado `Importar contactos privados próximamente`, sin JS, sin API, sin `localStorage`, sin MySQL, sin Consultorio y sin perfil público.
- `[shell]` UX-Shell-01B completado: acceso inferior de Mi Perfil agregado al sidebar con estructura tipo perfil/header; menu legacy de perfil en sidebar se mantiene oculto y dropdown superior permanece como respaldo transicional (`97281e2`).
- `[shell]` UX-Shell-01C completado: decision de navegacion documentada para mantener acceso inferior en sidebar como direccion preferida, con deuda futura de simplificacion del header y cierre responsive final.
- `[shell]` UX-Shell-01D5 completado: documentado comportamiento del control de sidebar; en desktop/tablet grande el control de colapso pertenece visualmente al sidebar y en movil no se muestra hamburguesa hasta existir overlay real.
- `[shell]` UX-Shell-01D10 completado: documentada la consolidacion de Mi Perfil en el dropdown inferior del sidebar; menu principal reservado para modulos generales y dropdown inferior funcional en modo expandido/compacto.
- `[shell]` UX-Shell-01E5-D completado: documentado el layout dashboard del panel principal de perfil, con completitud como bloque principal, actividad reciente como card lateral e indicadores clave como bloque inferior; pendiente definir metricas finales y decision de panel inicial.
- `[shell]` UX-Shell-01H2 completado: header global con identidad profesional verificada, especialidad en etiqueta, estado de plan sin selector y accion visual transicional de vigencia; evidencia commit `75c1321` + QA UX-Shell-01H3 PASS sin cambios.
- `[shell]` UX-Shell-02B completado: creado el primer piloto del componente maestro `mx-panel-subheader` y aplicado únicamente al subheader de `Panel principal de mi perfil`; evidencia commit `da76ef4`, sin cambios en JS, navegación, tabs ni otros subheaders.
- `[shell]` UX-Shell-02D completado: segundo piloto de `mx-panel-subheader` aplicado únicamente al subheader de `Opiniones recibidas en mi perfil`; evidencia commit `fb6fd0d`, sin CSS nuevo, sin JS, navegación, tabs ni otros subheaders.
- `[shell]` UX-Shell-02G completado: definido diseño maestro conceptual para tabs modernos asociados a subheaders (`mx-panel-tabs`), inspirado visualmente en Agenda pero sin reutilizar clases `mx-ag-*` ni lógica de Agenda; estrategia transicional conserva `mm-tabs`, Bootstrap pills, `data-bs-*` y `.tab-content`, con piloto futuro recomendado en `Paquetes y Promociones`.
- `[shell]` UX-Shell-02H completado: primer piloto de `mx-panel-tabs` implementado en `Paquetes y Promociones` junto con `mx-panel-subheader--with-tabs`; evidencia commit `2ae3b9f`, CSS acotado a `#p-paquetes`, conserva Bootstrap pills, `mm-tabs`, `data-bs-*`, `.tab-content` y `selectPaqTab('#paq-crear')`.
- `[shell]` UX-Shell-02J completado: segundo piloto real de `mx-panel-tabs` aplicado al panel `Seguridad` junto con `mx-panel-subheader--with-tabs`; evidencia commit `eed4d92`, CSS acotado a `#p-seguridad`, conserva Bootstrap pills, `mm-tabs`, `data-bs-*` y `.tab-content`, con ajuste fino de `tab-ico` para compactar `verified_user` y `lock_person`.
- `[shell]` UX-Shell-02L completado: consolidada base común mínima opt-in de `mx-panel-tabs`; evidencia commit `ca7322b`, reduce duplicación entre `#p-paquetes` y `#p-seguridad`, conserva overrides acotados, Bootstrap pills, `mm-tabs`, `data-bs-*`, `.tab-content` y `selectPaqTab('#paq-crear')`, sin tocar HTML, JS, Agenda ni tabs legacy.
- `[shell]` UX-Shell-02N completado: limpieza HTML controlada de tabs de `Facturación` como preparación para futura migración a `mx-panel-tabs`; evidencia commit `5c11074`, corrige atributos irregulares, agrega `type="button"` a los cuatro tabs y conserva `data-bs-*`, targets, textos visibles y `.tab-content`, sin tocar CSS ni JS.
- `[shell]` UX-Shell-02O completado: aplicado `mx-panel-subheader--with-tabs` y `mx-panel-tabs` al panel `Facturación`; evidencia commit `09bcfef`, se apoya en la limpieza previa `UX-Shell-02N`, conserva Bootstrap pills, `data-bs-*`, targets y `.tab-content`, sin tocar JS, Agenda ni `mx-ag-*`, con override acotado a `#p-facturacion` para wrap móvil.
- `[consultorio]` UX-Consultorio-01H completado: documentada estabilización multisede tras revertir el piloto visual de Consultorio (`ebd398c`), restaurar `#sede1` al cargar (`35ff9b3`) y bloquear autosave durante hidratación (`868aeb9`); Consultorio usa backend/MySQL real, datos demo locales para QA y queda pausado para `mx-panel-tabs` hasta nueva estrategia.
- `[profiles]` Siguiente recomendado: UX-Panel-01D3-D (diseno backend canonico de contacto como posible fase futura/refinamiento documental; sin implementacion definida todavia).

### 2a) Operación clínica extendida
- `[api]` órdenes, recetas y resultados con contratos estables.
- `[ui]` experiencia de episodio completa (acciones y contexto longitudinal).
- `[ops]` observabilidad básica de bridges y retries.
- Nota: perfiles Hospital/Lab/Pharma/Aseguradora son FUTURO y no bloquean cierre de Etapa 1.

### 3a) Integraciones institucionales
- `[contract]` hospitales/labs/pharma/insurers con IDs interoperables.
- `[api]` conectores externos y trazabilidad.
- Nota: esta etapa inicia después del gate UX/UI y cierre del núcleo médico de Etapa 1.

### 4a) Canales y monetización
- `[api]` notifications/reviews/billing/invoice.
- `[ui]` paneles operativos por perfil.
- `[ops]` controles de cumplimiento/auditoría.
- Nota: no bloquear Etapa 1 por funcionalidad comercial/organizacional futura.

## I. Índice de fuentes externas

| Fuente | Tipo | Fecha | Estado | Decisiones derivadas | Próxima acción |
|---|---|---|---|---|---|
| `00Introduccion para Desarrolladores.pdf` | PDF externo | 2026-02-25 | pendiente | Por definir | Extraer decisiones y actualizar Decision Log + Checklist + Backlog |
| `00Funcionalidades por Tipo de Perfil.pdf` | PDF externo | 2026-02-25 | pendiente | Por definir | Extraer decisiones y actualizar Decision Log + Checklist + Backlog |
| `00INDICE MAESTRO DE FUNCIONES.pdf` | PDF externo | 2026-02-25 | pendiente | Decisiones de arquitectura modular transversal (RBAC/Org/Order/Billing) | Extraer decisiones y actualizar Decision Log + Checklist + Backlog |
| `00MANUAL DE DISENO Y UX UI PENDIENTES VISUALES previo a 09 de Octubre 2025.pdf` | PDF externo | 2026-02-25 | en revisión / pendiente de extracción | Gobernanza UX/UI transversal (decisiones de método, no detalle visual) | Extraer decisiones y actualizar Decision Log + Checklist + Backlog |

## H1. Inventario de fuentes internas (repo)

| Fuente (ruta en repo) | Tipo (doc interno) | Propósito | Estado (pendiente / revisado / reemplazado) | Decisiones derivadas (si aplica) | Próxima acción |
|---|---|---|---|---|---|
| `docs/MAPA_TOTAL_SISTEMA_MXMED.md` | doc interno | Mapa total del sistema y estado transversal | pendiente | Por definir | Revisar y extraer decisiones al Decision Log |
| `docs/MAPEO_AGENDA_MXMED.md` | doc interno | Mapeo técnico de Agenda y adendas de cierre funcional | revisado | Consolida criterios de waitlist, resolver hueco y transición de alcance | Mantener como referencia activa para cambios de Agenda |
| `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | doc interno | Separación entre cierre funcional Agenda y deuda UX/UI | revisado | Define cierre funcional consolidado con deudas explícitas no bloqueantes | Mantener alineado con A1.2 y checklist por módulo |
| `docs/AGENDA_RBAC_MATRIZ_ACTORES_MXMED.md` | doc interno | Matriz de actores/permisos aplicable a operación Agenda | revisado | Soporta trazabilidad de alcance y enforcement progresivo por actor | Mantener como referencia activa para validación de permisos |
| `docs/AGENDA_AUDITORIA_ACTOR_ATTRIBUTION_MXMED.md` | doc interno | Auditoría de atribución de actor en flujos Agenda/Waitlist | revisado | Refuerza evidencia para decisiones de auditoría y hardening operativo | Mantener como referencia activa para QA/auditoría |
| `docs/AGENDA_ACTOR_AUTORITATIVO_MXMED.md` | doc interno | Definición de actor autoritativo y compatibilidad transicional | revisado | Alinea reglas de sentinel `__all__` y destino válido de cita | Mantener alineado con retiro progresivo/encapsulación del sentinel |
| `docs/clinical/DECISION_FUENTES_DE_VERDAD.md` | doc interno | Fuente canónica de dominios clínicos/paciente/documentos | pendiente | Por definir | Revisar y confirmar contratos canónicos |
| `docs/db/MAPA_DOMINIOS_DATOS.md` | doc interno | Mapa de dominios y relaciones de datos | pendiente | Por definir | Revisar y alinear arquitectura universal |
| `docs/ui/REGLAS_UI_MXMED.md` | doc interno | Metodología operativa para cambios UI/UX | pendiente | Por definir | Revisar y reflejar reglas en checklist |
| `docs/db/INTEGRACION_AGENDA_POST_PATIENTS.md` | doc interno | Integración Agenda -> alta de paciente cuando no hay patient_id | revisado | Alineado con validación backend de nombres y propagación de `invalid_params`/HTTP 422 desde Pacientes | Mantener sincronizado con contrato de Pacientes |
| `docs/db/INTEGRACION_PACIENTES_AGENDA.md` | doc interno | Integración conceptual Pacientes ↔ Agenda | pendiente | Por definir | Revisar y consolidar fronteras de dominio |
| `docs/clinical/DECISION_APPOINTMENT_ID_CANONICO_AGENDA_V1.md` | doc interno | Decisión de appointment_id canónico en Agenda | pendiente | Por definir | Revisar cumplimiento en endpoints y UI |
| `docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md` | doc interno | Contrato de correlación appointment ↔ encounter | pendiente | Por definir | Revisar y reforzar QA contractual |
| `docs/clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md` | doc interno | Decisión del bridge Agenda -> Clinical en completed | pendiente | Por definir | Revisar implementación y feature-flag |
| `docs/dev/LOCAL_DEV_SERVERS.md` | doc interno | Operación local dual-server y preflight QA | pendiente | Por definir | Revisar y mantener comandos operativos vigentes |
| `docs/qa/availability_no_show_flow_qa.sh` | doc interno | QA smoke de no_show + flag black + idempotencia | pendiente | Por definir | Revisar y convertir asserts en contrato explícito de flags |
| `docs/qa/availability_late_cancel_flow_qa.sh` | doc interno | QA smoke de late_cancel + flag grey + idempotencia | pendiente | Por definir | Revisar y convertir asserts en contrato explícito de flags |
| `docs/agenda/CIERRE_FASE_I6_SEMANTICA_Y_CONTRATO_FRONTEND.md` | doc interno | Semántica de cancel/no_show y expectativa de flags | pendiente | Por definir | Alinear naming y semántica con código vigente |
| `docs/db/MODELO_CANONICO_AGENDA.md` | doc interno | Modelo canónico de Agenda y significado de flags | pendiente | Por definir | Mantener alineado con enforcement futuro |
| `modules/agenda/README.md` | doc interno | Contrato operativo Agenda y estado real de flags (append-only) | pendiente | Por definir | Corregir naming red/black y mantener documentación sincronizada |

## J. Cómo trabajar este plan

Regla operativa obligatoria por iteración:
1. Revisar fuente nueva (doc interno/PDF/ticket).
2. Registrar decisión en sección E (o explícitamente “sin nueva decisión”).
3. Actualizar checklist sección D (estado + evidencia).
4. Actualizar backlog sección G (alta/baja/repriorización).
5. Vincular evidencia (ruta doc + endpoint + commit/tag cuando aplique).

Criterio de calidad de actualización:
- Sin evidencia concreta, no hay cambio de estado.
- Si cambia contrato canónico, debe existir decisión explícita en E.
- Si una tarea impacta múltiples módulos, actualizar también sección F (interconexiones).

## Arquitectura del flujo del expediente

El flujo de estados del expediente clínico se define en:

docs/ARQUITECTURA_ESTADOS_EXPEDIENTE.md

Este documento establece el contrato arquitectónico para:

- alta administrativa de pacientes
- inicio de consultas
- lifecycle clínico

Toda modificación relacionada con pacientes o consultas debe respetar este modelo.

## Arquitectura general del sistema

El mapa completo del sistema se encuentra en:

docs/MAPA_TOTAL_SISTEMA_MXMED.md

Este documento describe los módulos principales del sistema y su relación.

Debe utilizarse como referencia antes de implementar nuevos módulos o modificar los existentes.

## Interconexiones clínicas del sistema

El mapa de interconexiones clínicas se define en:

docs/MAPA_INTERCONEXIONES_CLINICAS_MXMED.md

Este documento especifica cómo se relacionan paciente, expediente, consulta, actividad clínica, documentos, órdenes, resultados y timeline.

Debe usarse como referencia antes de integrar módulos clínicos o modificar sus dependencias.

## Agenda como puerta de entrada clínica

La relación entre Agenda, Paciente, Expediente y Consulta se define en:

docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md

Este documento establece que Agenda puede abrir el contexto del paciente, pero no debe iniciar consulta automáticamente.

Debe usarse como referencia antes de modificar la integración entre citas y flujo clínico.

## Fase de levantamiento módulo por módulo

Se inicia una fase de levantamiento y mapeo técnico por módulo, basada en evidencia directa del repositorio.

Primer módulo analizado:

docs/MAPEO_AGENDA_MXMED.md

Este mapeo documenta archivos reales, endpoints, tablas, flujo funcional, interconexiones y divergencias de Agenda antes de cualquier cambio funcional.

Se documentó además la separación formal entre consolidación funcional de Agenda y deuda de implementación UX/UI en:

docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md

Tras el cierre documental de Agenda, se inicia el levantamiento del módulo Pacientes:

docs/MAPEO_PACIENTES_MXMED.md

Después del mapeo de Pacientes, se inicia el levantamiento del módulo Expediente:

docs/MAPEO_EXPEDIENTE_MXMED.md

Tras el mapeo de Expediente, inicia una fase de auditoría detallada por secciones del módulo:

docs/AUDITORIA_EXPEDIENTE_CHECKLIST_MXMED.md

## Cierre Fase UX del Expediente del Paciente (P14 UX-Min)

### Objetivo de la fase
Cerrar un ajuste mínimo y seguro de UX en `Pacientes > Expediente` para separar con claridad:
- ficha administrativa editable (`Datos Generales`)
- flujo clínico operativo (`Historia Clínica` / `Historial de Atención`)

Sin alterar el comportamiento clínico estabilizado de encounters, chips activos y cierre aislado.

### Cambios implementados y validados
1. Semántica visible en `Datos Generales`:
- `Nombre` -> `Nombre(s)`
- `Apellido Paterno` -> `Primer Apellido`
- `Apellido Materno` -> `Segundo Apellido`

2. `Datos Generales` deja de operar como portada obligatoria permanente del expediente y queda como ficha administrativa editable.

3. Regla de entrada al expediente:
- paciente incompleto -> entrada inicial a `Datos Generales`
- paciente con mínimos completos -> entrada inicial a flujo clínico
- preferencia clínica: `Historial de Atención`
- fallback: `Historia Clínica`

4. Navegación manual entre tabs clínicos liberada:
- ya no debe existir rebote artificial a `Datos Generales` al cambiar manualmente de tab.

5. Motivo de consulta:
- se mantiene lectura robusta y se muestra en header cuando existe
- **no** bloquea entrada clínica por completitud
- se clasifica como dato clínico primario opcional y cambiante

### Regla de completitud mínima vigente
Datos mínimos obligatorios (`completeProfile = true`):
- `Nombre(s)`
- `Primer Apellido`
- `Género`
- `Fecha de Nacimiento`

Dato opcional no bloqueante:
- `Motivo de consulta`

### Estado validado
- Sin regresiones funcionales en:
  - chips de consultas activas
  - consultas activas y cambio de paciente
  - límite de 3 consultas activas
  - finalize encounter (cierre aislado)
  - `ensureActiveEncounter`
  - navegación manual estabilizada de tabs clínicos

### Deuda / siguiente paso recomendado
- Diseñar y cerrar la versión final del **header clínico permanente** del expediente (jerarquía visual, contenido clínico persistente y reglas de degradación cuando no hay consulta activa), manteniendo separación estricta entre contexto administrativo y contexto clínico.

## Cierre Fase Header Clínico Permanente del Expediente (P14 UX-Header)

### Objetivo de la fase
Implementar de forma incremental el header clínico permanente del paciente dentro de `Pacientes > Expediente`, reforzando contexto clínico persistente sin intervenir la lógica clínica sensible ya estabilizada.

### Estructura final implementada del header
Se consolidó el header en 4 bloques funcionales:

1. Identidad fija principal:
- Nombre completo
- Edad
- Género

2. Contexto clínico opcional:
- Motivo de consulta (solo cuando existe)
- Sin texto de relleno cuando no existe dato

3. Estado clínico:
- Consulta activa / sin consulta activa
- Metadatos ya existentes (origen/inicio cuando disponibles)
- Acciones existentes:
  - Iniciar consulta
  - Cerrar consulta

4. Multi-activo:
- Strip de consultas activas conservado sin rediseño en esta fase

### Fuente de verdad por campo
- Nombre completo: identidad visible del paciente cargado en expediente (`Nombre(s)`, `Primer Apellido`, `Segundo Apellido`).
- Edad: cálculo vigente derivado de fecha de nacimiento (flujo actual de `computeAge`).
- Género: fuente canónica vigente del expediente (`input[name="pac-genero"]` / atributo normalizado del pane).
- Motivo: helper robusto con fallbacks vigentes del frontend.
- Estado clínico: resolución de consulta activa ya existente (sin introducir motor nuevo de encounters).
- Strip multi-activo: store multi-activo vigente.

### Reglas visuales y funcionales validadas
- Prioridad visual:
  - 1) Nombre completo
  - 2) Estado clínico
  - 3) Edad y género
  - 4) Motivo opcional
- El motivo se muestra como sublínea discreta y solo si existe.
- `syncExpedienteHeaderContext()` quedó como renderizador de contexto (no como motor de navegación).
- Se añadieron guardas de actualización para evitar repaints innecesarios del header.

### Restricciones respetadas
Sin regresiones en:
- chips de consultas activas
- consultas activas / multi-activo
- límite de 3 consultas activas
- navegación manual de tabs clínicos
- finalize / cierre aislado
- `ensureActiveEncounter`

### Estado validado
- Header muestra de forma persistente: nombre, edad y género.
- Motivo aparece solo cuando existe.
- Estado de consulta activa se mantiene consistente.
- Strip multi-activo continúa operando sin cambios estructurales en esta fase.
- Navegación manual en tabs clínicos permanece estable.

### Siguiente paso sugerido
- Afinación futura del contexto clínico superior (micro-UX y jerarquía visual avanzada) solo si el uso real lo justifica, manteniendo la separación entre:
  - ficha administrativa editable
  - contexto clínico persistente superior.

## Mitigación Runtime: Manejo Hospitalario sin Backend de Estancias

### Problema detectado
Durante validación de `Pacientes > Expediente > Manejo Hospitalario` se confirmó error funcional:
- `404 (Not Found)` por llamadas frontend a `api/hospital-stays.php` (`current`, `start`, `close`) en un runtime donde ese endpoint no existe.

### Decisión implementada
Se aplicó mitigación de frontend por **degradación controlada (capability gate)**:
- el tab **Manejo Hospitalario** permanece visible
- en este entorno, no se ejecutan llamadas a `api/hospital-stays.php`
- el módulo entra en estado neutral/no disponible
- se muestra aviso explícito de no disponibilidad
- acciones dependientes de estancia hospitalaria quedan deshabilitadas

### Estado actual validado
- eliminado el `404` funcional asociado al submódulo en este runtime
- apertura del tab estable, sin ruptura de layout
- sin impacto en:
  - chips de consultas activas
  - encounters activos
  - límite de 3 activas
  - finalize
  - ensureActiveEncounter
  - navegación manual de tabs clínicos base

### Alcance de la mitigación
- incidencia mitigada en frontend para el entorno actual
- sin backend nuevo
- sin endpoint puente
- sin refactorización amplia del submódulo

### Restricción arquitectónica explícita
La habilitación clínica real de Manejo Hospitalario requiere contrato backend formal de estancias hospitalarias (consulta de estancia activa, inicio y cierre), con su persistencia y reglas de negocio definidas.

### Siguiente paso recomendado
Planificar fase backend específica para estancias hospitalarias y, después, reactivar capacidades del tab retirando el modo neutral de forma controlada.

## Mejora de Hidratación de Expediente sin Draft Local (Patients API)

### Problema original
En `Pacientes > Expediente`, la hidratación de `Datos Generales` dependía principalmente de `patientIdentityDrafts` en memoria frontend.

Efecto observado:
- paciente visible en buscador (índice correcto),
- pero expediente con identidad vacía al abrir, cuando no existía draft local para ese `patient_id`.

### Fuente real anterior de hidratación
- `setActivePatientId()` aplicaba `applyExpedienteIdentityDraft(patientId)` como ruta principal.
- Si el draft no existía, no había respaldo robusto para poblar identidad desde backend de Patients en esa transición.

### Decisión implementada
Se incorporó una hidratación mínima y segura:
- si no existe draft local de identidad para el paciente activo,
- se consulta `GET /api/patients/index.php/patients/{patient_id}`,
- se construye draft frontend con datos de Patients (identidad/sexo/fecha de nacimiento),
- se reaplica la hidratación al formulario de expediente.

### Comportamiento nuevo validado
- pacientes existentes pueden abrir expediente con identidad visible aun sin draft local previo;
- header clínico obtiene contexto coherente (nombre/edad/género) desde la identidad ya hidratada en DOM;
- navegación manual de tabs permanece estable.

### Alcance y restricciones respetadas
Sin intervención de lógica clínica sensible:
- chips de activas
- lifecycle de encounters
- finalize
- ensureActiveEncounter
- límite de 3 consultas activas

Sin cambios de contrato backend en Patients.

### Estado validado
Validado con pacientes demo de prueba integral:
- búsqueda funcional,
- apertura de expediente con identidad visible,
- header consistente en cambio de paciente.

### Límite actual de la solución
La mejora cubre hidratación de identidad administrativa básica desde Patients.
No convierte automáticamente campos clínicos opcionales (ej. motivo de consulta) en requisito ni en fuente obligatoria para entrada clínica.

### Siguiente paso sugerido
Opcional: precarga contextual de motivo desde Agenda (`reason_text`) al abrir expediente, sin volverlo obligatorio ni bloquear flujo clínico.

## Corrección: Aislamiento de Motivo de Consulta por Paciente (Expediente)

### Problema detectado
Durante validación de cambio de paciente en `Pacientes > Expediente`, se observó fuga de estado en el campo visible de **Motivo de consulta**:
- al pasar de un paciente a otro, el textarea y la sublínea del header podían conservar el motivo del paciente anterior.
- esto producía contexto clínico incorrecto en pantalla.

### Causa raíz
La vista no limpiaba explícitamente el motivo visible al cambiar de paciente.
Con esa condición, la guarda de no sobrescritura ("si ya hay texto, no precargar") se activaba correctamente, pero sobre un texto residual del paciente previo.

### Solución aplicada
Se implementó corrección mínima y segura en frontend de expediente:
- captura del motivo del paciente saliente en su draft correspondiente,
- limpieza explícita del motivo visible al cambio de paciente,
- rehidratación del motivo del paciente entrante desde su draft,
- y después aplicación de prefill contextual solo si corresponde.

### Regla funcional final (vigente)
El motivo queda aislado por paciente y se resuelve con esta precedencia:
1. captura manual del paciente actual
2. draft del paciente actual
3. prefill contextual del paciente actual
4. vacío

Consecuencia: nunca debe heredarse visualmente el motivo entre expedientes de pacientes distintos.

### Estado validado
Prueba de navegación cruzada validada:
- `Adriana -> Jorge -> Adriana` conserva el motivo correcto de cada paciente.
- si el paciente entrante no tiene motivo, el campo queda vacío y no hereda el anterior.

### Restricciones respetadas
La corrección se aplicó sin regresiones en:
- header clínico
- tabs manuales
- chips
- encounters
- finalize
- ensureActiveEncounter
- límite de 3 activas

## P14 Context-Orchestrator F2 — Setter de Contexto de Encounter

### 1) Problema original
El contexto de encounter activo se escribía desde varias rutas de UI (chips, P10 y sincronizaciones parciales), lo que elevaba riesgo de desincronización entre:
- header clínico
- P10
- strip/chips
- store/datasets del expediente

### 2) Solución aplicada
Se implementó una ruta oficial frontend:
- `setCurrentEncounterForPatient(patientId, encounterKey, opts = {})`

Con esta fase se consolidó:
- actualización única de estado canónico (`currentPatientId`, `currentEncounterKey`, `activePatientId`, `activeEncounterKey`)
- actualización coherente de datasets del pane de expediente
- migración del handler de chips (`exp-switch-active-enc`) para usar el setter oficial
- emisión de eventos controlada cuando existe cambio real (sin emisión redundante)

### 3) Estado final de F2
Quedó validado el aislamiento de contexto por paciente:
- cambiar foco por chips mantiene sincronía de contexto
- header, P10 y datasets se mantienen coherentes
- al cambiar de paciente no se hereda encounter del paciente anterior
- abrir paciente sin activa mantiene estado neutro
- iniciar consulta actualiza store/pane/P10 de forma consistente

### 4) Riesgos residuales
- Persisten listeners legacy sobre `mxmed:encounter-changed`.
- En fases siguientes conviene migrar consumo progresivo a `mxmed:encounter-context-changed` como señal principal del cambio de contexto de encounter.

### 5) Validación QA de cierre
Checklist validado para esta fase:
1. abrir paciente sin consulta activa -> estado neutro
2. iniciar consulta en paciente objetivo -> estado activo coherente
3. cambiar entre pacientes con y sin activa -> sin herencia de encounter
4. confirmar sincronía entre store, pane expediente, P10 y header clínico

## Ajustes de Cierre — P15 Notas y Search Open (commit `0db14d4`)

### A) P15 — Notas de Evolución (UX de título visible)
Se cerró el ajuste de identificación visual en `Notas generadas` con estas reglas vigentes:
- el título visible usa solo el **Tema de la nota**
- se eliminó el prefijo visible `Nota de evolución —` en listado/render UX
- se eliminó el nombre del médico del meta visible del listado
- se conserva fecha/hora visible
- se mantiene compatibilidad con documentos legacy mediante normalización visual del título

Impacto funcional: mejora de legibilidad y diferenciación de notas sin alterar persistencia canónica ni contrato documental.

### B) Buscar -> Abrir expediente (entrada neutra y tab inicial)
Se cerró el ajuste de estabilidad del flujo `search_open` con estas reglas vigentes:
- `Abrir expediente` desde Buscar **no auto-activa consulta**
- apertura en estado clínico neutro (sin encounter operativo automático)
- tab inicial forzada para `search_open` en `#t-datos` (Datos Generales)
- se conserva hidratación correcta de identidad/datos generales
- la activación de consulta queda reservada a acciones explícitas del usuario (`Iniciar consulta`)

Impacto funcional: evita herencia de encounter al abrir desde búsqueda y estabiliza la entrada al expediente sin romper sincronización clínica existente.

## Actividad clínica — Fase 1 (launcher UX)

commit: `63ec87b`

Se introduce un entry point unificado para registrar acciones clínicas desde
Historial de Atención mediante el botón:

Registrar actividad clínica

Este launcher abre un panel con accesos directos a flujos existentes sin
modificar contratos backend ni módulos clínicos.

Acciones conectadas en esta fase:

- Nota clínica -> flujo existente de notas (`#t-notas`)
- Procedimiento -> handler existente `open-generic-procedure-modal` en historial embebido
- Receta -> modal de receta (`#ne_open_rx`) reutilizando flujo de notas

Objetivo de esta fase:
Unificar el punto de entrada UX para registrar eventos clínicos sin reescribir
módulos existentes.

Notas técnicas:
- no se modifica P10 ni lifecycle de encounter
- no se modifica API clínica
- procedimientos aún dependen del módulo embebido en iframe
- cambios limitados a `index.html` y `assets/js/app.js`

Fases futuras previstas:

Actividad clínica F2
- incorporar Orden de estudio
- incorporar Resultado de estudio
- incorporar Adjuntar documento

Actividad clínica F3
- desacoplar procedimientos del iframe
- normalizar catálogo de procedimientos

## REC-CAT-3 — Estrategia de catálogo mexicano importable y normalizado (receta)

Se formaliza la estrategia de evolución del catálogo de medicamentos para evitar
escalamiento indefinido de un mock manual en runtime.

Problema identificado:
- el catálogo embebido/manual no escala para cobertura clínica real en México
- agregar entradas una a una degrada mantenibilidad y trazabilidad

Decisión arquitectónica:
- runtime de receta consulta un **catálogo local MXMed** (JSON versionado)
- la **memoria del médico** permanece como capa personalizada adicional
- la ampliación de cobertura se hará por **importación offline** de datasets
  transformados al formato canónico MXMed

Fuentes objetivo para expansión:
- **COFEPRIS**: referencias de registros sanitarios, nombres genéricos,
  distintivos/comerciales y principios activos
- **Compendio Nacional de Insumos para la Salud**: base de normalización oficial

Regla operativa:
- durante la captura de receta no se depende de consultas en vivo a fuentes
  externas; la búsqueda clínica se resuelve con catálogo local + memoria médica

Artefacto documental asociado:
- `docs/catalogo-medicamentos-mxmed.md`

Alcance de esta definición:
- deja asentada la dirección técnica del módulo de receta y catálogo
- no implica scraping en vivo ni backend nuevo en esta iteración

### REC-ADM-1 — Patrones de administración para receta clínica

Se formaliza una decisión arquitectónica complementaria de Receta:
la lógica de prescripción en MXMed no debe depender de reglas por
medicamento individual.

Decisión:
- cada medicamento/presentación del catálogo se mapea a un **patrón de administración**
- el patrón define comportamiento clínico-operativo de captura y UX
- el medicamento hereda ese comportamiento por metadatos, no por lógica ad-hoc

Qué gobierna un patrón de administración:
- unidad de dosis
- vías permitidas
- sugerencias de frecuencia
- sugerencias de duración
- sugerencias/hints de indicaciones
- reglas de UI (campos/chips priorizados, ocultamiento/despriorización contextual)

Patrones base mínimos aprobados:
- `tableta_oral`
- `capsula_oral`
- `suspension_oral`
- `gotas_orales`
- `inyectable`
- `topico`
- `supositorio`
- `inhalado`

Reglas operativas:
1. El catálogo debe incluir metadatos suficientes para mapear perfil:
   `form`, `route` sugerida y metadata contextual de administración.
2. Si no existe mapeo confiable, Receta cae a modo manual sin bloquear flujo.
3. No crear reglas por medicamento individual salvo excepciones clínicas justificadas.
4. Esta lógica permanece en frontend en la fase actual, sin dependencia backend adicional.

Relación con REC-CAT:
- REC-CAT define la estrategia de cobertura y fuente de verdad del catálogo.
- REC-ADM define cómo ese catálogo gobierna la prescripción de forma escalable.
- Ambos bloques son acoplados por contrato de metadata de presentación.

Riesgos/consideraciones futuras:
- se requiere gobernanza de metadatos para evitar presentaciones sin perfil
- habrá casos límite que exigirán override clínico explícito
- al crecer el catálogo, conviene validar consistencia de perfiles en pipeline de importación

Nota de transición:
- Se renombra oficialmente el término "perfil de administración" a "patrón de administración"
  para evitar ambigüedad con perfiles de usuario. El código existente podrá alinearse a esta
  nomenclatura en fases futuras sin bloquear la operación actual.

### REC-F1 — Implementación base de receta clínica (estado actual)

A. Capacidades implementadas (funcionales en UI)
- autocompletado de medicamento/presentación desde catálogo
- catálogo híbrido operativo: seed local + memoria del médico + captura manual
- patrones de administración aplicados por presentación
- adaptación contextual de dosis/vía/frecuencia/duración/indicaciones por patrón
- control de rutas válidas por patrón y eliminación de herencias incompatibles
- reset controlado al cambiar de patrón (sin borrar de forma agresiva cuando el patrón se mantiene)
- chips de captura rápida integrados al flujo de edición
- cantidad total inteligente:
  - autocálculo en patrones autocálculables
  - modo manual asistido en patrones no autocálculables
- integración vigente con Historial y Viewer de documentos clínicos

B. Decisiones arquitectónicas consolidadas
- catálogo desacoplado del runtime principal y preparado para crecimiento por dataset local versionado
- gobernanza de prescripción basada en patrón de administración (no reglas ad-hoc por medicamento)
- lógica de receta resuelta en frontend en esta fase, sin backend nuevo dedicado
- ejecución clínica sin dependencia de APIs externas en tiempo real durante la captura

C. Estado actual
- la base de receta clínica está implementada y funcional para operación de Fase 1
- el módulo quedó estable para continuar con fases evolutivas sin reabrir diseño base

D. Pendientes futuros (fase siguiente, sin rediseño)
- plantillas de receta (guardado y reutilización clínica)
- validaciones clínicas avanzadas (interacciones, contraindicaciones, reglas terapéuticas)
- análisis asistido por IA bajo demanda con controles de consentimiento
- mejora de cobertura de catálogo vía importación masiva normalizada

## Directrices permanentes del núcleo clínico MXMed

El núcleo clínico de MXMed se organiza bajo la siguiente arquitectura conceptual:

Agenda → Encounter → Actividad clínica → Clinical Documents → Timeline → Expediente

Cada capa cumple un rol específico dentro del sistema:

**Agenda**
Origen de la interacción clínica. Representa citas programadas o atenciones previstas.

**Encounter (consulta)**
Contexto clínico activo donde se agrupan las acciones médicas realizadas durante una atención.

**Actividad clínica**
Capa de registro de acciones médicas. Desde aquí se capturan eventos como notas clínicas, procedimientos, recetas, órdenes de estudio, resultados o documentos clínicos.

**Clinical Documents**
Persistencia estructurada de las acciones clínicas. Cada registro clínico se almacena como un documento clínico con estructura uniforme.

**Timeline clínico**
Proyección cronológica de la actividad clínica del paciente. Permite visualizar eventos médicos en orden temporal.

**Expediente del paciente**
Interfaz donde se consultan los datos clínicos, historial y documentos generados a lo largo del tiempo.

### Principios arquitectónicos permanentes

1. Todo registro médico nuevo debe entrar por la capa **Actividad clínica**.
2. Toda acción clínica registrable debe persistirse como **clinical_document** o converger a esa arquitectura.
3. **Historial de Atención** funciona principalmente como capa de lectura del timeline clínico.
4. **Actividad clínica** funciona principalmente como capa de captura de eventos clínicos.
5. La clasificación semántica visible para UX debe privilegiar `clinical_category`.
6. `document_type` y otras capas de catálogo funcionan como soporte técnico interno.
7. Nuevas funcionalidades clínicas no deben introducir entry points aislados fuera de esta arquitectura, salvo justificación explícita documentada.

Estas directrices funcionan como marco de referencia para futuras intervenciones del sistema.

### Ordenamiento del catálogo documental clínico (v1)

Se formaliza el primer registro maestro versionado de `document_type` para MXMed en:

- `docs/DOCUMENT_TYPE_REGISTRY_MXMED.md`

Alcance de esta base v1:
- define estructura estándar por `document_type`
- consolida tipos prioritarios y su estatus (`active`, `planned`, `legacy_readonly`)
- fija reglas de captura canónica vs lectura legacy
- alinea clasificación semántica para Timeline/Expediente (`clinical_category`, `study_role`)

Secuencia de evolución aprobada:
1. documento técnico versionado (actual)
2. helper/config compartida en runtime
3. posible catálogo BD futuro si se requiere gobernanza dinámica

### CONS-2 — Contrato canónico mínimo de consentimiento_informado (definición documental)

Se define formalmente el contrato mínimo canónico de `consentimiento_informado` como
documento clínico-administrativo del ecosistema `clinical_documents`.

Referencia principal:
- `docs/DOCUMENT_TYPE_REGISTRY_MXMED.md` (sección `CONS-2 — Contrato canónico mínimo de consentimiento_informado`)

Decisiones asentadas:
- `document_type = consentimiento_informado`
- `patient_id` canónico obligatorio
- `encounter_key` opcional
- `appointment_id` opcional
- visible en timeline y expediente
- ruta objetivo: convergencia al modelo clínico canónico (sin dualidad como ruta principal)

Alcance de esta fase:
- definición documental del contrato mínimo (`title`, `summary`, `context`, `payload`, `event_datetime`)
- sin implementación backend/UI funcional en esta iteración

## Diagnóstico longitudinal — Prioridad de arquitectura clínica

Se establece como primera entidad longitudinal prioritaria del estado clínico en MXMed:

- **Diagnóstico longitudinal**

Principio de implementación:
- El estado clínico longitudinal debe construirse sobre la arquitectura vigente:
  `Actividad clínica -> Clinical Documents -> Timeline -> Expediente`.
- No debe crearse una fuente de verdad paralela desvinculada de `clinical_documents`.

Modelo conceptual base:
- lectura longitudinal derivada desde documentos clínicos
- captura clínica se mantiene en Actividad clínica
- fuente inicial principal: `nota_evolucion.diagnosticos`

Campos mínimos sugeridos del diagnóstico longitudinal:
- `patient_id`
- `diagnosis_key`
- `label`
- `code` (opcional)
- `status` (`active`/`resolved`)
- `onset_at` (opcional)
- `resolved_at` (opcional)
- `resolution_note` (opcional)
- `first_seen_at`
- `last_updated_at`
- `source_document_uuid`
- `source_encounter_key` (opcional)

Ruta de implementación propuesta:
- D1 solo lectura
- D2 estado `active`/`resolved` + bitácora
- D3 UX en expediente
- D4 captura explícita vía Actividad clínica
- D5 normalización avanzada

Riesgos a evitar:
- fuente paralela
- escritura fuera de Actividad clínica
- duplicación de diagnósticos
- obligar encounter en todos los casos
- falta de bitácora/auditoría

## DOC-ORD-1A — Validación de orden múltiple y ajuste UX de listado

Estado: implementado y validado en QA técnico.

Conclusión funcional:
- La persistencia de órdenes múltiples en DOC-ORD-1A queda validada como **documento único** en `clinical_documents`.
- Una selección múltiple (ej. 3 estudios) genera un solo `POST /api/clinical/index.php/documents` y un solo documento nuevo (`lab_order`/`imaging_order`) con:
  - `payload.requested_studies[]`
  - `payload.selection_count`

Alcance del ajuste aplicado:
- Se confirmó por trazabilidad frontend (`save_submit` / `save_response` / `list_refresh`) que el problema principal no era fragmentación de persistencia en la prueba validada, sino priorización visual del listado.
- Se reforzó el listado de “Órdenes generadas” para comportamiento determinístico:
  - orden por `event_datetime DESC`
  - desempate por `id DESC`
  - reconstrucción limpia del contenedor (sin append incremental de colección previa)
- Tras guardado exitoso:
  - captura de `document_db_id`/`document_uuid`
  - refresh del listado
  - localización de la card creada
  - promoción visual al inicio del contenedor si no quedó primera
  - resaltado temporal (`is-new-document`) y enfoque visual
  - trazabilidad DOM explícita por card:
    - `data-document-id`
    - `data-document-uuid`
    - `data-event-datetime`
  - la priorización post-save usa estos atributos estables (no texto visible ni posición).
  - ajuste final UX/listado:
    - confirmada persistencia correcta (documento único por acción)
    - cards con contenido legible (`summary` + preview corto)
    - orden histórico determinístico (`event_datetime DESC`, desempate `id DESC`)
    - separación explícita de bloques:
      - card “recién generada” fija en el bloque superior (sin duplicarse)
      - bloque “historicas” debajo con orden determinístico interno.

Límites explícitos de esta fase:
- Sin tocar resultados (`lab_result`/`imaging_result`).
- Sin refactor grande de viewer/modal.
- Sin delete canónico de órdenes en esta etapa.

## DOC-ORD / DOC-RES — Historial de Atención reutiliza capa compartida de detalle diagnóstico

Estado: ajuste de integración aplicado.

- Historial de Atención mantiene su rol longitudinal y deja de depender de un render diagnóstico paralelo para órdenes/resultados.
- Las acciones de eventos diagnósticos (`Ver orden`, `Ver resultado`, `Ver orden original`, `Ver orden reemplazante`, `Ver orden previa`) delegan al modal interno compartido del host (`openOrderDetailModal` vía bridge embed), reutilizando la misma capa funcional validada en Estudios Diagnósticos.
- Se conserva fallback local de visor documental solo cuando no está disponible la capa compartida del host.
- Se reduce ruido de consola en contexto `search_open` limitando logs redundantes de supresión auto-encounter en P10.
- Homologación de iconografía diagnóstica: Historial de Atención reutiliza la misma iconografía SVG inline de las cards de Estudios Diagnósticos, se elimina la ruta que renderizaba texto de ligatures (por ejemplo `radiology`) y el render queda centralizado exclusivamente en el helper `resolveClinicalDocumentSvgIcon(...)`.

## QR-NC — Captura de imagen clínica desde celular (QR)

### 1. Contexto del problema

En la operación real, el médico suele documentar en escritorio, pero la captura de evidencia clínica (foto de lesión, hallazgo o documento visual) se realiza más rápido desde celular. Sin este puente, la nota clínica pierde fluidez y se fragmenta la carga de adjuntos.

### 2. Solución implementada (V1)

Se implementó un flujo auxiliar desde **Nota clínica**:
- acción secundaria en el modal de nota: **Usar celular para capturar imagen**
- apertura de submodal QR en escritorio
- escaneo en celular y apertura de vista móvil mínima
- captura/subida de imagen desde móvil
- regreso a escritorio con verificación por polling controlado
- persistencia en la tubería canónica de `clinical_documents` (sin backend paralelo)

### 3. Arquitectura técnica

Base técnica del flujo:
- token temporal: `note_capture_token`
- asociación mínima:
  - `patient_id` (obligatorio)
  - `encounter_key` (opcional)

Endpoints implementados:
- `POST /api/clinical/index.php/note-capture-tokens`
- `GET /api/clinical/index.php/note-capture-tokens/{token}`
- `POST /api/clinical/index.php/note-capture-tokens/{token}/upload`
- `POST /api/clinical/index.php/note-capture-tokens/{token}/cancel`

Persistencia de archivo:
- la carga móvil reutiliza la tubería canónica de `/api/clinical/index.php/documents`
- `document_type = image`
- `media_tag_key = evidencia_clinica`
- `payload.source = nota_modal_qr_v1`
- `payload.note_capture_token = <token>`

### 4. UX implementada

Elementos UX de V1:
- acción secundaria dentro del modal de Nota clínica
- submodal QR con estado (`Pendiente`, `Imagen recibida`, `Expirado`)
- fallback con enlace móvil visible cuando el QR no se visualiza
- botón de copia de enlace para abrir en celular
- estado visible en modal principal de nota (`Sin imagen recibida` / `Imagen recibida`)
- vista móvil mínima para captura/subida (sin complejidad adicional)

### 5. Endurecimiento (QR-NC-1C)

Mejoras de robustez aplicadas:
- limpieza estricta de estado al abrir/cerrar modal de nota
- cancelación de token pendiente al cerrar submodal QR o modal de nota
- control de polling (sin intervalos huérfanos, stop por estado/expiración/cierre)
- fallback QR reforzado (enlace siempre visible + copiar)
- validación de separación de responsabilidades: sin acoplamiento directo en `historial.php`

### 6. Limitaciones actuales

Límites explícitos de esta versión:
- el QR depende de servicio externo para generación visual
- no hay sincronización en tiempo real (polling ligero)
- el token no se consume al guardar la nota (aún)
- flujo pensado para una imagen por ciclo de captura
- cancelación en frontend es best-effort (si se cierra abruptamente, aplica expiración por TTL)

### 7. Evolución futura (V2 sugerida)

Siguientes pasos recomendados:
- generación de QR local (sin dependencia externa)
- consumo/cierre definitivo de token al guardar nota clínica
- asociación formal del documento capturado con la nota clínica guardada
- soporte multi-imagen por sesión/token
- evaluación de sincronización en tiempo real si el volumen lo requiere

## 🔜 SIGUIENTE POR RESOLVER

## Segundo intento del programa de refinamiento (rama de actividad)

- Estado: **1/22; Actividad 1 CONCLUIDA**.
- Pendientes: **21**.
- Actividad 1/22: auditoría V2 y aprobación directoral A1–A5 de catálogo, precios provisionales, modalidad mensual y autoridad comercial.
- Actividad 2: **NO INICIADA**.
- Clasificación: **UI-0**; cierre documental sin implementación.
- Known-good protegido: `recovery/mxmed-pre-22-known-good` @ `e4f7d515cba4ae47fcdbd44cd55ce610466b982a`; 8091 sin cambios.
- Primer intento histórico: **PAUSED_AND_ARCHIVED**, `2/22`; no modificado ni reactivado.
- Decisiones: A1–A5 y B1–B7 aprobadas contractualmente; ownership y lifecycle permanecen pendientes en bloques separados.
- Dashboard de operadores: no implementado; requisitos candidatos clasificados UI-3.
- La formalización B1–B7 existe sólo en `docs/mxmed-capabilities-permissions-director-approval-v2` hasta su integración explícita; no inicia la Actividad 2 ni modifica la rama de programa.

### Estado de candidato de implementación (Actividad 2/22)

- La rama candidata `feature/mxmed-existing-capabilities-backend-authority-v2` registra **2/22; Actividad 2 candidata READY_FOR_DIRECTOR_REVIEW**.
- Este registro es una entrega aislada para revisión: no cambia el contador oficial del programa (`1/22`), no hace fast-forward, no abre PR y no integra en la rama de programa.
- Alcance: **UI-1**, `VISUAL_DIFF_REQUIRED_TO_BE_ZERO`, con `STOP_UI_SCOPE_ESCALATION_REQUIRED`; se mantiene 8091 en el known-good protegido y 8140 como puerto de revisión.
- La Actividad 3 permanece **NO INICIADA**; quedan **20 actividades pendientes** en el candidato.

### Gate transversal móvil

- Estado: `MOBILE_RESPONSIVE_FINALIZATION_PENDING`.
- La formalización actual es `UI-0 — NO_UI_IMPACT`; la ejecución futura será `UI-3 — UI_VISUAL_CHANGE_REQUIRES_APPROVAL`.
- Los smoke tests móviles de cada actividad son `INTERIM_MOBILE_SMOKE_ONLY` y no constituyen `FINAL_MOBILE_APPROVED`.
- El capítulo no crea automáticamente una Actividad 23 ni incrementa el contador `2/22`; debe asignarse dentro o inmediatamente antes del cierre funcional final.

### PP-281 — Auditoría V2 de planes, capacidades, ownership y lifecycle

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Registrar Activity 1/22 del segundo intento como auditoría candidata | Se requiere conocer el baseline real antes de decidir política o implementación | UI-0; inventario Backend ↔ Schema ↔ API/read-model ↔ UI; sin código, SQL, Stripe, AWS ni dashboard; known-good protegido; historia no reactivada; 21 actividades pendientes y Activity 2 no iniciada | `docs/MXMED_AUDITORIA_V2_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md`; evidencia `/tmp/mxmed-activity01-plans-capabilities-ownership-lifecycle-v2/` | `AUDIT_READY_FOR_INTEGRATION`; decisiones del director pendientes |

### PP-282 — Aprobación V2 de catálogo, precios provisionales, modalidad mensual y autoridad comercial

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Aprobar A1–A5 para el desarrollo posterior: catálogo `free`/`basic`/`standard`/`optimum`/`professional`; `free` como estado sin quinta card; precios anuales provisionales de $6,990/$9,990/$12,990/$21,990 MXN; mensualidad `anual ÷ 12 × 1.25` con redondeo vigente y primer pago de tres mensualidades; backend/catálogo persistido como autoridad, API/read-model como transporte y frontend como presentación | Cerrar contractualmente el bloque catálogo/precios/modalidades de la Actividad 1 sin confundir decisión con implementación | Precios sujetos a revisión formal pre-lanzamiento; recurrencia y cobros Stripe diferidos; UI-0 actual; gates futuros UI-1 con diff visual cero, UI-2 para comportamiento y UI-3 para precios/copy/composición; Actividad 1 candidata concluida y pendiente de fast-forward; Actividad 2 no iniciada | `docs/MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md`; `docs/MXMED_AUDITORIA_V2_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md`; evidencia `/tmp/mxmed-activity01-decisions-a1-a5-integration-v2/` | `DIRECTOR_APPROVED_CONTRACT_ONLY`; no autoriza implementación |

### PP-283 — Aprobación V2 de capacidades, autoridad backend y preservación visual

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Aprobar B1–B7: backend como única autoridad de capacidades/permisos; API/read-model como transporte; frontend como reflejo; separación entre registro técnico y presentación comercial; trazabilidad sin cambio automático de UI; progresión funcional objetivo; denegación fail-closed; funciones futuras efectivas sólo en estado `operational`; e implementación incremental | Proteger las fichas aprobadas mientras se prepara una autoridad backend mínima y comprobable para capacidades existentes | Cards, beneficios, copy y orden congelados en `recovery/mxmed-pre-22-known-good@e4f7d515cba4ae47fcdbd44cd55ce610466b982a`; anti-patrón de exponer matrices técnicas prohibido; UI-0 actual; futura Actividad 2 inicialmente UI-1 con `VISUAL_DIFF_REQUIRED_TO_BE_ZERO`; `STOP_UI_SCOPE_ESCALATION_REQUIRED` ante cambios visuales; 8091 protegido; Actividad 1 concluida `1/22`; Actividad 2 no iniciada | `docs/MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md`; `docs/MXMED_AUDITORIA_V2_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md`; `docs/MXMED_REGISTRO_CONTRATOS_VISUALES.md`; evidencia `/tmp/mxmed-decisions-b1-b7-v2/` | `DIRECTOR_APPROVED_CONTRACT_ONLY`; no autoriza implementación ni cambios UI |

### PP-284 — Implementación V2 de autoridad backend para capacidades existentes

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Ejecutar la Actividad 2 candidata con backend como única autoridad y frontend como binding no autoritativo | Convertir B1–B7 en un contrato mínimo verificable sin alterar la presentación aprobada | UI-1; catálogo deliberadamente mínimo de 7 capacidades existentes; decisiones fail-closed con códigos internos estables; read-model/API aditivos y compatibles; frontend enlaza sólo `available`/estado público; cards, copy, orden, clases y shell congelados; matrices técnicas, dependencias, lifecycle, conteos, funciones futuras y razón interna no se exponen; no Stripe, checkout, pagos, activación, SQL, migraciones, AWS ni escrituras | `docs/MXMED_IMPLEMENTACION_V2_AUTORIDAD_CAPACIDADES_EXISTENTES.md`; evidencia `/tmp/mxmed-activity02-existing-capabilities-backend-authority-v2/`; candidato `feature/mxmed-existing-capabilities-backend-authority-v2` | `READY_FOR_DIRECTOR_REVIEW`; visual diff estático 0; Actividad 3 no iniciada |

### PP-285 — Capítulo transversal final de adaptación móvil y responsive

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Registrar el capítulo final obligatorio de adaptación móvil para cierre funcional y lanzamiento | Los smoke tests intermedios previenen regresiones, pero no sustituyen una decisión visual integral sobre navegación y adaptación móvil | UI-0 documental ahora; UI-3 en la ejecución futura; `MOBILE_RESPONSIVE_FINALIZATION_PENDING`; viewports de 320 a 1024 px, Safari iOS, Chrome Android, desktop emulado y físicos disponibles; navegación, shell, módulos, formularios, tablas, modales, teclado, scroll, orientación, safe areas, touch y estados; no ralentiza el desarrollo y no crea automáticamente Actividad 23 | `docs/MXMED_CAPITULO_FINAL_ADAPTACION_MOVIL_RESPONSIVE.md`; `docs/MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md`; `docs/MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md`; `docs/MXMED_REGISTRO_CONTRATOS_VISUALES.md` | `MOBILE_RESPONSIVE_FINALIZATION_PENDING`; Actividad 2 concluida `2/22`; Actividad 3 no iniciada; gate obligatorio antes de lanzamiento |

### Estado candidato de auditoría (Actividad 3/22)

- La rama `audit/mxmed-claim-registration-login-recovery-sessions-security-v2` registra una auditoría read-only candidata para revisión del director: **Actividad 3/22, UI-0, código/UI/SQL/datos escritos 0, 8091 intacto**.
- El avance oficial permanece **2/22** hasta integración explícita; la Actividad 4 continúa **NO INICIADA** y no hay implementación autorizada.
- Las decisiones C1–C8 sobre identidad, reclamación, alta, recuperación, sesiones, límites, dispositivos y soporte asistido quedan pendientes.
- El capítulo final móvil sigue `MOBILE_RESPONSIVE_FINALIZATION_PENDING`; su ejecución futura exige `UI-3` y no se altera por esta auditoría.

### PP-286 — Auditoría V2 de identidad, reclamación, acceso, recuperación y sesiones

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Registrar la Actividad 3/22 como auditoría técnica y funcional read-only de identidad, acceso, reclamación, registro, login/logout, recuperación, sesiones, autorización y seguridad | No existe todavía un circuito productivo completo de cuenta/claim/auth; se requiere separar identidad, rol, propiedad, entidad profesional, suscripción y capacidades antes de implementar | `UI-0`; sin PHP/JS/CSS/HTML/SQL/migraciones/seeds/config/AWS/Stripe/datos; `http://127.0.0.1:8091/` intacto; decisiones C1–C8 pendientes; ninguna implementación autorizada; Actividad 4 no iniciada | `docs/MXMED_AUDITORIA_V2_IDENTIDAD_ACCESO_RECLAMACION_RECUPERACION_Y_SESIONES.md`; `/tmp/mxmed-activity03-identity-access-security-audit-v2/`; rama `audit/mxmed-claim-registration-login-recovery-sessions-security-v2` | `IDENTITY_ACCESS_SESSION_SECURITY_AUDIT_V2_READY_FOR_DIRECTOR_REVIEW`; candidata a cierre, pendiente de revisión/integración; gate móvil `MOBILE_RESPONSIVE_FINALIZATION_PENDING` |

### PP-287 — Decisiones directorales V2 de identidad, acceso, recuperación y sesiones

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Aprobar expresamente C1–C8 para el contrato futuro de identidad y el alcance restringido de Actividad 4 | La auditoría confirmó que autenticación, propiedad, roles, capacidades y sesiones no deben confundirse ni habilitarse sin una autoridad canónica | `UI-0` documental; códigos `ACCOUNT_ENTITY_PROFILE_MEMBERSHIP_MODEL`, `VERIFIED_START_PLUS_MANUAL_REVIEW`, `VERIFICATION_AND_CONSENT_BEFORE_ACTIVATION`, `SECURE_PASSWORD_EMAIL_RECOVERY_AND_PRIVILEGED_MFA`, `AWS_SERVER_SIDE_FAIL_CLOSED_SESSIONS`, `MULTI_DIMENSION_RATE_LIMIT_WITH_PROGRESSIVE_BACKOFF`, `FIVE_REVOCABLE_DEVICE_SESSIONS`, `SCOPED_AUDITED_SUPPORT_ASSISTED_SESSION`; inactividad 60 min, duración absoluta 12 h, máximo 5 sesiones; MFA obligatorio para administradores/operadores; reclamación con revisión manual; gates 4A–4D; sin implementación, sin cambios productivos, sin UI en este cierre | `docs/MXMED_AUDITORIA_V2_IDENTIDAD_ACCESO_RECLAMACION_RECUPERACION_Y_SESIONES.md`; `/tmp/mxmed-activity03-identity-access-security-decisions-v2/`; rama `audit/mxmed-claim-registration-login-recovery-sessions-security-v2` | `IDENTITY_ACCESS_SESSION_DIRECTOR_DECISIONS_READY_FOR_INTEGRATION`; Actividad 3 candidata a integración; avance oficial `2/22`; Actividad 4 `NO INICIADA`; capítulo móvil final `UI-3` pendiente |

### PP-288 — Gate 4A V2: modelo canónico de cuentas, membresías y consentimientos

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-19 | Ejecutar Gate 4A de Actividad 4 como implementación UI-0 de modelo account → membership → entidad/perfil, consentimiento versionado y migraciones reversibles | Aplicar C1 y C3 sin confundir persistencia con autenticación, sesiones, claim o capacidades de suscripción | Actividad 4/22; `auth_accounts`, `auth_account_consents`, `auth_account_memberships`; FKs canónicas a `profiles_doctors.doctor_id`/`medical_groups.group_id`; forward/rollback/segundo forward; pruebas aisladas; sin datos reales, login, sesiones, reclamación, frontend ni despliegue; 8091 intacto; Gate 4B no iniciado; Actividad 4 no integrada; capítulo móvil final UI-3 pendiente | `docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4A_MODELO_Y_MIGRACIONES.md`; `/tmp/mxmed-activity04-gate4a-identity-model-migrations-v2/`; rama `feature/mxmed-identity-auth-session-foundation-v2` | `IDENTITY_MODEL_MIGRATIONS_GATE_4A_READY_FOR_DIRECTOR_REVIEW`; Gate 4A candidato a cierre; avance oficial `3/22`; Gate 4B `NO INICIADO` |

### PP-289 — Gate 4B V2: autenticación y recuperación seguras sin sesiones

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Ejecutar Gate 4B como implementación UI-0 de credenciales Argon2id, registro pendiente, verificación de correo, recuperación de un solo uso y rate limiting multidimensional, sin sesiones | C4 y C6 exigen separar comprobación de credenciales de sesión, evitar enumeración, custodiar tokens por hash y diferir logout/revocación a Gate 4C | Actividad 4/22; Argon2id; política 12–128; `auth_account_credentials`, `auth_account_one_time_tokens`, `auth_rate_limit_buckets`; verificación 24 h; recuperación 30 min; `credential_version`; anti-enumeración; notificación sólo simulada; endpoints/UI `0`; sin SMS/passwordless/MFA/claim; 8091 intacto; Gate 4C no iniciado; Actividad 4 no integrada; capítulo móvil final UI-3 pendiente | `docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4B_AUTENTICACION_Y_RECUPERACION.md`; `/tmp/mxmed-activity04-gate4b-auth-recovery-v2/`; rama `feature/mxmed-identity-auth-session-foundation-v2` | `SECURE_AUTHENTICATION_RECOVERY_GATE_4B_READY_FOR_DIRECTOR_REVIEW`; Gate 4B candidato a cierre; avance oficial `3/22`; Gate 4C `NO INICIADO` |

### Pendientes reales detectados (fase actual P16)
- Consolidar cierre funcional del flujo “Integrar a caso clínico” en todos los contextos embebidos (historial base, expandido y casos clínicos).
- Validar y documentar paridad total de apertura de detalle por tipo de card dentro de “Casos clínicos” (sin excepciones por tipo).
- Terminar limpieza de acciones contextuales para que no reaparezcan CTAs redundantes por tipo/contexto en futuras iteraciones.

### Mejoras UX aún incompletas
- Definir criterio final de jerarquía visual entre Historial general vs Casos clínicos (qué acciones viven en cada contexto y cuáles no).
- Cerrar guía de microcopy unificada para mensajes de integración/conflicto/estado de caso activo.
- Completar baseline visual de cards/documentos/modales para evitar divergencias entre renderer principal y variantes embebidas.

### Siguientes módulos a intervenir (alineado al plan)
1. Clinical UI (`modules/clinical/ui/historial.php`): cierre de consistencia de interacción y estados.
2. Host principal (`index.html` / puente embed): validación final de eventos cross-iframe y aperturas de detalle.
3. Documentación operativa (`docs/*`): checklist de aceptación P16 y criterios de salida a siguiente fase.

### Riesgos / áreas delicadas
- Riesgo de regresión en handlers por cambios de markup en cards embebidas.
- Riesgo de desalineación entre fuente de verdad de casos y estado visual si no se refrescan listas tras conflictos/activación.
- Riesgo de reintroducir ruido UX al mezclar ajustes visuales con cambios funcionales en el mismo ciclo.

### PP-290 — Gate 4C V2: sesiones server-side y autorización fail-closed

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Ejecutar Gate 4C con sesiones server-side Valkey, token opaco HMAC-SHA256, fail-closed, rotación, revocación y autorización account → membership → profile → capability | C5/C7 requieren separar autenticación de autorización, limitar cinco dispositivos y revocar por `credential_version` | Actividad 4/22, UI-0; idle 3,600 s, absolute 43,200 s, touch 300 s; reconciliación histórica 1,800 → 3,600 bajo `C5_APPROVED_BY_DIRECTOR`; AWS/CDK modificado sólo en configuración/tests de TTL; password recovery 30 min preservado; endpoints/UI `0`; AWS deploys y recursos modificados `0`; Gate 4D no iniciado; Actividad 4 no integrada; capítulo móvil UI-3 pendiente | `docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4C_SESIONES_Y_AUTORIZACION.md`; `docs/PERFIL_PUBLICO_MEDICO_CONTRATO_MXMED.md`; `/tmp/mxmed-activity04-gate4c-session-authorization-v2/`; rama `feature/mxmed-identity-auth-session-foundation-v2` | `SERVER_SIDE_SESSION_AUTHORIZATION_GATE_4C_READY_FOR_DIRECTOR_REVIEW`; tercer commit candidato a revisión; avance oficial `3/22`; Gate 4D `NO INICIADO` |

### PP-291 — Corrección Gate 4C: adaptador productivo de autoridad de capacidades

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Autorizar el cuarto commit para conectar `SessionCapabilityAuthorityPort` mediante `ExistingCapabilityAuthorityAdapter` con `ExistingCapabilityAuthorityService` real y tipar la dependencia fail-closed | La revisión detectó un puerto existente sin implementación productiva; los fakes no demostraban integración real | Corrección backend UI-0; reglas duplicadas `0`; sólo adaptador, autorización, tests específicos y documentación; endpoints/UI/sesiones HTTP/cookies `0`; force push `0`; Gate 4C continúa candidato a cierre; Gate 4D no iniciado; avance oficial `3/22` | `docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4C_SESIONES_Y_AUTORIZACION.md`; `/tmp/mxmed-activity04-gate4c-capability-adapter-fix-v2/`; rama `feature/mxmed-identity-auth-session-foundation-v2` | `GATE_4C_CAPABILITY_AUTHORITY_ADAPTER_FIX_READY_FOR_DIRECTOR_REVIEW`; cuarto commit autorizado |
### PP-292 — Gate 4D V2: integración HTTP y UI de identidad aprobada

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Ejecutar la integración funcional HTTP de identidad sobre la UI-3 aprobada, con preview HTTPS aislado y composición fail-closed | Cerrar el circuito aprobado de registro, verificación, login, sesión actual, recuperación, reset y logout sin alterar copy, layout ni la UI oficial 8091 | Actividad 4/22; candidato 8140 HTTPS, backend 8141, Valkey local 6384 y base sintética mxmed_gate4d_preview_; cookie __Host-mxmed_session; CSRF, same-origin, JSON, no-store, nosniff, CSP; registro pendiente y anti-enumeración; claim/MFA/passwordless/social/AWS/Stripe excluidos; comparación visual DOM/texto/estilo/geometría/píxel con diferencia cero; smoke móvil intermedio; 8091 intacto; prototipo UI-3 inmutable | docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4D_INTEGRACION_HTTP_UI.md; /tmp/mxmed-activity04-gate4d-http-ui-integration-v2/; rama feature/mxmed-identity-auth-session-foundation-v2 | APPROVED_IDENTITY_UI_HTTP_INTEGRATION_GATE_4D_V2_READY_FOR_DIRECTOR_REVIEW; quinto commit autorizado; 8140 permanece activo |

### PP-294 — Auditoría de APIs, datos, permisos, scopes, privacidad y retención V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Ejecutar auditoría estática y read-only transversal de APIs, datos, autorización, scopes, ownership, privacidad, retención, logs, auditoría y riesgo R0–R3 | La Actividad 4 cerró identidad y sesiones en una candidata local, pero la autoridad transversal entre dominios todavía requiere evidencia separada | Actividad 5/22; 12 entrypoints HTTP PHP, 50 declaraciones route/method explícitas y familias dinámicas; account/membership/entity/profile/role/scope/capability; tres planos; datos públicos, identidad, contacto, Agenda, pacientes, clínicos, pagos, suscripciones, logs y auditoría; sin código, UI, SQL real, AWS, Stripe, writes, secretos o datos reales; Actividad 6 permanece bloqueada | docs/MXMED_AUDITORIA_APIS_DATOS_PERMISOS_SCOPES_PRIVACIDAD_RETENCION.md; /tmp/mxmed-activity05-apis-data-permissions-audit-v2/; rama audit/mxmed-apis-data-permissions-scopes-privacy-retention-v2 | PASS_STATIC_AUDIT_READY_FOR_DIRECTOR_REVIEW; Actividad 5 no concluida oficialmente; contador 4/22; 18 pendientes |

### PP-295 — Aprobación directoral de decisiones de APIs, datos, permisos, privacidad y retención V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Aprobar DEC-012A a DEC-012F para cerrar documentalmente la Actividad 5 y dejarla lista para integración fast-forward | La auditoría transversal requiere una frontera backend fail-closed, fuente canónica por dominio, retención y disposición controladas, audit trail unificado y mecanismos de soporte/break-glass separados | Actividad 5/22, UI-0; seis decisiones aprobadas; AWS deploy 0; SQL real 0; datos reales 0; cambios funcionales 0; antes de integración 4/22 y 18 pendientes; después de integración prevista 5/22 y 17 pendientes; Actividad 6 `GATES_RESOLVED_NOT_STARTED`, no iniciada | docs/MXMED_DECISIONES_V2_APIS_DATOS_PERMISOS_PRIVACIDAD_RETENCION.md; docs/MXMED_AUDITORIA_APIS_DATOS_PERMISOS_SCOPES_PRIVACIDAD_RETENCION.md; /tmp/mxmed-activity05-director-decisions-closure-v2/; rama audit/mxmed-apis-data-permissions-scopes-privacy-retention-v2 | `APPROVED_BY_DIRECTOR`; `ACTIVITY_5_AUDIT_DECISIONS_READY_FOR_FAST_FORWARD_INTEGRATION`; integración aún no realizada |

### PP-296 — Contratos transversales PG-08 Gate 6A V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Implementar contratos puros transversales para traducir DEC-012A–DEC-012F y preparar los Gates 6B–6F sin conectar comportamiento productivo | La Actividad 6 requiere una base única para autorización, riesgo, contexto, fuentes canónicas, retención, disposición, auditoría futura y acceso excepcional | Gate 6A/Actividad 6, UI-0; `modules/platform/contracts`, namespace `Platform\\Contracts`; planos customer/professional, internal operator, governance/emergency y public/system; R0–R3; contexto y decisiones deny-by-default; reason codes; canonical source; retención/disposición; audit trail diferido; soporte/break-glass deshabilitados; código aditivo; runtime wiring 0; UI 0; SQL/migraciones 0; AWS 0; datos reales 0; contador 5/22; Actividad 6 no concluida | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6A_CONTRATOS_TRANSVERSALES.md; `/tmp/mxmed-activity06-gate6a-contracts-foundation-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | `PASS_GATE_6A_CROSS_CUTTING_CONTRACTS_READY_FOR_REVIEW`; Gate 6A `CLOSED_INTERNAL_READY_FOR_GATE_6B_REVIEW`; Gate 6B bloqueado/no iniciado |

### PP-297 — Frontera central de autorización PG-08 Gate 6B V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Implementar `AuthorizationBoundary` puro, determinista y fail-closed sobre los contratos de Gate 6A, aplicando DEC-012A y DEC-012E | Los Gates posteriores requieren una única evaluación backend de contexto confiable, membership, ownership, role, scope, capability, acción, recurso, riesgo y obligación de auditoría | Gate 6B/Actividad 6, UI-0; orden de denegación estable; `TrustedAuthorizationContext`; roles/scopes/capabilities independientes; rutas public/system explícitas; R2/R3 fail-closed; adaptadores de audit sólo de prueba; runtime wiring 0; endpoints 0; UI 0; SQL/migraciones 0; AWS 0; datos reales 0; contador 5/22 | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6B_FRONTERA_AUTORIZACION.md; `/tmp/mxmed-activity06-gate6b-authorization-boundary-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | `PASS_GATE_6B_AUTHORIZATION_BOUNDARY_READY_FOR_REVIEW`; Gate 6B cerrado internamente; Gate 6C no iniciado |

### PP-298 — Fuente canónica, retención y disposición segura PG-08 Gate 6C V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Implementar autoridad canónica, registro de retención y planificador de disposición segura para traducir DEC-012B, DEC-012C y DEC-012D sin ejecución | Los dominios requieren una sola autoridad de escritura, políticas unresolved explícitas, legal hold y disposición R3 simulada antes de cualquier conexión runtime | Gate 6C/Actividad 6, UI-0; `CanonicalSourceAuthority`; `RetentionPolicyRegistry`; `DispositionRequest`; `DispositionPlanner`; una canonical_write; unresolved fail-closed; lecturas sin side effects; legal hold; clínico independiente de suscripción; delete/anonymize/export_mass R3; simulación exclusivamente; ejecución real 0; persistencia 0; endpoint/runtime wiring 0; UI 0; SQL/migraciones 0; AWS 0; datos reales 0; contador 5/22 | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6C_FUENTE_CANONICA_RETENCION_DISPOSICION.md; `/tmp/mxmed-activity06-gate6c-canonical-retention-disposition-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | `PASS_GATE_6C_CANONICAL_SOURCE_RETENTION_DISPOSITION_READY_FOR_REVIEW`; Gate 6C cerrado internamente; Gate 6D no iniciado |

### PP-299 — Audit trail transversal append-only PG-08 Gate 6D V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Implementar fundamentos persistentes, minimizados, append-only, idempotentes y tamper-evident del audit trail transversal definido en DEC-012E, sin wiring runtime | R2/R3 requieren una obligación de auditoría correlacionable, fail-closed y sin contenido sensible; Gate 6D prepara contratos, servicios puros, adapter PDO y migración para revisión antes de conectar comportamiento productivo | Actividad 6/22, UI-0; `AuditTrailPort` preservado; actores real/efectivo, sujeto, correlation/request/case; allow-list; canonicalización determinista; event_id estable; cadena SHA-256; verificador; repositorio PDO sólo INSERT; migración versionada no ejecutada; `retention_unresolved`; tablas/eventos reales 0; runtime/endpoint wiring 0; UI 0; AWS 0; Gate 6E no iniciado; contador 5/22; pendientes 17 | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6D_AUDIT_TRAIL_TRANSVERSAL.md; `/tmp/mxmed-activity06-gate6d-transversal-audit-trail-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | `PASS_GATE_6D_TRANSVERSAL_AUDIT_TRAIL_READY_FOR_REVIEW`; Gate 6D cerrado internamente listo para revisión de Gate 6E; Actividad 7 bloqueada |

### PP-300 — Support-assisted session y break-glass deshabilitados PG-08 Gate 6E V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | Implementar bases puras, separadas, temporales, auditables y fail-closed de support-assisted session y break-glass conforme a DEC-012F, manteniendo ambas funciones deshabilitadas y no activables | El acceso privilegiado futuro requiere actor real/efectivo, scope mínimo, caso, motivo por referencia, MFA, reautenticación, aprobación, visibilidad, revisión posterior y hard-stop antes de cualquier sesión real | Actividad 6/22, UI-0; contratos reutilizados de Gate 6A; evaluadores separados; feature flags false; `activatable=false`; scopes mínimos; expiración finita; duración máxima unresolved; una aprobación support y doble aprobación break-glass; auditoría Gate 6D fail-closed; clinical default deny; lifecycle teórico; sesiones/tokens/cookies/impersonaciones/privilegios 0; endpoint/runtime/session wiring 0; UI 0; SQL/migraciones 0; AWS 0; Gate 6F no iniciado; contador 5/22; pendientes 17 | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6E_SUPPORT_ASSISTED_BREAK_GLASS.md; `/tmp/mxmed-activity06-gate6e-support-break-glass-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | `PASS_GATE_6E_SUPPORT_ASSISTED_BREAK_GLASS_READY_FOR_REVIEW`; Gate 6E cerrado internamente listo para revisión de Gate 6F; Actividad 7 bloqueada |

### PP-301 — Contención legacy e integración interna PG-08 Gate 6F V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Ejecutar contención fail-closed de verificadores legacy, pureza read-only del Catálogo, manifiesto de superficies y readiness interno NO-GO, con eliminación de consultorios temporalmente deshabilitada y reactivación versionada | Los callers legacy iniciales eran ejecutables y aceptaban credenciales simuladas o fallos de red; la aprobación directorial UI-2 autoriza preservar la acción visual sin eliminarla hasta disponer de reautenticación segura | Actividad 6/22; UI-0 más UI-2 acotada; endpoints 410; Catálogo GET puro; DDL/seed preparados no ejecutados; blockers explícitos Profiles/Agenda/schema y consultorio; baseline runtime schema 21 sin aumento; integration wiring 0; support-assisted/break-glass deshabilitados; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT`; programa sin integrar; contador 5/22; Actividad 7 bloqueada | docs/MXMED_IMPLEMENTACION_V2_PG08_GATE_6F_CONTENCION_LEGACY_INTEGRACION_QA.md; `/tmp/mxmed-activity06-gate6f-legacy-containment-integration-v2/`; rama `feature/mxmed-apis-data-permissions-privacy-foundations-v2` | “La eliminación de consultorios secundarios queda temporalmente deshabilitada, no eliminada. Su reactivación permanece obligatoria y sólo procederá mediante reautenticación segura, autorización server-side, auditoría y aprobación funcional.”; Gate 6F listo para post-validación y revisión UI-2 |

### PP-293 — Aprobación visual y funcional y cierre de la Actividad 4 V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-20 | “Apruebo visual y funcionalmente la candidata Gate 4D en 8140 como base para integrar y cerrar la Actividad 4.” | La candidata completó los Gates 4A–4D y la corrección de autoridad de capacidades sin alterar 8091 ni el prototipo UI-3 aprobado | Rama feature/mxmed-identity-auth-session-foundation-v2 en HEAD 9f4e7eeee28b9f83fbbec07f82cbac4477c05800; Gate 4A, Gate 4B, Gate 4C, corrección de autoridad y Gate 4D concluidos; visual diff 0; HTTPS local; cookie segura; CSRF; anti-enumeración; guards fail-closed; base y store aislados; reclamación deshabilitada; AWS writes 0; DB oficial writes 0; candidata 8140 preservada; rollback probado; INTERIM_MOBILE_SMOKE_ONLY; aprobación móvil final pendiente; Actividad 5 no iniciada | docs/MXMED_IMPLEMENTACION_V2_IDENTIDAD_GATE_4D_INTEGRACION_HTTP_UI.md; /tmp/mxmed-activity04-director-approval-closure-v2/; /tmp/mxmed-activity04-gate4d-http-ui-integration-v2/ | APPROVED_BY_DIRECTOR; Actividad 4 READY_FOR_FAST_FORWARD_INTEGRATION; contador 3/22 hasta integración, 4/22 después; 18 pendientes |

### PP-302 — Auditoría PG-03 de Agenda, citas, contactos y duplicados V2

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Ejecutar la Actividad 7/22 como auditoría técnica, funcional, de seguridad, privacidad y arquitectura, sin implementación ni escrituras; dejar DEC-013A–L como propuestas para aprobación del director | Agenda mantiene autoridad de actor parcialmente cliente/autoritativa, fuentes de horario y disponibilidad distribuidas, estados de cita sin máquina formal, flujos públicos/OTP con datos y DDL durante runtime, contactos privados con separación incompleta y sin deduplicación/merge; los hallazgos deben cerrarse antes de Actividad 8 | UI-0; read-only; sin SQL ejecutado, datos reales, OTP real, citas, fusiones, runtime candidato, AWS o integración; programa no integrado; contador oficial 6/22; Actividad 8 bloqueada; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT`; DEC-013A–L `PENDING_DIRECTOR_APPROVAL` | `docs/MXMED_AUDITORIA_V2_PG03_AGENDA_DISPONIBILIDAD_CITAS_CONTACTOS_DUPLICADOS.md`; `docs/MXMED_DECISIONES_PROPUESTAS_V2_PG03_AGENDA_DISPONIBILIDAD_CITAS_CONTACTOS_DUPLICADOS.md`; `/tmp/mxmed-activity07-pg03-agenda-audit-v2/`; rama `audit/mxmed-agenda-availability-appointments-contacts-duplicates-v2` | `PASS_ACTIVITY_7_PG03_AUDIT_READY_FOR_DIRECTOR_DECISIONS`; no aprobación ni integración |

### PP-303 — Aprobación directorial DEC-013A–L de PG-03

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Aprobar individualmente DEC-013A–L como contrato funcional y arquitectónico de PG-03 para Agenda, disponibilidad, citas, contactos, identidad de pacientes, duplicados, migraciones, auditoría, retención y rollout | Cerrar los bloqueos de decisión detectados por la auditoría de Actividad 7 y permitir preparar una implementación por gates, sin autorizar aún cambios de runtime | UI-0; 12/12 decisiones aprobadas; runtime changes 0; SQL ejecutado 0; OTP real 0; citas reales 0; merges reales 0; Actividad 8 no iniciada; programa todavía no integrado; contador oficial 6/22; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT`; Actividad 7 `READY_FOR_POSTVALIDATION_AND_INTEGRATION` | Documento DEC-013 actualizado; aprobación explícita del director; evidencia de auditoría original; evidencia de corrección del resumen ejecutivo; nueva evidencia temporal `/tmp/mxmed-activity07-director-decisions-approval-v2/` | `APPROVED_BY_DIRECTOR`; `ACTIVITY_7_READY_FOR_POSTVALIDATION_AND_INTEGRATION`; `ACTIVITY_8_BLOCKED` |

### PP-304 — Contratos canónicos PG-03 Gate 8A

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Implementar la capa canónica pura y no conectada para DEC-013A–L antes de cambiar rutas, repositorios, esquema o UI de Agenda | Evitar que la implementación de Actividad 8 replique autoridad cliente, estados dispersos, DDL runtime o contratos inconsistentes | UI-0; contratos puros; tests deterministas; runtime wiring 0; route behavior changes 0; SQL ejecutado 0; datos reales 0; OTP real 0; citas reales 0; merges reales 0; servidor oficial 8091 intacto; Actividad 8 `IN_PROGRESS`; Gate 8B no iniciado; Actividad 9 `BLOCKED`; contador oficial 7/22; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT` | `docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8A_CONTRATOS_CANONICOS.md`; `modules/agenda/contracts/`; `modules/agenda/tests/Gate8ACanonicalContractsTest.php`; `/tmp/mxmed-activity08-gate8a-canonical-contracts-v2/` | `PASS_ACTIVITY_8_GATE_8A_CANONICAL_CONTRACTS_IMPLEMENTED`; `ACTIVITY_8_IN_PROGRESS`; `GATE_8B_NOT_STARTED`; `ACTIVITY_9_BLOCKED` |

### PP-305 — Autoridad server-side PG-03 Gate 8B

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Implementar el resolver autoritativo de actores de Agenda utilizando sesión Identity validada, membresía activa, perfil canónico, ownership, binding backend y AuthorizationBoundary, sin conectar todavía el router legacy | Eliminar la dependencia arquitectónica de rol, identidad y alcance declarados por headers, query, body o fallback antes del cutover de rutas | UI-0; autoridad server-side implementada; actor real/efectivo separados; claims cliente no confiables; matriz de rutas privadas; AuthorizationBoundary reutilizado; audit port inyectado; router legacy sin modificar; runtime wiring 0; route behavior changes 0; SQL ejecutado 0; datos reales 0; OTP real 0; citas reales 0; merges reales 0; 8091 intacto; Gate 8C no iniciado; Actividad 8 en progreso; Actividad 9 bloqueada; contador 7/22; readiness `NO_GO_LEGACY_BLOCKERS_PRESENT` | `docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8B_AUTORIDAD_SERVER_SIDE.md`; `modules/agenda/security/`; `modules/agenda/tests/Gate8BServerAuthoritativeActorsTest.php`; `/tmp/mxmed-activity08-gate8b-server-authority-v2/` | `PASS_ACTIVITY_8_GATE_8B_SERVER_AUTHORITATIVE_ACTORS_IMPLEMENTED`; `ACTIVITY_8_IN_PROGRESS`; `GATE_8C_NOT_STARTED`; `ACTIVITY_9_BLOCKED` |

### PP-306 — Horario y disponibilidad canónicos PG-03 Gate 8C

| Fecha | Decisión | Motivo | Alcance | Evidencia | Estado |
|---|---|---|---|---|---|
| 2026-07-21 | Implementar una fuente canónica, versionada, inmutable y específica por perfil/consultorio para horario y una proyección determinista de disponibilidad calculada desde ventanas base, feriados, overrides y colisiones | Eliminar divergencias conceptuales entre horario editable y disponibilidad calculada antes de reconciliar las tablas legacy o conectar rutas | UI-0; canonical schedule source; version selector; profile y consultorio obligatorios; timezone IANA; duration y gap canónicos; intervalos semiabiertos; overrides open/close; holiday closure; collisions last; slots deterministas; cambio de consultorio aislado; ScheduleAvailabilityContract como read model; editableAuthority false; safe return points preservados; router legacy sin cambios; runtime wiring 0; route behavior changes 0; SQL ejecutado 0; datos reales 0; OTP real 0; citas reales 0; merges reales 0; AWS writes 0; 8091 intacto; Gate 8D no iniciado; Actividad 8 en progreso; Actividad 9 bloqueada; contador 7/22; readiness NO_GO_LEGACY_BLOCKERS_PRESENT | `docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8C_HORARIO_DISPONIBILIDAD_CANONICOS.md`; `modules/agenda/availability/`; `modules/agenda/tests/Gate8CCanonicalScheduleAvailabilityTest.php`; `/tmp/mxmed-activity08-gate8c-canonical-schedule-availability-v2/` | `PASS_ACTIVITY_8_GATE_8C_CANONICAL_SCHEDULE_AVAILABILITY_IMPLEMENTED`; `ACTIVITY_8_IN_PROGRESS`; `GATE_8D_NOT_STARTED`; `ACTIVITY_9_BLOCKED` |
### PP-307 — Ciclo de citas, idempotencia y concurrencia PG-03 Gate 8D

Fecha:
2026-07-21

Decisión:
Implementar una autoridad de dominio pura, versionada y fail-closed para el ciclo de vida de citas, optimistic aggregate version, idempotency replay/conflict, identidad canónica de slot, detección determinista de superposición, claims activos, eventos append-only y el plan transaccional requerido para impedir doble reserva.

Motivo:
Formalizar estados, transiciones e invariantes antes de conectar el router legacy, persistencia, locks, unique constraints o migraciones.

Alcance:

- UI-0;
- siete estados canónicos;
- matriz exacta Gate 8A;
- lifecycle version 1;
- estados terminales canceled/no_show;
- cinco estados ocupantes;
- optimistic aggregate version;
- command tipado;
- idempotency key;
- fingerprint SHA-256;
- replay seguro;
- idempotency conflict 409;
- record minimizado;
- slot RFC3339;
- timezone IANA;
- intervalo semiabierto;
- active slot claim;
- overlap conflict;
- slots adyacentes permitidos;
- simulación pura de doble reserva;
- mutation plan de 12 pasos;
- locks e índice unique requeridos, no ejecutados;
- evento append-only;
- actor real y efectivo;
- Agenda separada de Clinical;
- safe return points preservados;
- router legacy sin cambios;
- runtime wiring 0;
- route behavior changes 0;
- SQL ejecutado 0;
- datos reales 0;
- OTP real 0;
- citas reales 0;
- merges reales 0;
- AWS writes 0;
- 8091 intacto;
- Gate 8E no iniciado;
- Actividad 8 en progreso;
- Actividad 9 bloqueada;
- contador 7/22;
- readiness NO_GO_LEGACY_BLOCKERS_PRESENT.

Estado:

PASS_ACTIVITY_8_GATE_8D_APPOINTMENT_LIFECYCLE_IDEMPOTENCY_IMPLEMENTED;
ACTIVITY_8_IN_PROGRESS;
GATE_8E_NOT_STARTED;
ACTIVITY_9_BLOCKED.

### PP-308 — Gate 8E: Agenda pública, OTP y privacidad de contacto

Identificador:
`pg03-public-agenda-otp-privacy`; versión de contrato `1`.

Resultado:
`PASS_ACTIVITY_8_GATE_8E_PUBLIC_AGENDA_OTP_CONTACT_PRIVACY_IMPLEMENTED`.

Baseline:
Gate 8A, Gate 8B, Gate 8C y Gate 8D postvalidados; Gate 8E implementado
pendiente de postvalidación; Gate 8F no iniciado; Actividad 8 en progreso;
Actividad 9 bloqueada; contador 7/22; readiness
`NO_GO_LEGACY_BLOCKERS_PRESENT`.

Clasificación y alcance:
UI-0. Se define una autoridad canónica, versionada, determinista y fail-closed
para intención de reserva pública, desafío OTP, verificación, replay,
privacidad, cancelación, auditoría y handoff declarativo. El flujo público no es
autoritativo y exige handoff server-authoritative. No conecta runtime, rutas,
controladores legacy, repositorios, servicios, persistencia, SQL, migraciones,
configuración ni servidor.

Política OTP:
canales exactos `sms` y `email`; seis dígitos; TTL 600 segundos; máximo cinco
intentos; un desafío activo por intención; raw OTP nunca persistido, respondido,
registrado ni emitido en eventos; debug OTP canónico deshabilitado; decisión de
rate-limit server-authoritative obligatoria y ausencia fail-closed.

Estados:
`pending`, `verified`, `expired`, `locked`, `consumed`. El replay verificado es
idempotente, devuelve el mismo grant digest, no añade intentos/eventos/handoffs y
los estados terminales no vuelven a pending.

Binding y privacidad:
la intención liga intent, profile, consultorio, slot Gate 8D, canal, referencia
opaca de contacto y versión mediante SHA-256 determinista. El contacto sólo
conserva un keyed digest de 64 hex y una máscara de proyección; no conserva ni
expone teléfono, email, paciente, domicilio o datos clínicos. La proyección usa
allow-list cerrada y errores genéricos.

Grant, capability y auditoría:
el grant de verificación y la capacidad de cancelación son minimizados,
deterministas, ligados a intención/binding y de un solo uso. Los eventos son
append-only, minimizados y sin OTP, credenciales, PII, tokens, cookies, headers o
payload libre.

Handoff Gate 8D:
se declaran `create_pending_otp_appointment`, `confirm_verified_appointment`,
`cancel_expired_appointment`, `cancel_locked_appointment` y
`cancel_by_public_capability`, con lifecycle version 1, razón allow-list,
`server_authoritative_required=true`, resolución de actor por Gate 8B y mutación
delegada a Gate 8D. No se acepta actor, estado, razón o autoridad del cliente.

Plan transaccional:
16 pasos declarativos desde `begin_transaction` hasta `commit`, con locks de
intención, rate-limit, desafío e idempotencia; verificación de binding/estado/
expiración/intentos/credencial; grant y auditoría en la misma transacción;
delegación Gate 8D; cualquier error requiere rollback. No ejecuta operaciones,
no permite escritura directa de cita ni SQL directo.

Idempotencia y rate-limit:
la misma operación aceptada se reproduce sin mutaciones; una clave incompatible
produce conflicto 409. La decisión de rate-limit debe venir tipada del adaptador
futuro y nunca se sustituye por una inferencia local.

Separaciones:
`PATIENT_IDENTITY_RESOLUTION_DEFERRED_TO_GATE_8F`; no se crea paciente ni se
hace merge. `agendaAppointmentIsClinicalEncounter() === false`; no se crea
encounter, expediente, nota, diagnóstico, receta o documento clínico.

Legado contenido:
las 16 superficies públicas existentes permanecen
`LEGACY_CONTAINED_PENDING_ADAPTER_AND_ROLLOUT`; no se refactorizan ni conectan.
El resultado declara cero runtime, rutas, SQL, OTP real, citas reales y datos
reales modificados.

Safe return, pruebas y estado:
el retorno seguro es el HEAD Gate 8D postvalidado
`4d44d14abe743bba0424c1a7856b231c4a9a3dc1`; rollback por `git revert --no-edit
<gate8e_commit>` en worktree detached. La prueba Gate 8E, las regresiones
acumulativas, lint, pureza estática, mutaciones negativas y simulación futura
PP-309 deben pasar antes de postvalidar.

Gate 8E queda `IMPLEMENTED_READY_FOR_POSTVALIDATION`; Gate 8F sigue `NOT_STARTED`;
Actividad 8 sigue `IN_PROGRESS`; Actividad 9 sigue `BLOCKED`. No iniciar Gate 8F,
no integrar y no crear checkpoint.

### PP-309 — Gate 8F: identidad canónica, duplicados y merge deshabilitado

Identificador y resultado:
`pg03-patient-identity-duplicates`, versión `1`;
`PASS_ACTIVITY_8_GATE_8F_PATIENT_IDENTITY_DUPLICATES_IMPLEMENTED`.

Baseline y preflight:
HEAD Gate 8E postvalidado `3877e26078aec32e2a9e4b0c58d7872b8033a27b`;
`PASS_ACTIVITY_8_GATE_8F_PREFLIGHT_READY`. Gate 8A, Gate 8B, Gate 8C, Gate
8D y Gate 8E permanecen postvalidados. Clasificación UI-0.

Autoridad y fuentes:
la única identidad canónica es `patients_patients.patient_id`, propiedad de
`modules/patients`. Los inputs exactos son `canonical_patient_id` y
`legacy_patient_key_hash`. La clave legacy cruda está prohibida; el hash debe
llegar como SHA-256 opaco de 64 hex generado por un adaptador confiable.

Evidencia opaca:
name, birthdate, phone y email se reciben sólo como referencias de 64 hex; sex
usa allow-list cerrada. No se reciben nombre, fecha, contacto, domicilio,
expediente, payload libre ni información clínica crudos.

Niveles exactos:
los matches fuertes, en precedencia, son `contact_birthdate_exact`,
`contact_name_exact` y `name_birthdate_sex_exact`. Los indicios débiles son
`name_birthdate_exact`, `contact_only` y `name_only`; nunca autorizan mapeo
automático. `no_match` no aporta coincidencia.

Contradicciones y ambigüedad:
contacto o nombre coincidente con birthdate distinta, candidato fuerte no
elegible, evidencia fuerte compartida, IDs repetidos, versiones inválidas o
evidencia mal formada fallan cerrado. Dos fuertes del mejor nivel producen
`ambiguous`; nunca se elige el primer candidato ni se suman scores.

Algoritmo:
input canónico existente/elegible produce `already_canonical`; no elegible
produce `review_required`; ausente produce `not_found`. Input legacy con match
fuerte único produce `mapped_from_legacy`; indicio débil o contradicción produce
`review_required`; múltiples fuertes producen `ambiguous`; sin señal produce
`create_minimal_required`.

Creación y duplicate review:
`create_minimal_required` sólo declara el modo eventual
`created_minimal_patient`; no crea paciente. `PatientDuplicateReview` contiene
ID determinista, razón cerrada, IDs canónicos ordenados/digests, tier y
fingerprint, sin PII ni evidencia cruda.

Merge deshabilitado:
automatic merge, manual merge, survivor selection, source deletion, clinical
record reassignment, contact/consent consolidation y merge endpoint permanecen
false. Cualquier solicitud falla con `patient_merge_disabled` y razón
`MERGE_DISABLED_PENDING_SEPARATE_APPROVAL_AND_IMPLEMENTATION`.

Auditoría:
eventos readonly, deterministas y append-only contienen operación, correlación,
source, input type, fingerprints/digests, outcome, tier, actores Gate 8B,
timestamp, flags de review/create y `merge_allowed=false`; nunca legacy raw,
contacto, nombre, fecha, sex, payload, notas o datos clínicos.

Fronteras:
Gate 8B resuelve actor real y efectivo. Gate 8E verifica contacto/flujo público,
pero `public_verified` no implica paciente existente y una referencia de
contacto no se convierte por sí sola en identidad. Persistencia, migración,
retention, backfill y rollout quedan
`IDENTITY_PERSISTENCE_MIGRATION_RETENTION_ROLLOUT_DEFERRED_TO_GATE_8G`.
`patientIdentityResolutionIsClinicalEncounter() === false`; no se crean
encounters ni se mueven o reasignan documentos, expedientes, casos, recetas o
notas.

Plan transaccional:
14 pasos declarativos desde `begin_transaction` hasta `commit`, con lock de
fingerprint e idempotencia, verificación Gate 8B, carga/validación de candidatos,
evaluación exacta, detección de conflictos, merge deshabilitado, selección de
existente o plan mínimo, delegación futura a Patients, auditoría e idempotencia.
Los errores requieren rollback. No ejecuta operaciones ni permite creación,
actualización, links, mutación clínica o SQL directos.

Privacidad, determinismo y pureza:
todos los timestamps e IDs son explícitos o derivados por SHA-256 canónico; no
hay reloj, aleatoriedad, red, filesystem, sesión, entorno o estado global. Las
13 superficies legacy inventariadas permanecen contenidas y sin cambios.

Impacto productivo:
runtime 0, rutas 0, SQL 0, pacientes reales 0, links reales 0, merges 0,
contactos reales 0, documentos clínicos 0 y AWS 0.

Safe return, pruebas, evidencia y Git:
retorno seguro `3877e26078aec32e2a9e4b0c58d7872b8033a27b`; rollback futuro por
`git revert --no-edit <gate8f_commit>` en worktree detached. Gate 8F, regresiones
acumulativas, lint, pureza, negativos y PP-310 futura deben pasar. La evidencia
se entrega separada. El commit es aditivo y reversible; no se integra ni crea
checkpoint.

Estado final:
Gate 8F `IMPLEMENTED_READY_FOR_POSTVALIDATION`; Gate 8G `NOT_STARTED`; Actividad
8 `IN_PROGRESS`; Actividad 9 `BLOCKED`; contador 7/22; readiness
`NO_GO_LEGACY_BLOCKERS_PRESENT`. No iniciar Gate 8G.

### PP-310 — Gate 8G: persistencia aditiva de identidad y rollout deshabilitado

Objetivo: incorporar, en una implementación futura y separada de esta revisión, contratos declarativos de persistencia para la resolución de identidad de pacientes definida y postvalidada en Gate 8F. El alcance es de backend/base de datos; no agrega UI y conserva intactos los módulos Clinical y la persistencia existente.

Baseline obligatorio: partir del HEAD postvalidado de Gate 8F y conservar la cadena verificada de Gate 8E y Gate 8F. Antes de implementar se debe volver a comprobar limpieza, upstream 0/0, ausencia de REVERT_HEAD y disponibilidad del bundle de retorno seguro.

Alcance de base de datos: agregar cuatro tablas nuevas, sin ALTER de tablas existentes y sin modificar ready_schema.sql: patient_identity_resolutions, patient_identity_audit_events, patient_identity_legacy_links y patient_identity_backfill_checkpoints. Las migraciones serán ocho archivos declarativos (cuatro create/rollback), InnoDB, utf8mb4, utf8mb4_unicode_ci, sin seed y sin ejecución automática. No habrá claves foráneas hacia patients_patients; la integridad se verificará mediante índices, restricciones y el adaptador futuro.

Auditoría: usar una tabla específica de identidad, append-only, con secuencia por stream, event_id único y cadena previous_hash/event_hash. La tabla platform_audit_events aporta el patrón, pero no se reutiliza porque su envelope protegido no admite el conjunto cerrado de metadatos y resultados de Gate 8F sin cambiar contratos existentes. Se prohíben UPDATE y DELETE mediante triggers y sólo se almacenan referencias opacas o digests, nunca contacto, atributos de identidad o payload clínico en claro.

Idempotencia y concurrencia: request_fingerprint será la clave durable; operation_reference será único; la referencia legacy tendrá un lock digest generado, nullable y único. El adaptador futuro deberá bloquear en el orden resolution_fingerprint, legacy_reference, candidate_set, audit_stream, volver a comparar candidate_set_digest, reproducir resultados completados y escribir resolución y auditoría en la misma transacción. Los estados de transacción serán processing, completed y failed. Cualquier fallo abortará y hará rollback; no se habilita merge automático.

Contratos PHP: agregar exactamente PatientIdentityPersistencePolicy, PatientIdentityPersistenceManifest, PatientIdentityRetentionPolicy, PatientIdentityBackfillPlan, PatientIdentityRolloutPolicy y PatientIdentityPersistencePort en modules/patients/identity/persistence/. Son contratos declarativos; este gate no agrega PDO, SQL embebido, adaptador concreto, wiring de runtime ni activación funcional.

Retención: los eventos de auditoría y vínculos legacy son durables sin purge. La duración de resoluciones/idempotencia y checkpoints queda como UNRESOLVED_PENDING_POLICY_APPROVAL. No se inventará TTL numérico y purge, archive y delete automáticos permanecerán deshabilitados hasta una aprobación posterior.

Backfill: declarar un plan determinista, reanudable, idempotente y limitado por lotes con las etapas preflight, external_snapshot_backup, shadow_scan, batched_read, trusted_adapter_digest, candidate_resolution, no_match_partition, review_queue_partition, idempotency_check, append_audit, persist_checkpoint, reconciliation, emit_metrics y abort_or_rollback. Gate 8G no ejecuta backfill, no borra datos, no hace merge, no modifica Clinical y no conserva PII en checkpoints.

Rollout: declarar R0=disabled, R1=shadow, R2=audit_only, R3=read_compare y R4=enabled. Gate 8G sólo deja R0 disabled como estado inicial; no activa R1-R4. Cada transición requiere aprobación, métricas y postvalidación separadas.

Pruebas y evidencia: agregar Gate8GPatientIdentityPersistenceMigrationTest.php para validar estáticamente convenciones SQL, pares up/down, privacidad, append-only, idempotencia, locks, retención no inventada, backfill declarativo, rollout deshabilitado y ausencia de dependencias de base de datos en el test. Documentar implementación, rollback y retorno seguro en docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_IDENTIDAD.md. No declarar PASS si falta un archivo, si se ejecutó SQL, si existe wiring de runtime, si se activa rollout o si aparece una ruta de merge.

Control Git y retorno seguro: la implementación futura tendrá 17 archivos en alcance (16 nuevos y 1 modificado): 8 SQL, 7 PHP —incluido un test— y 2 documentos —incluido este Plan Maestro—. Un commit atómico requerirá árbol limpio, upstream 0/0, pruebas en verde, inventario exacto y evidencia temporal válida. El retorno seguro consiste en revertir ese único commit; los rollback SQL se declaran en orden 04, 03, 02, 01 y sólo podrían ejecutarse mediante una operación de base de datos autorizada fuera de este gate.

Estado esperado al cerrar la revisión de alcance: Gate 8F permanece POSTVALIDATED; Gate 8G queda SCOPE_REVIEW_COMPLETE_READY_FOR_IMPLEMENTATION; Actividad 8 continúa IN_PROGRESS; Actividad 9 continúa BLOCKED; el contador permanece 7/22, con 15 pendientes y readiness NO_GO_LEGACY_BLOCKERS_PRESENT. La revisión termina con cero cambios versionados, cero commits, cero SQL ejecutado, cero conexiones de base de datos, cero datos tocados y cero runtime activado.
