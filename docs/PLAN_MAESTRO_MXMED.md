# PLAN MAESTRO MXMED (Documento Vivo)

Fuente principal (base de estado actual): `docs/MAPA_TOTAL_SISTEMA_MXMED.md`.
Este plan maestro NO duplica ese mapa completo; lo usa como referencia operativa y concentra gobernanza transversal: checklist + decisiones + roadmap + fuentes.

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
| Clinical API (timeline/encounters/documents) | En progreso | Timeline, encounters, documentos y casos en evolución | `docs/clinical/TIMELINE_V1_CONTRACT.md`; `docs/clinical/encounters.md`; tags `mxmed-camino2-step*` | Endurecer contratos cross-módulo y deuda legacy |
| Cases | En progreso | Caso activo y items por caso | `docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md`; endpoints `/cases/*` | Mejorar trazabilidad item_type y overlays de pertenencia |
| Clinical UI (historial/encounter embed) | En progreso | Embed estable + overlay documento + viewer host | tags `mxmed-camino2-step10`..`mxmed-camino2-step31`; `modules/clinical/ui/*.php` | Homologación visual completa y filtros/contexto de caso |
| QA scripts (smokes) | En progreso | Contrato embed + smoke encounters/docs | `modules/clinical/qa/embed_contract_check.sh`; `docs/qa/clinical_encounters_smoke.sh` | Unificar rutas/ownership y ampliar checks de contrato |
| Legacy wrappers (`clinical-documents`, `evolution-note`) | Deuda controlada | Compatibilidad histórica activa | `api/clinical-documents.php`; `api/evolution-note-generate.php`; `docs/clinical/DECISION_COMPAT_CLINICAL_DOCUMENTS_WRAPPER.md` | Plan de salida gradual sin ruptura |
| Migraciones pendientes (schema v1/v2, encounter_id typing, document_id vs uuid, cursor) | Pendiente | Pendientes de consolidación estructural | `modules/clinical/db/schema_v1.sql`; `modules/clinical/db/schema_v2.sql`; `docs/clinical/TIMELINE_V1_CONTRACT.md` | Definir plan formal de migración por fases y rollback |

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
- `[qa]` ampliar smoke de contratos timeline + cases + overlay.
- `[migration]` plan de migración schema v1/v2 y tipos de IDs.

### 2a) Operación clínica extendida
- `[api]` órdenes, recetas y resultados con contratos estables.
- `[ui]` experiencia de episodio completa (acciones y contexto longitudinal).
- `[ops]` observabilidad básica de bridges y retries.

### 3a) Integraciones institucionales
- `[contract]` hospitales/labs/pharma/insurers con IDs interoperables.
- `[api]` conectores externos y trazabilidad.

### 4a) Canales y monetización
- `[api]` notifications/reviews/billing/invoice.
- `[ui]` paneles operativos por perfil.
- `[ops]` controles de cumplimiento/auditoría.

## H. Índice de fuentes (incluye PDFs externos)

| Fuente | Tipo | Fecha | Estado | Decisiones derivadas | Próxima acción |
|---|---|---|---|---|---|
| `docs/MAPA_TOTAL_SISTEMA_MXMED.md` | Doc interno (fuente principal) | 2026-02-25 | revisado | Base de este plan maestro | Mantener sincronizado por iteración |
| `docs/clinical/DECISION_FUENTES_DE_VERDAD.md` | Decisión interna | 2026-02-25 | revisado | Fuentes canónicas (Patients/clinical_documents) | Revisar impacto en nuevas migraciones |
| `docs/clinical/DECISION_APPOINTMENT_ID_CANONICO_AGENDA_V1.md` | Decisión interna | 2026-02-25 | revisado | Appointment canónico en Agenda | Verificar cumplimiento en todos los endpoints |
| `docs/clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md` | Contrato interno | 2026-02-25 | revisado | Regla de correlación appointment↔encounter | Endurecer QA contractual |
| `docs/clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md` | Decisión interna | 2026-02-25 | revisado | Trigger Agenda->Clinical en completed | Activar con guard controlado |
| `docs/db/INTEGRACION_PACIENTES_AGENDA.md` | Integración interna | 2026-02-25 | revisado | Frontera Pacientes↔Agenda | Mantener alineado con contratos API |
| `docs/db/INTEGRACION_AGENDA_POST_PATIENTS.md` | Integración interna | 2026-02-25 | revisado | Auto-create patient desde Agenda | Revisar propagación de errores |
| `docs/ui/REGLAS_UI_MXMED.md` | Regla operativa UI | 2026-02-25 | revisado | Metodología de cambios UI/UX | Cumplimiento obligatorio en PRs |
| `docs/dev/LOCAL_DEV_SERVERS.md` | Operación dev | 2026-02-25 | revisado | Doble puerto 8091/8092 y preflight | Mantener checklist actualizado |
| `00Introduccion para Desarrolladores.pdf` | PDF externo | 2026-02-25 | pendiente | Por definir | Revisar y registrar decisiones en sección E |
| `00Funcionalidades por Tipo de Perfil.pdf` | PDF externo | 2026-02-25 | pendiente | Por definir | Revisar y convertir hallazgos a backlog/contratos |

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
