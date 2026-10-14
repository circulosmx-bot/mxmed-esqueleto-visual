# IDENTITY-QA02B — preparación de aprobación, step-up y auditoría

Estado: preparación verificada en base desechable. La asignación real de `owner/profile` permanece deshabilitada.

## Reutilización y límites

- La verificación fresca reutiliza `CredentialAuthenticationService`, Argon2id, `credential_version` y `RateLimitOperation::CREDENTIAL_CHECK`. No existe una segunda implementación de contraseña.
- `auth_account_one_time_tokens` no se amplía: su contrato sólo admite `email_verification` y `password_recovery`. Mezclar step-up con esos ciclos invalidaría sus reglas de propósito e invalidación.
- El cambio conserva `ProfileMembershipRoleChangeService`: bloquea cuenta, perfil, membresía y propietarios; consume ambas evidencias; actualiza la misma membresía; y escribe auditoría antes del commit.
- `platform_audit_events` y su cadena canónica se reutilizan. No se crea una tabla paralela de auditoría Identity.

## Autoridad independiente

La autoridad existente que puede servir como raíz futura es un principal Identity activo registrado como `internal_staff.governance_class=DIRECTOR`. Esa raíz es independiente del rol clínico `collaborator/profile`. QA02B no declara que una cuenta concreta esté autorizada para emitir aprobaciones de ownership y no crea una capacidad, grant, endpoint ni sesión privilegiada.

Por ello, el emisor real sigue deshabilitado. Una aprobación sólo puede llegar al consumidor como una fila durable creada por un emisor futuro, revisado y autorizado por separado. El adaptador implementado consume esa decisión; no ofrece un método para crearla. Un identificador enviado por el solicitante no crea autoridad.

El procedimiento futuro debe exigir: sesión Identity vigente del aprobador, clase interna `DIRECTOR` activa, decisión independiente, aprobación exacta del caso y auditoría de la emisión. No debe permitir autoaprobación, delegación implícita ni un interruptor de entorno.

## Persistencia propuesta

`auth_profile_ownership_approvals` guarda una decisión no secreta con:

- referencia de aprobación, operación, caso y decisión únicas;
- aprobador, ejecutor, cuenta objetivo, membresía y perfil exactos;
- rol y scope esperados, y rol final `owner`;
- ventana temporal y estado `APPROVED`, `CONSUMED`, `REVOKED` o `EXPIRED`;
- consumo único, ligado a la misma operación;
- claves foráneas y una restricción que prohíbe autoaprobación.

`auth_profile_ownership_step_up_proofs` guarda:

- un ID interno no secreto para auditoría;
- SHA-256 del comprobante aleatorio; nunca el valor claro;
- aprobación y operación exactas;
- cuenta, sesión, versión de credencial, membresía, perfil, roles y scope;
- ventana de cinco minutos y consumo único.

El comprobante claro se devuelve una sola vez al futuro adaptador HTTPS y sólo vive en memoria. `ProfileOwnershipStepUpIssuanceService` primero usa la autenticación canónica, comprueba que cuenta y `credential_version` coinciden con la sesión y valida la aprobación durable exacta. No está conectado a HTTP, CLI ni a la composición normal-local.

## Auditoría atómica

`CanonicalProfileMembershipRoleChangeAuditAdapter` agrega, dentro de la transacción propietaria del cambio:

1. `ROLE_REVOKED` para el rol anterior;
2. `ROLE_ASSIGNED` para `owner`;
3. `PROFILE_OWNERSHIP_ASSIGNED` para el perfil.

Los tres eventos incluyen principal actuante, autoridad aprobadora, cuenta, membresía, perfil, roles, scope, caso, decisión, referencia de aprobación, ID no secreto del step-up, operación y resultado. El comprobante de step-up no se registra. Si un evento falla, el adaptador canónico revierte la transacción completa; si la actualización de membresía falla, no se escriben eventos de éxito.

## Migración y recuperación

Forward: `modules/identity/db/migrations/2026_10_14_01_create_profile_ownership_governance.sql`.

Rollback: `modules/identity/db/migrations/2026_10_14_01_rollback_profile_ownership_governance.sql`.

La migración es aditiva e idempotente para una aplicación repetida. No transforma filas existentes. El rollback elimina primero proofs y después approvals; sólo es apropiado antes de habilitar emisión real. Después de habilitarla, la recuperación correcta es desactivar el emisor y los consumidores, preservar evidencia y aplicar una migración correctiva, no borrar tablas.

## Habilitación futura, separada

Una autorización posterior debe aprobar, en este orden:

1. el emisor y la ruta gobernada para `internal_staff.DIRECTOR`, incluidos CSRF/origin y auditoría de la decisión;
2. la aplicación de la migración a normal-local con respaldo y postchecks;
3. la composición explícita de los adaptadores PDO, el writer canónico unido a la misma conexión y la ruta de step-up HTTPS;
4. una ejecución real identificada por caso, después de revisar que exista una sola membresía activa y ningún owner;
5. la activación separada de políticas canónicas en Agenda y la sustitución de adaptadores transitorios en Profile/Media.

Hasta completar esos pasos, los adaptadores rechazadores siguen siendo el default y no existe una superficie que promueva roles. La selección de plan `REAL/STANDARD/OPTIMUM/PROFESSIONAL`, suscripciones, billing y capacidades clínicas permanecen independientes del rol Identity.

## Ensayo desechable

`ProfileOwnershipPersistenceTest.php` crea una base con prefijo protegido, aplica forward dos veces, valida nueve FK, emite step-up con contraseña sintética, ejecuta el cambio sobre una membresía sintética, comprueba los tres eventos canónicos, replay, expiración, autoaprobación y rollback por fallo de auditoría. Después ejecuta el rollback y elimina la base completa. `ProfileMembershipRoleChangeServiceTest.php` conserva la matriz negativa de QA02A, concurrencia serializada y dependencia del plan comercial.

Ninguna migración se aplicó a `mxmed`; no se cambió ninguna cuenta, sesión, membresía, suscripción ni dato clínico real.
