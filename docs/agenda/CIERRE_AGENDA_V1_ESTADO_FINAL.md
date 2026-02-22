# Agenda Médica v1 + Waitlist v1 — Estado Final (READY)

---

## Resumen ejecutivo
- Agenda v1 funcional y validada por QA interno y pruebas curl/QA_MODE.
- Waitlist v1 funcional, integrado y validado con los pasos 2 a 10 cerrados.
- Backend estable con contratos JSON claros, trazabilidad de eventos y timestamps consistentes.
- UI server-rendered mínima (PHP) operativa para operación manual.
- V1 manual, sin automatizaciones ni IA activas.
- No hay SPA ni FullCalendar; la experiencia se mantiene en PHP tradicional.

## Alcance congelado
- **Incluye Agenda CORE**: disponibilidad, control de colisiones/concurrencia, creación/cancelación/no-show/reprogramación, eventos auditables, contratos JSON estables y QA mínimo backend.
- **Incluye Waitlist**: pasos 2 a 10 documentados y validados (aterrizaje, UX paciente, UX operadora, trazabilidad, reglas, checklist).
- **NO incluye**: automatizaciones, agentes IA, lógica marcada como FUTURO que no se implementó, frontends SPA ni FullCalendar (es parte de futuras fases).

## Entregables por capa
- **Backend**: API centralizada en `/api/agenda/index.php` con `WaitlistController`, `AppointmentWriteController`, repositorios conectados, eventos auditables y timestamps corregidos.
- **UI**: vistas PHP server-rendered (`day.php`, `waitlist.php`) con flujo de asignación manual funcional.
- **Contratos**: contratos JSON estables documentados y probados por QA (ok/error/message/data/meta).
- **QA**: smoke tests ejecutados vía curl y UI local para validar entradas, asignaciones, eventos y estados.

## Flujo crítico validado
1. Entrada del paciente a la waitlist cuando no hay citas directas.
2. Asignación manual desde la operadora sobre una entrada activa.
3. Creación real de la cita derivada de la entrada.
4. Registro de eventos (`appointment_created`, `appointment_reassigned_from_waitlist`, etc.).
5. Limpieza manual / por reglas simples de entries expiradas/confirmadas (sin automatización).
6. Bloqueo y verificación de colisiones antes de confirmar slot.

Rutas reales y ejemplos reproducibles: ver `modules/agenda/qa/requests.sh` (READY MODE + Waitlist minimal flow).

## QA mínimo — Smoke tests
- `GET [REEMPLAZAR_RUTA_REAL]/appointments/{id}/events` con `ok:true` y `events` ordenados.
- `POST [REEMPLAZAR_RUTA_REAL]/waitlist` para crear entrada y verificar respuesta con `data.id`.
- `PATCH [REEMPLAZAR_RUTA_REAL]/waitlist/{id}` para cambiar status (contacted/accepted).
- `POST [REEMPLAZAR_RUTA_REAL]/waitlist/{id}/assign` para asignar slot y obtener `appointment_id`.
- `GET [REEMPLAZAR_RUTA_REAL]/waitlist?doctor_id={id}&consultorio_id={id}&status=active` para listar entradas activas.

## Limitaciones conocidas (v1)
- Operación totalmente manual (sin automatización).
- UI mínima basada en PHP server-rendered.
- No se incluye FullCalendar ni vistas avanzadas.
- No existe visualización pública de agenda para pacientes.

## FUTURO (fuera de alcance)
- Visualización pública en perfil del médico.
- Integración FullCalendar.
- Automatización de asignación waitlist.

## Criterios de cierre (READY)
- Backend responde con contratos JSON estables y eventos trazables.
- Waitlist documentado (PASOS 2–10) y validado con QA.
- UI server-rendered disponible para operadora y paciente.
- No hay funcionalidades marcadas como futuristas habilitadas en v1.
- Documentación de cierre publicada y comunicada al equipo.
