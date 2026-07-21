# PRODUCT-IMPLEMENTATION — PG-08 Gate 6D: audit trail transversal

## Resultado

`PASS_GATE_6D_TRANSVERSAL_AUDIT_TRAIL_READY_FOR_REVIEW`

Contrato: `MXMED_PG08_TRANSVERSAL_APPEND_ONLY_AUDIT_TRAIL_GATE_6D_V1`
Clasificación: UI-0 — `PERSISTENCE_READY_NO_RUNTIME_WIRING`

Gate 6D implementa fundamentos puros y preparados para persistencia PDO del
audit trail definido en DEC-012E. No ejecuta SQL, no crea tablas reales y no
conecta el adapter a runtime.

## Arquitectura

- Contratos: `modules/platform/contracts/AuditEventEnvelope.php` y
  `AuditIntegrityReason.php`.
- Servicios puros: `AuditEventSanitizer`, `AuditEventCanonicalizer`,
  `AuditIntegrityChain` y `AuditIntegrityVerifier`.
- Repositorio: `Platform\Repositories\AuditEventRepository` y
  `PdoAuditEventRepository`.
- Adapter: `PdoAuditTrailAdapter`, compatible con `AuditTrailPort` sin cambiar
  su firma pública.
- Migración no ejecutada:
  `modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql`.

No se modificaron `AuthorizationBoundary` ni los adapters existentes de Gates
6A/6B.

## Evento, minimización y correlación

`AuditEventEnvelope` conserva sólo versión, evento, stream, secuencia, UTC,
acción, riesgo, resultado, reason code, referencias opacas de actor real y
efectivo, sujeto afectado, correlation/request/case, recurso, metadata permitida
y hashes. No serializa el `AuditEventReference` completo.

La allow-list de metadata contiene únicamente `resource_type`,
`resource_reference`, `decision`, `reason_code`, `authorization_plane`,
`case_reference`, `source_reference`, `policy_reference`, `disposition_mode` y
`audit_category`. Keys sensibles, estructuras, saltos de línea y texto clínico
son rechazados sin truncamiento silencioso.

## Canonicalización e integridad

La canonicalización usa JSON determinista, orden fijo, metadata ordenada,
Unicode/UTC estable y no usa serialización PHP. `event_id` es SHA-256 estable
para la huella lógica minimizada. `AuditIntegrityChain` enlaza cada evento con
`SHA-256(previous_hash + canonical_event_content)` desde el génesis explícito
versionado `audit-genesis-v1`.

`AuditIntegrityVerifier` detecta modificación, metadata alterada, actor u
outcome alterado, secuencia duplicada/faltante, reordenamiento, eliminación
intermedia, stream distinto, versión no soportada y hash previo incorrecto.
Esto es `tamper-evident hash chaining`, no firma criptográfica externa ni
non-repudiation; KMS/firma externa queda fuera de Gate 6D.

## Persistencia append-only

El repositorio PDO usa prepared statements, transacción, lectura bloqueada del
último evento, secuencia siguiente, hash previo e INSERT atómico. Sólo expone
`findByEventId`, `latest` e `insert`; no existen métodos de mutación posterior,
soft-delete, purga o fallback a archivos/error log. El esquema versionado añade
índices de stream/secuencia, event/correlation/request/occurred y triggers que
rechazan modificaciones o eliminaciones.

Duplicados idénticos se aceptan idempotentemente; un mismo `event_id` con
huella canónica incompatible se rechaza. Conflictos transaccionales y base no
disponible retornan `unavailable`; sanitización o integridad inválida retorna
`rejected`.

## Fail-closed y retención

`AuditTrailPort`, `AuditAvailability` y `AuditWriteResult` se preservan. La
frontera de autorización de Gate 6B sigue siendo la autoridad: R2/R3 sin
auditoría disponible, rechazada o ausente deniegan. Gate 6D no conecta un
fallback in-memory al runtime.

La retención se mantiene `retention_unresolved`: no hay periodo inventado,
TTL, purga, job, archivado, eliminación ni dependencia del estado comercial.

## No conexión

Runtime wiring, endpoint wiring, visor/exportación, UI, AWS, tablas reales,
eventos reales y writes reales: `0`. La migración es sólo un artefacto
versionado y no se ejecutó.

## Estado

- Contador oficial: `5/22`; pendientes: `17`.
- Gate 6A, 6B y 6C preservados.
- Gate 6E no iniciado.
- Actividad 7 bloqueada.
