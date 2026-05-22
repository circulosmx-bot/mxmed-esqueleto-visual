# AGENDA RBAC · MATRIZ DE ACTORES · MXMED

Fecha: 2026-05-21  
Estado: F2.3 parcial cerrado (F2.1 + F2.2 + F2.3A + F2.3B)

## 0. Estado F2.3 (cierre parcial)
Implementación ya cerrada:
- F2.1 Matriz RBAC documental (`9bcfbe0`).
- F2.2 Frontend gating médico vs operador activo (`1330e31`).
- F2.3A Backend enforcement: `operator` bloqueado en `/operators/*` (`ca1f220`).
- F2.3B Backend enforcement: `operator` bloqueado en rutas de Configuración de Agenda (`0ccc0fc`).

Resultado funcional actual:
- `doctor` conserva acceso total.
- `operator` conserva operación de Agenda.
- `operator` no puede gestionar Operadores ni Configuración de Agenda en backend.

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

## 6. Decisiones pendientes (F2.4+)
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

## 9. Enforcement activo en F2.3 (operator)

### 9.1 Endpoints bloqueados para `operator`
- `/operators/*`
- `GET /settings`
- `PUT /settings`
- `GET /schedule`
- `PUT /schedule`
- `PUT /consultorios`
- `POST /geocode/google`
- `GET /geocode/google-js-config`

### 9.2 Endpoints permitidos para `operator`
- `GET /appointments`
- `GET /appointments/{id}`
- `GET /appointments/{id}/events`
- `POST /appointments`
- `PATCH /appointments/{id}/reschedule`
- `POST /appointments/{id}/cancel`
- `POST /appointments/{id}/no_show`
- `GET /availability`
- `GET /consultorios`
- `GET /waitlist`
- `POST /waitlist`
- `PATCH /waitlist/{id}`
- `POST /waitlist/{id}/assign`
- Rutas `public/*` sin cambio

## 10. QA F2.3 documentado
- `operator` recibe `403` en rutas restringidas de Operadores y Configuración.
- `doctor` no recibe `forbidden` en esas mismas rutas.
- Sin header de rol se mantiene compatibilidad actual (fallback temporal a `doctor`).
- `operator` sigue operando rutas de citas/disponibilidad/waitlist.
- `public/*` permanece sin afectación por F2.3.
- `php -l api/agenda/index.php` en PASS.

## 11. Riesgos pendientes y siguiente fase
Riesgos abiertos:
- Fuente de rol temporal/spoofeable por headers/query hasta identidad autoritativa.
- Falta integrar sesión/JWT/API key para resolución de actor confiable.
- Falta auditoría unificada por actor en todas las mutaciones operativas.
- Falta cerrar políticas para actores externos (`patient`, `call_center`, `ai_operator`).
- Falta F2.6 QA integral RBAC (positivas, negativas y spoofing).

## 12. Adenda F3.1 (fuente autoritativa de actor)

Referencia principal:
- Ver `AGENDA_ACTOR_AUTORITATIVO_MXMED.md`.

Definición incorporada:
- Se formaliza contrato de actor efectivo para Agenda privada:
  - `actor_role`
  - `actor_id`
  - `doctor_id`
  - `operator_id`
  - `channel_origin`
  - `auth_source`
  - `is_authoritative`
  - `auth_mode`

Modos documentados:
- `strict` (producción): identidad fuerte, sin overrides spoofeables.
- `compat` (local/dev): compatibilidad temporal con fallback.
- `qa_override` (QA controlado): overrides permitidos solo bajo flag explícito.
- `public_flow`: separado del RBAC privado.

Regla RBAC futura para `operator` en modo estricto:
- Debe existir en `agenda_operators`.
- Debe estar `active`.
- Debe coincidir en `doctor_id` con el scope efectivo.
- `paused`, `pending`, `archived` quedan denegados para rutas privadas.

Pendiente de implementación (F3.2+):
- Resolver actor autoritativo en backend y acoplar enforcement a identidad fuerte.

## 14. Adenda F3.2A (actor efectivo backend compat/QA)

Estado:
- Cerrado en backend con helper central (`api/agenda/index.php`, commit `82f6320`).

Alcance implementado:
- Se introduce `resolveEffectiveAgendaActor(...)` para construir contexto efectivo aditivo.
- Se mantienen claves legacy y se agregan metadatos de actor:
  - `actor_role`, `actor_id`, `doctor_id`, `operator_id`, `channel_origin`,
  - `auth_source`, `auth_mode`, `is_authoritative`,
  - `actor_role_source`, `warnings`, `mode/strict/compat/user_id`.

