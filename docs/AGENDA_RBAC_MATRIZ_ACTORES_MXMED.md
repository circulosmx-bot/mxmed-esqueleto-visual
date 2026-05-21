# AGENDA RBAC · MATRIZ DE ACTORES · MXMED

Fecha: 2026-05-21  
Estado: F2.1 (documental, sin enforcement aún)

## 1. Propósito y alcance
Este documento fija la matriz RBAC de Agenda para preparar F2 sin romper los flujos estabilizados de Semana/Día, bloqueos, citas y Operadores F1.

Alcance de F2.1:
- Definir actores canónicos y permisos por función.
- Definir criterios de aceptación por endpoint.
- Separar claramente `frontend gating` vs `backend enforcement`.
- Dejar decisiones pendientes explícitas antes de implementar.

Fuera de alcance en F2.1:
- Cambios de código frontend/backend.
- Activación de enforcement real.
- Refactor funcional de Agenda.

## 2. Actores canónicos

| Clave | Actor | Descripción operativa |
|---|---|---|
| `doctor` | Usuario principal / médico propietario | Administra toda Agenda, Configuración y Operadores. |
| `operator` | Operador activo interno | Ejecuta operación diaria de Agenda; no administra Configuración ni Operadores. |
| `patient` | Paciente | Solo flujo público/contextual de reserva y consulta de disponibilidad. |
| `call_center` | Call Center | Actor externo asistido; alcance parcial pendiente en acciones sensibles. |
| `ai_operator` | Operador IA | Actor automatizado; crea/reserva y consulta disponibilidad según canal autorizado. |
| `system` | Sistema | Actor técnico para tareas automáticas auditables (mantenimiento/servicios). |

## 3. Matriz actor x función (funcional)
Convención de valores:
- `permitido`
- `no permitido`
- `pendiente de decisión`
- `solo flujo público`
- `solo backend/API`

| Función | doctor | operator | patient | call_center | ai_operator |
|---|---|---|---|---|---|
| Ver Agenda interna Día/Semana | permitido | permitido | no permitido | pendiente de decisión | pendiente de decisión |
| Ver disponibilidad pública | permitido | permitido | solo flujo público | solo flujo público | solo backend/API |
| Crear cita interna | permitido | permitido | no permitido | pendiente de decisión | pendiente de decisión |
| Reservar cita pública | no permitido | no permitido | solo flujo público | solo flujo público | solo backend/API |
| Buscar siguiente cita disponible | permitido | permitido | solo flujo público | permitido (limitado) | permitido (limitado/API) |
| Reprogramar | permitido | permitido | no permitido | pendiente de decisión | pendiente de decisión |
| Cancelar | permitido | permitido | solo flujo público (propia cita) | pendiente de decisión | pendiente de decisión |
| No show | permitido | permitido | no permitido | no permitido | no permitido |
| Bloquear horario | permitido | permitido | no permitido | no permitido | no permitido |
| Desbloquear horario | permitido | permitido | no permitido | no permitido | no permitido |
| Waitlist / asignación | permitido | permitido | no permitido | pendiente de decisión | pendiente de decisión |
| Ver detalle de cita interna | permitido | permitido | no permitido | pendiente de decisión | pendiente de decisión |
| Ver eventos/auditoría interna | permitido | permitido (limitado) | no permitido | no permitido | no permitido |
| Configuración Agenda | permitido | no permitido | no permitido | no permitido | no permitido |
| Operadores | permitido | no permitido | no permitido | no permitido | no permitido |

## 4. Separación por capas

### 4.1 Frontend gating (UX)
Objetivo:
- Ocultar/inhabilitar acciones no permitidas por actor.
- Mostrar mensajes claros: "No cuentas con permiso para esta acción."
- Evitar rutas visuales ambiguas para operadores (ej. Configuración/Operadores).

Regla:
- El gating frontend **no sustituye** el enforcement backend.

### 4.2 Backend enforcement (autoritativo)
Objetivo:
- Validar permisos por actor/endpoint/acción en servidor.
- Rechazar spoofing de payload (`actor_role`, `created_by_role`, `channel_origin`) si no coincide con sesión/token/canal.
- Mantener validación de `doctor_scope` como requisito base.

### 4.3 Auditoría obligatoria
Cada mutación sensible debe registrar:
- `actor_role`
- `actor_id`
- `channel_origin`
- `doctor_id` efectivo
- entidad afectada (`appointment_id`, `waitlist_id`, etc.)
- timestamp

### 4.4 Rutas públicas vs privadas
- Rutas `public/*`: solo contexto paciente/canal externo autorizado.
- Rutas privadas (`appointments`, `waitlist`, `schedule`, `settings`, `operators`, etc.): requieren contexto autenticado y política RBAC por actor.

## 5. Endpoints a proteger (criterios de aceptación)

### 5.1 Endpoints privados de Agenda

