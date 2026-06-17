# MAPEO EXPEDIENTE MXMED

## 1) Objetivo del módulo
Levantar el estado real implementado del módulo Expediente en MXMed para documentar:
- archivos reales involucrados,
- pantallas y flujo UI vigentes,
- endpoints consumidos,
- tablas relacionadas,
- frontera Expediente vs Consulta,
- interconexiones con Pacientes, Agenda y Clinical.

Este documento es descriptivo (sin cambios funcionales).

## 2) Inventario de archivos

### Shell principal y UI de Expediente
- `index.html`
  - Propósito: define panel `#p-expediente`, tabs clínicos/administrativos y barra P10 embebida del historial.
  - Relación: es la superficie principal del Expediente en la app shell.

- `assets/js/app.js`
  - Propósito: wiring runtime del expediente (`setActivePatientId`, guardas, header contextual, banda de consultas activas, tabs, búsqueda/abrir expediente).
  - Relación: orquestador principal del estado de Expediente en frontend.

- `assets/js/perfil/datos-generales.js`
  - Propósito: captura de identidad en tab Datos Generales y guardado explícito del paciente.
  - Relación: puerta administrativa al expediente (nuevo paciente / alta) con normalización y validación frontend de Nombre(s), Primer apellido y Segundo apellido.

### Vistas clínicas embebidas dentro del Expediente
- `modules/clinical/ui/historial.php`
  - Propósito: timeline clínico consolidado por `patient_id`; resuelve `patient_id` desde `encounter_key` cuando falta.
  - Relación: subvista principal del tab “Historial de atención”.

- `modules/clinical/ui/encounter.php`
  - Propósito: detalle del episodio/consulta (`encounter_key`), documentos del episodio, cierre de consulta.
  - Relación: subvista del modo “Episodio” en el embed del expediente.

- `modules/clinical/ui/document.php`
  - Propósito: detalle de documento clínico individual.
  - Relación: subvista del modo “Documento” en el embed del expediente.

- `modules/clinical/ui/viewer.php`
  - Propósito: visor de documento/bundle clínico.
  - Relación: navegación secundaria desde documento/encounter dentro del contexto clínico.

- `modules/clinical/ui/timeline.php`
  - Propósito: vista timeline standalone de soporte.
  - Relación: referencia funcional paralela al historial embebido.

### Helpers / parciales de soporte
- `modules/_partials/clinical_embed.php`
  - Propósito: utilidades de modo embed (`embed=1`, carry params, envoltura visual).
  - Relación: contrato técnico para incrustar historial/encounter/document en el expediente shell.

### APIs consumidas por Expediente
- `api/patients/index.php`
  - Propósito: alta/listado/lectura de pacientes.
  - Relación: fuente de identidad canónica (`patient_id`) usada por Expediente.

- `api/clinical/index.php`
  - Propósito: timeline, encounters, documentos, casos y lifecycle clínico.
  - Relación: backend clínico del bloque embebido del expediente.

- `api/agenda/index.php` (consumo indirecto desde `encounter.php` por controllers Agenda)
  - Propósito: detalle/eventos de cita.
  - Relación: aporta contexto de cita en el episodio clínico.

### Documentación relevante
- `docs/ARQUITECTURA_ESTADOS_EXPEDIENTE.md`
- `docs/AGENDA_COMO_PUERTA_DE_ENTRADA_CLINICA_MXMED.md`
- `docs/clinical/encounters.md`
- `docs/expediente_inventario_existente.md`

## 3) Endpoints reales consumidos por Expediente

## 3.1 Pacientes (contexto administrativo)
- `GET /api/patients/index.php/doctors/{doctor_id}/patients?limit=...`
  - Uso: búsqueda en “Archivo de pacientes” (`assets/js/app.js`).
  - Propósito: seleccionar paciente para abrir expediente.

