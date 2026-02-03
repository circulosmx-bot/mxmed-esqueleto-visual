# Mapa Maestro de Dominios de Datos — México Médico

## A) Principios
- Este documento NO define tablas ni columnas; describe dominios, quién produce y quién consume cada dato.
- Cada dominio es la fuente de verdad de sus datos; otros dominios pueden referenciar pero no duplicar.

## B) Dominios

### Dominio: Auth & Seguridad (`auth_*`)
- Propósito: controlar quién entra al sistema y qué puede hacer.
- Entidades: usuarios, roles, sesiones/tokens, política de contraseñas.
- Datos sensibles: credenciales, tokens de sesión, dispositivos.
- Escribe: sistemas de identidad, admins, despliegues.
- Lee: todos los servicios que validan acceso.
- Relación: entrega identidad a todos los demás dominios; registra accesos en auditoría (`audit_*`).
- Crecimiento: medio (usuarios + sesiones activas).
- Notas: debe integrar auditoría en cada operación crítica.

### Dominio: Perfiles públicos (`profiles_*`)
- Propósito: mostrar la cara pública de médicos y consultorios.
- Entidades: perfil médico, especialidades, ubicaciones, SEO/slug de perfil.
- Datos sensibles: imágenes/identidad del profesional (controlar notoriedad).
- Escribe: médicos/operadores y CMS editorial.
- Lee: pacientes, buscadores, operaciones de agenda.
- Relación: enlaza a consultorios y agenda para mostrar disponibilidad; alimenta notificaciones.
- Crecimiento: medio.

### Dominio: Consultorios (`consultorios_*`)
- Propósito: describir cada espacio físico y su configuración base.
- Entidades: consultorios, dirección, teléfonos, horarios base (catálogo).
- Datos sensibles: ubicaciones privadas (no publicar direcciones confidenciales).
- Escribe: consultorios/operadores.
- Lee: agenda, perfiles, pacientes, notificaciones.
- Relación: suministra horarios base a Agenda y se muestra en Perfiles; alimenta catálogos de Notificaciones.
- Crecimiento: bajo.

### Dominio: Agenda (`agenda_*`)
- Propósito: coordinar la creación, reprogramación, cancelación y auditoría de citas.
- Entidades: citas, eventos (bitácora), flags de paciente, operadores.
- Datos sensibles: motivos de cancelación, flags de riesgo.
- Escribe: médicos, operadores, automatismos (hooks de notificaciones).
- Lee: pacientes, notificaciones, billing para pagos.
- Relación: lee horarios de consultorios; produce eventos para auditoría y notificaciones; entrega datos a billing y flags.
- Crecimiento: alto (citas diarias y bitácora append-only).

### Dominio: Pacientes (`patients_*`)
- Propósito: identificar pacientes y su vínculo con médicos.
- Entidades: paciente, datos de contacto, consentimiento de comunicación, vínculos y notas administrativas.
- Datos sensibles: datos personales, consentimiento legal.
- Escribe: operadores, pacientes (autogestión), servicios clínicos.
- Lee: agenda, billing, notificaciones, expediente clínico.
- Relación: alimenta agenda y ticketing de pagos; provoca flags cuando hay no-show.
- Crecimiento: alto.

### Dominio: Expediente clínico (`ehr_*`)
- Propósito: guardar evidencias clínicas (notas, diagnósticos, signos vitales, adjuntos).
- Entidades: notas, diagnósticos, plan terapéutico, adjuntos e imágenes.
- Datos sensibles: historia clínica completa.
- Escribe: médicos, operadores clínicos.
- Lee: pacientes (autoconsulta), billing (por contexto), auditoría.
- Relación: enlaza con pacientes y cita (agenda); genera eventos auditables en `audit_*`.
- Crecimiento: medio-alto.

### Dominio: Recetas (`rx_*`)
- Propósito: registrar prescripciones y su trazabilidad.
- Entidades: receta, medicamentos, firma/QR, estados (pendiente, entregada).
- Datos sensibles: medicamentos recetados.
- Escribe: médicos y operadores farmacéuticos.
- Lee: pacientes, billing (para cobros), notificaciones.
- Relación: deriva de agenda y expediente, informa a billing/notificaciones.
- Crecimiento: medio.

### Dominio: Estudios (`ehr_*` o `labs_*` y `imaging_*`)
- Propósito: administrar ordenes de laboratorio e imagen y sus resultados.
- Entidades: órdenes, resultados, adjuntos, referencias cruzadas.
- Datos sensibles: resultados médicos.
- Escribe: médicos, laboratorios, proveedores externos.
- Lee: pacientes, expediente y billing.
- Relación: enlaza expediente/pacientes; produce datos para billing y notificaciones (resultado listo).
- Crecimiento: medio.

### Dominio: Facturación y pagos (`billing_*`)
- Propósito: manejar facturas, pagos, folios y comprobantes.
- Entidades: facturas, pagos, reembolsos, recibos electrónicos.
- Datos sensibles: montos y medios de pago.
- Escribe: operadores, módulos automáticos de cobro.
- Lee: pacientes, agenda, expediente para validar servicios.
- Relación: consume datos de agenda/recetas/estudios; alimenta auditoría/notifications.
- Crecimiento: medio.

### Dominio: Plan & anualidad (`plans_*` / `billing_*`)
- Propósito: documentar planes contratados, fechas de corte y renovaciones.
- Entidades: plan contratado, periodo activo, activación de features.
- Datos sensibles: beneficios contractuales.
- Escribe: operaciones comerciales.
- Lee: billing, agenda (para habilitar funciones) y notificaciones (renovaciones).
- Relación: dicta qué features (p. ej. overrides) están habilitados; dialoga con notifications y billing.
- Crecimiento: bajo.

### Dominio: Opiniones (`reviews_*`)
- Propósito: capturar reseñas y moderación.
- Entidades: reseñas, moderaciones, indicadores de interacción.
- Datos sensibles: opiniones públicas (requieren control de abuso).
- Escribe: pacientes y moderadores.
- Lee: perfiles, notificaciones, auditoría.
- Relación: enlaza paciente/perfil; afecta notificaciones y auditoría.
- Crecimiento: medio.

### Dominio: Notificaciones / Buzón (`notifications_*`)
- Propósito: entregar alertas y avisos en bandeja.
- Entidades: mensajes, triggers, preferencias.
- Datos sensibles: contenido de mensajes personales.
- Escribe: todos los dominios que alertan (agenda, billing, auth).
- Lee: pacientes, operadores, auditoría.
- Relación: se nutre de agenda/billing/auth; entrega contexto a pacientes y operadores.
- Crecimiento: alto (mensajes frecuentes).

### Dominio: Auditoría global (`audit_*`)
- Propósito: conservar trazabilidad de acciones críticas en todo el sistema.
- Entidades: bitácoras de acciones, eventos syslog, errores.
- Datos sensibles: acciones administrativas.
- Escribe: módulos que modifican datos (agenda, auth, billing, etc.).
- Lee: compliance, seguridad, operaciones.
- Relación: recolecta eventos de todos los dominios anteriores; guía alertas y revisiones.
- Crecimiento: alto.
