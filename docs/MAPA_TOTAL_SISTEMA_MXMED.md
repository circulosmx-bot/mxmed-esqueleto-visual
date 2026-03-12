# MAPA TOTAL DEL SISTEMA MXMED

## 0) Contexto, alcance y método
- Alcance: mapa verificable del estado actual del repo (arquitectura + evidencia técnica), sin implementar cambios funcionales.
- Método: inspección de `modules/*`, `api/*`, `assets/js/*`, `index.html`, `docs/*` y utilerías en raíz.
- Este documento combina:
  - claridad arquitectónica (para decisiones)
  - inventario técnico real (para ejecución)
- Estados usados:
  - `DONE`: módulo funcional y operando
  - `PARTIAL`: existe pero con huecos de integración o contrato
  - `FUTURO`: visual/prototipo sin backend operativo
  - `NO CONFIRMADO`: referencia documental sin implementación verificable
- Decisiones canónicas respetadas:
  - Agenda/Waitlist v1 `READY`.
  - Paciente canónico: `modules/patients` (`patients_patients.patient_id`).
  - Documentos clínicos canónicos: `clinical_documents`.
  - `modules/clinical` no debe duplicar identidad paciente.

## 1) Resumen arquitectónico general

### Núcleo funcional
1. Pacientes
2. Consultas (encounters)
3. Expediente clínico

Cadena principal:

Paciente  
↓  
Consulta  
↓  
Registro clínico  
↓  
Documentos / órdenes / resultados

### Módulos principales de alto nivel
- Patients (identidad administrativa)
- Agenda/Waitlist (operación de citas)
- Expediente UI (captura y visualización clínica)
- Clinical Documents (persistencia documental clínica)
- Clinical (integrador en transición)

### Flujo principal de negocio (alto nivel)
1. Alta/selección de paciente
2. Apertura de expediente
3. Inicio explícito de consulta
4. Registro de actividad clínica
5. Generación de documentos/órdenes/resultados
6. Consolidación en timeline
7. Cierre de consulta

## 2) Inventario por dominio / módulo / sección

### Dominio 1: Agenda / Waitlist
- Propósito: citas, disponibilidad, eventos auditables, flags y lista de espera.
- Archivos/rutas detectadas:
```text
api/agenda/index.php
api/agenda/ui/day.php
api/agenda/ui/appointment.php
api/agenda/ui/waitlist.php
api/agenda/ui/waitlist_assign_pick_day.php
api/agenda/ui/waitlist_assign_pick_slot.php
api/agenda/ui/action.php
modules/agenda/controllers/*.php
modules/agenda/repositories/*.php
modules/agenda/config/agenda.php
modules/agenda/sql/ready_schema.sql
modules/agenda/db/ready_schema.sql
modules/agenda/db/availability_bootstrap_min.sql
modules/agenda/db/availability_overrides_min.sql
docs/agenda/CIERRE_AGENDA_V1_ESTADO_FINAL.md
```
- DB:
```text
agenda_appointments
agenda_appointment_events
agenda_patient_flags
agenda_waitlist_entries
consultorio_schedule
agenda_availability_overrides
```
- API:
```text
GET    /api/agenda/index.php/appointments
GET    /api/agenda/index.php/appointments/{id}
POST   /api/agenda/index.php/appointments
PATCH  /api/agenda/index.php/appointments/{id}/reschedule
POST   /api/agenda/index.php/appointments/{id}/cancel
POST   /api/agenda/index.php/appointments/{id}/no_show
GET    /api/agenda/index.php/appointments/{id}/events
GET    /api/agenda/index.php/patients/{patient_id}/flags
GET    /api/agenda/index.php/consultorios
GET    /api/agenda/index.php/availability
GET    /api/agenda/index.php/waitlist
POST   /api/agenda/index.php/waitlist
PATCH  /api/agenda/index.php/waitlist/{id}
POST   /api/agenda/index.php/waitlist/{id}/assign
```
- UI:
```text
UI server-rendered operativa en api/agenda/ui/*
UI raíz (index.html, paneles p-ag-*) existe, pero no consume API Agenda de forma integral
```
- Estado: `DONE`
- Dependencias:
  - Consume `modules/patients` para crear paciente desde waitlist sin `patient_id`.