- `POST /api/patients/index.php/patients`
  - Uso: botón “Guardar paciente” en Datos Generales (`assets/js/perfil/datos-generales.js`).
  - Propósito: alta explícita de paciente sin iniciar consulta.
  - Validación vigente:
    - frontend bloquea nombres inválidos antes del POST con `window.mxmedPatientNameTools`;
    - backend revalida `display_name` con `PatientNameValidator.php`;
    - nombres inválidos responden HTTP `422`, `error:"invalid_params"` y mensaje `Captura un nombre de paciente válido.`.

- `POST /api/patients/index.php/patients/{patient_id}/profile`
  - Uso: edición de perfil administrativo desde Datos Generales.
  - Propósito: actualizar campos de perfil del paciente existente.
  - Validación vigente:
    - se validan `first_name`, `paternal_last_name` y `maternal_last_name` si vienen presentes;
    - `maternal_last_name` vacío sigue permitido;
    - campos no relacionados, como ocupación o estado civil, no forman parte de esta validación de nombres.

- `GET /api/patients/index.php/patients/{patient_id}`
  - Disponible en módulo Pacientes; utilizable para lectura canónica de identidad.

## 3.2 Clinical (historial/encounter/documentos)
- `GET /api/clinical/index.php/patients/{patient_id}/timeline?include=agenda,clinical&limit=...`
  - Uso: `modules/clinical/ui/historial.php`.
  - Propósito: alimentar timeline consolidado.

- `GET /api/clinical/index.php/patients/{patient_id}/encounters/active`
  - Uso: barra P10 en `index.html` + `ensureActiveEncounter` en `assets/js/app.js` + `historial.php`.
  - Propósito: detectar consulta activa del paciente.

- `POST /api/clinical/index.php/patients/{patient_id}/encounters`
  - Uso: iniciar consulta desde P10/flujo clínico explícito.
  - Propósito: crear encounter abierto (idempotente por regla de active/open).

- `GET /api/clinical/index.php/encounters/{encounter_key}`
  - Uso: `encounter.php`, `historial.php` (resolución patient), `app.js` (payload activo).
  - Propósito: obtener detalle del episodio.

- `POST /api/clinical/index.php/encounters/{encounter_key}/finalize`
  - Uso: cierre de consulta (P10 y `encounter.php`).
  - Propósito: cerrar encounter activo.

- `POST /api/clinical/index.php/encounters/{encounter_key}/documents`
  - Uso: alta de documento ligado a encounter (vistas clínicas).

- `GET /api/clinical/index.php/documents/{id_or_uuid}`
  - Uso: `document.php` y `viewer.php`.

- `GET /api/clinical/index.php/bundles/{bundle_id}/documents`
  - Uso: `viewer.php`.

- `GET /api/clinical/index.php/patients/{patient_id}/cases/active`
  - Uso: `historial.php` y `encounter.php`.

- `GET|POST /api/clinical/index.php/patients/{patient_id}/cases`
  - Uso: manejo de casos clínicos desde historial/encounter.

- `GET|POST /api/clinical/index.php/cases/{case_id}/items`
  - Uso: integración de items al caso activo desde historial/encounter.

- `PATCH /api/clinical/index.php/cases/{case_id}`
- `POST /api/clinical/index.php/cases/{case_id}/activate`
  - Uso: operaciones de gestión de caso.

## 3.3 Agenda (contexto de cita dentro de episodio)
- Consumo indirecto en `modules/clinical/ui/encounter.php` vía controladores:
  - `GET /appointments/{id}`
  - `GET /appointments/{id}/events`
  - Fuente: `modules/agenda/controllers/AppointmentsController.php`, `AppointmentEventsController.php`.

## 4) Tablas reales relacionadas con Expediente

### 4.1 Fuentes primarias
- `patients_patients` (módulo Pacientes)
  - Rol: identidad canónica base del expediente (`patient_id`).

- `clinical_documents`
  - Rol: fuente documental clínica (notas, recetas, procedimientos, etc.) mostrada en historial/encounter/document/viewer.

- `clinical_encounters`
  - Rol: estado de consulta activa/cerrada y eje del episodio clínico.

