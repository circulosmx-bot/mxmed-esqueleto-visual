# PRODUCT-IMPLEMENTATION — PG-08 Gate 6A: contratos transversales

## Resultado

`PASS_GATE_6A_CROSS_CUTTING_CONTRACTS_READY_FOR_REVIEW`

Contrato: `MXMED_PG08_CROSS_CUTTING_CONTRACTS_FOUNDATION_GATE_6A_V1`
Clasificación: UI-0 — `BACKEND_CONTRACTS_NO_RUNTIME_BEHAVIOR_CHANGE`

Gate 6A traduce DEC-012A–DEC-012F a contratos puros, vocabularios,
value objects, interfaces y pruebas unitarias. No conecta los contratos a
endpoints, routers, bases de datos, UI, identidad productiva ni runtime.

## Baseline y ubicación arquitectónica

- Programa: `program/mxmed-product-refinement-22-v2`
- Baseline: `be9cd4f067096a7798d1ea6941ec291981039823`
- Rama: `feature/mxmed-apis-data-permissions-privacy-foundations-v2`
- Bounded context elegido: `modules/platform/contracts`
- Namespace: `Platform\\Contracts`
- Pruebas: `modules/platform/tests/Gate6AContractsTest.php`
- Autoload versionado modificado: no; el repositorio usa carga explícita en
  pruebas y no contiene un PSR-4 transversal que deba ampliarse.

La ubicación sigue el patrón existente de módulos (`modules/identity/contracts`
y `modules/subscriptions/contracts`) sin duplicar tipos de Identity o
Capabilities ni mover archivos existentes.

## Contratos creados

- `AuthorizationPlane`: customer/professional, internal operator,
  governance/emergency y public/system, sin equivalencia con planes comerciales.
- `RiskLevel`: R0–R3 y requisitos de actor, auditoría, reautenticación, MFA,
  caso y aprobación.
- `AuthorizationContext`: actor real, actor efectivo, sujeto afectado,
  referencia de sesión no sensible, account, credential version, membership,
  entity, profile, ownership, role, scopes, capabilities, action, resource,
  plane, risk, correlation/request/case y approvals.
- `AuthorizationDecision`: default deny, reason code obligatorio para deny y
  regla satisfecha obligatoria para allow.
- `ReasonCode`: registro único, estable, tipado y fail-closed.
- `CanonicalSourceRecord` y `CanonicalSourceRegistry`: autoridad canónica,
  proyección, legacy, draft, fixture y unresolved con una sola
  `canonical_write`.
- `RetentionPolicy`, `RetentionState`, `DispositionAction` y
  `DispositionResolution`: retención por dominio, `retention_unresolved`,
  `anonymization_unresolved`, legal hold, estados de ciclo y acciones R3.
- `AuditTrailPort`, `AuditEventReference`, `AuditAvailability` y
  `AuditWriteResult`: frontera diferida sin almacenamiento persistente.
- `FeatureFlags`, `SupportAccessState`, `SupportAssistedAccessContract` y
  `BreakGlassContract`: estados futuros, scope/caso/expiración/auditoría y
  flags inicialmente `false`.

## Invariantes

- Default deny y `transitional_open`/client identity no autoritativos.
- R2/R3 requieren audit trail futuro; la indisponibilidad es representable.
- Sólo una fuente `canonical_write` activa por entidad.
- Proyecciones, legacy, draft y fixtures no autorizan writes.
- `retention_unresolved` bloquea eliminación automática.
- Legal hold bloquea anonimización y eliminación.
- Datos clínicos no dependen del estado comercial.
- Delete, anonymize y exportación masiva son R3.
- No se permite transición directa active/inactive → deleted.
- `real_actor` y `effective_actor` permanecen separados.
- Soporte clínico y break-glass deniegan por defecto; ambos flags son false.
- No se aceptan tokens, cookies, contraseñas, secretos, `client_secret`,
  payloads ni datos clínicos en los value objects o metadatos sanitizados.

## Frontera diferida

El audit trail persistente queda para Gate 6D; su conexión operacional queda
para Gates 6B, 6D y 6F. Support-assisted session y break-glass no crean
sesiones, roles, endpoints, cookies, UI ni almacenamiento en Gate 6A.

## Estado de actividad

- Contador oficial: `5/22`; 17 pendientes.
- Actividad 6: `IN_PROGRESS_INTERNAL`; no concluida.
- Gate 6A: `CLOSED_INTERNAL_READY_FOR_GATE_6B_REVIEW`.
- Gate 6B: no iniciado/bloqueado hasta revisión.
- Actividad 7: bloqueada.

## No conexión

No se modificaron `api/**`, `public/**`, routers, entrypoints, perfiles,
Agenda, Clinical, Suscripciones, Stripe, webhooks, identidad productiva, 8091,
schemas, migraciones, SQL, AWS, UI, JavaScript, CSS, HTML o configuración.
No se ejecutaron writes, exportaciones, anonimización, eliminación, soporte
asistido, break-glass ni Gate 6B.
