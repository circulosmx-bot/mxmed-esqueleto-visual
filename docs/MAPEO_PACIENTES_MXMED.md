# MAPEO PACIENTES MXMED

## 1) Objetivo del módulo
Levantar el estado real implementado del módulo Pacientes en MXMed para documentar:
- archivos reales involucrados,
- endpoints disponibles,
- tablas usadas,
- flujo funcional actual,
- contrato canónico de `patient_id`,
- interconexiones con Agenda, Expediente y Clinical.

Este documento es descriptivo (sin cambios funcionales).

## 2) Inventario de archivos

### API Gateway Pacientes
- `api/patients/index.php`
  - Propósito: router real del dominio Pacientes.
  - Relación: expone lectura de paciente por ID, listado por doctor y alta de paciente.

### Controladores Pacientes
- `modules/patients/controllers/GetPatientController.php`
  - Propósito: `GET patient by id` con contactos enmascarados.
- `modules/patients/controllers/GetDoctorPatientsController.php`
  - Propósito: `GET doctor patients` con `limit` y contactos enmascarados.
- `modules/patients/controllers/CreatePatientController.php`
  - Propósito: alta de paciente y validaciones de payload.

### Repositorio
- `modules/patients/repositories/PatientsRepository.php`
  - Propósito: acceso SQL para create/get/list; inserta paciente, contactos y link doctor-paciente.
  - Nota: genera IDs internos (`p_`, `c_`, `l_`) con `random_bytes`.

### DB/SQL
- `modules/patients/db/ready_schema.sql`
  - Propósito: schema base del dominio (`patients_patients`, `patients_contacts`, `patients_consents`, `patients_doctor_links`).

### Puentes con Agenda
- `modules/agenda/helpers/patients_client.php`
  - Propósito: wrapper `agenda_patients_create(...)` que invoca `CreatePatientController`.
- `modules/agenda/controllers/AppointmentWriteController.php`
  - Relación: auto-crea paciente cuando `POST /appointments` llega sin `patient_id` y con datos mínimos.
- `modules/agenda/controllers/WaitlistController.php`
  - Relación: al asignar waitlist, puede resolver/crear paciente por `patient_name + patient_phone`.

### Consumo frontend (expediente/archivo)
- `assets/js/perfil/datos-generales.js`
  - Relación: guardado explícito de paciente desde Datos Generales (`POST /api/patients/index.php/patients`).
- `assets/js/app.js`
  - Relación: búsqueda en Archivo de pacientes vía API de Pacientes, apertura de expediente y cache local del índice.

### Documentación relacionada (evidencia)
- `docs/db/INTEGRACION_PACIENTES_AGENDA.md`
- `docs/db/INTEGRACION_AGENDA_POST_PATIENTS.md`
- `docs/clinical/DECISION_FUENTES_DE_VERDAD.md`
- `docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md`

## 3) Endpoints reales del módulo Pacientes

### 3.1 GET `/api/patients/index.php/patients/{patient_id}`
- Archivo: `api/patients/index.php` -> `GetPatientController@handle`.
- Propósito: obtener paciente por ID canónico.
- Parámetros:
  - path: `patient_id`.
- Respuesta (wrapper):
  - `ok:true` con `data` paciente + `contacts` enmascarados.
  - `ok:false` con `error`: `invalid_params`, `not_found`, `db_not_ready`, `db_error`.
- Meta relevante: `visibility.contact = masked` (+ `qa_mode_seen` si aplica).

### 3.2 GET `/api/patients/index.php/doctors/{doctor_id}/patients?limit=N`
- Archivo: `api/patients/index.php` -> `GetDoctorPatientsController@handle`.
- Propósito: listar pacientes activos vinculados a un doctor.
- Parámetros:
  - path: `doctor_id`.
  - query: `limit` (1..200, default 50).
- Respuesta:
  - `ok:true` con arreglo de pacientes (`patient_id`, `display_name`, `status`, `contacts masked`).
  - `ok:false`: `invalid_params`, `db_not_ready`, `db_error`.
