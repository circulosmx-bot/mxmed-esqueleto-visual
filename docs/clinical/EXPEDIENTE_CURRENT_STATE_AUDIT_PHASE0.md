# CLIN-REFORM-PHASE0-AUDIT01 — estado actual del Expediente Clínico

```text
AUDIT_DATE=2026-09-18
SOURCE_BRANCH=design/physician-crd03-credentials-ui-v1
STARTING_ACCEPTED_HEAD=7a4a29936135d689b6386bff5ac686e42359c145
AUDIT_STATUS=READY_FOR_DIRECTOR_REVIEW
PHASE_0_STATUS=IN_PROGRESS
PRODUCT_CHANGES=NONE
DB_WRITES=0
```

Esta auditoría describe el sistema observado, no aprueba un nuevo modelo ni una navegación futura. `SOURCE_EVIDENCE` significa que el comportamiento se deriva de código versionado; `DATABASE_EVIDENCE` identifica sólo metadatos y agregados de la base local `mxmed`, sin leer ni publicar datos personales; `PHYSICAL_RUNTIME_EVIDENCE` identifica una petición local observada; `DOCUMENTATION_ONLY` aporta contexto, nunca sustituye una prueba. `NO_VERIFICADO` significa que no se demostró el comportamiento físico. Los estados `PARTIAL` reflejan implementación comprobable en fuente con límites de alcance, cobertura o prueba física; no afirman que el flujo completo pase QA.

## Matriz principal de las nueve secciones

| SECTION | CURRENT_UI | FUNCTIONAL_STATUS | READ_AUTHORITY | WRITE_AUTHORITY | STORAGE_TABLES | PATIENT_SCOPED | DOCTOR_SCOPED | ENCOUNTER_SCOPED | CASE_SCOPED | PERSISTS_AFTER_RELOAD | BEHAVIOR_ON_NEXT_ENCOUNTER | KNOWN_BLOCKERS | LOCAL_FIXTURE_DEPENDENCY | DUPLICATE_AUTHORITY_RISK | NOTES |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Datos Generales | Identidad, contactos y domicilio estructurados | PARTIAL | `GetPatientController`, link activo | `SavePatientDetailsController` | `patients_patients`, `patients_profiles`, `patients_contacts`, `patients_addresses`, `patients_doctor_links` | true | true | false | false | Persistencia por contrato y tablas; recarga física NO_VERIFICADO | Misma identidad longitudinal | Runtime 8091 roto; creación + detalle no atómicos entre llamadas | No específica; QA local necesita sesión | Bajo respecto a clínica; campos repetidos en encabezados derivados | `modules/patients/controllers/SavePatientDetailsController.php:17`; `modules/patients/repositories/PatientDetailsRepository.php:19`; `assets/js/perfil/datos-generales.js:1287,1340`. |
| Exploración Física | Signos, antropometría, sistemas y hallazgos | PARTIAL | GET borrador de paciente | PUT mismo borrador | `clinical_record_entries` | true | false | false | false | Fuente GET sobre fila persistida; recarga física NO_VERIFICADO | Reabre el mismo borrador por paciente, no uno independiente | Default Normal; sin fecha efectiva ni médico/consulta en fila; endpoint GET puede hacer DDL | Servidor 8091 y gateway | Alto: signos también posibles en nota/documento | `assets/js/app.js:75290`; `api/clinical/index.php:3856,3880,4630`; `index.html:5687`. |
| Historia Clínica | Motivo, padecimiento, interrogatorio y antecedentes | PARTIAL | GET borrador de paciente | PUT mismo borrador | `clinical_record_entries` | true | false | false | false | Fuente GET sobre fila persistida; recarga física NO_VERIFICADO | Reabre contenido compartido del paciente | Mezcla temporalidad longitudinal y consulta; sin versión de encuentro ni scope médico en ruta | Servidor 8091 y gateway | Alto: motivo/medicación/alergias aparecen en más superficies | `assets/js/app.js:57220`; `api/clinical/index.php:3851-3935,4475`; `index.html:4950`. |
| Historial de Atención | Iframe de timeline/encounter y casos | BROKEN | Timeline y detalle doctor-scoped en fuente | Inicio/finalización de encounter y casos por rutas clínicas | `clinical_encounters`, `clinical_documents`, `clinical_cases`, `clinical_case_items` | true | true | mixed | mixed | Datos físicos existen; render local NO_VERIFICADO | La timeline filtra por médico y puede excluir encuentros legacy | Router temporal ausente; 13/13 encuentros físicos sin `doctor_id`; gateway GET puede hacer DDL | `/tmp/mxmed-qa02-20260912/director-router.php` | Medio: timeline agrega documentos y citas | BROKEN describe el runtime local, no un defecto probado del producto. `index.html:5321,10814`; `api/clinical/index.php:2745,3113,3997,6755`. |
| Estudios Diagnóstico | Órdenes de laboratorio/imagen, resultados y áreas aún no persistidas | PARTIAL | Documentos clínicos y UI local | POST documentos de orden/resultado | `clinical_documents` | true | partial | mixed | false | Documentos físicos existen; flujo de recarga NO_VERIFICADO | Documentos independientes; vínculo a consulta variable | Sólo lab/imagen guardan en ruta inspeccionada; resultado no envía `encounter_key` | Servidor y gateway | Medio: orden/resultado también en Documentos e Historial | `assets/js/app.js:72930-73165,72290-72420`; `index.html:6050`. |
| Tratamiento / Recetas | Tarjeta que abre emisión en Actividad Clínica | PARTIAL | Documentos y contexto clínico derivado | POST `prescription` | `clinical_documents`; lectura auxiliar de `clinical_record_entries` | true | partial | mixed | false | Recetas físicas existen; reapertura UI NO_VERIFICADO | Documento nuevo no modifica por sí mismo el anterior; medicación actual no tiene autoridad probada propia | Flujo delegado; datos de medicación pueden venir de borrador | Servidor y gateway | Alto: receta frente a medicación actual y notas | `index.html:7841`; `assets/js/app.js:40587,38650,38090`. |
| Manejo Hospitalario | Panel inyectado con puerta de capacidad | PARTIAL | `api/hospital-stays.php?action=current` si habilitado | `start`/`close` si habilitado | `hospital_stays` | true | false | false | false | 5 filas físicas; flujo UI deshabilitado por defecto local | Episodio de estancia separado, sin `encounter_id` | Capacidad local deshabilitada; propagación de paciente NO_VERIFICADO; API sin scope médico | `MXMED_FEATURES.hospital_stays` o atributo de entorno | Medio: estancia vs encuentro | `assets/js/manejo-hospitalario.js:214,497,640`; `api/hospital-stays.php:1-18,185-291`. |
| Documentos Clínicos | Catálogo de 7 lanzadores reales y 3 placeholders visibles | PARTIAL | Rutas de documentos clínicos | Generadores y POST documentales | `clinical_documents` | true | partial | mixed | mixed | 413 documentos físicos; reapertura de cada tipo NO_VERIFICADO | Cada emisión crea documento; vínculo de encuentro no universal | Tres tipos sin flujo; finalización/firma e inmutabilidad física NO_VERIFICADO | Servidor y gateway | Medio: notas/recetas/órdenes también aparecen en otras vistas | `index.html:8196-8299`; `assets/js/app.js:42839,53100`; `api/clinical/index.php:7705`. |
| Archivo | Sólo “Adjuntos del expediente” | PLACEHOLDER | Ninguna lectura implementada en esta pestaña | Ninguna escritura implementada en esta pestaña | Ninguna demostrada para esta pestaña | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | No hay listado, carga ni eliminación visibles | Servidor 8091 | Potencial conceptual con adjuntos documentales; autoridad duplicada no probada | `index.html:9896`; otras vistas sí adjuntan documentos, no esta pestaña. |

