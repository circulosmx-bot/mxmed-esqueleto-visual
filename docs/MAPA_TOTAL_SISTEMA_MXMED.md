# MAPA TOTAL DEL SISTEMA MXMED

## 0) Contexto, alcance y método
- Alcance de este documento: mapa verificable del estado actual del repo, sin implementación.
- Método aplicado: escaneo real de `modules/*`, `api/*`, `docs/*`, `assets/js/*`, `index.html` y rutas auxiliares en raíz.
- Estados usados: `DONE`, `PARTIAL`, `FUTURO`, `NO CONFIRMADO`.
- Decisiones canónicas respetadas:
  - Agenda/Waitlist v1 `READY` y congelada.
  - Paciente canónico: `modules/patients`.
  - Documentos clínicos canónicos: `clinical_documents`.
  - `modules/clinical` no debe duplicar paciente.

## 1) Estructura relevante detectada
- `modules/agenda`, `modules/patients`, `modules/clinical`.
- `api/agenda/index.php`, `api/patients/index.php`, `api/clinical-documents.php`, `api/evolution-note-generate.php`, `api/verify-password.php`, `api/verify-sms.php`.
- UI principal: `index.html` con scripts en `assets/js/*`.
- UI Agenda server-rendered: `api/agenda/ui/*`.
- Contratos/documentación: `docs/contracts/clinical_documents/*`, `docs/agenda/*`, `docs/db/*`, `docs/clinical/*`.
- SQL adicional legacy: `database/agenda/*`.
- Auxiliares de geolocalización/CP: `geocode-proxy.php`, `sepomex-local.php`, `sepomex-proxy.php`, `sepomex-import.php`.

## 2) Inventario por dominio / módulo / sección

### Dominio 1: Agenda / Waitlist
- Nombre humano: Agenda Médica v1 + Waitlist v1.
- Propósito: operación de citas, disponibilidad, eventos auditables, flags y lista de espera.
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
- DB: sí.
```text
agenda_appointments
agenda_appointment_events
agenda_patient_flags
agenda_waitlist_entries
consultorio_schedule
agenda_availability_overrides
```
- API: sí.
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
- UI: sí.
```text
UI server-rendered funcional en api/agenda/ui/*
UI raíz (index.html, paneles p-ag-*) existe, pero no consume API Agenda
```
- Estado: `DONE`.
- Dependencias:
  - Consume: `modules/patients` para crear paciente desde waitlist sin `patient_id` (`modules/agenda/helpers/patients_client.php`).
  - Requerido por: operación de agenda y flujos de espera.
- Riesgos/Notas:
  - Divergencia de schema: `modules/agenda/sql/ready_schema.sql` sí incluye `agenda_waitlist_entries`; `modules/agenda/db/ready_schema.sql` no.
  - `modules/agenda/README.md` contiene partes desactualizadas frente al estado real implementado.

### Dominio 2: Patients (canónico de identidad)
- Nombre humano: Pacientes administrativos.
- Propósito: identidad de paciente, contactos, vínculos médico-paciente y consentimientos administrativos.
- Archivos/rutas detectadas:
```text
modules/patients/db/ready_schema.sql
modules/patients/controllers/CreatePatientController.php
modules/patients/controllers/GetPatientController.php
modules/patients/controllers/GetDoctorPatientsController.php
modules/patients/repositories/PatientsRepository.php
api/patients/index.php
```
- DB: sí.
```text
patients_patients
patients_contacts
patients_consents
patients_doctor_links
```
- API: sí.
```text
POST /api/patients/index.php/patients
GET  /api/patients/index.php/patients/{patient_id}
GET  /api/patients/index.php/doctors/{doctor_id}/patients
```
- UI: parcial.
```text
index.html: tab #t-datos y paneles de pacientes
No se detecta consumo directo de /api/patients desde assets/js de la UI raíz
```
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: DB propia `patients_*`.
  - Requerido por: Agenda/Waitlist, Clinical v2, clinical_documents (por convención de patient_id).
