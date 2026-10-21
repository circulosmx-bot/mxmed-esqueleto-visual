# Inventario funcional existente — CTX-MAP02

Fuente `1b8dfccb68a7613b2182507a840454631dbf012a`, 2026-10-17. Lectura de fuente/contratos; sin pruebas de producto ni acciones externas. [Cobertura y límites](COBERTURA_Y_PENDIENTES_MXMED.md), [datos](DATOS_Y_MIGRACIONES_MXMED.md), [journeys](INTERACCIONES_MXMED.md).

**Suplemento dirigido CTX-MAP03:** fuente `0bea8195ce75c309a54cba35acb4605ba290ee00`, misma fecha, sin repetir censo ni leer filas. [Canales y actores Agenda](AGENDA_CANALES_Y_PERMISOS_MXMED.md) y [casos comerciales/asesores](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md) detallan las conexiones que una capacidad de módulo no prueba por sí sola. BILL-01..06 se amplía en el mapa financiero; los IDs existentes se conservan.

## Cómo recuperar una función

Cada ID agrupa operaciones con la misma autoridad; las rutas de autoridad distinta se separan. `UI` apunta a la pantalla original y handler; `ruta` es método/patrón de dispatcher, no garantía de autorización. Los writers y readers se nombran aunque compartan archivo. Para esquema por objeto usar los CSV del mapa de datos. Los endpoints fuera de un prefijo exacto conservan aliases/dynamic dispatch indicados; no ejecutar la ruta para navegar el mapa.

Los cinco ejes se expresan como `política / fuente / conexión / verificación / operación`. Perfiles comunes de estado:

- **S1:** `unknown / implemented / wired / source-inspected / external-state-not-verified`. El contrato se cita por sección; referencia técnica no equivale a política ratificada. Las decisiones aprobadas expresas se detallan aparte.
- **S2:** `approved / implemented / optional-composition / source-inspected / inactive`. Política limitada a la decisión aprobada citada; la evidencia QA histórica y autorizaciones pendientes se describen por separado.
- **S3:** `unknown / partial / unresolved / source-inspected / external-state-not-verified`.
- **S4:** `unknown / historical / disconnected / source-inspected / inactive`.

La ausencia de evidencia específica deja `VERIFICATION=source-inspected` aun cuando exista un test en disco. La tarea no reejecuta QA. Las evidencias de cierre se citan con su propio commit/entorno, nunca como pruebas del HEAD actual completo. [ACT04-RECON02](../ESTADO_ACTUAL_MXMED.md) publicó las tres correcciones de tests en el baseline de este mapa.

## Shell y experiencia compartida

### SYS-01 — Aplicación privada y contexto profesional
- **Actor/UI/handler:** cuenta Identity con perfil único elegible; [private-app.php](../../private-app.php), [private-entry](../../assets/js/core/private-entry.js), [private-session](../../assets/js/core/private-session.js).
- **Ruta/autoridad:** `GET /private-app.php`; [PrivateApplicationAccessResolver](../../modules/identity/http/PrivateApplicationAccessResolver.php) y [CanonicalClinicalIdentityResolver](../../modules/identity/http/CanonicalClinicalIdentityResolver.php), no doctor/role del browser.
- **Writer/read/datos:** no writer de shell; SessionService/Valkey y lectura SQL de cuenta/membresía/capacidad; header/sidebar usan contexto minimizado.
- **Resultado/gates:** shell original o redirect 302 a acceso; 403 si no hay contexto único, 503 si dependencia falla. **S1**; [contrato de autenticación](../product-completion/authentication-productization-contract-v1.md), [test privado](../../modules/identity/tests/PrivateSession02ContractTest.php). Brecha: el shell empaquetado AWS no incluye automáticamente esta fuente actual.

### SYS-02 — Navegación, borradores y protección de cambios pendientes
- **UI:** sidebar/Información/consulta en shell; [navigation](../../assets/js/core/navigation.js), [dirty-tracker](../../assets/js/core/dirty-tracker.js), [explicit-save-surface](../../assets/js/core/explicit-save-surface.js).
- **Ruta/writer/read:** componente compartido; guardar delega al writer de cada formulario, no existe writer general de navegación. Estado de formulario en memoria, baseline tras éxito, confirmación de salida.
- **Resultado/gates:** guardar/descartar/seguir editando; error conserva draft. **S1**; [protocolo UI](../MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md). No convertir dirty-state en guardado automático ni persistir autoridad en storage.

## Ecosistema público y perfil médico

### PUB-01 — Directorio, filtros y rutas SEO
- **UI/handler:** [profiles/listing.php](../../profiles/listing.php), filtros estado/ciudad/especialidad; [PublicDiscoveryController](../../modules/profiles/controllers/PublicDiscoveryController.php).
- **Ruta/read:** `GET /profiles/listing.php` y rama pública de [api/profiles](../../api/profiles/index.php); [PublicDiscoveryRepository](../../modules/profiles/repositories/PublicDiscoveryRepository.php) sobre perfil público, consultorios y `public_profile_seo_routes`.
- **Writer/datos/external:** no write por búsqueda; SEO route administrado por datos/contrato, no por browser. Imágenes/CDN son recursos de presentación.
- **Resultado/gates:** lista paginada/empty/error; normalización de filtros y sólo proyección elegible. **S1**; [endpoint público](../PERFIL_PUBLICO_MEDICO_ENDPOINT_PUBLICO_MXMED.md) conserva fase histórica; [QA discovery](../../modules/profiles/tests/). Slugs/reglas rewrite dinámicas se contrastan con dispatcher, no con título de rama.

### PUB-02 — Perfil público, identidad y secciones
- **UI:** [profiles/doctor.php](../../profiles/doctor.php), about/consultation/gallery; [public-profile-panel](../../assets/js/public-profile-panel.js), [about](../../assets/js/public-profile-about.js).
- **Ruta/service/read:** `GET /profiles/doctor.php?doctor_id=…`; [PublicProfileController](../../modules/profiles/controllers/PublicProfileController.php) → [PublicProfileRepository](../../modules/profiles/repositories/PublicProfileRepository.php), identidad/credenciales/profesional/consultorios/media aprobada.
- **Writer/datos:** lectura pública; writes PRF/VID/CRD/Media distintos. Nunca usa declaraciones de nombre/email como identidad acreditada.
- **Resultado/gates:** DTO y HTML filtrados, perfil no elegible/404/error. **S1**; [identidad canónica](../PERFIL_PUBLICO_MEDICO_IDENTIDAD_CANONICA_MXMED.md), [hero/contact test corregido](../../modules/profiles/tests/PublicProfileHeroConsultorioContactLayoutTest.php), ACT04 baseline. Maps externo tiene evidencia de fallo, no aceptación completa nueva.