- Riesgos/notas:
  - Divergencia entre `modules/agenda/sql/ready_schema.sql` y `modules/agenda/db/ready_schema.sql`.
  - README con secciones desactualizadas respecto a implementación.

### Dominio 2: Patients (canónico de identidad)
- Propósito: identidad, contactos y vínculo médico-paciente.
- Archivos/rutas detectadas:
```text
modules/patients/db/ready_schema.sql
modules/patients/controllers/CreatePatientController.php
modules/patients/controllers/GetPatientController.php
modules/patients/controllers/GetDoctorPatientsController.php
modules/patients/repositories/PatientsRepository.php
api/patients/index.php
```
- DB:
```text
patients_patients
patients_contacts
patients_consents
patients_doctor_links
```
- API:
```text
POST /api/patients/index.php/patients
GET  /api/patients/index.php/patients/{patient_id}
GET  /api/patients/index.php/doctors/{doctor_id}/patients
```
- UI:
```text
index.html: paneles de pacientes y datos generales
Integración aún parcial entre runtime raíz y API patients
```
- Estado: `PARTIAL`
- Dependencias:
  - Base para Agenda, expediente clínico y relaciones por `patient_id`.
- Riesgos/notas:
  - Divergencia de naming con contratos clínicos legacy (`display_name/birthdate` vs `full_name/birth_date`).

### Dominio 3: Clinical Documents (canónico documental)
- Propósito: persistir/consultar documentos clínicos estructurados.
- Archivos/rutas detectadas:
```text
api/clinical-documents.php
api/evolution-note-generate.php
api/_lib/clinical_documents.php
api/_lib/clinical_documents_hospital.php
docs/contracts/clinical_documents/README.md
docs/contracts/clinical_documents/*.json
```
- DB:
```text
clinical_documents
clinical_document_participants
```
- API:
```text
POST /api/clinical-documents.php?action=save
GET  /api/clinical-documents.php?action=list&patient_id=...
GET  /api/clinical-documents.php?action=get&id=...
POST /api/evolution-note-generate.php (legacy)
```
- UI:
```text
index.html tab #t-notas
assets/js/app.js (nota evolución)
assets/js/manejo-hospitalario.js
```
- Estado: `DONE`
- Dependencias:
  - Depende de `patient_id` contextual.
- Riesgos/notas:
  - Sin FK explícita a `patients_patients`.
  - Wrapper de respuesta no totalmente homogéneo con estándar global.

### Dominio 4: Expediente UI avanzado
- Propósito: captura clínica multipestaña y contexto operativo del paciente.
- Archivos/rutas detectadas:
```text
index.html (t-datos, t-historia, t-gineco, t-exploracion, t-estudios, t-tratamiento, t-notas, t-manejo, t-consent, t-archivo)
assets/js/app.js
assets/js/core/navigation.js
assets/js/perfil/datos-generales.js
assets/js/manejo-hospitalario.js
```
- DB:
```text
Persistencia real consolidada: clinical_documents
Resto de secciones: parcial o DOM/localStorage
```
- API:
```text
Integrada: api/clinical-documents.php
Referenciada pero faltante en varios flujos históricos: api/hospital-stays.php, api/prescription-generate.php, api/ci/*
```
- UI: sí, amplia.
- Estado: `PARTIAL`
- Dependencias:
  - Contexto activo de paciente/consulta en frontend.
- Riesgos/notas:
  - Persistencia heterogénea (backend + localStorage + estado DOM).
  - Riesgo de IDs sintéticos en flujos legacy si no se fuerza `patient_id` canónico.

