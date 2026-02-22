# Cierre Agenda Publica v1 (P1-P5-A)

## A) Resumen ejecutivo

Agenda Publica v1 resuelve un flujo publico de agendamiento sin login para pacientes:
- consulta de disponibilidad publica (P1),
- verificacion OTP (P2),
- reserva y confirmacion de cita con bloqueo temprano de slot (P3),
- cancelacion por token publico (P4),
- expiracion formal de reservas vencidas `pending_otp` (P5-A).

Beneficio principal: evita doble-booking en superficie publica y mantiene trazabilidad del flujo en `agenda_public_appointment_flows`.

Alcance NO incluido aun:
- cobros/pagos en plataforma,
- reglas finales de precio/comision,
- experiencia UX final de wizard (P6),
- integracion final embebida/white-label en perfil publico,
- automatizacion productiva del expire por cron/job (en P5-A es endpoint manual/operativo).

## B) Mapa de endpoints

| Fase | Metodo + ruta | Proposito | Request minimo | Response shape | Errores posibles | `meta.route` esperado |
|---|---|---|---|---|---|---|
| P1 | `GET /api/agenda/index.php/public/availability` | Exponer slots publicos disponibles por doctor | `doctor_id` (+ `mode`) | `{ok,error,message,data:{days[]},meta}` | `invalid_params`, `db_not_ready`, `db_error` | N/A (no route fijo en availability) |
| P2 | `POST /api/agenda/index.php/public/otp/request` | Solicitar OTP para contacto publico | `{doctor_id,contact_type,contact_value}` | `{ok,error,message,data:{otp_id,expires_in},meta}` | `invalid_params`, `db_error`, `server_error` | `public_otp_request` |
| P2 | `POST /api/agenda/index.php/public/otp/verify` | Verificar OTP | `{otp_id,code}` | `{ok,error,message,data:{verified},meta}` | `invalid_params`, `not_found`, `expired`, `invalid_code`, `too_many_attempts`, `db_error` | `public_otp_verify` |
| P3 | `POST /api/agenda/index.php/public/appointments/reserve` | Reservar slot en `pending_otp` (bloqueo temprano) | `{doctor_id,start_at,end_at,visit_kind,patient_type,booker_is_patient,patient,payment_mode}` | `{ok,error,message,data:{appointment_id,status,expires_in,cancel_token},meta}` | `invalid_params`, `slot_taken`, `db_error`, `db_not_ready` | `public_reserve` |
| P3 | `POST /api/agenda/index.php/public/appointments/confirm` | Confirmar cita con OTP validado | `{appointment_id,otp_id,code}` | `{ok,error,message,data:{appointment_id,status},meta}` | `invalid_params`, `not_found`, `otp_expired`, `otp_mismatch`, `invalid_code`, `conflict`, `db_error` | `public_confirm` |
| P4 | `POST /api/agenda/index.php/public/appointments/cancel` | Cancelar cita publica por `cancel_token` | `{cancel_token}` | `{ok,error,message,data:{appointment_id,status},meta}` | `validation_error`, `invalid_token`, `not_cancelable`, `db_error` | `public_cancel` |
| P5-A | `POST /api/agenda/index.php/public/maintenance/expire` | Expirar flows vencidos y cancelar `pending_otp` | opcional `{limit,dry_run}` (QA: `{force,appointment_id}`) | `{ok,error,message,data:{flows_expired,appointments_canceled},meta}` | `db_error` | `public_expire` |

Notas de contrato:
- Todos usan wrapper canonico: `{ok,error,message,data,meta}`.
- En P3 `reserve`, `cancel_token` se agrega en `data` sin romper compatibilidad.

## C) Maquina de estados

### appointment.status

- Camino principal:
  - `pending_otp -> confirmed -> canceled`
- Caminos alternos:
  - `pending_otp -> canceled` por cancelacion publica (P4)
  - `pending_otp -> canceled` por expiracion (P5-A)

Reglas de transicion:
- `reserve` crea `pending_otp`.
- `confirm` mueve `pending_otp` a `confirmed`.
- `cancel` acepta `pending_otp` y `confirmed`, y deja `canceled`.
- `expire` solo cancela cita si sigue `pending_otp`.

### flow.status (`agenda_public_appointment_flows`)

- Camino principal:
  - `pending_otp -> confirmed`