### 4.2 Fuentes clínicas complementarias
- `clinical_cases`
  - Rol: caso activo del paciente (agrupación clínica).

- `clinical_case_items`
  - Rol: vínculo de items (appointments/documentos) al caso.

- `clinical_patient_identity_bridge`
  - Rol: puente legacy -> canonical `patient_id` en Clinical (soporte de transición).

### 4.3 Fuentes de soporte/interconexión
- `agenda_appointments`
  - Rol: eventos de agenda que aparecen en timeline y contexto de cita.

- `agenda_appointment_events`
  - Rol: bitácora de eventos de cita mostrada en contexto de encounter.

- `patients_contacts`, `patients_doctor_links`
  - Rol: soporte de datos administrativos y discoverability (búsqueda por doctor).

- `patients_consents`
  - Rol: tabla administrativa de consentimientos del dominio Pacientes; no domina por sí sola el consentimiento clínico del expediente actual.

## 5) Flujo funcional real del Expediente

1. **Apertura de expediente**
   - Desde “Archivo de pacientes”, se selecciona paciente (`data-pid`) y se ejecuta `setActivePatientId(pid)` + `jumpTo('p-expediente')`.
   - En búsqueda general (`open_origin=search_general`) NO inicia consulta automática.
   - Si origen clínico explícito (`open_origin=clinical_explicit`), se permite `ensureActiveEncounter(pid)`.

2. **Recepción/uso de `patient_id`**
   - `setActivePatientId` sincroniza `patient_id` en dataset de `#p-expediente`, store global y hash/session.
   - Eventos emitidos: `patient:selected`, `expediente:patient_changed`, `expediente:patient-changed`.

3. **Render de paneles del expediente**
   - Tabs base en `#p-expediente`:
     - Datos Generales
     - Historia Clínica
     - Historial de atención (embed)
     - Exploración, Estudios, Tratamiento, Notas, Manejo, Consentimiento, Archivo.
   - Gate visual: sin paciente activo, solo se habilita contexto básico (Datos); con paciente activo, habilita tabs clínicos.

4. **Datos Generales / nombres**
   - Nombre(s), Primer apellido y Segundo apellido se normalizan con `trim` y colapso de espacios internos.
   - Se preservan acentos, `ñ`, guiones y apóstrofes.
   - No se autocapitaliza ni se fuerza mayúsculas/minúsculas.
   - Se bloquean genéricos, dígitos y símbolos inválidos antes de POST.
   - Backend aplica la misma política mínima para proteger llamadas directas.

5. **Historial embebido**
   - Tab `t-historial-atencion` carga iframe con 3 modos:
     - historial (`/modules/clinical/ui/historial.php?patient_id=...&embed=1`)
     - episodio (`/modules/clinical/ui/encounter.php?encounter_key=...&embed=1`)
     - documento (`/modules/clinical/ui/document.php?uuid=...&embed=1`)

6. **Consulta activa y P10**
   - P10 consulta `encounters/active`.
   - Si no existe encounter activo, muestra “No hay consulta activa” + botón “Iniciar consulta”.
   - Al iniciar, crea encounter por `POST /patients/{id}/encounters` y cambia modo a episodio.
   - Al cerrar, usa `POST /encounters/{key}/finalize` y regresa a historial.

7. **Expediente sin consulta activa**
   - Sí existe y es válido: identidad del paciente visible, tabs consultables según gate, header en estado neutro.

8. **Expediente con consulta activa**
   - Header muestra `CONSULTA ACTIVA`, origen/inicio si disponibles.
   - Banda de consultas activas permite alternar entre encounters/pacientes activos (modelo multi-activo).

## 6) Frontera Expediente vs Consulta (evidencia real)

## 6.1 Qué pertenece al Expediente
- Contexto del paciente visible (`patient_id`, identidad, navegación de tabs).
- Estado administrativo de captura (nuevo paciente, dirty guard, neutralización).
- Orquestación de qué subvista clínica se embebe y cuándo.

