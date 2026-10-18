# Interacciones del sistema existente — CTX-MAP02

Baseline fuente `1b8dfccb68a7613b2182507a840454631dbf012a`, 2026-10-17. [Funciones](INVENTARIO_FUNCIONAL_MXMED.md), [clínica](FUNCIONES_CLINICAS_MXMED.md), [datos](DATOS_Y_MIGRACIONES_MXMED.md), [finanzas](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md), [entornos](AWS_Y_ENTORNOS_MXMED.md), [cobertura](COBERTURA_Y_PENDIENTES_MXMED.md).

Ampliación CTX-MAP03: revisión dirigida sobre `0bea8195ce75c309a54cba35acb4605ba290ee00`, misma fecha, sin ejecución funcional. [Canales Agenda](AGENDA_CANALES_Y_PERMISOS_MXMED.md) y [casos comerciales/asesores](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md) refinan actores y límites de estos journeys sin rehacer el censo CTX-MAP02.

Estos once recorridos reconstruyen conexiones de fuente y evidencia previa. No son once pruebas ejecutadas ni once procesos activos. Cada paso conserva su autoridad: un click, una aceptación humana, un webhook y un commit SQL son hechos distintos. Los cinco ejes de estado se consultan en las capacidades enlazadas; donde falta composición/permiso/proveedor se detiene la operación, sin concluir que falte todo el módulo.

<a id="j01-claim-existente"></a>

## J01 — Claim existente

**Acción y entrada.** CTA del [perfil público](../../profiles/doctor.php) → [physician-enrollment.js](../../assets/js/identity/physician-enrollment.js) → [perfil-medico.php](../../public/identity/perfil-medico.php) `entry=claim` → [API enrollment](../../api/identity/physician-enrollment.php) / [PublicEnrollmentHttp](../../modules/identity/Accreditation/PublicEnrollmentHttp.php). Registro/login normal retoma el origen persistido, no usa el target arbitrario del browser como autoridad.

**Autoridad y persistencia.** [PublicEnrollmentService](../../modules/identity/Accreditation/PublicEnrollmentService.php) fija `CLAIM_EXISTING_PROFILE`, médico objetivo, cuenta y cohorte; guarda origen/intake cifrado con auditoría. Sesión/CSRF, versión y elegibilidad se revalidan. [ReviewedEnrollmentService](../../modules/identity/Accreditation/ReviewedEnrollmentService.php) presenta matching/facts al revisor humano autorizado; begin/MFA/decide separan desafío y decisión. La resolución y su audit se escriben dentro de la transacción gobernada, con versión y command digest para rechazo/replay.

**Handoff y límite.** [EnrollmentResolutionAuthority](../../modules/identity/Accreditation/EnrollmentResolutionAuthority.php) revalida la decisión vigente → handoff de evidencia C4 → [PhysicianEvidenceService](../../modules/identity/Accreditation/PhysicianEvidenceService.php) → [ProfessionalAuthorityIssuer](../../modules/identity/Accreditation/ProfessionalAuthorityIssuer.php). VID/CRD pueden provenir del [provisioning ACT03](../../modules/identity/ProfessionalSources/SourceProvisioningService.php); no se duplican la revisión humana ni el archivo cifrado por existir servicios separados. Admisión/binding profesional no otorga ownership: éste pertenece a [R3ProfileGovernanceService](../../modules/identity/services/R3ProfileGovernanceService.php). Tampoco habilita automáticamente firma clínica.

**Lectura/recuperación/estado.** Snapshot del solicitante y cola staff originales muestran resolución/solicitud vigente; cambios de fuentes, versión, cuenta o prueba invalidan la continuación. Las etapas son síncronas SQL con pausas humanas/MFA, no job que autoaprueba. Se recupera estado persistido, sin recrear cuenta/perfil. Fuente implementada, composición normal-local inactiva; evidencia aislada de [PUB-ENR03](../identity/PUB-ENR03-reviewed-resolution-handoff.md) y [ACT03](../identity/ACT03-governed-professional-sources.md), no nueva aceptación física. [PUB-04 e ID-04/05/06](INVENTARIO_FUNCIONAL_MXMED.md).

<a id="j02-formar-parte"></a>

