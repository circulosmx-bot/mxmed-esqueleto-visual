# AGENDA · CONTRATO DE AUDITORIA Y ACTOR ATTRIBUTION · MXMED

Fecha: 2026-05-21  
Estado: F2.5A documental (sin cambios funcionales)

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

## 8) Fases de implementacion (posteriores a F2.5A)

- F2.5B: normalizacion de payload frontend (sin romper compatibilidad).
- F2.5C: persistencia backend unificada en eventos appointment/waitlist/bloqueos.
- F2.5D: DTO uniforme en `GET /appointments/{id}/events`.
- F2.5E: auditoria homologada para waitlist y bloqueos.
- F2.5F: QA integral por actor/canal.

## 9) QA propuesto

Casos minimos:
- doctor crea/reprograma/cancela/no_show.
- operator crea/reprograma/cancela/no_show.
- patient reserva publico.
- call_center crea/reserva.
- ai_operator crea/reserva.
- `GET /appointments/{id}/events` muestra actor consistente.
- pruebas negativas de spoofing documentadas como limitacion actual hasta identidad fuerte.

## 10) Riesgos y guardrails

- No romper compatibilidad de payloads actuales mientras se migra contrato.
- No rechazar actores externos antes de homologar enums por endpoint.
- No exponer datos sensibles de attribution en UI no autorizada.
- No asumir identidad fuerte mientras actor provenga de headers/query/body.

## 11) Decisiones pendientes

1. Fuente autoritativa final de actor (sesion/JWT/API key).
2. Politica final de visibilidad de auditoria para `operator`.
3. Cierre de alcance para `call_center` y `ai_operator` en cancel/reprogram.
4. Contrato definitivo de auditoria para bloqueos si parte del flujo sigue en capa local.
