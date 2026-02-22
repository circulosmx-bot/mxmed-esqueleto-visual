# Inventario de Expediente Existente en el Repo

## Alcance y método
Este inventario se construyó revisando referencias en código y documentación a:
- expediente
- historia clínica
- antecedentes
- alergias
- medicamentos
- diagnósticos
- signos vitales
- recetas
- consentimientos

Fuentes revisadas (principal):
- `index.html`, `assets/js/app.js`, `assets/js/manejo-hospitalario.js`, `assets/js/core/navigation.js`
- `api/clinical-documents.php`, `api/evolution-note-generate.php`, `api/_lib/clinical_documents.php`, `api/_lib/clinical_documents_hospital.php`
- `api/patients/index.php`, `modules/patients/*`
- `docs/contracts/clinical_documents/*`, `docs/mock/*`, `docs/assets/js/*`

## Inventario por sección/capacidad

### 1) Paciente (Datos generales / identidad)
- Nombre (humano): Paciente administrativo (identidad y contacto)
- Ruta/archivos relacionados:
  - `modules/patients/db/ready_schema.sql`
  - `api/patients/index.php`
  - `modules/patients/controllers/CreatePatientController.php`
  - `modules/patients/controllers/GetPatientController.php`
  - `modules/patients/controllers/GetDoctorPatientsController.php`
  - `modules/patients/repositories/PatientsRepository.php`
  - `index.html` (tab `#t-datos`)
- ¿DB existe?: sí
  - Tablas: `patients_patients`, `patients_contacts`, `patients_consents`, `patients_doctor_links`
- ¿API existe?: sí
  - Rutas: `POST /api/patients/index.php/patients`, `GET /api/patients/index.php/patients/{patient_id}`, `GET /api/patients/index.php/doctors/{doctor_id}/patients`
- ¿UI existe?: sí
  - Dónde: `index.html` tab `#t-datos`
- Campos principales detectados:
  - Backend: `patient_id`, `display_name`, `birthdate`, `sex`, `status`, `phone`, `email`, `doctor_id`
  - UI: nombre, apellidos, fecha de nacimiento, sexo, teléfonos, correo, domicilio y datos demográficos
- Estado: PARTIAL
- Notas (dependencias):
  - La UI de expediente (`#t-datos`) no está conectada de forma explícita al API de `patients` para persistencia completa de todos sus campos.

### 2) Historia clínica (motivo/padecimiento/antecedentes/alergias/medicamentos/vacunas/familiares)
- Nombre (humano): Historia Clínica y antecedentes
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-historia`)
  - `assets/js/app.js` (bloques `Historia Clinica: registros con chips + modal` y `vacunas relevantes`)
- ¿DB existe?: no (dedicada)
- ¿API existe?: no (dedicada)
- ¿UI existe?: sí
  - Dónde: tab `#t-historia` con chips/modales
- Campos principales detectados:
  - `motivo_consulta`, `padecimiento_actual`
  - chips de antecedentes personales (incluye `alergias`, `medicamentos de uso continuo`, transfusiones, etc.)
  - vacunación (COVID, influenza, tétanos + observaciones)
  - antecedentes familiares (texto libre)
- Estado: PARTIAL
- Notas (dependencias):
  - En la implementación actual, la persistencia de chips es en DOM (sin tabla ni endpoint específico).
  - `motivo_consulta` y `padecimiento_actual` sí se consumen desde Nota de Evolución.

### 3) Antecedentes gineco-obstétricos
- Nombre (humano): Antecedentes gineco-obstétricos
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-gineco`)
  - `assets/js/app.js` (control condicional por sexo)
- ¿DB existe?: no
- ¿API existe?: no
- ¿UI existe?: sí
  - Dónde: tab `#t-gineco` (visible según sexo)
- Campos principales detectados:
  - gestas, partos, cesáreas, abortos, nacidos vivos
  - FUM, regularidad, duración/frecuencia ciclo, intensidad, dolor
  - anticoncepción, tiempo de uso, plan reproductivo, observaciones