### Datos Generales — autoridad y atomicidad

`SOURCE_EVIDENCE`: los campos de nombre estructurado, fecha de nacimiento, género, contactos y domicilio se presentan en `index.html:4532-4940`. `GetPatientController` y `SavePatientDetailsController` exigen usuario/médico de sesión y vínculo activo; el repositorio guarda el detalle existente dentro de transacción con bloqueo, commit y rollback (`modules/patients/repositories/PatientDetailsRepository.php:19-83`). La creación inicial y el PUT de detalle son dos peticiones: si la segunda falla, el paciente ya puede existir; el cliente comunica ese estado (`assets/js/perfil/datos-generales.js:1340`). `DATABASE_EVIDENCE`: `patients_patients=169`, `patients_doctor_links=171`; no prueba de recarga de un registro concreto. Temporalidad: identidad administrativa longitudinal, no dato de consulta.

### Exploración Física — medidas, fecha y “Normal”

`SOURCE_EVIDENCE`: presión arterial, pulso, frecuencia respiratoria, temperatura, saturación, dolor, peso, talla y cintura se serializan en un único `payload_json` de borrador; IMC se calcula en cliente (`assets/js/app.js:75178,75290-75670`). La tabla tiene `entry_date`, `created_at` y `updated_at`, pero no fecha efectiva individual de cada medición, `doctor_id` ni `encounter_id` (`modules/clinical/db/schema_v2.sql:32-60`). No hay serie temporal independiente de peso/presión ni atribución médica por medida. Los sistemas se dibujan con radios `normal` marcados por defecto (`index.html:5687-6050`), y la lectura JS también usa `normal` como fallback; abrir sin guardar no demuestra un hallazgo, pero guardar podría registrar normalidad no explorada. `DATABASE_EVIDENCE`: siete borradores `exploracion_fisica`, siete pacientes. Si una segunda consulta guarda Y, la ruta actual actualiza el mismo borrador; X no es recuperable por esta autoridad. La visualización de una consulta nueva usa el mismo valor compartido, no una nueva medición vacía. La fuente de copia/referencia con procedencia es inexistente en este flujo. `PHYSICAL_RUNTIME_EVIDENCE` de guardado/recarga: NO_VERIFICADO.

### Historia Clínica — ciclos de vida mezclados

`SOURCE_EVIDENCE`: motivo de consulta, padecimiento actual e interrogatorio por sistemas coexisten con hábitos, tabaquismo, alcohol, actividad, sustancias, dieta, antecedentes personales/familiares, alergias, medicación continua, vacunación y gineco-obstétricos (`index.html:4950-5284`; `assets/js/app.js:57220-57590`). GET/PUT de `/patients/{id}/history` leen y actualizan el último borrador por `patient_id` y `note_type=historia_clinica`, sin `encounter_id` ni `doctor_id` (`api/clinical/index.php:3851-3935,4475-4630`). `DATABASE_EVIDENCE`: once borradores, once pacientes. Motivo/padecimiento son conceptualmente de consulta; antecedentes/hábitos son longitudinales mutables. La implementación no los separa. En consulta siguiente se vuelve a mostrar el mismo borrador; Y sobrescribe X por la ruta, sin versión de consulta recuperable. No se probó una transición física de dos encuentros.

### Historial de Atención — fallo local, ruta e historia física

`SOURCE_EVIDENCE`: el host contiene iframe y construye `/modules/clinical/ui/historial.php?patient_id=...&embed=1`; si hay `encounter_key`, construye `/modules/clinical/ui/encounter.php?...` (`index.html:5321,10814-10820`). Ambas páginas existen en fuente. La timeline y el detalle tienen rutas GET; la timeline exige doctor de sesión y vínculo activo, filtra `clinical_encounters.doctor_id` y combina documentos elegibles y casos (`api/clinical/index.php:3997-4265,3113-3185`); el detalle exige propietario del encounter y vínculo (`api/clinical/index.php:6755-6868`). La cita `appointment_id` puede generar claves/agrupaciones legacy; no equivale a un encounter atribuido.

