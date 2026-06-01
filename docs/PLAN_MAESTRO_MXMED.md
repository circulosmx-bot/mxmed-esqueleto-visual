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

## E. Registro de decisiones (Decision Log)

Formato obligatorio por entrada:
- Fecha (`YYYY-MM-DD`)
- Decisión
- Motivo
- Impacto
- Referencias (docs/endpoint/commit/tag)
- Estado (`vigente`, `en revisión`, `reemplazada`)

| Fecha | Decisión | Motivo | Impacto | Referencias | Estado |
|---|---|---|---|---|---|
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
| 2026-02-25 | Naming canónico: no_show => black (deprecar red en docs) | Alinear código, QA y documentación con una sola semántica | Actualización documental pendiente para eliminar ambigüedad red/black | `modules/agenda/repositories/AppointmentWriteRepository.php:538-539`; `modules/agenda/README.md:106` | vigente |
| 2026-04-28 | Ubicación pública de consultorio usará coordenadas confirmadas (`lat/lng`) como fuente principal | Evitar divergencia entre mapa configurado en admin y mapa visible al público | Admin mantiene captura con Leaflet; perfil público futuro renderiza iframe de Google Maps por coordenadas (sin API Key); fallback por dirección solo visual | `modules/agenda/helpers/consultorio_map.php`; `modules/agenda/controllers/ConsultoriosController.php`; `modules/agenda/README.md` | vigente |
| 2026-05-24 | Waitlist no representa cita confirmada ni garantiza disponibilidad anticipada | Evitar falsas expectativas operativas en admisión/agendamiento | Se separa explícitamente flujo de espera vs cita confirmada | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | vigente |
| 2026-05-24 | Waitlist puede aplicar a cualquier consultorio mediante `consultorio_scope="all"` | Formalizar alcance cross-consultorio con semántica canónica | Permite priorización y asignación sin restringir a un solo consultorio desde el alta | `docs/MAPEO_AGENDA_MXMED.md` | vigente |
| 2026-05-24 | `__all__` queda como sentinel transicional y nunca puede ser destino de cita | Preservar compatibilidad de transición sin romper integridad de citas reales | El assign debe resolver siempre a consultorio real; destino sentinel queda bloqueado | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ACTOR_AUTORITATIVO_MXMED.md` | vigente |
| 2026-05-24 | Resolver hueco permite recuperar slots post-cancelación mediante candidatos waitlist | Reducir pérdida operativa por cancelaciones/reprogramaciones | Se habilita recuperación guiada con ranking/candidatos y asignación explícita | `docs/MAPEO_AGENDA_MXMED.md`; `docs/AGENDA_ESTADO_CONSOLIDACION_Y_DEUDA_UI_MXMED.md` | vigente |
| 2026-05-24 | Agenda puede abrir contexto de paciente/expediente, pero no inicia consulta automáticamente | Mantener frontera entre operación administrativa y acto clínico | Evita arranque automático de consulta y preserva control clínico explícito | `docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md`; `docs/PLAN_MAESTRO_MXMED.md` | vigente |
| _pendiente_ | _agregar nuevas decisiones aquí_ |  |  |  |  |

## F. Mapa de interconexiones (flows principales)

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

## G. Backlog por etapas (1a/2a/3a/4a)

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
- `[shell]` UX-Shell-01B completado: acceso inferior de Mi Perfil agregado al sidebar con estructura tipo perfil/header; menu legacy de perfil en sidebar se mantiene oculto y dropdown superior permanece como respaldo transicional (`97281e2`).
- `[shell]` UX-Shell-01C completado: decision de navegacion documentada para mantener acceso inferior en sidebar como direccion preferida, con deuda futura de simplificacion del header y cierre responsive final.
- `[shell]` UX-Shell-01D5 completado: documentado comportamiento del control de sidebar; en desktop/tablet grande el control de colapso pertenece visualmente al sidebar y en movil no se muestra hamburguesa hasta existir overlay real.
- `[shell]` UX-Shell-01D10 completado: documentada la consolidacion de Mi Perfil en el dropdown inferior del sidebar; menu principal reservado para modulos generales y dropdown inferior funcional en modo expandido/compacto.
- `[shell]` UX-Shell-01E5-D completado: documentado el layout dashboard del panel principal de perfil, con completitud como bloque principal, actividad reciente como card lateral e indicadores clave como bloque inferior; pendiente definir metricas finales y decision de panel inicial.
- `[profiles]` Siguiente recomendado: UX-Panel-01D3-C (UI placeholder de categorias seguridad/privado/publico/operativo/consultorio sin backend) o UX-Panel-01D3-D (diseno backend canonico de contacto), segun prioridad.

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

## H. Índice de fuentes externas

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
| `docs/db/INTEGRACION_AGENDA_POST_PATIENTS.md` | doc interno | Integración Agenda -> alta de paciente cuando no hay patient_id | pendiente | Por definir | Revisar y validar flujo/errores propagados |
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

## I. Cómo trabajar este plan

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