- Estado: PARTIAL
- Notas (dependencias):
  - No hay persistencia backend detectada para este bloque.

### 4) Exploración física y signos vitales
- Nombre (humano): Exploración Física
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-exploracion`)
  - `assets/js/app.js` (captura de vitales y resumen de exploración)
- ¿DB existe?: no (tabla dedicada de exploración)
- ¿API existe?: parcial (vía documento clínico)
  - Rutas indirectas: `POST /api/clinical-documents.php?action=save`
- ¿UI existe?: sí
  - Dónde: tab `#t-exploracion`
- Campos principales detectados:
  - vitales: TA sistólica/diastólica, FC, FR, temperatura, SpO2, dolor EVA
  - antropometría: peso, talla, IMC, cintura
  - exploración por sistemas: estado por sistema, notas, hallazgos, resumen
- Estado: PARTIAL
- Notas (dependencias):
  - Estos datos se usan para construir `nota_evolucion`, pero no tienen CRUD propio dedicado.

### 5) Estudios diagnósticos
- Nombre (humano): Estudios Diagnóstico (selección/órdenes en UI)
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-estudios`)
  - `assets/js/app.js` (catálogos modales, flags, resumen y tarjetas de orden)
- ¿DB existe?: no (tabla dedicada de órdenes de estudios)
- ¿API existe?: no (dedicada para órdenes)
- ¿UI existe?: sí
  - Dónde: tab `#t-estudios`
- Campos principales detectados:
  - ítems de estudio por área/modalidad, flags clínicos (incluye `requires_consent`), prioridad, lateralidad, etc.
  - resumen de selección y tarjetas `est-order-card`
- Estado: PARTIAL
- Notas (dependencias):
  - El bloque existe y es amplio en UI, pero sin endpoint persistente específico detectado.
  - La Nota de Evolución toma estudios recientes desde estas tarjetas.

### 6) Nota de evolución (consulta/urgencias/hospitalización)
- Nombre (humano): Nota de Evolución clínica (NOM-004)
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-notas`, sección `data-ne-section="nota_evolucion"`)
  - `assets/js/app.js` (initNotaEvolucion)
  - `api/clinical-documents.php`
  - `api/evolution-note-generate.php`
  - `api/_lib/clinical_documents.php`
  - `docs/contracts/clinical_documents/nota_evolucion.v1.example.json`
- ¿DB existe?: sí
  - Tablas: `clinical_documents`, `clinical_document_participants` (creación runtime en `mxmed_ensure_clinical_docs_schema`)
- ¿API existe?: sí
  - Rutas: `POST /api/clinical-documents.php?action=save`, `GET /api/clinical-documents.php?action=list`, `GET /api/clinical-documents.php?action=get`
  - Ruta legacy: `POST /api/evolution-note-generate.php`
- ¿UI existe?: sí
  - Dónde: tab `#t-notas` (editor + timeline + modal de lectura)
- Campos principales detectados:
  - `ambito`, `citas_clinicas`, `complemento_sintomas`, `evolucion_cuadro_clinico`
  - `signos_vitales`, `exploracion_relevante`, `estudios_relevantes`
  - `diagnosticos`, `pronostico`, `plan_indicaciones`
  - `receta` (resumen), `snapshot`
- Estado: DONE
- Notas (dependencias):
  - Si falla API, hay fallback local en `localStorage` para timeline/notas.
  - No hay FK explícita entre `clinical_documents.patient_id` y tablas de `patients_*`.

### 7) Manejo hospitalario (nota intrahospitalaria + hoja de indicaciones)
- Nombre (humano): Manejo Hospitalario
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-manejo` + template `#tpl-manejo-hospitalario`)
  - `assets/js/manejo-hospitalario.js`
  - `api/clinical-documents.php`
  - `api/_lib/clinical_documents_hospital.php`
  - `docs/contracts/clinical_documents/nota_evolucion_hosp.v1.example.json`
  - `docs/contracts/clinical_documents/hoja_indicaciones.v1.example.json`