`PHYSICAL_RUNTIME_EVIDENCE`: el proceso PHP de `127.0.0.1:8091` usa `-t <repo> /tmp/mxmed-qa02-20260912/director-router.php`. Ese router ya no existe y su log registra `Failed opening required`; un GET al iframe responde HTTP 200 con cuerpo fatal de PHP, por lo que el código 200 no demuestra render. Clasificación: `LOCAL_ENVIRONMENT_DEPENDENCY`; la alternativa `STALE_QA_FIXTURE` describe la procedencia probable, no una causa de producto probada. `DATABASE_EVIDENCE`: 13 encuentros (11 cerrados, 2 abiertos), los 13 sin `doctor_id`. No se atribuye médico por inferencia. Con el filtro actual, esos encuentros legacy no quedan demostrados como legibles para un médico; documentos sin `encounter_id` podrían figurar en timeline por paciente, pero no reconstituyen el encuentro. Existe otra ruta/API de lectura en fuente; **lectura física actual por esa ruta: NO_VERIFICADO**. No se invocó porque el gateway puede ejecutar DDL en GET (`api/clinical/index.php:3953-3975,4111`). Casos: dos filas `clinical_cases` y quince `clinical_case_items`, sin prueba física de render.

### Estudios, recetas, hospital y documentos

`SOURCE_EVIDENCE`: el guardado de órdenes de laboratorio e imagen crea documentos; otras opciones muestran persistencia futura (`assets/js/app.js:72930-73165`). Los resultados también crean documentos, con referencia a la orden, pero el formulario de resultado no propaga `encounter_key` en el flujo inspeccionado (`assets/js/app.js:72290-72420`). Hay estados visibles de orden/resultado, pero un ciclo físico pendiente→resultado no se verificó. `DATABASE_EVIDENCE`: existen tipos `lab_order`, `imaging_order`, `lab_result` e `imaging_result`; no prueban que cada botón actual funcione.

`SOURCE_EVIDENCE`: “Emitir receta” delega a Actividad Clínica (`assets/js/app.js:40587-40595`), cuyo POST crea un documento `prescription` con `encounter_key` opcional (`assets/js/app.js:38650-38750`). La medicación actual mostrada en el encabezado de receta se deriva primero de chips de Historia y, si faltan, del borrador de receta (`assets/js/app.js:38090-38120`); no se demostró una autoridad clínica independiente de medicación vigente. Nueve recetas físicas `generated` carecen de vínculo directo a encuentro en la muestra agregada. La emisión de un documento nuevo no actualiza el documento previo en la ruta inspeccionada; recuperabilidad por la UI actual: NO_VERIFICADO.

`SOURCE_EVIDENCE`: la UI hospitalaria tiene puerta `hasHospitalStaySupport`, falsa en el servidor local salvo bandera/atributo explícito; se detiene antes del refresco del paciente y presenta “no disponible” (`assets/js/manejo-hospitalario.js:214-225,497-566,630-645`). “Selecciona paciente” puede ser un aviso distinto (`index.html:10469`); **propagación real del contexto activo: NO_VERIFICADO**. El backend `api/hospital-stays.php` sí ofrece current/start/close y almacena una estancia por paciente, sin `encounter_id` ni `doctor_id`; `attending_user_id` es texto opcional y no prueba autoría médica. Cinco estancias físicas. El API inspeccionado comprueba existencia de paciente, pero no sesión ni vínculo médico; no se probó acceso cruzado.

| Documento visible | Estado de fuente | Autoridad / límite observado |
| --- | --- | --- |
| Consentimiento informado | PARTIAL | Lanzador real; `clinical_documents`; emisión y firma física NO_VERIFICADO. |
| Responsiva médica | PARTIAL | Lanzador real; `clinical_documents`; encuentro opcional/no probado. |
| Consentimiento multimedia | CONFIGURATION_INITIAL | Abre placeholder, no generador visible. |
| Certificado médico | PARTIAL | Lanzador real; filas físicas; finalización física NO_VERIFICADO. |
| Certificados especiales | CONFIGURATION_INITIAL | Abre placeholder. |
| Interconsulta | PARTIAL | Lanzador real; filas físicas; vinculación a encuentro no universal. |
| Informe médico | PARTIAL | Lanzador real; filas físicas. |
| Nota médica | PARTIAL | Lanzador real; posible solapamiento conceptual con Historia/Actividad Clínica. |
| Alta médica | PARTIAL | Lanzador real; no equivale por sí sola a cerrar encuentro. |
| Documento libre | CONFIGURATION_INITIAL | Abre placeholder. |

Los siete lanzadores reales y tres placeholders visibles están en `index.html:8196-8299`; los tres subtipos ocultos no se contaron. Los lanzadores despachan a modales reales o al modal de placeholder (`assets/js/app.js:53100-53150`). `DATABASE_EVIDENCE`: 413 documentos, 402 sin `encounter_id` directo y cero con `appointment_id` poblado. El esquema permite `patient_id`, `encounter_id` nullable, `appointment_id` nullable, `hospital_stay_id` nullable y `created_by_user_id`; la atribución del usuario no sustituye `doctor_id`. Existe ruta de réplica documental; inmutabilidad histórica y estados de firmado por tipo no se probaron físicamente. La UI de Archivo sólo contiene el texto de adjuntos (`index.html:9896`): no se halló allí lectura, carga, visibilidad pública/privada, enlace a encuentro ni escritura; todo ello es `NO_VERIFICADO` para Archivo, aunque otras superficies permitan adjuntar archivos.

## Mapa de titularidad de encuentro

| Superficie | `patient_id` | `doctor_id` | `encounter_id`/clave | `appointment_id` | Consecuencia comprobada |
| --- | --- | --- | --- | --- | --- |
| Datos Generales | Sí | Sí, sesión/link | No | No | Identidad longitudinal; no inicia consulta. |
| Historia y Exploración | Sí | No en ruta | No | No | Un borrador mutable por paciente/tipo; sin versión de consulta. |
| Historial y Actividad Clínica | Sí | Sí en rutas de encounter | Sí | Opcional | Timeline/encounter exigen propiedad; legacy físico sin médico. |
| Estudios y recetas documentales | Sí | Parcial según gateway | Opcional | Opcional en documento | Gran parte de documentos físicos no está enlazada. |
| Hospitalización | Sí | No en API observado | No | No | Episodio de estancia separado. |
| Documentos Clínicos | Sí | Parcial según ruta | Opcional | Opcional | Registro documental no garantiza pertenencia a consulta. |
| Archivo | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | Pestaña sin autoridad implementada. |

