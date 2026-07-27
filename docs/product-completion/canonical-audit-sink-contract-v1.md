# Contrato canónico del audit sink v1

## Ratificación

```text
PROGRAM=MXMed_PRODUCT_COMPLETION_V1
CONTRACT=MXMED_CANONICAL_AUDIT_SINK_CONTRACT_V1
DIRECTOR_DECISION=APPROVE_RECOMMENDED_CANONICAL_AUDIT_SINK_CONTRACT
DIRECTOR_TEXT=APRUEBO EL CONTRATO RECOMENDADO DEL AUDIT SINK CANÓNICO
RATIFICATION_STATUS=APPROVED
RUNTIME_IMPACT=NONE
```

Este documento ratifica el contrato funcional, de seguridad, privacidad,
integridad y evolución del audit sink canónico. No crea tablas, no ejecuta
migraciones, no registra eventos y no activa ningún consumidor o productor.

## D-MP01-01 — Persistencia canónica

```text
AUDIT_PERSISTENCE_MODEL=CANONICAL_CORE_PLUS_SPECIALIZED_LINKED_LEDGERS
CANONICAL_AUDIT_TABLE=platform_audit_events
SPECIALIZED_LEDGERS_PRESERVED=true
SPECIALIZED_PAYLOAD_DUPLICATION_ALLOWED=false
```

`platform_audit_events` es el núcleo general. Se conservan ledgers
especializados de pagos, Clinical, Suscripciones y dominios con semántica
propia. El núcleo enlaza por referencia y no copia payloads completos. Ningún
ledger especializado se elimina sin equivalencia demostrada.

## D-MP01-02 — Retención por clase

```text
RETENTION_MODEL=PER_EVENT_CLASS
RETENTION_CLASS_COUNT=7
PRODUCTION_RETENTION_DURATIONS_RATIFIED=false
LEGAL_REVIEW_REQUIRED_BEFORE_PRODUCTION_PURGE=true
```

El esquema soportará retención por clase, archivo, legal hold, purge gobernado
y evidencia de integridad. No se ratifican duraciones en este contrato.

### RETENTION-01 — AUTH_SECURITY

Autenticación, credenciales, verificación y sesiones.

### RETENTION-02 — OWNERSHIP

Claims, ownership e invitaciones.

### RETENTION-03 — ROLE_ADMIN

Roles, step-up y acciones administrativas sensibles.

### RETENTION-04 — PAYMENT

Referencias a ledgers especializados de pagos.

### RETENTION-05 — CLINICAL_ACCESS

Acceso clínico con metadata opaca y sin contenido.

### RETENTION-06 — GENERAL_ACTIVITY

Actividad general autorizada que no pertenece a una clase más restrictiva.

### RETENTION-07 — BREAK_GLASS_LEGAL_HOLD

Break-glass, investigaciones y eventos sujetos a legal hold.

## D-MP01-03 — IP y user agent

```text
IP_STORAGE_POLICY=HMAC_PROTECTED
RAW_IP_STORAGE_ALLOWED=false
USER_AGENT_POLICY=COARSE_SUMMARY
RAW_USER_AGENT_STORAGE_ALLOWED=false
```

El resumen de user agent sólo admite familia de navegador, sistema operativo
general y clase de dispositivo. Se prohíbe fingerprinting innecesario.

## D-MP01-04 — Timeline personal

```text
USER_SELF_SECURITY_TIMELINE_ALLOWED=true
USER_SELF_GENERAL_AUDIT_ACCESS=false
```

La timeline personal sólo proyectará las filas del catálogo marcadas
`SELF_TIMELINE=true`. Se excluyen notas internas, investigaciones, risk scoring,
soporte, disputas de ownership, datos clínicos y acciones administrativas.

## D-MP01-05 — Acceso interno

```text
PLATFORM_AUDIT_ACCESS=LEAST_PRIVILEGE
CASE_SCOPE_REQUIRED=true
ACCESS_REASON_REQUIRED=true
AUDIT_READS_ARE_AUDITED=true
```

Todo acceso exige rol y scope autorizados, case o reason, step-up cuando aplique
y auditoría de la propia lectura. No existe acceso global por defecto.

## D-MP01-06 — Eventos Clinical

```text
CLINICAL_CONTENT_ALLOWED_IN_CORE_AUDIT=false
CLINICAL_OPAQUE_METADATA_ONLY=true
```

