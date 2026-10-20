# Router de conocimiento — México Médico

Entrada para tareas acotadas. Leer primero [estado actual](ESTADO_ACTUAL_MXMED.md).
Verificar el worktree y HEAD reales; este índice localiza componentes, no autoriza su ejecución.
Las rutas se verificaron contra el baseline indicado en el estado; no constituyen una nueva auditoría funcional.

## Cómo consultar y decidir

1. Elegir el dominio y localizar UI → controlador/servicio → writer/read model.
2. Consultar su contrato y la decisión aplicable; confirmar alcance y vigencia en fuente.
3. Buscar símbolos con `rg` y leer fragmentos; no cargar todos los documentos o `app.js` completos.
4. Clasificar la brecha y elegir QA por impacto, revisando primero sus efectos.
5. Informar resultado, HEAD/entorno probado, límites, evidencia y siguiente autorización pendiente.

Etiquetas: **CURRENT** = referencia vigente para ese alcance, no certificación live;
**IMPLEMENTED_BUT_INACTIVE** = fuente entregada, operación normal-local deshabilitada;
**APPROVED_DECISION** = decisión registrada cuyo alcance debe respetarse;
**HISTORICAL** = evidencia de su fase, no estado actual;
**PENDING_AUTHORIZATION** = acción real que requiere aprobación específica.
Separar decisión, contrato implementado y evidencia QA; una prueba aislada no demuestra activación.

## Mapas actuales del sistema — CTX-MAP02

Baseline fuente `1b8dfccb68a7613b2182507a840454631dbf012a`, 2026-10-17. Elegir un mapa y sus símbolos; no cargar la colección completa. Fuente, conexión, política, verificación y operación se registran por separado. Inventario no equivale a cobertura funcional de tests ni activación.

| Necesidad | Referencia bajo demanda |
|---|---|
| Encontrar capacidad, UI original, autoridad y writer | [Inventario funcional](contexto/INVENTARIO_FUNCIONAL_MXMED.md): público, perfil, Identity, Agenda, Operadores, Media, provider, platform y superficies parciales |
| Paciente/consulta/expediente, estudios, documentos y firma | [Funciones clínicas](contexto/FUNCIONES_CLINICAS_MXMED.md), con gates y 59 capacidades |
| Seguir conexiones, transacciones, fallos e impacto | [Once journeys e interacciones](contexto/INTERACCIONES_MXMED.md), [trazas clínicas](contexto/INTERACCIONES_CLINICAS_MXMED.md) |
| Tablas, writers/readers, FK, metadata y migraciones | [Datos y migraciones](contexto/DATOS_Y_MIGRACIONES_MXMED.md), CSV/JSON enlazados por objeto/artefacto |
| AWS, runtime local, packaging y evidencia de despliegue/teardown | [AWS y entornos](contexto/AWS_Y_ENTORNOS_MXMED.md); remoto actual no verificado |
| Stripe, activación, facturación/PAC y otros proveedores | [Stripe y servicios externos](contexto/STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md); confirmación de pago y activación tienen dueños distintos |
| Límites, historia, doce preguntas y mantenimiento | [Cobertura y pendientes](contexto/COBERTURA_Y_PENDIENTES_MXMED.md) |
| Casos de negocio por canal/actor de Agenda, waitlist y Call Center | [Matriz CTX-MAP03](contexto/AGENDA_CANALES_Y_PERMISOS_MXMED.md): CTA integrado del perfil, médico, operador y límites de atribución |
| Captación comercial, asesor de venta/soporte, cupones y workspace interno | [Trazabilidad comercial CTX-MAP03](contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md): evidencia por caso y readiness, separadas de enrollment/C4 |

Cobertura medida: 2.967 paths del baseline, doce módulos, 183 entradas candidatas, 119 capacidades funcionales y 18 componentes AWS; metadata local de 128 tablas/204 nombres fuente o físicos. Las 49 declaraciones de rutas extraídas no son el universo de rutas dinámicas. Detalles, exclusiones, refs fuera del baseline e incertidumbres están en cobertura. Próxima acción de producto/piloto: `NONE_PENDING_DIRECTOR_REVIEW`.