### PUB-03 — Visibilidad de contacto, FREE y información de consulta
- **UI/read:** mismo perfil público; controller construye `public_visibility`, contacto y paneles de sede desde [capabilities](../../modules/profiles/services/PublicProfilePlanCapabilities.php) y [fuente comercial](../../modules/subscriptions/services/PublicCommercialPlanSource.php).
- **Ruta/writer:** GET público; sin writer propio. `doctor_contact_points` global y `consultorios.telefonos_json/whatsapp` se proyectan por vías separadas; no reinterpretar urgencias legacy.
- **Resultado/gates:** FREE puede almacenar privadamente y ocultar teléfono/WhatsApp públicamente; plan pagado no sustituye flags de publicación. **S1**; [prueba de visibilidad](../../modules/profiles/tests/PublicProfileVisualFidelityContactTest.php), [ACT03 FREE](../identity/ACT03-governed-professional-sources.md#teléfonos-free-contratos-distintos), [finanzas SUBS](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md).

### PUB-04 — Claim de un perfil existente
- **UI/ruta:** CTA del perfil → `GET /public/identity/perfil-medico.php?entry=claim&…`; [physician-enrollment.js](../../assets/js/identity/physician-enrollment.js), [PublicEnrollmentHttp](../../modules/identity/Accreditation/PublicEnrollmentHttp.php), [API](../../api/identity/physician-enrollment.php), POST por acción.
- **Autoridad/writer/read:** origen fijado en servidor, sesión/CSRF para intake; [PublicEnrollmentService](../../modules/identity/Accreditation/PublicEnrollmentService.php) conserva target/cohorte y cifrado, [ReviewedEnrollmentService](../../modules/identity/Accreditation/ReviewedEnrollmentService.php) revisor humano/MFA/caso.
- **Datos/resultado:** origin/intake/resolution/handoff; claim no cambia owner, cuenta ni perfil al hacer click. 401/403/409/503/expired conservan el origen. **S2**; [PUB-ENR02](../identity/PUB-ENR02-pending-enrollment.md), [PUB-ENR03](../identity/PUB-ENR03-reviewed-resolution-handoff.md), QA aislada registrada en sus cierres de octubre; [J01](INTERACCIONES_MXMED.md#j01-claim-existente).

### PUB-05 — FORMAR PARTE y resolución sin perfil nuevo automático
- **UI/ruta:** mismo entry original con `entry=join`; POST start/intake, login/registro con continuación; no segunda página ni login.
- **Writer/read:** Enrollment conserva `REQUEST_NEW_PROFILE`/target original null; matching [ProfessionalMatchCandidates](../../modules/identity/Accreditation/ProfessionalMatchCandidates.php) y resolución humana pueden continuar a un perfil existente elegible.
- **Datos/gates/resultado:** no-match no crea `profiles_doctors`, VID/CRD, owner ni suscripción; creación nueva pendiente de procedimiento explícito. **S2**, limitación parcial del camino nuevo; contrato PUB-ENR03 y [J02](INTERACCIONES_MXMED.md#j02-formar-parte).

### PUB-06 — Informe de corrección, moderación y aplicación tipada
- **UI:** [public-profile-correction](../../assets/js/public-profile-correction.js), [staff original](../../internal/media-review/index.php) `section=correcciones`, [review](../../assets/js/profile-correction-review.js)/[application](../../assets/js/profile-correction-application.js).
- **Ruta/service:** POST [intake público](../../api/profiles/public/correction-reports.php) y [acciones staff](../../api/profiles/internal/correction-review.php), [ReportHttp](../../modules/profiles/Corrections/ReportHttp.php); intake público y acciones staff son autoridades distintas.
- **Writer/read:** [ReportService](../../modules/profiles/Corrections/ReportService.php), [ReportReviewService](../../modules/profiles/Corrections/ReportReviewService.php), [ApplicationService](../../modules/profiles/Corrections/ApplicationService.php) → [PublicPhoneCorrectionAdapter](../../modules/profiles/Corrections/PublicPhoneCorrectionAdapter.php) → writer de contactos y DTO público.
- **Datos/resultado/gates:** reports/reviews/applications/journal cifrados; aceptado ≠ aplicado. Sólo teléfono global ya publicado elegible, no WhatsApp, sedes, FREE oculto, identidad, owner o clínica. **S2**; [PUB-COR01](../profiles/PUB-COR01-secure-intake-human-review.md), [PUB-COR02](../profiles/PUB-COR02-governed-typed-application.md), evidencia aislada 264 checks/35 grupos browser del cierre; [J03](INTERACCIONES_MXMED.md#j03-correcciones).

## Información y Consultorio de la aplicación original

### PRF-01 — Contactos administrativos privados
- **UI/handler:** Información personal → `mx-admin-email`, `mx-admin-phone`, `mx-admin-whatsapp`; `persistAdministrativeContact` en [app.js](../../assets/js/app.js), blur/Enter.
- **Ruta/autoridad:** GET/POST `/api/profiles/private/doctor/{doctor}/contact-points`, PATCH `/{id}`; alias con `index.php` en [router](../../api/profiles/index.php), contexto canónico de perfil.
- **Writer/read:** [DoctorContactPointsController](../../modules/profiles/controllers/DoctorContactPointsController.php) y [repository](../../modules/profiles/repositories/DoctorContactPointsRepository.php), `doctor_contact_points` scope `platform_admin`, flags públicos/seguridad false. POST y PATCH son dos requests, no commit único distribuido.
- **Resultado/gates:** confirmación sólo tras servidor, entrada inválida/no-success 403/errores. **S1**; ACT04 documenta 14 checks de módulo con red en memoria y pruebas DTO, no SQL live. No inventar editor global por handlers no montados; [retrieval](COBERTURA_Y_PENDIENTES_MXMED.md#preguntas-de-recuperación).

### PRF-02 — Nombre visible, tema y datos profesionales
- **UI:** `mx-public-identity-card`, datos generales y formación; [datos-generales](../../assets/js/perfil/datos-generales.js), [professional-information](../../assets/js/perfil/professional-information.js), [credenciales](../../assets/js/perfil/credenciales.js).
- **Ruta/service:** GET/PATCH `/api/profiles/private/doctor/{doctor}` y GET/PUT [professional-information.php](../../api/profiles/professional-information.php); [PrivateProfileController](../../modules/profiles/controllers/PrivateProfileController.php), [ProfessionalInformationService](../../modules/profiles/services/ProfessionalInformationService.php).
- **Writer/read/datos:** PrivateProfileRepository y structured professional items; VID/CRD quedan bajo sus writers gobernados. [PublicDisplayNamePolicy](../../modules/profiles/services/PublicDisplayNamePolicy.php), [theme catalog](../../modules/profiles/services/ProfileThemeCatalog.php) y DTO público limitan presentación.
- **Resultado/gates:** save explícito, dirty-state, permisos/CSRF, campos verificados readonly y solicitud de revisión. **S1**; [UX-ALIGN01](../identity/UX-ALIGN01-product-experience.md), [registro visual](../MXMED_REGISTRO_CONTRATOS_VISUALES.md). Formulario no da autoridad profesional nueva.

### PRF-03 — Sedes, teléfonos de consulta y urgencias
- **UI:** `p-consultorio`, `cons-tel1..3`, `cons-wa`, `cons-urg1/2`; [multisede.js](../../assets/js/perfil/consultorio/multisede.js), `collectPanePayload`/autosave.
- **Ruta/autoridad:** GET/PUT `/api/agenda/index.php/consultorios`; PUT `/consultorios/emergency-contacts` y publicación separada; gate actual PrivateApplicationAccessResolver + scope médico; [PrivateAgendaRoutePolicy](../../modules/agenda/security/PrivateAgendaRoutePolicy.php) prepara restricción owner/administrator, sin enforcement por rol acreditado en el dispatch/controller ordinario actual. No interpretarlo como nueva autorización de producto para collaborator.
- **Writer/read:** [ConsultoriosController](../../modules/agenda/controllers/ConsultoriosController.php) → [ConsultoriosRepository](../../modules/agenda/repositories/ConsultoriosRepository.php), `consultorios.telefonos_json/whatsapp`; [PhysicianEmergencyContactsService](../../modules/profiles/services/PhysicianEmergencyContactsService.php) → DCP emergency compartido, fingerprint/CAS.
- **Resultado/gates:** lectura/guardado de sede, 403 feedback, 409 stale, no publicación automática. Orden de pestañas y `document_publication_enabled` son contratos distintos; teléfonos regulares autorizados pueden alimentar documentos, no WhatsApp/urgencias. **S1**; [CONS_VIS01 contract](../../modules/agenda/tests/ConsVis01ContractTest.php), QA histórica local aislada; [datos](DATOS_Y_MIGRACIONES_MXMED.md).

### PRF-04 — Google Maps, dirección y confirmación de ubicación
- **UI/handler:** `cons-map-frame`, `confirmConsultorioMapLocationByIndex`, `mxPersistMapCoordsNow` en multisede; Google Maps arrastrable existente, fallback y referencia manual.
- **Ruta/service:** GET `/api/agenda/index.php/geocode/google-js-config`, POST `/geocode/google`, PUT `/consultorios`; [GoogleGeocodeController](../../modules/agenda/controllers/GoogleGeocodeController.php).
- **Writer/read:** mismo writer de sede; lat/lng, `geocode_source/updated_at`, dirección confirmada; public map snapshot y [helper](../../modules/agenda/helpers/consultorio_map.php).
- **Resultado/gates/external:** key/config/proveedor y permisos; sin configuración no se simula proveedor. Movimiento pendiente no se convierte en ubicación persistida sin confirmación. **S1**; ACT04 clasifica errores externos ACT03 de perfil público, no reproduce defecto privado. No reemplazar Maps; [EXT](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md).

### PRF-05 — Orden, branding, fotos y publicación documental
- **UI:** arrastre/teclado/touch de sedes y controles logo/foto/publicación; [consultorio-ordering](../../assets/js/perfil/consultorio/consultorio-ordering.js).
- **Ruta/writer/read:** PUT `/consultorios/reorder` según [dispatcher Agenda](../../api/agenda/index.php), `ConsultoriosRepository::reorderDocumentPresentation`; logo por [api/profiles](../../api/profiles/index.php). Presentación profesional [clinical_professional_presentation](../../api/_lib/clinical_professional_presentation.php) y DTO público.
- **Datos/resultado/gates:** orden CAS, principal único/orden completo, no borrar historia por mover pestañas; publicación explícita y media aprobada. **S1**; [DOC-REC fixture](../../modules/agenda/tests/DocRecStd03AIntegrationTest.php), [CONS_VIS01](../../modules/agenda/tests/ConsVis01ContractTest.php). Runtime de cada entorno no revalidado por el mapa.

## Identity, revisión profesional y gobierno

### ID-01 — Registro, verificación de correo y resend canónico
- **UI:** [crear-cuenta](../../public/identity/crear-cuenta.php), [verificar-correo](../../public/identity/verificar-correo.php), [identity-access.js](../../public/identity/identity-access.js).
- **Ruta/writer:** POST `/api/identity/index.php/registration-request`, POST `/email-verification`; [RegistrationService](../../modules/identity/services/RegistrationService.php), [EmailVerificationService](../../modules/identity/services/EmailVerificationService.php), token/credential/consent repositories.
- **Datos/read:** `auth_accounts`, credentials/consents/one_time_tokens y notificación; password policy 12–64; token de un uso/expiración y resend por servicio, no INSERT manual.
- **Resultado/gates:** pending→active sólo verificación válida; notificación fallida no se presenta como éxito. **S1**; [auth contract](../product-completion/authentication-productization-contract-v1.md), [Identity tests](../../modules/identity/tests/). Resend del servicio no acredita una ruta HTTP independiente inexistente.

### ID-02 — Login, recuperación y cierre de sesiones
- **UI/ruta:** [acceso](../../public/identity/acceso.php), [recuperación](../../public/identity/recuperar-acceso.php), [reset](../../public/identity/restablecer-acceso.php); POST `/login`, `/recovery-request`, `/password-reset`, `/logout` del gateway Identity.
- **Writer/read:** [CredentialAuthenticationService](../../modules/identity/services/CredentialAuthenticationService.php), [RecoveryService](../../modules/identity/services/RecoveryService.php), SessionService, rate-limit y audit adapters; SQL credenciales/tokens y Valkey sesiones.
- **Resultado/gates:** HMAC/CSRF/origin, cuenta/credential version, revocación tras reset; respuestas no enumerativas y fallos de store 503. **S1**; normal-local y preview/productive se seleccionan explícitamente, no credenciales QA implícitas.

### ID-03 — Lifecycle de sesión y autoridad por request
- **Ruta:** GET `/api/identity/index.php/csrf-token`, `/current-session`, `/sessions`; POST `/session-rotate`, `/session-revoke`; comprobar ramas concretas del [gateway](../../api/identity/index.php).
- **Servicio/datos:** [SessionService](../../modules/identity/services/SessionService.php), stores Valkey/Memory de composición, cookie `__Host-mxmed_session`, idle/absolute TTL, rotación y credential version; lector canónico clínico request-scoped.
- **Resultado/gates:** expirado/revocado/ambiguo/disabled falla cerrado; no autoridad persistida en PHP/browser. **S1**; [normal-local](../product-completion/identity-normal-local-composition.md), [sesión privada](../../modules/identity/tests/PrivateSession02ContractTest.php), evidencia ACT04/ACT03 con scope limitado.

### ID-04 — Evidencia profesional y provisioning VID01/CRD02
- **UI/ruta:** sección de fuentes de solicitante/staff original; [professional-sources.js](../../assets/js/identity/professional-sources.js), POST `source_*` en [onboarding API](../../api/identity/physician-onboarding.php).
- **Autoridad/writer/read:** [SourceProvisioningService](../../modules/identity/ProfessionalSources/SourceProvisioningService.php), exact capability `professional_source_provision`, reauth/MFA/caso/humano; writers VID/CRD originales y [SourceEvidenceReference](../../modules/identity/ProfessionalSources/SourceEvidenceReference.php).
- **Datos/transacción:** evidencia cifrada y journal; autorización durable previa, VID+CRD+auditoría+resultado en una transacción SQL compartida; ACK perdido consulta resultado, no doble provisioning.
- **Resultado/gates:** un revisor humano autorizado rutinario, no duplicar revisión por separar fuentes; conflicto/ambigüedad bloquea. **S2**; [ACT03](../identity/ACT03-governed-professional-sources.md), 127 checks/12 journeys aislados de su cierre; no activación normal-local.

### ID-05 — Acreditación C4 y binding cuenta-profesional
- **UI/ruta:** [perfil-medico.php](../../public/identity/perfil-medico.php), [staff](../../internal/media-review/index.php) acreditación; GET/POST por acción [PhysicianOnboardingHttp](../../modules/identity/Accreditation/PhysicianOnboardingHttp.php).
- **Writer/read:** [PhysicianEvidenceService](../../modules/identity/Accreditation/PhysicianEvidenceService.php), [ProfessionalIssuanceWorkflow](../../modules/identity/Accreditation/ProfessionalIssuanceWorkflow.php), [ProfessionalAuthorityIssuer](../../modules/identity/Accreditation/ProfessionalAuthorityIssuer.php), [ProfessionalAccountBindingProjection](../../modules/identity/repositories/ProfessionalAccountBindingProjection.php).
- **Datos/resultado:** documentos cifrados, phone OTP propio, evidencia de lectura humana, confirmación del solicitante y admisión/binding/versiones; scope cuenta/perfil/fuente. Admisión no asigna owner ni firma automáticamente.
- **Gates:** fuentes vigentes, verificación/consentimiento/OTP, grant staff y MFA; **S2**. [C4-EV01](../identity/C4-EV01-protected-evidence-foundation.md), [issuer](../identity/C4-ADM02-IMP02-physician-authority-issuer.md), QA aislada de octubre, tablas preparadas ausentes. El consumidor clínico sigue cerrado aunque exista el issuer fuente.

### ID-06 — Primera propiedad, roles y recuperación gobernada
- **UI/entry:** [API gobierno](../../api/identity/governance.php), [CLI ceremony](../../scripts/governance-ceremony.php), workspace staff existente; no editor de owner nuevo.
- **Writer/read:** [R3ProfileGovernanceService](../../modules/identity/services/R3ProfileGovernanceService.php), [PhysicianOwnershipPolicy](../../modules/identity/services/PhysicianOwnershipPolicy.php), [ProfileMembershipRoleChangeService](../../modules/identity/services/ProfileMembershipRoleChangeService.php), repositorio step-up/membership/projection.
- **Datos/gates:** caso/endorsement/proof/versiones y audit; owner único, un revisor rutinario bajo GOV-SIM02, recuperación/extraordinarios conservan quorum independiente. No promover por plan, nombre ni bootstrap implícito.
- **Resultado/estado:** elegibilidad/acreditación vigentes antes de membresía/owner; **S2**. [GOV-SIM02](../identity/GOV-SIM02-physician-ownership-session.md), [GOV03A](../identity/IDENTITY-GOV03A-governance-runtime.md), evidencia histórica/aislada, activación/personas reales pendiente.

### ID-07 — Staff interno, primer Director, MFA y auditoría de gobierno

- **Sucesor ASE-INT01 R2:** [Equipo interno sólo consulta](../identity/ASE-INT01-R2-internal-team-readonly.md), workspace original→GET/HEAD→Identity\Team\ReadService. Cuenta/staff/sesión actuales; Director Advisors/Masters, Master management+delegación exacta sólo Advisors; Advisor denegado. Datos personales/evidencia/comercial null; ningún command conectado. QA aislada, sin operación real; censo de capacidades no regenerado.

- **Entry/writer:** mismos API/CLI con [FirstDirectorBootstrapService](../../modules/identity/services/FirstDirectorBootstrapService.php), [InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php), [StaffMfaService](../../modules/identity/services/StaffMfaService.php), [capability catalog](../../modules/identity/services/InternalCapabilityCatalog.php).
- **Datos/read:** `internal_staff`, grants/delegaciones, `auth_staff_mfa_*`, casos, pruebas y auditoría. Staff interno no es operador de Agenda.
- **Resultado/gates:** bootstrap/recuperación requieren ceremonia y custodias; MFA no equivale a concesión de capability. **S2**; [bootstrap](../identity/IDENTITY-QA02E-internal-governance-bootstrap-preparation.md). No staff real provisionado por esta tarea ni inferido por tabla existente.
- **Readiness CTX-MAP03:** [COM-10 y workspace](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#workspace-com-10) distinguen servicio backend de administración de asesores, grants/delegaciones, proyecciones `profile_*` sin ruta y colas originales efectivas. Clase ADVISOR no es vendedor atribuido, cartera asignada ni acceso clínico.

## Agenda y Operadores

### AG-01 — Agenda privada, crear/reprogramar/cancelar y colisiones
- **UI:** `p-ag-admin`, [agenda-workspace](../../assets/js/agenda/agenda-workspace.js), handlers Agenda de app.js; [API](../../api/agenda/index.php) GET/POST `/appointments`, GET `/appointments/{id}`, PATCH `/appointments/{id}/reschedule` y POST sufijos `cancel|confirm|no-show` del dispatcher.
- **Autoridad/writer/read:** gate canónico actual [PrivateApplicationAccessResolver](../../modules/identity/http/PrivateApplicationAccessResolver.php) en gateway; [PrivateAgendaRoutePolicy](../../modules/agenda/security/PrivateAgendaRoutePolicy.php) es política preparada R0 y no prueba dispatch por acción vigente. [AppointmentWriteController](../../modules/agenda/controllers/AppointmentWriteController.php), [AppointmentWriteRepository](../../modules/agenda/repositories/AppointmentWriteRepository.php), [AppointmentsController](../../modules/agenda/controllers/AppointmentsController.php), collisions/events repos.
- **Datos/resultado:** `agenda_appointments/events`, doctor/consultorio/patient, command/version/idempotency; lock/colisión/version stale; estados cancel/no-show/reprogramación conservan historial.
- **Gates/evidencia:** **S1**, roles/capabilidades individuales; [Gate8D](../../modules/agenda/tests/Gate8DAppointmentLifecycleIdempotencyTest.php), [Agenda README](../../modules/agenda/README.md). No cancelar citas ni resetear fixture para mapear.
- **Precisión CTX-MAP03:** [AGENDA-MED01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-med01) separa autorización Identity del perfil y atributos de actor/canal aceptados por el writer ordinario; no certificar `created_by_*` como identidad servidor por existir sesión. El adapter del operador tiene un límite distinto.

### AG-02 — Horarios, ajustes y bloqueos de disponibilidad
- **UI/ruta:** Consultorio/`p-ag-ajustes`, GET/PUT `/schedule`, GET/PUT `/settings`; overrides por ramas de `/availability` del gateway.
- **Writer/read:** [ScheduleController](../../modules/agenda/controllers/ScheduleController.php), [ScheduleRepository](../../modules/agenda/repositories/ScheduleRepository.php), [AvailabilityBlocksController](../../modules/agenda/controllers/AvailabilityBlocksController.php), [OverrideRepository](../../modules/agenda/repositories/OverrideRepository.php), [AgendaSettingsRepository](../../modules/agenda/repositories/AgendaSettingsRepository.php).
- **Datos/resultado:** `consultorio_schedule`, overrides/settings; afecta disponibilidad/validación de writes, no reescribe citas existentes automáticamente. **S1**; política preparada owner/administrator en configuración y settings owner-only; consumidor actual valida scope médico sin preservar esos roles como gate por operación. Es brecha de conexión, no permiso nuevo; [Gate8C](../../modules/agenda/tests/Gate8CCanonicalScheduleAvailabilityTest.php), [mapa histórico](../MAPEO_AGENDA_MXMED.md) y fuente actual prevalecen para persistencia de bloqueos.

### AG-03 — Disponibilidad y siguiente cita pública
- **UI/read:** [next-available](../../assets/js/public-profile-next-available.js), [daily](../../assets/js/public-profile-daily.js), [PublicDailyAvailability](../../modules/agenda/services/PublicDailyAvailability.php), [AvailabilityController](../../modules/agenda/controllers/AvailabilityController.php), disponibilidad/repositorio.
- **Ruta/datos:** GET `/api/agenda/index.php/public/...` y `/availability`, fechas/sede/doctor; horarios, overrides y citas ocupantes. Sin writer por consultar slots.
- **Resultado/gates:** slots/empty/expired/provider-error; público sólo agenda/plan elegible, no datos clínicos ni otra sede por fallback. **S1**; [P1](../agenda/PUBLIC_AVAILABILITY_P1.md), [public daily QA](../../modules/agenda/tests/PublicDailyAvailabilityTest.php); [J04](INTERACCIONES_MXMED.md#j04-consultorio-y-reserva).

### AG-04 — Reserva pública, OTP, hold, confirmación y cancelación
- **UI:** CTA y formulario integrado de [perfil público](../../profiles/doctor.php), `submitInlineBooking` → reserve → `requestBookingOtp` → `verifyBookingOtp`; [traza AGENDA-PUB01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-pub01). [public-book.html](../../public-book.html), [public-agenda.html](../../public-agenda.html), [public-cancel.html](../../public-cancel.html), [agenda-wizard](../../assets/js/public/agenda-wizard.js) y [agenda-cancel](../../assets/js/public/agenda-cancel.js) son superficies alternativas; no sustituyen la evidencia de conexión del CTA integrado.
- **Ruta/service:** POST `/public/otp/{request|verify}` y `/public/appointments/{reserve|confirm|cancel|request|verify}` según [dispatcher](../../api/agenda/index.php); [PublicAppointmentsController](../../modules/agenda/controllers/PublicAppointmentsController.php), [PublicOtpController](../../modules/agenda/controllers/PublicOtpController.php), [OtpSender](../../modules/agenda/services/OtpSender.php).
- **Writer/read/datos:** public flow/OTP y AppointmentWriteRepository; expiración de hold, rate limit, OTP destino/propósito, evento de creación del writer; confirmación/cancelación públicas actualizan estado/flow directamente, sin afirmar toda la cadena de eventos privados ni transacción única cita+flow; compensación de reserva si falla flow; citas privadas consultan mismo modelo.
- **Resultado/gates:** OTP/hold no son cita confirmada; idempotencia, slot ocupado, expirado y fallo delivery explícitos. **S1**, operación externa no verificada; [P2](../agenda/PUBLIC_OTP_P2.md), [P3](../agenda/PUBLIC_BOOKING_P3.md), [P4](../agenda/PUBLIC_CANCEL_P4.md), [SES histórico](../aws/MXMED_EMAIL_OTP_PROVIDER_CLOSEOUT.md).

### AG-05 — Waitlist, flags, incidentes y reconciliación ambigua
- **UI/ruta:** Agenda original; GET/POST/PATCH ramas `/waitlist`, `/patients/{id}/flags`, `/patients/{id}/behavior`; [WaitlistController](../../modules/agenda/controllers/WaitlistController.php), [PatientFlagsController](../../modules/agenda/controllers/PatientFlagsController.php), [AmbiguousPatientReconciliationController](../../modules/agenda/controllers/AmbiguousPatientReconciliationController.php).
- **Writer/read:** WaitlistRepository, PatientFlagsWriteRepository/PatientIncidentsWriteRepository, [PatientBehaviorService](../../modules/agenda/services/PatientBehaviorService.php) y PatientsRepository; no otro maestro de pacientes.
- **Datos/resultado/gates:** espera/estado, asignación valida entrada/médico/sede/rango y usa repository directamente, sin paridad acreditada de lock/idempotencia/disponibilidad con el controller de crear cita; actualización de estado por ID sin mismo guard de recurso, fallback audit conserva última metadata y no toda la historia; resolver ambigüedad con autoridad/versión, no merge por parecido. **S1**; [Waitlist](../agenda/waitlist/PASO_5_Reglas_Operativas_y_Roles_v1.md), [reconciliación QA](../../modules/agenda/tests/AmbiguousPatientReconciliationTest.php). R0/cutover preparado puede seguir inactivo; no inferir su ejecución.
- **Actores CTX-MAP03:** [AGENDA-ESP01..05](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-esp01) distingue reglas históricas de operadora y grants canónicos actuales: waitlist no está en los cinco grants. Call Center conserva prerrequisitos y consumidor pendientes, separados de las dos plazas del médico.

### AG-06 — Cita, paciente y encounter; seguimientos clínicos en Agenda
- **UI:** seleccionar cita/seguir consulta; `agf01-followups` y [agf01-followups.js](../../assets/js/agenda/agf01-followups.js).
- **Ruta/writer/read:** [ClinicalEncounterBridge](../../modules/agenda/services/ClinicalEncounterBridge.php), consultas GET appointment + tareas longitudinales; transición de seguimiento usa [gateway clínico](../../api/clinical/index.php).
- **Datos/resultado:** cita conserva paciente; bridge encounter opcional sólo `completed`, `AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE=1` y gate M6, HTTP separado; no iniciar consulta al guardar paciente; tareas resultas/canceladas conservan audit. **S1**, con override `SOURCE=partial` según workflow; [decisión encounter](../clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md), [interacciones clínicas](INTERACCIONES_CLINICAS_MXMED.md). Identificar autoridad clínica antes de acciones de seguimiento, no heredar permiso de gestionar Agenda.

### AG-07 — Grupos médicos de consultorio
- **UI/ruta:** sugerencias/vinculación desde Consultorio; GET `/medical-groups/search`, POST `/medical-groups`, `/{id}/join|approve|reject|merge` en gateway.
- **Writer/read:** [MedicalGroupsController](../../modules/agenda/controllers/MedicalGroupsController.php), [MedicalGroupsRepository](../../modules/agenda/repositories/MedicalGroupsRepository.php), memberships/review log; tablas `medical_groups/memberships/review_log`.
- **Resultado/gates:** sugerencia ≠ claim aprobado ni identidad de organización; revisión/relación/merge autorizados preservan historia. **S1**, políticas de roles; los tipos provider se describen separadamente en ORG, no duplicar por nombre de grupo.

### OP-01 — Invitación y aceptación canónica, dos plazas
- **UI/ruta:** `p-ag-operadores`, [partial](../../partials/private-app/operators.php), [canonical-operators](../../assets/js/agenda/canonical-operators.js), [panel](../../assets/js/agenda/operators-panel.js); GET `/api/profile-operators/index.php/bootstrap|overview`, POST `/command|inspect-invitation`.
- **Autoridad/writer/read:** [ProfileOperatorService](../../modules/identity/operators/ProfileOperatorService.php), [OperatorHttp](../../modules/identity/operators/OperatorHttp.php), snapshot/overview; owner gestiona, destinatario exacto acepta/rechaza, no actor/role del body.
- **Datos/resultado:** invitations/grants/revisions/commands; reserva de plaza pending/active/suspended, owner fuera del cupo; lock perfil+command/idempotency+audit y delivery gobernado. **S2** normal-local; [OP-IMP01](../identity/OP-IMP01_OPERATOR_FOUNDATION.md), [OP-IMP02](../identity/OP-IMP02_ISOLATED_OPERATORS_RUNBOOK.md), [despliegue](../identity/OP-ACT01_NORMAL_LOCAL_DEPLOYMENT_PACKAGE.md), QA aislada sin reset persistente.

### OP-02 — Suspensión, revocación y cinco grants de Agenda
- **Ruta/autoridad:** POST `/command` gestiona grants/lifecycle; GET/POST/PATCH Agenda sólo mediante `executeAgenda` y revisión vigente, no una página/calendario paralelo.
- **Writer/read/datos:** mismo servicio, `auth_profile_operator_grant_revisions/grants`; capabilities `agenda.appointments.read`, `.create`, `.reschedule`, `.cancel`, `agenda.availability.read` según [catálogo exacto](../../modules/identity/services/ProfileOperatorCapabilityCatalog.php). Recuperar el catálogo antes de usar los nombres; no un permiso agregado `billing` o prescripción.
- **Resultado/gates:** sesión/cuenta/membresía/revisión/entitlement, suspended no acceso pero conserva plaza; revoked conserva historia/libera plaza. **S2**; no escritura clínica ni firma heredada, [integración original](../identity/OP-INT04_NORMAL_LOCAL_OPERATORS_INTEGRATION.md).
- **Subacciones CTX-MAP03:** [AGENDA-OP01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-op01) enumera rutas efectivamente admitidas; endpoints individuales de detalle/eventos, confirmación/no-show, waitlist, horarios/bloqueos y Billing no se heredan de `appointments.read` ni de un rol legacy. El detalle visual mínimo sí deriva de la lista autorizada; no es autorización del endpoint individual completo. Alias actual y snapshot del creador son registros distintos.

### OP-03 — Alias, asiento, creador y referencias históricas
- **UI/writer/read:** [operators-panel](../../assets/js/agenda/operators-panel.js), [OperatorPresentationRepository](../../modules/identity/operators/OperatorPresentationRepository.php), [OperatorPanelReadiness](../../modules/identity/operators/OperatorPanelReadiness.php).
- **Datos/ruta:** presentations canónicas y `agenda_appointment_creator_snapshots`; POST rename por command, GET overview/audit; lista legacy server/browser conservada separada cuando identidad no está probada.
- **Resultado/gates:** alias no cambia identidad, plaza ni creator histórico; snapshot de origen inmutable. **S1** para lectura legacy; **S2** para writer canónico opcional. [OP-IMP07 QA fuente](../../scripts/op-imp07-contract-qa.py); sólo historia local no acredita membresía. No borrar storage para eliminar duplicados.

## Media y operaciones de revisión

### MED-01 — Foto de perfil y galería
- **UI/API:** [profile-photo.js](../../assets/js/profile-photo.js), [fotos.js](../../assets/js/fotos.js), GET/POST/DELETE de [profile-photo.php](../../api/media/profile-photo.php) y [gallery.php](../../api/media/gallery.php), método exacto por endpoint.
- **Writer/read:** [DoctorProfilePhotoService](../../modules/media/services/DoctorProfilePhotoService.php), [DoctorGalleryService](../../modules/media/services/DoctorGalleryService.php), [MediaAssetsRepository](../../modules/media/repositories/MediaAssetsRepository.php), DTO público.
- **Datos/resultado/gates:** archivo validado/procesado y cupos. UI de revisión usa ProfilePhotoReviewCandidateService/GalleryReviewCandidateService y storage privado; endpoints directos DoctorProfilePhotoService/DoctorGalleryService aún escriben público READY mediante PublicMediaStoragePort. Son vías distintas; candidato no implica aprobado. **S1**; [Media arquitectura](../architecture/MXMED_MEDIA_STORAGE_IMAGE_OPTIMIZATION_AND_SEMANTIC_METADATA.md), [photo tests](../../modules/media/tests/DoctorProfilePhotoHttpTest.mjs). Proveedor/S3 no consultado.

### MED-02 — Logo, conservación de originales y mejora
- **UI:** media card/Consultorio, [staff original](../../internal/media-review/index.php); APIs [physician-logo-review-candidate](../../api/media/physician-logo-review-candidate.php), [approve-logo](../../api/internal/media-review/approve-logo.php), mejora/aceptación separadas.
- **Writer/read:** [PhysicianLogoApprovalService](../../modules/media/services/PhysicianLogoApprovalService.php), [LogoImprovementService](../../modules/media/services/LogoImprovementService.php), [HistoricalOriginalArchive](../../modules/media/services/HistoricalOriginalArchive.php), [PublicLogoMediaService](../../modules/media/services/PublicLogoMediaService.php).
- **Datos/resultado/gates:** original privado preservado, transformación/revisión/aprobación/version pública; no sustitución silenciosa ni inferir logo por URL arbitraria. **S1**; [original archive](../product-completion/media-review-mr12a2-original-archive.md), [logo tests](../../modules/media/tests/PhysicianLogoReviewTest.php).

### MED-03 — Inbox staff, lotes, moderación y reemplazo
- **UI/API:** [media-review-inbox.js](../../assets/js/media-review-inbox.js), [owner-media-review](../../assets/js/owner-media-review.js), [API interna](../../api/internal/media-review/), [batch submit](../../api/media/review-batch-submit.php).
- **Writer/read:** [MediaCandidateSubmissionService](../../modules/media/services/MediaCandidateSubmissionService.php), [MediaReviewBatchService](../../modules/media/services/MediaReviewBatchService.php), [MediaReplacementService](../../modules/media/services/MediaReplacementService.php), [MediaReviewInboxService](../../modules/media/services/MediaReviewInboxService.php).
- **Datos/resultado/gates:** batch/files/submissions/ready-events, hash/dedupe/lifecycle, dominio/rol/capability exacta y audit; aprobar/solicitar reemplazo no expone original públicamente. **S1**, runtime efectivo no auditado; [candidatos](../../modules/media/docs/private-review-candidates.md), [MR11 closeout](../../modules/media/docs/mr11-1-operational-closeout.md), [J09](INTERACCIONES_MXMED.md#j09-media).

## Organizaciones y proveedores de estudios

### ORG-01 — Organizaciones, claim y tipos de entidad
- **UI/entry:** [provider/index.php](../../provider/index.php), [provider.js](../../provider/provider.js), GET `/api/provider/index.php/me/organizations` y `/organizations/{id}`.
- **Autoridad/writer/read:** [ProviderManagementApi](../../modules/agenda/http/ProviderManagementApi.php), [HealthcareOrganizationManagementAuthorizationService](../../modules/agenda/services/HealthcareOrganizationManagementAuthorizationService.php), [DirectoryService](../../modules/agenda/services/HealthcareOrganizationDirectoryService.php), [ClaimService](../../modules/agenda/services/HealthcareOrganizationClaimService.php).
- **Datos/resultado/gates:** `medical_groups` y `healthcare_*` por organization, sesión/grant/entitlement y ownership separado del perfil médico; hospital/clinic/lab/gabinete son tipos encontrados en contratos/servicios, no portales completos equivalentes a médico.
- **Estado/evidencia:** **S1**, con override `SOURCE=partial`, schema provider no observado en `mxmed`; [tests Agenda/provider](../../modules/agenda/tests/), [provider QA](../../provider/qa/). Presencia del front no acredita onboarding operativo de toda entidad.

### ORG-02 — Sedes, maestro de servicios y cobertura/oferta
- **Ruta/UI:** GET/POST/PATCH recursos `/api/provider/index.php/organizations/{id}/master-services|locations` y `/organizations/{id}/locations/{location}/offerings`, con métodos y sufijos del [dispatcher](../../modules/agenda/http/ProviderManagementApi.php).
- **Writer/read:** [HealthcareOrganizationMasterService](../../modules/agenda/services/HealthcareOrganizationMasterService.php), [CoverageService](../../modules/agenda/services/HealthcareProviderCoverageService.php), [StudyProviderMatchingService](../../modules/agenda/services/HealthcareStudyProviderMatchingService.php), catálogo clínico canónico.
- **Datos/resultado/gates:** maestro/oferta/cobertura por branch y study_type, submissions idempotentes y CAS/estado; catálogo de estudio no es precio/servicio comercial ni resultado clínico. **S1**, con override `SOURCE=partial`, cambios requieren capability específica y entitlement, no acceso por nombre/tipo de entidad. [Mapa clínico STUDY](FUNCIONES_CLINICAS_MXMED.md).

### ORG-03 — Equipo e invitaciones provider
- **Ruta/autoridad:** GET `/me/invitations`, GET/POST `/organizations/{id}/invitations`, POST `/invitations/{id}/accept`, POST `/organizations/{id}/members/{member}/{suspend|revoke}` y `/organizations/{id}/invitations/{id}/revoke`; [ProviderManagementApi](../../modules/agenda/http/ProviderManagementApi.php).
- **Writer/read:** [HealthcareOrganizationTeamService](../../modules/agenda/services/HealthcareOrganizationTeamService.php), [InviteeResolutionService](../../modules/agenda/services/HealthcareOrganizationInviteeResolutionService.php), internal entitlement provider de Subscriptions.
- **Datos/resultado/gates:** `healthcare_*` team/invitation/authority, cuenta exacta y grant; resolución de invitado no debe enumerar personas ajenas. **S1**, con override `SOURCE=partial`, no reutilizar las dos plazas de Operadores de médico como política de organización ni crear perfiles/pagos desde browser. [Finanzas provider](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md).

## Platform, catálogos y superficies parciales

### PLT-01 — Auditoría canónica y cadena de integridad
- **Entry/servicio:** llamadas de dominio → [CanonicalAuditWriter](../../modules/platform/services/CanonicalAuditWriter.php), [PolicyRegistry](../../modules/platform/services/CanonicalAuditPolicyRegistry.php), [AuditIntegrityVerifier](../../modules/platform/services/AuditIntegrityVerifier.php).
- **Writer/read/datos:** adapters PDO normal/joined, `platform_audit_events/stream_heads`, routines lock/CAS, metadata cerrada, actor/caso/request/correlation y hashes; sin endpoint genérico para inventar eventos.
- **Resultado/gates:** atomicidad con writer cuando joined, fallo revierte, definer/body/signature/target validados. **S1**; [audit sink](../product-completion/canonical-audit-sink-contract-v1.md), [test corregido ACT04](../../modules/platform/tests/PhysicianCredentialAuditPolicyTest.php), 57 políticas vigentes de fuente y 52 prefijo preservado.

### PLT-02 — Autorización transversal, correlación y acceso excepcional
- **Servicio/read:** [AuthorizationBoundary](../../modules/platform/services/AuthorizationBoundary.php), [CanonicalSourceRoutePolicy](../../modules/platform/services/CanonicalSourceRoutePolicy.php), [ContextPropagator](../../modules/platform/services/ContextPropagator.php), [SupportAssistedAccessEvaluator](../../modules/platform/services/SupportAssistedAccessEvaluator.php), [PrivilegedAccessActivationGate](../../modules/platform/services/PrivilegedAccessActivationGate.php).
- **Ruta/writer/datos:** biblioteca consumida por composición; no nueva UI/admin API universal. RetentionPolicyRegistry/DispositionPlanner describen política/planes, no eliminación real autorizada por este mapa.
- **Resultado/gates:** fail-closed frente a acceso asistido/breakglass no activado, scopes y minimum capability; **S1** en biblioteca; **S2** en composición gobernada opcional. [Decisiones V2](../MXMED_DECISIONES_V2_CAPACIDADES_AUTORIDAD_PERMISOS.md), [audit activation](../product-completion/audit-mp01h-activation-readiness.md). Revalidar si un consumidor invoca la frontera; biblioteca presente no prueba cobertura de todas las rutas.

### CAT-01 — CP, colonias y catálogos de referencia
- **UI/ruta:** formularios dirección, GET `/api/catalog/index.php/cp/{cp}` y alias rewrite `/api/catalog/cp/{cp}`; [API catalog](../../api/catalog/index.php), `modules/catalog` contiene apoyo, no todos los catálogos.
- **Writer/read/datos:** consulta `catalog_cp_colonias`; [sepomex-local](../../sepomex-local.php) usa contrato legacy distinto. Herramientas de importación/encoding y datasets aprobados son mantenimiento manual, no auto-DDL.
- **Resultado/gates:** CP inválido/no encontrado/DB unavailable; **S1** para lector, **S4** para proxy legacy con red externa. [README datos](../../assets/data/README_SEPOMEX.md), [dependencias externas](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md). Catálogos clínicos/fiscales mantienen autoridad propia.

### AUX-01 — Resumen y porcentaje de completitud
- **UI/handler:** `p-resumen`, [dashboard.js](../../assets/js/core/dashboard.js), app.js nudge/CTA hacia perfil/Consultorio.
- **Datos/writer/read:** cálculos y valores fallback/hardcoded de presentación; no escritor canónico de perfil ni read model completo de completitud acreditado. **S3**; porcentaje visible no prueba grado real de integración/datos. Brecha concreta: consolidar fuente del resumen sólo con alcance futuro aprobado.

### AUX-02 — Opiniones y paquetes/promociones
- **UI:** `p-opiniones`, `p-paquetes` en shell original; handlers locales en app.js, cards/formularios y propuesta IA.
- **Ruta/writer/datos:** no módulo first-party `reviews`/`offers` ni writer server de esas superficies localizado entre las raíces inventariadas; no inferir API por mockups/documentos conceptuales. **S3**, persistencia/moderación/campañas completas `unresolved`.
- **Gates/resultado:** preservar copy/plan Professional según decisiones; una UI/botón o campo IA no acredita proveedor de IA, publicación ni envío. [Mapa histórico](../MAPA_TOTAL_SISTEMA_MXMED.md), [planes](../MXMED_DECISIONES_V2_CATALOGO_PRECIOS_MODALIDADES.md). No reconstruir hasta precisar operación faltante y buscar nuevamente sus consumidores.

### AUX-03 — Notificaciones de producto y delivery transaccional
- **UI:** `p-Notificaciones`, [messages.js](../../assets/js/messages.js), [subscription-messages.js](../../assets/js/subscription-messages.js).
- **Writer/read:** centro visual usa mensajes/dismiss/read en `localStorage`; no equivale a inbox SQL/queue canónica. Identity/Agenda/Operadores tienen adaptadores de delivery distintos, SES/jobs en AWS.
- **Resultado/gates:** marcar leído en browser no confirma entrega; **S3** para centro general y **S1** para adaptadores existentes o **S2** para composición gobernada, según su contrato. [EXT](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md), [AWS](AWS_Y_ENTORNOS_MXMED.md). No envío ni lectura de destinos reales en este mapa.

### AUX-04 — Verificaciones legacy y herramientas administrativas
- **Entry:** [verify-password.php](../../api/verify-password.php), [verify-sms.php](../../api/verify-sms.php), [tools](../../tools/), [scripts](../../scripts/), root SEPOMEX/geocode utilities.
- **Autoridad/datos/estado:** endpoints legacy retirados no son autenticación canónica ni gateway SMS; las herramientas de backup/import/encoding/deploy/QA pueden mutar y son **S4** o `authorization-pending`, no endpoints de producto para reusar sin contrato.
- **Resultado/gates:** 410/denegación y comandos explícitos; [inventario entrypoints](PUNTOS_ENTRADA_MXMED.csv) distingue CLI/QA y no ejecuta ninguno. `/tmp`, backups, docs/assets e index.bak se clasifican como evidencia/copia, no módulos a revivir.

### AUX-05 — Otros tipos de perfil y componentes compartidos
- **Fuente:** contratos de catálogo/ecosistema, organization types, profesional designation y [provider](../../provider/index.php); aseguradoras, farmacéuticas y ofertas de entidad aparecen como modelo/UX/futuro, no automáticamente como módulos completos.
- **Writer/read/estado:** reutilizar ORG para organización encontrada y DENT/STUDY para clínica real. `modules/_partials` es layout/embed compartido; `modules/catalog` pequeño no prueba catálogo maestro universal. **S3/S4** para tipos sin cadena UI→autoridad→writer localizada; estado operacional `external-state-not-verified`.
- **Brecha:** confirmar actor, contrato aprobado y cadena específica antes de un nuevo tipo; no copiar módulos por analogía. [Cobertura por raíz](COBERTURA_Y_PENDIENTES_MXMED.md#raíces-y-exclusiones).

## Dominios detallados sin duplicación

- [Funciones clínicas](FUNCIONES_CLINICAS_MXMED.md): IDs CLIN/DOC/STUDY/DENT, pacientes, encounter, longitudinal, órdenes/resultados, prescripción/documentos, firma/QR y restricciones.
- [Stripe y finanzas](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md): IDs SUBS/BILL, confirmación/activación, renovación parcial, CFDI/PAC seleccionado y fronteras.
- [AWS y entornos](AWS_Y_ENTORNOS_MXMED.md): IDs AWS, stacks/runtime/packaging/operaciones y evidencia histórica separada del remoto desconocido.
- [Datos por objeto](DATOS_Y_MIGRACIONES_MXMED.md), [inventario de fuentes](INVENTARIO_FUENTES_MXMED.csv) y [entrypoints](PUNTOS_ENTRADA_MXMED.csv) permiten buscar componentes no nombrados individualmente en esta guía. Cobertura de inventario no equivale a cobertura funcional ni a cada variante dinámica resuelta.

## Mantenimiento

Actualizar sólo IDs afectados por una operación posterior autorizada, con rutas/writer/datos/decisión/evidencia y cinco ejes separados. Conservar las decisiones y cierres originales; no convertir un bloqueo técnico en nueva política comercial ni cargar este mapa entero para una tarea pequeña.

### ID-07 — sucesor backend ASE-INT02A

[API Advisor individual](../identity/ASE-INT02A-governed-advisor-grants.md): `api/internal/team/capability.php` → `Team\MutationHttp` → `InternalGovernanceService::execute` con target ADVISOR/ACTIVE bajo locks. Bootstrap CSRF protegido en `api/internal/team/csrf.php`. P=grant/revoke acotado; S=writer canónico extendido de forma opcional; C=HTTP aislado, GUI sin handler; V=122 checks/siete carreras; O=sin activación. ID-02/03 sólo reuso canónico; sin nuevos IDs ni regeneración del censo.

## ID-07 — conexión UI ASE-INT02B

[Matriz individual](../identity/ASE-INT02B-team-permission-ui.md): P=grant/revoke acotado; S=proyección editable y coordinador UI; C=read/CSRF/POST canónicos reutilizados; V=QA real sintética con Chromium/WebKit, audit y concurrencia; O=gate default off, sin activación. Sucede GUI sin handler del hito ASE-INT02A; no modifica censo ni IDs.

## ID-07 — lifecycle conectado ASE-INT03A

[Contrato](../identity/ASE-INT03A-advisor-lifecycle.md): P=suspend/reactivate Advisor existente; S=mismo writer con scope estricto y HTTP compartido; C=botones aprobados/CSRF/POST/refresh; V=223 checks, seis carreras y ocho combinaciones de navegador; O=gate default off, sin activación. Censo/IDs preservados; Master/admisión fuera.

## ID-07 — preparación privilegiada ASE-INT03B-PREP01

[Contrato](../identity/ASE-INT03B-PREP01-master-privileged-authorization.md): P=autoridad exclusiva Director y separación de poderes aprobadas; S=comandos Master directos existentes, seguridad específica propuesta; C=HTTP/UI Master desconectados; V=inspección documental, sin QA nueva; O=inactivo. ID-02/03 se reutilizarían para reauth/sesión, sin otro motor. No modifica el censo ni concede capabilities.

## ID-07 — sucesor ASE-INT03B-IMP01

[Fundación Master](../identity/ASE-INT03B-IMP01-master-security-foundation.md): P=cuatro decisiones Director aprobadas; S=seis operaciones privilegiadas y elegibilidad tipada fail-closed; C=API preparada detrás de gate propio, UI sucedida por IMP02 aislado; V=QA sintética dirigida y rehearsal, límites en evidencia; O=inactivo. Reusa ID-02/ID-03 para credenciales y sesión; no amplía el censo ni ADM.

## ASE-INT03B-IMP02 — conexión UI aislada

[IMP02](../identity/ASE-INT03B-IMP02-master-ui-integration.md), ID-07/COM-10: P=UI-2 autorizada; S=proyección Master y controlador de caso, reutiliza ID-02/03; C=controles existentes conectados a ocho endpoints, appointment no disponible; V=812 checks sintéticos PASS, nueve carreras y Chromium/WebKit en cuatro viewports, ver evidencia del contrato; O=inactivo. No nuevas capabilities ni cambios de consumidores.

## ACT03-CHECK-COMPAT01 — sucesor de compatibilidad SQL

ID-04/05/06/07: [COMPAT01](../identity/ACT03-CHECK-COMPAT01-historical-case-check.md) corrige una dependencia de persistencia, sin capacidades, autoridad o UI nuevas. P=preservación histórica autorizada; S=sucesor SQL; C=manifiesto y preflight; V=ensayo SQL aislado; O=inactivo.

## ID-07 / COM-10 — política ASE-ADM02-POL01

[Contrato documental](../identity/ASE-ADM02-POL01-staff-admission-policy.md): P=siete principios staff aprobados, detalles pendientes; S=Identity/gobierno/port existentes sin cambios; C=admisión/perfil/evidencia/revisión/activación sin conectar; V=inspección y validación documental, sin QA producto; O=inactivo. Reutiliza ID-01/02/03, preserva frontera ID-04/05 y J08; no nuevas capabilities ejecutables, censos ni grants. Master review/admisión necesitan autoridad específica; management y acreditación médica no la conceden.

## ID-07 / COM-10 — ADM-IMP01 aislado

[Fundación](../identity/ASE-ADM02-IMP01-staff-case-foundation.md): P=alcance Director/preparación/confirmación exacta autorizado; S=caso/versiones/recibos y dos eventos audit; C=API acotada default off, binding producto pendiente; V=101 checks sintéticos y tres escenarios concurrentes; O=inactivo. Reutiliza ID-01/02/03, sin nuevo motor de permisos, perfiles médicos o catálogo ejecutable.

## ID-07 / COM-10 — ADM-IMP02 aislado

[Contrato y evidencia](../identity/ASE-ADM02-IMP02-staff-invitation-binding.md): P=Director autoriza destinatario y aceptación personal; S=invitación/recibo/binding al caso existente; C=servicios canónicos y endpoints preparados, composición HTTP indisponible por políticas productivas pendientes; V=QA sintética con Identity real y confirmación propia HTTP IMP01; O=inactivo. Reuso ID-01/02/03, sin catálogo/grants nuevos.

## ID-07 / COM-10 — media staff IMP03

[Contrato y evidencia](../identity/ASE-ADM02-IMP03-private-staff-media.md): P=propósitos privados separados, own-applicant; S=StaffMedia y dos tablas/eventos, reuso Identity/UnitOfWork/GD/puerto privado; C=callable, sin endpoints ni readers directorio/reviewer; V=QA sintética/carreras/audit; O=inactivo. Sin nuevas capabilities ni datos reales.

## Consolidación staff aislada

ID-07 / COM-10: flujo sintético integrado de admisión, revisión y alta Advisor; reusa ID-01/02/03, sin permisos implícitos. UI del equipo existente conectada sólo en ensayo propietario. Véase [contrato funcional](../identity/ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md).

## ID-07 / COM-10 — UXFUNC01

[Corrección dirigida](../identity/ASE-ADM02-UXFUNC01.md): UI-2 autorizada; baja definitiva del equipo confirmada por Director. Writer canónico y estado terminal REMOVED, grupos editables según autoridad, alta visible y nombres sólo de fuente personal autorizada. Exclusivamente sintético; ninguna capacidad/rol ni activación productiva nueva.

## ID-07 / COM-10 — UXFUNC02

P=corrección solicitada; S=filtro SQL de estado y navegación de solicitudes; C=UI existente con escritor canónico intacto; V=27 checks integrados y 11 smoke checks PASS; O=sintético aislado. Véase [UXFUNC02](../identity/ASE-ADM02-UXFUNC02.md).

## ID-07 / COM-10 — UXFLOW03

P=separación explícita aprobada; S=asistente y lectores/writers existentes; C=caso/invitación, revisión contextual con step-up, edición personal versionada; V=39 checks funcionales y 11 de presentación PASS; O=aislado sintético. Véase [UXFLOW03](../identity/ASE-ADM02-UXFLOW03.md).

## ID-07 / COM-10 — PANEL04

P=reorganización explícita del Director; S=componentes/writers existentes y proyección audit minimizada; C=logout, documento por manifest, historial según target/delegación; V=ver [PANEL04](../identity/ASE-ADM02-PANEL04.md); O=ensayo aislado, sin activación. COM financiero no integrado: falta atribución canónica y clasificación vendedor, no se infiere de la categoría ADVISOR.

## ID-07 / COM-10 — PANEL05

P=seis correcciones explícitas; S=modal/filtros/búsqueda y recibos canónicos; C=consulta de operación/caso/invitación antes de reintento explícito, sin writer nuevo; V=ver [PANEL05](../identity/ASE-ADM02-PANEL05.md); O=preview sintético loopback. ACTIVE editable sólo según autoridad real; SUSPENDED/REMOVED protegidos.

## ID-02 / ID-03 / ID-07 / COM-10 — PANEL06

P=corrección explícita de diálogos/perfil/login, sin ampliar roles; S=perfil versionado con género/contacto no verificado/avatar, retrato StaffMedia privado Director con relación completada y propósito separado, cierre consistente; C=writers/recibos existentes y login/logout Identity originales conectados al router aislado; V=ver [PANEL06](../identity/ASE-ADM02-PANEL06.md); O=ensayo propio loopback, sin migración/activación real. Diez capacidades ya conectadas conservan edición/enforcement; dos de correcciones excluyen Advisor y cuatro de perfiles carecen de consumidor. No se elimina ninguna de estas restricciones.

## ID-02 / ID-03 / ID-07 / COM-10 — PANEL07

[PANEL07](../identity/ASE-ADM02-PANEL07.md): P=correcciones explícitas de cinco hallazgos, sin ampliar autoridades; S=renovación CSRF antes de acción sin replay, 401 diferenciado, errores en diálogo y menú compacto con popover; C=writers/lectores existentes, evidencia con MFA y login Identity con guía sólo en ensayo; V=134 checks funcionales, 42 de zoom nativo, 18 de recuperación y 58 de layout final dirigidos; O=loopback sintético, aceptación visual pendiente. Los consumidores de correcciones rechazan Advisor aun con grant y su contrato MFA lo excluye; las cuatro claves profile_* siguen sin consumidor. Sin modificación de owner/colaborador/publicación, schema o roles.

## ID-02 / ID-03 / ID-07 / COM-10 — PANEL08

[PANEL08](../identity/ASE-ADM02-PANEL08.md): P=corrección UI-2 y retrato preparatorio case-scoped autorizados; S=contenedor modal con cuerpo flex desplazable, imagen opcional en Persona y misma escritura versionada/cifrada; C=caso → imagen → presentación → invitación, binding/confirmación/admisión separados; V=111 checks funcionales/seguridad +18 zoom nativo PASS y38 recorridos modales; O=sólo loopback sintético. Correcciones siguen restringidas a Director/Master; profile_* no tiene consumidor staff. No nuevos IDs ni autoridad owner.

## ID-01 / ID-02 / ID-03 / ID-07 / MED-03 / COM-10 — OPER09

[OPER09](../identity/ASE-ADM02-OPER09.md): P=corrección puntual y primer Advisor operativo autorizados, sin nuevo workspace; S=lanzador Chromium con viewport nativo, denegaciones Team tipadas; C=consumer Media original conectado sólo en composición propia, Identity/grants revalidados por request; V=alta canónica por UI/servicios, lectura REVIEW permitida, SOURCE/revoke/suspend/logout denegados y modales completos en ventana física; O=sólo loopback sintético, aceptación visual Director PENDING. Inventario individual de dieciséis permisos en el contrato; seis límites PANEL08 conservados y positivos no ejecutados marcados NOT_TESTED. No inferir disponibilidad por checkbox. Edición posterior a admisión conserva historia pero invalida revisión anterior; el contrato existente bloquea nuevos envíos tras completar admisión, sin ampliación en OPER09.

## ID-02 / ID-03 / ID-07 / MED-01 / MED-02 / MED-03 / COM-10 — WORKSPACE01-R2

[Contrato y matrices](../identity/ASE-ADVISOR-WORKSPACE01-R2.md): P=composición autorizada médico → pestañas superiores → panel, reemplaza navegación lateral; modal OPER09 aceptado para esta etapa salvo regresión. S=agregación mínima de trabajo Media, contexto por ID y capacidades vigentes; C=Resumen/Imágenes y consumidores Media existentes, sin nuevo writer de dominio ni permisos por pestaña; V=57 comprobaciones nuevas PASS, seis smoke suplementarios, 13 retorno-login, seis combinaciones Chromium/WebKit y ventana nativa, sin sumar QA histórica; O=ensayo propio sintético y dos ventanas entregadas, sin activación productiva. Los nueve dominios propuestos no son nueve funciones operativas: perfil/consultorios/servicios/plan no adquieren un adapter staff por tener readers propietarios; C4 conserva caso/MFA/cohorte propios; opiniones/SEO no tienen editor staff habilitado. Seis límites PANEL08 preservados. Cuenta solicitada ausente en DB owned inspeccionada, sin clonación/lectura de credenciales ni nuevo selector de roles; login Identity sintético separado para Director/Advisor.

MED-01/MED-02 incluyen los consumidores existentes de foto/galería y logo/mejora, sus derivados y `media_assets`; no se crea un mecanismo alterno de variantes. ID-07 recibe una corrección mínima de lectura en `ActivityProjection` para eventos Media cuyo `operation` es nulo: evita avisos PHP 8.5 en el JSON, conserva `action` como descripción y no altera writes/audit. QA repetida sólo del segmento afectado PASS, con admisión previa preservada.


## PUB-04 / PUB-05 / ID-02..07 / MED-01..03 / COM-10 / PLT-01 — WORKSPACE02

[Contrato](../identity/ASE-ADVISOR-WORKSPACE02.md): P=Director prioriza solicitud CLAIM/nueva dentro del workspace, materialización mínima y delegación exacta; no edición general de los dominios restantes. S=RequestService sobre enrollment/evidencia existentes, cuatro grants individuales, ledger canónico, writer de draft y adapter R3 por solicitud; C=Solicitudes/Historial, selector compacto y continuación propia en la ruta Identity existente. V=principal 51 PASS (37 navegador incluidos), ownership 23 PASS con propietarios solicitantes reales y UI adicional 10 PASS; seguridad final 20 PASS, sin heredar QA R2. O=preview owned conservado, producción inactiva y aceptación visual pendiente.

PUB-05 sucede la ausencia de materialización sólo para esta aplicación nueva aprobada y ligada: hidden/FREE/no candidato público, sin segunda registry. PUB-04 conserva perfil al aceptar/rechazar. ID-04/05/06 mantienen fuentes/C4/vínculo/owner separados: solicitante prepara membresía legítima y acepta con contraseña; el Advisor no obtiene plaza médica ni autoridad general. ID-07/COM-10 añaden grants request_read/evidence_read/claim_resolve/new_profile_resolve al gobierno existente. MED-01..03 conservan consumidores/auditoría y se proyectan en el mismo historial autorizado; no reciben nueva autoridad.

## Sucesor WORKSPACE03 — COM-10, ID-04..07, PUB-04..05, MED-01..03

[WORKSPACE03](../identity/ASE-ADVISOR-WORKSPACE03.md) agrega un reader transaccional de pendientes por contexto exacto y el grant independiente physician_pending_summary_read. UI de once dominios, cuatro paneles existentes; los demás sólo informativos. Resumen autorizado no concede documentos, decisiones ni ámbito de cartera. Fuentes anteriores y writers conservados; ensayo owned con 53 checks principales y 10 complementarios PASS.

## STAFF-CONTROL04 — ID-01/02/03/07, MED-01..03 y COM-10

[Contrato](../identity/STAFF-CONTROL04.md). PRODUCT_POLICY: intenciones pendientes no conceden acceso; revisión/admisión/activación separadas. SOURCE_IMPLEMENTATION: adapter geográfico sobre `catalog_cp_colonias`, ledger versionado de intenciones y recibos del writer canónico, simulación por Identity/applicant HTTP; retorno de login sintético corregido. CONNECTIVITY: quince dominios discoverables, Requests/Media/Resumen/Historial operativos según grant; otros consumidores no conectados. Índice de seis rutas conserva identidades independientes. VERIFICATION: ensayos dirigidos enlazados en contrato. OPERATIONAL_STATUS: owned sintético, sin nuevas autoridades clínicas/comerciales ni activación normal-local. OP-01..03 y ORG-01..03 sólo mapeados, no implementados ni activados por esta entrega.

## STAFF-CONTROL05 — corrección contextual aislada

[Contrato e inventario](../identity/STAFF-CONTROL05.md). ID-01/02/03/07, MED-01..03 y COM-10 conservan fuentes y permisos. La preparación permite sólo once claves Media/Requests ya conectadas; los otros once dominios son integración pendiente para Asesor. Historial cubre Requests/Media, no audit universal. Política/fuente/conexión/QA/operación separadas en el contrato; cero activación real.

## STAFF-CONTROL06 — ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-CONTROL06.md). P=corrección dirigida autorizada; S=selectores y panel existentes, referencia de cuenta propia desde sesión canónica; C=readers de admisión/intenciones y autenticación/MFA sin writers nuevos; V=QA dirigida documentada en contrato; O=owned sintético, sin activación. No nuevas capacidades ni consumidores Advisor. Selección de solicitante no concede relación, permisos ni plaza médica.

## STAFF-CONTROL06-R2 — mismos ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-CONTROL06-R2.md). P=feedback explícito del Director; S=misma pantalla con cabecera compacta, lista Integrantes y alta superior; C=mismos handlers/readers/writers; V=ensayo dirigido en contrato; O=sintético owned. Ayuda de contraseña y MFA no concede autoridad ni muestra secretos.

## STAFF-CONTROL07 — ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-CONTROL07.md). P=corrección dirigida autorizada; S=paneles estables y confirmación por operación; C=sesión/readers/writers/MFA/audit existentes; V=ensayo dirigido documentado; O=owned sintético. No reutilización de pruebas consumidas ni nuevas facultades. Master conserva caso/gate/autoridad propios.

## STAFF-DOC-VIS01 — ID-02/03/07

[Contrato](../identity/STAFF-DOC-VIS01.md). P=visor privado autorizado; S=contexto/tipo/lados/versiones, ampliación y limpieza; C=READ/manifest/recibo canónicos existentes; V=QA dirigida en contrato; O=owned sintético. No nueva capacidad ni permiso para Advisor/Master.

## STAFF-DOC-VIS02 — ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-DOC-VIS02.md). P=examen separado de aprobación; S=ACK versionado y auditable, invitaciones legibles y corrección contextual; C=mismo UnitOfWork/Identity/MFA, nueva acción específica sin proof reutilizable; V=verificación dirigida en contrato; O=owned sintético, sin activación normal-local. Advisor/Master sin nueva facultad.

## STAFF-DOC-LIFECYCLE03 — ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-DOC-LIFECYCLE03.md). P=consulta persistente y actualización controlada autorizadas; S=READ exacto independiente de currentSet, submit post-admisión; C=Identity/MFA/StaffMedia/writers originales; V=verificación dirigida en contrato; O=sólo owned sintético. No nueva facultad para Master/Advisor.

## STAFF-AUTH-UX08 — ID-02/03/07 y COM-10

[Contrato](../identity/STAFF-AUTH-UX08.md). P=decisión expresa de sesión Director para acciones staff acotadas; S=authorize y consumo ligados a sesión/caso/set/snapshot; C=Identity, CSRF, readers privados y writers canónicos; V=QA dirigida en contrato; O=owned sintético, sin activación real. Sucede la reautenticación por acción de los hitos anteriores sólo en este alcance.

## STAFF-PANEL-UX09 — presentación aislada

[Contrato](../identity/STAFF-PANEL-UX09.md). ID-02/03/07 y COM-10: P=refinamiento expresamente solicitado; S=presenter/template/CSS existentes; C=mismos endpoints y sesiones; V=QA dirigida en contrato; O=owned sintético, sin producción. Sin capacidades nuevas.