- Meta: `visibility.contact = masked`, `paging.limit`.

### 3.3 POST `/api/patients/index.php/patients`
- Archivo: `api/patients/index.php` -> `CreatePatientController@handle`.
- Propósito: crear paciente canónico.
- Parámetros principales del payload:
  - requerido: `display_name`.
  - opcionales: `birthdate` (`Y-m-d`), `sex`, `contacts[]`, `doctor_id`.
  - `contacts[]`: `{ type: phone|email, value, is_primary?, preferred_contact_method? }`.
- Respuesta:
  - `ok:true` con `data.patient_id`, `contacts masked`, `links` (si vino `doctor_id`), `audit`.
  - `ok:false`: `invalid_params`, `db_not_ready`, `db_error`.
- Meta: `visibility.contact = masked` (+ `fields` en errores de validación).

### 3.4 Rutas no implementadas en este router
- No existe `GET /api/patients/index.php/patients` (lista global) en `api/patients/index.php`.
- En frontend existe fallback a esa ruta cuando no se resuelve `doctor_id`; en ese caso hoy devuelve `route not found`.

## 4) Tablas reales relacionadas

### 4.1 `patients_patients`
- Propósito: identidad canónica del paciente.
- Campos clave detectables:
  - `patient_id` (PK)
  - `display_name`
  - `status`
  - `birthdate`
  - `sex`
  - `created_at`, `updated_at`

### 4.2 `patients_contacts`
- Propósito: contacto del paciente (tel/email) asociado a `patient_id`.
- Campos clave:
  - `contact_id` (PK)
  - `patient_id`
  - `phone`, `email`
  - `preferred_contact_method`
  - `is_primary`
- Comportamiento API: contactos se exponen enmascarados (`value_masked`).

### 4.3 `patients_doctor_links`
- Propósito: vínculo doctor-paciente.
- Campos clave:
  - `link_id` (PK)
  - `doctor_id`
  - `patient_id`
  - `status`
  - `created_at`, `ended_at`
- Regla técnica detectada: `UNIQUE(doctor_id, patient_id)`.

### 4.4 `patients_consents`
- Propósito en schema: consentimientos del dominio Pacientes.
- Campos clave:
  - `consent_id`, `patient_id`, `consent_type`, `consent_value`, `consented_at`.
- Estado real en código actual: no hay endpoints/controladores en `api/patients/index.php` que operen esta tabla.

## 5) Flujo funcional real de Pacientes

1. **Alta de paciente (ruta explícita actual)**
   - UI expediente (`assets/js/perfil/datos-generales.js`) usa botón `Guardar paciente`.
   - Hace `POST /api/patients/index.php/patients` con payload mínimo (`display_name`, opcionales).
   - Si éxito: fija `patient_id` activo (`setActivePatientId`), invalida cache de búsqueda y mantiene flujo en expediente sin iniciar consulta automática.

2. **Lectura de paciente por ID**
   - Se usa `GET /patients/{patient_id}` para recuperar snapshot canónico.

3. **Listado por doctor (archivo de pacientes)**
   - `assets/js/app.js` usa `GET /doctors/{doctor_id}/patients?limit=200` para índice local de búsqueda.
   - Resultado cacheado en memoria (`cachedList`) con invalidación explícita al guardar paciente.

4. **Vínculo doctor-paciente**
   - En create, si viene `doctor_id`, se inserta en `patients_doctor_links` con `status=active`.

5. **Relación con Agenda**
   - Agenda puede crear paciente vía helper compartido (`agenda_patients_create`) en:
     - `AppointmentWriteController::createFromPayload`.
     - `WaitlistController::resolvePatientId`.

6. **Relación con Expediente/Consulta**
   - Pacientes provee `patient_id` canónico para abrir contexto de expediente.
   - Iniciar consulta (encounter) ocurre en Clinical/P14, no en alta de paciente.

## 6) `patient_id` como identidad canónica (estado real)

