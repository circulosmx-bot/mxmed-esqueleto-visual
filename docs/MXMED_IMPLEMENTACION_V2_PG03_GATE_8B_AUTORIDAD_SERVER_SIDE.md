# BE/MXMed-PG03-Activity08-Gate8B-ServerAuthoritativeActors-01

## Resultado

`PASS_ACTIVITY_8_GATE_8B_SERVER_AUTHORITATIVE_ACTORS_IMPLEMENTED`

Gate 8B implementa una resolución server-side, tipada y fail-closed de actores
de Agenda. La autoridad se construye sólo desde Identity validado, membresía
activa, perfil canónico, ownership, alcance de backend, binding de operador y
`AuthorizationBoundary` de Platform.

## Resumen ejecutivo

`AgendaActorAuthorityResolver` produce una autoridad real de Gate 8A y separa
actor real de actor efectivo. Un propietario conserva su actor de cuenta; un
operador válido sólo puede actuar mediante un `OperatorBinding` ya resuelto por
backend y queda representado como actor efectivo distinto. Los roles usados
son únicamente `owner`, `administrator` y `collaborator`.

Los valores provenientes de cliente se reciben, cuando corresponde, como
`ClientAuthorityClaims` no confiables. Sólo generan un diagnóstico minimizado
de mismatch; nunca pueden crear sesión, seleccionar actor, conceder rol,
ownership, membresía, scope o una decisión `allow`.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity08-v2`.
- HEAD inicial obligatorio: `9bb7d8f8ec448edd8a0d77dabd44834b9d1f98af`.
- Programa oficial no integrado: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Checkpoint base: `checkpoint/mxmed-product-refinement-v2-activity07`.
- Gate 8A: `POSTVALIDATED_COMPLETE`.
- Contador oficial: `7/22`.
- Actividad 8: `IN_PROGRESS_GATE_8B`.
- Actividad 9: `BLOCKED`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

## Dependencias Gate 4, Gate 6 y Gate 8A

Gate 8B consume el resultado validado de Identity (sesión, principal,
membership y referencia canónica), la frontera central de Platform y el
`ActorAuthorityContract` puro de Gate 8A. Gate 4 aporta la separación de
sesión/autorización; Gate 6 aporta `TrustedAuthorizationContext`, riesgos,
scopes, audit port y `AuthorizationBoundary`. No se modifican Identity,
Platform ni ninguno de los contratos de Gate 8A.

## Autoridad server-side

`AgendaActorAuthorityResolver` recibe exclusivamente objetos ya resueltos:

- `AuthenticatedAccessContext` con `SessionPrincipal` y `SessionRecord` activo;
- `AccountMembership` activa y perteneciente a la cuenta del principal;
- `CanonicalProfileReference` de perfil coincidente;
- `AgendaAuthorizationTarget` normalizado por backend;
- `OperatorBinding` opcional proveniente de un puerto backend;
- `ClientAuthorityClaims` opcional y siempre no confiable.

El resolver verifica sesión activa, cuenta coincidente, membresía activa,
referencia de perfil, método/recurso privado, ownership, rol derivado, scope
solicitado y binding cuando aplica. Cualquier inconsistencia se deniega antes
de crear autoridad.

## Actor real y actor efectivo

`AgendaServerAuthorityContext` es inmutable y contiene el contexto Identity,
membership, perfil canónico, actores real/efectivo, cuenta, membership,
ownership, scope, correlation ID, request ID y fuente fija
`backend_resolver`. El actor real es la cuenta autenticada. El actor efectivo
es la misma cuenta para owner/administrator o el operador backend enlazado.

El resultado produce `ActorAuthorityContract::trusted(...)` sólo después de
que `AuthorizationBoundary` devuelve `allow`. Nunca existe un constructor de
autoridad desde headers, query, body o fallback.

## Membresía y ownership

La cuenta del principal debe coincidir exactamente con la membresía. Sólo
`MembershipStatus::ACTIVE` concede autoridad. El perfil solicitado debe ser el
perfil canónico de la membresía; perfiles distintos, membresías de otra cuenta,
pendientes, suspendidas o revocadas fallan cerrado con 403.

`owner` puede actuar sobre el perfil coincidente. `administrator` conserva su
rol de membership y queda sujeto al scope server-side. `collaborator` no se
convierte automáticamente en doctor u operador; necesita un binding backend
válido para los recursos que permiten operador. Settings, consultorios,
operators y medical-groups mantienen configuración reservada al propietario o
al rol explícitamente permitido.

## Binding de operador

`OperatorBinding` representa `operator_id`, `account_id`, `profile_id`, estado,
activo, scopes, fuente backend y vigencia opcional. No consulta base de datos y
no acepta un `operator_id` del cliente como autoridad.

El binding sólo es utilizable si está activo, en estado `active`, proviene de
`backend`, coincide con cuenta y perfil, contiene el scope solicitado y está
dentro de su vigencia. Ausente, inactivo, revocado, vencido o asociado a otro
perfil produce 403. Un binding no habilita rutas reservadas al propietario.

## Claims cliente no confiables

`ClientAuthorityClaims` conserva únicamente señales de mismatch para detectar
role, actor, doctor/profile u operator claim discrepantes. Expone un diagnóstico
booleano minimizado con `trusted=false`; no conserva payload, cookies, tokens,
PII ni valores de autoridad. `X-Actor-Role`, `X-User-Role`, `X-Actor-Id`,
`X-User-Id`, `X-Operator-Id`, `actor_role`, `actor_id`, `created_by_id`,
`operator_id`, `doctor_id`, `channel_origin`, query y body no pueden cambiar
ninguna decisión server-side.

## Matriz de rutas privadas

La política determinista cubre exactamente diez recursos privados y ninguna
ruta pública:

| Recurso | Métodos | Roles derivados | Operador | Ownership | Riesgo |
|---|---|---|---:|---:|---|
| `appointments` | GET/POST/PATCH/DELETE | owner, administrator, collaborator | sí | sí | R1 |
| `patients` | GET/POST/PATCH | owner, administrator, collaborator | sí | sí | R1 |
| `consultorios` | GET/POST/PATCH/DELETE | owner, administrator | no | sí | R1 |
| `availability` | GET/POST/PATCH | owner, administrator, collaborator | sí | sí | R1 |
| `schedule` | GET/POST/PATCH | owner, administrator, collaborator | sí | sí | R1 |
| `settings` | GET/PATCH | owner | no | sí | R2 |
| `waitlist` | GET/POST/PATCH | owner, administrator, collaborator | sí | sí | R1 |
| `operators` | GET/POST/PATCH | owner, administrator | no | sí | R2 |
| `medical-groups` | GET/POST/PATCH | owner, administrator | no | sí | R1 |
| `geocode` | GET | owner, administrator, collaborator | sí | sí | R1 |

No se agregan capacidades comerciales, seeds ni rutas públicas. No existen
roles wildcard (`*`, `all`, `admin.everything`, `support.all`).

## AuthorizationBoundary

El resolver adapta el contexto de Agenda a `AuthorizationContext` y lo marca
trusted únicamente con `TrustedAuthorizationContext::fromBackend`. Reutiliza
`Platform\Services\AuthorizationBoundary` para sesión, cuenta, membership,
perfil, ownership, rol, scope, acción, recurso y riesgo. El `AuditTrailPort` se
inyecta en el servicio; el resolver no instancia adaptadores de auditoría.

Una decisión denegada nunca se convierte en allow. Para R1/R2 la ausencia o
indisponibilidad del audit port produce fail-closed y HTTP 503.

## Fail-closed y resultados HTTP

- sesión faltante o inválida: 401;
- cuenta o membresía inactiva, mismatch de cuenta/perfil, ownership, rol,
  scope o binding: 403;
- audit port requerido ausente/no disponible: 503;
- allow sólo después de satisfacer todas las condiciones server-side.

La respuesta pública sólo contiene `authorized`, `status` y un error genérico.
No expone account_id, membership_id, profile_id, tokens, cookies ni reason
codes internos.

## Límites del Gate 8B

No se cambian `api/agenda/index.php`, otros entrypoints, controladores,
repositories, helpers, Identity, Platform, UI, JavaScript, CSS, HTML, SQL,
migraciones, AWS ni datos reales. No se conecta aún el router legacy, no se
realiza cutover y no se inicia Gate 8C.

El blocker de compatibilidad de autoridad cliente en el router legacy permanece
contenido y pendiente. El siguiente paso es una integración shadow/rollout
controlada; el fallback debe retirarse antes de cualquier readiness productivo.

## Seguridad y privacidad

Los nuevos archivos no leen superglobales, headers, cookies, sesiones globales,
red, filesystem, base de datos ni payloads. No generan ni envían OTP, no crean
citas, no ejecutan SQL y no escriben datos. Los diagnósticos de claims sólo
contienen flags booleanos.

## No runtime wiring

El servicio y sus contratos son aditivos y sólo están ejercitados por la prueba
aislada de Gate 8B. El router legacy conserva su contenido byte por byte.
Runtime wiring y route behavior changes permanecen en cero; 8091 no cambia.

## Pruebas

`modules/agenda/tests/Gate8BServerAuthoritativeActorsTest.php` cubre sesión,
cuenta, membresía, perfil, ownership, claims no confiables, fallback,
collaborator/operator binding, rutas reservadas, matriz 10/10, roles wildcard,
AuthorizationBoundary cliente, audit fail-closed, respuesta minimizada y
byte-equivalencia de router, contratos Gate 8A, Gate8ACanonicalContractsTest y
PP-304.

También se ejecutan las regresiones Identity, Platform y Subscriptions exigidas
por el Gate.

## Rollback

El rollback es la eliminación del commit de Gate 8B en la rama candidata o la
reversión del único commit nuevo. No requiere migración, SQL, cambio de runtime,
despliegue AWS ni modificación del router.

## Evidencia

`/tmp/mxmed-activity08-gate8b-server-authority-v2/` contiene exactamente diez
JSON válidos (`baseline-state.json`, `changed-files-audit.json`,
`identity-platform-dependency-audit.json`, `actor-authority-resolution-audit.json`,
`client-claims-rejection-audit.json`, `operator-binding-audit.json`,
`private-route-policy-audit.json`, `fail-closed-audit.json`, `test-results.json`
y `qa-result.json`) y tres textos (`git-final-state.txt`,
`no-runtime-wiring.txt`, `no-real-data-write.txt`).

## Git

Gate 8B se entrega como el tercer commit sobre el programa:

`feat(agenda): implementa autoridad server-side PG03 gate 8B`

No se usa amend, rebase, squash, merge, cherry-pick, force push ni checkpoint.

## Estado del programa

- Gate 8A: `POSTVALIDATED_COMPLETE`.
- Gate 8B: `COMPLETE`.
- Gate 8C: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS_GATE_8B_COMPLETE`.
- Actividad 9: `BLOCKED`.
- Contador oficial: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.