- Riesgos/Notas:
  - Contrato maestro clínico usa nombres `full_name`/`birth_date`; backend actual de Patients usa `display_name`/`birthdate`.
  - Integración UI raíz con API de Patients no está cerrada.

### Dominio 3: Clinical Documents (canónico documental)
- Nombre humano: Motor documental clínico.
- Propósito: persistir y recuperar documentos clínicos estructurados con payload JSON y timeline.
- Archivos/rutas detectadas:
```text
api/clinical-documents.php
api/evolution-note-generate.php
api/_lib/clinical_documents.php
api/_lib/clinical_documents_hospital.php
api/_lib/README_clinical_documents.md
docs/contracts/clinical_documents/README.md
docs/contracts/clinical_documents/*.json
```
- DB: sí (creación runtime).
```text
clinical_documents
clinical_document_participants
```
- API: sí.
```text
POST /api/clinical-documents.php?action=save
GET  /api/clinical-documents.php?action=list&patient_id=...
GET  /api/clinical-documents.php?action=get&id=...
POST /api/evolution-note-generate.php (legacy)
```
- UI: sí.
```text
index.html tab #t-notas (nota_evolucion)
assets/js/app.js (initNotaEvolucion)
assets/js/manejo-hospitalario.js (nota intrahospitalaria / hoja indicaciones)
```
- Estado: `DONE`.
- Dependencias:
  - Consume: `patient_id` del flujo UI/contexto.
  - Requerido por: expediente UI (timeline/notas), manejo hospitalario.
- Riesgos/Notas:
  - No hay FK explícita desde `clinical_documents.patient_id` a `patients_patients.patient_id`.
  - Contrato JSON de respuesta no está normalizado al wrapper global `{ok,error,message,data,meta}`.

### Dominio 4: Expediente UI avanzado (tabs clínicos)
- Nombre humano: Expediente médico UI (captura clínica multipestaña).
- Propósito: captura operativa de datos clínicos en tabs del expediente.
- Archivos/rutas detectadas:
```text
index.html (tabs: t-datos, t-historia, t-gineco, t-exploracion, t-estudios, t-tratamiento, t-notas, t-manejo, t-consent, t-archivo)
assets/js/app.js
assets/js/core/navigation.js
assets/js/manejo-hospitalario.js
```
- DB: parcial.
```text
Persistencia real detectada: clinical_documents
Resto de secciones: sin tablas dedicadas detectadas
```
- API: parcial.
```text
Sí: api/clinical-documents.php
Referenciados pero inexistentes: api/hospital-stays.php, api/prescription-generate.php, api/ci/*
```
- UI: sí.
```text
index.html implementa toda la estructura visual de tabs y formularios
```
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: clinical_documents, localStorage, contexto de paciente en DOM.
  - Requerido por: consolidación clínica futura en modules/clinical.
- Riesgos/Notas:
  - Persistencia heterogénea: combinación de backend real + localStorage + estado DOM.
  - En varios flujos, `patient_id` se sintetiza desde nombre/fecha/sexo (riesgo de IDs paralelos).

### Dominio 5: modules/clinical (nuevo integrador)
- Nombre humano: Módulo Clinical integrador (nuevo).
- Propósito: ordenar dominio clínico faltante y normalizar contratos API.
- Archivos/rutas detectadas:
```text
modules/clinical/README.md
modules/clinical/docs/API_V1.md
modules/clinical/db/schema_v1.sql
modules/clinical/db/schema_v2.sql
modules/clinical/qa/requests.sh
```
- DB: sí (documental/DDL, sin despliegue confirmado).
```text
schema_v1: clinical_patients, clinical_record_entries, clinical_consents
schema_v2: clinical_record_entries, clinical_consents (FK a patients_patients)
```
- API: no implementación real.
```text
Contrato propuesto en docs: /api/clinical/index.php
Ruta real api/clinical/index.php: no detectada
```
- UI: no.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: decisión canónica en `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`.
  - Requerido por: consolidación clínica estructurada pendiente.
