# BE/MXMed-PG03-Activity08-Gate8A-CanonicalContracts-01

## Resultado

`PASS_ACTIVITY_8_GATE_8A_CANONICAL_CONTRACTS_IMPLEMENTED`

Gate 8A implementa contratos y value objects puros, deterministas y no conectados para DEC-013A–L. No implementa operaciones reales de Agenda, no modifica rutas, no cambia controladores/repositorios, no ejecuta SQL y no altera la UI.

## Resumen ejecutivo

Se creó `modules/agenda/contracts/` con una representación canónica de autoridad server-side, disponibilidad calculada, ciclo de estados de citas, idempotencia, política pública OTP, contactos/privacidad, identidad/duplicados, merge deshabilitado, migraciones, auditoría, retención y rollout. `DecisionContractRegistry` demuestra cobertura exacta de las doce decisiones aprobadas.

La prueba `modules/agenda/tests/Gate8ACanonicalContractsTest.php` verifica invariantes, fail-closed, separación de actores, separación Agenda appointment/Clinical encounter, transición inválida 409, replay seguro, merge no ejecutable, CURP opcional, ausencia de plazos concretos, retirement condition y rechazo de metadata sensible.

## Baseline

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- Worktree: `/Users/circulodigital/Documents/GitHub/mxmed-esqueleto-visual-activity08-v2`.
- HEAD inicial: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Programa: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Checkpoint base: `checkpoint/mxmed-product-refinement-v2-activity07`.
- Actividad 7: `INTEGRATED_AND_CHECKPOINTED`.
- Contador oficial: `7/22`.
- Actividad 8: `IN_PROGRESS_GATE_8A`.
- Actividad 9: `BLOCKED`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

## DEC-013A–L crosswalk

| Decisión | Artefacto | Cobertura |
|---|---|---|
| DEC-013A | `ActorAuthorityContract`, `ActorReference` | trusted server context, actor real/efectivo, cuenta, membership, role, scope, ownership, deny-by-default |
| DEC-013B | `ScheduleAvailabilityContract`, `ScheduleWindow` | profile, consultorio, timezone, ventanas, duración, gap, overrides, feriados, colisiones, vigencia; read model no editable |
| DEC-013C | `AppointmentLifecycleContract`, `AppointmentTransitionResult`, referencias separadas | estados observados, transiciones permitidas/409, Agenda appointment distinto de Clinical encounter |
| DEC-013D | `IdempotencyRecord`, `IdempotencyContract`, `IdempotencyEvaluation` | key, slot identity, fingerprint, replay original, conflicto 409 y una sola mutación efectiva |
| DEC-013E | `PublicOtpPolicy`, `OtpDecision` | hash-only, expiración, intentos, consumo, replay denial, rate limit, anti-enumeración, QA aislado y payload minimizado |
| DEC-013F | `ContactDescriptor` | categoría, procedencia, consentimiento, canal, visibilidad, masking, vigencia y revocación |
| DEC-013G | `PatientIdentityMatch` | exact/probable/no_match, evidencia, warning antes de crear, no auto-merge, CURP no obligatoria |
| DEC-013H | `PatientMergeContract` | dry-run/apply/undo representados, alias, snapshot, referencias, actor, R3, reautenticación; siempre disabled/no endpoint |
| DEC-013I | `MigrationContract` | versión, checksum, preflight, apply, verify, rollback/recovery y ledger; ejecución false |
| DEC-013J | `AuditEventContract` | actores, subject, action, reason, correlation/request, estados, result, metadata minimizada, append-only |
| DEC-013K | `RetentionContract` | source, projection, copia temporal, categoría, legal hold, dry-run, ejecución autorizada y blockers; plazo concreto null |
| DEC-013L | `RolloutContract` | shadow, dual-read, backfill reversible, flag server-side, métricas, hard-stop, rollback, owner y retiro obligatorio |

## Contratos creados

Todos los archivos nuevos están bajo `modules/agenda/contracts/`. Son clases finales, sin dependencias de Agenda controllers/repositories, sin acceso a base, red, sesión, variables globales o filesystem, y sólo validan valores recibidos por sus constructores. `DecisionContractRegistry::all()` contiene exactamente DEC-013A–L, sin duplicados.

