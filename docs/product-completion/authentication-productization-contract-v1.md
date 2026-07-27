# Contrato canónico de productización de autenticación v1

## Ratificación

```text
PROGRAM=MXMed_PRODUCT_COMPLETION_V1
CONTRACT=MXMED_AUTHENTICATION_PRODUCTIZATION_CONTRACT_V1
DIRECTOR_DECISION=APPROVE_RECOMMENDED_PRODUCTION_AUTHENTICATION_CONTRACT
DIRECTOR_TEXT=APRUEBO EL CONTRATO RECOMENDADO DE AUTENTICACIÓN PRODUCTIVA
RATIFICATION_STATUS=APPROVED
RUNTIME_IMPACT=NONE
```

Este documento ratifica reglas de producto, seguridad, migración y experiencia
de usuario. No implementa ni activa autenticación productiva. Identidad
autenticada, actor efectivo, rol, scope, ownership y entidad operada son
conceptos separados.

## D04-01 — Acceso de médicos existentes

Los médicos que ya poseen un perfil no deben registrarse desde cero ni crear
perfiles duplicados. El flujo vinculante es:

```text
perfil médico existente
→ invitación vinculada
→ verificación del correo
→ creación de contraseña
→ vinculación de identidad
→ acceso al perfil
```

Correo ausente o duplicado, identidad dudosa, múltiples perfiles y conflictos
de ownership deben ingresar a revisión manual.

```text
EXISTING_PHYSICIAN_ACCESS=INVITATION_AND_PASSWORD_CREATION
DUPLICATE_PROFILE_CREATION_ALLOWED=false
CONFLICTS_REQUIRE_MANUAL_REVIEW=true
```

Criterio de aceptación: ninguna invitación puede crear un perfil duplicado ni
conceder acceso antes de verificar el correo y completar la vinculación.

## D04-02 — Propiedad de perfiles

Crear una cuenta no otorga propiedad automática. Ownership sólo puede
concederse mediante invitación previamente vinculada, reclamo revisado y
aprobado, o asignación explícita por un operador autorizado. Nombre o
coincidencia de correo no son prueba suficiente por sí solos.

```text
PROFILE_OWNERSHIP_AUTO_GRANTED=false
OWNERSHIP_GRANT_METHODS=INVITATION|REVIEWED_CLAIM|AUTHORIZED_ASSIGNMENT
```

Criterio de aceptación: todo grant, rechazo, transferencia o revocación de
ownership debe ser trazable, reversible y asociado a evidencia autorizada.

## D04-03 — Registro de médicos nuevos

El registro público puede crear una identidad autenticable. Después de
verificar el correo, la persona puede reclamar un perfil existente o iniciar
la creación de uno nuevo. La cuenta inicia únicamente como
`AUTHENTICATED_USER`.

No recibe automáticamente ownership, rol de operador, acceso clínico, rol
interno ni privilegios administrativos.

```text
DEFAULT_REGISTRATION_ROLE=AUTHENTICATED_USER
DEFAULT_REGISTRATION_OWNERSHIP=false
DEFAULT_REGISTRATION_CLINICAL_ACCESS=false
DEFAULT_REGISTRATION_PLATFORM_PRIVILEGES=false
```

Criterio de aceptación: la primera sesión válida no puede operar recursos
privados sin membership, vínculo o autorización adicional.

## D04-04 — Canal de correo

La interfaz de entrega debe permanecer desacoplada del proveedor. Preview es
el adapter de desarrollo local y AWS SES es el proveedor futuro seleccionado
para producción. Esta ratificación no autoriza configuración o despliegue de
SES.

```text
LOCAL_DEVELOPMENT_EMAIL_ADAPTER=PREVIEW
PRODUCTION_EMAIL_PROVIDER=AWS_SES
AWS_SES_DEPLOYMENT_AUTHORIZED=false
```

Los correos iniciales son: verificación de cuenta, recuperación de contraseña,
invitación a perfil, invitación de operador, aviso de cambio de contraseña y
alerta de seguridad.

Criterio de aceptación: templates versionados, retry, auditoría de entrega y
tratamiento de rebotes deben existir antes de activar entrega productiva; los
tokens completos nunca se registran.