CTX-MAP03 amplía casos concretos sobre `0bea8195ce75c309a54cba35acb4605ba290ee00`; conserva el censo/metadata de CTX-MAP02 sin reejecutarlo. La [traza fiscal BILL-01..06](contexto/STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) distingue médico, operador y staff. Fuente conectada no acredita operación física ni crea una nueva política comercial o delegación.

## Componentes y contratos

### Aplicación privada original — CURRENT
- UI y gate: [private-app.php](../private-app.php), [PrivateApplicationAccessResolver](../modules/identity/http/PrivateApplicationAccessResolver.php).
- Composición/sesiones: [IdentityHttpComposition](../modules/identity/http/IdentityHttpComposition.php), [SessionService](../modules/identity/services/SessionService.php); datos y writers siguen perteneciendo a cada dominio.
- Contrato: [autenticación](product-completion/authentication-productization-contract-v1.md). QA: [sesión privada](../modules/identity/tests/PrivateSession02ContractTest.php).
- Límite: preservar el shell autenticado; `index.html` y snapshots no sustituyen esta entrada.

### Perfiles públicos y teléfonos privados — CURRENT
- UI: [perfil público](../profiles/doctor.php), [aplicación privada](../private-app.php) (`mx-admin-phone`) y [adaptador JS](../assets/js/app.js).
- Servicio/writer: [DoctorContactPointsController](../modules/profiles/controllers/DoctorContactPointsController.php) → [DoctorContactPointsRepository](../modules/profiles/repositories/DoctorContactPointsRepository.php).
- Lectura pública: [PublicProfileController](../modules/profiles/controllers/PublicProfileController.php) → [PublicProfileRepository](../modules/profiles/repositories/PublicProfileRepository.php).
- Contrato y brecha: [ACT03: teléfonos FREE](identity/ACT03-governed-professional-sources.md#teléfonos-free-contratos-distintos). QA: [Act03FreeContactTest](../modules/identity/tests/Act03FreeContactTest.php).
- Límite: teléfono administrativo ≠ teléfono global de consulta ≠ OTP. Existen handlers `mx-dg-public-contact-list`/`mx-dg-contact-create-form`, pero faltan esos nodos en el shell; no crear otro writer ni publicar FREE.

### Identity y acreditación profesional — CURRENT / IMPLEMENTED_BUT_INACTIVE
- UI: [acceso](../public/identity/acceso.php), [solicitante](../public/identity/perfil-medico.php), [staff original](../internal/media-review/index.php) (`section=acreditacion`).
- Servicios: [PhysicianEvidenceService](../modules/identity/Accreditation/PhysicianEvidenceService.php), [ProfessionalAuthorityIssuer](../modules/identity/Accreditation/ProfessionalAuthorityIssuer.php).
- Fuentes/writers: [VerifiedPhysicianIdentityService](../modules/profiles/services/VerifiedPhysicianIdentityService.php), [VerifiedDoctorCredentialService](../modules/profiles/services/VerifiedDoctorCredentialService.php), [SourceProvisioningService](../modules/identity/ProfessionalSources/SourceProvisioningService.php); lectura [EvidenceAuthority](../modules/identity/Accreditation/EvidenceAuthority.php).
- Contratos: [C4-EV01](identity/C4-EV01-protected-evidence-foundation.md), [issuer C4](identity/C4-ADM02-IMP02-physician-authority-issuer.md), [UI original](identity/C4-UI01-original-physician-onboarding.md), [ACT03](identity/ACT03-governed-professional-sources.md). QA: [Act03IntegrationTest](../modules/identity/tests/Act03IntegrationTest.php).
- Límite: acreditación no crea owner ni firma clínica; fuentes gobernadas y C4 requieren activación real separada.

### Enrollment y reclamación pública — IMPLEMENTED_BUT_INACTIVE
- UI: [perfil público](../profiles/doctor.php), [intake original](../public/identity/perfil-medico.php) (`entry=join` para FORMAR PARTE; no hay otra página).
- Servicio/writer: [PublicEnrollmentService](../modules/identity/Accreditation/PublicEnrollmentService.php), [ReviewedEnrollmentService](../modules/identity/Accreditation/ReviewedEnrollmentService.php); lectura/autoridad [EnrollmentResolutionAuthority](../modules/identity/Accreditation/EnrollmentResolutionAuthority.php).
- Contratos: [PUB-ENR02](identity/PUB-ENR02-pending-enrollment.md), [PUB-ENR03](identity/PUB-ENR03-reviewed-resolution-handoff.md). QA: [browser PUB-ENR03](../scripts/pub-enr03-browser-qa.py).
- Límite: preservar origen CLAIM/FORMAR PARTE; una resolución no fabrica fuentes, membresía ni aprobación C4.
- Sucesor acotado: [WORKSPACE02](identity/ASE-ADVISOR-WORKSPACE02.md) integra resolución de solicitud en el workspace, draft nuevo ligado y continuación de ownership/C4 separada; principal 51 PASS, ownership 23 PASS y UI adicional 10 PASS, seguridad final 20 PASS; sin activación normal-local.

### Informes de corrección pública — IMPLEMENTED_BUT_INACTIVE
- UI: [perfil público](../profiles/doctor.php), [staff original](../internal/media-review/index.php) (`section=correcciones`).
- Intake/revisión: [ReportService](../modules/profiles/Corrections/ReportService.php), [ReportReviewService](../modules/profiles/Corrections/ReportReviewService.php); aplicación [ApplicationService](../modules/profiles/Corrections/ApplicationService.php).
- Writer/lectura: [PublicPhoneCorrectionAdapter](../modules/profiles/Corrections/PublicPhoneCorrectionAdapter.php) reutiliza contactos y DTO público; no crea otro maestro.
- Contratos: [PUB-COR01](profiles/PUB-COR01-secure-intake-human-review.md), [PUB-COR02](profiles/PUB-COR02-governed-typed-application.md). QA: [browser PUB-COR02](../scripts/pub-cor02-browser-qa.py).
- Límite: aceptar un informe no aplica su valor; sólo el teléfono global actualmente publicado es elegible para el adaptador entregado.

### Suscripciones y visibilidad comercial — CURRENT / APPROVED_DECISION
- UI/API: [panel original](../private-app.php), [API Subscriptions](../api/subscriptions/index.php).
- Escritura: [CreateSubscriptionWithAcceptanceService](../modules/subscriptions/services/CreateSubscriptionWithAcceptanceService.php), [ProfileSubscriptionRepository](../modules/subscriptions/repositories/ProfileSubscriptionRepository.php).
- Lectura: [CurrentSubscriptionReadModelService](../modules/subscriptions/services/CurrentSubscriptionReadModelService.php), [PublicCommercialPlanSource](../modules/subscriptions/services/PublicCommercialPlanSource.php).
- Contratos: [decisiones comerciales](MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md), [registro visual](MXMED_REGISTRO_CONTRATOS_VISUALES.md), [ACT02 comercial](identity/ACT02-integrated-public-workflows.md). QA: [capabilities](../modules/subscriptions/tests/ExistingCapabilityAuthorityServiceTest.php).
- Límite: override local de revisión no es suscripción real; preservar opt-in, entitlements y gates de cobro/mapping.

### Agenda y Consultorio / Google Maps — CURRENT
- UI: [shell](../private-app.php) (`p-consultorio`), [multisede y selector Maps](../assets/js/perfil/consultorio/multisede.js), [API Agenda](../api/agenda/index.php).
- Controller/writer: [ConsultoriosController](../modules/agenda/controllers/ConsultoriosController.php) → [ConsultoriosRepository](../modules/agenda/repositories/ConsultoriosRepository.php); geocoding [GoogleGeocodeController](../modules/agenda/controllers/GoogleGeocodeController.php).
- Lectura: el mismo controller publica consultorios y coordenadas; `mxPersistMapCoordsNow` conecta confirmación del marcador con persistencia.
- Contratos: [Agenda](../modules/agenda/README.md), [cierre Agenda](agenda/CIERRE_AGENDA_V1_ESTADO_FINAL.md); mapa de rutas [MAPEO_AGENDA](MAPEO_AGENDA_MXMED.md) (**HISTORICAL**, contrastar con fuente). QA: [directorio de pruebas Agenda](../modules/agenda/tests/).
- Límite: selector existente no acredita disponibilidad externa de Maps; no reiniciar servidores ni cancelar citas para probar documentación.

### Operadores — CURRENT / PENDING_AUTHORIZATION
- UI: [partial original](../partials/private-app/operators.php) (`p-ag-operadores`), [operators-panel.js](../assets/js/agenda/operators-panel.js).
- Servicio/writer: [ProfileOperatorService](../modules/identity/operators/ProfileOperatorService.php), [OperatorPresentationRepository](../modules/identity/operators/OperatorPresentationRepository.php); lectura histórica/readiness [OperatorPanelReadiness](../modules/identity/operators/OperatorPanelReadiness.php).
- Contratos: [OP-IMP01](identity/OP-IMP01_OPERATOR_FOUNDATION.md), [integración original OP-INT04](identity/OP-INT04_NORMAL_LOCAL_OPERATORS_INTEGRATION.md), [despliegue](identity/OP-ACT01_NORMAL_LOCAL_DEPLOYMENT_PACKAGE.md). QA: [fundación](../modules/identity/tests/OperatorFoundationTest.php), [presentación OP-IMP07](../scripts/op-imp07-contract-qa.py).
- Límite: owner-only, dos plazas y cinco grants Agenda separados. Alias/historia no prueban identidad; conservar registros legacy y QA05/OP02/UI03 sin resetearlos.

### Pacientes y expediente clínico — CURRENT
- UI/API: [shell](../private-app.php) (`p-expediente`), [API Patients](../api/patients/index.php), [API clínica](../api/clinical/index.php).
- Servicio/lectura: [SearchDoctorPatientsController](../modules/patients/controllers/SearchDoctorPatientsController.php), [CanonicalClinicalIdentityResolver](../modules/identity/http/CanonicalClinicalIdentityResolver.php).
- Writers: [PatientsRepository](../modules/patients/repositories/PatientsRepository.php), [PatientDetailsRepository](../modules/patients/repositories/PatientDetailsRepository.php); servicios clínicos específicos en [modules/clinical](../modules/clinical/).
- Contratos: [arquitectura clínica](ARQUITECTURA_CLINICA_MXMED.md), [fuentes de verdad](clinical/DECISION_FUENTES_DE_VERDAD.md), [contención CLIN-AUTH02A](clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md). QA: [autorización de lectura](../modules/patients/tests/PatientReadAuthorizationTest.php).
- Límite: paciente global no implica expediente accesible; conservar ámbito médico, encounter, versiones y autoridad del servidor.

### Recetas y documentos — CURRENT
- UI: [preview original](../private-app.php) (`ne_rx_review`, `ne_rx_preview_html`), [viewer](../modules/clinical/ui/viewer.php), [detalle](../modules/clinical/ui/document.php).
- Renderer/writer V2: [clinical_prescription_v2.php](../api/_lib/clinical_prescription_v2.php); lifecycle [clinical_prescription_lifecycle.php](../api/_lib/clinical_prescription_lifecycle.php); lectura [clinical_prescription_history.php](../api/_lib/clinical_prescription_history.php).
- Presentación: [clinical_professional_presentation.php](../api/_lib/clinical_professional_presentation.php), [stylesheet documental](../assets/css/clinical-doc-base.css).
- Contratos: [evaluación de presentación](clinical/UX-ALIGN01-prescription-presentation-assessment.md), [firma](product-completion/SIG03A-canonical-signature.md). QA: [contrato preview](../modules/clinical/qa/rec_preview01_contract.php), [footer](../modules/clinical/qa/doc_rec_std03a_footer_test.php).
- Límite: HTML/snapshots históricos y CSS compartido requieren compatibilidad; no regenerar, firmar ni emitir documentos reales como efecto de un cambio visual.

### Media — CURRENT
- UI/API: [perfil privado](../private-app.php), [staff](../internal/media-review/index.php), [API Media](../api/media/index.php).
- Servicios: [DoctorProfilePhotoService](../modules/media/services/DoctorProfilePhotoService.php), [GalleryApprovalService](../modules/media/services/GalleryApprovalService.php); writer/read model [MediaAssetsRepository](../modules/media/repositories/MediaAssetsRepository.php).
- Contratos: [candidatos privados](../modules/media/docs/private-review-candidates.md), [originales](product-completion/media-review-mr12a2-original-archive.md), [arquitectura Media](architecture/MXMED_MEDIA_STORAGE_IMAGE_OPTIMIZATION_AND_SEMANTIC_METADATA.md). QA: [PrivateMediaStorageTest](../modules/media/tests/PrivateMediaStorageTest.php).
- Límite: preservar original, revisión y publicación; storage privado y claves no son recursos públicos ni fixtures.

### Billing — CURRENT / PENDING_AUTHORIZATION
- UI/API: [Facturación original](../private-app.php), [perfiles fiscales](../api/billing/patient-profiles.php), [emisión](../api/billing/issuance.php).
- Servicios: [PatientBillingProfilesService](../modules/billing/services/PatientBillingProfilesService.php), [InvoiceDraftService](../modules/billing/services/InvoiceDraftService.php); writers/readers [perfiles](../modules/billing/repositories/PatientBillingProfilesRepository.php), [archivo](../modules/billing/repositories/InvoiceRepository.php).
- Contratos: [Billing](../modules/billing/README.md), [FISC02B](billing/FISC02B_ISSUANCE_CORE.md), [decisión PAC](billing/PAC_DEC01_PROVIDER_SELECTION_AND_INTEGRATION_STRATEGY.md). QA: [IssuanceCoreTest](../modules/billing/tests/IssuanceCoreTest.php).
- Límite: borrador no es CFDI timbrado. PAC-DEC01 eligió SW primario y Finkok alternativo; adaptar/configurar el proveedor y operar fiscalmente requiere autorización específica, no una nueva selección.

### Gobierno, seguridad y auditoría — CURRENT / APPROVED_DECISION

- **Workspace staff centrado en médico, integración R2:** [ASE-ADVISOR-WORKSPACE01-R2](identity/ASE-ADVISOR-WORKSPACE01-R2.md), contexto/pestañas superiores, búsqueda y pendientes por ámbito Media; matrices de nueve dominios y dieciséis capacidades, autoridad Director/Advisor separada. Resumen e Imágenes conectados; 57 checks, seis smoke suplementarios, 13 retorno-login y seis combinaciones Chromium/WebKit PASS; dos ventanas nativas entregadas, aceptación visual PENDING, sin producción.
- **Solicitudes de perfil e historial, WORKSPACE02:** [Contrato](identity/ASE-ADVISOR-WORKSPACE02.md), cuatro grants exactos, aplicación/perfil/solicitante separados y efecto owner mediante R3/aceptación canónica; principal/ownership/UI verificados y preview owned conservado; seguridad final 20 PASS, sin producción.
- **Inbox agrupado y resúmenes acotados, WORKSPACE03:** [Contrato](identity/ASE-ADVISOR-WORKSPACE03.md), una tarjeta por médico/aplicación, once pestañas y autoridad independiente para conteos dentro del ámbito existente; sin módulos nuevos ni activación.
- **Baseline Advisor funcional y ventana nativa aislada:** [ASE-ADM02-OPER09](identity/ASE-ADM02-OPER09.md): formulario completo en área real macOS, alta canónica, grant/revoke y consumer Media original con sesión independiente; no implementó el workspace que ahora entrega R2.

- **Consolidación funcional staff aislada:** [ASE-ADM02](identity/ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md), solicitudes, vínculo Identity, documentos agrupados, revisión Director con MFA y alta Advisor canónica; UI aprobada y grants individuales, sólo ensayo sintético.

- **Media staff privada aislada:** [ASE-ADM02-IMP03](identity/ASE-ADM02-IMP03-private-staff-media.md), retrato/evidencia separados, cipher staff, versiones privadas, auditoría y reconciliación SQL/archivos. Sólo QA sintética callable; sin HTTP, PDF, reviewer ni operación real.

- **Invitación/binding staff aislados:** [ASE-ADM02-IMP02](identity/ASE-ADM02-IMP02-staff-invitation-binding.md), destinatario protegido, Identity canónica, consumo/vínculo/audit atómicos y recuperación. Positivo sintético CLI; HTTP invitación cerrado por políticas productivas pendientes. UI-0, sin staff/activación.

- **Fundación aislada staff:** [ASE-ADM02-IMP01](identity/ASE-ADM02-IMP01-staff-case-foundation.md), caso/versiones/procedencia, API exacta y recibos/audit. Director prepara; cuenta ligada confirma, binding producto pendiente. 101 checks sintéticos; UI-0/default off, sin review/admisión aprobada/activación.

- **Política staff vigente, UI-0:** [ASE-ADM02-POL01](identity/ASE-ADM02-POL01-staff-admission-policy.md): siete principios aprobados por el Director; un caso asistido/remoto, diccionario, custodia, autoridad, legacy y handoff canónico. Detalles legales/retención/delegación e implementación pendientes; no intake real ni activación. Sucede sólo los principios ADM pendientes de contratos históricos.

- Preparación documental histórica: [ASE-INT03B-PREP01 — autorización privilegiada Master](identity/ASE-INT03B-PREP01-master-privileged-authorization.md), gaps MFA/casos, transacción, elegibilidad y ficha UI-2 pendiente. No implementa ni habilita comandos Master.
- Sucesor backend: [ASE-INT03B-IMP01 — fundación privilegiada Master](identity/ASE-INT03B-IMP01-master-security-foundation.md), seis operaciones Director-only, caso/MFA/audit atómicos, rehearsal acotado y reader real de identidad pendiente. Gate off; conexión UI sucedida por IMP02 aislado.
- Sucesor UI-2: [ASE-INT03B-IMP02 — gobierno Master individual](identity/ASE-INT03B-IMP02-master-ui-integration.md), confirmación/contraseña/TOTP, caso exacto, reconciliación audit y borradores protegidos. Appointment producto bloqueado; route D sin reparar, sin readiness de despliegue.

- Sucesor backend aislado: [ASE-INT02A — grants individuales Advisor](identity/ASE-INT02A-governed-advisor-grants.md), POST + CSRF canónico, target bajo locks y auditoría existente. En ese hito, UI sólo consulta; sin activación.
- Sucesor UI aislado: [ASE-INT02B — matriz y comandos individuales](identity/ASE-INT02B-team-permission-ui.md), disponibilidad canónica, borrador/confirmación, secuencia y reconciliación parcial; QA sintética Chromium/WebKit, gate apagado por defecto y sin activación.
- Sucesor lifecycle aislado: [ASE-INT03A — suspender/reactivar Advisor](identity/ASE-INT03A-advisor-lifecycle.md), writer/HTTP canónicos, estado bajo locks, confirmación y grants preservados; gate apagado por defecto.

- Integración aislada vigente: [ASE-INT01 R2 — Equipo y permisos, sólo consulta](identity/ASE-INT01-R2-internal-team-readonly.md), sección `equipo`, reader GET/HEAD, autoridad independiente de Media; sin admisión/mutaciones ni activación.

- UI/API: [workspace staff original](../internal/media-review/index.php), [servicios Identity](../modules/identity/services/).
- Writers: [InternalGovernanceService](../modules/identity/services/InternalGovernanceService.php), [StaffMfaService](../modules/identity/services/StaffMfaService.php), [PdoAuditEventRepository](../modules/platform/repositories/PdoAuditEventRepository.php); lectura [AuditIntegrityVerifier](../modules/platform/services/AuditIntegrityVerifier.php).
- Contratos: [GOV-SIM02](identity/GOV-SIM02-physician-ownership-session.md), [GOV03A](identity/IDENTITY-GOV03A-governance-runtime.md), [audit sink](product-completion/canonical-audit-sink-contract-v1.md), [protocolo UI/UX](MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md). QA: [gobierno](../modules/identity/tests/GovernanceGov03ATest.php).
- Límite: documentos previos pueden describir emisores luego implementados; no inferir staff, grants, MFA o firma desde roles/planes.

### Infraestructura y despliegue — PENDING_AUTHORIZATION

- [ACT03-CHECK-COMPAT01](identity/ACT03-CHECK-COMPAT01-historical-case-check.md): sucesor SQL previo al CHECK histórico incompatible, rutas por metadata/evidencia, preservación de correcciones y Master; ensayo desechable, sin activación.
- Entradas/tooling: [Identity normal-local](../scripts/normal-local-identity.py), [proxy HTTPS](../scripts/normal-local-https-proxy.py), [AWS](../infra/aws/README.md).
- Composición/lectura: [OperationalRuntime](../modules/identity/Operational/OperationalRuntime.php), [WorkflowFactory](../modules/identity/Operational/WorkflowFactory.php), [ActivationGuard](../modules/identity/Operational/ActivationGuard.php), [DeploymentPackage](../modules/identity/Operational/DeploymentPackage.php).
- Contratos: [composición local](product-completion/identity-normal-local-composition.md), [HTTPS](product-completion/identity-normal-local-ui-https.md), [ACT02](identity/ACT02-integrated-public-workflows.md), [ACT03](identity/ACT03-governed-professional-sources.md). QA: [Act02IntegrationTest](../modules/identity/tests/Act02IntegrationTest.php).
- Límite: tooling/migraciones no autorizan writes; no hay writer operacional nuevo aquí. Revalidar estado físico antes de cualquier operación aprobada; las notas de esquema incompleto ID-LOCAL03A/B son históricas.

## QA por impacto, sin ejecución automática

| Cambio | Selección inicial | Frontera de ejecución |
|---|---|---|
| Sólo documentación | Diff, rutas, anchors, tamaño, alcance y descubrimiento | No correr suites SQL/HTTP de producto. |
| Identity / sesión | Contrato de sesión privada y tests del servicio modificado | Identificar store/DB/credenciales; jamás reutilizar sesiones reales como fixture mutante. |
| C4 / enrollment / fuentes / correcciones | Tests del contrato y runners enlazados arriba; [runner desechable](../scripts/gov03b-disposable-qa.py) | Revisar suite, targets y limpieza; QA aislada no habilita normal-local. |
| Operadores / Agenda | Contrato de panel, cuota/grants/concurrencia o servicio Agenda afectado | No ejecutar reset de OP02/QA05/UI03 ni entregar invitaciones reales. |
| Clínico / documentos | Autoridad, ámbito y compatibilidad del renderer/lifecycle tocado | Datos sintéticos; preservar snapshots, firma y gates de contención. |
| Perfiles / Media / comercial / Billing | Tests del controller, writer y read model tocados | Revisar escrituras, storage, publicación y proveedores externos antes de correrlos. |

Los enlaces son candidatos de selección, no una lista obligatoria de ejecución. Registrar fallos históricos sin relajar assertions para declarar una suite verde.

## Historia, decisiones y continuidad

- **APPROVED_DECISION:** [registro visual](MXMED_REGISTRO_CONTRATOS_VISUALES.md), [decisiones de autoridad](MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md) y decisiones específicas del contrato afectado. Una aprobación aplica a su alcance, no a cualquier despliegue posterior.
- **HISTORICAL:** [README inicial](../README.md), [README_extra](../README_extra.md), [pendientes antiguos](../pendientes_integracion.txt), [servidores duales](dev/LOCAL_DEV_SERVERS.md), [mapa total](MAPA_TOTAL_SISTEMA_MXMED.md), [índice V2](MXMED_INDICE_FINAL_V2_ACTIVITIES_CHECKPOINTS_EVIDENCE.md). Consultar por problema concreto; no ejecutar sus instrucciones antiguas por defecto.
- [C4-ACT01](identity/C4-ACT01-operational-activation-package.md) y pasajes de [ACT02](identity/ACT02-integrated-public-workflows.md) son evidencia de su fase; [ACT03](identity/ACT03-governed-professional-sources.md) sucede la carencia del writer gobernado. No borrar ni reinterpretar el registro anterior.
- Reutilizar la [plantilla segura existente](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md). Cierre breve: baseline/post HEAD, decisión/alcance, comportamiento, pruebas/entorno, evidencia, límites y autorizaciones pendientes.
- **CTX-SYNC01:** cada cambio autorizado incluye la [revisión/sincronización obligatoria](../AGENTS.md#sincronización-obligatoria-de-contexto--ctx-sync01) y los [campos/gate de entrega](MXMED_PLANTILLA_ACTIVIDAD_SEGURA_BACKEND_API_UI.md#context-sync). Actualizar el estado ante cambios materiales y este router sólo si cambia el enrutamiento; conservar historia e inicio acotado. La condición documental anterior queda sucedida por esa regla; no existe actualizador automático.
- Para ChatGPT compartir commit/enlaces GitHub o estos archivos cuando haya acceso. Git ofrece referencias comunes; no memoria automática entre asistentes. La evidencia privada permanece fuera de Git.

- **Preparación y revisión staff separadas:** [ASE-ADM02-UXFLOW03](identity/ASE-ADM02-UXFLOW03.md), asistente aprobado, revisión contextual y edición personal.
- **Listado por estado y revisión de permisos ACTIVE:** [ASE-ADM02-UXFUNC02](identity/ASE-ADM02-UXFUNC02.md), sucesor acotado de UXFUNC01.
- **Corrección funcional de administración staff:** [ASE-ADM02-UXFUNC01](identity/ASE-ADM02-UXFUNC01.md), alta visible, permisos interactivos y baja definitiva de Advisor; autoridad Master independiente preservada.

- **Administración contextual del equipo:** [ASE-ADM02-PANEL04](identity/ASE-ADM02-PANEL04.md), sucesor de presentación UXFLOW03; logout, documentación e historial canónicos, con límites comerciales explícitos.

- **Interacción y recuperación del panel staff:** [ASE-ADM02-PANEL05](identity/ASE-ADM02-PANEL05.md), sucesor dirigido de PANEL04; seis correcciones y QA sintética focalizada.

## STAFF-CONTROL04 — incorporación, acceso manual e índice de interfaces

[Contrato, matriz de quince dominios y guía de revisión](identity/STAFF-CONTROL04.md). Consultar para catálogo estado/municipio, facultades preparadas frente a efectivas, simulación del solicitante, login Director y seis entradas de desarrollo. Sucesor acotado de WORKSPACE03; sus consumidores Requests/Media permanecen canónicos. Cuatro superficies se documentan indisponibles, sin impersonación ni aplicaciones nuevas. Operación sólo sintética owned.
