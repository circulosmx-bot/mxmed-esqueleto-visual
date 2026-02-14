# Public Appointments P2 (OTP)

## Scope
P2 adds public appointment confirmation with OTP (6 digits, expires in 10 minutes) for Agenda Publica.
No SMS provider is integrated yet; sender is abstract and dev-only logging is used.

## Public Endpoints
Base: `/api/agenda/index.php/public`

1. `POST /appointments/request`
2. `POST /appointments/verify`

## Request: `POST /public/appointments/request`

### Input JSON
```json
{
  "doctor_id": "1",
  "consultorio_id": "1",
  "start_at": "2026-02-16 09:00:00",
  "end_at": "2026-02-16 09:30:00",
  "patient_name": "Paciente Publico",
  "patient_phone": "+5215512345678",
  "patient_email": "publico@example.com"
}
```

Notes:
- `consultorio_id` is optional. If missing/non-numeric, backend resolves doctor default consultorio.
- Must provide at least one contact: `patient_phone` or `patient_email`.
- Slot is validated against agenda availability/collisions before OTP request is persisted.

### Success response
```json
{
  "ok": true,
  "error": null,
  "message": "verification required",
  "data": {
    "request_id": "uuid",
    "expires_at": "YYYY-MM-DD HH:MM:SS"
  },
  "meta": {
    "verification_required": true,
    "consultorio_id_used": "1"
  }
}
```

### QA-only debug
If `QA_MODE=1` (env or `X-QA-Mode: 1` header), `meta.otp_debug` is returned for reproducible tests.

## Verify: `POST /public/appointments/verify`

### Input JSON
```json
{
  "request_id": "uuid",
  "otp": "123456"
}
```

### Success response
```json
{
  "ok": true,
  "error": null,
  "message": "appointment confirmed",
  "data": {
    "appointment_id": "...",
    "status": "confirmed"
  },
  "meta": {
    "request_id": "uuid",
    "consultorio_id_used": "1",
    "confirmed": true
  }
}
```

### Error cases
- `invalid_params`: invalid payload fields.
- `slot_unavailable`: slot no longer available.
- `otp_invalid`: invalid OTP; attempts incremented; max 5.
- `otp_expired`: OTP expired (10-minute window).
- `not_found`: request id not found.
- `conflict`: already verified request.

## DB
Table: `agenda_public_otp_requests`

Schema SQL file:
- `modules/agenda/db/public_appointments_otp_requests.sql`

## QA script
- `modules/agenda/qa/public_appointment_otp_p2.sh`

Run:
```bash
BASE_URL=http://127.0.0.1:8090 QA_MODE=1 bash modules/agenda/qa/public_appointment_otp_p2.sh
```

## Frontend P2
Public UI file:
- `public-agenda.html`
- `assets/js/public/agenda-publica.js`

Flow:
1. Select slot.
2. Submit patient name + phone/email.
3. Receive OTP.
4. Verify OTP.
5. Appointment confirmed.
