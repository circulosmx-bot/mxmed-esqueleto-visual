# AGENDA · FUENTE AUTORITATIVA DE ACTOR · MXMED

Fecha: 2026-05-22  
Estado: F3.3C-B cerrado (enforcement strict operador activo implementado + QA post-enforcement PASS, con pendientes controlados)

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
- Operadores existen en backend (`agenda_operators`) y ya existe enforcement real en `strict` para rutas privadas operativas elegibles cuando `actor_role=operator`.

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
- Si no se puede validar operador en `strict`, denegar (`403`) o `503` para indisponibilidad de fuente de identidad.

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

## 13) Cierre F3.3B (validación observacional de operador)

Implementado en:
- `api/agenda/index.php`
- `modules/agenda/repositories/OperatorsRepository.php`
- commit: `15d23a4`

Componentes:
- Helper router: `resolveAgendaOperatorIdentity(...)`
- Método repository: `findOperatorIdentity(...)`

Alcance:
- Cuando `actor_role=operator`, backend intenta validar identidad contra `agenda_operators`.
- En `compat` y `qa_override` la validación es **observacional**:
  - no bloquea rutas operativas;
  - agrega señales en `actorContext`.

Warnings definidos:
- `operator_id_missing`
- `operator_not_found`
- `operator_doctor_mismatch`
- `operator_not_active`
- `operator_identity_db_not_ready`
- `operator_identity_valid`

Campos observacionales en contexto:
- `operator_identity_checked`
- `operator_identity_found`
- `operator_status`
- `operator_is_active`
- `operator_identity_warning`
- `warnings[]`

Compatibilidad preservada:
- Sin `operator_id`, operación permitida en `compat`.
- Header/query QA se mantienen.
- RBAC F2.3 mantiene deny/allow existentes.
- `public/*` sin afectación.

Resultado F3.3C:
- Enforcement real en `strict` ya activado para rutas elegibles de operador.
- `operator_id` obligatorio, operador existente, `doctor_id` consistente y `status=active` requerido en strict.
- Operadores `paused` / `pending` / `archived` quedan bloqueados en strict (`403`).
- Observabilidad QA del guard strict ya expuesta bajo `X-QA-Mode: ready`.

## 14) Cierre F3.3C (strict operator enforcement)

Implementado y validado en commits:
- `018a18a` `feat(agenda): prepara guard strict operator dry-run`
- `8b877a4` `feat(agenda): expone meta qa para guard strict operator`
- `4070629` `feat(agenda): activa enforcement strict para operador`

### 14.1 Qué quedó protegido

En `auth_mode=strict`, cuando `actor_role=operator` y la ruta privada es elegible, Agenda aplica enforcement real de identidad de operador antes del controller.

Rutas privadas elegibles:
- `appointments` (read/write operativo de agenda privada)
- `availability` solo `GET`
- `waitlist` `GET/POST/PATCH`
- `consultorios` solo `GET`

Rutas y modos no afectados:
- `public/*`
- `compat`
- `qa_override`
- actor `doctor`
- RBAC F2.3 existente (`/operators`, `settings`, `schedule`, `geocode`)

### 14.2 Diferencia por fase

F3.3C-A (dry-run estructural):
- Evalúa identidad strict para operador.
- Marca `would_block` y `reason`.
- No bloquea ni cambia HTTP.

F3.3C-A2 (observabilidad QA mínima):
- En QA (`X-QA-Mode: ready`) expone meta del guard strict.
- Mantiene sin bloqueo real.

F3.3C-B (enforcement real):
- Activa salida temprana en strict cuando `would_block=true`.
- Mantiene observabilidad QA en respuestas permitidas y bloqueadas.

### 14.3 Reglas strict activas

Para rutas elegibles y `actor_role=operator`:
- `missing_operator_id` => `403`
- `operator_not_found` => `403`
- `doctor_mismatch` => `403`
- `status_not_active` (`paused`/`pending`/`archived`) => `403`
- `operator_identity_db_not_ready` => `503` (preparado en enforcement)

### 14.4 JSON esperado (enforcement)

`403 forbidden_operator_identity`:

```json
{
  "ok": false,
  "error": "forbidden_operator_identity",
  "message": "forbidden for actor role"
}
```

`503 operator_identity_unavailable`:

```json
{
  "ok": false,
  "error": "operator_identity_unavailable",
  "message": "operator identity source unavailable"
}
```

### 14.5 Observabilidad QA (A2 + B)

Con `X-QA-Mode: ready` en rutas privadas no públicas:
- `strict_operator_guard_checked`
- `strict_operator_guard_mode`
- `strict_operator_would_block`
- `strict_operator_block_reason`
- `strict_operator_http_status_future`

Notas:
- No se exponen alias/login/nombre ni datos internos sensibles.
- En `public/*` no se agregan campos strict.

### 14.6 Matriz QA final resumida

- strict + operator active => allowed (200), `checked=true`, `would_block=false`.
- strict + operator paused => `403` (`forbidden_operator_identity`).
- strict + operator archived => `403`.
- strict + missing `operator_id` => `403`.
- strict + wrong doctor => `403`.
- compat + operator inválido => allowed (200) como antes.
- qa_override + operator inválido => allowed (200) como antes.
- doctor + strict => allowed (200).
- public availability => allowed (200), sin campos strict.
- RBAC F2.3 (`/operators`, `settings`, `schedule`, `geocode`) => sin regresión.

### 14.7 Riesgos y pendientes conocidos

- `operator_identity_db_not_ready -> 503` está implementado pero pendiente de validación controlada en QA.
- Smoke UI strict con sesión real queda pendiente fuera de CLI con servidor embebido de un solo hilo.

### 14.8 Estado final F3.3C

F3.3C enforcement strict para operador activo queda implementado y validado en API, con compatibilidad preservada para `compat`, `qa_override`, `doctor`, `public/*` y RBAC F2.3 existente.
