# MXMed — Gate 4B: autenticación y recuperación seguras V2

Contrato: `MXMED_SECURE_AUTHENTICATION_AND_RECOVERY_GATE_4B_V2`

Actividad: `4/22` — `PRODUCT-IMPLEMENTATION/MXMed-Identity-Auth-Session-Foundation-V2/Gate-4B-Secure-Authentication-And-Recovery`

Estado: **`SECURE_AUTHENTICATION_RECOVERY_GATE_4B_READY_FOR_DIRECTOR_REVIEW`**
Clasificación: **UI-0 — NO_UI_IMPACT**

## 1. Baseline y límites

Gate 4B se implementó en `feature/mxmed-identity-auth-session-foundation-v2`, sobre Gate 4A `32b6ed8ec2764e00f30ca34f60f5fc610ce56e6b`, con baseline de programa `program/mxmed-product-refinement-22-v2@2172423557b1ba7d5840d9ea4861d74f5516bda3`. Gate 4A queda cerrado internamente; Gate 4C y Gate 4D no se iniciaron. El avance oficial permanece `3/22`.

Este gate agrega sólo credenciales backend, verificación, recuperación, límites y notificación simulada. No crea sesiones, cookies, endpoints, middleware, login PHP, frontend, SMS, passwordless, MFA, claim operativo, AWS/CDK, Stripe ni despliegue. La base usada por 8091 no fue modificada.

## 2. Modelo persistente

### `auth_account_credentials`

- PK/FK: `account_id → auth_accounts.account_id`.
- `password_hash VARCHAR(255)` producido exclusivamente con Argon2id.
- `password_changed_at`, `credential_version` (inicia en `1`), `created_at`, `updated_at`.
- Una credencial por cuenta; no guarda contraseña, PIN, preguntas de seguridad, MFA ni historial.
- Rollback: `2026_07_20_04_rollback_auth_account_credentials.sql`.

### `auth_account_one_time_tokens`

- PK `token_id`; FK `account_id`.
- `purpose`: `email_verification` o `password_recovery`.
- `token_hash CHAR(64)` SHA-256 del token aleatorio; nunca se persiste el token claro.
- `issued_at`, `expires_at`, `consumed_at`, `invalidated_at`, timestamps.
- Unicidad por propósito/hash, índices por cuenta/estado/expiración y constraints de propósito/TTL.
- TTL: verificación `24 horas`; recuperación `30 minutos`.
- Un solo uso e invalidación explícita de tokens previos.
- Rollback: `2026_07_20_05_rollback_auth_account_one_time_tokens.sql`.

### `auth_rate_limit_buckets`

- PK determinista `bucket_id`.
- `operation_code`, `dimension_code`, `dimension_key_hash`, ventana, contador y `blocked_until`.
- Las dimensiones se derivan mediante HMAC con pepper inyectado; no se persisten IP, correo ni dispositivo en claro.
- Índices por ventana y bloqueo; fallo de almacenamiento produce decisión cerrada `storage_unavailable`.
- Rollback: `2026_07_20_06_rollback_auth_rate_limit_buckets.sql`.

## 3. Contratos de dominio

Se añadieron contratos para política de contraseña, Argon2id, versiones de credencial, propósitos/estados de tokens, decisiones de registro/verificación/auth/recuperación/reset, operaciones y decisiones de rate limit, mensajes de notificación, reloj y `AuthenticationPrincipalCandidate`.

La política de contraseña es:

- mínimo 12 y máximo 128 caracteres;
- frases permitidas;
- NUL y entradas inválidas rechazadas;
- no se recorta silenciosamente;
- contraseña igual al correo normalizado rechazada;
- sin rotación periódica.

Argon2id usa únicamente `password_hash`, `password_verify` y `password_needs_rehash`. No existe fallback a bcrypt. Los parámetros están centralizados y cualquier configuración por debajo del mínimo seguro produce `argon2id_unsafe_configuration`; si Argon2id no está disponible se produce `ARGON2ID_RUNTIME_UNAVAILABLE`.

La redacción visible de esta política queda `PASSWORD_POLICY_UI_COPY_PENDING_DIRECTOR_REVIEW` para Gate 4D.

## 4. Flujos internos

### Registro pendiente

`RegistrationService` valida correo, contraseña, consentimientos y límite; normaliza el correo; crea en una transacción cuenta `pending_verification`, credencial Argon2id, consentimientos `terms`/`privacy_notice` y token de verificación. La notificación ocurre después del commit. Duplicados devuelven `REGISTRATION_RECEIVED` sin revelar existencia ni crear otra cuenta. Una notificación fallida deja la cuenta pendiente y el token invalidado.

### Verificación de correo

