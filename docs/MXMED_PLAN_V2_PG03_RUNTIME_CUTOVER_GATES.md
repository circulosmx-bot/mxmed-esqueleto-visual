# MXMed V2 · PG-03 · Plan propuesto de gates de cutover runtime

## Estado y límite de autorización

Este plan es una propuesta técnica derivada de `AUDIT/MXMed-PG03-Runtime-Cutover-Readiness-01`. CUT-01–CUT-05 no están aprobados, no inician Actividad 10 y no autorizan código, SQL, conexiones DB, migraciones, backfill, cambios de ruta, OTP, citas, pacientes, merges, Clinical, UI, rollout ni AWS.

Readiness de auditoría: `NO_GO_BLOCKERS_PRESENT`. Readiness general: `NO_GO_LEGACY_BLOCKERS_PRESENT`. Hash PP-310: `c3c0339ad05b127b08288f3a026f2122f9af130061369db2c1a4c0c8d4a17459`. Hash PP-311: `b0513742662320879fb910595aaa9d4d431f4cf12b72414b6451da7335ae8b76`.

## Dependencias y secuencia

`CUT-01 → CUT-02 → CUT-03 → CUT-04 → CUT-05`. Ningún gate puede solaparse con el siguiente. CUT-03 puede preparar rehearsal después de CUT-01, pero no ejecutar producción y no habilita CUT-04 hasta reconciliar. Cada salida requiere QA, evidencia, aprobación directorial separada y safe return probado.

Dependencias transversales: Identity/sesión/membership, Gate 6B y audit trail, política de retención, proveedor OTP, ownership DBA/datos, observabilidad SRE, decisiones de scope/`__all__`, contrato Clinical y feature flags server-side.

## Matriz R0–R4

| Etapa | Modo | Reads | Writes | Datos/migración | Criterio de entrada | Criterio de salida | Safe return |
|---|---|---|---|---|---|---|---|
| R0 | disabled | legacy | legacy | ninguna Gate 8G | baseline + flags off | adapters compilables y auditables | permanecer legacy |
| R1 | shadow | canonical en sombra, respuesta legacy | legacy | ninguna | CUT-01 PASS + decisiones críticas | error/latencia dentro de budget | apagar flags shadow |
| R2 | audit_only | sombra + eventos sanitizados | legacy; canonical sin autoridad | schema/rehearsal sólo aprobado | R1 estable + audit sink | cobertura y reconciliación aprobadas | volver a R1/R0 |
| R3 | read_compare | dual read y comparación | legacy | migración/backfill ensayados | CUT-03 PASS | diferencias bajo umbral por scope | lectura legacy + detener backfill |
| R4 | enabled | canonical progresivo | canonical sólo por segmento aprobado | migración aplicada y reconciliada | CUT-04 PASS + decisión writes | QA/observabilidad/cierre | kill switch + legacy read + runbook DB |

## Feature flags candidatos

Son nombres candidatos, no API ni configuración aprobada. Todos: default `false`, owner nominal antes de CUT-01, evaluación server-side, scope mínimo profile+consultorio, sin PII en labels y deny on unknown.

| Flag | Owner propuesto | R0–R4 | Métrica / budget tentativo | Kill switch / rollback | Dependencia / datos |
|---|---|---|---|---|---|
| `canonical_actor_authority` | Identity/Agenda | off; shadow R1; enforce R4 | mismatch/deny/latency; budget por aprobar | volver a resolver legacy sólo en R1–R3 | sesión, membership, profile |
| `canonical_schedule_read` | Agenda | off; shadow; compare R3; on R4 | schedule/slot diff; 0 scope leakage | lectura legacy | schedule versionado |
| `canonical_availability_compare` | Agenda/SRE | off; R1–R3 compare; R4 optional | slot diff, holiday/override diff | apagar compare | sin datos paciente |
| `canonical_appointment_lifecycle` | Agenda | off; shadow R1–R3; writes R4 | transition diff/conflict/rollback | legacy write sólo si aprobado | citas/eventos |
| `canonical_public_agenda` | Agenda/Security | off; shadow; on R4 | OTP fail/replay/privacy/booking | deshabilitar flow canonical | contacto opaco |
| `canonical_patient_identity` | Patients | off; shadow/audit/read compare; on R4 | outcomes/ambiguity/review | resolver legacy | digests e IDs |
| `patient_identity_persistence` | Patients/DBA | off R0–R2; compare R3; on R4 | tx/audit/idempotency/latency | stop writes + read legacy | cuatro tablas 8G |
| `legacy_write_disable` | Arquitectura | false R0–R3; true sólo R4 segmentado | legacy attempts/denials | false tras rollback aprobado | rutas write |
| `shadow_audit` | Security/SRE | off R0; on R1–R4 | accepted/rejected audit, sink failure | apagar sombra; fail policy aprobada | metadata cerrada |
| `read_compare` | SRE | off; R3 on | equality/mismatch/latency | off | dual reads |
| `backfill` | Datos/DBA | off R0–R2; rehearsal CUT-03; producción sólo aprobación | throughput/lag/error/reconcile | stop/checkpoint/restore | snapshot, batches |

