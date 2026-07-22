# MXMed V2 · PG03 · Gate 8G · Persistencia y migraciones declarativas

## Resultado y clasificación

Resultado: `PASS_ACTIVITY_8_GATE_8G_PERSISTENCE_MIGRATION_IMPLEMENTED`.

Clasificación: UI-0. Gate 8G agrega únicamente contratos PHP puros, SQL declarativo no ejecutado, una prueba estática y documentación. No cambia frontend, rutas, APIs, controladores ni comportamiento del runtime.

## Baseline y fuentes vinculantes

La implementación parte del HEAD Gate 8F postvalidado `b807f58585966936ed62c29c59025734d7295b0f`, sobre la cadena protegida `b807f585 → f4b8f068 → 64c698aa → 3877e260`. El preflight Gate 8G pasó su manifiesto completo y el scope review original concluyó `PASS_ACTIVITY_8_GATE_8G_SCOPE_REVIEW_READY_FOR_IMPLEMENTATION`.

La primera implementación fue detenida correctamente por `BLOCKED_ACTIVITY_8_GATE_8G_PERSISTENCE_MIGRATION_IMPLEMENTATION`. `CORR-001` fijó la ruta canónica de este documento como `docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_MIGRACIONES.md` y descartó `docs/MXMED_IMPLEMENTACION_V2_PG03_GATE_8G_PERSISTENCIA_IDENTIDAD.md`. `CORR-002` completó el inventario del Manifest y los contratos de preservación del runtime, rollout y ejecución.

La precedencia contractual aplicada fue: scope review correction, scope review original, preflight Gate 8G y contratos versionados Gates 8A–8F.

## Alcance versionado

El cambio contiene exactamente 17 archivos: 16 nuevos y un archivo modificado. Son ocho SQL, siete PHP —incluida la prueba— y dos documentos —incluido Plan Maestro—.

Los seis contratos son `PatientIdentityPersistencePolicy`, `PatientIdentityPersistenceManifest`, `PatientIdentityRetentionPolicy`, `PatientIdentityBackfillPlan`, `PatientIdentityRolloutPolicy` y la interfaz `PatientIdentityPersistencePort`. La prueba es `modules/patients/tests/Gate8GPatientIdentityPersistenceMigrationTest.php`; el único archivo existente modificado es `docs/PLAN_MAESTRO_MXMED.md`, donde PP-310 se agrega una vez.

## Contrato de base de datos

Las cuatro tablas declaradas son:

1. `patient_identity_resolutions` para idempotencia y resultado durable.
2. `patient_identity_audit_events` para auditoría específica append-only.
3. `patient_identity_legacy_links` para el vínculo histórico, sin merge.
4. `patient_identity_backfill_checkpoints` para progreso reanudable sin payload de pacientes.

Los ocho archivos SQL son cuatro pares create/rollback. El orden ascendente es resolutions, audit events, legacy links y backfill checkpoints; el orden descendente es 04, 03, 02, 01. Todos son declarativos, aditivos y no ejecutados. Usan `CREATE TABLE IF NOT EXISTS`, `DROP TABLE IF EXISTS`, InnoDB, utf8mb4, `utf8mb4_unicode_ci` y `DATETIME(6)`. No contienen foreign keys, cascadas, `ALTER TABLE`, seed data ni cambios a tablas existentes.

`patients_patients.patient_id` continúa como fuente canónica. La existencia de un paciente será validada por un adaptador futuro; Gate 8G no implementa ni conecta ese adaptador.

## Auditoría, idempotencia y concurrencia

La auditoría usa una tabla específica porque el envelope protegido de `platform_audit_events` no representa sin cambios los outcomes, tiers y flags de identidad de Gate 8F. La tabla conserva únicamente referencias opacas, digests, códigos cerrados, flags y timestamps. Su PK es stream/sequence, y event ID y event hash son únicos. Los triggers `reject_patient_identity_audit_events_update` y `reject_patient_identity_audit_events_delete` rechazan UPDATE y DELETE; el rollback elimina primero ambos triggers y después la tabla.