- Riesgos/Notas:
  - `schema_v1.sql` y `API_V1.md` aún modelan `clinical_patients` (duplica paciente canónico).
  - `schema_v2.sql` sí está alineado a `patients_patients.patient_id`.
  - `modules/clinical/README.md` quedó desactualizado frente a archivos ya existentes.

### Dominio 6: Reviews / Opiniones
- Nombre humano: Opiniones del perfil médico.
- Propósito: supervisión y respuesta de opiniones.
- Archivos/rutas detectadas:
```text
index.html sección #p-opiniones
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: navegación/UI general.
  - Requerido por: experiencia de perfil y reputación.
- Riesgos/Notas:
  - Sección visual sin persistencia real ni moderación backend.

### Dominio 7: Offers / Paquetes / Promociones
- Nombre humano: Paquetes y promociones.
- Propósito: gestión comercial de paquetes/promos.
- Archivos/rutas detectadas:
```text
index.html sección #p-paquetes
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: navegación/UI general.
  - Requerido por: monetización comercial.
- Riesgos/Notas:
  - Flujo actualmente maqueta; no hay persistencia de catálogo/ofertas.
  - Elementos marcados con IA se muestran como deshabilitados (FUTURO).

### Dominio 8: Notificaciones
- Nombre humano: Centro de notificaciones.
- Propósito: mostrar avisos y acciones rápidas.
- Archivos/rutas detectadas:
```text
index.html sección #p-Notificaciones
assets/js/messages.js
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: `localStorage` (`mxmed_msgs_read`) y navegación (`jumpTo`).
  - Requerido por: UX de alertas.
- Riesgos/Notas:
  - Datos semilla hardcodeados; no hay fuente transaccional ni trazabilidad backend.

### Dominio 9: Roles / Permisos / Auth / Seguridad
- Nombre humano: Seguridad de cuenta y verificación.
- Propósito: control de verificación básica en UI.
- Archivos/rutas detectadas:
```text
index.html sección #p-seguridad
assets/js/perfil/consultorio/multisede.js
api/verify-password.php
api/verify-sms.php
```
- DB: no detectada para auth/roles.
- API: parcial.
```text
POST/GET api/verify-password.php (stub)
POST/GET api/verify-sms.php (stub)
```
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: formularios de seguridad/consultorio.
  - Requerido por: acciones sensibles de UI.
- Riesgos/Notas:
  - Endpoints de verificación son stubs (aceptan cualquier valor no vacío).
  - No se detecta RBAC/auth canónico en backend.

### Dominio 10: Planes / Suscripción / Estado del perfil
- Nombre humano: Suscripción y planes.
- Propósito: mostrar estado de plan, renovaciones y catálogo comercial.
- Archivos/rutas detectadas:
```text
index.html sección #p-suscripcion
assets/js/app.js (bloque "SUSCRIPCIÓN (maqueta)")
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: datos mock in-memory en JS.
  - Requerido por: vistas de cuenta y facturación UX.
- Riesgos/Notas:
  - Lógica demo sin persistencia real ni integraciones de cobro.