## D04-05 — Verificación obligatoria

El correo debe verificarse antes del acceso normal al producto. Una cuenta no
verificada sólo puede reenviar verificación, corregir o cambiar correo mediante
un flujo seguro, cerrar sesión o solicitar soporte.

```text
EMAIL_VERIFICATION_REQUIRED=true
UNVERIFIED_ACCOUNT_FULL_ACCESS=false
```

Criterio de aceptación: toda ruta normal del producto rechaza una cuenta no
verificada y la UI restringida nunca aparenta acceso completo.

## D04-06 — Política de sesiones

Se conserva el contrato existente:

```text
SESSION_IDLE_TIMEOUT_SECONDS=3600
SESSION_ABSOLUTE_TIMEOUT_SECONDS=43200
MAX_ACTIVE_SESSIONS=5
REVOKE_PRIOR_SESSIONS=true
COOKIE_NAME=__Host-mxmed_session
COOKIE_SECURE=true
COOKIE_HTTPONLY=true
COOKIE_SAMESITE=Lax
```

El usuario debe poder cerrar la sesión actual, listar sesiones activas, revocar
una sesión y cerrar las demás. Cambiar o restablecer contraseña revoca las
sesiones previas.

Criterio de aceptación: expiración, rotación, revocación y límites deben
probarse contra el store real; ninguna lista mock puede presentarse como activa.

## D04-07 — Recuperación de contraseña

El flujo aprobado es:

```text
solicitud neutral
→ token aleatorio
→ almacenamiento del hash
→ entrega por canal autorizado
→ uso único
→ expiración
→ nueva contraseña
→ invalidación del token
→ revocación de sesiones previas
→ auditoría
```

La respuesta no confirma si el correo existe. Copy conceptual: “Si existe una
cuenta asociada, recibirás las instrucciones.”

```text
ANTI_ENUMERATION_REQUIRED=true
RESET_TOKEN_SINGLE_USE=true
RESET_TOKEN_HASHED_AT_REST=true
RESET_TOKEN_RETURNED_BY_PRODUCTION_API=false
SESSIONS_REVOKED_AFTER_RESET=true
```

Criterio de aceptación: replay, expiración, concurrencia y revocación
transversal deben fallar cerrado.

## D04-08 — MFA y step-up authentication

MFA es obligatorio para administradores técnicos, operadores internos de
plataforma, personal de seguridad y acciones break-glass. Step-up es obligatorio
para cambiar correo o contraseña, transferir ownership, asignar roles, realizar
activaciones manuales, acceso clínico extraordinario y exportaciones sensibles.

Para médicos, MFA es opcional inicialmente en login ordinario y sólo podrá
activarse desde Seguridad cuando exista soporte real end-to-end. Controles de
2FA, biometría o sesiones no pueden mostrarse como activos mientras sean mock.

```text
MFA_REQUIRED_FOR_PLATFORM_PRIVILEGED_ROLES=true
MFA_REQUIRED_FOR_BREAK_GLASS=true
STEP_UP_REQUIRED_FOR_SENSITIVE_ACTIONS=true
PHYSICIAN_MFA_INITIAL_POLICY=OPTIONAL
MOCK_SECURITY_CONTROLS_VISIBLE_AS_ACTIVE=false
```

Criterio de aceptación: el servidor decide cuándo exigir MFA/step-up; el
cliente no asigna nivel de confianza.

## D04-09 — Operadores de Agenda existentes

Los operadores actuales migran mediante invitación:

```text
operador existente
→ invitación
→ identidad propia
→ verificación
→ preservación de asignación al médico
→ permisos limitados de Agenda
```

No se convierten automáticamente en operadores internos, soporte, moderadores,
usuarios clínicos o administradores.

```text
AGENDA_OPERATOR_MIGRATION=INVITATION_BASED
AGENDA_OPERATOR_SCOPE=AGENDA_ONLY
TEMPORARY_PASSWORDS_PERMANENTLY_ALLOWED=false
```

Criterio de aceptación: cada operador tiene identidad propia, binding revocable
y actor efectivo auditable sin heredar ownership.

## D04-10 — Orden del cutover de autoridad

El orden vinculante es:

```text
1. Audit sink
2. Identidad canónica
3. Sesiones reales
4. Perfil y Suscripciones
5. Agenda
6. Pacientes
7. Clinical
8. Retiro de session_scope en rutas productivas
```

Clinical va al final por sensibilidad. En cada cutover se comparan legacy y
canónico, se conserva rollback, no se retira legacy antes de postvalidación y
no se activan varios módulos simultáneamente.

Criterio de aceptación: cada gate tiene evidencia propia y safe return probado;
`session_scope` sólo se retira después de completar todos sus consumidores.

## D04-11 — Auditoría de seguridad

Debe existir un audit sink canónico antes del primer cutover productivo. El
conjunto mínimo incluye: registro, verificación, login exitoso y fallido,
recuperación, reset, cambio de contraseña, creación y revocación de sesión,
logout all, reclamo de perfil, cambio de ownership, invitación, cambio de rol,
acción sensible, break-glass y acción administrativa de alto riesgo.

No se almacenan contraseñas, tokens completos, cookies, secretos o información
clínica innecesaria.

```text
AUDIT_SINK_REQUIRED_BEFORE_FIRST_CUTOVER=true
SECURITY_AUDIT_MINIMUM_EVENT_SET_DEFINED=true
```

Criterio de aceptación: sanitización, correlación, integridad, disponibilidad y
fallo cerrado están probados antes de autorizar un cutover.

## D04-12 — UI de autenticación

Se reutilizan y productizan las superficies UI-3 existentes:

```text
Registro
Login
Verificación pendiente
Recuperación de contraseña
Nueva contraseña
Invitación
Reclamo de perfil
Sesiones activas
Seguridad de cuenta
Estados de error
Estados de carga
Estados de éxito
```

No se construye una segunda UI paralela sin necesidad, no se muestran controles
mock como funcionales, cada estado corresponde a backend real, se conserva la
coherencia visual del producto y el cierre visual requiere revisión directiva
en navegador.

Criterio de aceptación: responsive, accesibilidad, navegación y estados reales
de cada rol pasan E2E y revisión visual.

## Roles mínimos ratificados

```text
AUTHENTICATED_USER
PROFILE_OWNER
AGENDA_OPERATOR
CLINICAL_USER
PLATFORM_OPERATOR
TECHNICAL_ADMIN
```

No son equivalentes.

```text
ROLE_ASSIGNMENT_FROM_CLIENT=false
PLATFORM_ROLE_ASSIGNMENT_RESTRICTED=true
CLINICAL_ACCESS_FROM_OWNERSHIP_ONLY=false
AGENDA_OPERATOR_IMPLIED_CLINICAL_ACCESS=false
```

## Estrategia de migración ratificada

```text
MIGRATION_MODE=COHORT_INVITATION_AND_REVIEWED_OWNERSHIP_LINKING
```

La estrategia usa cohortes de médicos, invitación de operadores, ownership
revisado, cola manual de conflictos, reset obligatorio cuando no exista hash
compatible, migración directa de hash sólo tras demostrar compatibilidad,
auditoría de cada vínculo y rollback sin eliminar identidades legacy durante la
transición.

## Secuencia vinculante de microfases

## AUTH-MP01 — Audit Sink Foundation

- **Objetivo:** contrato de eventos, persistencia, sanitización, correlación y
  tests, sin cutover.
- **Entrada:** esquema de eventos y límites de privacidad aprobados.
- **Salida:** sink canónico disponible y fail-closed.
- **Aceptación:** eventos mínimos, redacción e indisponibilidad probados.
- **Rollback:** ninguna autoridad se activa en esta microfase.

## AUTH-MP02 — Identity Migration and Ownership Readiness

- **Objetivo:** fuentes existentes, matching, cohortes, conflictos, rollback y
  dry-run.
- **Entrada:** reglas D04-01 a D04-03 ratificadas.
- **Salida:** plan determinista sin escrituras productivas.
- **Aceptación:** duplicados y ownership ambiguo entran a revisión manual.
- **Rollback:** fuentes legacy intactas.

## AUTH-MP03 — Email Delivery Adapter and Templates

- **Objetivo:** interfaz, preview adapter, adapter SES desacoplado, templates,
  auditoría y contrato de retry, sin acceso AWS real.