El núcleo sólo admite event type, actor reference, target opaque reference,
result, reason code y correlation. Diagnósticos, recetas, notas, síntomas,
imágenes, resultados, expedientes y payloads clínicos quedan prohibidos.

## D-MP01-07 — Integración con pagos

```text
PAYMENT_AUDIT_MODEL=REFERENCE_SPECIALIZED_PAYMENT_EVENT
PAYMENT_PROVIDER_PAYLOAD_DUPLICATION_ALLOWED=false
```

El núcleo conserva acción, resultado, actor, correlation ID y referencia opaca
al payment event. No copia payloads del proveedor.

## D-MP01-08 — Append-only y correcciones

```text
APPEND_ONLY_REQUIRED=true
ORDINARY_UPDATE_ALLOWED=false
ORDINARY_DELETE_ALLOWED=false
CORRECTION_MODEL=COMPENSATING_EVENT
```

Una corrección crea otro evento, referencia el original, incluye reason code y
preserva la cadena de integridad.

## D-MP01-09 — Request y correlation IDs

```text
REQUEST_ID_GENERATED_BY_SERVER=true
CORRELATION_ID_GENERATED_OR_VALIDATED_BY_SERVER=true
CLIENT_SUPPLIED_AUTHORITY_FOR_CORRELATION=false
REQUEST_ID_REQUIRED=true
CORRELATION_ID_REQUIRED_FOR_MULTI_STEP_OPERATIONS=true
```

Los IDs se propagarán por UI, API, service, persistence y external adapter.
Ningún cliente puede imponerlos arbitrariamente en rutas productivas.

## D-MP01-10 — Severidad y alertas

```text
EVENT_SEVERITY_MODEL=STABLE_ENUM
EVENT_SEVERITIES=INFO|WARN|HIGH|CRITICAL
ALERT_POLICY_DECOUPLED=true
ALERTING_ACTIVATED=false
```

La severidad no genera alertas automáticamente. La política de alertas tendrá
un contrato y una autorización posteriores.

## Envelope canónico

```text
ENVELOPE_VERSION=audit.v1
CANONICAL_EVENT_ID_FORMAT=UUID
TIMESTAMP_STANDARD=UTC_ISO8601
EVENT_VERSIONING_REQUIRED=true
```

Campos mínimos:

```text
event_id
occurred_at
event_type
event_version
severity
result
actor_identity_id
actor_type
actor_role
actor_scope
effective_entity_type
effective_entity_id
target_type
target_id
session_id
request_id
correlation_id
source_module
source_route
reason_code
retention_class
metadata_json
ip_hmac
user_agent_summary
sequence_number
previous_hash
event_hash
created_at
```

La evolución deberá distinguir además authenticated identity, real actor,
effective actor, ownership, operated entity, delegation, impersonation,
break-glass y target.

```text
ACTOR_FROM_CLIENT_BODY_ALLOWED=false
ACTOR_FROM_QUERY_ALLOWED=false
ACTOR_FROM_UNTRUSTED_HEADER_ALLOWED=false
UNKNOWN_ACTOR_ALLOWED_FOR_FAILED_AUTH_ATTEMPTS=true
DELEGATED_ACTOR_MODEL_REQUIRED=true
BREAK_GLASS_AUDIT_REQUIRED=true
```

## Privacidad y sanitización

Cada event type tendrá contrato propio de metadata. Una clave desconocida se
rechaza antes de persistir.

```text
AUDIT_METADATA_ALLOWLIST_REQUIRED=true
UNKNOWN_METADATA_REJECTED=true
RAW_REQUEST_BODY_ALLOWED=false
RAW_HEADERS_ALLOWED=false
RAW_COOKIE_ALLOWED=false
RAW_TOKEN_ALLOWED=false
RAW_OTP_ALLOWED=false
PASSWORD_OR_CREDENTIAL_HASH_ALLOWED=false
CLINICAL_CONTENT_ALLOWED=false
HASH_CHAIN_REQUIRED=true
INSERT_ONLY_WRITER=true
DATABASE_UPDATE_DELETE_GUARDS_REQUIRED=true
TAMPER_EVIDENCE_REQUIRED=true
```

## Catálogo mínimo de eventos

Cada identificador aparece exactamente una vez. `Metadata` enumera sólo claves
permitidas; cualquier otra clave queda rechazada.