- ¿DB existe?: parcial
  - Sí para documentos en `clinical_documents`/`clinical_document_participants`
  - No detectada tabla `hospital_stays` en SQL del repo
- ¿API existe?: parcial
  - Sí: `api/clinical-documents.php?action=save|list|get`
  - No detectado: `api/hospital-stays.php?action=current|start|close` (referenciado por UI)
- ¿UI existe?: sí
  - Dónde: `assets/js/manejo-hospitalario.js` (cargado por navegación)
- Campos principales detectados:
  - episodio hospitalario (`hospital_stay_id`, servicio, habitación/cama)
  - estado actual, soporte, signos vitales, balance hídrico
  - exploración, evolución diaria, diagnósticos, pronóstico, plan
  - `receta`, eventos, snapshot
- Estado: PARTIAL
- Notas (dependencias):
  - Sin backend de `hospital-stays`, la experiencia queda incompleta fuera de modo demo/mock.

### 8) Recetas
- Nombre (humano): Recetas (resumen en expediente + panel avanzado en docs)
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-notas`, modal `#modalReceta`)
  - `assets/js/app.js` (draft de receta para nota de evolución)
  - `docs/assets/partials/recetas.html`
  - `docs/assets/js/recetas.js`
  - `docs/mock/prescription-generate.json`
- ¿DB existe?: parcial
  - En runtime queda embebido en `clinical_documents.payload_json.receta`
  - No detectada tabla dedicada `rx_*`
- ¿API existe?: parcial
  - No detectado `api/prescription-generate.php` (sí referenciado en UI docs)
  - Sí hay `clinical-documents.php` para persistir receta embebida en documento
- ¿UI existe?: sí
  - Dónde: modal simple en `#t-notas` + panel avanzado en `docs/assets/js/recetas.js`
- Campos principales detectados:
  - medicamentos: `medicamento`, `dosis`, `via`, `periodicidad`, `duracion`, `indicaciones`
  - `folio`, `consultorio`, `signature`, `qr_enabled`, `diagnosticos`, `indicaciones_generales`
- Estado: PARTIAL
- Notas (dependencias):
  - Flujo avanzado de recetas depende de endpoint no presente en `api/`.

### 9) Consentimiento informado (clínico)
- Nombre (humano): Consentimientos informados del expediente
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-consent`, wizard paso 1/2)
  - `docs/assets/js/consentimientos.js`
  - `docs/mock/ci-list.json`, `docs/mock/ci-templates.json`, `docs/mock/ci-draft.json`, `docs/mock/ci-step1.json`, `docs/mock/ci-step2.json`
- ¿DB existe?: no (clínica específica detectada)
- ¿API existe?: no (implementación real detectada)
  - UI docs espera: `api/ci/list`, `api/ci/templates`, `api/ci/draft`, `api/ci/{id}/step1`, `api/ci/{id}/step2`
- ¿UI existe?: sí
  - Dónde: tab `#t-consent` con wizard y lista
  - Nota: en raíz no se detecta carga de `assets/js/consentimientos.js`; en `docs/` sí existe script funcional con mock/API esperado
- Campos principales detectados:
  - paso 1: datos paciente/contacto (`telefono`, `correo`, `domicilio`, flags de update)
  - paso 2: `template_id`, `procedimiento`, `motivo`, `objetivo`
  - metadatos: `consentimiento_id`, `folio`, `medico_id`, `consultorio_id`
- Estado: PARTIAL
- Notas (dependencias):
  - Existe UI y contrato implícito (incluyendo mocks), pero no endpoint real en `api/`.

### 10) Consentimientos administrativos de paciente (no clínicos del expediente)
- Nombre (humano): Consentimientos de comunicación/privacidad del dominio Pacientes
- Ruta/archivos relacionados:
  - `modules/patients/db/ready_schema.sql` (tabla `patients_consents`)
  - `docs/db/CONTRATO_JSON_PACIENTES.md`
  - `docs/db/MODELO_CANONICO_PACIENTES.md`