`clinical_encounters` es la autoridad de encuentro; `clinical_cases`/`clinical_case_items` representan caso. `appointment_id` es opcional y la cita no se puede tratar como encuentro ni atribuir retrospectivamente. `SOURCE_EVIDENCE`: `api/clinical/index.php:5707-5991,6446-6868`; `DOCUMENTATION_ONLY`: plan vivo, principios de dominio.

## Mapa de guardado/persistencia

| SURFACE | TRIGGER | API/ROUTE | TABLE | SCOPE | ATOMICITY | ERROR_FEEDBACK | RELOAD_PROOF |
| --- | --- | --- | --- | --- | --- | --- | --- |
| Datos Generales existente | Guardar explícito | PUT `/api/patients/index.php/doctors/{doctor}/patients/{patient}/datos-generales` | Tablas `patients_*` | Paciente + médico/link | Transacción de detalle | Mensaje de error cliente | Contrato GET; físico NO_VERIFICADO |
| Datos Generales nuevo | Crear y después guardar detalle | POST paciente + PUT detalle | `patients_*` | Paciente + médico/link | Dos llamadas, no atómicas juntas | Advierte creación parcial | Físico NO_VERIFICADO |
| Historia Clínica | Guardar borrador | PUT `/api/clinical/index.php/patients/{id}/history` | `clinical_record_entries` | Paciente/tipo | Una fila insert/update; sin versión de encuentro | Respuesta HTTP y mensaje UI | GET en fuente; físico NO_VERIFICADO |
| Exploración Física | Guardar borrador | PUT `/api/clinical/index.php/patients/{id}/physical-exam` | `clinical_record_entries` | Paciente/tipo | Una fila insert/update; sin versión de encuentro | Respuesta HTTP y mensaje UI | GET en fuente; físico NO_VERIFICADO |
| Encounter | Abrir/finalizar | POST rutas de encounters/finalize | `clinical_encounters`, documentos derivados | Paciente + médico + encounter | Transacciones/rutas separadas | API error; UI NO_VERIFICADO | GET en fuente; físico NO_VERIFICADO |
| Estudios | Guardar orden/resultado | POST documento doctor-scoped | `clinical_documents` | Paciente + médico, encounter opcional | Documento individual | Mensaje de UI | Lectura documental en fuente; físico NO_VERIFICADO |
| Receta | Emitir desde Actividad Clínica | POST documento doctor-scoped | `clinical_documents` | Paciente + médico, encounter opcional | Documento individual | Mensaje de UI | Físico NO_VERIFICADO |
| Hospital | Iniciar/cerrar | `api/hospital-stays.php?action=start/close` | `hospital_stays` | Paciente; sin médico verificado | Transacción de inicio, actualización de cierre | API error; UI local deshabilitada | GET current en fuente; físico NO_VERIFICADO |
| Documentos Clínicos | Generar/replicar | POST documento y rutas especializadas | `clinical_documents` | Paciente + doctor parcial, encounter opcional | Documento individual | Modal/API por tipo | Físico por tipo NO_VERIFICADO |
| Archivo | Ninguno visible | Ninguna ruta de pestaña | Ninguna probada | NO_VERIFICADO | No aplica | No aplica | NO_VERIFICADO |

## Documentos y autoridad duplicada

El productor de órdenes, resultados, recetas y los siete tipos documentales visibles usa `clinical_documents`; Historial/Actividad Clínica leen parte de esos documentos. Una fila documental no demuestra que la sección de origen tenga ciclo completo ni que el documento pertenezca a un encuentro. Las filas `note` con vínculo directo a encuentro son 11; el resto del catálogo no tiene cobertura universal. `SOURCE_EVIDENCE`: `api/clinical/index.php:2375-2395,2745-2800,6493-6748,7705-8088`; `DATABASE_EVIDENCE`: agregado por tipo y vínculo.

| Concepto | Superficies con edición/derivación | Riesgo probado o límite |
| --- | --- | --- |
| Motivo | Historia, modal de Actividad Clínica, notas; cita como fuente de prellenado | Varias entradas/derivaciones; precedencia y conservación entre consultas no unificadas. |
| Alergias | Chips de Historia; encabezado de receta derivado | Mismo borrador longitudinal, no autoridad independiente revisada. |
| Medicación actual | Chips de Historia; borrador de receta como fallback; receta emitida | Un borrador de receta puede aparecer como medicación vigente; `PRESCRIPTION != CURRENT_MEDICATION`. |
| Signos vitales | Exploración; notas/documentos de Actividad Clínica | Duplicación conceptual sin fecha/procedencia compartida demostrada. |
| Notas | Historia/Actividad Clínica/Nota médica/documentos de encuentro | Distintos registros y momentos; reconciliación NO_VERIFICADO. |
| Receta | Actividad Clínica y documento histórico | Emisión documental única observada; edición paralela no probada. |
| Archivo/Documentos | Adjunto documental y pestaña Archivo | Duplicación de autoridad NO_VERIFICADO; Archivo es placeholder. |

### Fuentes del encabezado clínico actual