Evidencia:
- `index.html` sección `#p-expediente`.
- `assets/js/app.js` (`setActivePatientId`, `applyPatientGate`, `syncExpedienteHeaderContext`).

## 6.2 Qué pertenece a Consulta/Encounter
- Crear/cerrar consulta.
- Estado `open/closed`, `encounter_key`, `started_at`, documentos del episodio.
- Operaciones clínicas del episodio y su persistencia.

Evidencia:
- `api/clinical/index.php` rutas `patients/{patient_id}/encounters*`, `encounters/{encounter_key}*`.
- `modules/clinical/ui/encounter.php` (detalle y finalize).

## 6.3 Eventos/estados que conectan ambos
- Eventos puente: `encounter:active`, `mxmed:encounter-changed`, `mxmed:encounter-lifecycle`, `mxmed:encounter-activity`.
- Datasets puente en `#p-expediente`: `data-patient-id`, `data-active-patient-id`, `data-encounter-key`, `data-active-encounter-key`.
- P10 sincroniza UI de expediente con estado de encounter activo.

## 6.4 Regla arquitectónica observada
- Expediente puede existir sin consulta activa.
- Consulta activa es capa clínica adicional, no condición para abrir expediente.

## 7) Interconexiones reales

### 7.1 Pacientes
- Fuente canónica de `patient_id`.
- Alta y búsqueda del paciente para abrir expediente.

### 7.2 Agenda
- Agenda puede detonar contexto de paciente/appointment.
- En encounter se consumen datos/eventos de cita para contexto operativo.

### 7.3 Clinical / encounters
- Núcleo clínico de historial, episodio, documentos y lifecycle de consulta.

### 7.4 Timeline
- Vista consolidada del historial en `historial.php` consumiendo `patients/{patient_id}/timeline`.

### 7.5 Documentos clínicos
- `document.php` y `viewer.php` para detalle/visor.
- Altas y lecturas de documentos por API clinical.

### 7.6 Consentimientos
- Tab visual de consentimiento en expediente.
- En repo actual, consentimiento administrativo (`patients_consents`) y clínico no están plenamente unificados en una sola superficie API de expediente.

### 7.7 Casos clínicos
- Integración activa desde historial/encounter con rutas `cases/*`.

## 8) Divergencias / riesgos detectados (solo reporte)

1. **Expediente como shell híbrido**
   - Parte del expediente es captura frontend/local/UI, mientras la parte clínica fuerte vive en vistas embebidas `modules/clinical/ui/*`.

2. **Acoplamiento múltiple por eventos/dataset/store**
   - Estado se replica en dataset DOM + `mxmedStore` + eventos globales; riesgo de desalineación en bordes.

3. **Frontera consentimiento administrativo vs clínico no totalmente cerrada**
   - Existe `patients_consents` en Pacientes, pero el flujo de consentimiento clínico del expediente no está centralizado en una API única equivalente.

4. **Dependencia de fallback/legacy en algunas rutas**
   - Ejemplos: bridge de identidad legacy en Clinical y superficies standalone/embebidas coexistiendo.

5. **Scripting inline crítico en `index.html`**
   - Parte central de orquestación P10/embed está en script inline (no modularizado), lo cual aumenta fragilidad de mantenimiento.

6. **Integración Agenda en expediente no uniforme**
   - El contexto de agenda en encounter existe, pero no toda la experiencia Agenda->Expediente está unificada en una sola UX shell.

## 9) Preguntas abiertas / zonas grises

1. ¿Qué subset del expediente debe migrar de estado local/UI a persistencia clínica canónica en próximas fases?
2. ¿Cuál será el contrato final unificado para consentimiento clínico vs consentimiento administrativo?
3. ¿Se consolidará la orquestación P10/embed fuera de script inline hacia un módulo JS dedicado?
4. ¿Qué política final se aplicará para coexistencia standalone (`modules/clinical/ui/*`) vs embed en shell?
5. ¿Cómo se formalizará el contrato de sincronización única de estado (DOM dataset vs store global vs eventos)?