No se fijan cifras: error budgets y thresholds están pendientes de D-10 y deben aprobarse con baseline medido.

## Métricas, observabilidad y kill switches

Mínimos sin PII: total, allow/deny reason, claims mismatch, operator binding failure, schedule/slot diff, lifecycle transition diff, idempotency new/replay/conflict, slot claim conflict, OTP requested/verified/expired/locked/replay, identity outcome/review/ambiguous, audit append failure, rollback, latency buckets, backfill checkpoint lag y reconciliation delta.

Cada flag requiere dashboard, alertas, correlation/request IDs, owner/on-call, runbook, budget aprobado y switch independiente. Un breach, audit unavailable según riesgo, PII leak, aumento de doble reserva, mismatch de scope o corrupción de reconciliación activa hard stop y retorno a la etapa previa.

## Registro de decisiones requerido

Antes de gates posteriores deben resolverse D-01 a D-18 del documento de auditoría: grey/black flags; duration/gap/override; scope/backfill; `__all__`; retención de resoluciones/checkpoints; canal OTP y retries; observabilidad; thresholds; ventana/owner de migración/backfill; rollback; RTO/RPO; writes; merge; Clinical; y retiro localStorage. Ninguna está aprobada aquí.

## CUT-01 — Adapter and Feature Flag Readiness

- Objetivo: composition roots, adapters, interfaces, flags y observabilidad en R0; cero cutover.
- Prerrequisitos: aprobación separada; resolver D-02/D-03/D-04/D-07/D-08/D-09/D-13/D-17; threat model; inventario de schema sin mutar.
- Incluye: adapter `AgendaActorAuthorityResolver`; bindings de lectura schedule/availability/lifecycle/identity; implementación de ports sin activar; flags default off; eliminación planificada de DDL on-request; tests puros/integración aislada.
- Excluye: writes canonical, migraciones, DB productiva, backfill, route/UI behavior, OTP real, merge y Clinical.
- UI: UI-0.
- Métricas: cobertura adapter, claims mismatch, parity de modelos, latencia en harness.
- Rollback: revert único; flags siguen off.
- Decisiones: owners, error budgets y failure modes.
- PASS propuesto: todas las interfaces cableables en harness, flags off, DDL on-request bloqueado por diseño, no efectos productivos.
- Bloqueo siguiente: cualquier BLOCKER de autoridad, privacidad, DDL, flags u observabilidad.

## CUT-02 — Shadow and Audit-Only

- Objetivo: R1 shadow y luego R2 audit_only sin autoridad de escritura.
- Prerrequisitos: CUT-01 PASS; kill switches; dashboards; canal de auditoría sanitizado; D-09/D-10 aprobadas.
- Incluye: evaluación canonical paralela, comparación sin responder, auditoría cerrada, métricas y sampling aprobado.
- Excluye: canonical writes, respuesta desde canonical, migración/backfill productivo, OTP real nuevo y UI.
- UI: UI-0.
- Métricas: mismatch autoridad/scope/schedule/lifecycle/identity; audit failure; p95/p99 según baseline aprobado.
- Rollback: apagar flags a R0; descartar proyecciones shadow según política.
- Decisiones: thresholds, sampling, retención de shadow audit.
- PASS propuesto: ventana observada aprobada, cero PII, budgets cumplidos y reconciliación explicable.
- Bloqueo siguiente: unknowns de volumen/schema/retención o mismatch sobre umbral.

## CUT-03 — Migration and Backfill Rehearsal