Compatibilidad:
- No hay endurecimiento nuevo en F3.2A.
- RBAC F2.3 mantiene las mismas reglas deny/allow.
- Rutas `public/*` continúan sin afectación funcional.

Pendiente para F3.3/F3.4:
- Validación de `operator` activo real contra `agenda_operators`.
- Bloqueo por estado (`paused`/`pending`/`archived`) en modo autoritativo.
- Restricción de overrides header/query/body a QA/dev controlado.

## 15. Adenda F3.3B (validación observacional operator)

Estado:
- Cerrado de forma observacional (sin enforcement nuevo) en commit `15d23a4`.

Implementación:
- Router Agenda incorpora `resolveAgendaOperatorIdentity(...)`.
- Repositorio de Operadores expone `findOperatorIdentity(...)`.

Comportamiento por modo:
- `compat` / `qa_override`:
  - no bloquea operación de agenda;
  - agrega warnings/contexto de identidad operator.
- RBAC F2.3 no cambia:
  - `operator` sigue bloqueado en `/operators/*` y configuración;
  - `operator` sigue permitido en rutas operativas.

Warnings documentados:
- `operator_id_missing`
- `operator_not_found`
- `operator_doctor_mismatch`
- `operator_not_active`
- `operator_identity_db_not_ready`
- `operator_identity_valid`

Pendiente siguiente:
- F3.3C para activar enforcement real en `strict`.

Siguiente fase sugerida:
- F2.4 actores externos de Agenda (`patient`, `call_center`, `ai_operator`).
- Alternativamente F2.5 para consolidar auditoría/actor attribution antes de ampliar actores.

## 12. Adenda F2.5A (contrato de auditoria)

Documento canonico:
- `docs/AGENDA_AUDITORIA_ACTOR_ATTRIBUTION_MXMED.md`

Acuerdo de F2.5A:
- Se documenta contrato canonico de actor attribution sin cambios funcionales.
- Se mantiene compatibilidad backward con `created_by_*` y `actor_*`.
- Se define set canonico de roles:
  - `doctor`, `operator`, `patient`, `call_center`, `ai_operator`, `system`.
- Se define set sugerido de `channel_origin`:
  - `agenda_internal`, `public_profile`, `call_center`, `ai_assistant`, `system`, `migration`.

Pendiente posterior:
- F2.5B-F2.5F para normalizacion de payload, persistencia, DTO de eventos y QA integral.

## 13. Cierre F2.6 (QA integral RBAC + auditoría)

Estado: **PASS** (cierre de QA integral ejecutado, sin cambios funcionales en esta fase).

Bloques validados:
- Preflight (repo limpio, rama esperada, consola sin errores JS nuevos atribuibles a RBAC).
- RBAC frontend doctor/operator (doctor con acceso completo; operator sin acceso a Configuración/Operadores, con bloqueo por navegación directa).
- RBAC backend para `operator` (403 en rutas restringidas y acceso permitido en rutas operativas).
- Actor attribution en citas (`create`, `reschedule`, `cancel`, `no_show`) con eventos consistentes.
- Actor attribution en waitlist (`POST`, `PATCH`, `assign`) con metadata estructurada en assign.
- Compatibilidad legacy sin actor explícito.
- Smoke mínimo de Agenda (Semana, Día, siguiente cita disponible).

IDs QA de referencia:
- `doctor_id`: `1`
- `appointment_id`: `93730ced68f31f7d5e545ff9`, `2c4a2b508e970bb30c37f8c3`, `7e525e13e8117b07677e760f`
- `waitlist_id`: `05b249d5f734d24b378b4a74`, `720d267fe240ef76f4627ba7`

Observaciones:
- Quedaron datos QA en tablas de citas/waitlist (sin limpieza destructiva en este cierre).
- Se mantiene aparición ocasional de `409 Conflict` en entorno QA, sin bloqueo de los flujos validados.
- En esta corrida, el smoke de Agenda fue mínimo; `bloqueo parcial` y `domingo sin horario` se mantienen en PASS por corridas dedicadas previas.

Pendiente posterior a F2.6:
- Fuente autoritativa de actor (sesión/JWT/API key).
- Actores externos (`patient`, `call_center`, `ai_operator`) con enforcement completo.
- Backend de bloqueos/desbloqueos y auditoría `availability_blocked` / `availability_unblocked`.