| Dato | Fuente observada | Clase | Límite |
| --- | --- | --- | --- |
| Motivo | `getClinicalCitations()` lee Historia; otra rutina prellena desde motivo de cita/timeline (`assets/js/app.js:35134,39000-39124`) | Derivado, mezcla paciente/agenda/consulta | Fecha y procedencia visibles NO_VERIFICADO. |
| Alergias | Chips del panel Historia (`assets/js/app.js:37820-37851,38090`) | Paciente/borrador derivado | No hay revisión por encuentro independiente. |
| Medicación actual | Historia continua; fallback a borrador de receta (`assets/js/app.js:38090-38120`) | Derivado y posiblemente no guardado | No equivale a prescripción previa ni lista vigente validada. |
| Estado de consulta | `encounter_key` activo consultado en rutas clínicas (`index.html:10934-11121`) | Encounter derivado | Encuentros legacy sin médico no se reatribuyen. |

## Matriz de temporalidad

| FIELD_OR_CONCEPT | CURRENT_OWNER | TEMPORAL_CLASS | HISTORICAL_VERSION_AVAILABLE | CURRENT_VALUE_SOURCE | PROVENANCE_AVAILABLE | RISK |
| --- | --- | --- | --- | --- | --- | --- |
| Nombre, nacimiento, género, domicilio | `patients_*` | STATIC_OR_SLOW | NO_VERIFICADO | Detalle del paciente | Link médico; historial de cambios NO_VERIFICADO | No confundir edición administrativa con consulta. |
| Contactos | `patients_contacts` | LONGITUDINAL_MUTABLE | NO_VERIFICADO | Detalle del paciente | Historial de cambios NO_VERIFICADO | Valor vigente reemplaza anterior. |
| Motivo/padecimiento | `clinical_record_entries` Historia; cita como prefill | MIXED | No por ruta de borrador | Mismo borrador o derivación de agenda | `updated_at` de fila, no consulta | X se sustituye por Y sin versión de encuentro. |
| Antecedentes, hábitos, alergias | `clinical_record_entries` Historia | LONGITUDINAL_MUTABLE | No por ruta de borrador | Mismo borrador | `updated_at` de fila | Cambios históricos no recuperables allí. |
| Medicación continua | Chips de Historia | LONGITUDINAL_MUTABLE | No por ruta de borrador | Chips; fallback de Rx para encabezado | No por medicamento | Puede confundirse con receta emitida. |
| Presión/pulso/FR/temperatura/SpO2/dolor | `clinical_record_entries` Exploración | ENCOUNTER_OBSERVATION | No por ruta de borrador | Mismo borrador | Sólo fecha de fila | Previo podría mostrarse como actual. |
| Peso/talla/cintura/IMC | `clinical_record_entries`; IMC derivado | ENCOUNTER_OBSERVATION | No por ruta de borrador | Mismo borrador y cálculo local | Sólo fecha de fila | Sin serie/comparación clínica fiable. |
| Sistemas de exploración | `clinical_record_entries` Exploración | ENCOUNTER_OBSERVATION | No por ruta de borrador | Radios con default Normal | Sin observador/fecha por sistema | Vacío puede volverse Normal al guardar. |
| Encounter/consulta | `clinical_encounters` | ENCOUNTER_OBSERVATION | Sí, filas separadas en esquema | Timeline doctor-scoped | Fecha/médico en esquema; 13 legacy sin médico | Historia física legacy no accesible bajo scope actual. |
| Caso clínico | `clinical_cases`, `clinical_case_items` | EPISODE | Filas de caso presentes | Timeline/caso | NO_VERIFICADO | Asociación clínica completa no probada. |
| Estancia hospitalaria | `hospital_stays` | EPISODE | Cinco filas locales | API current | Fechas de inicio/cierre, attending opcional | Sin médico/encounter canónico. |
| Órdenes/resultados | `clinical_documents` | DOCUMENT | Documentos separados | Catálogo/timeline | Fecha de evento y creador; encounter opcional | Resultado sin vínculo universal a visita. |
| Recetas | `clinical_documents` | DOCUMENT | Documentos separados | Actividad Clínica/documentos | Fecha/creador; encuentro opcional | No equivale a medicación actual. |
| Documento clínico emitido | `clinical_documents` | DOCUMENT | Documentos separados | Generador por tipo | Fecha/creador; firma por tipo NO_VERIFICADO | 402/413 sin encuentro directo. |
| Cita/estado administrativo | Agenda | ADMINISTRATIVE | NO_VERIFICADO aquí | Agenda/timeline derivada | Cita/fecha en fuente | Cita no prueba encuentro ni cobro. |

## Especialidades, autorización y dependencias

```text
SPECIALTY_EXTENSION_POINT_PRESENT=partial (pestaña condicional gineco y campos gineco-obstétricos en Historia)
SPECIALTY_CONFIG_AUTHORITY_PRESENT=NO_VERIFICADO
SPECIALTY_SCHEMA_ALREADY_EXISTS=NO_VERIFICADO para módulos de pediatría, gineco-obstetricia y odontología
SPECIALTY_UI_ALREADY_EXISTS=partial (gineco condicional; pediatría/odontología no demostradas)
```

`SOURCE_EVIDENCE`: `index.html:4508` y campos ginecológicos en Historia. No se deduce de esa pestaña un expediente paralelo ni preparación de esquema para las tres especialidades. Diseño futuro: fuera de PHASE 0.

| Autoridad de lectura/escritura | Sesión/médico | Link activo | Médico ajeno / propiedad de encuentro | Evidencia y límite |
| --- | --- | --- | --- | --- |
| Datos Generales GET/PUT | Sí | Sí | Rechazo por controlador/repo | `GetPatientController`; `SavePatientDetailsController`; prueba cruzada física NO_VERIFICADO. |
| Historia/Exploración GET/PUT | No se exige en bloque de ruta inspeccionado | No se exige | No se verifica | `api/clinical/index.php:4475-4800` comprueba existencia de paciente, no `clinical_require_doctor_context`; riesgo de scope, sin explotación ni escritura de prueba. |
| Timeline/encounter/finalize | Sí | Sí | Propiedad de encounter comprobada en detalle/finalización | `api/clinical/index.php:3997,5707,6446,6755`; legacy físico sin médico. |
| Documento doctor-scoped | Contexto de doctor/ruta | Según ruta | Encuentro opcional; propietario se comprueba en ruta `encounters/{key}/documents` | `api/clinical/index.php:6493-6603,7705-8088`; no se afirma cobertura universal para rutas antiguas. |
| Hospital stay current/start/close | No se observa exigencia | No se observa | No se observa | `api/hospital-stays.php:1-291`; sólo verifica existencia de paciente. |
| Archivo | NO_VERIFICADO | NO_VERIFICADO | NO_VERIFICADO | Pestaña sin flujo. |