`EmailVerificationService` liga token a `email_verification`, valida hash, expiración, consumo, estado de cuenta y ambos consentimientos; activa únicamente `pending_verification`, consume el token e invalida los anteriores en una transacción. Tokens de recuperación no pueden verificar correo; cuentas `blocked`/`disabled` no se activan.

### Comprobación de credenciales

`CredentialAuthenticationService` aplica límites, usa dummy hash para cuentas inexistentes, verifica Argon2id y revisa estado. Éxito devuelve únicamente `AUTHENTICATION_PRINCIPAL_CANDIDATE` con `account_id`, `credential_version`, `account_status`, `authenticated_at` y `reason_code=allowed`.

No crea cookie, session ID, sesión PHP, membresías, plan, capacidades, redirección ni acceso privado. Los errores públicos se traducen a `INVALID_CREDENTIALS`; el detalle interno distingue reason codes sin exponerlos a una futura capa pública.

### Recuperación

`RecoveryService` responde genéricamente, limita solicitudes, invalida tokens previos y entrega un token de 30 minutos sólo al adaptador de notificación. El reset valida propósito, hash, expiración y un solo uso; crea nuevo hash Argon2id, incrementa `credential_version`, actualiza `password_changed_at`, consume el token e invalida los restantes de forma transaccional.

La revocación efectiva de sesiones queda `SESSION_REVOCATION_ENFORCEMENT_DEFERRED_TO_GATE_4C`.

## 5. Rate limiting

Políticas implementadas:

| Operación | Límite inicial |
|---|---|
| Comprobación de credenciales | 5 fallos / 15 minutos, backoff progresivo, sin bloqueo permanente |
| Recuperación | 3 solicitudes / hora |
| Registro | 3 intentos / hora |
| Reenvío de verificación | 3 solicitudes / hora |
| Consumo de tokens/reset | 5 / 15 minutos |

Las dimensiones admitidas son identidad, IP y dispositivo; se almacenan sólo hashes HMAC. El rate limit falla cerrado si el storage no está disponible.

## 6. Notificación simulada

`IdentityNotificationPort` es el único puerto. Sólo existen:

- `InMemoryIdentityNotificationAdapter` para pruebas;
- `RejectingIdentityNotificationAdapter` para probar fallos.

No se implementaron SMTP, SES, SendGrid, Twilio, SMS, endpoints DEV, escritura de tokens a archivos ni logging de tokens. El token claro vive sólo en memoria durante la notificación de prueba.

## 7. Atomicidad e idempotencia

Se demostró atomicidad para alta, credencial, consentimientos, emisión de token, activación, consumo y reset. Un fallo de validación no deja cuenta parcial; un token consumido o invalidado no puede reutilizarse; una solicitud posterior invalida la anterior; la unicidad de correo impide duplicados; `credential_version` incrementa en cada reset.

## 8. Migraciones y pruebas

Forward posterior a Gate 4A:

1. `2026_07_20_04_create_auth_account_credentials.sql`;
2. `2026_07_20_05_create_auth_account_one_time_tokens.sql`;
3. `2026_07_20_06_create_auth_rate_limit_buckets.sql`.

El runner `modules/identity/tests/run_gate4b_auth_recovery_test.php` exige que la base temporal empiece exactamente con `mxmed_gate4b_test_`, ejecuta forward, rollback y segundo forward, y elimina la base temporal al terminar. Se verificó también la suite completa de Gate 4A.

Resultados:

```text
Gate4A migration forward/rollback/second-forward PASS
Gate4B secure authentication/recovery tests PASS
```

## 9. Seguridad, UI y móvil

- Passwords en claro: `0`.
- Tokens en claro persistidos o en logs: `0`.
- Sessions/cookies: `0`.
- Endpoints públicos: `0`.
- SMS/passwordless/MFA: `0`.
- PII real, datos clínicos/pacientes y secretos: `0`.
- HTML/CSS/JavaScript/API existente/frontend: `0`.
- 8091: intacto y HTTP 200.
- 8140: libre; no se abrió puerto.
- `mobileSmokeRequired=false`.
- `mobileSmokeResult=NOT_APPLICABLE_UI_0`.
- `newMobileDebt=false`.
- `affectedSurfaces=[]`.
- `finalizationChapterDependency=true`.
- `regressionDetected=false`.

## 10. Deuda diferida y siguiente gate

- Sesiones, cookies, logout efectivo y revocación por `credential_version`: Gate 4C.
- Integración frontend, copy y comportamiento visible: Gate 4D.
- MFA, SMS, passwordless y correo productivo: fuera de Gate 4B.
- Reclamación operativa: fuera de Gate 4B.

Gate 4B queda **`READY_FOR_DIRECTOR_REVIEW`**. No se integra parcialmente a `program`, no se crea checkpoint y no se inicia Gate 4C.
