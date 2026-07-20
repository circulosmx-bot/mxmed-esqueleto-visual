# MXMed Identity — Gate 4C: sesiones server-side y autorización fail-closed

## Estado

`SERVER_SIDE_SESSION_AUTHORIZATION_GATE_4C_READY_FOR_DIRECTOR_REVIEW`

Actividad 4/22, UI-0, sin conexión de UI ni endpoints públicos. Gate 4A y Gate
4B permanecen cerrados internamente; Gate 4D no iniciado; avance oficial 3/22.

## Alcance implementado

Gate 4C añade contratos PHP para IDs/tokens opacos, estados, política temporal,
registros mínimos, descriptores de cookie y decisiones internas. El token tiene
32 bytes CSPRNG, se entrega sólo como valor interno y se deriva a HMAC-SHA256
con pepper inyectado; el store nunca conserva el token en claro.

La política vigente es idle TTL de 3,600 segundos, touch cada 300 segundos,
duración absoluta de 43,200 segundos y máximo de cinco sesiones activas. La
sexta sustituye la menos recientemente utilizada con estado `superseded`.
No existe remember-me ni fallback local silencioso.

`InMemorySessionStoreAdapter` queda limitado a test/DEV explícito; el adaptador
rejecting demuestra `session_store_unavailable` fail-closed; la factory exige
Valkey en staging/production. `ValkeySessionStoreAdapter` depende de una
interfaz estrecha inyectable y no conecta a AWS durante las pruebas.

La validación comprueba store, estado, inactividad, expiración absoluta, cuenta
activa y `credential_version`. Rotación invalida el token anterior, logout es
idempotente y devuelve únicamente un descriptor interno; Gate 4C no llama
`setcookie`, no inicia sesión PHP y no emite cabeceras HTTP.

La autorización separa sesión, cuenta, membresía, perfil, rol/scope y capacidad.
Reutiliza `ExistingCapabilityAuthorityService` mediante su contrato público,
deniega valores desconocidos y nunca permite que `transitional_open` conceda
acceso privado.

## Reconciliación TTL

PP260/PP261 aprobaron originalmente idle TTL de 1,800 segundos. La decisión
posterior `C5_APPROVED_BY_DIRECTOR` lo sustituyó exclusivamente por 3,600
segundos. Se preservan Valkey 8.2, prefijo ambiental, cookie, cifrado,
topología, duración absoluta de 43,200 segundos y demás decisiones AWS. La
recuperación de contraseña conserva sus 30 minutos y el RTO documental de
sesiones permanece en 30 minutos.

La ampliación `MXMED_GATE4C_AWS_CDK_SESSION_IDLE_TTL_SCOPE_EXPANSION_V2`
autorizó únicamente actualizar los contratos/configuración/tests AWS/CDK
relacionados con `sessionIdleTtlSeconds`; no se cambiaron recursos, capacidad,
red, secretos, IAM ni arquitectura.

## Evidencia y límites

- endpoints públicos, UI, `session_start` y `setcookie`: 0;
- AWS writes/deployments y base principal: 0;
- 8091 intacto y 8140 libre;
- recuperación 30 minutos y valores no relacionados preservados;
- Gate 4D, soporte asistido, panel de dispositivos y correo productivo quedan
  diferidos.

Evidencia no versionada: `/tmp/mxmed-activity04-gate4c-session-authorization-v2/`.