| Endpoint | doctor | operator | patient | call_center | ai_operator | Doctor scope | Audit required |
|---|---|---|---|---|---|---|---|
| `GET /appointments` | allowed | allowed | denied | pending | pending | required | opcional (read) |
| `GET /appointments/{id}` | allowed | allowed | denied | pending | pending | required | opcional (read) |
| `GET /appointments/{id}/events` | allowed | allowed (limitado) | denied | denied | denied | required | opcional (read) |
| `POST /appointments` | allowed | allowed | denied | pending | pending | required | required |
| `PATCH /appointments/{id}/reschedule` | allowed | allowed | denied | pending | pending | required | required |
| `POST /appointments/{id}/cancel` | allowed | allowed | denied | pending | pending | required | required |
| `POST /appointments/{id}/no_show` | allowed | allowed | denied | denied | denied | required | required |
| `GET /availability` | allowed | allowed | denied | pending | pending | required | opcional (read) |
| `GET /waitlist` | allowed | allowed | denied | pending | pending | required | opcional (read) |
| `POST /waitlist` | allowed | allowed | denied | pending | pending | required | required |
| `PATCH /waitlist/{id}` | allowed | allowed | denied | pending | pending | required | required |
| `POST /waitlist/{id}/assign` | allowed | allowed | denied | pending | pending | required | required |
| `GET /schedule` | allowed | denied | denied | denied | denied | required | opcional (read) |
| `PUT /schedule` | allowed | denied | denied | denied | denied | required | required |
| `GET /settings` | allowed | denied | denied | denied | denied | required | opcional (read) |
| `PUT /settings` | allowed | denied | denied | denied | denied | required | required |
| `GET /operators` | allowed | denied | denied | denied | denied | required | opcional (read) |
| `POST /operators` | allowed | denied | denied | denied | denied | required | required |
| `PATCH /operators/{id}/pause` | allowed | denied | denied | denied | denied | required | required |
| `PATCH /operators/{id}/reactivate` | allowed | denied | denied | denied | denied | required | required |
| `PATCH /operators/{id}/archive` | allowed | denied | denied | denied | denied | required | required |
| `PATCH /operators/{id}/restore` | allowed | denied | denied | denied | denied | required | required |

### 5.2 Endpoints públicos

| Endpoint | doctor | operator | patient | call_center | ai_operator | Doctor scope | Audit required |
|---|---|---|---|---|---|---|---|
| `GET /public/availability` | allowed (uso técnico) | allowed (uso técnico) | allowed | allowed | allowed (API) | n/a (public) | opcional (read) |
| `POST /public/appointments/reserve` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/appointments/confirm` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/appointments/cancel` | denied | denied | allowed (propia reserva) | allowed (si flujo autorizado) | pending | n/a (public) | required |
| `POST /public/appointments/request` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/appointments/verify` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/otp/request` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/otp/verify` | denied | denied | allowed | allowed | allowed (API) | n/a (public) | required |
| `POST /public/maintenance/expire` | denied | denied | denied | denied | allowed (system/API) | n/a (public) | required |

## 6. Decisiones pendientes (bloqueantes para F2.3/F2.4)
1. Call Center: confirmar si puede `cancel` y/o `reschedule` en privado.
2. Operador IA: confirmar si podrá `cancel` y/o `reschedule` en fase futura.
3. Fuente autoritativa de actor:
   - sesión web,
   - JWT,
   - API key de canal,
   - mapeo híbrido.
4. Nivel de auditoría visible para `operator` (timeline completo vs vista resumida).
5. Política final de `GET /appointments/{id}/events` para operadores internos.

## 7. Plan de implementación F2

### F2.2 Frontend gating (doctor vs operator)
- Ocultar/inhabilitar botones/tabs no permitidos a `operator`.
- Mantener mensajes de permiso denegado.
- Sin alterar lógica de negocio existente.

### F2.3 Backend enforcement mínimo
- Introducir policy helper RBAC por endpoint/acción.
- Validar actor contra contexto servidor (no solo payload).
- Mantener `doctor_scope` obligatorio en rutas privadas.

### F2.4 Actores externos (patient/call_center/ai_operator)
- Delimitar capacidades por canal.
- Mantener separación fuerte entre rutas públicas y privadas.
- Formalizar decisiones pendientes de cancel/reprogram.

### F2.5 Auditoría unificada
- Homologar `actor_role`, `actor_id`, `channel_origin`, `created_by_id`.
- Garantizar traza por acción y entidad.

### F2.6 QA integral
- Matriz positiva y negativa por actor.
- Pruebas de spoofing de payload.
- Pruebas de doctor scope.
- Validar no regresión de Semana/Día, bloqueos y post-cancel.

## 8. QA sugerido para RBAC

### 8.1 Pruebas positivas
- `doctor` ejecuta todas las funciones internas.
- `operator` ejecuta operación Agenda incluyendo `no_show`.
- `patient` ejecuta solo flujo público.

### 8.2 Pruebas negativas
- `operator` no entra a `settings/operators`.
- `patient` no accede a endpoints privados.
- `call_center` y `ai_operator` no ejecutan acciones pendientes/no autorizadas.

### 8.3 Seguridad
- Intentos con payload manipulado (`actor_role`, `created_by_role`, `channel_origin`) deben fallar.
- `doctor_scope` mismatch debe responder `403`.
- `GET /appointments/{id}/events` no debe filtrar datos fuera de scope.

## 9. Estado documental
- Este documento define la política objetivo para F2.
- No implica que el enforcement ya esté activo.
- Cualquier cambio funcional debe ejecutarse en tickets F2.2+ con QA dedicado.
