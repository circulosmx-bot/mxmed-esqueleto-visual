# AGENDA · FUENTE AUTORITATIVA DE ACTOR · MXMED

Fecha: 2026-05-21  
Estado: F3.2A cerrado (documentación + helper backend compat/QA)

## 1) Estado actual

- El backend de Agenda inicia sesión (`session_start`) en `api/agenda/index.php`.
- La resolución actual de rol (`resolveAgendaActorRole`) acepta, en este orden:
  - `X-Actor-Role`
  - `X-User-Role`
  - sesión (`$_SESSION`)
  - body (`actor_role` / `created_by_role`)
  - query (`actor_role`)
  - fallback `doctor`
- El backend puede operar en `compat` si no se activa modo estricto por entorno.
- El frontend usa contexto no autoritativo para UX (`mxmedStore`, dataset, `mxmedResolveActiveProfessionalContext`).
- Operadores existen en backend (`agenda_operators`), pero aún no se valida de forma autoritativa que un actor `operator` corresponda a un operador activo real ligado al `doctor_id` efectivo.

## 2) Problema

- `headers` / `query` / `body` para actor son spoofeables.
- El frontend puede simular rol para UX y QA, pero no debe ser fuente de seguridad.
- RBAC y auditoría requieren actor confiable en servidor.
- QA/local deben seguir funcionando sin romperse durante la transición.

## 3) Contrato canónico de actor efectivo

Campos objetivo para toda ruta privada de Agenda:

- `actor_role`
- `actor_id`
- `doctor_id`
- `operator_id`
- `channel_origin`
- `auth_source`
- `is_authoritative`
- `auth_mode`

Notas:
- `operator_id` puede ser `null` cuando no aplica.
- `auth_source` y `is_authoritative` deben viajar en metadatos de respuesta para trazabilidad de modo.

## 4) Modos de resolución de actor

### A) `strict`

- Uso objetivo: producción.
- Actor debe venir de fuente fuerte (sesión/JWT/API key autorizada).
- Ignorar override desde body/query/headers salvo configuración explícita QA.
- `is_authoritative=true`.

### B) `compat`

- Uso objetivo: desarrollo/local existente.
- Mantiene fallback para continuidad operativa.
- `is_authoritative=false`.
- `auth_source=compat`.

### C) `qa_override`

- Uso objetivo: QA/dev controlado.
- Permite override por headers/query bajo flag explícito.
- Condicionado por entorno/flag QA.
- `is_authoritative=false` por defecto (o política explícita de laboratorio).

### D) `public_flow`

- Uso objetivo: rutas `/public/*`.
- Separado del RBAC privado.
- Actor esperado típico: `patient`.

## 5) Reglas para `operator`

- Debe existir en `agenda_operators`.
- Debe estar en estado `active`.
- `doctor_id` del operador debe coincidir con el scope efectivo.
- `paused`, `pending` o `archived` no deben operar rutas privadas.
- Si no se puede validar operador en `strict`, denegar (`403`).

## 6) Reglas para otros actores

- `doctor`: identidad propietaria/autenticada del consultorio.
- `patient`: solo flujo público (`/public/*`).
- `call_center`: fase futura con API key/canal autorizado.
- `ai_operator`: fase futura con API key/canal autorizado.
- `system`: procesos internos controlados y auditables.

## 7) Helpers futuros propuestos

- `resolveAuthoritativeAgendaActor()`
- `validateAgendaActorScope()`
- `resolveAgendaOperatorIdentity()`
- `isAgendaQaActorOverrideAllowed()`

Objetivo:
- centralizar decisión de actor efectivo,
- separar seguridad real de compatibilidad QA,
- evitar lógica duplicada por controlador.

## 8) Plan de fases F3

- **F3.1** documentación (este documento).
- **F3.2** helper backend en modo `compat`, agregando meta `auth_source` / `is_authoritative`.
- **F3.3** validación de operador activo contra `agenda_operators`.
- **F3.4** restringir overrides de headers/query/body a QA/dev autorizado.
- **F3.5** frontend consume actor efectivo del backend/sesión (sin inventar actor de seguridad).
- **F3.6** QA integral (`strict` / `compat` / `public` / spoofing).

## 9) QA propuesto

- `compat` sigue funcionando como hoy.
- `strict` sin sesión/token válido rechaza.
- `strict` con doctor autenticado permite operación válida.
- `operator active` permite operación.
- `operator paused/pending/archived` rechaza.
- Spoofing por header/query no debe funcionar en `strict`.
- `/public/*` no debe romperse.
- `qa_override` solo debe funcionar cuando QA esté explícitamente habilitado.

## 10) Riesgos

- Romper entorno local si se endurece demasiado pronto.
- Romper rutas públicas por mezclar reglas privadas/públicas.
- Romper QA histórica si se corta override sin transición.
- Divergencia temporal frontend/backend durante migración de modo.

## 11) Decisiones pendientes

1. Fuente autoritativa final de identidad (sesión, JWT, API key o híbrido).
2. Política exacta para marcar `is_authoritative` en `qa_override`.
3. Contrato definitivo para `call_center` y `ai_operator`.
4. Estrategia de rollout por entorno (local, QA, staging, producción).

## 12) Cierre F3.2A (helper backend actor efectivo)

Implementado en:
- `api/agenda/index.php` (commit `82f6320`)

Helper nuevo:
- `resolveEffectiveAgendaActor(array $segments, string $method, array $query, array $body): array`

Contexto de actor efectivo ahora disponible:
- `actor_role`
- `actor_id`
- `doctor_id`
- `operator_id`
- `channel_origin`
- `auth_source`
- `auth_mode`
- `is_authoritative`
- `actor_role_source`
- `warnings`
- `mode`
- `strict`
- `compat`
- `user_id`

Compatibilidad confirmada:
- Sin header/query/body actor se mantiene fallback `doctor` en `compat`.
- Header/query/body siguen operativos en `compat`/`qa_override`.
- RBAC F2.3 conserva comportamiento (sin nuevas rutas bloqueadas).
- `public/*` no se rompe (`auth_mode=public_flow`).

Limitaciones que permanecen (intencional F3.2A):
- Aún no se valida `operator_id` contra `agenda_operators`.
- Aún no se bloquea `operator` por estado (`paused`/`pending`/`archived`) vía fuente autoritativa.
- Fuentes header/query siguen spoofeables fuera de `strict`.
- Endurecimiento `strict` real queda para F3.3/F3.4.

QA documentado (PASS):
- Sin header => `compat doctor`.
- `X-Actor-Role: operator` => `auth_source=header`.
- `actor_role` por query con QA => `qa_override`.
- Ruta pública => `public_flow`.
- RBAC F2.3 sin regresión.
- `php -l api/agenda/index.php` PASS.
