# Cierre Timeline v1

## Qué se entregó
- Contrato: `docs/clinical/TIMELINE_V1_CONTRACT.md`
- Endpoint: `GET /api/clinical/index.php/patients/{patient_id}/timeline`
  - `include=clinical`: documents + encounters (por `appointment_id`)
  - `include=agenda`: appointments via identity bridge
  - `include=agenda,clinical`: mezcla
- UI QA: `modules/clinical/ui/timeline.php`

## Cómo probar
```bash
php -l api/clinical/index.php
php -l modules/clinical/ui/timeline.php
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/health" | jq .
curl -sS "http://127.0.0.1:8091/api/clinical/index.php/patients/p_abc123456789/timeline?include=agenda,clinical&limit=50" | jq .
```

Abrir en navegador:

`http://127.0.0.1:8091/modules/clinical/ui/timeline.php?patient_id=p_abc123456789`

## Qué NO se hizo
- Cursor unificado cuando hay mezcla de streams (`agenda + clinical + encounters`).
- Billing/Fiscal real (solo “preparado” a nivel contrato).
- UI para paciente público (esto es QA interna).

## Riesgos y pendientes
- Orden actual de items (mixto).
- Falta normalización de `appointment_id` en `clinical_documents` (depende de flujos que lo escriban).
- Necesidad de index/DDL en schema del repo para `appointment_id`.