- Objetivo: rehearsal reproducible con snapshots, lotes, checkpoints, reconcile y rollback; sin producción hasta aprobación.
- Prerrequisitos: CUT-02 estable; D-05/D-06/D-11/D-12/D-13/D-14; adapter persistence probado.
- Incluye: preflight, checksums/ledger, up/down en entorno aislado autorizado, dataset sintético o snapshot autorizado, batch resume, idempotency, abort/restore.
- Excluye: datos productivos sin aprobación, merge, deletes, Clinical y habilitación de rutas.
- UI: UI-0.
- Métricas: migration duration, rows attempted/succeeded/reviewed, checkpoint lag, reconcile delta, rollback time; cifras pendientes.
- Rollback: detener job, preservar checkpoint, restaurar snapshot, verificar ledger.
- Decisiones: ventana, owner, RTO/RPO si fuente aprobada.
- PASS propuesto: rehearsal repetible, checksum/rollback/reconcile en verde y evidence pack.
- Bloqueo siguiente: cualquier delta inexplicable o rollback no probado.

## CUT-04 — Read Compare and Route Cutover

- Objetivo: R3 dual-read/read_compare y cutover progresivo de lecturas.
- Prerrequisitos: CUT-03 PASS; segmentos/scopes aprobados; localStorage retirement plan; route matrix actualizada.
- Incluye: compare por profile/consultorio; schedule/availability/identity read model; selección progresiva; kill switch por segmento.
- Excluye: writes canonical generales, merge, Clinical y retiro irreversible legacy.
- UI: UI-0 salvo aprobación separada si cambia presentación; este plan no la concede.
- Métricas: equality, semantic diff, stale reads, latency, fallback rate, scope leakage.
- Rollback: responder desde legacy, apagar `read_compare`, conservar telemetry según retención.
- Decisiones: threshold final, prioridad backend/localStorage y segmentos.
- PASS propuesto: diferencias bajo umbral aprobado, cero leakage y rollback ejercitado.
- Bloqueo siguiente: writes sin D-15 o cualquier drift de datos.

## CUT-05 — Enabled Rollout and Closure

- Objetivo: R4 enabled progresivo, QA, observabilidad, safe return y cierre.
- Prerrequisitos: CUT-04 PASS; aprobación expresa R4/writes; migración productiva autorizada; support/on-call; postvalidación.
- Incluye: segmentos pequeños, canonical reads/writes autorizados, `legacy_write_disable` gradual, monitorización y retiro posterior separado.
- Excluye: patient merge salvo gate R3 independiente; cambio Clinical salvo aprobación específica; big-bang; force migration; UI no aprobada.
- UI: UI-0 para backend; cualquier UI requiere clasificación/contrato propio.
- Métricas: SLOs aprobados, conflicts, replay, audit, privacy incidents, rollback rate, data reconciliation.
- Rollback: kill switch inmediato, legacy read, detener canonical writes, reconcile y runbook DB; no auto-delete.
- Decisiones: D-15/D-16/D-17 y aceptación final.
- PASS propuesto: rollout completo dentro de budgets, QA/postvalidación, safe return probado y deudas con owner.
- Bloqueo de cierre: alertas abiertas, datos no reconciliados, ruta legacy sin retirement owner o decisión pendiente.

## Criterios globales de entrada y salida

Entrada de cualquier gate: parent exacto, branch/worktree limpios, bundle/checkpoint, alcance aprobado, no `REVERT_HEAD`, pruebas anteriores verdes, inventario de archivos, privacidad y plan de rollback. Salida: commit atómico reversible, tests/read-only regressions, diff check, evidence pack válido, upstream 0/0, safe-return dry-run y decisión de avance separada.

## Safe return por capa

- Código/config: flags off y revert atómico.
- R1/R2: dejar de evaluar canonical; no cambia respuesta legacy.
- R3: responder sólo legacy; detener compare.
- Migration/backfill: stop, checkpoint, reconcile y restore según runbook aprobado.
- R4: kill switch, bloquear nuevos writes canonical, retornar lectura legacy sólo si compatibilidad fue validada, reconciliar antes de reintentar.
- Clinical: no formar parte del cutover hasta D-17; no intentar compensación automática no aprobada.

## Qué no está autorizado todavía

No están autorizados CUT-01–CUT-05, Actividad 10, adapters, flags, runtime wiring, route cutover, cambios UI, DDL, conexiones DB, SQL, migraciones, datos, backfill, OTP, citas, pacientes, merge, Clinical, AWS ni rollout R1–R4. El estado efectivo permanece R0 disabled.
