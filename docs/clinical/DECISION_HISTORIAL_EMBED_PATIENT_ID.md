# DECISION — Historial Embed Patient ID Source

## Fuente oficial elegida
Para el panel embebido de **Historial de atención** en Expediente, la fuente primaria de `patient_id` es:
- `#p-expediente[data-patient-id]` / `dataset.patientId`

Esta prioridad está alineada con la lógica existente en `assets/js/app.js` (`getCurrentPatientId()`).

## Fallbacks aplicados
Si no existe fuente primaria:
1. hidden/input `patient_id` en el contenedor
2. dataset adicional del contenedor
3. construcción legacy con `mxmedIdentity.buildLegacyPatientId(...)` usando nombre + DOB + sexo
4. storage local (si existe)
5. querystring `?patient_id=`

## Idempotencia
El iframe embebido usa `lastLoadedPatientId` / `data-loaded-patient-id` para:
- cargar solo una vez al activar tab
- recargar solo cuando cambia el paciente y el tab está visible