## J02 — FORMAR PARTE

**Cadena.** CTA FORMAR PARTE → el mismo intake original con `entry=join` → API enrollment → `PublicEnrollmentService` conserva `REQUEST_NEW_PROFILE` y target original null → [ProfessionalMatchCandidates](../../modules/identity/Accreditation/ProfessionalMatchCandidates.php) → resolución humana `ReviewedEnrollmentService` → continuación a perfil existente elegible, cuando la decisión y las fuentes lo permiten → handoff C4 de J01.

**Autoridad/writer/read.** Sesión, anti-CSRF, cohorte, exact review capability, reauth/MFA/caso y versión de facts; el escritor es enrollment, no el formulario ni matching. Origina filas de origen/intake/resolución/handoff, cifrado y auditoría, con transacciones y replay de decisión como J01. El solicitante lee un estado minimizado; staff consulta coincidencias autorizadas. No se infiere Identity de nombres/licencias declaradas/email.

**Sin coincidencia.** La solicitud permanece en procedimiento pendiente; no hay creación automática acreditada de un perfil, VID/CRD, owner, cuenta adicional, suscripción ni publicación FREE. Resolver a existente no borra la procedencia JOIN ni transforma retroactivamente la solicitud en CLAIM. Recuperación mediante versión/resolución persistida; rechazo/needs-information y expiración no son éxito. [PUB-05](INVENTARIO_FUNCIONAL_MXMED.md#pub-05--formar-parte-y-resolución-sin-perfil-nuevo-automático), [PUB-ENR03](../identity/PUB-ENR03-reviewed-resolution-handoff.md). El siguiente procedimiento de perfil realmente nuevo requiere decisión explícita; esta cartografía no lo diseña ni activa.

<a id="j03-correcciones"></a>

## J03 — Correcciones

**Cadena real.** Reportar desde perfil → [public-profile-correction.js](../../assets/js/public-profile-correction.js) → POST [public/correction-reports.php](../../api/profiles/public/correction-reports.php) → [ReportHttp](../../modules/profiles/Corrections/ReportHttp.php) / [ReportService](../../modules/profiles/Corrections/ReportService.php) → reporte privado cifrado. Staff original `section=correcciones` → POST [internal/correction-review.php](../../api/profiles/internal/correction-review.php) → [ReportReviewService](../../modules/profiles/Corrections/ReportReviewService.php) → decisión humana. Un reporte aceptado aún no cambió el perfil.

**Aplicación separada.** [profile-correction-application.js](../../assets/js/profile-correction-application.js) → mismo límite staff por acción → [ApplicationService](../../modules/profiles/Corrections/ApplicationService.php) / [ApplicationAuthority](../../modules/profiles/Corrections/ApplicationAuthority.php) → exact grant, caso, reauth/MFA, reporte aceptado/versión/fingerprint → [PublicPhoneCorrectionAdapter](../../modules/profiles/Corrections/PublicPhoneCorrectionAdapter.php) → writer tipado del teléfono global ya publicado → DTO público.

**Consistencia.** Preparación/autorización/journal durables preceden la aplicación. `execute` registra `APPLY_STARTED`, luego cambio exacto + resultado + auditoría en transacción nativa; ACK perdido o commit ambiguo conserva journal para `reconcile`, no replay ciego. Snapshot/CAS rechaza cambios concurrentes y propuestas fuera de scope. El reader muestra estado y resultado minimizados; no publica el payload original del tercero.

**Límite actual.** Sólo teléfono global publicado y elegible: no WhatsApp, sedes, contactos FREE ocultos, identidad, owner ni información clínica. Fuente y QA aislada implementadas, runtime normal-local inactivo. [PUB-COR01](../profiles/PUB-COR01-secure-intake-human-review.md), [PUB-COR02](../profiles/PUB-COR02-governed-typed-application.md), [PUB-06](INVENTARIO_FUNCIONAL_MXMED.md).

<a id="j04-consultorio-y-reserva"></a>

## J04 — Consultorio, disponibilidad y cita

**Canal original verificado en fuente CTX-MAP03.** La reserva integrada del [perfil público](../../profiles/doctor.php) usa `submitInlineBooking` → `/public/appointments/reserve` → `requestBookingOtp` → `/public/otp/request` → `verifyBookingOtp` → `/public/appointments/confirm`. [AGENDA-PUB01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-pub01) identifica médico/sede, solicitante distinto del paciente, campos persistidos y fallos; las páginas legacy no son su prueba de conexión. La [matriz por acción](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-matriz) separa médico, operador, Call Center y sistema/IA.

**Edición de sede/horario.** Consultorio original `#p-consultorio` → [multisede.js](../../assets/js/perfil/consultorio/multisede.js) (`collectPanePayload`, confirmación de coordenadas) / [Agenda UI](../../assets/js/agenda/) → PUT ramas de consultorio/horarios en [gateway Agenda](../../api/agenda/index.php) → gate actual [PrivateApplicationAccessResolver](../../modules/identity/http/PrivateApplicationAccessResolver.php) del gateway + contexto perfil → [ConsultoriosController](../../modules/agenda/controllers/ConsultoriosController.php) / [ScheduleController](../../modules/agenda/controllers/ScheduleController.php) → repositorios y tablas `consultorios/consultorio_schedule`.

**Política preparada:** [PrivateAgendaRoutePolicy](../../modules/agenda/security/PrivateAgendaRoutePolicy.php) y la raíz R0 no equivalen al dispatch actual ni acreditan enforcement por método/rol en cada controller. La matriz CTX-MAP03 separa esas piezas y la autorización efectiva del operador canónico.

**Lectura y reserva.** Dirección/coordenadas confirmadas y flags alimentan DTO público; cambiar Maps en pantalla sin persistencia no cambia la ubicación pública. [AvailabilityController](../../modules/agenda/controllers/AvailabilityController.php) y sus repositorios combinan horario, zona/overrides, bloqueos y ocupación. Reserva pública → [PublicAppointmentsController](../../modules/agenda/controllers/PublicAppointmentsController.php), hold/OTP/delivery/verificación → `PublicAppointmentsController::reserve/confirm`, [PublicBookingPatientIdentityResolver](../../modules/patients/services/PublicBookingPatientIdentityResolver.php) y writer de cita. Es otra autoridad distinta del editor owner/administrator. [Contratos P2/P3/P4](../agenda/PUBLIC_BOOKING_P3.md) definen hold, expiración, cancelación y feedback.

**Consistencia de reserva.** `reserve` resuelve paciente, crea cita y guarda flow en pasos separados; fallo al guardar flow intenta cancelar la cita pendiente como compensación. `confirm` verifica OTP, actualiza cita y marca flow, sin transacción única envolvente. No describirlo como commit ACID común.

**Cita/paciente/encounter.** Escritura privada usa [AppointmentWriteController](../../modules/agenda/controllers/AppointmentWriteController.php) / [AppointmentWriteRepository](../../modules/agenda/repositories/AppointmentWriteRepository.php), lock/colisiones/versionado e idempotencia; snapshots de origen se conservan. La resolución del paciente pertenece a `PatientsRepository`; [ClinicalEncounterBridge](../../modules/agenda/services/ClinicalEncounterBridge.php) sólo intenta enlazar encounter con `AGENDA_ENABLE_CLINICAL_ENCOUNTER_BRIDGE=1`, estado `completed` y gate M6, por HTTP separado con error capturado/log; la [decisión de Agenda](../clinical/DECISION_AGENDA_CREATES_CLINICAL_ENCOUNTER_V1.md) explica su propósito, no elimina esos gates. No asumir transacción distribuida entre guardado de sede, OTP, cita y consulta: son comandos/etapas separados, con conflictos y recuperación propios.

**Resultado/estado.** Read models Agenda/público vuelven a consultar disponibilidad/cita; un hold o mensaje enviado no es cita confirmada. Proveedor, token o slot inválidos tienen respuestas de fallo/expiración/conflicto. Replay confirmado se trata por su etapa; no se acredita consistencia global de todos los reintentos de reserva/flow. Waitlist asigna por otro consumidor: faltan paridad de controles/atomicidad global y guard equivalente en actualización por ID; ver AGENDA-ESP. Autoridad de operador sólo dentro de grants exactos y no inicia actos profesionales. [AG-01..07 y PRF-03..05](INVENTARIO_FUNCIONAL_MXMED.md), [mapa Agenda histórico](../MAPEO_AGENDA_MXMED.md); actual ejecución/delivery no revalidada aquí.

<a id="j05-paciente-consulta-y-expediente"></a>

## J05 — Paciente, consulta y expediente

Traza detallada [F05](INTERACCIONES_CLINICAS_MXMED.md#f05): selección en shell → handlers originales → gateways pacientes/clínica → sesión Identity y scope doctor-paciente → maestro `patients_*` y encounter → secciones/observations/longitudinal → órdenes/consent/documentos → timeline/historia.

El writer de identidad del paciente no escribe historia clínica; el read model de timeline no crea documentos. Las transacciones, idempotencia, guardas de dirty-state y autorización por familia están documentadas por paso, incluidos los gates profesionales y la disparidad de autorización fuente en alta paciente aún no resuelta. No se probó acceso real ni se leyeron expedientes. [PAT/CLIN/STUDY](FUNCIONES_CLINICAS_MXMED.md).

<a id="j06-borrador-firma-y-documento-emitido"></a>

## J06 — Borrador, firma y documento emitido

Traza detallada [F06](INTERACCIONES_CLINICAS_MXMED.md#f06): editor/plantilla original → contexto y validación → draft estructurado → preview renderer → firma local/QR según familia → comando de emisión/versionado → documento/snapshot/hash/audit → viewer/print → void/reissue con lineage.

Cada familia conserva writer, autoridad y estrategia de snapshot; un PNG de firma registrado no permite prescribir. El consumidor profesional actual deniega aunque exista issuer C4; UI o QA histórica no elimina ese gate. CSS compartido puede alterar presentación de HTML histórico sin cambiar su contenido, por lo que requiere análisis posterior. La emisión/transacción y compensación/reintento no se sustituyen por volver a dibujar preview. [DOC/SIG](FUNCIONES_CLINICAS_MXMED.md), [estándar de presentación](../clinical/UX-ALIGN01-prescription-presentation-assessment.md).

<a id="j07-owner-operador-y-agenda-original"></a>

## J07 — Owner, operador y Agenda original

**Precisiones CTX-MAP03.** [AGENDA-OP01](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-op01) recupera las rutas efectivas del adapter, no toda acción de Agenda por analogía; [identidades y atribución](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-identidad) separa creador original, alias actual, editor posterior, canal, solicitante, paciente y médico. [Waitlist](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-esp01) y [Call Center](AGENDA_CANALES_Y_PERMISOS_MXMED.md#agenda-cc01) conservan fronteras diferentes; personal de plataforma no ocupa las dos plazas por ese motivo.

**Cadena.** `#p-ag-operadores` → [canonical-operators.js](../../assets/js/agenda/canonical-operators.js) / [operators-panel.js](../../assets/js/agenda/operators-panel.js) → [API operadores](../../api/profile-operators/index.php) → [OperatorHttp](../../modules/identity/operators/OperatorHttp.php) → [ProfileOperatorService](../../modules/identity/operators/ProfileOperatorService.php). Bootstrap/overview son lectura; command es escritura gobernada; invitado debe identificarse como destinatario exacto para aceptar/rechazar. Sólo owner administra, sin elevar al collaborator existente.

**Transacción/delivery.** Sesión/CSRF, cuenta, membership vigente, entitlement, command digest y cupo se revalidan bajo lock de perfil. Invitaciones/grants/revisiones + auditoría se escriben atómicamente según comando; entrega usa adaptador separado, no browser ni insert manual. Pending/active/suspended reservan plaza de las dos; revoked libera conservando historia. Repetición y concurrencia se resuelven por comando/revisión, no por email/alias.

**Uso.** Agenda original entra por `executeAgenda` y revisión vigente; [catálogo de grants](../../modules/identity/services/ProfileOperatorCapabilityCatalog.php): `agenda.appointments.read`, `agenda.appointments.create`, `agenda.appointments.reschedule`, `agenda.appointments.cancel`, `agenda.availability.read`. [OperatorPresentationRepository](../../modules/identity/operators/OperatorPresentationRepository.php) mantiene alias y lectura de asiento; snapshots de creador preservan actor/origen al cambiar alias o revocar. Ninguno concede clínica, firma, billing u ownership.

**Resultado y límites.** Overview renderiza estado canónico cuando existe composición; panel pending conserva historial server y referencias browser inciertas separadas/colapsables. No inferir que nombres iguales son la misma Identity; no borrar storage. Sin confirmación servidor no hay éxito de invitación. [OP-IMP01](../identity/OP-IMP01_OPERATOR_FOUNDATION.md), [OP-IMP02](../identity/OP-IMP02_ISOLATED_OPERATORS_RUNBOOK.md), [OP-INT04](../identity/OP-INT04_NORMAL_LOCAL_OPERATORS_INTEGRATION.md) y tests de autoridad/concurrencia constituyen evidencia previa, no activación normal-local actual.

<a id="j08-plan-stripe-y-activación"></a>

## J08 — Plan, Stripe y activación

CTX-MAP03 amplía [descuento y contrato COM-09](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#descuentos-com-09): el control de cupón no prueba validación ni persistencia. [COM-01..08](TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#casos-com-01-a-com-08) separa procedencia comercial, asesor de venta/soporte, revisor, aceptante y actor técnico; aceptación contractual no atribuye automáticamente una venta.

Traza detallada [J08 financiero](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#j08--plan--pago--evento--activación--entitlements): catálogo/precio/aceptación → ruta/checkout snapshot servidor → PaymentIntent → montaje browser (confirmación/retorno incompletos) → webhook firmado/deduplicado → pago confirmado → activación explícita en otra transacción → `profile_subscriptions` → capacidades/DTO público.

La confirmación pertenece a `ProcessStripeSubscriptionWebhookService`; la activación a `ActivateSubscriptionAfterPaymentService`, no al retorno browser ni al webhook por sí solo. Gates de escritura Identity, contrato QA, retorno faltante y renovación parcial se conservan explícitos. Evidencia sandbox PP220/231/232 es histórica; no se hizo llamada Stripe ni piloto nuevo.

<a id="j09-media"></a>

## J09 — Media privada, revisión y proyección

**Workspace sucesor ASE-INT01 R2.** [Equipo y permisos](../identity/ASE-INT01-R2-internal-team-readonly.md) agrega sección independiente antes del guard Media; links de Media/C4/correcciones reutilizados. Media no concede lectura staff. Nuevo GET/HEAD mínimo, sin writers ni cambio al pipeline Media. J08 sólo conserva límites COM aprobados; pagos/atribución/finanzas siguen desconectados. Once journeys preservados.


**Cadena.** Foto/galería/logo original → [profile-photo.js](../../assets/js/profile-photo.js), [fotos.js](../../assets/js/fotos.js), intake candidato → [APIs Media](../../api/media/) → identidad/scope/CSRF y validación MIME/tamaño → [ProfilePhotoReviewCandidateService](../../modules/media/services/ProfilePhotoReviewCandidateService.php) / [GalleryReviewCandidateService](../../modules/media/services/GalleryReviewCandidateService.php) / [PhysicianLogoReviewCandidateService](../../modules/media/services/PhysicianLogoReviewCandidateService.php) → [MediaAssetsRepository](../../modules/media/repositories/MediaAssetsRepository.php) + storage privado; [MediaCandidateSubmissionService](../../modules/media/services/MediaCandidateSubmissionService.php) registra submission/audit.

**Vía directa distinta.** Los endpoints directos photo/gallery y [DoctorProfilePhotoService](../../modules/media/services/DoctorProfilePhotoService.php)/[DoctorGalleryService](../../modules/media/services/DoctorGalleryService.php) aún usan `PublicMediaStoragePort` y assets públicos READY. No son equivalentes al intake privado de revisión usado por la UI nueva; ambas rutas fuente se conservan y deben evaluarse por separado.

**Revisión.** Inbox staff original → [API interna Media](../../api/internal/media-review/) → grant exacto/dominio → [PhysicianLogoApprovalService](../../modules/media/services/PhysicianLogoApprovalService.php), [MediaReviewBatchService](../../modules/media/services/MediaReviewBatchService.php), [MediaReplacementService](../../modules/media/services/MediaReplacementService.php) → versión aprobada y auditoría/ready event → [PublicLogoMediaService](../../modules/media/services/PublicLogoMediaService.php) / DTO público / presentación documental. Mejora y aceptación son acciones distintas; [HistoricalOriginalArchive](../../modules/media/services/HistoricalOriginalArchive.php) conserva original.

**Consistencia/fallos.** SQL y bytes no forman una transacción ACID común: servicios delimitan escritura/staging/publicación/cleanup y validan refs/hash/estado; no inventar rollback distribuido ni worker automático por existir ready event. Dedupe/versionado/inmutabilidad evitan sustituir un original silenciosamente; error de almacenamiento/revisión conserva rechazo/estado pendiente, no URL pública arbitraria. Acceso a original sigue privado; aprobación de media no acredita a la persona ni autoriza firma. [MED-01..03](INVENTARIO_FUNCIONAL_MXMED.md), [storage y optimización](../architecture/MXMED_MEDIA_STORAGE_IMAGE_OPTIMIZATION_AND_SEMANTIC_METADATA.md), [MR11](../../modules/media/docs/mr11-1-operational-closeout.md). Estado efectivo de storage/revisores no consultado.

<a id="j10-facturación-clínica-y-frontera-fiscal"></a>

## J10 — Facturación clínica y frontera fiscal

CTX-MAP03 detalla operaciones de BILL-01..06 y su matriz médico/operador/staff en el [mapa financiero](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md). Selección paciente/link, defaults fiscales, emisor/CSD, borradores/revisiones, archivo privado y PAC no comparten automáticamente permisos ni la misma revalidación de lectura. Un descuento de concepto fiscal tampoco es cupón de suscripción MXMED.

Traza detallada [J10 financiero](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#j10--contexto-clínico--receptoremisor--borradorarchivo--límite-pac): paciente/contexto → perfil fiscal receptor y emisor/CSD → borrador/items/impuestos/preview → archivo importado o frontera PAC → estados/audit/reconciliación.

Cobrar un plan MXMED por Stripe no timbra un CFDI al paciente. SW es PAC primario seleccionado y Finkok secundario; port/provider/configuración y custodia pendientes no reabren selección comercial. Timeout no es fracaso fiscal confirmado ni autoriza duplicar timbrado. No hubo proveedor fiscal, archivo de paciente ni configuración inspeccionados operacionalmente.

<a id="j11-notificación-qr-y-acción-remota"></a>

## J11 — Notificación, QR y acción remota

Traza detallada [F11](INTERACCIONES_CLINICAS_MXMED.md#f11): emisor autorizado → token/session acotado → entrega/presentación QR → dispositivo receptor con token propio → validación de propósito/estado/TTL → bytes/acción permitida → consumo/expiración/revocación → polling/read model del emisor.

Firma registrada, QR clínico, captura móvil e Identity verification/recovery tienen contratos distintos; token no hereda cookies ni autoridad profesional del desktop. URL relativa Rx resuelta con `location.origin` puede producir localhost inaccesible desde teléfono físico; código constructor conocido no equivale a conocer el QR real o su conectividad. [Integraciones externas](STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md#integraciones-externas-y-recursos-compartidos) separa archivo local email, SES, SMS aprobado ausente, QR local/fallback externo y centro visual de mensajes. Expirar/consumir un token no borra la historia ni autoriza reissue manual.

## Dependencias compartidas

| Cambio autorizado futuro | Consumidores que recuperar antes de editar | Límite que no debe cruzarse |
|---|---|---|
| Identity/sesión/credential version | Shell privado, clinical resolver, staff/MFA, Operadores, APIs perfil/provider/billing | Una sesión válida no concede cada capability; revisar denegaciones y propagación de revocación |
| Membership/ownership/C4 | Gobierno, fuentes, issuer, Operadores y consumidor clínico profesional | Médico, owner, staff y operador son autoridades diferentes; limitación de implementación no se convierte en política |
| Suscripción/entitlements | DTO público/contactos, Agenda/Operadores, Media, documentos, provider | Pago confirmado ≠ activación; plan ≠ permiso profesional; FREE privado ≠ publicación |
| Consultorio/horario/ubicación | Disponibilidad, booking, perfil público, presentación documental, citas | Geocode/mapa pendiente no es ubicación confirmada; orden/público/documento son flags/contratos distintos |
| Contactos | Administración privada, sedes, DCP global/emergency, OTP, DTO público, corrección tipada | No copiar propósitos ni publicar FREE; teléfono de seguridad no se deriva de consulta |
| Paciente/link/encounter | Agenda, expediente, longitudinal, órdenes, docs, billing | Maestro de identidad ≠ historia; todo reader/writer necesita scope doctor-paciente |
| Renderer/CSS/plantillas | Preview, HTML histórico, print/PDF, firma y reissue | Conservar snapshots/versiones, evaluar paginación histórica y datos firmados |
| Media/storage | Perfil, inbox staff, logos, imágenes de documento/firma y handoff | Bytes privados/candidato no son proyección pública; refs no prueban permiso |
| Auditoría y SQL routines | Todos los writers gobernados, transacciones joined, deployment fingerprints | Fallo audit/deriva debe cerrar/revertir; no ejecutar migración por encontrar dependencia |
| Infra/runtime/packaging | Entrypoints empacados, DB env names, Valkey/TLS, SES/jobs, webhook ingress | IaC/evidencia histórica no acredita imagen desplegada o estado remoto actual |

## Mantenimiento y evidencia

Registrar cambios de conexión en el journey afectado y en su capability, sin duplicar contratos. Anclar nuevo entorno/commit/fecha sólo cuando se verifiquen. Ver [cobertura y preguntas de recuperación](COBERTURA_Y_PENDIENTES_MXMED.md) para distinguir análisis disponible de estado externo no observable. Próxima acción de producto/piloto: `NONE_PENDING_DIRECTOR_REVIEW`.

### J09 — sucesor backend ASE-INT02A

[Comando individual Advisor](../identity/ASE-INT02A-governed-advisor-grants.md): sesión/CSRF/origen → POST exacto → locks actor/target y autoridad actual → writer/auditoría → commit o rollback → refresh autoritativo futuro. La UI aún no realiza esta cadena; deuda `ASE-INT02A-UI-CONNECTION` exige confirmación y resultados parciales, nunca bulk-save atómico/retry ciego. J08 no cambia; sin admisión, Master o pagos.

## J09 — matriz conectada ASE-INT02B

[Flujo verificado](../identity/ASE-INT02B-team-permission-ui.md): snapshot autorizado → borrador del target → refresh/confirmación/refresh → comandos individuales secuenciales → lectura canónica por intento. Conflicto, autoridad perdida o ACK incierto detienen restantes; éxitos previos conservados, sin replay ni compensación. Grupos sólo claves elegibles, ocultos UNKNOWN; descarte al cambiar contexto. Sucede la deuda UI del hito anterior, exclusivamente en feature/QA aislada. J08 y activación sin cambios.

## J09 — suspensión/reactivación ASE-INT03A

[Flujo](../identity/ASE-INT03A-advisor-lifecycle.md): resolver borrador → snapshot → confirmación española → snapshot vigente → comando lifecycle único bajo locks → audit/commit → refresh. Conflicto o ACK incierto no produce éxito inventado/retry. Suspensión bloquea siguiente autoridad operativa; reactivación conserva gates independientes. J08/D06 no cambia.

## J09 — flujo Master propuesto ASE-INT03B-PREP01

[Contrato y secuencia](../identity/ASE-INT03B-PREP01-master-privileged-authorization.md): selección/snapshot → confirmación → reauth canónica → MFA exacto → locks/consumo/mutación/audit → refresh/reconciliación. Es propuesta, no journey conectado ni probado; casos actuales son específicos de otros dominios. Preserva J09 existente y frontera J08; no nuevas asignaciones comerciales.

## J09 — sucesor backend ASE-INT03B-IMP01

[Secuencia implementada](../identity/ASE-INT03B-IMP01-master-security-foundation.md): abrir intención servidor → confirmar digest/contraseña Director → TOTP → proof exacto → locks/revalidación/consumo/writer/audit atómicos → reconciliar caso/UUID/audit. UI-2 aprobada conceptualmente; conexión IMP02 pendiente. Logout anterior al check final deniega; después de ese punto no se promete fencing SQL/Valkey. Conserva frontera J08 y atribución comercial.