- ¿DB existe?: sí
  - Tabla: `patients_consents`
- ¿API existe?: no detectada para CRUD de `patients_consents`
- ¿UI existe?: no detectada en expediente
- Campos principales detectados:
  - `consent_id`, `patient_id`, `consent_type`, `consent_value`, `version`, `consented_at`, `source`, `actor_id`
- Estado: PARTIAL
- Notas (dependencias):
  - Es consentimiento administrativo del dominio pacientes; no resuelve el consentimiento informado clínico del expediente.

### 11) Archivo del expediente (adjuntos)
- Nombre (humano): Archivo / Adjuntos del expediente
- Ruta/archivos relacionados:
  - `index.html` (tab `#t-archivo`)
- ¿DB existe?: no
- ¿API existe?: no
- ¿UI existe?: sí (placeholder)
  - Dónde: tab `#t-archivo` muestra mensaje de adjuntos
- Campos principales detectados:
  - No aplica (placeholder)
- Estado: FUTURO
- Notas (dependencias):
  - No hay flujo de adjuntos detectado en backend/API.

### 12) Contratos y ejemplos clínicos (documentación)
- Nombre (humano): Contratos de documentos clínicos v1
- Ruta/archivos relacionados:
  - `docs/contracts/clinical_documents/README.md`
  - `docs/contracts/clinical_documents/nota_evolucion.v1.example.json`
  - `docs/contracts/clinical_documents/nota_evolucion_hosp.v1.example.json`
  - `docs/contracts/clinical_documents/hoja_indicaciones.v1.example.json`
- ¿DB existe?: n/a (documentación)
- ¿API existe?: n/a (documentación)
- ¿UI existe?: n/a (documentación)
- Campos principales detectados:
  - contratos de payload para `nota_evolucion`, `nota_evolucion_hosp`, `hoja_indicaciones`
- Estado: DONE
- Notas (dependencias):
  - Son referencia útil para migrar/normalizar contratos al módulo `clinical`.

## Resumen de integración a `modules/clinical`

### Qué ya existe (aprovechable sin re-trabajo)
- Backend/documentos clínicos operativo para timeline y detalle:
  - `clinical_documents` + `clinical_document_participants`
  - endpoint `api/clinical-documents.php?action=save|list|get`
- Constructor y validación de payload clínico para:
  - `nota_evolucion`
  - `nota_evolucion_hosp`
  - `hoja_indicaciones`
- UI de expediente ya avanzada en:
  - historia clínica
  - exploración física/signos vitales
  - estudios diagnósticos
  - nota de evolución
  - manejo hospitalario
- Dominio Pacientes con DB/API básico ya implementado (`patients_*`).
- Contratos y ejemplos JSON clínicos en `docs/contracts/clinical_documents/*`.

### Qué falta para integrar limpio con `modules/clinical`
- Unificar persistencia estructurada v1 para secciones sin backend dedicado:
  - historia clínica
  - gineco-obstétricos
  - exploración física
  - estudios diagnósticos
  - consentimiento informado clínico
- Implementar endpoints faltantes referenciados por UI:
  - `api/ci/*`
  - `api/prescription-generate.php` (o reemplazar por contrato unificado)
  - `api/hospital-stays.php` (si se mantiene flujo de hospitalización)
- Resolver divergencia UI raíz vs UI en `docs/`:
  - en raíz no se detecta carga del script de consentimientos/recetas modular de `docs/assets/js/*`.
- Normalizar contratos de respuesta API para converger en formato estándar único.
- Definir relación canónica entre `patients_*` y recursos clínicos (`clinical_*`/`clinical_documents`) para evitar IDs paralelos o snapshots no sincronizados.

---
Estado global del expediente existente en repo:
- Núcleo de documentos clínicos: funcional.
- Resto de secciones de expediente: mayormente UI avanzada con persistencia parcial o pendiente.
