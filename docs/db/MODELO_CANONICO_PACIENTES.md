# Modelo Canónico — Pacientes

## 1) Qué es Pacientes
- Propósito: mantener la identidad, contacto y consentimiento de cada paciente, y servir como origen oficial del vínculo médico↔paciente.
- No le corresponde: contenido clínico (EHR), recetas, citas, billing o autenticación de usuarios. Es una capa de datos administrativos.

## 2) Entidades canónicas
- **Paciente (Patient):** datos básicos y estatus del individuo.
- **Contacto del paciente (Patient Contact):** teléfonos, correos y canales preferidos.
- **Consentimientos (Patient Consents):** registros de permiso para comunicaciones (contacto) y aceptación de términos/privacidad, con versión y timestamp.
- **Vínculo médico↔paciente (Doctor-Patient Link):** relación activa que autoriza a un médico a ver un paciente.
- **Nota administrativa (Administrative Note):** anotaciones no clínicas (recordatorios de documentación, alertas administrativas).

## 3) Identidades y referencias
- Identidad canónica: `patient_id` (string).
- Referencias externas: `doctor_id` (para el vínculo). `consultorio_id` y `appointment_id` pueden aparecer como referencias de contexto en otros dominios o reportes; Pacientes no depende de ellos como identidad.
- Regla: Pacientes no crea `doctor_id`; solo lo referencia mediante vínculos creados por roles autorizados.

## 4) Propiedad de datos
- Vive aquí: contacto actualizado, historial de consentimientos, estado del vínculo médico➜paciente (activo/inactivo/bloqueado).
- No se duplica: lo clínico se guarda en `ehr_*`, las citas en `agenda_*`, los pagos en `billing_*`.

## 5) Reglas mínimas de privacidad y acceso
- Solo un doctor puede ver pacientes con vínculo activo (al menos mientras el vínculo exista).
- Operadores trabajan “en nombre” del doctor y su acción debe auditarse externamente (actor_role/actor_id en `audit_*`).
- Datos sensibles (teléfono/email/fecha de nacimiento) se manejan con permiso explícito.
- Antes de enviar avisos directos, debe existir `consent_contact` válido.

## 6) Operaciones canónicas
- Crear paciente: datos mínimos (nombre y contacto si existe) y estatus inicial.
- Actualizar contacto: actualizar teléfono/correo con registro de quién lo hizo.
- Registrar consentimientos: guardar timestamp/version cuando el paciente acepta un canal.
- Vincular paciente a doctor: crea una relación activa y registra creado_por.
- Bloquear/desvincular: marca el vínculo como inactivo sin borrar el historial.
- Consultar lista de pacientes de un doctor (visión futura): devolver `patient_id` + contacto mínimo.

## 7) Integraciones futuras
- **Agenda:** usa `patient_id` en citas y flags; no se duplican datos de contacto.
- **EHR:** enlaza notas/diagnósticos a `patient_id`, referencia `appointment_id` cuando se necesita contexto de cita.
- **Billing:** factura servicios por `patient_id`; consulta contacto solo si el paciente autorizó recibir comprobantes.
- **Auth:** un login del paciente podría referenciar `patient_id` y obtener consentimiento.
- **Notificaciones:** respeta consentimientos antes de emitir mensajes.

## 8) Crecimiento y trazabilidad
- Crecimiento alto: pacientes nuevos, consentimientos y vínculos.
- Recomendación: documentar cambios sensibles en `audit_*` o eventos internos antes de borrar información.

## 9) Límites y no-alcance
- No hay UI ni migraciones complejas.
- No se toca el router global ni el contrato JSON.
- Mantener coherencia con `docs/db/CONVENCION_DB.md` y `docs/db/MAPA_DOMINIOS_DATOS.md`.
