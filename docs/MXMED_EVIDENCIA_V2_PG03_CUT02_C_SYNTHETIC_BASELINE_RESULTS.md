# CUT-02C — Resultados agregados del baseline sintético

## Identidad y alcance

Actividad 19/22. Identificador `QA-ARCH/MXMed-PG03-CUT02-C-Synthetic-Baseline-Evidence-Package-01`. Parent `d765b3270b8085f1f6bec1f321932d82b7dd0f77`; checkpoint 18 `checkpoint/mxmed-product-refinement-v2-activity18`, objeto anotado `77cf0686933c8c876bb6644e4d74fb5ef79f7886`.

Este documento registra exclusivamente evidencia agregada de fixtures sintéticos ejecutados por el harness R0 offline. No contiene resultados raw individualizados, tráfico, requests, datos reales ni inferencias productivas.

```text
SURFACES=5/5
SCENARIO_CATEGORIES=8/8
SYNTHETIC_FIXTURES=40/40
EXECUTIONS_PER_FIXTURE=2
TOTAL_OFFLINE_EXECUTIONS=80/80
DETERMINISTIC_RESULTS=40/40
PRIVACY_VALIDATION=PASS
INTEGRITY_VALIDATION=PASS
REAL_TRAFFIC=0
```

## Matriz agregada

Leyenda: `P` = PASS, `F` = FAIL cerrado por hard stop, `R` = REJECTED controlado.

| Superficie | nominal | boundary | invalid_closed | privacy_rejection | hard_stop | legacy_invariant | outcome_difference_without_response_mutation | deterministic_repeat | Total |
|---|---:|---:|---:|---:|---:|---:|---:|---:|---:|
| `canonical_actor_authority` | P | P | R | F | F | P | P | P | 8 |
| `canonical_schedule_read` | P | P | R | F | F | P | P | P | 8 |
| `canonical_availability_compare` | P | P | R | F | F | P | P | P | 8 |
| `canonical_appointment_lifecycle` | P | P | R | F | F | P | P | P | 8 |
| `canonical_patient_identity` | P | P | R | F | F | P | P | P | 8 |
| Total | 5 P | 5 P | 5 R | 5 F | 5 F | 5 P | 5 P | 5 P | 40 |

```text
PASS=25/40
FAIL=10/40
REJECTED=5/40
DUPLICATE_COMBINATIONS=0
MISSING_COMBINATIONS=0
EXTRA_COMBINATIONS=0
```

## Hard stops observados

La categoría `privacy_rejection` produjo cinco veces `PII_OR_CLINICAL_DATA_DETECTED`. La categoría `hard_stop` eligió una señal sintética determinista por superficie:

| Superficie | Hard stop | Conteo |
|---|---|---:|
| `canonical_actor_authority` | `AUTHORITY_AUDIT_UNAVAILABLE` | 1 |
| `canonical_schedule_read` | `CANONICAL_WRITE_ATTEMPTED` | 1 |
| `canonical_availability_compare` | `NEW_DB_CONNECTION_ATTEMPTED` | 1 |
| `canonical_appointment_lifecycle` | `CLINICAL_REQUEST_ATTEMPTED` | 1 |
| `canonical_patient_identity` | `SCOPE_LEAKAGE_DETECTED` | 1 |
| Todas, privacidad | `PII_OR_CLINICAL_DATA_DETECTED` | 5 |

Los nombres describen entradas sintéticas al evaluador; no representan efectos ejecutados. Cada FAIL conservó destino de safe return `R0` / `disabled`.

## Invariancia y determinismo

Los 25 casos PASS conservaron status, headers normalizados y payload canonicalizado; `legacy_invariant=true`. Los cinco casos `outcome_difference_without_response_mutation` conservaron la respuesta y diferenciaron únicamente outcomes diagnósticos. Los diez hard stops produjeron `legacy_invariant=false` de forma cerrada, y los cinco fixtures inválidos fueron transformados en `REJECTED` sin evidencia raw.

Cada fixture se evaluó exactamente dos veces dentro de la prueba principal. Las dos serializaciones sanitizadas coincidieron en `40/40`.

## Integridad agregada

```text
CATALOG_SHA256=5d7934acbdcbb3dd45dfaa2442de467a4e40beb9828103c84acb55220e5d9a96
RESULTS_SHA256=b7c5f323cf38e02d970e33673bb0f943c16c23363466ea02e9f50a0f59c861f7
SCHEMA_FIELDS=18/18
```

Los digests cubren el catálogo ordenado y las 80 proyecciones sanitizadas en memoria. No son identificadores de personas, citas, médicos o pacientes.

## Límites

Los fixtures son mínimos, cerrados y sintéticos. No representan distribución, volumen, latencia, concurrencia, fallos de infraestructura, datos productivos o comportamiento de usuarios. Este resultado no permite fijar sampling, ventanas, percentiles, SLO, error budgets, thresholds, sink, retención, owners, on-call, RTO o RPO; tampoco autoriza baseline real, revisión técnica, CUT-02, R1 o R2.

```text
SAMPLING=0
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
SANITIZED_EVIDENCE_TECHNICAL_REVIEW_AUTHORIZED=false
```
