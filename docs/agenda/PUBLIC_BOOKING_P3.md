# Public Booking P3 (Reserva + OTP + anti doble-booking)

## Endpoints publicos
Base: `/api/agenda/index.php/public`

1. `POST /appointments/reserve`
2. `POST /appointments/confirm`
3. `POST /otp/request` (existente P2)

## POST /public/appointments/reserve

Reserva el slot en `pending_otp` para bloquear concurrencia temprano.

### Payload
```json
{
  "doctor_id": "1",
  "consultorio_id": "1",
  "start_at": "2026-02-16 09:00:00",
  "end_at": "2026-02-16 09:30:00",
  "visit_kind": "presencial",
  "patient_type": "first_time",
  "booker_is_patient": true,
  "booker": {
    "name": "Paciente Publico",
    "phone": "+5215512345678",
    "email": "publico@example.com"
  },
  "patient": {
    "name": "Paciente Publico",
    "phone": "+5215512345678",
    "email": "publico@example.com",
    "dob": "1990-01-01",
    "gender": "M",
    "reason": "Chequeo general"
  },
  "extras": {
    "address": {
      "line1": "Calle 1",
      "cp": "01000",
      "city": "CDMX",
      "state": "CDMX"
    },
    "allergies": "ninguna",
    "habits": "camina diario"
  },
  "otp": {
    "channel": "sms"
  },
  "payment_mode": "none"
}
```

### Success
```json
{
  "ok": true,
  "data": {
    "appointment_id": "...",
    "status": "pending_otp",
    "expires_in": 600
  },
  "meta": {
    "route": "public_reserve"
  }
}
```

### Slot tomado
```json
{
  "ok": false,
  "error": "slot_taken",
  "message": "El horario ya fue reservado, elige otro",
  "meta": {
    "start_at": "...",
    "end_at": "...",
    "doctor_id": "1",
    "consultorio_id_used": "1"
  }
}
```

## POST /public/appointments/confirm

Valida OTP usando la misma logica de `/public/otp/verify` y confirma la cita.

### Payload
```json
{
  "appointment_id": "...",
  "otp_id": 123,
  "code": "123456"
}
```

### Success
```json
{
  "ok": true,
  "data": {
    "appointment_id": "...",
    "status": "confirmed"
  },
  "meta": {
    "route": "public_confirm"
  }
}
```

## Flujo recomendado (P3)

1. `POST /public/appointments/reserve`
2. `POST /public/otp/request`
3. `POST /public/appointments/confirm`

Esto bloquea el slot antes de OTP y reduce carrera de doble-booking.

## QA reproducible

Script:
- `modules/agenda/qa/public_booking_p3.sh`

Ejecutar:
```bash
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_booking_p3.sh
```

Opcionales:
```bash
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 CONSULTORIO_ID=1 bash modules/agenda/qa/public_booking_p3.sh
```

El script valida:
1. Obtiene slot de `public/availability`.
2. Reserva (`pending_otp`).
3. Solicita OTP en QA (`debug_code`).
4. Confirma cita.
5. Reintenta el mismo slot y espera `slot_taken`.

## SQL P3

Archivo de apoyo:
- `modules/agenda/db/public_booking_p3.sql`

Incluye:
- `UNIQUE KEY uniq_appointments_slot (doctor_id, consultorio_id, start_at)`.
- Tabla `agenda_public_appointment_flows` para estado de reserva publica + concepto de `cancel_token` (uso futuro).