| Event type | Producer | Actor | Target | Session | Reason | Severity | Retention | Metadata | Self timeline |
|---|---|---:|---:|---:|---:|---|---|---|---:|
| AUTH_REGISTRATION_REQUESTED | Identity | no | sí | no | no | INFO | AUTH_SECURITY | target_email_hmac, source | false |
| AUTH_EMAIL_VERIFICATION_SENT | Identity | no | sí | no | no | INFO | AUTH_SECURITY | target_email_hmac, delivery_reference | false |
| AUTH_EMAIL_VERIFIED | Identity | sí | sí | no | no | INFO | AUTH_SECURITY | verification_method | true |
| AUTH_LOGIN_SUCCEEDED | Identity | sí | sí | sí | no | INFO | AUTH_SECURITY | ip_hmac, user_agent_summary | true |
| AUTH_LOGIN_FAILED | Identity | no | sí | no | sí | WARN | AUTH_SECURITY | target_email_hmac, ip_hmac, failure_code | true |
| AUTH_PASSWORD_RECOVERY_REQUESTED | Identity | no | sí | no | no | INFO | AUTH_SECURITY | target_email_hmac, accepted | true |
| AUTH_PASSWORD_RESET_SUCCEEDED | Identity | sí | sí | no | no | HIGH | AUTH_SECURITY | recovery_method | true |
| AUTH_PASSWORD_CHANGED | Identity | sí | sí | sí | no | HIGH | AUTH_SECURITY | change_method | true |
| AUTH_SESSION_CREATED | Sessions | sí | sí | sí | no | INFO | AUTH_SECURITY | ip_hmac, user_agent_summary | true |
| AUTH_SESSION_ROTATED | Sessions | sí | sí | sí | sí | INFO | AUTH_SECURITY | rotation_reason | false |
| AUTH_SESSION_REVOKED | Sessions | sí | sí | sí | sí | HIGH | AUTH_SECURITY | revocation_reason | true |
| AUTH_LOGOUT | Sessions | sí | sí | sí | no | INFO | AUTH_SECURITY | logout_source | true |
| AUTH_LOGOUT_ALL | Sessions | sí | sí | no | sí | HIGH | AUTH_SECURITY | revoked_session_count | true |
| PROFILE_CLAIM_REQUESTED | Ownership | sí | sí | sí | no | INFO | OWNERSHIP | claim_reference, evidence_class | false |
| PROFILE_CLAIM_APPROVED | Ownership | sí | sí | sí | sí | HIGH | OWNERSHIP | claim_reference, decision_code | false |
| PROFILE_CLAIM_REJECTED | Ownership | sí | sí | sí | sí | WARN | OWNERSHIP | claim_reference, decision_code | false |
| PROFILE_OWNERSHIP_ASSIGNED | Ownership | sí | sí | sí | sí | HIGH | OWNERSHIP | ownership_reference, assignment_method | false |
| PROFILE_OWNERSHIP_TRANSFERRED | Ownership | sí | sí | sí | sí | CRITICAL | OWNERSHIP | ownership_reference, approval_reference | false |
| INVITATION_CREATED | Ownership | sí | sí | sí | no | INFO | OWNERSHIP | invitation_reference, target_email_hmac | false |
| INVITATION_ACCEPTED | Ownership | sí | sí | sí | no | HIGH | OWNERSHIP | invitation_reference | false |
| INVITATION_REVOKED | Ownership | sí | sí | sí | sí | HIGH | OWNERSHIP | invitation_reference, revocation_reason | false |
| ROLE_ASSIGNED | Roles | sí | sí | sí | sí | HIGH | ROLE_ADMIN | role_code, scope_code, approval_reference | false |
| ROLE_REVOKED | Roles | sí | sí | sí | sí | HIGH | ROLE_ADMIN | role_code, scope_code, revocation_reason | false |
| STEP_UP_CHALLENGE_SUCCEEDED | Roles | sí | sí | sí | no | HIGH | ROLE_ADMIN | challenge_method, operation_class | true |
| STEP_UP_CHALLENGE_FAILED | Roles | sí | sí | sí | sí | HIGH | ROLE_ADMIN | challenge_method, failure_code | true |
| BREAK_GLASS_STARTED | Roles | sí | sí | sí | sí | CRITICAL | BREAK_GLASS_LEGAL_HOLD | case_reference, approval_reference, expiry | false |
| BREAK_GLASS_ENDED | Roles | sí | sí | sí | sí | CRITICAL | BREAK_GLASS_LEGAL_HOLD | case_reference, outcome | false |
| SENSITIVE_ADMIN_ACTION | Roles | sí | sí | sí | sí | HIGH | ROLE_ADMIN | action_code, case_reference, decision_code | false |

