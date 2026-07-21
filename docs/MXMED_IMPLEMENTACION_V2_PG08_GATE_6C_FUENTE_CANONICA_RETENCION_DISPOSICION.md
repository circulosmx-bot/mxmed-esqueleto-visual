# PRODUCT-IMPLEMENTATION — PG-08 Gate 6C: fuente canónica, retención y disposición

## Resultado

`PASS_GATE_6C_CANONICAL_SOURCE_RETENTION_DISPOSITION_READY_FOR_REVIEW`

Contrato: `MXMED_PG08_CANONICAL_SOURCE_RETENTION_DISPOSITION_SAFETY_GATE_6C_V1`
Clasificación: UI-0 — `PURE_DATA_GOVERNANCE_SERVICES_NO_RUNTIME_EXECUTION`

Gate 6C traduce DEC-012B, DEC-012C y DEC-012D a servicios puros y
deterministas. Todo estado es in-memory de prueba; no se leen tablas, schemas
ni datos reales y no se ejecuta ninguna disposición.

## Baseline y arquitectura

- Programa: `program/mxmed-product-refinement-22-v2`
- Programa HEAD: `be9cd4f067096a7798d1ea6941ec291981039823`
- Gate 6B HEAD: `24f4a9101a9382fc9d8bec4512365b1871369855`
- Rama: `feature/mxmed-apis-data-permissions-privacy-foundations-v2`
- Servicios: `modules/platform/services/`
- Contratos adicionales: `modules/platform/contracts/`
- Pruebas: `modules/platform/tests/Gate6CCanonicalRetentionDispositionTest.php`

Se reutilizan `CanonicalSourceRecord`, `CanonicalSourceRegistry`,
`SourceClassification`, `RetentionPolicy`, `RetentionState`,
`DispositionAction`, `DispositionResolution` y `RiskLevel` de Gate 6A.

## Autoridad canónica

`CanonicalSourceAuthority` registra definiciones tipadas en memoria, valida
conflictos, resuelve lectura/escritura y produce snapshots sanitizados.

- Una sola `canonical_write` activa por dominio/entidad.
- `canonical_read`, projection, migration, legacy, draft y fixture no autorizan
  writes ordinarios.
- `unresolved`, ausencia de autoridad, conflicto y dual-write son fail-closed.
- No hay fallback silencioso.

## Registro y snapshot

Los snapshots contienen únicamente dominio, entidad, clasificación, autoridades,
referencia lógica, migración, reconciliación, rollback y estado. No contienen
filas, SQL, conexiones, credenciales, payloads ni datos clínicos.

## Lecturas sin efectos

`ReadOperationContract` rechaza creación de schema, migración, seed, update,
delete, reconciliación y dual-write. El endpoint de Catálogo no fue modificado;
su contención queda para Gate 6F.

## Retención

`RetentionPolicyRegistry` mantiene políticas tipadas por dominio/clase,
incluyendo `retention_unresolved`, legal hold, estado actual, estado de archivo,
owner, aprobación, implementación, `clinical_data` y
`commercial_state_dependency`.

- No se inventan periodos.
- Unresolved bloquea delete irreversible automático.
- Legal hold bloquea anonymize/delete.
- Los datos clínicos no dependen del estado comercial.
- Una política ausente es fail-closed.

## Disposición

`DispositionRequest` y `DispositionPlanner` modelan sujeto, acción, finalidad,
autorización, fuente, política, idempotencia, simulación, aprobaciones,
auditoría, conciliación, rollback y expiración.

- Delete, anonymize y `export_mass` requieren R3.
- `anonymization_unresolved` bloquea anonimización.
- Exportación masiva requiere aprobación y expiración.
- Legal hold y ausencia de auditoría bloquean acciones irreversibles.
- Active → deleted para datos sensibles está bloqueado.
- Todos los planes tienen `executable=false`.
- No se generan archivos, enlaces, descargas ni datos.

## Idempotencia, conciliación y rollback

Las referencias de solicitud e idempotencia son sanitizadas y estables. Los
planes expresan conciliación, rollback, pasos requeridos, bloqueos y revisión
posterior, sin almacenar claves ni ejecutar operaciones.

## Estado

- Contador oficial: `5/22`; 17 pendientes.
- Actividad 6: `IN_PROGRESS_INTERNAL`; no concluida.
- Gate 6C: `CLOSED_INTERNAL_READY_FOR_GATE_6D_REVIEW`.
- Gate 6D: `NOT_STARTED`.
- Actividad 7: bloqueada.

## No conexión

No se modificaron APIs, routers, middleware, Catálogo, Pacientes, Perfiles,
Agenda, Clinical, Suscripciones, Stripe, webhooks, identidad HTTP, 8091, SQL,
migraciones, AWS, UI ni runtime. No se ejecutaron exportaciones,
anonimización, eliminación, archivado, restauración, purga ni reconciliación de
filas.