### Dominio 5: modules/clinical (integrador en transición)
- Propósito: ordenar dominio clínico estructurado y contratos API v1/v2.
- Archivos/rutas detectadas:
```text
modules/clinical/README.md
modules/clinical/docs/API_V1.md
modules/clinical/db/schema_v1.sql
modules/clinical/db/schema_v2.sql
modules/clinical/qa/requests.sh
```
- DB:
```text
schema_v1: clinical_patients, clinical_record_entries, clinical_consents
schema_v2: clinical_record_entries, clinical_consents (referencia a patients_patients)
```
- API:
```text
Contrato propuesto: /api/clinical/index.php
Implementación real completa en raíz: parcial/no uniforme por rutas
```
- UI: parcial (conectado por piezas del expediente y embed).
- Estado: `PARTIAL`
- Dependencias:
  - Debe alinearse a decisión de fuentes de verdad en `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`.
- Riesgos/notas:
  - `schema_v1` duplica paciente canónico.
  - Coexistencia v1/v2 aumenta deuda de convergencia.

### Dominio 6: Reviews / Opiniones
- Propósito: feedback/reputación del perfil.
- Archivos/rutas detectadas:
```text
index.html sección #p-opiniones
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Capa visual sin persistencia ni moderación backend.

### Dominio 7: Offers / Paquetes
- Propósito: productos/promociones comerciales.
- Archivos/rutas detectadas:
```text
index.html sección #p-paquetes
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Flujo mayormente maqueta.