`request_fingerprint` es la clave de idempotencia; `operation_reference` es única y `legacy_lock_digest` es generado, nullable y único. El contrato exige comparar `candidate_set_digest`, reproducir resultados completados y usar los estados processing, completed y failed. El orden futuro de locks es `resolution_fingerprint`, `legacy_reference`, `candidate_set`, `audit_stream`. Resolución y auditoría se escribirán en la misma transacción futura; esta Gate no toma locks ni escribe datos.

## Retención, backfill y rollout

Auditoría y links legacy son durables sin purga automática. La retención de resoluciones y checkpoints permanece `UNRESOLVED_PENDING_POLICY_APPROVAL`; no existe TTL numérico. Purge, archive y delete automáticos son false.

El backfill es un plan declarativo de 14 etapas: preflight, external snapshot backup, shadow scan, batched read, trusted adapter digest, candidate resolution, no-match partition, review queue partition, idempotency check, append audit, persist checkpoint, reconciliation, emit metrics y abort/rollback. Es determinista, reanudable, idempotente y limitado por lotes; no se ejecuta y declara `legacyRuntimePreserved=true`.

El rollout declara R0 disabled, R1 shadow, R2 audit_only, R3 read_compare y R4 enabled. El estado permanece R0/disabled. `activationAllowed=false`, `writesEnabled=false`, `backfillEnabled=false` y `execution=false`; R1–R4 no fueron activados.

Las cinco clases readonly declaran `executesOperations()=false` y serializan `execution=false`. `PatientIdentityPersistencePort` es sólo una interfaz abstracta, sin PDO, SQL, adaptador, filesystem, red ni wiring de runtime.

## Privacidad, pureza e impacto productivo

Las migraciones no almacenan nombre, nacimiento, teléfono, email, domicilio, raw legacy key, payload de paciente ni documento Clinical. Los checkpoints no contienen registros completos ni listas de pacientes. Los legacy links no representan merge.

SQL ejecutado: 0. Conexiones DB: 0. Migraciones aplicadas: 0. Datos reales escritos: 0. Pacientes creados: 0. Links reales creados: 0. Merges: 0. Contactos modificados: 0. Clinical: 0. Runtime: 0. Rutas: 0. AWS: 0.

Gate 8F y sus 14 archivos permanecen intactos. PP-309 permanece byte-semánticamente estable: 4869 bytes normalizados y SHA-256 `2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70`. PP-310 está presente una vez y su SHA-256 normalizado es `5f7de1da73097783c1495feb13f07516ccfa150811b967d080c4f31960697b60`.

## Pruebas, simulación y retorno seguro

La prueba Gate 8G valida estáticamente contratos, tablas, columnas, tipos, defaults, llaves, índices, create/rollback, triggers, privacidad, idempotencia, locks, retención, backfill, rollout y protección del parent, sin abrir una base de datos ni ejecutar SQL. Las regresiones Gates 8A–8G y las regresiones transversales deben pasar.

La simulación futura agrega temporalmente `PP-311 — Simulación temporal posterior a Gate 8G` en un worktree detached, ejecuta Gates 8C–8G y comprueba que el hash de PP-310 permanece estable. El Plan real no contiene PP-311.

El safe return Git consiste en `git revert --no-commit <gate8g_commit>` dentro de un worktree detached y debe reconstruir exactamente el árbol `b807f58585966936ed62c29c59025734d7295b0f^{tree}` sin crear commit. Los rollback SQL sólo son artefactos declarativos y no se ejecutan durante el retorno Git.

## Estado final

- Gate 8F: `POSTVALIDATED_COMPLETE_WITH_OPAQUE_METADATA_REFERENCES_AND_MERGE_DISABLED`.
- Gate 8G: `IMPLEMENTED_READY_FOR_POSTVALIDATION`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador: `7/22`.
- Pendientes: `15`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.
