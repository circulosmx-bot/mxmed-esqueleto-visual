# CUT-02D — Revisión técnica de evidencia sintética

## Identidad

- Fecha: 2026-07-23.
- Actividad: 20/22.
- Identificador: `ARCH-QA/MXMed-PG03-CUT02-D-Synthetic-Evidence-Technical-Review-Sampling-Proposal-01`.
- Clasificación: UI-0.
- Naturaleza: revisión técnica documental, sin aprobación ni impacto runtime.

## Baseline y checkpoint

- Parent: `d63bb02544cd00807ca931456991e692f3115f6f`.
- Checkpoint 19: `checkpoint/mxmed-product-refinement-v2-activity19`.
- Objeto anotado: `f88f5396a6bedf50c26b17c9c843165cb25e9afb`.
- Rama: `architecture/mxmed-pg03-cut02d-technical-review-sampling-proposal-v2`.
- Programa integrado revisado: Actividad 19 cerrada e integrada.

## Fuentes revisadas

La revisión se limita a estas fuentes versionadas:

1. `docs/MXMED_ARQUITECTURA_V2_PG03_CUT02_B_BASELINE_COLLECTION_PLAN.md`
2. `docs/MXMED_EVIDENCIA_V2_PG03_CUT02_B_SANITIZED_BASELINE_SCHEMA.md`
3. `tests/fixtures/cut02c-synthetic-baseline/catalog.php`
4. `modules/platform/tests/Cut02CSyntheticBaselineExecutionTest.php`
5. `modules/platform/tests/Cut02CSanitizedEvidencePrivacyTest.php`
6. `modules/platform/tests/Cut02CEvidenceIntegrityTest.php`
7. `docs/MXMED_EVIDENCIA_V2_PG03_CUT02_C_SYNTHETIC_BASELINE_RESULTS.md`
8. `docs/MXMED_REVISION_V2_PG03_CUT02_C_PRIVACY_INTEGRITY.md`
9. `docs/MXMED_IMPLEMENTACION_V2_PG03_CUT02_C_SYNTHETIC_BASELINE_PACKAGE.md`

No se emplean fuentes externas ni se extrapolan parámetros productivos.

## Trazabilidad

| Requisito CUT-02B | Implementación CUT-02C | Evidencia revisada | Conclusión |
|---|---|---|---|
| Catálogo sintético cerrado | Catálogo estable de fixtures | Matriz completa y hash agregado | Conforme para evidencia offline |
| Schema sanitizado allowlisted | Proyección exacta de dieciocho campos | Pruebas de schema e integridad | Conforme para evidencia offline |
| Privacidad recursiva | Rechazo de key sintética prohibida | Denylist y ausencia de filtración | Conforme dentro del universo sintético |
| Determinismo | Repetición local por fixture | Serializaciones coincidentes | Conforme |
| Safe return R0 | Catálogo existente de hard stops | Destino R0 disabled | Conforme |
| Límites de representatividad | Exclusiones documentadas | Sin tráfico ni datos reales | No demuestra producción |

La cadena plan → catálogo → ejecución → sanitización → privacidad → integridad → resultados → implementación es coherente y conserva los límites declarados.

## Resumen de catálogo

```text
ELIGIBLE_SURFACES=5/5
SCENARIO_CATEGORIES=8/8
SYNTHETIC_FIXTURES=40/40
CATALOG_SHA256=5d7934acbdcbb3dd45dfaa2442de467a4e40beb9828103c84acb55220e5d9a96
```

Las superficies, en orden candidato no efectivo, son:

1. `canonical_actor_authority`
2. `canonical_schedule_read`
3. `canonical_availability_compare`
4. `canonical_appointment_lifecycle`
5. `canonical_patient_identity`

## Resumen de resultados

```text
TOTAL_OFFLINE_EXECUTIONS=80/80
DETERMINISTIC_RESULTS=40/40
PASS_RESULTS=25/40
FAIL_RESULTS=10/40
REJECTED_RESULTS=5/40
RESULTS_SHA256=b7c5f323cf38e02d970e33673bb0f943c16c23363466ea02e9f50a0f59c861f7
```

Los FAIL y REJECTED son respuestas controladas del evaluador sintético; no representan fallos observados en producción.

## Privacidad

```text
PRIVACY_VALIDATION=PASS
PROHIBITED_EVIDENCE_TYPES=20/20
SCHEMA_FIELDS=18/18
```

