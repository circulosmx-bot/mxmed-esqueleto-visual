# Public Wizard P6 (UI dedicada)

## Objetivo

Agregar una vista dedicada mobile-first para agenda publica, consumiendo endpoints existentes P1-P4 sin cambios de backend.

## Archivos

- `public-book.html`
- `assets/js/public/agenda-wizard.js`

## Como abrir

Ejemplo minimo:

```bash
http://127.0.0.1:8090/public-book.html?doctor_id=1
```

Opcional consultorio:

```bash
http://127.0.0.1:8090/public-book.html?doctor_id=1&consultorio_id=1
```

Si falta `doctor_id`, la vista muestra error grande y no inicia flujo.

## Flujo UX (4 pasos)

1. Horario
- Carga disponibilidad `mode=next&days=3&limit_per_day=10`.
- Renderiza dias + slots como botones grandes.
- Al elegir slot, habilita avanzar.

2. Datos
- Captura tipo de cita (`presencial|video`).
- Captura tipo de paciente (`first_time|follow_up`).
- Captura paciente (nombre, telefono, email) + opcionales (dob, genero, reason).
- Permite “quien agenda es paciente” o datos de booker separados.

3. OTP
- Primero reserva (`/public/appointments/reserve`).
- Luego solicita OTP (`/public/otp/request`, canal sms).
- Luego confirma (`/public/appointments/confirm`).
- Si `slot_taken`, vuelve a paso 1 con aviso.

4. Confirmacion
- Muestra `appointment_id`, horario y `cancel_token`.
- Boton “Cancelar cita” (`/public/appointments/cancel`).
- Link respaldo: `public-cancel.html?token=<cancel_token>`.

## Endpoints consumidos y payloads

### Availability (P1)
`GET /api/agenda/index.php/public/availability`

Query usada por wizard:
- `doctor_id`
- `consultorio_id` (si viene en URL)
- `mode=next`
- `days=3`
- `limit_per_day=10`

### Reserve (P3)
`POST /api/agenda/index.php/public/appointments/reserve`

Payload minimo enviado:

```json
{
  "doctor_id": "1",
  "consultorio_id": "1",
  "start_at": "2026-02-16 09:00:00",
  "end_at": "2026-02-16 09:30:00",
  "visit_kind": "presencial",
  "patient_type": "first_time",
  "booker_is_patient": true,
  "booker": {"name":"...","phone":"...","email":"..."},
  "patient": {"name":"...","phone":"...","email":"...","dob":"...","gender":"...","reason":"..."},
  "payment_mode": "none"
}
```

### OTP request (P2)
`POST /api/agenda/index.php/public/otp/request`

```json
{
  "doctor_id": "1",
  "contact_type": "sms",
  "contact_value": "+52..."
}
```

### Confirm (P3)
`POST /api/agenda/index.php/public/appointments/confirm`

```json
{
  "appointment_id": "...",
  "otp_id": 123,
  "code": "123456"
}
```

### Cancel (P4)
`POST /api/agenda/index.php/public/appointments/cancel`

```json
{
  "cancel_token": "...",
  "reason": "Cancelacion desde wizard"
}
```

## Manejo de estados y errores

- Cargando: mensaje visible en paso 1.
- Error de red/no JSON: muestra “Error de red”.
- Mensajes por severidad:
  - `success` (verde)
  - `warning` (amarillo)
  - `error` (rojo)
- Reintento de availability con boton dedicado.

## Reglas tecnicas

- Sin `localStorage` para tokens (todo en memoria JS).
- JS vanilla + Bootstrap (sin frameworks pesados).
- No modifica contratos backend.

## Manual QA UI

1. Abrir `public-book.html?doctor_id=1`.
2. Seleccionar slot en paso 1.
3. Completar paso 2.
4. En paso 3, pulsar “Reservar y enviar OTP”.
5. Ingresar OTP valido y confirmar.
6. Ver paso 4 con `appointment_id` y `cancel_token`.
7. Probar “Cancelar cita” desde misma pantalla.
8. Probar link a `public-cancel.html?token=...`.

## Regresion backend recomendada

```bash
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_booking_p3.sh
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_cancel_p4.sh
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_expire_p5.sh
```