```text
MINIMUM_SECURITY_EVENTS=28/28
DUPLICATE_EVENT_IDENTIFIERS=0
MISSING_EVENT_IDENTIFIERS=0
```

## DDL durante requests

```text
RUNTIME_DDL_ALLOWED=false
RUNTIME_DDL_OCCURRENCES_KNOWN=20
RUNTIME_DDL_PRODUCTION_PATHS_KNOWN=3
VERSIONED_MIGRATIONS_REQUIRED=true
```

Hallazgos conocidos, que no se corrigen en esta ratificación:

```text
modules/agenda/helpers/doctor_identity.php
api/clinical/index.php
api/_lib/clinical_documents.php
```

Todo DDL deberá salir de los requests y ejecutarse mediante migraciones
versionadas antes del primer cutover.

## Productores

Orden inicial ratificado:

1. Identity
2. Sessions
3. Ownership
4. Roles

Después se abordarán Perfil, Agenda, Pacientes, Clinical, Suscripciones, Pagos,
Operadores, Moderación, Soporte y Notificaciones. No se instrumentarán todos
simultáneamente.

## Consumidores conceptuales futuros

```text
USER_SELF_SECURITY_TIMELINE
PLATFORM_SECURITY_AUDIT_VIEW
CASE_SCOPED_OPERATOR_AUDIT_VIEW
OWNERSHIP_HISTORY_VIEW
BREAK_GLASS_REVIEW_VIEW
```

No se implementan rutas, APIs o UI en esta ratificación.

## AUDIT-MP01A — Contract and Schema Ratification

- Objetivo: contrato, esquema, eventos, privacidad y retención.
- Límite: ratificación documental sin implementación.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01B — Migration and Persistence Foundation

- Objetivo: migración versionada, evolución de `platform_audit_events`,
  constraints, append-only y hash chain.
- Límite: persistencia sin wiring runtime.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01C — Writer API and Sanitization

- Objetivo: writer canónico, allow-lists, redacción, HMAC y tests.
- Límite: sin productores productivos.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01D — Request and Correlation Context

- Objetivo: request ID, correlation ID, propagación y contexto de actor.
- Límite: sin cutover.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01E — Identity and Session Producers

- Objetivo: eventos Auth/Sessions, preview/shadow y postvalidación.
- Límite: no retirar mecanismos legacy.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01F — Ownership and Role Producers

- Objetivo: claims, ownership, invitations, roles, step-up y break-glass.
- Límite: productores sujetos a autorización posterior.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01G — Read API and Self-Security Timeline Contract

- Objetivo: contrato de lectura, filtros, least privilege y timeline propia.
- Límite: sin dashboard completo.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## AUDIT-MP01H — Postvalidation and Cutover Readiness

- Objetivo: QA, privacidad, integridad, DDL y readiness.
- Límite: no autoriza automáticamente el primer cutover.
- Ciclo futuro: preparación, implementación local, postvalidación, publicación,
  integración, evidencia y rollback.

## Límites explícitos de no activación

```text
AUDIT_SINK_RUNTIME_WIRING_ACTIVATED=false
AUDIT_EVENTS_CREATED=0
AUDIT_MIGRATION_EXECUTED=false
RUNTIME_DDL_REMOVED=false
REQUEST_CONTEXT_ACTIVATED=false
CORRELATION_CONTEXT_ACTIVATED=false
IDENTITY_PRODUCERS_ACTIVATED=false
SESSION_PRODUCERS_ACTIVATED=false
OWNERSHIP_PRODUCERS_ACTIVATED=false
ROLE_PRODUCERS_ACTIVATED=false
AUDIT_READ_API_ACTIVATED=false
AUDIT_UI_ACTIVATED=false
PLATFORM_AUDIT_DASHBOARD_ACTIVATED=false
ALERTING_ACTIVATED=false
RETENTION_PURGE_ACTIVATED=false
AUTHENTICATION_ACTIVATED=false
AWS_USED=false
DOCKER_USED=false
```

Ratificar este contrato no activa auditoría runtime, autenticación, correlation
context, APIs, UI, alertas, migraciones, retención o integraciones externas.