La evidencia sanitizada excluye payload, headers, fixture, side effects, stacks, PII e identificadores reales. Las referencias variables permanecen bajo el patrón opaco `test:`. Esta validación sólo cubre fixtures sintéticos y no aprueba privacidad para tráfico real.

## Integridad y determinismo

La unicidad de IDs y combinaciones, el orden estable, el schema cerrado y los hashes agregados se verifican sin faltantes, extras o duplicados. La repetición produce resultados serializados iguales para cada fixture. Los hashes prueban estabilidad del paquete revisado, no representatividad productiva.

## Hard stops y safe return

Se reutiliza sin cambios el catálogo CUT-02A:

```text
HARD_STOPS_REUSED=15/15
TARGET_STAGE=R0
TARGET_MODE=disabled
LEGACY_CONTINUES=true
CANONICAL_RESPONSE_ALLOWED=false
CANONICAL_WRITE_ALLOWED=false
SQL_ROLLBACK_REQUIRED=false
```

No se crean thresholds ni reglas numéricas. El retorno seguro mantiene legacy y no autoriza evaluación, respuesta o escritura canónica efectiva.

## Cobertura, sesgo y exclusiones

La cobertura es declarativa sobre cinco superficies y ocho categorías sintéticas. El catálogo está sesgado intencionalmente hacia entradas mínimas, cerradas, estables y deterministas.

Quedan excluidos tráfico, requests y datos reales; distribución productiva; latencia, carga, concurrencia, capacidad y disponibilidad reales; comportamiento de proveedores; retención; observabilidad y operación on-call reales; privacidad de tráfico real; recuperación real; DB, SQL, DDL, persistencia, backfill, OTP, Clinical, red, AWS, métricas y auditoría real.

## Riesgos residuales y límites de representatividad

La evidencia no demuestra:

- distribución productiva;
- latencia real, p95 o p99;
- carga, concurrencia o capacidad;
- disponibilidad o error budget;
- tasa de fallos productiva;
- comportamiento de proveedores;
- retención;
- observabilidad real;
- operación on-call;
- privacidad de tráfico real;
- recuperación real;
- RTO o RPO.

Persisten riesgos de selección de muestra, datos no representados, interacción entre superficies, variaciones de infraestructura, degradaciones operativas, control de acceso a evidencia futura y respuesta humana ante hard stops.

## Dependencia de baseline real

Un baseline real, aprobado por separado y sujeto a privacidad y controles operativos, es requisito previo para cualquier aprobación numérica. Esta revisión no autoriza recolectarlo ni usarlo.

```text
sampling_rate=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
observation_window=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
minimum_volume=UNRESOLVED_PENDING_DIRECTOR_APPROVAL
p95_threshold=UNRESOLVED_PENDING_REAL_BASELINE
p99_threshold=UNRESOLVED_PENDING_REAL_BASELINE
error_budget=UNRESOLVED_PENDING_REAL_BASELINE
latency_budget=UNRESOLVED_PENDING_REAL_BASELINE
```

## Recomendación técnica

El paquete es aceptable únicamente como evidencia sintética offline para formular propuestas posteriores. No habilita sampling, ventana de observación, baseline real, R1, R2 ni producción.

```text
SYNTHETIC_PACKAGE_ACCEPTED_AS_OFFLINE_EVIDENCE=true
PRODUCTION_REPRESENTATIVENESS_PROVEN=false
REAL_BASELINE_REQUIRED_BEFORE_NUMERIC_APPROVAL=true
TECHNICAL_RECOMMENDATION=GO_FOR_PARAMETER_PROPOSAL_ONLY
R1_READINESS=NO_GO_PARAMETERS_AND_OPERATIONAL_CONTROLS_PENDING
```

## Estado de autorización

```text
SAMPLING=0
SAMPLING_APPROVED=false
OBSERVATION_WINDOW_APPROVED=false
REAL_BASELINE_COLLECTION_AUTHORIZED=false
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
DECISION_RATIFICATION_AUTHORIZED=false
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```

## Cero efectos reales

```text
REAL_TRAFFIC=0
DATABASE_CONNECTIONS_OPENED=0
SQL_EXECUTED=0
DDL_EXECUTED=0
PERSISTENCE_WRITES=0
BACKFILL_EXECUTED=0
REAL_OTP_SENT=0
CLINICAL_REQUESTS_EXECUTED=0
NETWORK_CALLS=0
METRICS_EMITTED=0
AUDIT_EVENTS_WRITTEN=0
```
