# Agenda QA Pack v1

## Objetivo
Validar de manera automatizada que el módulo Agenda cumple el contrato JSON (ok/error/message/data/meta), que `meta` siempre es un objeto y que los errores críticos (db_not_ready, not_found, invalid_params) devuelven los mensajes exactos acordados.

## Mensajes contractuales definitivos
Cada error debe utilizar exactamente el mensaje que se indica a continuación. Hay que verificarlo en cada caso y, cuando la tabla correspondiente no existe, el script debe salir con código distinto de cero.
- `availability base schedule not ready`
- `availability overrides not ready` *(controlado únicamente por `overrides_table` dentro de `modules/agenda/config/agenda.php`)*
- `appointments table not ready`
- `appointment events not ready`
- `patient flags not ready`
- `appointment not found`
- `database error`

> **Nota:** las configuraciones de overrides NO se exponen por query params; hay que editar `modules/agenda/config/agenda.php` para simular escenarios distintos.

## Contract pack & flujo
Todos los casos siguen el patrón Given / When / Then y deben validar:
1. Presence of `ok`, `error`, `message`, `data`, `meta`
2. `meta` is always an object (`{}`) even when empty
3. The response body matches the expected `error` + `message`

### Casos mínimos (entorno sin tablas)
1. **GET /availability**
   - Given: las tablas de agenda no están creadas
   - When: `GET /api/agenda/index.php/availability?doctor_id=DOCTOR_ID&consultorio_id=CONSULTORIO_ID&date=DATE`
   - Then: `error=db_not_ready`, `message="availability base schedule not ready"`, `meta=(object){}`

2. **GET /appointments/{id}/events**
   - Given: `appointment_events_table=null`
   - When: `GET /api/agenda/index.php/appointments/APPOINTMENT_ID/events`
   - Then: `error=db_not_ready`, `message="appointment events not ready"`

3. **GET /patients/{id}/flags**
   - Given: `patient_flags_table=null`
   - When: `GET /api/agenda/index.php/patients/PATIENT_ID/flags`
   - Then: `error=db_not_ready`, `message="patient flags not ready"`

4. **POST /appointments**
   - Given: `appointments_table=null`
   - When: `POST /api/agenda/index.php/appointments` con payload mínimo `{}`
   - Then: `error=invalid_params`, `message="invalid_params"` y meta objeto

5. **PATCH /appointments/{id}/reschedule**
   - Given: el appointment ID no existe
   - When: `PATCH /api/agenda/index.php/appointments/unknown/reschedule` con payload válido
   - Then: `error=not_found`, `message="appointment not found"`

6. **POST /appointments/{id}/cancel**
   - Given: el appointment ID no existe
   - When: `POST /api/agenda/index.php/appointments/unknown/cancel` con `{ "motivo_code": "test" }`
   - Then: `error=not_found`, `message="appointment not found"`

7. **POST /appointments/{id}/no_show**
   - Given: payload incompleto
   - When: `POST /api/agenda/index.php/appointments/unknown/no_show` con `{}`
   - Then: `error=invalid_params`, `message="invalid_params"`

## Overrides (capas B/C)
- `overrides_table = null` → `/availability` continúa respondiendo `ok:true` con `meta.overrides_enabled=false`.
- `overrides_table = 'tabla_missing'` (configurada pero tabla ausente) → error `availability overrides not ready`.
- `overrides_table` apunta a tabla existente → `/availability` debe responder `ok:true` y `meta.overrides_enabled=true`.

## Requisitos del script QA
- Usa `BASE_URL`, `DOCTOR_ID`, `CONSULTORIO_ID`, `APPOINTMENT_ID`, `PATIENT_ID`, `DATE` como variables configurables
- No modifica routers ni rewrites
- No depende de WAMP ni levanta servidores
- El script debe fallar (exit != 0) si algún contrato se rompe o un mensaje no es el esperado

## Ejecución recomendada
```
BASE_URL=http://127.0.0.1:8089/api/agenda/index.php \
  DOCTOR_ID=1 CONSULTORIO_ID=1 APPOINTMENT_ID=demo PATIENT_ID=demo DATE=2026-02-01 \
  bash modules/agenda/qa/requests.sh
```

Ajusta las variables para cubrir distintos escenarios y repite.
