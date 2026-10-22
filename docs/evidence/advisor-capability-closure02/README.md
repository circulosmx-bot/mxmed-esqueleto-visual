# Evidencia CLOSURE02

Matriz exclusiva de26 IDs del catálogo; no se repitió la prueba histórica de14 capacidades.

| ID canónico | Clase | Conectado en alcance |
|---|---|---|
| `media_review_read` | OPERATIONAL_IN_SCOPE | Sí |
| `media_review_approve` | OPERATIONAL_IN_SCOPE | Sí |
| `media_review_source_download` | OPERATIONAL_IN_SCOPE | Sí |
| `media_review_corrected_upload` | OPERATIONAL_IN_SCOPE | Sí |
| `media_review_improve` | OPERATIONAL_IN_SCOPE | Sí |
| `media_review_request_replacement` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_pending_summary_read` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_request_read` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_request_evidence_read` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_claim_resolve` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_new_profile_resolve` | OPERATIONAL_IN_SCOPE | Sí |
| `profile_create` | NO_ADVISOR_CONSUMER | No |
| `profile_curate` | NO_ADVISOR_CONSUMER | No |
| `profile_publication_manage` | NO_ADVISOR_CONSUMER | No |
| `profile_support_assist` | NO_ADVISOR_CONSUMER | No |
| `physician_locations_read` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_administrative_read` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_operator_support_read` | OPERATIONAL_IN_SCOPE | Sí |
| `internal_advisors_manage` | RESERVED | No |
| `profile_initial_ownership_approve` | RESERVED | No |
| `provider_claim_review` | OPERATIONAL_IN_SCOPE | Sí |
| `professional_source_provision` | OPERATIONAL_IN_SCOPE | Sí |
| `physician_accreditation_review` | OPERATIONAL_IN_SCOPE | Sí |
| `public_profile_correction_review` | RESERVED | No |
| `public_profile_correction_apply` | RESERVED | No |
| `provider_subscription_activate` | COMMERCIAL_OUT_OF_SCOPE | No |

[Contrato y guía](../../identity/ADVISOR-CAPABILITY-CLOSURE02.md).

- `summary-fields.json`: mismo actor/sesión, campos antes/después, sin token CSRF exportado.
- `checks.json`, `consumer-checks.json`, `security.json`, `reserved.json`: comprobaciones dirigidas.
- `consumer-attempt1/2`: fallos del harness (nombre NOT_REQUESTED y forma del retorno upload); resultados positivos previos preservados.
- `consumer-attempt3-scope-defect.json`: defecto real de estado fuera de scope, corregido y vuelto a verificar en consumer-checks.
- 30 nombres distintos de aserciones browser/HTTP con PASS, más3 denegaciones nativas; no se suman reintentos como nueva cobertura.
- `consumer-audit.json`, `grant-audit.json`: eventos canónicos, tres pruebas MFA consumidas; journal de claims identificado por separado.
- `original-records.json`, `protected-runtime.json`, `prior-demo-preserved.json`: preservación acotada y diferencia de puntero antiguo explícita.
- `demonstration.json`, `capability-matrix.json`, `tested-artifacts.json`: cuenta nueva,17 grants, catálogo y hashes de fuentes.
- Documentos y decisiones sólo sintéticos; consulta original por lector restringido. Ningún secreto, cookie o prueba MFA se publica.
