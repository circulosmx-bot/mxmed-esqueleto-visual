# CHECKLIST QA — AGENDA ↔ PACIENTES (P-11)

## 1) POST /patients — OK (201)
- Propósito: Crear paciente mínimo con contacto.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/patients/index.php/patients" \
  -H "Content-Type: application/json" \
  -d '{"display_name":"Paciente QA","contacts":[{"type":"phone","value":"+5215512345678","is_primary":true}]}'
```
- Expected: HTTP 201 (o 200 si el controlador usa 200), `ok:true`, `data.patient_id` presente, `meta.visibility.contact="masked"`.
- Notas: patient_id se usará en casos siguientes.

## 2) POST /patients — invalid_params (200)
- Propósito: Validar rechazo de payload vacío.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/patients/index.php/patients" \
  -H "Content-Type: application/json" \
  -d '{"display_name":"","contacts":[{"type":"phone","value":""}]}'
```
- Expected: HTTP 200, `ok:false`, `error:"invalid_params"`, `meta.fields` con `display_name` y `contacts[0].value`, `meta.visibility.contact="masked"`.

## 3) POST /patients — db_not_ready (QA_MODE=not_ready)
- Propósito: Verificar respuesta controlada sin DB.
- Request:
```bash
QA_MODE=not_ready curl -i -X POST "http://127.0.0.1:8088/api/patients/index.php/patients" \
  -H "Content-Type: application/json" \
  -d '{"display_name":"Paciente QA","contacts":[{"type":"phone","value":"+5215512345678"}]}'
```
- Expected: HTTP 200, `ok:false`, `error:"db_not_ready"`, `message:"patients db not ready"`, `meta.visibility.contact="masked"` (y `meta.qa_mode_seen` si aplica).

## 4) POST /appointments — OK con patient{} y timestamps con "T"
- Propósito: Auto-crear paciente y cita con formato ISO T.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04T10:00:00",
    "end_at":"2026-02-04T10:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "patient":{
      "display_name":"Paciente T",
      "contacts":[{"type":"phone","value":"+5215512345678"}]
    }
  }'
```
 - Expected: `ok:true`, `data.patient_id` asignado, `data.appointment_id` presente, `meta.write="create"`, `meta.events_appended=1`.

## 5) POST /appointments — OK con patient{} y timestamps con espacio
- Propósito: Aceptar formato con espacio.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04 11:00:00",
    "end_at":"2026-02-04 11:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "patient":{
      "display_name":"Paciente Espacio",
      "contacts":[{"type":"email","value":"espacio@example.com"}]
    }
  }'
```
 - Expected: `ok:true`, `data.patient_id` creado, `data.appointment_id` presente, `meta.write="create"`, `meta.events_appended=1`.

## 6) POST /appointments — NO auto-crea si viene patient_id
- Propósito: Reusar paciente existente.
- Request (reusar `patient_id` del caso 1):
```bash
curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04 12:00:00",
    "end_at":"2026-02-04 12:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "patient_id":"REEMPLAZA_AQUI",
    "patient":{
      "display_name":"Ignorado",
      "contacts":[{"type":"phone","value":"+529999999999"}]
    }
  }'
```
- Expected: `ok:true`, `data.patient_id` igual al enviado, no depende de `patient{}`.
 - Expected: `ok:true`, `data.patient_id` igual al enviado, no depende de `patient{}`. Nota: si viene `patient_id`, Agenda no intenta crear paciente y el objeto `patient{}` se ignora para creación.

## 7) POST /appointments — Fallback en raíz (sin patient{})
- Propósito: Compatibilidad con payload plano.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04 13:00:00",
    "end_at":"2026-02-04 13:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "display_name":"Paciente Fallback",
    "contacts":[{"type":"phone","value":"+5215588888888"}]
  }'
```
 - Expected: `ok:true`, `data.patient_id` generado, `data.appointment_id` presente, `meta.write="create"`, `meta.events_appended=1`.

## 8) POST /appointments — Propaga error invalid_params de pacientes
- Propósito: Ver propagación de errores de creación de paciente.
- Request:
```bash
curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04 14:00:00",
    "end_at":"2026-02-04 14:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "patient":{
      "display_name":"",
      "contacts":[{"type":"phone","value":""}]
    }
  }'
```
- Expected: `ok:false`, `error:"invalid_params"` (proveniente de Pacientes), `meta.visibility.contact="masked"`, la cita no se crea.

## 9) POST /appointments — Propaga error db_not_ready de pacientes (QA_MODE=not_ready)
- Propósito: Ver degradación controlada cuando Pacientes no está listo.
- Request:
```bash
QA_MODE=not_ready curl -i -X POST "http://127.0.0.1:8088/api/agenda/index.php/appointments" \
  -H "Content-Type: application/json" \
  -d '{
    "doctor_id":"d_1",
    "consultorio_id":"c_1",
    "start_at":"2026-02-04 15:00:00",
    "end_at":"2026-02-04 15:30:00",
    "modality":"presencial",
    "channel_origin":"panel",
    "created_by_role":"system",
    "created_by_id":"qa",
    "patient":{
      "display_name":"Paciente NR",
      "contacts":[{"type":"phone","value":"+5215577777777"}]
    }
  }'
```
- Expected: `ok:false`, `error:"db_not_ready"`, `message:"patients db not ready"`, `meta.visibility.contact="masked"`, la cita no se crea.
