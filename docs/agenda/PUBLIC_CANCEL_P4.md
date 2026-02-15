# Public Cancel P4 (cancel_token)

## Endpoint

`POST /api/agenda/index.php/public/appointments/cancel`

## Request

```json
{
  "cancel_token": "string",
  "reason": "optional <= 280"
}
```

## Success responses

### Cancel exitoso

```json
{
  "ok": true,
  "error": null,
  "message": "canceled",
  "data": {
    "appointment_id": "...",
    "status": "canceled"
  },
  "meta": {
    "route": "public_cancel",
    "released_slot": true
  }
}
```

### Idempotente (ya cancelada)

```json
{
  "ok": true,
  "error": null,
  "message": "already_canceled",
  "data": {
    "appointment_id": "...",
    "status": "canceled"
  },
  "meta": {
    "route": "public_cancel",
    "released_slot": true,
    "idempotent": true
  }
}
```

## Errores

- `validation_error`: falta `cancel_token` o `reason` > 280.
- `invalid_token`: token inexistente.
- `not_cancelable`: estado distinto de `pending_otp|confirmed|canceled`.
- `appointment_missing`: flujo existe pero cita no existe.

## Comportamiento de slot

Al cancelar, la cita pasa a `status='canceled'`.
Con `active_slot_key` generado, ese estado deja de bloquear `uniq_active_slot`, por lo que el slot se libera automáticamente.

## QA reproducible

Script:
- `modules/agenda/qa/public_cancel_p4.sh`

Ejecutar:

```bash
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_cancel_p4.sh
```

El script valida:
1. toma slot disponible,
2. reserva y obtiene `cancel_token`,
3. confirma con OTP,
4. cancela por token,
5. reserva de nuevo el mismo slot,
6. recancela (idempotente),
7. token inválido.