### Dominio 8: Notificaciones
- Propósito: avisos y atajos de navegación.
- Archivos/rutas detectadas:
```text
index.html sección #p-Notificaciones
assets/js/messages.js
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Datos semilla/localStorage, sin fuente transaccional.

### Dominio 9: Seguridad / Roles / Verificación
- Propósito: verificación básica de acciones sensibles.
- Archivos/rutas detectadas:
```text
index.html sección #p-seguridad
api/verify-password.php
api/verify-sms.php
assets/js/perfil/consultorio/multisede.js
```
- DB de auth/RBAC: no confirmada en este repo.
- API: parcial (stubs).
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Endpoints de verificación no representan validación robusta de producción.

### Dominio 10: Suscripción / Planes
- Propósito: estado de plan y vistas de renovación.
- Archivos/rutas detectadas:
```text
index.html sección #p-suscripcion
assets/js/app.js (bloques de maqueta)
```
- DB/API: no detectadas integradas.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Lógica demo sin persistencia real.

### Dominio 11: Billing
- Propósito: facturación y datos fiscales.
- Archivos/rutas detectadas:
```text
index.html sección #p-facturacion
```
- DB/API: no detectadas operativas.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Panel visual sin timbrado/integración fiscal real.

### Dominio 12: Dashboard
- Propósito: resumen de actividad/completitud.
- Archivos/rutas detectadas:
```text
index.html sección #p-resumen
assets/js/core/dashboard.js
```
- DB/API: no detectadas.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Métricas de carácter demostrativo.

### Dominio 13: Archivo / Adjuntos
- Propósito: gestión de adjuntos del expediente.
- Archivos/rutas detectadas:
```text
index.html tab #t-archivo
index.html panel #p-pac-archivo
```
- DB/API: no detectadas completas.
- UI: parcial/placeholder.
- Estado: `FUTURO`
- Riesgos/notas:
  - Falta backend de archivos y modelo de metadatos.

### Dominio 14: Consultorio / multisede / geolocalización
- Propósito: datos de consultorio, sedes, CP/colonias, ubicación.
- Archivos/rutas detectadas:
```text
assets/js/perfil/consultorio/multisede.js
geocode-proxy.php
sepomex-local.php
sepomex-proxy.php
sepomex-import.php
assets/data/sepomex-fallback.json
```
- DB/API: parcial.
- UI: sí.
- Estado: `PARTIAL`
- Riesgos/notas:
  - Dependencia de servicios externos y almacenamiento local para parte del flujo.

### Dominio 15: docs/mock/prototipos
- Propósito: contratos, mocks y soporte QA/UX.
- Archivos/rutas detectadas:
```text
docs/contracts/clinical_documents/*
docs/mock/*
docs/assets/js/consentimientos.js
docs/assets/js/recetas.js
```
- Estado: `PARTIAL`
- Riesgos/notas:
  - Existen referencias de endpoints que no siempre existen en `api/` runtime.

## 3) Interconexiones de alto nivel
- Paciente -> Expediente: todo contexto clínico útil requiere `patient_id`.
- Expediente -> Consulta: el expediente puede existir sin consulta activa.
- Consulta -> Actividad: solo acciones clínicas exitosas deben actualizar actividad.
- Consulta -> Documentos/Órdenes/Resultados: asociación clínica por encounter cuando corresponda.
- Timeline <- Consulta + Documentos + Resultados: vista consolidada, no fuente primaria de captura.

## 4) Fuentes de verdad canónicas
- Paciente canónico: `patients_patients.patient_id` (`modules/patients`).
- Documento clínico canónico: `clinical_documents`.
- Consulta activa (frontend): `mxmedStore.activeEncounters`, `mxmedStore.currentEncounterKey`, contexto de expediente.
- Consulta activa (backend clínico): encounter en endpoints clínicos/agenda según flujo.
- Timeline: consolidación de eventos clínicos del paciente, no motor de captura.

## 5) Huecos de integración
1. Endpoints referenciados históricamente pero faltantes en runtime raíz (`api/hospital-stays.php`, `api/ci/*`, `api/prescription-generate.php`).
2. Paneles UI con backend incompleto o no conectado (facturación, paquetes, parte de seguridad/suscripción).
3. Persistencia parcial en cliente para secciones clínicas y de perfil.
4. Integración desigual entre UI raíz y APIs operativas (ej. Agenda server-rendered vs shell principal).
5. Secciones de expediente con estructura visual avanzada, pero sin backend estructurado homogéneo por sección.

## 6) Divergencias de contrato
- Wrappers estándar más consistentes:
```text
api/agenda/index.php
api/patients/index.php
```
- Wrappers no homogéneos o legacy:
```text
api/clinical-documents.php
api/evolution-note-generate.php
api/verify-password.php
api/verify-sms.php
```
- Nombres de campos divergentes detectados:
  - Patients: `display_name`, `birthdate`
  - Contratos clínicos/docs legacy: `full_name`, `birth_date` en algunas referencias
- Contrato clinical v1/v2 coexistente, con deuda de convergencia.

## 7) Riesgos de duplicidad
1. `patient_id` paralelo/sintético en frontend legacy si no se fuerza `patients_patients.patient_id`.
2. `clinical_patients` (v1) vs `patients_patients` (canónico).
3. Consentimientos potencialmente superpuestos (`patients_consents` vs `clinical_consents`).
4. `clinical_documents` sin FK explícita a identidad canónica.
5. Schemas Agenda duplicados con diferencias de cobertura de tablas.

## 8) Backlog propuesto de consolidación (documental)
1. Normalizar wrappers de respuesta sin romper compatibilidad.
2. Eliminar generación sintética de identidad y forzar `patient_id` canónico end-to-end.
3. Cerrar o retirar referencias a endpoints inexistentes.
4. Definir matriz explícita: qué vive en `clinical_documents` y qué en `modules/clinical` estructurado.
5. Consolidar documentación/esquemas divergentes (Agenda y Clinical v1/v2).
6. Integrar shell UI con APIs reales o declarar explícitamente paneles de maqueta.
7. Añadir validaciones de integridad cruzada entre módulos (paciente/encounter/documento/resultado).

## 9) Resumen ejecutivo
- Consolidado:
  - Agenda/Waitlist operativo.
  - Patients operativo en DB/API.
  - Clinical Documents operativo para notas/documentos.
- En transición:
  - Expediente multipestaña con mezcla de persistencia real y local.
  - Clinical estructurado (v1/v2) sin convergencia total.
- Pendiente clave:
  - Homologación de contratos y cierre de huecos de integración para lograr trazabilidad clínica completa.

## Anexo: Clinical Resolver v2 (determinismo por appointment)
- Endpoint:
  - `GET /api/clinical/index.php/encounters/appt:{appointment_id}`
- Regla canónica de selección:
  - `ORDER BY encounter_dt DESC, encounter_id DESC LIMIT 1`
- Extensión de timeline:
  - `has_encounter`
  - `latest_encounter_key`
- Principio:
  - El frontend no infiere encounter; consume señales explícitas del backend.