- Alternos:
  - `pending_otp -> canceled` (cancelacion)
  - `pending_otp -> expired` (expire)

Estados terminales del flow:
- `confirmed`, `canceled`, `expired` (una vez que llega aqui, el flow ya no debe volver a `pending_otp`).

Reglas:
- `reserve` crea flow en `pending_otp`.
- `confirm` marca flow `confirmed`.
- `cancel` marca flow `canceled` (con auditoria en `payload_json`).
- `expire` marca flow `expired` y guarda auditoria `payload_json.expiration`.

### Idempotencia por endpoint

- `GET public/availability`: idempotente por naturaleza (read).
- `POST public/otp/request`: no idempotente (cada request genera OTP nuevo).
- `POST public/otp/verify`: idempotente cuando ya esta verificado (`ok:true`).
- `POST public/appointments/reserve`: no idempotente (nueva reserva/intento).
- `POST public/appointments/confirm`: idempotente si cita ya confirmada.
- `POST public/appointments/cancel`: idempotente (`already_canceled`).
- `POST public/maintenance/expire`: idempotente (segunda corrida sobre mismos registros -> 0 cambios).

## D) Reglas de seguridad

- `cancel_token` es el unico input para cancelacion publica (sin `appointment_id` en request).
- Hardening aplicado:
  - si flow existe pero la cita no existe, se responde `invalid_token` (no filtrar existencia interna).
- Features QA-only:
  - `X-QA-Mode: 1` habilita `debug_code` en OTP,
  - `force + appointment_id` en `maintenance/expire` solo en QA.
- Pendiente operativo:
  - restringir/autorizar `public/maintenance/expire` en produccion (job interno o auth operacional).

## E) QA reproducible

| Script | Objetivo | Comando exacto | Que valida | Resultado esperado |
|---|---|---|---|---|
| `modules/agenda/qa/public_booking_p3.sh` | Reserva + confirmacion OTP + anti double-booking | `BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_booking_p3.sh` | 1) toma slot, 2) reserve, 3) otp/request QA, 4) confirm, 5) reintento mismo slot -> `slot_taken` | `PASS` |
| `modules/agenda/qa/public_cancel_p4.sh` | Cancelacion por token + idempotencia | `BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_cancel_p4.sh` | 1) reserve, 2) confirm, 3) cancel token, 4) reusar slot, 5) cancel idempotente, 6) invalid token, 7) cleanup | `PASS` |
| `modules/agenda/qa/public_expire_p5.sh` | Expiracion formal de `pending_otp` | `BASE_URL=http://127.0.0.1:8090 DOCTOR_ID=1 bash modules/agenda/qa/public_expire_p5.sh` | 1) reserve, 2) force QA expire, 3) maintenance/expire, 4) reusar slot, 5) segunda corrida idempotente (0 cambios), 6) cleanup | `PASS` |

## F) Contratos y compatibilidad

- Contrato JSON estandar mantenido en toda la superficie publica:
  - `{ok,error,message,data,meta}`
- Compatibilidad:
  - se agrego `data.cancel_token` en `reserve` de forma aditiva (no rompe clientes previos).
- Concurrencia/DB:
  - esquema actual basado en `active_slot_key` (GENERATED) + `UNIQUE uniq_active_slot` para bloquear solo citas activas,
  - al pasar a `canceled`, el slot se libera automaticamente,
  - compatible con `modules/agenda/db/ready_schema.sql` y `modules/agenda/sql/ready_schema.sql`.

## G) Pendientes / siguiente fase sugerida

1. P6 UX wizard final (mobile-first, copy final, accesibilidad, estados de error y reintento).
2. Cron/job real para expiracion (`maintenance/expire`) y observabilidad operacional.
3. Integracion en perfil publico (embed/vista dedicada y enlaces de comunicacion).
4. Arquitectura de pagos/comision (sin implementar en v1):
   - preautorizacion / captura,
   - liquidacion al medico,
   - politicas de cancelacion y reembolso.

## Estado de cierre v1

Agenda Publica v1 queda funcional en ciclo end-to-end:
- descubrimiento de disponibilidad,
- reserva y confirmacion,
- cancelacion por token,
- expiracion formal de reservas vencidas,
con QA reproducible y contratos estables para evolucion a P6+.
