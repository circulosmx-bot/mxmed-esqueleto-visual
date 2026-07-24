# CUT-02C — Revisión de privacidad e integridad

## Resultado

La revisión estática y offline del paquete sintético pasa sus controles de privacidad, determinismo e integridad. La revisión no equivale a aprobación técnica del paquete ni autoriza uso de información real.

```text
PRIVACY_VALIDATION=PASS
INTEGRITY_VALIDATION=PASS
PROHIBITED_EVIDENCE_TYPES=20/20
SANITIZED_EVIDENCE_TECHNICAL_REVIEW_AUTHORIZED=false
```

## Denylist cerrada

La inspección recursiva valida exactamente estas veinte categorías:

1. `patient_id`
2. `appointment_id`
3. `doctor_id`
4. `name`
5. `phone`
6. `email`
7. `address`
8. `birthdate`
9. `contact`
10. `otp`
11. `token`
12. `diagnosis`
13. `clinical_notes`
14. `real_request_bodies`
15. `sensitive_headers`
16. `cookies`
17. `credentials`
18. `stack_traces_with_data`
19. `production_payloads`
20. `real_query_strings`

La validación recorre keys y valores anidados de cada proyección sanitizada. El fixture de rechazo contiene únicamente la key de prueba requerida y el valor obvio `synthetic-forbidden-value`; ni la key como campo ni el valor llegan al resultado sanitizado.

## Privacidad

Los resultados contienen sólo los dieciocho campos allowlisted. No incluyen fixture, side effects, payload o headers raw, bodies, stacks, cookies, credenciales, texto clínico o strings libres. Las únicas referencias variables siguen el patrón opaco `test:[a-z0-9][a-z0-9._:-]*`.

Se comprobó ausencia de emails, teléfonos y UUIDs reales. Los outcomes, reason codes, versiones y hard stops pertenecen a catálogos cerrados. Los digests SHA-256 no se usan para reidentificación y se calculan únicamente sobre snapshots sintéticos o marcadores cerrados de rechazo.

```text
PII_PRESENT=false
RAW_PAYLOAD_PRESENT=false
RAW_HEADERS_PRESENT=false
REAL_UUID_PRESENT=false
REAL_DATA_PRESENT=false
```

## Integridad

El catálogo conserva orden estable de superficie y categoría, cuarenta IDs únicos y una sola combinación por celda. Las 80 ejecuciones generan dos proyecciones idénticas por fixture.

```text
SYNTHETIC_FIXTURES=40/40
TOTAL_OFFLINE_EXECUTIONS=80/80
DETERMINISTIC_RESULTS=40/40
SCHEMA_FIELDS=18/18
DUPLICATE_COMBINATIONS=0
MISSING_COMBINATIONS=0
EXTRA_COMBINATIONS=0
CATALOG_SHA256=5d7934acbdcbb3dd45dfaa2442de467a4e40beb9828103c84acb55220e5d9a96
RESULTS_SHA256=b7c5f323cf38e02d970e33673bb0f943c16c23363466ea02e9f50a0f59c861f7
```

La repetición del cálculo agregado produjo los mismos digests. Todo hard stop conserva safe return `R0` / `disabled`; cada rechazo cerrado se representa sin stack ni entrada raw.

## Cobertura, sesgo y exclusiones

La cobertura es declarativa: cinco superficies por ocho categorías. Comprueba rutas nominales, límites sintéticos válidos, cierre inválido, privacidad, hard stop, invariancia, diferencia de outcome sin mutación de respuesta y repetición determinista.

El catálogo está sesgado deliberadamente hacia entradas pequeñas, estables y cerradas. Excluye tráfico, requests, datos reales, distribución productiva, latencia, carga, concurrencia, red, DB, SQL, persistencia, backfill, OTP, Clinical, AWS, fallos de proveedores, métricas y auditoría real. Por ello no demuestra representatividad productiva ni permite inferir percentiles, porcentajes, duración, capacidad o riesgo residual.

Sampling, key/scope/exclusions, ventanas, p95, p99, SLO, error budgets, thresholds, sink, retención, owners, on-call, plataformas, rutas, timeouts, RTO y RPO continúan diferidos.

```text
SAMPLING=0
REAL_BASELINE_COLLECTION_AUTHORIZED=false
SANITIZED_EVIDENCE_TECHNICAL_REVIEW_AUTHORIZED=false
CUT02_IMPLEMENTATION_AUTHORIZED=false
R1_ACTIVATION_AUTHORIZED=false
R2_ACTIVATION_AUTHORIZED=false
```