No se hicieron pruebas de escritura ni de acceso cruzado. La inspección de fuente identifica límites de autorización, pero no prueba explotabilidad en el despliegue actual. Además, `api/clinical/index.php:3953-3975` invoca funciones de aseguramiento de esquema en el arranque de rutas, y el GET de timeline invoca `clinical_cases_ensure_schema`/`clinical_encounters_ensure_schema` (`:4111`); estas funciones contienen `CREATE TABLE`/`ALTER TABLE` (`:530,643-690,3294-3328`). Por ello se omitieron GET a ese gateway durante una auditoría sin escrituras; un verbo HTTP GET no bastaba para garantizar sólo lectura.

| DEPENDENCY | SURFACE | REQUIRED_FOR_RUNTIME | REPRODUCIBLE_FROM_REPO | CLASSIFICATION | RECOMMENDED_FUTURE_ACTION |
| --- | --- | --- | --- | --- | --- |
| `/tmp/mxmed-qa02-20260912/director-router.php` | Servidor local `127.0.0.1:8091`, incluido Historial | Sí para ese proceso PHP | No; falta en disco y no está versionado | LOCAL_ENVIRONMENT_DEPENDENCY / STALE_QA_FIXTURE | En capítulo futuro, reconstruir servidor desde configuración reproducible y repetir GET. |
| `MXMED_DB_NAME=mxmed` del proceso PHP | Gateway y consultas locales | Sí para ese proceso | Configuración de entorno, no fuente | ENVIRONMENT_ONLY_CONFIG | Documentar arranque reproducible antes de QA física. |
| `MXMED_FEATURES.hospital_stays`/atributo de capacidad | Manejo Hospitalario | Sí para habilitar UI local | Gate en fuente; valor del entorno no versionado | CONFIGURATION_DEPENDENCY | Separar prueba de capacidad de prueba de contexto paciente. |
| Sesión/doctor local de QA | Rutas protegidas y datos Leticia | Sí para QA autenticada | Fixture no garantizado por repositorio | LOCAL_FIXTURE_DEPENDENCY | Repetir pruebas con sesión de QA autorizada sin mutar datos clínicos. |

## Límites de la prueba y estado de revisión

La inspección física se limitó a proceso/HTTP local y consultas `SELECT` de metadatos/agregados en `mxmed`; no se leyeron payloads clínicos ni identificadores de paciente, no se ejecutaron endpoints clínicos con potencial DDL y no se escribieron datos. La presencia de filas demuestra almacenamiento, **no** guardado/recarga, firma, permisos efectivos ni QA de dos consultas. El fallo del router impide observar los nueve paneles funcionando en el runtime local. Los hallazgos de riesgos de sobrescritura, ausencia de doctor scope en rutas y default Normal son derivados de fuente y requieren revisión del Director; no equivalen a una decisión de implementación. `PHASE_0_AUDIT01=READY_FOR_DIRECTOR_REVIEW`; `PHASE_0_CURRENT_STATE_AUDIT` continúa `IN_PROGRESS` hasta aceptación explícita.

## CLIN-REFORM-PHASE0-AUDIT02 — Physical Runtime Validation

```text
AUDIT02_DATE=2026-09-18
AUDIT02_STARTING_ACCEPTED_HEAD=d0602f9c443c1d3e215934c4cc2aa6084e126d12
AUDIT01_STATUS=ACCEPTED
AUDIT02_STATUS=READY_FOR_DIRECTOR_REVIEW
PHASE_0_STATUS=IN_PROGRESS
PRODUCT_SOURCE_CHANGED=false
PRODUCT_DATA_MUTATIONS=0
```

Esta sección **añade** evidencia física al corte de AUDIT01; su matriz y hallazgos originales se conservan como registro de ese momento. `PHYSICAL_RUNTIME_EVIDENCE` se limita a las rutas y vistas efectivamente ejecutadas. La auditoría usó una sesión de QA local ya existente, un paciente vinculado a ese médico y sólo navegación/lectura. No se publican identificadores ni valores del paciente. Todas las peticiones no clasificadas `SAFE_READ` se abortaron en el navegador antes de llegar al servidor. No se ejecutaron acciones de guardar, generar, iniciar, finalizar ni subir.

### Recuperación reproducible del servidor

El proceso anterior en `127.0.0.1:8091` invocaba `/tmp/mxmed-qa02-20260912/director-router.php`, archivo ausente. Se detuvo ese proceso y se inició PHP directamente con raíz documental en el repositorio; no se creó router nuevo ni se modificó fuente. Los valores de conexión no secretos se tomaron del entorno autorizado del proceso anterior. Comando equivalente para este entorno local:

```bash
MXMED_DB_HOST=127.0.0.1 MXMED_DB_PORT=3306 MXMED_DB_NAME=mxmed MXMED_DB_USER=root MXMED_PROFILES_PRIVATE_AUTH_REQUIRED=1 \
php -d upload_max_filesize=10M -d post_max_size=12M -d memory_limit=256M \
  -d session.save_path=/tmp/mxmed-qa02-20260912/sessions \
  -S 127.0.0.1:8091 -t /Users/circulodigital/Documents/GitHub/mxmed-crd03-credentials-ui
```

La carpeta de sesiones es una **dependencia de QA autenticada**, no un router ni autoridad de producto; el servidor sirve HTML/CSS/JS y el shell de Historial sin ella. No se crearon usuarios, credenciales ni sesiones. El proceso nuevo quedó escuchando en `127.0.0.1:8091` con ese document root. `CUSTOM_ROUTER_REQUIRED=false`; `OLD_TMP_ROUTER_USED=false`; `AUDIT_ROUTER_CREATED=false`; `AUDIT_ROUTER_SHA256=NO_APLICA`.