- **Entrada:** audit sink disponible y contrato de entrega aprobado.
- **Salida:** adapters aislados por entorno.
- **Aceptación:** tokens redactados, idempotencia y bounce probados.
- **Rollback:** adapter rejecting y entrega productiva apagada.

## AUTH-MP04 — Registration and Email Verification Productization

- **Objetivo:** rutas productivas, UI real, verificación obligatoria, resend y
  anti-duplicados, sin ownership automático.
- **Entrada:** identidad, correo y migraciones autorizadas disponibles.
- **Salida:** cuenta verificada con rol `AUTHENTICATED_USER`.
- **Aceptación:** CSRF, rate limit, replay y duplicados pasan E2E.
- **Rollback:** entrada pública desactivable sin eliminar cuentas.

## AUTH-MP05 — Login, Sessions and Logout Productization

- **Objetivo:** login productivo, cookie canónica, rotación, límites, logout,
  logout all y sesiones activas.
- **Entrada:** cuenta verificada y store productivo listos.
- **Salida:** sesión canónica observable.
- **Aceptación:** fixation, TTL, límites y revocación pasan E2E.
- **Rollback:** safe return sin fallback abierto.

## AUTH-MP06 — Password Recovery and Reset Productization

- **Objetivo:** solicitud neutral, token single-use, reset, revocación y avisos
  de seguridad.
- **Entrada:** correo y sesiones productivas disponibles.
- **Salida:** recuperación funcional sin enumeración.
- **Aceptación:** replay, expiry y revocación total pasan E2E.
- **Rollback:** soporte asistido y endpoint público apagado.

## AUTH-MP07 — Profile Claim and Invitation

- **Objetivo:** invitación de médicos, reclamo, ownership, revisión manual y
  auditoría.
- **Entrada:** dry-run de ownership aprobado.
- **Salida:** memberships verificadas y reversibles.
- **Aceptación:** email o nombre por sí solos nunca conceden ownership.
- **Rollback:** revocar membership sin eliminar identidad.

## AUTH-MP08 — Agenda Operator Identity Migration

- **Objetivo:** invitaciones, identidad propia, asignaciones, retiro de
  contraseñas temporales y scope Agenda-only.
- **Entrada:** bindings y cohortes de operadores aprobados.
- **Salida:** operator_id vinculado a account_id.
- **Aceptación:** permisos limitados y actor efectivo auditable.
- **Rollback:** binding revocable y registro legacy preservado.

## AUTH-MP09 — Canonical Authority Cutover by Module

- **Objetivo:** cutover secuencial de Perfil/Suscripciones, Agenda, Pacientes y
  Clinical.
- **Entrada:** microfases anteriores postvalidadas.
- **Salida:** cada módulo consume autoridad canónica.
- **Aceptación:** no se retira `session_scope` hasta completar cada gate.
- **Rollback:** un módulo por vez, con safe return probado.

## AUTH-MP10 — Authentication UI and End-to-End Closure

- **Objetivo:** estados visuales, responsive, accesibilidad, E2E por rol,
  revisión visual directiva y cierre.
- **Entrada:** backend y cutovers postvalidados.
- **Salida:** una UI veraz y coherente.
- **Aceptación:** journeys por rol, errores, carga y éxito pasan E2E y revisión.
- **Rollback:** ninguna simulación se presenta como función activa.

Cada microfase futura debe pasar preparación, implementación local,
postvalidación, publicación, integración y evidencia mediante autorizaciones
separadas.

## Límites explícitos de esta ratificación

```text
PRODUCTION_LOGIN_ACTIVATED=false
PASSWORD_RECOVERY_ACTIVATED=false
EMAIL_DELIVERY_ACTIVATED=false
AWS_SES_CONFIGURED=false
IDENTITY_MIGRATION_EXECUTED=false
OWNERSHIP_MIGRATION_EXECUTED=false
SESSION_SCOPE_REMOVED=false
CANONICAL_AUTHORITY_ACTIVATED=false
ROLES_ACTIVATED=false
MFA_ACTIVATED=false
```

Ratificar este contrato no activa ninguna función, no modifica datos y no
autoriza una fase posterior.