### 6.1 Generación
- Se genera en `PatientsRepository::generateId('p_')` (prefijo `p_` + random hex).

### 6.2 Persistencia
- Se guarda como PK en `patients_patients.patient_id`.
- Se propaga a:
  - `patients_contacts.patient_id`
  - `patients_doctor_links.patient_id`

### 6.3 Reutilización en módulos
- **Agenda**: usa `patient_id` para crear/vincular citas; si falta, intenta crearlo en Patients.
- **Clinical/Encounters**:
  - `clinical_encounters.patient_id` y `clinical_cases.patient_id` tienen FK a `patients_patients(patient_id)`.
  - Timeline/documentos consumen `patient_id` como parámetro principal.

### 6.4 Resolver/bridge en Clinical
- En `api/clinical/index.php` existen rutas de resolución/bridge:
  - `GET patient-id/inspect`
  - `POST patient-id/resolve`
  - `identity-bridge/*` (lookup/upsert/delete en dev/local)
- Tabla puente: `clinical_patient_identity_bridge` para transición legacy -> canónico.

## 7) Interconexiones reales

### 7.1 Agenda
- Agenda depende de Pacientes para identidad cuando no recibe `patient_id`.
- La integración es directa en backend (no HTTP externo): helper invoca `CreatePatientController`.

### 7.2 Expediente (shell frontend)
- “Buscar paciente” depende del endpoint por doctor de Pacientes.
- “Nuevo registrar paciente” usa alta explícita en Pacientes y luego abre contexto del expediente del nuevo `patient_id`.

### 7.3 Encounter / Clinical
- Clinical usa `patient_id` para:
  - crear/listar encounter,
  - crear/listar casos,
  - timeline clínico.
- FK de `clinical_encounters` y `clinical_cases` a `patients_patients` refuerza canonicalidad.

### 7.4 Timeline y documentos clínicos
- Timeline se consulta por `patients/{patient_id}/timeline`.
- Documentos clínicos se filtran por `patient_id`; existe deuda histórica de IDs legacy en `clinical_documents` (documentada en decisiones clínicas).

### 7.5 Consentimientos
- `patients_consents` existe en schema de Pacientes, pero no hay API activa en `api/patients/index.php` para operación de consentimientos en este estado.

## 8) Divergencias / riesgos detectados (solo reporte)

1. **Fallback de búsqueda no soportado por API actual**
   - Frontend contempla `/api/patients/index.php/patients` para lista global si no hay `doctor_id`.
   - Router actual no implementa esa ruta (solo POST en `/patients`).

2. **Consentimientos en schema sin superficie API activa**
   - Tabla `patients_consents` existe, pero no hay controladores/endpoints de lectura/escritura en módulo Pacientes actual.

3. **Dependencia fuerte de `doctor_id` para discoverability**
   - El flujo principal de búsqueda depende de vínculos en `patients_doctor_links`.
   - Si un alta se crea sin `doctor_id`, puede quedar fuera del índice por doctor.

4. **Coexistencia canónico/legacy en Clinical (transición)**
   - Pacientes define canónico, pero Clinical mantiene bridge de compatibilidad legacy.
   - Riesgo controlado por resolver/bridge; sigue siendo zona sensible de integración.

5. **Enmascarado de contactos por default**
   - Es consistente con privacidad, pero limita algunos usos operativos si frontend espera datos completos sin rutas adicionales.

## 9) Preguntas abiertas / zonas grises

1. ¿Se implementará `GET /api/patients/index.php/patients` (lista global) o se elimina ese fallback del frontend?
2. ¿Cuál será la ruta canónica de consentimientos: Pacientes (`patients_consents`) o Clinical (consentimiento clínico específico), y cómo se separarán formalmente?
3. ¿Se requiere endpoint de actualización (`PATCH`) en Pacientes para edición administrativa canónica?
4. ¿Cuál será la política para altas sin `doctor_id` respecto a buscador principal por doctor?
5. ¿Qué estrategia/fecha de corte se definirá para terminar la transición legacy -> canonical de `patient_id` en Clinical?
