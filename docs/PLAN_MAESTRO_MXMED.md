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
| Agenda | Hecho (v1) | Citas, eventos, waitlist | `docs/MAPA_TOTAL_SISTEMA_MXMED.md`; `api/agenda/index.php`; `docs/agenda/CIERRE_AGENDA_V1_ESTADO_FINAL.md` | Consolidar bridge robusto a Clinical en completed |
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
