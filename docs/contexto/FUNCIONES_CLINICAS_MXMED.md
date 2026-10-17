# Funciones existentes — pacientes, clínica, estudios y documentos

## Alcance y lectura

Mapa sucesor de la parte clínica de [mapa total](../MAPA_TOTAL_SISTEMA_MXMED.md),
[interconexiones históricas](../MAPA_INTERCONEXIONES_CLINICAS_MXMED.md) y
[arquitectura clínica](../ARQUITECTURA_CLINICA_MXMED.md). No sustituye sus decisiones.
Inspección **de fuente**, 2026-10-17, baseline
`1b8dfccb68a7613b2182507a840454631dbf012a`, rama
`ux/consultation-step2-vitals-r1`; entorno: repositorio local, sin ejecutar producto,
HTTP, SQL, pruebas, capturas ni proveedores. Los nombres de tablas son fuente definida;
su instalación se consulta en [datos y migraciones](DATOS_Y_MIGRACIONES_MXMED.md).

Se agrupan **59 capacidades**: PAT 4, CLIN 19, STUDY 9, DENT 2, DOC 20, SIG 4 y AI 1.
Son grupos de acciones con un propósito común, no un conteo de botones o pruebas.
Las diferencias de autoridad y persistencia se explicitan. Para la secuencia completa,
consultar [F05, F06 y F11](INTERACCIONES_CLINICAS_MXMED.md).

### Rutas, autoridad y ejes comunes

- `PAPI` = `/api/patients/index.php`; `CAPI` = `/api/clinical/index.php`.
  Los sufijos de las tablas siguientes se anexan a esa base. CAPI acepta segmentos
  del path y `?route=`/`?path=`; recorta `index.php` por compatibilidad en
  `clinical_route_segments()`. Los alias comparten dispatcher, no son otro writer.
- `D/P/E/DOC` = perfil médico/paciente/encounter/documento exactos. `encounter_key`
  admite `enc:{id}`, `appt:{id}` y referencias compuestas: `#` debe enviarse `%23`.
- `A` = Identity canónica, cuenta/sesión/versión vigentes, membresía única activa,
  capability comercial y ámbito servidor; [CanonicalClinicalIdentityResolver][resolver].
  `patients`, `clinical_record` y `prescriptions` son gates distintos. No se deducen
  permisos de nombre, plan QA, owner, alias ni cuerpo HTTP.
- `V` = acto profesional: [clinical_professional_authority_decision()][professional].
  **En este baseline el consumidor clínico devuelve siempre denegación**
  `account_professional_binding_unavailable` para un contexto completo. El issuer y
  binding C4 posteriores existen, pero esta función todavía no los consume. No se
  interpreta su comentario histórico «no existe binding» como ausencia actual del
  componente Identity; la brecha es conectar la autoridad clínica aprobada.
- `M` = integridad/schema, cohortes M6 y ventana de escritura. Un writer antiguo
  continúa retirado para los pares M6 aunque un endpoint legacy siga presente.
- Error común: 401 sesión ausente; 403 autoridad/capability/origen insuficiente;
  404 fuera de scope o recurso ausente; 409 versión/estado/replay incompatible;
  422 contrato inválido; 503 autoridad/schema/servicio no disponible. Cada fila añade
  sus errores específicos; no se verificaron respuestas HTTP durante este mapa.
- Dependencias comunes: Identity, MySQL/PDO y capacidades comerciales. «Sin externa
  adicional» significa que la acción no llama por sí misma a un proveedor remoto.
  No hay garantía nueva de disponibilidad de esas dependencias.

Cada fila referencia un perfil de **cinco ejes independientes**, en orden
`PRODUCT_POLICY / SOURCE / CONNECTIVITY / VERIFICATION / OPERATION`:

| Perfil | Cinco ejes | Interpretación acotada |
|---|---|---|
| R | `unknown / implemented / wired / source-inspected / external-state-not-verified` | Cadena en fuente y contrato enlazado; la inspección no certifica por sí sola la ratificación completa de cada CRUD. |
| U | `unknown / partial / unresolved / source-inspected / external-state-not-verified` | Hay código; contrato, composición o autoridad requiere reconciliación exacta. |
| C | `unknown / implemented / optional-composition / isolated-QA-evidence / external-state-not-verified` | Fuente e informe histórico aislado; variables/cohortes/esquema deciden ejecución, sin nueva ratificación integral. |
| G | `approved / implemented / wired / source-inspected / inactive` | Mecanismo implementado; transición profesional actualmente denegada por V. |
| H | `superseded / historical / wired / source-inspected / external-state-not-verified` | Entrada legacy conservada con restricciones actuales; no permiso para reactivarla. |
| D | `unknown / implemented / disconnected / source-inspected / authorization-pending` | Componente entregado cuya UI/activación final está expresamente pendiente. |
| P | `proposed / absent / unresolved / not-verified / authorization-pending` | Propuesta o placeholder; no funcionalidad operativa atribuida. |
| N | `unknown / absent / unresolved / source-inspected / authorization-pending` | No encontrado en el alcance inspeccionado; no se inventa una propuesta aprobada. |

