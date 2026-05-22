# AGENDA · CONTRATO DE AUDITORIA Y ACTOR ATTRIBUTION · MXMED

Fecha: 2026-05-21  
Estado: F2.5B-F2.5E2 cerrado (payload frontend + persistencia backend + DTO uniforme events + waitlist create/update + waitlist assign audit explicita)

## 1) Estado actual

Situacion observada en implementacion vigente:
- Crear cita usa principalmente:
  - `created_by_role`
  - `created_by_id`
  - `channel_origin`
- Mutaciones operativas usan principalmente:
  - `actor_role`
  - `actor_id`
  - `channel_origin`
- `GET /appointments/{id}/events` expone eventos mayormente raw (sin DTO uniforme transversal).
- Coexisten dos modelos de attribution (`created_by_*` y `actor_*`).

## 2) Problema a resolver

- Inconsistencia entre create y mutaciones.
- Enums de rol todavia no totalmente homologados en todas las rutas/controladores.
- Waitlist y bloqueos no tienen contrato de auditoria completamente uniforme.
- Riesgo de spoofing mientras no exista identidad autoritativa fuerte (sesion/JWT/API key).

## 3) Contrato canonico propuesto

Toda accion operativa de Agenda debe mapear a este shape canonico:

- `entity_type`
- `entity_id`
- `action`
- `actor_role`
- `actor_id`
- `actor_display_name`
- `channel_origin`
- `created_by_role`
- `created_by_id`
- `occurred_at`
- `metadata`

Notas:
- `created_by_*` se conserva por compatibilidad backward.
- `actor_*` sera la referencia operativa principal para trazabilidad.

## 4) Roles canonicos

- `doctor`
- `operator`
- `patient`
- `call_center`
- `ai_operator`
- `system`

## 5) Channel origin canonico (propuesto)

- `agenda_internal`
- `public_profile`
- `call_center`
- `ai_assistant`
- `system`
- `migration`

Regla:
- No romper valores legacy existentes; mapear gradualmente a este catalogo.

## 6) Mapeo backward-compatible

| Caso | Entrada actual | Canonico destino | Politica |
|---|---|---|---|
| Create cita | `created_by_role` | `actor_role` | Mapear en persistencia/evento sin eliminar `created_by_role` |
| Create cita | `created_by_id` | `actor_id` | Mapear en persistencia/evento sin eliminar `created_by_id` |
| Mutaciones | `actor_role` | `actor_role` | Mantener |
| Mutaciones | `actor_id` | `actor_id` | Mantener |
| Todos | `channel_origin` | `channel_origin` | Mantener y normalizar catalogo |

## 7) Matriz accion x auditoria (objetivo)

| Evento/accion | entity_type | actor_role/id | channel_origin | occurred_at | metadata |
|---|---|---|---|---|---|
| `appointment_created` | `appointment` | requerido | requerido | requerido | opcional |
| `appointment_rescheduled` | `appointment` | requerido | requerido | requerido | from/to consultorio, reason |
| `appointment_canceled` | `appointment` | requerido | requerido | requerido | reason, contact |
| `appointment_no_show` | `appointment` | requerido | requerido | requerido | motivo/flags |
| `waitlist_created` | `waitlist` | requerido | requerido | requerido | prioridad/observaciones |
| `waitlist_assigned` | `waitlist` + `appointment` | requerido | requerido | requerido | referencia cruzada |
| `availability_blocked` | `availability_block` | requerido | requerido | requerido | rango, consultorio, motivo |
| `availability_unblocked` | `availability_block` | requerido | requerido | requerido | rango, consultorio |
| `operator_created` | `operator` | requerido | requerido | requerido | rol/permisos iniciales |
| `operator_paused` | `operator` | requerido | requerido | requerido | reason |
| `operator_reactivated` | `operator` | requerido | requerido | requerido | reason |
| `operator_archived` | `operator` | requerido | requerido | requerido | reason |
| `operator_restored` | `operator` | requerido | requerido | requerido | reason |
| `operator_migrated_from_local` | `operator` | `system` o actor invocador | `migration` | requerido | source local payload |

## 8) Estado de implementacion por fase (actualizado)

- F2.5A: contrato documental base.
- F2.5B: cerrado.
  - Frontend normaliza payload de actor en:
    - create appointment
    - reschedule
    - cancel
    - no_show
    - waitlist assign
    - alta medica equivalente
  - Compatibilidad preservada:
    - `created_by_role`
    - `created_by_id`
    - `channel_origin`
  - Canonico agregado en payload:
    - `actor_role`
    - `actor_id`
    - `actor_display_name`
    - `action`
    - `entity_type`
    - `entity_id`
    - `occurred_at`
    - `metadata`
- F2.5C: cerrado.
  - Backend normaliza actor attribution en writes de citas.
  - `appointment_rescheduled` persiste:
    - `actor_role`
    - `actor_id`
    - `channel_origin`
  - `appointment_created`, `appointment_canceled` y `appointment_no_show` mantienen persistencia operativa de actor.
  - Se conserva trazabilidad `from_consultorio_id` / `to_consultorio_id`.
- F2.5D: cerrado.
  - `GET /appointments/{id}/events` devuelve DTO uniforme aditivo por evento.
  - Conserva campos raw/legacy existentes.
  - Agrega:
    - `action`
    - `entity_type`
    - `entity_id`
    - `occurred_at`
    - `created_by_role`
    - `created_by_id`
    - `actor_display_name`
    - `metadata`
  - `notes` se conserva como string raw.
  - Si `notes` es JSON valido, tambien se expone como `metadata`.
  - Si `notes` no es JSON, se expone como `metadata.notes_text`.
