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
- Usa `BASE_URL`, `DOCTOR_ID`, `CONSULTORIO_ID`, `APPOINTMENT_ID`, `PATIENT_ID`, `DATE` y `QA_MODE` como variables configurables
- No modifica routers ni rewrites
- No depende de WAMP ni levanta servidores
- El script debe fallar (exit != 0) si algún contrato se rompe o un mensaje no es el esperado

## Ejecución (Mac)
- Modo `not_ready` (tablas faltantes)
  ```sh
  BASE_URL=http://127.0.0.1:8089/api/agenda/index.php \
    DOCTOR_ID=1 CONSULTORIO_ID=1 APPOINTMENT_ID=demo PATIENT_ID=demo DATE=2026-02-01 \
    QA_MODE=not_ready bash modules/agenda/qa/requests.sh
  ```
- Modo `ready` (tablas reales listas)
  ```sh
  BASE_URL=http://127.0.0.1:8089/api/agenda/index.php \
    DOCTOR_ID=1 CONSULTORIO_ID=1 APPOINTMENT_ID=demo PATIENT_ID=demo DATE=2026-02-01 \
    QA_MODE=ready bash modules/agenda/qa/requests.sh
  ```

## READY MODE (tablas reales)
- Given: las tablas `appointments`, `appointment_events`, `patient_flags` están creadas y configuradas en `modules/agenda/config/agenda.php`.
- When: se ejecutan las operaciones `POST /appointments`, `PATCH /appointments/{id}/reschedule`, `POST /appointments/{id}/cancel`, `POST /appointments/{id}/no_show` y las lecturas relacionadas (`/events`, `/patients/{id}/flags`).
- Then:
  - Cada respuesta cumple el contrato JSON (ok/error/message/data/meta) y `meta` es siempre un objeto.
  - `POST /appointments` crea la cita y genera un evento `appointment_created`.
  - Después de reschedule/cancel/no_show los eventos incrementan su contador.
  - `POST /appointments/{id}/no_show` retorna `meta.flag_appended` (0 o 1) y `GET /patients/{id}/flags` reporta un `reason_code` `no_show` o `late_cancel` cuando el flag está habilitado.
- El script imprime diferencias de cuenta y el estado del flag al final.

## READY MODE bootstrap
- El SQL `modules/agenda/sql/ready_schema.sql` crea las tablas mínimas `agenda_appointments`, `agenda_appointment_events` y `agenda_patient_flags` necesarias para los writes.
- Importa este SQL en tu instancia MySQL local (puede ser `mysql -u user -p < modules/agenda/sql/ready_schema.sql`); el comando debe ejecutarse en la carpeta raíz del repo.
- Ajusta `modules/agenda/config/agenda.php` para que apunte a esas tablas (ya está preconfigurado en este repo).
- Una vez las tablas existan, corre:
  ```sh
  QA_MODE=ready BASE_URL=http://127.0.0.1:8089/api/agenda/index.php bash modules/agenda/qa/requests.sh
  ```
- Observá que los eventos aumentan de cuenta (`GET /appointments/{id}/events` después de cada operación) y que `meta.flag_appended` refleja si el flag se creó.
- Si las tablas aún no existen o la conexión falla, el modo `ready` devolverá `db_not_ready` o `db_error` y se debe resolver la base antes de volver a correr.