| Ruta directa | Resultado físico | Evidencia |
| --- | --- | --- |
| `/` y `/index.html` | HTTP 200, 764699 bytes, idénticos al `index.html` versionado, sin fatal PHP | `PHYSICAL_RUNTIME_EVIDENCE`; comparación byte a byte |
| `/assets/css/style.css` | HTTP 200, 675757 bytes, idénticos al archivo versionado | `PHYSICAL_RUNTIME_EVIDENCE` |
| `/assets/js/app.js` | HTTP 200, 3342511 bytes, idénticos al archivo versionado | `PHYSICAL_RUNTIME_EVIDENCE` |
| `/modules/clinical/ui/historial.php?embed=1` **sin paciente** | HTTP 200, shell `clinical-historial` presente, sin fatal | `PHYSICAL_RUNTIME_EVIDENCE`; no equivale a timeline clínica |

El shell de Historial con `patient_id` **no se solicitó**: `modules/clinical/ui/historial.php:1259-1443` hace GET a timeline, encuentro activo y caso **desde PHP**; un interceptor del navegador no detendría esas llamadas. El shell sin paciente evita esos bloques. El GET de timeline en `api/clinical/index.php:4110-4111` invoca aseguramiento de esquema con posible DDL. Por tanto, `HISTORIAL_SHELL_RENDER=true`, `TIMELINE_DATA_RENDER=NO_VERIFICADO_SAFETY_GATE` y `OLD_ROUTER_FAILURE_RESOLVED_AS_ENVIRONMENT=true`. La clasificación actual del **shell** pasa de `BROKEN` local de AUDIT01 a `PARTIAL`; el flujo de datos de Historial **no** se declara funcional. El log del servidor directo no contiene fatales PHP.

### Clasificación previa de rutas y barrera de red

| Método y patrón | Clasificación | Ejecución | Razón de fuente |
| --- | --- | --- | --- |
| GET `/api/patients/index.php/doctors/{doctor}/patients?view=archive` | SAFE_READ | Sí | `GetDoctorPatientsController` exige sesión/médico y delega lectura a `PatientsRepository`; la rama GET no muta (`api/patients/index.php:100-123`). |
| GET `/api/patients/index.php/patients/{patient}` | SAFE_READ | Sí | `GetPatientController` exige sesión y link activo antes de `findPatientById` (`modules/patients/controllers/GetPatientController.php:24-75`). |
| GET `/api/clinical/index.php/patients/{patient}/history` | READ_WITH_POTENTIAL_DDL | NOT_EXECUTED_SAFETY_GATE | Arranque del gateway llama aseguramiento de esquema (`api/clinical/index.php:3953-3975`). |
| GET `/api/clinical/index.php/patients/{patient}/physical-exam` | READ_WITH_POTENTIAL_DDL | NOT_EXECUTED_SAFETY_GATE | Mismo arranque del gateway. |
| GET `/api/clinical/index.php/patients/{patient}/timeline` | READ_WITH_POTENTIAL_DDL | NOT_EXECUTED_SAFETY_GATE | La excepción al aseguramiento inicial sigue ejecutando `clinical_cases_ensure_schema` y `clinical_encounters_ensure_schema` (`:4110-4111`). |
| GET `/api/clinical/index.php/patients/{patient}/encounters/active`, detalle/casos/documentos | READ_WITH_POTENTIAL_DDL | NOT_EXECUTED_SAFETY_GATE | Arranque y/o bloque de ruta pueden asegurar esquema. |
| POST/PUT/PATCH/DELETE de cualquier API | WRITE | NOT_EXECUTED_SAFETY_GATE | Bloqueados antes de red; no se pulsaron acciones de escritura. |
| GET media/agenda/perfiles/billing y cualquier ruta no examinada | UNKNOWN | NOT_EXECUTED_SAFETY_GATE | Arranque normal de la página intentó algunas lecturas; no se autorizó inferir que fueran seguras. |

**Registro de peticiones API efectivamente recibidas por el servidor directo** (log local normalizado; sin cookies, tokens, consultas ni IDs):

| METHOD | PATH_PATTERN | CLASSIFICATION | EXECUTED | HTTP_STATUS | EFFECTIVE_DB_WRITE |
| --- | --- | --- | --- | --- | --- |
| GET | `/api/patients/index.php/patients/{patient}` | SAFE_READ | 17 | 200 | 0 |
| GET | `/api/patients/index.php/doctors/{doctor}/patients` | SAFE_READ | 6 | 200 | 0 |

Total: 23 peticiones API `SAFE_READ`, 0 clínicas, 0 de escritura. El navegador produjo errores de red esperados para peticiones que el interceptor abortó deliberadamente; no se atribuyen al producto. En los recorridos registrados hubo **cero excepciones JavaScript `pageerror`**. Las llamadas abortadas no llegaron al servidor. No se infiere ausencia de errores funcionales en flujos cuyas APIs se bloquearon.

### Paciente existente, lectura y navegación

`PHYSICAL_RUNTIME_EVIDENCE`: desde Pacientes se abrió «Ver todos»; el archivo mostró 25 filas en su primera página. Se abrió una fila de paciente ya vinculada al médico de la sesión QA, sin crear consulta. `#p-expediente[data-patient-id]` coincidió con la fila elegida. Se renderizaron «Cerrar expediente» y «Cambiar paciente», sin invocarlos. La ruta GET de detalle respondió HTTP 200 y la vista mostró no vacíos los tres campos de nombre estructurado. Tras recarga dura del documento, se conservó el mismo paciente y esos tres valores coincidieron exactamente; dos GET directos adicionales del mismo detalle devolvieron un objeto de diez campos con huella local coincidente. La huella y los valores no se guardaron ni publicaron. Este resultado prueba **lectura/recarga de identidad estructurada**, no contactos, domicilio, guardado ni un flujo completo de Datos Generales.