## Invariantes

- Autoridad confiable sólo puede ser `server_context`; no existe factory de autoridad desde headers, query, body o fallback.
- Actor real y efectivo son referencias distintas y serializables sin credenciales.
- Disponibilidad es `calculated_read_model` y `editable_authority=false`.
- Los estados de Agenda no implican atención clínica; las referencias de cita y encounter tienen tipos distintos.
- Una transición no permitida devuelve razón `invalid_transition` y HTTP 409.
- Replay con misma key/fingerprint devuelve el resultado original sin nueva mutación; mismatch devuelve conflicto 409.
- OTP es hash-only, de un solo consumo, limitado y sin generación/envío.
- Contactos tienen visibilidad/masking/procedencia; no se incluyen valores reales.
- Coincidencia probable exige warning y nunca auto-merge; CURP no es requisito.
- Merge es siempre disabled, sin endpoint y sin ejecución aun para `apply`/`undo`.
- Migración sólo describe lifecycle; `executionAllowed=false`.
- Audit metadata rechaza claves sensibles y conserva actores real/efectivo.
- Retención no contiene plazos legales concretos y sólo permite dry-run cuando no hay autorización.
- Todo rollout temporal requiere condición de retiro explícita.

## Límites del Gate 8A

No se implementan locks, índices, repositorios, migradores, tablas, endpoints, autenticación, sesiones, autorización HTTP, pacientes, contactos reales, disponibilidad persistida, OTP, citas, merge, disposición ni UI. Gate 8B no está iniciado.

## Pruebas

Prueba nueva: `modules/agenda/tests/Gate8ACanonicalContractsTest.php` (`Gate8ACanonicalContractsTest PASS`). Regresiones read-only ejecutadas y PASS:

- `modules/platform/tests/Gate6BAuthorizationBoundaryTest.php`;
- `modules/platform/tests/Gate6FLegacyContainmentIntegrationTest.php`;
- `modules/identity/tests/IdentityModelContractTest.php`;
- `modules/subscriptions/tests/ExistingCapabilityAuthorityServiceTest.php`;
- `modules/subscriptions/tests/CurrentSubscriptionFeatureAccessReadModelTest.php`.

También pasó lint PHP de contratos/tests, validación de JSON de evidencia, `git diff --check`, HTTP 8091 y verificación de puertos.

## Seguridad y privacidad

No se guardan OTP, tokens, secretos, passwords ni payloads completos en `AuditEventContract`; las pruebas usan identificadores sintéticos. `PatientMergeContract` mantiene riesgo R3 y reautenticación requerida. No hay plazos legales concretos de retención. Cita de Agenda y encuentro clínico continúan siendo entidades distintas.

## No runtime wiring

No se modificaron `api/agenda/**`, controllers, repositories, helpers, rutas, configuración, UI, JavaScript, CSS, HTML, SQL, migraciones, AWS ni el servidor oficial 8091. No se agregó autoload global. Los contratos sólo son cargables directamente por la prueba.

## No SQL

No se creó ni ejecutó SQL, DDL, seed o migración. El contrato de migración es declarativo y tiene ejecución deshabilitada.

## Rollback

El rollback de Gate 8A es eliminar/revertir el commit único de contratos/tests/documentación; no requiere operación de datos ni infraestructura. No se realizó rollback ni integración.

## Evidencia

`/tmp/mxmed-activity08-gate8a-canonical-contracts-v2/` contiene exactamente 9 JSON y 3 textos, sin PII, secretos, OTP, tokens, credenciales ni datos reales.

## Git

Se requiere un único commit sobre el programa con mensaje `feat(agenda): define contratos canonicos PG03 gate 8A`, push normal, upstream 0/0 y worktree limpio. No se hace amend, rebase, squash, force push, merge, cherry-pick ni checkpoint.

## Estado del programa

- Gate 8A: completo.
- Gate 8B: no iniciado.
- Actividad 8: `IN_PROGRESS_GATE_8A_COMPLETE`.
- Actividad 9: `BLOCKED`.
- Contador oficial: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar.
No crear checkpoint.
No iniciar Gate 8B.
No iniciar Actividad 9.
