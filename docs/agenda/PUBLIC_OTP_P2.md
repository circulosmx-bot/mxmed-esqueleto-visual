# PUBLIC OTP P2

## Objetivo
Implementar OTP publico (6 digitos, expira en 10 minutos) para Agenda Publica, sin proveedor SMS/email real por ahora.

## Endpoints
Base: `/api/agenda/index.php/public`

1. `POST /otp/request`
2. `POST /otp/verify`

Todos responden con wrapper:
```json
{ "ok": true|false, "error": "..."|null, "message": "...", "data": {}, "meta": {} }
```

## POST /public/otp/request

### Request JSON
```json
{
  "doctor_id": "1",
  "contact_type": "sms",
  "contact_value": "+52 55 1234-5678"
}
```

Reglas:
- `doctor_id`: numerico requerido.
- `contact_type`: `sms` o `email`.
- `contact_value`: requerido.
  - email: validacion basica con `@` + `filter_var`.
  - sms: validacion simple (`+`, digitos, espacio, guion).
- OTP generado: 6 digitos (100000..999999).
- Se guarda **solo hash** (`password_hash`) en `agenda_public_otps`.
- Expiracion: 10 minutos.

### Success
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": { "otp_id": "123", "expires_in": 600 },
  "meta": { "route": "public_otp_request" }
}
```

### QA debug_code
Para QA reproducible, `meta.debug_code` solo se incluye cuando:
- `MXMED_QA_MODE=1` en entorno del servidor, o
- header `X-MXMed-QA-Mode: 1`.

## POST /public/otp/verify

### Request JSON
```json
{
  "otp_id": "123",
  "code": "123456"
}
```

### Reglas
- `otp_id` numerico.
- `code` exactamente 6 digitos.
- Si OTP no existe: `not_found`.
- Si ya estaba verificado: respuesta `ok:true` (idempotente).
- Si expiro: `expired`.
- Si intentos >= 5: `too_many_attempts`.
- Si codigo incorrecto: incrementa attempts y devuelve `invalid_code` (o `too_many_attempts` si alcanza limite).
- Si codigo correcto: `verified=1` y `ok:true`.

### Success
```json
{
  "ok": true,
  "error": null,
  "message": "",
  "data": { "verified": true },
  "meta": { "route": "public_otp_verify", "otp_id": 123 }
}
```

## QA script
Archivo:
- `modules/agenda/qa/public_otp_p2.sh`

Ejecutar:
```bash
BASE_URL=http://127.0.0.1:8090 bash modules/agenda/qa/public_otp_p2.sh
```

El script solicita OTP, toma `debug_code` (QA) y verifica OTP.