| SECTION | PATIENT_CONTEXT_PRESENT | PATIENT_CONTEXT_CORRECT | ENCOUNTER_CONTEXT_PRESENT | ERROR_PRESENT |
| --- | --- | --- | --- | --- |
| Datos Generales | true | true | false | Sin fatal JS; errores de APIs bloqueadas posibles |
| Estudios Diagnóstico | true | true | false | Datos remotos `NO_VERIFICADO_SAFETY_GATE` |
| Tratamiento / Recetas | true | true | false | Datos remotos `NO_VERIFICADO_SAFETY_GATE` |
| Manejo Hospitalario | true en Expediente | false en aviso/contexto hospitalario | false | Aviso de paciente + capacidad deshabilitada |
| Documentos Clínicos | true | true | false | Datos remotos `NO_VERIFICADO_SAFETY_GATE` |
| Archivo | true | true | false | Sin fatal JS; pestaña sólo textual |

No se abrieron físicamente Historia Clínica ni Exploración Física con un paciente, pues sus GET son `READ_WITH_POTENTIAL_DDL`. `HISTORIA_PHYSICAL_READ`, `HISTORIA_RELOAD_PHYSICAL`, `EXPLORACION_PHYSICAL_READ` y `DEFAULT_NORMAL_PHYSICAL_STATE` permanecen `NO_VERIFICADO_SAFETY_GATE`; el default «Normal» de AUDIT01 sigue siendo evidencia de fuente, no hallazgo clínico físico.

`PHYSICAL_RUNTIME_EVIDENCE`: Estudios mostró sus controles y conservó el paciente, sin crear orden/resultado. Tratamiento / Recetas mostró «Emitir receta»; pulsar **sólo el lanzador** abrió `#modalReceta` y conservó el paciente, sin emitir nada. Documentos Clínicos mostró diez lanzadores visibles; Archivo mostró «Adjuntos del expediente» sin `input[type=file]` ni listado en esa pestaña. La lectura de órdenes, recetas, adjuntos o documentos existentes no se verificó porque las APIs clínicas quedaron bloqueadas.

| DOCUMENT_TYPE | LAUNCHER_RENDER | TARGET_OPENED | TARGET_CLASSIFICATION | WRITE_EXECUTED |
| --- | --- | --- | --- | --- |
| Consentimiento informado | true | true | REAL_MODAL | false |
| Responsiva médica | true | true | REAL_MODAL | false |
| Consentimiento multimedia | true | true | CONFIGURATION_INITIAL_PLACEHOLDER | false |
| Certificado médico | true | true | REAL_MODAL | false |
| Certificados especiales | true | true | CONFIGURATION_INITIAL_PLACEHOLDER | false |
| Interconsulta | true | true | REAL_MODAL | false |
| Informe médico | true | true | REAL_MODAL | false |
| Nota médica | true | true | REAL_MODAL | false |
| Alta médica | true | true | REAL_MODAL | false |
| Documento libre | true | true | CONFIGURATION_INITIAL_PLACEHOLDER | false |

`TARGET_OPENED` sólo acredita apertura del modal, **no** generación, firma, persistencia, atribución médica ni corrección histórica.

### Manejo Hospitalario: capacidad y contexto por separado

`PHYSICAL_RUNTIME_EVIDENCE`: en el servidor local la capacidad hospitalaria quedó `DISABLED`; se mostró «Manejo hospitalario no disponible en este entorno» y «Iniciar hospitalización» estaba deshabilitado. Al mismo tiempo, `#p-expediente[data-patient-id]`, el resolver global y el store de paciente coincidían con el paciente activo, pero `#mh_context_notice` **seguía visible** con «Selecciona paciente» y el contexto hospitalario no contenía ese ID. La puerta de capacidad y la propagación de paciente son, por tanto, dos observaciones distintas. `HOSPITAL_CONTEXT_BUG_PROVEN=true` **para este runtime local**; la causa definitiva no se probó. `SOURCE_EVIDENCE`: `assets/js/manejo-hospitalario.js:214-225,382-410,436-475,624-650,950-975` muestra la puerta y la sincronización de contexto. No se llamó `api/hospital-stays.php` ni se inició/cerró una estancia.

### Guardia de datos antes y después

Una consulta `SELECT` con doce conteos se capturó antes de la QA y se repitió después. Los pares pre/post fueron idénticos:

| Tabla | PRE | POST |
| --- | ---: | ---: |
| `patients_patients` | 169 | 169 |
| `patients_profiles` | 29 | 29 |
| `patients_contacts` | 152 | 152 |
| `patients_addresses` | 27 | 27 |
| `patients_doctor_links` | 171 | 171 |
| `clinical_record_entries` | 18 | 18 |
| `clinical_encounters` | 13 | 13 |
| `clinical_documents` | 413 | 413 |
| `clinical_cases` | 2 | 2 |
| `clinical_case_items` | 15 | 15 |
| `hospital_stays` | 5 | 5 |
| `agenda_appointments` | 206 | 206 |

`ROW_COUNT_DRIFT=0`. Se ejecutaron siete sentencias `SELECT` directas (dos guardias y selección/conteo de sesión-paciente QA) más las consultas internas de las 23 rutas `SAFE_READ` de Pacientes, cuyo número interno no se infiere. Los conteos no prueban por sí solos ausencia de UPDATE; la barrera HTTP, la inspección de las dos rutas permitidas y la ausencia de acciones de escritura sustentan `WRITE_DB_QUERIES_EXECUTED=0`, `DDL_EXECUTED=0` y `PRODUCT_DATA_MUTATIONS=0` por esta auditoría.

AUDIT02 cierra la dependencia del **router ausente** y la recarga física de identidad estructurada; mantiene abiertas las lecturas clínicas/temporales que el contrato de cero escrituras impide probar. `PHASE_0_AUDIT02=READY_FOR_DIRECTOR_REVIEW`; PHASE 0 sigue `IN_PROGRESS` a la espera de decisión del Director/asistente.