- F2.5E1: cerrado.
  - `POST /waitlist` acepta y persiste actor attribution compatible.
  - `PATCH /waitlist/{id}` acepta y persiste actor attribution compatible.
  - Compatibilidad legacy preservada para payloads sin actor.
  - En instancias sin columnas actor en `agenda_waitlist_entries`, se usa fallback seguro en `notes` JSON.
  - Respuestas de waitlist hidratan campos canónicos (`actor_*`, `created_by_*`, `action`, `entity_*`, `occurred_at`, `metadata`).
- F2.5E2: cerrado.
  - `POST /waitlist/{id}/assign` mantiene flujo operativo: crea cita + genera `appointment_created` + genera `appointment_reassigned_from_waitlist`.
  - `appointment_created` desde assign conserva actor attribution.
  - `appointment_reassigned_from_waitlist` conserva `actor_role`, `actor_id`, `channel_origin`.
  - `appointment_reassigned_from_waitlist` usa `notes` JSON estructurado.
  - `metadata` resultante incluye:
    - `source=waitlist_assign`
    - `waitlist_entry_id`
    - `consultorio_id`
    - `assigned_slot.start_at`
    - `assigned_slot.end_at`
    - `assigned_slot.slot_minutes`
    - `actor_display_name` si existe
    - `linked_cancelled_appointment_id` si aplica
    - `override` / `override_reason` si aplica
  - el cambio de estado waitlist a `confirmed` recibe audit payload compatible (`waitlist_assigned`).
- F2.5E3-E5: pendiente.
- F2.5F: pendiente.

## 9) QA ejecutado y pendiente

Validado (PASS):
- UI real `create/reschedule/cancel` con payload normalizado.
- API runtime para `no_show` y `waitlist assign` con actor attribution.
- Flujo de alta medica equivalente con canal/origen preservado.
- `GET /appointments/{id}/events` con DTO uniforme aditivo.
- Smoke Semana/Dia PASS.
- Ajuste de corte horario semanal validado y separado en commit `8af3e7b`.

Pendiente para F2.5E/F2.5F:
- doctor crea/reprograma/cancela/no_show.
- operator crea/reprograma/cancela/no_show.
- patient reserva publico.
- call_center crea/reserva.
- ai_operator crea/reserva.
- `GET /appointments/{id}/events` muestra actor consistente.
- pruebas negativas de spoofing documentadas como limitacion actual hasta identidad fuerte.
- auditoría canónica de bloqueos/desbloqueos (`availability_blocked` / `availability_unblocked`) pendiente por persistencia backend de bloqueos.

## 10) Commits relevantes

- `7d00d52` frontend actor payload (F2.5B)
- `62e170a` persistencia actor en reprogramacion (F2.5C minimo)
- `3df2255` DTO uniforme aditivo en `GET /appointments/{id}/events` (F2.5D)
- `8af3e7b` fix corte horario semanal (separado; relacionado por interrupcion de QA, no parte del contrato de auditoria)
- `be3f86c` actor attribution compatible en `POST/PATCH /waitlist` (F2.5E1)
- `1e455cb` estandarización explícita de auditoría en `waitlist assign` (F2.5E2)

## 11) Riesgos y guardrails

- No romper compatibilidad de payloads actuales mientras se migra contrato.
- No rechazar actores externos antes de homologar enums por endpoint.
- No exponer datos sensibles de attribution en UI no autorizada.
- No asumir identidad fuerte mientras actor provenga de headers/query/body.

## 12) Decisiones pendientes

1. Fuente autoritativa final de actor (sesion/JWT/API key).
2. Politica final de visibilidad de auditoria para `operator`.
3. Cierre de alcance para `call_center` y `ai_operator` en cancel/reprogram.
4. Contrato definitivo de auditoria para bloqueos si parte del flujo sigue en capa local.

## 13) Pendiente inmediato (F2.5E)

- Auditoria waitlist residual (si se amplía visibilidad por rol o nuevos eventos dedicados).
- Auditoria de bloqueos/desbloqueos.
- Estrategia de persistencia backend de bloqueos (si aplica).
- Actor attribution para eventos de disponibilidad/bloqueo.
- Reglas de visibilidad de auditoria por rol.

## 14) Cierre F2.6 (QA integral RBAC + auditoría)

Estado: **PASS**.

Alcance validado en F2.6:
- RBAC frontend doctor/operator.
- RBAC backend para `operator` (rutas restringidas vs operativas).
- Actor attribution de citas (`appointment_created`, `appointment_rescheduled`, `appointment_canceled`, `appointment_no_show`).
- Actor attribution de waitlist (`waitlist_created`, `waitlist_updated`, `waitlist_assigned`) y eventos de cita derivados de assign.
- Compatibilidad legacy sin actor explícito.
- Smoke mínimo de Agenda sin regresión visible.

IDs QA usados:
- `doctor_id`: `1`
- `appointment_id`: `93730ced68f31f7d5e545ff9`, `2c4a2b508e970bb30c37f8c3`, `7e525e13e8117b07677e760f`
- `waitlist_id`: `05b249d5f734d24b378b4a74`, `720d267fe240ef76f4627ba7`

Observaciones operativas:
- Datos QA permanecen en tablas de citas/waitlist (sin limpieza destructiva en este cierre).
- Se observa ocasionalmente `409 Conflict` conocido del entorno QA, sin bloquear las rutas auditadas.
- `Bloqueo parcial` y `domingo sin horario` no se re-ejecutaron exhaustivamente en F2.6; ambos conservan PASS de corridas dedicadas previas.

Pendiente posterior:
- Fuente autoritativa de actor (identidad fuerte en servidor).
- Auditoría backend de bloqueos/desbloqueos (`availability_blocked` / `availability_unblocked`).
- Política final de visibilidad de auditoría por rol.