`approved` se limita a la contención de seguridad expresamente preservada; las reglas
compartidas aprobadas se referencian abajo. `unknown` no invalida un contrato existente:
evita convertir presencia de código o QA en aprobación de toda la capacidad. No aprueba DDL, delegación
clínica, firma real ni despliegue. La evidencia `isolated-QA-evidence` se refiere al
informe indicado en [evidencia](#evidencia-y-límites), nunca a una suite ejecutada aquí.

### Decisiones y contratos que gobiernan la lectura

| Referencia existente | Regla preservada y capacidades afectadas |
|---|---|
| [Fuentes de verdad][truth] y [modelo clínico](../clinical/EXPEDIENTE_CLINICAL_INFORMATION_MODEL_UX_CONTRACT.md) | Identidad paciente separada de historia; estado longitudinal separado de una consulta, documento y dato histórico. PAT y CLIN. |
| [Appointment ID canónico](../clinical/DECISION_APPOINTMENT_ID_CANONICO_AGENDA_V1.md), [Agenda→encounter](../clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md), [linking](../clinical/CONTRATO_APPOINTMENT_ENCOUNTER_LINKING_V1.md) | Reusar referencia de cita original con scope; distinguir la cita de la atención médica y conservar compatibilidad. CLIN-02/08 y STUDY-08. |
| [Contención CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md) y [GOV-SIM02](../identity/GOV-SIM02-physician-ownership-session.md) | Cuenta/owner/membresía/plan no son identidad profesional ni autorización de firma; preserva denegación actual. Todas las escrituras profesionales y SIG. |
| [CLIN-AUTH02B1](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md) | Modelo/matriz **propuestos donde así indica el contrato**; separar preparación y acto profesional, sin convertir toda opción de matriz en grant implementado/aprobado. |
| [Compatibilidad wrapper](../clinical/DECISION_COMPAT_CLINICAL_DOCUMENTS_WRAPPER.md) y [documentos base](../clinical/DOCUMENTOS_CLINICOS_BASE_V1.md) | Reusar writers/maestro e historia, no borrar compatibilidad ni usar wrapper para burlar restricciones posteriores. DOC-15..20. |
| [UX-ALIGN01](../clinical/UX-ALIGN01-prescription-presentation-assessment.md) y [registro visual](../MXMED_REGISTRO_CONTRATOS_VISUALES.md) | Conservar editor/renderer/shell aprobados; falta operacional no significa falta de UI. DOC-10..17. |

Las fechas y commits de evidencia se listan al final. No se infiere una aprobación
individual de cada endpoint desde el título de un documento o el nombre de una rama.

## Pacientes e identidad administrativa

| ID / propósito y actor | UI original y handler → método/ruta | Writer y lectura / datos | Gate, resultado, brecha mínima y ejes |
|---|---|---|---|
| PAT-01 — buscar/listar/abrir pacientes del médico; miembro A | [Shell][shell] `#p-expediente`, buscadores de pacientes y `[data-rx-global-query]`; [app.js][app] y [rec-ux01][recui] → GET PAPI `/doctors/{D}/patients`, `/doctors/{D}/patients/search`, `/patients/{P}` | [SearchDoctorPatientsController][patientsearch], GetDoctorPatientsController/GetPatientController → [PatientsRepository][patientsrepo]; `patients_patients`, `patients_doctor_links`, contactos enmascarados y estado agregado de encounters/Agenda | A con `patients`, vínculo activo; búsqueda vacía no crea paciente, otro D denegado. Archivo/listado no implica writer de archivado. Sin externa adicional. R; evidencia E-PAT. |
| PAT-02 — alta administrativa; usuario del formulario existente | [Shell][shell], formulario nuevo paciente y [app.js][app] → POST PAPI `/patients` → [CreatePatientController][patientcreate] | `PatientsRepository::createPatient()` → transacción de `patients_patients`, contactos y vínculo opcional; lectura retorna identidad/contactos enmascarados | Validación de nombre, máximo cinco contactos, IDs generados servidor. **Diferencia localizada:** dispatcher/controller no invoca el mismo `clinical_request_identity()` de PAT-01/03; no se acreditó aquí el gate exterior de este alta. No afirmar ruta explotable ni autoridad segura por analogía. Requiere auditoría dirigida antes de activar/modificar. Sin externa adicional. U. |
| PAT-03 — datos generales, dirección y contactos privados; miembro A | `#t-datos`, `[data-exp-datos-form]`; [vis07-administrative][adminui] / app.js → PUT PAPI `/doctors/{D}/patients/{P}/datos-generales`, GET/PUT `/doctors/{D}/patients/{P}/contacts/editable` | [SavePatientDetailsController][patientsave] → [PatientDetailsRepository][detailsrepo] y PatientsRepository → `patients_patients`, `patients_profiles`, `patients_contacts`, `patients_addresses`; lectura doctor-scoped | A `patients`, D/P y rol de contacto (principal/emergencia); save bajo lock/transacción. POST `/patients/{P}/address` y `/profile` devuelven 403: usar save doctor-scoped. No son campos de contacto del médico ni OTP. Sin externa adicional. R. |
| PAT-04 — resolver identidad legada/duplicados y persistencia preparada; backend/administración autorizada | CAPI GET `/patient-id/inspect`, POST `/patient-id/resolve`; bridge GET `/identity-bridge/lookup`, POST `/identity-bridge/upsert`, DELETE `/identity-bridge/delete`; [dispatcher][clinicalapi]. Nuevo [PatientsAuthorityCompositionRoot][patientauthority] R0 sólo preparado | Resolver HTTP/`clinical_patient_identity_bridge` legacy; nuevo [PatientIdentityResolver][patientresolver], policy/candidate set/merge/duplicate review y [PdoPatientIdentityPersistenceAdapter][patientpersist]; migraciones `patient_identity_*` y checkpoints | Mapping explícito, nunca unión por nombre. Bridge de mantenimiento usa gate local/dev; su cobertura de gobierno no se infiere del resolver privado. Rollout nuevo `disabled`, `writesEnabled=false`, `activationAllowed=false`; no backfill autorizado. U; separar relación legacy instalada de contrato nuevo. |

Identidad paciente pertenece a `patients_*`; historia médica no se convierte en un
segundo maestro por existir el antiguo `clinical_patients`. Reglas: [fuentes de verdad][truth],
[patient-id resolver](../clinical/CONTRATO_PATIENT_ID_RESOLVER_V1.md),
[puente](../clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md).

## Consulta, longitudinal y control

| ID / propósito y actor | UI original y handler → método/ruta | Writer/read model / persistencia | Gates, resultado/error, menor brecha y ejes |
|---|---|---|---|
| CLIN-01 — seleccionar paciente y contexto visible; miembro A | `#p-expediente`, `#exp_clinical_context`; [vis01-navigation][navigation], [vis02-patient-header][header], app.js; consume PAT-01, consulta actual y Agenda | Contexto de paciente en DOM/eventos; datos autoritativos de PAT-01, encounters y doctor-context; no writer clínico nuevo | Navegar no crea E ni autoridad; epoch/contexto evita pintar paciente anterior. Sin externa adicional. R. |
| CLIN-02 — iniciar/continuar consulta; miembro A `clinical_record` | `#m7-workspace`, `[data-m7-start]`, `[data-m7-resume]`; [m7-workspace][m7], [vis04-consultation][consultui] → GET `/patients/{P}/encounters/active`, GET/POST `/patients/{P}/encounters`, GET `/encounters/{E}` | [ClinicalEncounterIntegrityRepository/Service][integrity] `start()`, snapshot/active reader; `clinical_encounters`, `clinical_encounter_start_requests`, citas vinculadas | A+D/P, cita exacta si existe; idempotencia de inicio, máximo abierto, único conflicto devuelve consulta existente; M. La cita no sustituye E. Sin externa adicional. C, E-M5. |
| CLIN-03 — historia y exploración legadas; miembro A | Secciones históricas de expediente y app.js → GET/PUT `/patients/{P}/history`, `/patients/{P}/physical-exam` | `clinical_record_entry_upsert_draft()` en [dispatcher][clinicalapi] → `clinical_record_entries`; lectores de payload/subjective/objective | A+D/P; M6 deniega writer retirado, no fallback de escritura ante error V1. Mantener lectura de historia sin convertirla a sección actual. Sin externa adicional. H. |
| CLIN-04 — motivo, exploración, diagnóstico/assessment y plan estructurados; miembro A | `[data-m7-section]`, `#m7-section-text`; [m7][m7] y [m7-ws03][ws03] para exploración → PUT `/encounters/{E}/sections/{type}` | [ClinicalEncounterSectionsRepository][sections] y IntegrityService; `clinical_encounter_sections`, revisión y actor; tipos validados (`reason_evolution`, `review_of_systems`, `physical_exam`, `assessment`, `plan`, `follow_up`) | A+D/P/E abierto, schema_version/row_version; conflicto conserva borrador y exige resolución. Actos por operación/grants profesionales siguen deuda de CLIN-AUTH02B1; no asumir que membership significa médico. M. Sin externa adicional. C. |
| CLIN-05 — medir, corregir, reutilizar e invalidar signos vitales; miembro A | `[data-m7-measurements-list]`, `[data-ef-vital]`, `#exp_weight`; [m7-ws03][ws03] y app.js → POST `/encounters/{E}/observations`, PATCH `/observations/{id}`, POST `/observations/reuse`, `/observations/{id}/void`; GET `/patients/{P}/vital-references` | [ClinicalObservationsRepository][observations] / IntegrityService → `clinical_observations`; catálogo/unidades/`row_version`, procedencia/reuso y medición UTC. [clinical_vital_references_resolve()][vitalrefs] usa datos canónicos y registry local | A+D/P/E; rangos técnicos no diagnóstico, invalidado queda histórico; tiempo no confirmado no alimenta serie comparable. Conflicto/duplicado/razón ausente deniegan. Revalidar grants médicos de captura pendientes; M. Sin externa adicional. C. |
| CLIN-06 — finalizar, anular consulta y enmendar; miembro A y gate V donde la transición lo requiere | `[data-m7-section="finalize"]`, `[data-m7-finalize]`; [m7-ws05][ws05] → POST `/encounters/{E}/finalize`, `/void`, `/amendments`; POST `/documents/{DOC}/amendments` | IntegrityService `finalize()`, `void()`, amendments/revisions → `clinical_encounter_final_notes`, `clinical_encounter_amendments`, `clinical_document_revisions`, `clinical_documents`, E terminal | A+D/P/E/DOC, locks/transacción, nota final inmutable, idempotencia y M. No reabrir/reescribir terminal; recuperación relee resultado durable. Autoridad profesional general por operación todavía no plenamente integrada; no certificar cierre médico operativo. C, E-M5 y E-AUTH. |
| CLIN-07 — casos activos y agrupación de ítems; miembro A | Controles de caso en app.js/encounter UI → GET `/patients/{P}/cases/active`, GET/POST `/patients/{P}/cases`, PATCH `/cases/{id}`, POST `/cases/{id}/activate`, GET/POST `/cases/{id}/items` | Funciones `clinical_case_*` del [dispatcher][clinicalapi] → `clinical_cases`, `clinical_case_items`; referencias a E/documento del mismo P | A+D/P y scope del caso; referencia de otro paciente rechazada. Activación no equivale a crear un encounter. Sin externa adicional. R; verificar UX concreta si una tarea modifica ese selector dinámico. |
| CLIN-08 — timeline mixto Agenda/clínico; miembro A | [timeline.php][timeline], [historial.php][historyui], app.js → GET `/patients/{P}/timeline` (`include=agenda,clinical`) | Lectura `clinical_timeline_*`, [clinical_document_timeline_read][timelineread], catálogo de timeline → E, `agenda_appointments`, documentos; dedup por identidad/relación/fecha | A+D/P; una misma cita no se duplica como encuentro; referencias legadas con fecha distinta preservadas. Sólo lectura. Sin externa adicional. R; [contrato timeline](../clinical/TIMELINE_V1_CONTRACT.md). |
| CLIN-09 — resumen longitudinal e historial consultable; miembro A | `#lon01-history`, panel de resumen; [lon01-history][lonhistory], [lon02-summary][lonsummary] → GET `/patients/{P}/longitudinal-summary`, `/longitudinal-history` | Read models en dispatcher agregan E/sections/observations/documents, legado `clinical_record_entries` y módulos longitudinales; no nuevo maestro | A+D/P; distinguir dato de consulta, persistente y histórico; navegación no copia ni promueve hechos. Sin externa adicional. R. |
| CLIN-10 — antecedentes y alergias con revisión; miembro A | `#lon03b-antecedents`, `[data-lon03b-form]`; [lon03b][lonante] → GET/POST `/patients/{P}/longitudinal/{antecedents,antecedent-reviews,allergies,allergy-reviews}`, GET/PATCH `/{id}`, GET `/history` o `/{id}/history` | [ClinicalLongitudinalAntecedents][antecedents] → `clinical_patient_antecedent_facts`, `clinical_patient_antecedent_reviews`, `clinical_patient_allergies`, `clinical_patient_allergy_reviews`, `clinical_longitudinal_audit_events`, `clinical_longitudinal_idempotency`; documentos/E sólo como procedencia | A+D/P, versión/idempotencia, transacción+audit; reportado ≠ clínicamente revisado; no sustituir snapshot de consulta. Profesional por operación no acreditado automáticamente. Sin externa adicional. R; [LON03A](../../modules/clinical/docs/LON03A_ANTECEDENT_AUTHORITY.md). |
| CLIN-11 — problemas y promoción explícita; miembro A | `#lon04b-problems`, `[data-lon04b-m7-promote]`; [lon04b][lonproblem] → GET/POST `/patients/{P}/longitudinal/problems`, POST `/promotions`, PATCH `/{id}`, POST `/{id}/{resolve,mark-inactive,activate,reactivate}`, GET historial | [ClinicalLongitudinalProblems][problems] → `clinical_patient_problems`, `clinical_patient_problem_audit_events`, ledger longitudinal; promoción fija E/section fuente | A+D/P, estado/versión y razón; no promover todo diagnóstico automáticamente ni deducir resuelto del texto. Sin externa adicional. R; [LON04A](../../modules/clinical/docs/LON04A_PROBLEM_AUTHORITY.md). |
| CLIN-12 — medicación actual/referida/derivada y conciliación; miembro A | `#lon05b-medications`; [lon05b][lonmedui] → GET/POST `/patients/{P}/longitudinal/medications`, POST `/patient-reported`, `/from-prescription`; PATCH `/{id}`, POST `/{id}/{confirm-active,discontinue,complete}`; GET historial; GET/POST `/medication-reconciliations` | [ClinicalLongitudinalMedications][medications] → `clinical_patient_medication_lists`, `clinical_patient_medications`, reconciliations/items/audit y ledger | A+D/P, CAS de lista+items, locks y transacción; receta no confirma toma real; conciliación explícita preserva procedencia. Sin externa adicional. R; [LON05A](../../modules/clinical/docs/LON05A_MEDICATION_AUTHORITY.md). |
| CLIN-13 — tareas y seguimiento con relación a Agenda; miembro A | `#lon06b-tasks`, `[data-lon06b-follow-up-appointment-select]`; [lon06b][lontaskui], [plan02b][planui] → GET/POST `/patients/{P}/longitudinal/tasks`, PATCH `/{id}`, POST `/{id}/{resolve,cancel}`, GET historial; GET `/longitudinal/follow-ups/agenda` | [ClinicalLongitudinalTasks][tasks] → `clinical_patient_tasks`, task audit/ledger; lee citas del mismo P/D | A+D/P y versión; ligar cita no la crea/cancela ni resuelve seguimiento automáticamente. Fecha límite independiente. Sin externa adicional. R; QA de fuente `lon06a_http_gate.py`, `plan02b_agenda_browser.py` sin ejecución. |
| CLIN-14 — tendencias de mediciones; miembro A | `#lon07c-measurements`; [lon07c][lonmeasureui] → GET `/patients/{P}/longitudinal/measurements` | [ClinicalMeasurementTrends][trends] lee observaciones/encounter/procedencia y cursor; no writer de valores calculados | A+D/P; sólo tiempo autoritativo comparable entra a gráfico, invalidación e historia preservadas; no interpolación/rango médico inventado. Sin externa adicional. R; [MEAS01](../clinical/MEAS01-observation-invalidation.md). |
| CLIN-15 — planes y sesiones de tratamiento; cuenta con grants clínicos explícitos | UI de ejecución TRT04 no integrada por este contrato; CAPI `/patients/{P}/treatments`: GET/POST `/plans`, GET `/plans/{id}`, POST `/plans/{id}/transition`; GET/POST `/sessions`, GET/PATCH `/sessions/{id}`, POST `/{id}/{complete,void,correct}`, GET `/sessions/standalone-history` | [ClinicalTreatments][treatments] y [clinical_treatment_route][treatmentroute] → `clinical_treatment_plans`, `clinical_treatment_sessions`, plan audit, `clinical_performer_authorizations`, ledger clínico | A+D/P; performer/responsable deben tener grant vigente, cuenta/membresía activas, relación E/cita/consultorio exacta, tiempo UTC; corrección crea sucesor. No HTTP de alta de grants. UI de tratamientos del perfil no prueba esta integración. D; [TRT04](../../modules/clinical/docs/TRT04_TREATMENT_AUTHORITY.md). |
| CLIN-16 — estancia y notas hospitalarias; miembro A | Manejo hospitalario, [manejo-hospitalario.js][hospitalui] → GET `api/hospital-stays.php?action=current`, POST `?action=start`, POST `?action=close`; documentos vía CAPI doctor-scoped | [hospital-stays.php][hospitalapi] → `hospital_stays`; [clinical_documents_hospital][hospitaldocs] construye `nota_evolucion_hosp` y `hoja_indicaciones` en maestro documental | Identidad legacy HTTP canónica+D/P; apertura/cierre usa scope y validación; datos demo son mocks explícitos. Generación profesional conserva V y M. No historia de hospital independiente inferida. Sin externa adicional. U: read source, no aceptación normal actual. |
| CLIN-17 — ventana M6, cohortes y observabilidad; operación autorizada | No pantalla médica nueva; [dispatcher][clinicalapi] antes de writers; [m6_write_window][window], [m6_cutover][cutover], [m6_observability][observability], reglas de monitoring | Config servidor `MXMED_CLINICAL_M6_*`; contadores/artefactos operacionales; selector por pareja D/P. Writer viejo bloqueado aunque V1 falle | Flag/cohorte/ventana son independientes; config inválida falla cerrado, 503 `M6_WRITE_WINDOW_BLOCKED`/`M6_WRITE_WINDOW_CONFIG_INVALID`; no ejecutar scripts de control por leer mapa. C; [write window](../clinical/M6_WRITE_WINDOW_CONTROL.md), [monitoring](../clinical/M6_MONITORING_OBSERVABILITY.md). |
| CLIN-18 — navegación, borrador y cambios sin guardar; usuario del workspace | [patient-workspace-navigation-guard][dirty] registra fuentes únicas y captura tabs/sidebar/CTA/cambio de paciente; [m7][m7], [editor-host][editorhost], app.js registran progreso | Estado local/Map y borradores del módulo; guard `register/request`, waits de guardado, conflicto servidor; no write propio | No ofrecer descartar mientras write en progreso; cancelar mantiene contexto, fallo save impide navegar, epoch evita respuesta tardía. Borrador local no sustituye versión SQL. Sin externa adicional. R; QA fuente `step2_draft_recovery_r2_browser.py`. |
| CLIN-19 — diagnóstico/dev y schema readiness; desarrollador dentro de alcance explícito | GET CAPI `/health`, `/version`; POST `/debug/seed_document`, `/debug/seed_encounter`; scripts `modules/clinical/qa/` y `scripts/clinical-*` descubiertos por inventario raíz | `clinical_schema_readiness.php`, [readiness_authority.json][schemaready]; seed legacy y DDL helpers en gateway, herramientas de migration/reconciliation | Health/version no certifican DB ni permisos. Debug/seed usa condiciones dev y es **mutante**; no invocar para descubrimiento. Mapa no autoriza ejecutar QA, reparación, DDL ni fixtures. H; revisar gate exacto de cada herramienta antes de uso. |

## Estudios, órdenes, resultados y dental

| ID / propósito y actor | UI/handler y método/ruta | Autoridad de catálogo, writer/read y datos | Gates, resultados, dependencia, brecha y ejes |
|---|---|---|---|
| STUDY-01 — descubrir estudios por navegación/búsqueda; miembro A | `#t-estudios`, [vis06-modules][modulesui] genera `.vis06-intent-card` y navegación; [tax03c composer][studyui], `[data-tax03c-key]`, navegación progresiva/especialidad/favoritos; GET CAPI `/doctors/{D}/study-types` | [clinical_study_catalog_read()][studyread], [clinical_study_search][studysearch], `clinical_study_types` + JSON versionados en `modules/clinical/catalog/` | A `clinical_record`, D; catálogo activo y términos curados; no búsquedas difusas convierten alias en identidad. Sin proveedor externo en lookup. R; E-STUDY. |
| STUDY-02 — componer lote de órdenes; miembro A | `#t-estudios .ordcomp-host` generado por [vis06-modules][modulesui] → `mxmedOrderCompositionV1.mount()`, [order-composition-v1][orderui], tax03c; markup previo `[data-role="ac-order-entry-selector"]` conservado → POST `/doctors/{D}/patients/{P}/orders/batch` | [clinical_order_composition_create()][orderwrite] orquesta `clinical_v1_document_insert()` y ledger; `clinical_documents`, `clinical_idempotency_requests`, routing JSON | A+D/P+M, UUID lote=Idempotency-Key, máx20 órdenes/100 ítems; valida todo antes de write, transacción global, replay exacto. No concluye integración de V por existir indicación: requiere revisión de autoridad por operación. Sin externa adicional. R; E-STUDY. |
| STUDY-03 — laboratorio, paneles, presets y toma de muestra; miembro A | Tax03c/lab navigation y selección de recolección → STUDY-01/02 | [clinical_lab_panel][labpanel], [clinical_lab_presets][labpreset], [clinical_specimen_requirements][specimen]; catálogo y JSON lab/panel/specimen; snapshots por ítem documental | Panel verdadero tiene identidad; preset expande componentes, no fabrica un estudio. Gasometría registra oxígeno/contexto, tiempo y espécimen requeridos; alias insuficiente denegado422. Sin LIS externo acreditado. R; E-STUDY y contratos LAB_CAT05B/C, URINE_FLUIDS_CAT03B. |
| STUDY-04 — imagen y parámetros; miembro A | [imaging-parameters-v1][imagingui] montado por tax03c → STUDY-01/02 | [clinical_imaging_parameters][imaging]; `imaging_order_parameters_v1.json`, snapshot tipo/contraste/lateralidad/región | Validación servidor por estudio, versión y campos; UI no inventa autoridad de catálogo. Proveedor ejecutor no implica PACS/DICOM integrado. Sin externa adicional. R; [IMG_CAT02B](../clinical/IMG_CAT02B_IMAGING_MINIMUM_SAFE_CATALOG_IMPLEMENTED.md). |
| STUDY-05 — patología y muestra; miembro A | [pathology-parameters-v1][pathui] → STUDY-01/02 | [clinical_pathology_parameters][pathology], `pathology_order_parameters_v1.json`; snapshot de muestra/sitio/técnica | Contrato curado, no confundir procedimiento de toma con análisis definitivo. Campo inválido422, versiones fijas en orden. Sin proveedor remoto. R; [PATH_CAT02B](../clinical/PATH_CAT02B_PATHOLOGY_MINIMUM_SAFE_CATALOG_IMPLEMENTED.md). |
| STUDY-06 — funcionales y parámetros técnicos; miembro A | [functional-parameters-v1][functionalui] → STUDY-01/02 | [clinical_functional_parameters][functional], `functional_order_parameters_v1.json`; snapshots y label legible | No asumir disponibilidad por hospital/especialidad; protocolo técnico acotado. Sin equipo remoto conectado. R; [FUNC_CAT02B](../clinical/FUNC_CAT02B_FUNCTIONAL_QUALITY_GI_IMPLEMENTED.md). |
| STUDY-07 — procedimientos diagnósticos y routing; miembro A | Tax03c y grupos de orden → STUDY-01/02 | [clinical_diagnostic_procedure][procedure], `diagnostic_procedure_scope_v1.json`, `study_order_routing_v1.json`; documento/ítems | Procedimiento diagnóstico de catálogo ≠ sesión de tratamiento TRT04; custom requiere routing confirmado, duplicado inválido. Sin proveedor remoto. R; [PROC_CAT02B](../clinical/PROC_CAT02B_MINIMUM_SAFE_PROCEDURES_IMPLEMENTED.md). |
| STUDY-08 — registrar/consultar resultado exacto; miembro A | `[data-doc-tool="result"]`, `#res02a-linked-result` **creado dinámicamente**; [res02a composer][resultui], [m7-ws04][ws04] → POST `/encounters/{E}/documents` o `/doctors/{D}/patients/{P}/documents`; GET documentos CAPI | Writer documental canónico y [clinical_order_result_read][orderresult]; `clinical_documents`/revisions, orden/ítem fuente exactos, adjunto privado opcional | A+D/P/E/DOC, versiones/ítems de orden, mismo paciente; resultado tiene identidad propia, puede llegar tras cierre bajo política, no reescribe orden. Validación/interpretación profesional distinta de upload. Sin interfaz LIS automática acreditada. C; E-M5/E-STUDY. |
| STUDY-09 — orden portable, impresión/PDF y coincidencia de prestadores; miembro A | [portable-order.php][portable], [portable-order-pdf.php][portablepdf]; GET CAPI `/doctors/{D}/portable-orders/{DOC}`, `/doctors/{D}/patients/{P}/study-orders/{DOC}/provider-matches` | [clinical_portable_order_read()][portableread], [clinical_portable_order_pdf][pdf]; [healthcare_study_interop][interop] → Agenda `HealthcareStudyProviderMatchingService` y registro de capacidades de prestador | A+D/P/document_version, región y modo exactos; matching es lectura sin reservar/contratar. PDF usa Chromium local `MXMED_PORTABLE_PDF_CHROME`, falla503 sin emisión falsa; no genera acceso público a expediente. R; E-STUDY. |
| DENT-01 — ubicación odontológica de estudios; miembro A | Diálogo generado `[data-tax03c-dental]`; [dental-odontogram-v2][dentalui], [adapter][dentaladapter], V1 compatible → STUDY-02 | [clinical_dental_location_v2][dental], [clinical_dental_acquisition][dentalacquisition]; FDI/ISO3950 y dentición permanente/temporal/mixta, snapshot `dental_location` y protocolo | Servidor valida modalidad, piezas/arco/región y versión; panorámica no fuerza odontograma; oclusal maxilar/mandibular son dos ubicaciones válidas, idéntica ubicación duplica. Sin imagen remota. R; E-DENT. |
| DENT-02 — odontograma diagnóstico/terapéutico completo y ficha dental independiente | No ruta/writer separado identificado en `modules/clinical`, `api/_lib` ni UI dental inspeccionada; contratos DENTAL_ODONTOGRAM01/02 describen selección de **ubicación** | No se encontró autoridad de evolución dental/tooth-chart clínico; no convertir JSON de orden en nuevo expediente | Alcance distinto del selector entregado; no crear módulo por analogía ni inventar una propuesta aprobada sin decisión Director. No proveedor. N; este alcance no niega un producto dental de perfil público en otros dominios. |

## Documentos clínicos y Recetas

Todos los writers de familia siguientes se alcanzan desde **POST CAPI
`/doctors/{D}/patients/{P}/documents`** mediante `document_type` validado; el dispatcher
llama al escritor concreto, no a un writer paralelo. GET `/doctors/{D}/patients/{P}/documents`
y GET `/doctors/{D}/documents/{DOC}` son lectores doctor-scoped. Preview no equivale
a emisión. E puede ser explícitamente nulo cuando el contrato longitudinal lo permite;
no inventar consulta para poder emitir. [F06](INTERACCIONES_CLINICAS_MXMED.md#f06-documento-borrador-a-historia).

| ID / familia y actor | UI/handler; ruta adicional | Writer/render/read y persistencia | Gates, resultado, brecha mínima y ejes |
|---|---|---|---|
| DOC-01 — consentimiento informado; miembro A, paciente/representante y médico en firmas | `#ci_new_btn`, `#ci_review_panel`, `#ci_signatures_panel`, `#ci_save`, `#ci_emit`; app.js y [consent binding][consentbindingui]; writer común; QR SIG-03 | [clinical_consent_create()][consentwriter], [clinical_consent_signature_binding][consentbinding]; `clinical_documents`, participantes/evidencia remota; no inferir que `clinical_consents` legacy sea el writer actual | A+D/P, borrador/versión; emisión granted requiere firmas de ambos lados válidas y V médico; anexos identificatorios privados. Incompleto conserva draft. G para emisión; borrador implementado R; [consentimiento](../CONSENTIMIENTO_INFORMADO_MXMED.md). |
| DOC-02 — consentimiento multimedia | `[data-action="documents-open-multimedia"]`; app.js y [multimedia binding][multimediabindingui]; POST `.../multimedia-preview`, writer común | [clinical_multimedia_consent_write][multimediawriter], render/binding homónimos → maestro documental, roles/propósitos/medios snapshot | A+D/P, firmante y representación exacta; consentimiento multimedia no autoriza todo uso futuro por defecto. V para firma médica/emitir; error422 contrato. G en emisión, R en draft. |
| DOC-03 — responsiva médica | `#rm_declaration_text`, `#rm_emit`; app.js y [responsiva binding][responsivabindingui]; POST `.../responsiva-preview` | [clinical_responsiva_write][responsivawriter], render/binding; `clinical_documents`; paciente/responsable/médico y hashes de contenido | A+D/P, firmantes y declaración, CAS/binding; V en acto médico. Dibujo ajeno no se vuelve firma del médico. G para emisión, R en draft. |
| DOC-04 — certificado y subtipos/constancia | `[data-action="documents-open-certificado"]` y formulario certificado de shell; [certificado binding][certbindingui]; POST `.../certificado-preview` | [clinical_certificado_write][certwriter], render/binding; snapshot legal/presentación y maestro documental | A+D/P, contrato/subtipo/datos requeridos, firma/version/content fingerprint; V para generar/firmar. Constancia launcher oculto como subtipo no es otro writer. G para emisión; [QA CERT_EXT02](../../modules/clinical/qa/cert_ext02b_contract.py) en fuente. |
| DOC-05 — nota médica canónica | `[data-action="documents-open-nota"]`, campos `#nm_tratamiento_indicaciones`; [nota binding][notabindingui]; POST `.../nota-preview`, GET `.../nota-encounters[/{id}]` | [clinical_nota_write][notawriter], [clinical_nota_encounter_source][notasource], render/binding → snapshot de E/section y documento | A+D/P/E seleccionado, actor snapshot, versión fuente, no dictado mutable de E cerrado; V en emisión/firma. G para emisión, R en captura/preview. |
| DOC-06 — documento libre | Launcher documento libre y app.js; [free-document binding][freebindingui]; POST `.../free-document-preview` | [clinical_free_document_write][freewriter], normalize/render/binding → documento contractual, presentación congelada | A+D/P y creator/version; writer genérico rechaza `documento_libre`; V en firma/emisión. Texto libre no elimina scope ni binding. G para emisión, R en draft. |
| DOC-07 — alta médica | Formulario `#am_tratamiento`, `#am_recomendaciones`; [alta binding][altabindingui]; POST `.../alta-preview` | [clinical_alta_write][altawriter], render/binding; contrato V2 y maestro documental | A+D/P, contexto e indicaciones, versión/signature; generic builder no puede emitir alta. V conserva denegación. G para emisión, R en draft. |
| DOC-08 — informe médico | Formulario informe en shell; [informe binding][informebindingui]; POST `.../informe-preview`, GET `.../informe-encounters[/{id}]` | [clinical_informe_write][informewriter], render/binding y fuente de encounter canónica → documento/versiones | A+D/P/E si referido; autoridad/source exacta y V médico; error de source impide afirmar hechos no obtenidos. G para emisión, R en draft. |
| DOC-09 — interconsulta | `[data-action="documents-open-interconsulta"]`; [interconsulta binding][interconsultabindingui]; POST `.../interconsulta-preview`, GET `.../interconsulta-encounters[/{id}]` | [clinical_interconsulta_write][interconsultawriter], render/binding → maestro y source E | A+D/P, destinatario/contexto clínico, V en firma/emisión; no mensajería/entrega a otro médico acreditada sólo por generar documento. G para emisión, R en draft. |
| DOC-10 — editor Recetas V2 y borradores; miembro A `prescriptions` | `#t-tratamiento`, `#ne_rx_capture`, `#ne_rx_grid`, `#ne_rx_save_draft`, `#ne_rx_preview`; [editor-host][editorhost], app.js → GET `/patients/{P}/prescription-context`, GET `/doctors/{D}/patients/{P}/prescriptions/drafts`; POST `.../prescriptions`, `.../prescriptions/preview` | [clinical_prescription_v2_write/normalize/render][rxwriter], [ClinicalPrescriptionContext][rxcontext]; `clinical_documents` payload/snapshot/rendered_text, ledger clínico | A+D/P, creator exacto del draft, E opcional explícito; intent save_draft, CAS/version, regimen estructurado REC-REG02/03/04, preview sin writer. No crear consulta automáticamente ni inferir toma actual. Sin externa adicional. R; E-RX. |
| DOC-11 — plantillas de Recetas; miembro A `prescriptions` | `#ne_rx_use_template`, `#ne_rx_save_template`; app.js → GET/POST `/doctors/{D}/prescription-templates`, GET/PUT/PATCH/DELETE `/{uuid}`, POST `/{uuid}/{duplicate,archive}` | [clinical_prescription_template_*][rxtemplates] → `clinical_prescription_templates`, CAS; sólo contenido reutilizable | D servidor; plantilla excluye paciente/firma/contexto profesional; archived conserva hasta delete explícito permitido. Aplicar plantilla no emite ni adopta historial. Sin externa adicional. R; QA `rec_tpl02_disposable.py` inspeccionado como referencia. |
| DOC-12 — firma aplicada y emisión Recetas; cuenta médica calificada requerida | `#ne_rx_apply_signature`, `#ne_rx_registered_signature`, `#ne_rx_remote_signature`, `#ne_rx_emit_signed`; app.js y [prescription binding][rxbindingui]; POST `.../prescriptions` intents `apply_signature`, `finalize` | Writer V2 y [clinical_prescription_binding][rxbinding] → documento/snapshot/binding; firma registrada SIG-01 o QR SIG-03 | A+D/P/DOC+creator/version/fingerprint y V. V actual deniega con `PROFESSIONAL_AUTHORITY_REQUIRED`, aunque UI y writer existen; owner, membresía, imagen guardada y pago no bastan. Conectar consumidor clínico sólo bajo autorización posterior. G. |
| DOC-13 — historial y recetas históricas; miembro A `prescriptions` | `[data-rx-history-list]`, acciones de fila; [rec-ux01][recui] → GET `.../prescriptions/history`, GET doctor-scoped DOC | [clinical_prescription_history][rxhistory], proyección lifecycle, snapshots de `clinical_documents` | A+D/P, filtros/page/type, draft del creador; no reconstruir paciente/médico histórico con dato actual salvo proyección explícitamente rotulada. Leer dos recetas históricas no acredita emisión nueva. Sin externa adicional. R; E-RX. |
| DOC-14 — anular y reemitir Recetas | `[data-rx-lifecycle-form]`; rec-ux01 → POST `.../prescriptions/{uuid}/void`, `/reissue` | [clinical_prescription_lifecycle_void/reissue][rxlifecycle] → `clinical_prescription_lifecycle` y documentos, transacción/locks; reissue crea draft sucesor, completa al emitir | A+D/P/DOC, razón/creator y V; replay estable, original no se modifica clínicamente ni se duplica al retry. V bloquea transición actual. G. |
| DOC-15 — adjuntos privados, multipart y recuperación | Captura documental/M7/resultado; POST CAPI `/encounters/{E}/documents` multipart y adaptadores note capture; GET `/documents/{DOC}/binary/{variant}` | [ClinicalMultipartDocumentService][multipart], [ClinicalPrivateBinaryStorage][binary], [ClinicalPrivateBinaryRetrieval][binaryread] → `clinical_binary_uploads`, `clinical_document_binaries`, ledger y FS privado | A+D/P/E/DOC, MIME/tamaño/sha256/longitud, claves opacas fuera webroot, no URL directa. Staging→final create-only→commit SQL; reconciliación explícita en fallos. Código inspeccionado no cifra por sí mismo estos archivos: privacidad/ruta/hash ≠ cifrado. Sin externa remota en adapter local. C. |
| DOC-16 — leer, abrir, imprimir y versionar documentos | [viewer.php][viewer], [document.php][documentui], [encounter.php][encounterui], embeds/shared kits; GET CAPI `/documents`, `/documents/{DOC}`, `/bundles/{id}/documents`, rutas doctor-scoped; PATCH/POST replace/replicate según policy | Dispatcher + [clinical_documents][documentlib] + timeline read → maestro, revisions/lineage/participants; reader portable separado STUDY-09 | A+D/P/E/DOC y política del tipo; genérico rechaza cambios de recetas e inmutables. Print usa snapshot/rendered_text; CSS actual puede afectar documento viejo. Viewer no token público general. Sin externa adicional salvo fuentes/CSS referenciados. R. |
| DOC-17 — encabezado profesional, consultorio y presentación versionada | `#ne_rx_presentation`, preview/final y [prescription-document-presentation][presentationui]; renderer compartido | [clinical_professional_presentation_resolve()][presentation], [legal presentation][legalpresentation], [clinical-doc-base.css][doccss] → datos verificados del perfil/logo aprobado/consultorio y snapshot contractual documental | Branding/plan/gates/opt-in no sustituyen fuente profesional. QR público conserva destino de perfil publicado y rótulo **AGENDA UNA CITA**. Variantes logo+datos/sólo datos/sin encabezado; cuidado historial CSS. Sin cambiar routing. R; E-PRESENT. |
| DOC-18 — wrappers históricos y notas hospitalarias/evolución | `api/clinical-documents.php?action={save,list,get}`, POST `api/evolution-note-generate.php`; [legacy documents][legacydocs], [evolution][evolutionapi], app.js/manejo-hospitalario | `mxmed_build_clinical_document()` / hospital builders → maestro `clinical_documents`, participantes | Identidad HTTP canónica+D/P+M; save/generate exige V, receta/alta/documento libre prohíben bypass genérico. CLI QA aislada distinta de HTTP. Mantener compatibilidad de lectura, no reactivar writer retirado. H. |
| DOC-19 — plantillas de consentimiento; miembro A `clinical_record` | `#ci_save_as_template`, `#ci_save_template_overlay`, `#ci_tpl_modal_title`; app.js → GET/POST `/doctors/{D}/consent-templates`, GET/PUT/PATCH/DELETE `/{uuid}`, POST `/{uuid}/{duplicate,archive}` | [clinical_consent_template_*][consenttemplates] → `clinical_consent_templates`; CAS/lista/archivo/duplicación y lectura de contenido permitido | D servidor; whitelist excluye paciente/firmas/anexos, aplicar sólo reutiliza contenido. No compartir tabla ni entitlement Rx por semejanza visual. Sin externa adicional. R; QA fuente `cons_tpl03_disposable.py` y `cons_tpl04_disposable.py`. |
| DOC-20 — actividad clínica de procedimiento/vacuna/aplicación/curación; miembro A | `[data-action="open-actividad-clinica"]`, `[data-action="actividad-clinica-open-immunization"]`, `#acProcType`, `[data-action="ac-proc-submit"]`; app.js modal host → POST `/doctors/{D}/patients/{P}/documents` | Builder de tipos `procedure`, `immunization`, `medication_administration`, `wound_care`; maestro documental y timeline, evento UI `mxmed:clinical-document-created` | Actor recibido por UI no autoriza servidor; A+D/P/E si existe, tipo/fecha/lugar y M. No reinterpretar estos registros como TRT04 plans/sessions o catálogo diagnóstico. Autoridad médica por operación pendiente de integración/ratificación. Sin externa adicional. H. |

Las familias comparten firma UX en [clinical-document-signature-experience.js][signatureux],
binding por contenido/versión y estructura de documentos. No se repiten sus reglas
en cada plantilla. **DOC-12/14 G no convierte todos los borradores o lectores en inactivos.**

## Firmas, dispositivos, captura y placeholders

| ID / propósito y actor | UI/handler y ruta | Servicio/writer/read / datos | Vigencia, fallos, dependencia y ejes |
|---|---|---|---|
| SIG-01 — firma registrada privada; contexto propio resuelto en servidor | `#dg-signature-card`, `#dg-signature-pad`, `#dg-signature-save`; [physician-signature.js][signatureui] → GET/POST/DELETE `/api/media/physician-signature.php` | [PhysicianSignatureService][signatureservice], SignatureImage, [GallerySessionScope][galleryscope] → `physician_signatures`, PrivateMediaStoragePort objeto immutable PNG, audit canónico | CSRF `X-Signature-CSRF`, normalización/cotas/integridad, lock perfil+audit+SQL en transacción; falla conserva fila previa y cleanup best effort. No cifrado de aplicación observado en este servicio; storage privado/S3 adapter determina capa externa. Guardar dibujo **no concede V**. R; E-SIG. |
| SIG-02 — registrar esa firma desde otro dispositivo | `#dg-signature-handoff-open`, [signature-handoff-device.js][handoffui], [signature-handoff.php][handoffpage] → GET/POST/DELETE `/api/media/signature-handoff.php`, POST `/api/media/signature-handoff-device.php` | [SignatureHandoffService][handoffservice] y [SignatureHandoffHttp][handoffhttp]; `physician_signature_handoffs` guarda SHA256 del bearer, purpose y creator; completa en `saveAtomically()` de SIG-01 | 300s, one-shot, nueva sesión invalida PENDING anterior, status/cancel desktop dueño+CSRF; dispositivo bearer-only sin cookie Identity, no permiso Admin. URL `MXMED_SIGNATURE_HANDOFF_BASE_URL`; HTTP sólo loopback para review automatizado. Móvil real requiere HTTPS/origin alcanzable; no se verificó red. R; E-SIG. |
| SIG-03 — firma de una versión documental por QR; firmante designado | `#modalConsentSignatureQr` y diálogo Rx; app.js/signature UX → CAPI POST `/{family}-qr-sessions`, GET `/{token}/status`, `/mobile-context`, POST `/{token}/{review,signature,cancel,invalidate}`; consentimiento vía `/note-capture-tokens` y contexto específico | [clinical_prescription_qr][rxqr] y nueve handlers family; tablas `clinical_{responsiva,multimedia,certificado,nota,free_document,prescription,alta,informe,interconsulta}_qr_sessions`; review HTML/hash, versión, role, firma y consumo | QR clínico normalmente900s, invalida al cambiar contenido, revisión previa, claim/consume bajo lock. Bearer móvil no necesita cookie desktop; crear/pollear/cancelar sí A. Firma médica V denegada hoy, paciente/representante no adquiere autoridad clínica. Tokens clínicos son columnas `token` en fuente, no asumir hash por analogía con SIG-02. G médico; R mecanismo no profesional; F11. |
| SIG-04 — foto/multipágina de documento externo desde móvil | `public/note-capture.html`, [public/mobile-capture-session.js][captureui], workspace documental; CAPI POST `/mobile-capture-sessions`, GET `/{uuid}`, POST `/{uuid}/{pages,remove,reorder,finalize,cancel}`; token GET `/note-capture-tokens/{token}/mobile-context`, POST `/upload` | [ClinicalMobileCapture][mobilecapture] → `clinical_mobile_capture_sessions`, `clinical_mobile_capture_pages`, `clinical_note_capture_tokens`, objetos/thumbnail/manifests y DOC-15; [atomic adapter][captureatomic] para legado | Sesión1h, página min(fin sesión,900s); bearer `X-Capture-Continuation` o desktop A validado, encounter abierto, clasificación explícita. Finalizar consume todas páginas en transacción; cancelar/expirar retira pendientes, no borrar historia durable. Origen `clinical_capture_mobile_url()` y `MXMED_CAPTURE_PUBLIC_ORIGIN`, red externa no verificada. C; F11. |
| AI-01 — «Analizar receta con IA» y automatizaciones clínicas futuras | `#ne_rx_analysis_run` disabled, copy «Disponible próximamente bajo demanda» en [shell][shell]; confirm modal y validaciones locales de app.js | No llamada a proveedor IA de análisis encontrada en esta ruta; controles cliente no constituyen servicio clínico IA | Propósito de apoyo futuro; nunca afirmar diagnóstico/validación/terapia automatizados implementados por existir botón. Proveedor/config/consentimiento/activación sin resolver. P. |

## Evidencia y límites

La columna «evidencia» localiza aceptación histórica o tests existentes, no recertifica
la fuente actual completa. Fecha de inspección de **todas** las filas: 2026-10-17,
commit de lectura `1b8dfccb68a7613b2182507a840454631dbf012a`, entorno repositorio local.
Un test presente sin informe se clasifica `source-inspected`, no PASS.

| ID | Evidencia/fecha y ancla verificable | Alcance y límite |
|---|---|---|
| E-PAT | [PatientReadAuthorizationTest](../../modules/patients/tests/PatientReadAuthorizationTest.php), última edición 2026-09-17, `13fe6e83bde6c6b623c3d8596408f7eb25065b26` | Fuente de tests del scope/401/403, no ejecución nueva ni cobertura de alta PAT-02. |
| E-M5 | [M5 QA sintética](../clinical/EXPEDIENTE_ENCOUNTER_M5_SYNTHETIC_QA.md), documento 2026-09-19 `b069fe7cfa66fc71a2aaf323676dc74a411f1ec2`; suite anclada `115923cac322958ad4f443ab783a8cf19f9c5093` | 35 escenarios reportados en DB/runtime desechables y concurrencia; no autoridad médica posterior ni QA física actual. |
| E-AUTH | [CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md), 2026-10-15 `3fc19bc59d5aa9fc73d739035b2f30737bc85168`; [CLIN-AUTH02B1](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md) | Contención instalada; matriz amplia de delegación/profesional es contrato propuesto donde así indica. C4 entregado después no implica consumidor clínico conectado. |
| E-RX | [contrato REC-REG03](../../modules/clinical/qa/rec_reg03_contract.php), edición 2026-10-14 `75bc60e286715c6e818fce732f967ac9bfd2bccc`; [preview](../../modules/clinical/qa/rec_preview01_contract.php), `f67beafbc4ad4da8beb25e3763743702978856e4` | Fuente de contrato/regímenes/preview; aceptación previa no salta restricción profesional añadida después. |
| E-STUDY | [STUDY-COVERAGE02](../clinical/STUDY_COVERAGE02_CROSS_FAMILY_CLOSEOUT.md), 2026-10-05 `1c0ab69285a1370b9591a12723c55be81e2a7778`; baseline propio `6edd6e485ac861121f3a9207476946da97bb98a5` | 289 estudios activos **en su DB de review histórica**, lote seis órdenes, siete resultados, rollback/replay, HTML/PDF en desechable. No nuevo conteo global instalado. Distingue doce checks de contadores históricos obsoletos. |
| E-DENT | [DENTAL-ODONTOGRAM02](../clinical/DENTAL_ODONTOGRAM02_UNIFIED_SELECTOR_V2_IMPLEMENTED.md), 2026-10-04 `99c7694a843c64efc713e828e932f716a9daa8ac` | Ubicación V2/UI/contratos y ensayo desechable; no ficha dental de diagnóstico/tratamiento. Propuesta ODONTOGRAM01 sucede a implementación02 sólo en alcance selector. |
| E-SIG | [SIG03A](../product-completion/SIG03A-canonical-signature.md), 2026-09-14 `97f85f3bccc6c3ba25acf1d8c8ed8b6ade921488`; [SIG03B](../product-completion/SIG03B-secure-device-handoff.md) | QA sintética física/HTTP y navegador con mutaciones mock; no firma real ni smartphone remoto actual. |
| E-PRESENT | [UX-ALIGN01](../clinical/UX-ALIGN01-prescription-presentation-assessment.md), 2026-10-16 `4429f4140620a250d70aa67ac7d060bc43484907` | Preview/jerarquía existentes; no justificar reemplazar renderer. [DOC_REC_STD03A](../clinical/DOC_REC_STD03A_SCHEMA_PROPOSAL.md) es propuesta histórica de schema: contrastar migración/presentación actual. |
| E-ACT04 | [estado reconciliado](../ESTADO_ACTUAL_MXMED.md#evidencia-reciente-y-reconciliación-de-tests), baseline de entrega `1b8dfccb68a7613b2182507a840454631dbf012a` | Los tres fallos históricos ACT02/03 fueron corregidos; no reportarlos fallando hoy por copiar texto antiguo. AUTH02A_29 admite fixture UX-ALIGN01 exacta con pins conservados. No suites ejecutadas en CTX-MAP02. |

## Reconciliación y siguientes lecturas mínimas

- `modules/clinical/README.md` y `modules/clinical/db/README.md` todavía describen
  scaffolding futuro; `modules/clinical/docs/API_V1.md` es contrato antiguo sin
  implementación. Son **históricos para existencia del módulo**: gateway, maestros,
  writers/UI y contratos posteriores arriba acreditan implementación en fuente.
- `schema_v1.sql/clinical_patients` no sustituye `patients_patients`; `schema_v2.sql`
  conserva historia de transición. FK física vs asociación lógica y migración
  instalada/preparada corresponden al [mapa de datos](DATOS_Y_MIGRACIONES_MXMED.md).
- Los manuales que aún afirman inexistencia de account-professional binding se
  contrastan con C4 entregado. La decisión clínica V sigue fail-closed en el código
  actual, sin nueva regla empresarial de «nadie debe firmar».
- Backend de órdenes, longitudinal y tratamiento no prueba autorización profesional
  por operación totalmente concluida. CLIN-AUTH02B1 separa preparación, acto médico,
  firma y delegación; grants Agenda no abren expediente ni prescripción.
- Patient alta/bridge/dev, tiempos y CSRF de handlers dinámicos requieren auditoría
  específica si se autoriza cambiar su frontera; este inventario no ejecuta probes.
- Dental encontrado es catálogo/ubicación/protocolo de estudios. Hospitalización
  tiene API/constructor/UI y demo explícita; no certificar producto hospitalario pleno.
- No hay nuevo conteo de recetas/pacientes/órdenes reales ni tokens, firmas, payloads
  o documentos privados en este mapa. No activar C4, M6, firma, SMS ni migraciones.

Mantenimiento: actualizar únicamente las filas afectadas con commit/fecha/entorno,
writer y próximo gap real tras un cambio autorizado. Entrada general:
[inventario funcional](INVENTARIO_FUNCIONAL_MXMED.md); siguiente acción producto:
**NONE_PENDING_DIRECTOR_REVIEW**.

[shell]: ../../private-app.php
[app]: ../../assets/js/app.js
[clinicalapi]: ../../api/clinical/index.php
[resolver]: ../../modules/identity/http/CanonicalClinicalIdentityResolver.php
[professional]: ../../api/_lib/clinical_professional_authority.php
[patientsearch]: ../../modules/patients/controllers/SearchDoctorPatientsController.php
[patientcreate]: ../../modules/patients/controllers/CreatePatientController.php
[patientsave]: ../../modules/patients/controllers/SavePatientDetailsController.php
[patientsrepo]: ../../modules/patients/repositories/PatientsRepository.php
[detailsrepo]: ../../modules/patients/repositories/PatientDetailsRepository.php
[patientauthority]: ../../modules/patients/composition/PatientsAuthorityCompositionRoot.php
[patientresolver]: ../../modules/patients/identity/PatientIdentityResolver.php
[patientpersist]: ../../modules/patients/identity/persistence/PdoPatientIdentityPersistenceAdapter.php
[truth]: ../clinical/DECISION_FUENTES_DE_VERDAD.md
[recui]: ../../assets/js/clinical/rec-ux01.js
[adminui]: ../../assets/js/clinical/vis07-administrative.js
[navigation]: ../../assets/js/clinical/vis01-navigation.js
[header]: ../../assets/js/clinical/vis02-patient-header.js
[m7]: ../../assets/js/clinical/m7-workspace.js
[consultui]: ../../assets/js/clinical/vis04-consultation.js
[integrity]: ../../api/_lib/clinical_encounter_integrity.php
[sections]: ../../api/_lib/clinical_encounter_sections.php
[observations]: ../../api/_lib/clinical_observations.php
[vitalrefs]: ../../api/_lib/clinical_vital_references.php
[ws03]: ../../assets/js/clinical/m7-ws03.js
[ws04]: ../../assets/js/clinical/m7-ws04.js
[ws05]: ../../assets/js/clinical/m7-ws05.js
[ws06]: ../../assets/js/clinical/m7-ws06.js
[timeline]: ../../modules/clinical/ui/timeline.php
[historyui]: ../../modules/clinical/ui/historial.php
[timelineread]: ../../api/_lib/clinical_document_timeline_read.php
[lonhistory]: ../../assets/js/clinical/lon01-history.js
[lonsummary]: ../../assets/js/clinical/lon02-summary.js
[lonante]: ../../assets/js/clinical/lon03b-antecedents.js
[antecedents]: ../../api/_lib/clinical_longitudinal_antecedents.php
[lonproblem]: ../../assets/js/clinical/lon04b-problems.js
[problems]: ../../api/_lib/clinical_longitudinal_problems.php
[lonmedui]: ../../assets/js/clinical/lon05b-medications.js
[medications]: ../../api/_lib/clinical_longitudinal_medications.php
[lontaskui]: ../../assets/js/clinical/lon06b-tasks.js
[tasks]: ../../api/_lib/clinical_longitudinal_tasks.php
[planui]: ../../assets/js/clinical/plan02b-next-steps.js
[lonmeasureui]: ../../assets/js/clinical/lon07c-measurements.js
[trends]: ../../api/_lib/clinical_measurement_trends.php
[treatments]: ../../api/_lib/clinical_treatments.php
[treatmentroute]: ../../api/_lib/clinical_treatment_routes.php
[hospitalui]: ../../assets/js/manejo-hospitalario.js
[hospitalapi]: ../../api/hospital-stays.php
[hospitaldocs]: ../../api/_lib/clinical_documents_hospital.php
[window]: ../../api/_lib/clinical_m6_write_window.php
[cutover]: ../../api/_lib/clinical_m6_cutover.php
[observability]: ../../api/_lib/clinical_m6_observability.php
[dirty]: ../../assets/js/clinical/patient-workspace-navigation-guard.js
[editorhost]: ../../assets/js/clinical/prescription-editor-host.js
[schemaready]: ../../modules/clinical/schema/readiness_authority.json
[studyui]: ../../assets/js/clinical/tax03c-study-composer.js
[modulesui]: ../../assets/js/clinical/vis06-modules.js
[studyread]: ../../api/_lib/clinical_study_catalog_read.php
[studysearch]: ../../api/_lib/clinical_study_search.php
[orderui]: ../../assets/js/clinical/order-composition-v1.js
[orderwrite]: ../../api/_lib/clinical_order_composition.php
[labpanel]: ../../api/_lib/clinical_lab_panel.php
[labpreset]: ../../api/_lib/clinical_lab_presets.php
[specimen]: ../../api/_lib/clinical_specimen_requirements.php
[imagingui]: ../../assets/js/clinical/imaging-parameters-v1.js
[imaging]: ../../api/_lib/clinical_imaging_parameters.php
[pathui]: ../../assets/js/clinical/pathology-parameters-v1.js
[pathology]: ../../api/_lib/clinical_pathology_parameters.php
[functionalui]: ../../assets/js/clinical/functional-parameters-v1.js
[functional]: ../../api/_lib/clinical_functional_parameters.php
[procedure]: ../../api/_lib/clinical_diagnostic_procedure.php
[resultui]: ../../assets/js/clinical/res02a-linked-result-composer.js
[orderresult]: ../../api/_lib/clinical_order_result_read.php
[portable]: ../../modules/clinical/ui/portable-order.php
[portablepdf]: ../../modules/clinical/ui/portable-order-pdf.php
[portableread]: ../../api/_lib/clinical_portable_order.php
[pdf]: ../../api/_lib/clinical_portable_order_pdf.php
[interop]: ../../api/_lib/healthcare_study_interop.php
[dentalui]: ../../assets/js/clinical/dental-odontogram-v2.js
[dentaladapter]: ../../assets/js/clinical/dental-location-v2-adapter.js
[dental]: ../../api/_lib/clinical_dental_location_v2.php
[dentalacquisition]: ../../api/_lib/clinical_dental_acquisition.php
[consentbindingui]: ../../assets/js/clinical/consent-signature-binding.js
[consentwriter]: ../../api/_lib/clinical_consent_write.php
[consenttemplates]: ../../api/_lib/clinical_consent_templates.php
[consentbinding]: ../../api/_lib/clinical_consent_signature_binding.php
[multimediabindingui]: ../../assets/js/clinical/multimedia-consent-signature-binding.js
[multimediawriter]: ../../api/_lib/clinical_multimedia_consent_write.php
[responsivabindingui]: ../../assets/js/clinical/responsiva-signature-binding.js
[responsivawriter]: ../../api/_lib/clinical_responsiva_write.php
[certbindingui]: ../../assets/js/clinical/certificado-signature-binding.js
[certwriter]: ../../api/_lib/clinical_certificado_write.php
[notabindingui]: ../../assets/js/clinical/nota-signature-binding.js
[notawriter]: ../../api/_lib/clinical_nota_write.php
[notasource]: ../../api/_lib/clinical_nota_encounter_source.php
[freebindingui]: ../../assets/js/clinical/free-document-signature-binding.js
[freewriter]: ../../api/_lib/clinical_free_document_write.php
[altabindingui]: ../../assets/js/clinical/alta-signature-binding.js
[altawriter]: ../../api/_lib/clinical_alta_write.php
[informebindingui]: ../../assets/js/clinical/informe-signature-binding.js
[informewriter]: ../../api/_lib/clinical_informe_write.php
[interconsultabindingui]: ../../assets/js/clinical/interconsulta-signature-binding.js
[interconsultawriter]: ../../api/_lib/clinical_interconsulta_write.php
[rxwriter]: ../../api/_lib/clinical_prescription_v2.php
[rxcontext]: ../../api/_lib/clinical_prescription_context.php
[rxtemplates]: ../../api/_lib/clinical_prescription_templates.php
[rxbindingui]: ../../assets/js/clinical/prescription-signature-binding.js
[rxbinding]: ../../api/_lib/clinical_prescription_binding.php
[rxhistory]: ../../api/_lib/clinical_prescription_history.php
[rxlifecycle]: ../../api/_lib/clinical_prescription_lifecycle.php
[multipart]: ../../api/_lib/clinical_multipart_document_service.php
[binary]: ../../api/_lib/clinical_private_binary_storage.php
[binaryread]: ../../api/_lib/clinical_private_binary_retrieval.php
[viewer]: ../../modules/clinical/ui/viewer.php
[documentui]: ../../modules/clinical/ui/document.php
[encounterui]: ../../modules/clinical/ui/encounter.php
[documentlib]: ../../api/_lib/clinical_documents.php
[presentationui]: ../../assets/js/clinical/prescription-document-presentation.js
[presentation]: ../../api/_lib/clinical_professional_presentation.php
[legalpresentation]: ../../api/_lib/clinical_legal_document_presentation.php
[doccss]: ../../assets/css/clinical-doc-base.css
[legacydocs]: ../../api/clinical-documents.php
[evolutionapi]: ../../api/evolution-note-generate.php
[signatureux]: ../../assets/js/clinical-document-signature-experience.js
[signatureui]: ../../assets/js/physician-signature.js
[signatureservice]: ../../modules/signatures/PhysicianSignatureService.php
[galleryscope]: ../../modules/media/services/GallerySessionScope.php
[handoffui]: ../../assets/js/signature-handoff-device.js
[handoffpage]: ../../signature-handoff.php
[handoffservice]: ../../modules/signatures/SignatureHandoffService.php
[handoffhttp]: ../../modules/signatures/SignatureHandoffHttp.php
[rxqr]: ../../api/_lib/clinical_prescription_qr.php
[captureui]: ../../public/mobile-capture-session.js
[mobilecapture]: ../../api/_lib/clinical_mobile_capture.php
[captureatomic]: ../../api/_lib/clinical_note_capture_atomic.php