### Dominio 11: Billing plataforma / Billing clínica
- Nombre humano: Facturación.
- Propósito: captura/listado de CFDI y directorio fiscal.
- Archivos/rutas detectadas:
```text
index.html sección #p-facturacion
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: paneles y formularios UI.
  - Requerido por: operación administrativa de cobros/facturas.
- Riesgos/Notas:
  - Panel completamente visual; no hay timbrado real ni almacenamiento.

### Dominio 12: Dashboard / Actividad
- Nombre humano: Resumen de actividad y completitud.
- Propósito: mostrar métricas visuales y nudge de acciones.
- Archivos/rutas detectadas:
```text
index.html sección #p-resumen
assets/js/core/dashboard.js
```
- DB: no detectada.
- API: no detectada.
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: valores JS (`window.mxm_sec`) y navegación.
  - Requerido por: home dashboard.
- Riesgos/Notas:
  - Métricas estáticas/demostrativas, no conectadas a datos reales.

### Dominio 13: Adjuntos / Archivo
- Nombre humano: Archivo del expediente y adjuntos.
- Propósito: gestión de archivos del paciente.
- Archivos/rutas detectadas:
```text
index.html tab #t-archivo
index.html panel #p-pac-archivo
```
- DB: no detectada.
- API: no detectada.
- UI: parcial/placeholder.
- Estado: `FUTURO`.
- Dependencias:
  - Consume: navegación de pacientes/expediente.
  - Requerido por: expediente completo y compliance documental.
- Riesgos/Notas:
  - No hay backend de archivos ni modelo de metadatos.

### Dominio 14: Consultorio / Perfil multisede + geolocalización
- Nombre humano: Perfil profesional y consultorios.
- Propósito: captura de datos de consultorio, horarios, ubicación, CP/colonias.
- Archivos/rutas detectadas:
```text
index.html secciones de perfil y consultorio
assets/js/app.js
assets/js/perfil/consultorio/multisede.js
geocode-proxy.php
sepomex-local.php
sepomex-proxy.php
sepomex-import.php
assets/data/sepomex-fallback.json
```
- DB: parcial.
```text
Tabla opcional local: sepomex
No se detecta tabla canónica de consultorios en backend del repo
```
- API: parcial.
```text
GET geocode-proxy.php?q=...
GET sepomex-local.php?cp=.....
GET sepomex-proxy.php?cp=.....
POST/GET api/verify-password.php y api/verify-sms.php (para flujos de borrado en UI)
```
- UI: sí.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: servicios externos (Nominatim, SEPOMEX), localStorage y stubs de verify.
  - Requerido por: configuración de sedes/consultorio.
- Riesgos/Notas:
  - Persistencia principal sigue en cliente para varias partes.
  - Integración parcial con backend real.

### Dominio 15: Entorno docs/mock y prototipos
- Nombre humano: Activos de documentación y mock.
- Propósito: contratos JSON, mocks y referencia visual.
- Archivos/rutas detectadas:
```text
docs/contracts/clinical_documents/*
docs/mock/*
docs/assets/js/consentimientos.js
docs/assets/js/recetas.js
docs/assets/js/core/context.js
```
- DB: no aplica.
- API: contratos/mocks, no implementación real en `api/` para varias rutas referenciadas.
- UI: parcial.
- Estado: `PARTIAL`.
- Dependencias:
  - Consume: documentación clínica y front prototipo.
  - Requerido por: definición funcional y QA manual.
- Riesgos/Notas:
  - Existen scripts de consentimiento/recetas en `docs/assets/js`, pero no están cargados por el `index.html` raíz.
  - Endpoints referenciados por esos scripts (`api/ci/*`, `api/prescription-generate.php`) no existen en `api/`.

## 3) Integración de fuentes internas obligatorias (estado)
- `docs/expediente_inventario_existente.md`: alineado; se confirman secciones DONE/PARTIAL y huecos.
- `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`: alineado; paciente canónico y documentos canónicos respetados como base arquitectónica.
- `docs/modulo_a_pacientes_expedientes_consentimientos_v1.md`: tomado como contrato maestro clínico v1.
- `modules/clinical/docs/API_V1.md`: existe como contrato, pero contiene divergencia con la decisión canónica (usa `clinical_patients`).
- `modules/clinical/db/schema_v1.sql` y `modules/clinical/db/schema_v2.sql`: coexisten; v2 alinea canónico, v1 duplica paciente.
- `modules/patients/db/ready_schema.sql`: confirma tabla/PK canónica `patients_patients.patient_id`.
- `docs/contracts/clinical_documents/*`: confirma contrato documental actual en producción para notas/documentos.

## 4) Secciones obligatorias finales

### A) HUECOS DE INTEGRACIÓN (priorizados)
1. `P0` UI clínica avanzada sin backend estructurado por sección fuera de `clinical_documents`.
2. `P0` Endpoints referenciados pero inexistentes: `api/clinical/index.php`, `api/hospital-stays.php`, `api/ci/*`, `api/prescription-generate.php`.
3. `P0` Persistencia parcial en cliente (`localStorage`, DOM-only) en varias secciones del expediente/perfil.
4. `P1` UI raíz de Agenda (`p-ag-*`) no está integrada al API real de Agenda; la UI operativa real está en `api/agenda/ui/*`.
5. `P1` Consentimiento informado del expediente (#t-consent) tiene estructura UI, pero sin integración JS/backend en runtime raíz.

### B) DIVERGENCIAS DE CONTRATO
- Endpoints que sí siguen `{ok,error,message,data,meta}`:
```text
api/agenda/index.php
api/patients/index.php
```
- Endpoints que no siguen completamente el wrapper estándar:
```text
api/clinical-documents.php (devuelve ok + document/items/error, sin message/data/meta uniforme)
api/evolution-note-generate.php (ok + document_id/document_uuid)
api/verify-password.php (solo {ok})
api/verify-sms.php (solo {ok})
```
- Contrato legacy vs estándar:
  - Superficie clínica documental actual usa contrato legacy funcional.
  - Contrato estándar está documentado en módulo clinical, pero no implementado en `api/clinical/index.php`.

### C) RIESGOS DE DUPLICIDAD
1. IDs paralelos de paciente en UI: se sintetiza `patient_id` desde nombre/fecha/sexo en JS de expediente, en lugar de usar exclusivamente `patients_patients.patient_id`.
2. Duplicidad de fuente de verdad en diseño clinical:
   - `schema_v1.sql` / `API_V1.md` modelan `clinical_patients`.
   - Decisión canónica exige no duplicar paciente y referenciar `patients_patients.patient_id`.
3. Consentimientos potencialmente duplicados por dominio:
   - `patients_consents` (administrativo).
   - `clinical_consents` (clínico, propuesto en schema v2).
4. `clinical_documents` sin FK explícita a `patients_patients` incrementa riesgo de desalineación de identidad.
5. Duplicidad de artefactos de schema Agenda (`modules/agenda/sql/ready_schema.sql` vs `modules/agenda/db/ready_schema.sql`) con cobertura distinta de tablas.

### D) BACKLOG PROPUESTO DE CONSOLIDACIÓN (sin implementar)
1. `FUTURO` Formalizar matriz única de contratos API y normalizar todas las respuestas a `{ok,error,message,data,meta}` sin romper compatibilidad existente.
2. `FUTURO` Forzar uso de `patients_patients.patient_id` en UI clínica (eliminar generación sintética de IDs).
3. `FUTURO` Cerrar brecha de endpoints faltantes (`api/clinical/index.php`, `api/ci/*`, `api/hospital-stays.php`, `api/prescription-generate.php`) o retirar referencias para evitar deuda activa.
4. `FUTURO` Definir qué secciones del expediente vivirán en `clinical_documents` y cuáles en `modules/clinical` estructurado (v2), sin duplicar paciente/documentos canónicos.
5. `FUTURO` Consolidar artefactos de schema/documentación divergentes (`schema_v1` vs `schema_v2`, README desactualizados, schema Agenda duplicado).
6. `FUTURO` Integrar UI raíz con APIs reales por dominio o declarar explícitamente qué paneles se mantienen como maqueta.
7. `FUTURO` Incorporar verificación de integridad entre dominios (patient_id canónico en Agenda, Clinical Documents y Clinical estructurado) y validaciones de no-duplicidad.

## 5) Resumen ejecutivo (qué ya existe vs qué falta)
- Qué ya existe:
  - Agenda/Waitlist backend + UI operativa server-rendered en estado `READY`.
  - Dominio Patients funcional a nivel DB/API.
  - Motor `clinical_documents` funcional para notas/documentos clínicos.
  - UI raíz extensa con múltiples dominios y tabs clínicos.
- Qué falta para consolidación total:
  - Integración backend real para secciones UI hoy locales/placeholder.
  - Normalización de contratos API clínicos y cierre de endpoints faltantes.
  - Alineación definitiva a fuentes canónicas para evitar duplicidad de identidad y contratos.
