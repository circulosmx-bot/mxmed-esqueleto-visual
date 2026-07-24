# CUT-02E — Condiciones de entrada R0→R1

## Identidad y propósito

Actividad 21/22. Identificador `GOV-ARCH/MXMed-PG03-CUT02-E-Director-Ratification-R0-Governance-Gate-01`. Este gate formaliza reglas de gobierno y condiciones futuras. No activa R0 runtime ni autoriza R1.

## Estado del gate

```text
R0_POLICY_APPROVED=true
R0_RUNTIME_ACTIVATION_APPROVED=false
R0_REAL_TRAFFIC_APPROVED=false
R0_REAL_BASELINE_APPROVED=false
R1_ENTRY_APPROVED=false
HARD_STOPS_REUSED=15/15
TARGET_STAGE=R0
TARGET_MODE=disabled
LEGACY_CONTINUES=true
CANONICAL_RESPONSE_ALLOWED=false
CANONICAL_WRITE_ALLOWED=false
SQL_ROLLBACK_REQUIRED=false
```

La aprobación de policy sólo hace vinculantes los límites de gobierno. La ejecución permanece deshabilitada.

## Condiciones pendientes para R1

```text
AUDIT_SINK_SELECTED=false
AUDIT_SINK_CONFIGURED=false
DASHBOARD_IMPLEMENTED=false
ALERTING_IMPLEMENTED=false
OWNER_APPROVED=false
ON_CALL_APPROVED=false
RETENTION_APPROVED=false
PRIVACY_REVIEW_FOR_REAL_TRAFFIC_APPROVED=false
REAL_BASELINE_COLLECTION_AUTHORIZED=false
NUMERIC_PARAMETERS_APPROVED=false
R1_ACTIVATION_AUTHORIZED=false
```

Cada condición requiere una autorización futura y separada. Esta actividad no selecciona personas, equipos, proveedores, plataformas, sink o retención.

## Parámetros sin resolver

```text
sampling_rate=UNRESOLVED_PENDING_FUTURE_OPERATIONAL_APPROVAL
observation_window=UNRESOLVED_PENDING_FUTURE_OPERATIONAL_APPROVAL
minimum_volume=UNRESOLVED_PENDING_FUTURE_OPERATIONAL_APPROVAL
p95_threshold=UNRESOLVED_PENDING_REAL_BASELINE
p99_threshold=UNRESOLVED_PENDING_REAL_BASELINE
error_budget=UNRESOLVED_PENDING_REAL_BASELINE
latency_budget=UNRESOLVED_PENDING_REAL_BASELINE
UNRESOLVED_NUMERIC_PARAMETERS=7/7
```

No se fija tasa, duración, volumen, threshold, percentil o budget.

## Criterios de entrada futuros

Una revisión futura sólo puede considerar entrada a R1 cuando todas las condiciones operativas, de privacidad y evidencia estén completas; exista baseline real autorizado; los parámetros estén sustentados y aprobados; safe return esté verificado; y una autorización separada cambie explícitamente el estado de entrada.

## Criterios de rechazo y detención

El gate rechaza cualquier solicitud con parámetros sin resolver, control operativo ausente, privacidad real no aprobada, superficie fuera del catálogo, cambio legacy, respuesta/write canónico, side effect, PII o falta de retorno seguro. Cualquier hard stop conserva R0 disabled y legacy.

## Estado posterior

```text
SAMPLING=0
SAMPLING_APPROVED=false
OBSERVATION_WINDOW_APPROVED=false
REAL_BASELINE_COLLECTION_AUTHORIZED=false
PRIVACY_REVIEW_FOR_REAL_TRAFFIC_APPROVED=false
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
FEATURE_FLAGS=11/11
FEATURE_FLAGS_FALSE=11/11
RUNTIME_TRUE_FLAGS=0
ROLLOUT_STAGE=R0
ROLLOUT_MODE=disabled
```

Actividad 22 permanece bloqueada.
