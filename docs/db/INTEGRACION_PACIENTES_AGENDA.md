# INTEGRACIÓN CONCEPTUAL — PACIENTES ↔ AGENDA (P-6)

## 1) Principios de diseño
- Cada dominio mantiene su frontera; Pacientes administra identidad/contacto y Agenda administra citas/estados.
- Single Source of Truth: Pacientes es origen de patient_id y contactos, Agenda es origen de citas, eventos y flags.
- Comunicación mínima: Agenda solo pide el snapshot necesario (masked) y no duplica datos sensibles.
- Privacidad por defecto: las respuestas llegan con `visibility.contact = masked`.

## 2) Qué datos necesita Agenda de Pacientes
- Patient Snapshot consumido por Agenda:
  - `patient_id`
  - `display_name`
  - `contact` (solo masked, no full)
  - Flags administrativos como referencia (no reescritura)
- Agenda no mantiene `birthdate` completo ni contactos sin enmascarar.
- Los consentimientos viven en Pacientes y no se duplican en Agenda.

## 3) Flujos canónicos de integración
### 3.1 Crear cita con paciente existente
- Entrada: `patient_id` válido.
- Agenda consulta Pacientes y obtiene snapshot.
- Agenda crea la cita usando solo el snapshot mínimo.
- Pacientes no cambia.

### 3.2 Crear cita con paciente nuevo
- Agenda recibe datos mínimos (display_name, contacto masked).
- Hace `POST /patients` para crear identidad.
- Pacientes responde con `patient_id`.
- Agenda continúa creando la cita con `patient_id`.
- Errores: `invalid_params`, `db_not_ready` se deben propagar o degradar (cita provisional futuro).

### 3.3 Reprogramar / cancelar / no_show
- Solo Agenda ajusta estados de cita y agrega eventos.
- Pacientes no se modifica, salvo si el flujo requiere flag administrativo (Agenda crea flag y Pacientes lo puede mostrar aparte).

### 3.4 Citas históricas
- Agenda conserva todos los eventos; Pacientes solo expone su identidad.

## 4) Manejo de flags (referencia cruzada)
- `agenda_patient_flags` es propiedad de Agenda.
- Pacientes solo consulta resumen de flags (`Patient` puede incluir `flags_summary`).
- Pacientes no escribe flags directamente; Agenda los crea y eventualmente los notifica.

## 5) Reglas de visibilidad y seguridad
- Agenda siempre pide `visibility.contact = masked` de Pacientes.
- Si Pacientes degrada a `none`, Agenda examina contactos disponibles y ajusta notificaciones.
- Full solo en contextos distintos (p. ej. módulo administrativo fuera de Agenda).

## 6) Errores y degradación entre dominios
- `db_not_ready` en Pacientes puede bloquear la creación de citas, o bien activar un modo provisional (FUTURO).
- `not_found` obliga a crear paciente nuevo o rechazar la cita según política local.

## 7) Qué NO debe hacer Agenda
- No crea/edita contactos.
- No modifica consentimientos.
- No asume estructura interna del dominio Pacientes.
- No expone datos sensibles en respuestas.

## 8) Resumen operativo
- Pacientes manda identidad/contacto (masked); Agenda lee y crea citas.
- Agenda manda eventos/flags a dominios de auditoría/notificaciones.
- Pacientes jamás escribe eventos de cita; Agenda jamás duplica contacts.
