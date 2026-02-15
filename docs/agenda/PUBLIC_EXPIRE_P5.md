# Public Expire P5 (maintenance)

## Objetivo

Expirar reservas públicas vencidas (`pending_otp`) y liberar slot en forma formal, transaccional e idempotente.

## Endpoint

`POST /api/agenda/index.php/public/maintenance/expire`

## Request

```json
{
  "limit": 50,
  "dry_run": false,
  "force": false,
  "appointment_id": "optional_qa"
}
```

Campos:
- `limit` opcional, default 50, max 200.
- `dry_run` opcional, default `false`.
- `force` + `appointment_id` opcional QA para pruebas reproducibles con `X-QA-Mode: 1`.

## Response

```json
{
  "ok": true,
  "error": null,
  "message": "expire completed",
  "data": {
    "flows_expired": 1,
    "appointments_canceled": 1
  },
  "meta": {
    "route": "public_expire",
    "limit_used": 50,
    "dry_run": false
  }
}
```

## Reglas

- Solo procesa flows con `status='pending_otp'` y `expires_at <= NOW()`.
- Si la cita asociada sigue `pending_otp`, la marca `canceled`.
- El flow se marca `expired`.
- Auditoría en `payload_json.expiration`:
  - `expired_at`
  - `reason: ttl_reached`
- Idempotencia:
  - segunda ejecución sobre los mismos registros no realiza cambios.

## QA reproducible

Script:
- `modules/agenda/qa/public_expire_p5.sh`

Ejecutar:

```bash
BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_expire_p5.sh
```

Valida:
1. reserve `pending_otp`
2. forzado QA de expiración (`force`)
3. expire maintenance
4. slot reutilizable
5. segunda corrida idempotente (0 cambios)
6. cleanup
