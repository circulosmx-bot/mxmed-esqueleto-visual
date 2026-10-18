# ASE-VIS01 R2 — prototipo reversible del equipo interno

## ASE-COM01 — aprobación R2 conservada y extensión comercial aislada

**Sucesor vigente, 2026-10-17:** el Director denomina expresamente R2 `d87174774de6526d54082c1b7a770b228c472746` **visualmente aprobado** en ASE-COM01. Se conserva esa aprobación; las menciones anteriores de aprobación R2 pendiente más abajo son historia de su entrega. No se infiere Safari físico ni autorización de integración. Sólo las adiciones comerciales esperan nueva revisión visual.

[Contrato comercial único](ASE-COM01-commercial-attribution-recurring-commission.md) registra requerimiento recurrente, decisiones recuperadas/pendientes y productor→consumidor→primer enlace ausente. La misma UI añade familia **CAPTACIÓN Y VENTAS** con cuatro actividades propuestas sólo lectura, **CONDICIONES COMERCIALES** en detalle y modal **MI ACTIVIDAD COMERCIAL** con médicos ficticios del asesor seleccionado. Sin catálogo/grants ejecutables nuevos, tasa/base/saldos, API/SQL, códigos/acciones financieras reales o integración ASE-INT01. Master no hereda atribución, código ni poder financiero.

R2 conserva DOM/geometría de encabezado, directorio, detalle y cinco familias previas; nuevas zonas debajo y con CSS acotado. Catálogo inalterado (18/16). Un hook de render/presentación y helper modal reutilizan la UI existente; expansión comercial no entra en el editor de grants. La QA R2 vigente se ejecuta con las dos nuevas rutas estáticas permitidas y evidencia propia, sin sobrescribir R1/R2 históricos. [Suite comercial](../../prototypes/ase-vis01/evidence/com01/browser/summary.json), [regresión R2](../../prototypes/ase-vis01/evidence/com01/r2-regression/summary.json), [capturas](../../prototypes/ase-vis01/evidence/com01/browser/), [aislamiento](../../prototypes/ase-vis01/evidence/com01/isolation.json). Los diez campos CTX-SYNC vigentes constan en el contrato comercial y reporte final.

Misma URL `http://127.0.0.1:8140/`, expiración `2026-10-25T00:00:00Z`. Retiro seguro al final de este contrato; nuevo commit/QA/digests en entrega. `NEW_VISUAL_APPROVAL_PENDING=true` sólo para ASE-COM01. `OPERATIONAL_STATUS=PROTOTYPE_ONLY_NOT_OPERATIONAL`.

<a id="ase-vis01-r2--revisión-vigente-sin-integración"></a>
## ASE-VIS01 R2 — revisión entregada, sin integración

**Inicio:** prototipo `6e7def31cf16f9e782ea9489e40f8d68ebf0b5b6`; fuente oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`. Misma rama/worktree/preview de R1. R1 permanece como checkpoint en Git y sus capturas/resultados anteriores son históricos de esa versión, no QA de R2.

| Eje | R2 |
|---|---|
| `PRODUCT_POLICY` / `APPROVED FUNCTIONAL DESIGN` | Tres decisiones del Director: interfaz común para asesores/Masters; una incorporación guiada; familias contraíbles con selección múltiple visual e individualización. Sólo Director nombra/retira Masters; Master sólo gestiona asesores con gestión y delegación exactas. |
| `SOURCE_IMPLEMENTATION` / `SIMULATED PROTOTYPE` | Extiende el mismo mock en memoria; directorio por categoría, gestión Master, incorporación de cuatro pasos y familias triestado. Ningún cambio productivo. |
| `PROPOSED VISUAL APPEARANCE` | Conserva directorio izquierdo/detalle derecho, tipografía/colores y superficie. «EQUIPO INTERNO Y PERMISOS»; apariencia y responsive finales pendientes de aprobación. |
| `EXISTING BACKEND` | Catálogo, Identity, gobierno, auditoría y ceremonia MFA existentes, leídos en fuente. No ejecutados por el mock. |
| `CONNECTIVITY` / `MISSING BACKEND CONNECTION` | Sin sesión/API/DB/transporte. Faltan reader/adapter staff, incorporación orquestada, invitación/admisión staff, versionado y contrato específico de step-up Master. |
| `VERIFICATION` | QA R2 y reducción de altura documentadas abajo. No prueba de autorización backend ni Safari físico. |
| `OPERATIONAL_STATUS` | `PROTOTYPE_ONLY_NOT_OPERATIONAL`; no cuenta, grant, delegación, MFA o invitación real. 8091/8092/8443/6385 preservados. |

### Directorio, permisos y gobierno

- Asesores y Administradores maestros son filtros del mismo directorio. El Director raíz no es una tarjeta editable. Ocho personas ficticias iniciales; no constituyen cuota de staff. Las dos plazas de operadores por médico permanecen independientes.
- Lucía Castaño ilustra dos grants operativos, gestión de asesores y tres delegaciones distintas (`media_review_read`, `media_review_approve`, `physician_accreditation_review`). Tomás Niebla ilustra un Master suspendido. Ninguno representa una persona real.
- Director puede proponer/retirar designación Master con confirmación explícita separada y acknowledgement obligatorio. Proponer deja `PENDING_AUTHORIZATION` **sólo como estado UX**, conserva grants operativos y no añade gestión/delegaciones. Retirar conserva grants/historia, retira gestión y delegaciones y vuelve a la categoría Asesor, como el contrato de `remove_master`; conservar un grant no elimina gates de consumidor.
- Master no accede a controles para nombrar Masters ni a targets propios/Masters; sólo asesores y su subconjunto exacto. Sin `internal_advisors_manage` no hay gestión. Tener grant de uso no equivale a poder concederlo; una delegación sin gestión tampoco autoriza administración.
- En detalle Master, «Permisos para usar funciones» está separado de «Gestión y delegación para asesores». `internal_advisors_manage` se representa sólo allí como autoridad reservada otorgable por Director, nunca como familia operativa ADVISOR. `profile_initial_ownership_approve` sigue fuera del editor.

### Familias y triestado

Mismo catálogo de **18** entradas: **16** operativas delegables y **2** de gobierno. La tabla exacta de dominios/IDs de R1 siguiente continúa vigente. Cinco familias de presentación: Acreditación profesional (dos claves), Revisión de medios (seis), Correcciones de perfiles (dos), Administración de perfiles (cuatro), Organizaciones y proveedores (dos). No se crea capability genérica, bundle ni writer de grupo.

Cada fila tiene **selección** y **expandir/contraer** como controles nativos distintos, con nombre accesible y `aria-expanded/controls`. Expandir no cambia grants. Checkbox padre usa `indeterminate` y `aria-checked=mixed` cuando hay selección parcial del total; conteos separan total seleccionado y subconjunto editable. Seleccionar/deseleccionar opera sólo sobre claves editables; conserva sin alteración las bloqueadas o fuera de delegación. Si sólo dos de seis son elegibles y quedan seleccionadas, el indicador global continúa mixto, con «2 de 2 editables» explícito. Si las otras cuatro ya estaban asignadas, se conservan y el total refleja ese hecho, sin atribuir su concesión al actor actual.

Los cuatro `profile_*` siguen sólo lectura. Correcciones permanece bloqueada para targets ADVISOR; en Master se representa su consumidor de clase existente, sin nueva habilitación para asesores. Delegaciones de correcciones/futuro se conservan pero no se ofrecen como funciones utilizables por asesores. Las dos proveedoras mantienen el aviso de pantalla pendiente. **Asignación, autoridad efectiva y disponibilidad del consumidor son tres hechos distintos.**

No autosave: **GUARDAR PERMISOS** confirma cada concesión/retiro y delegación por su etiqueta española, más cambios de gestión. Descarga privada/aprobación pública permanecen explícitas. Borrador, revertir, suspensión sin borrado, historial ficticio, conflicto/refresh y foco modal conservan la base R1. La simulación que rechaza el conjunto ante conflicto no promete atomicidad del backend futuro.

### Incorporación unificada: una experiencia, varios pasos canónicos futuros

«+ AGREGAR INTEGRANTE» abre Persona → Categoría → Permisos → Revisión. Sólo admite nombres ficticios y correos `@example.test`, sin consulta real de existencia. Director puede proponer Asesor/Master; Master autorizado sólo Asesor con su delegación. Configuración de gestión/delegaciones y confirmación privilegiada Master son exclusivas de Director. No se pide contraseña de otra persona.

| Estado UX propuesto, nunca enum nuevo | Presentación del caso ficticio |
|---|---|
| `PENDING_INVITATION` | Solicitud de invitación o invitación vencida; nunca «enviada» ni cuenta activa. |
| `PENDING_IDENTITY_VERIFICATION` | Falta verificación; sin autorización operativa. |
| `PENDING_AUTHORIZATION` | Cuenta activa existente a reutilizar, propuesta Master o fallo parcial; falta autorización confirmada. |
| `PENDING_MFA_SETUP` | Falta ceremonia MFA aplicable, con registrador independiente. |
| `ACTIVE` / `SUSPENDED` | Fixtures previos para revisar uso/gestión y conservación de grants; no resultado automático de solicitar incorporación. |

Confirmar sólo añade una **propuesta ficticia** al directorio con cero nuevos grants/gestión/delegaciones efectivos; guarda la intención por separado. El caso duplicado no crea otra entrada. Vencimiento y fallo parcial requieren revisión, refresh/reconciliación y confirmación legítima; no reenvío ni retry ciego. Cerrar/recargar descarta memoria. No se infiere identidad o relación staff por nombre/email coincidente.

### Contrato futuro contrastado con registro y notificaciones

| Fuente existente inspeccionada, sin instanciar | Reuso y límite |
|---|---|
| [RegistrationService](../../modules/identity/services/RegistrationService.php) | La persona aporta contraseña y aceptaciones/versiones legales. Crea cuenta pendiente, credencial/consents y token en transacción propia; **luego** notifica. Duplicado puede devolver decisión aceptada con razón específica: no acredita alta ni activación. Si falla notificar, conserva cuenta pendiente y trata de invalidar el token; no repetir alta a ciegas. |
| [EmailVerificationService](../../modules/identity/services/EmailVerificationService.php) | Verifica propósito/expiración/consumo/invalidación, cuenta pendiente y consents; activa/consume canónicamente. `resend` reemplaza token en transacción y notifica después. No es invitación de staff ni autoriza gobierno. |
| [IdentityNotificationPort](../../modules/identity/contracts/IdentityNotificationPort.php), [NotificationMessage](../../modules/identity/contracts/NotificationMessage.php), [OneTimeTokenPurpose](../../modules/identity/contracts/OneTimeTokenPurpose.php) | Transporte/proósitos existentes. No se localizó propósito ni handoff de invitación/admisión de staff interno. `OPERATOR_INVITATION` pertenece a operadores por médico; no se reutiliza como staff. |
| [PreviewIdentityNotificationAdapter](../../modules/identity/adapters/PreviewIdentityNotificationAdapter.php), [composición normal-local](../../modules/identity/http/IdentityHttpComposition.php), [SesIdentityNotificationAdapter](../../modules/identity/adapters/SesIdentityNotificationAdapter.php) | Sink privado local y transporte productivo existentes, con sus límites de propósito/configuración; sólo lectura de fuente. Ninguna notificación, archivo de tokens ni proveedor ejecutado en R2. |
| [InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php) | Cuenta activa existente para `register`; `appoint_master` crea relación Master o promueve Asesor activo bajo Director. `remove_master` conserva grants operativos y revoca gestión/delegaciones. `grant/revoke` y `delegate/revoke_delegation` son comandos exactos separados. No hay adapter staff conectado. |
| [GovernanceStaffMfaCeremony](../../modules/identity/services/GovernanceStaffMfaCeremony.php), [GovernanceSecurityContext](../../modules/identity/services/GovernanceSecurityContext.php), [StaffMfaService](../../modules/identity/services/StaffMfaService.php) | Existen reauth/sesión viva, enrolamiento con registrador independiente aprobado y proofs ligados a operación. `InternalGovernanceService::execute` no los compone para nombramiento Master; la allowlist actual de desafíos no define `appoint_master`. No presentar una ceremonia visual completa ya implementada ni sustituirla por otra operación MFA. |

Antes de integrar: aprobar adapter/reader paginado y minimizado con sesión canónica, métodos/CSRF, no-store/same-origin, autoridad/targets/allowlists y grants/delegaciones actuales; matching de cuenta autorizado sin enumeración ni filtración entre cuentas; admisión/invitación y aceptación por la persona, expiración/reemisión y duplicados; contrato específico de acción privilegiada con step-up ligado a actor/target/intención/versiones, auditoría y revalidación; definición de activación final y MFA aplicable. Reusar los servicios existentes, no un segundo login, password delegado, writer de grants o motor de roles.

**Una incorporación guiada no es una transacción única.** El gobierno abre una transacción por comando y rechaza una exterior; no hay batch/CAS/idempotencia existentes. Hace falta snapshot/versionado real y orquestación aprobada con resultado autoritativo por capability/paso, éxito parcial visible, estado de commit/entrega desconocido, refresh, reconciliación de respuesta perdida y nueva confirmación. No declarar éxito global, retries ciegos ni rollback global silencioso. No activar una cuenta pendiente por el mero retorno `accepted` ni conceder gobierno desde una invitación.

### QA R2 y gate

**PASS del prototipo R2: 1.112 checks, ocho recorridos de 139, Chromium y WebKit en 1440×900, 1366×768, 820×1180 y 390×844.** [Resumen y hashes del candidato](../../prototypes/ase-vis01/evidence/r2/browser/summary.json). Se cubren escenarios 1–20, grupo completo/individual/mixto, permisos bloqueados conservados, gestión/delegación separadas, nombramiento/retiro, onboarding existente/nuevo/duplicado/vencido/parcial/verificación/MFA, conflicto/reconciliación y reapertura rápida de diálogo. Sin errores de aplicación, solicitudes fuera del allowlist, storage/cookies, llamadas Identity/DB o entrega real.

La [comparación medida](../../prototypes/ase-vis01/evidence/r2/height-comparison.json) contra el [baseline R1](../../prototypes/ase-vis01/evidence/r2/baseline.json) muestra **61,48–67,14% menos altura de matriz**, según viewport/motor. Desktop 1440: ~1.682 →553px; tablet: ~1.757 →676px; móvil: ~2.058 →731px. La página inicial medida también se reduce; móvil usa directorio/detalle alternables. Sin overflow horizontal ni recortes observados en capturas. Targets >=44px, labels/descripciones asociados, triestado anunciado, expandir separado, foco visible, Tab/Shift+Tab modal, Escape/cancelar y retorno de foco verificados. Alcance de accesibilidad: DOM/teclado, sin lector de pantalla físico. Móvil `INTERIM_MOBILE_SMOKE_ONLY`; Safari físico `NOT_RUN` y aprobación visual final pendiente.

**28 capturas R2:** [desktop](../../prototypes/ase-vis01/evidence/r2/browser/chromium-1440x900-collapsed.png), [tablet](../../prototypes/ase-vis01/evidence/r2/browser/webkit-820x1180-collapsed.png), [móvil](../../prototypes/ase-vis01/evidence/r2/browser/webkit-390x844-collapsed.png), [Master](../../prototypes/ase-vis01/evidence/r2/browser/chromium-1440x900-master-administration.png), [incorporación](../../prototypes/ase-vis01/evidence/r2/browser/chromium-1440x900-unified-onboarding.png), [expandido/mixto](../../prototypes/ase-vis01/evidence/r2/browser/chromium-1440x900-expanded-partial.png), [confirmación individual múltiple](../../prototypes/ase-vis01/evidence/r2/browser/chromium-1440x900-multi-confirmation.png). Las 40 capturas y el resultado R1 más abajo permanecen históricos, sin modificación.

[Preservación y rollback R2](../../prototypes/ase-vis01/evidence/r2/isolation.json): 2.992 archivos fuente idénticos, HEAD/worktree oficiales preservados y mismos procesos 8443/8091/8092/6385. Servidor estático rechaza rutas productivas/repositorio (404) y POST (405). Se reabrió/detuvo sólo el preview de prueba8141, liberando ese puerto y conservando8140; recargar descarta simulaciones. Cero conexiones SQL o peticiones HTTP a la aplicación original. No se reejecutaron suites backend que muten datos; sus contratos/evidencias históricas no se sustituyen por esta QA de mocks.

WebKit conserva 6/1/1/6 diagnósticos de captura por viewport (14 total): inserción exacta `body {}` del driver Playwright rechazada por CSP, correlacionada con mutación/timing/hash de herramienta como en R1. Otros errores de consola/CSP son fatales. No se cambió driver ni CSP. El candidato se probó antes de commit; el reporte identifica el HEAD inicial y SHA-256 de los nueve archivos pertinentes, que se cotejan con el árbol final antes del push. Un HEAD previo no se presenta como commit nuevo ya probado.

Preview mismo loopback8140, expiración `2026-10-25T00:00:00Z`; servidor static allowlisted existente, ninguna exposición del repositorio/PHP. Reapertura y descarte seguro conservan las instrucciones R1. Fuentes/listeners originales deben permanecer idénticos; sin integración a la rama oficial. El commit exacto se informa al cerrar y sus bytes deben coincidir con los digests del candidato probado.

CTX-SYNC01 R2: `CONTEXT_BASELINE=6e7def31` prototipo / `f92f37f1` fuente; mapas CTX-MAP02 `1b8dfccb` y CTX-MAP03 `0bea8195` como anclas históricas. IDs **ID-07/COM-10**, 18 claves sólo representadas; journeys **NONE** modificados; objetos **NONE** leídos/escritos (tablas Identity/staff/grants/delegaciones/auditoría sólo contrato futuro). Afecta este contrato, registro visual, estado, mapa asesores e IW01; no cambia censos ni router. `CONTEXT_VERIFICATION=PASS`: catálogo/fuente contrastados, 1.112 checks, capturas, reducción medida, aislamiento/rollback, diff y enlaces/anchors verificados. `CONTEXT_SYNC_STATUS=PASS_UPDATED` para los cinco documentos afectados; el cotejo de digests ata el candidato al commit final. `DOCUMENTATION_COMMIT` en entrega final; gaps: aprobación visual, reader/API/orquestación/invitación/versionado y step-up Master autorizados, operación real separada.

**FINAL_VISUAL_APPROVAL=false**. Integración sólo tras **“Apruebo visualmente esta versión.”** y alcance posterior autorizado. Las tres decisiones funcionales no aprueban todavía la apariencia final.

---

## Contrato R1 preservado — checkpoint histórico

Lo siguiente documenta el alcance, capturas y QA de R1 (`6e7def31`). R2 sucede su exclusión visual de gestión Master y su placeholder de alta; sus límites canónicos, fuente y seguridad continúan salvo los refinamientos explícitos anteriores. No interpretar el resultado R1 como QA de la revisión actual.

## Alcance y aprobación

**Baseline:** `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, 2026-10-17. Rama fuente protegida: `ux/consultation-step2-vitals-r1`, worktree `/Users/circulodigital/Documents/GitHub/mxmed-step2-vitals-r1`. Prototipo aislado: `prototype/ase-vis01-advisor-capability-matrix`, worktree `/Users/circulodigital/Documents/GitHub/mxmed-ase-vis01-prototype`.

| Eje | Estado de esta entrega |
|---|---|
| `PRODUCT_POLICY` | Director aprobó el concepto directorio izquierdo / matriz individual derecha. Apariencia, espaciado, responsive e interacción final pendientes. Se conservan ASE-ALIGN01 V2 e IW01. |
| `SOURCE_IMPLEMENTATION` | Prototipo estático UI-3, sólo identidades y eventos ficticios en memoria de la página. La UI administrativa real de asesores sigue sin implementar. |
| `CONNECTIVITY` | Sin adapter HTTP, sesión, comandos de gobierno ni persistencia. No modifica ni sustituye el workspace interno original. |
| `VERIFICATION` | Evidencia del prototipo y navegadores en la sección de QA; no es prueba de autoridad de backend ni Safari físico del Director. |
| `OPERATIONAL_STATUS` | `PROTOTYPE_ONLY_NOT_OPERATIONAL`; normal-local original sin cambios. Ningún staff, grant, factor MFA, cuenta, invitación, DDL/DML o activación real. |

Superficie `INTERNAL_ADVISOR_CAPABILITY_MATRIX_PROTOTYPE`; [registro visual](../MXMED_REGISTRO_CONTRATOS_VISUALES.md#ase-vis01--matriz-individual-de-asesores-prototipo). El prototipo se presenta como **PROTOTIPO — NO OPERATIVO**. La integración requiere la frase expresa **“Apruebo visualmente esta versión.”**; no recibida. No hay merge ni promoción a 8091/8443.

## Composición propuesta

- Encabezado **ASESORES Y PERMISOS**; apoyo **Administración individual de funciones autorizadas.**
- Directorio izquierdo aproximadamente un tercio y detalle derecho dos tercios en desktop; selección en la misma página, estado activo/suspendido, conteo individual y búsqueda por nombre. En pantallas estrechas, apilado legible.
- Matriz con etiquetas en español y checkboxes independientes; IDs técnicos sólo en atributos QA/catálogo/documentación. Conteo de grants asignados no implica servicio disponible ni autoridad efectiva de dominio.
- Se reutiliza el lenguaje visual sobrio del [workspace interno](../../internal/media-review/index.php) y su [CSS de referencia](../../assets/css/media-review-inbox.css): tipografía de sistema, texto azul oscuro, fondo claro, bordes discretos, controles legibles y foco visible. No se edita esa pantalla ni su CSS.
- **GUARDAR PERMISOS** es explícito: cambios pendientes, revertir, confirmación separando concesiones/retiros y resultado marcado como simulación. No autosave. Cambiar de selección con cambios pendientes exige resolverlos.
- Suspender/reactivar solicita confirmación; la simulación conserva grants e historia, no elimina cuentas. Historial de alta, concesión, retiro, suspensión y reactivación siempre ficticio.
- Contextos de demostración Director/Master: el Master requiere gestión y delegación exactas, no se autoadministra. Las capacidades sin delegación o consumidor admisible quedan deshabilitadas con motivo legible. No hay selector que conceda poderes reservados.
- **Agregar asesor** sólo explica el futuro registro de relación staff sobre cuenta Identity legítima existente; no registra cuenta ni relación. La cantidad de fixtures no define cuota de asesores. Las dos plazas de operadores por médico permanecen separadas.

La simulación es una propuesta visual, no una segunda fuente de permisos. Recargar restaura fixtures; ningún estado local autoriza operaciones reales.

## Catálogo exacto y disponibilidad

Fuente única: [InternalCapabilityCatalog::all()](../../modules/identity/services/InternalCapabilityCatalog.php) del baseline. La exportación del prototipo es una instantánea reproducible de ese método, no un registro editable de autoridad. **18 definiciones: 16 operativas delegables y dos de gobierno no delegables.** Los grupos conservan los dominios fuente; una capacidad marcada no implica otra. La UI reúne esos ocho dominios en cinco grupos: Acreditación profesional, Revisión de medios, Correcciones de perfiles, Administración de perfiles y Organizaciones y proveedores.

| Dominio fuente / grupo visual | IDs exactos | Consumidor y límite comprobados |
|---|---|---|
| `media_review` / Revisión de medios | `media_review_read`, `media_review_approve`, `media_review_source_download`, `media_review_corrected_upload`, `media_review_improve`, `media_review_request_replacement` | Consumidores y workspace Media existentes; ADVISOR ACTIVE con cada grant exacto y gates particulares. Consultar cola no concede mutaciones, descarga ni publicación. |
| `profile_administration` / Administración de perfiles | `profile_create`, `profile_curate`, `profile_publication_manage`, `profile_support_assist` | Definidas; [ProfileGovernancePolicy](../../modules/identity/services/ProfileGovernancePolicy.php) proyecta política. Adapter/writer/pantalla genéricos no localizados. Sólo lectura/deshabilitadas en este prototipo. |
| `professional_sources` / Acreditación profesional: fuentes | `professional_source_provision` | [SourceProvisioningService](../../modules/identity/ProfessionalSources/SourceProvisioningService.php), workspace original, caso/scope/reauth/MFA. ADVISOR elegible sólo para la rutina exacta ASE-ALIGN01 V2; normal-local inactivo. |
| `physician_accreditation` / Acreditación profesional | `physician_accreditation_review` | [PhysicianOnboardingHttp](../../modules/identity/Accreditation/PhysicianOnboardingHttp.php), [EvidenceAuthority](../../modules/identity/Accreditation/EvidenceAuthority.php) y [ReviewedEnrollmentService](../../modules/identity/Accreditation/ReviewedEnrollmentService.php). ADVISOR elegible para las dos rutinas exactas; no ownership/firma. Normal-local inactivo. |
| `public_profile_corrections` / Correcciones de perfiles: revisión | `public_profile_correction_review` | [ReportReviewService](../../modules/profiles/Corrections/ReportReviewService.php) y workspace presentes; consumidor exige DIRECTOR/MASTER_ADMIN además del grant. Un grant ADVISOR no habilita revisión. |
| `public_profile_correction_application` / Correcciones de perfiles: aplicación | `public_profile_correction_apply` | [ApplicationAuthority](../../modules/profiles/Corrections/ApplicationAuthority.php)/[ApplicationService](../../modules/profiles/Corrections/ApplicationService.php) y workspace presentes; DIRECTOR/MASTER_ADMIN, caso/reauth/MFA y cambio tipado exacto. Un grant ADVISOR no habilita aplicación. |
| `provider_claim` / Organizaciones y proveedores: reclamaciones | `provider_claim_review` | [HealthcareOrganizationClaimService](../../modules/agenda/services/HealthcareOrganizationClaimService.php) admite staff activo con grant exacto. Backend existente; adapter/pantalla staff no localizados. No es cartera médica. |
| `provider_commercial` / Organizaciones y proveedores: suscripciones | `provider_subscription_activate` | [ProviderOrganizationSubscriptionService](../../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php) admite staff activo con grant exacto. Backend existente; adapter/pantalla staff no localizados. Activar no acredita pago. |

Las seis capacidades Media y las dos rutinarias de acreditación/fuentes tienen consumidores y UI originales. Las cuatro `profile_*` siguen sin consumidor genérico encontrado; las dos proveedoras tienen backend sin pantalla staff localizada; las dos de correcciones tienen consumidor con restricción de clase que excluye ADVISOR. Por ello **16 delegables no equivale a 16 funciones utilizables por un asesor**. Un fixture puede mostrar grants históricos/asignados de catálogo sin presentarlos como acceso efectivo. En vista Director, diez checkboxes admiten edición simulada: seis Media, dos rutinas acreditación/fuentes y dos servicios backend de organizaciones. Los cuatro genéricos de perfiles y los dos de correcciones permanecen sólo lectura; los grants simulados preexistentes siguen visibles. Esto no cambia lo que el backend de gobierno puede almacenar ni los gates de cada consumidor.

Separadas de los checkboxes: `internal_advisors_manage` (`internal_governance`) y `profile_initial_ownership_approve` (`profile_ownership`), ambas `kind=governance`, `delegable=false`. «Todas las elegibles» no las incluye. No hay UI de nombramiento Master, delegaciones, recuperación extraordinaria, ownership ni autoridad clínica.

## Contrato futuro: reutilizar, no implementar

| Pieza existente | Reuso y frontera |
|---|---|
| [CanonicalHttpSessionResolver](../../modules/identity/http/CanonicalHttpSessionResolver.php) / [SessionService](../../modules/identity/services/SessionService.php) | Sesión canónica actual, cuenta y versión de credencial; ningún ID/cookie del prototipo es autoridad. |
| [InternalGovernanceService::execute](../../modules/identity/services/InternalGovernanceService.php) | Único writer para el futuro subconjunto aprobado `register`, `suspend`, `reactivate`, `grant`, `revoke`. No implementar writer paralelo. |
| [InternalOperatorGrantRepository::activeCapabilities](../../modules/identity/repositories/InternalOperatorGrantRepository.php) | Relee grants vigentes sin caché, une cuenta activa/staff ACTIVE, filtra catálogo conocido. `forUpdate=true` exige transacción propia activa. No es un directorio staff ni reader de grants/historia administrativa completo. |
| [InternalOperatorAuthority](../../modules/identity/services/InternalOperatorAuthority.php) | Contexto backend, actor real/efectivo canónico y capacidades vigentes; cada consumidor conserva sus gates. No da autoridad sólo por clase. |
| [CanonicalAuditWriter](../../modules/platform/services/CanonicalAuditWriter.php) | Evento `INTERNAL_GOVERNANCE_CHANGED` en la transacción del comando, no una nueva base de historia. |
| [API GOV actual](../../api/identity/governance.php) | Cases/MFA/recuperación; no expone `InternalGovernanceService::execute` para gestión staff. |

### Validación del futuro adapter

Debe vivir en la composición/workspace existentes, con método explícito y **POST para mutaciones**, CSRF, same-origin y respuestas privadas/no-store. Reutilizar sesión canónica sin aceptar actor/clase/grants como verdad del cliente. Definir allowlists de acciones y capacidades exactas; no aceptar wildcards, SQL ni clases arbitrarias.

Cada petición revalida sesión/cuenta/credencial/staff, target permitido y autoridad actual. El registro exige cuenta target activa; otros comandos actuales no aplican todos esos filtros al target, por lo que grant almacenado y uso efectivo deben distinguirse y el adapter futuro debe definir su validación sin cambiar silenciosamente el servicio. DIRECTOR conserva su contrato raíz, incluidos grant/revoke operativos explícitos propios; este prototipo no representa un target Director propio. MASTER_ADMIN requiere ACTIVE + `internal_advisors_manage`, target ADVISOR distinto de sí mismo y, para grant/revoke, delegación exacta de capacidad delegable. El futuro adapter de asesores limita targets a ADVISOR y excluye autogestión; no amplía las operaciones raíz del servicio. «Agregar» resuelve una cuenta legítima existente y activa, nunca crea credenciales. Suspender cambia sólo estado interno; reactivar no repone grants revocados.

La delegabilidad del catálogo no elimina gates de consumidor (correcciones, MFA, casos, recursos y activación). Staff, médico acreditado, owner y operador Agenda permanecen separados. La UI no atribuye permisos clínicos ni usa datos de pacientes.

### Transacciones, concurrencia y guardado múltiple

`execute` resuelve sesión, **rechaza una transacción exterior**, inicia su propia transacción, bloquea cuentas/staff de actor y target en orden estable, revalida autoridad, ejecuta **un comando**, añade auditoría al mismo PDO y hace commit; excepción revierte esa transacción. Devuelve cuenta/clase/estado, no un snapshot completo ni resultado versionado por grant. No se encontró batch, CAS/versionado de conjunto ni idempotencia explícita para este servicio.

Por tanto **no hay garantía existente de guardado atómico multigrants**. Antes de integrar la interacción «Guardar», diseñar y aprobar:

1. Reader autorizado con snapshot/versiones de cuenta/staff/grants/delegaciones/catálogo y validación de versión antes de actuar; la guarda de versión debe tener semántica transaccional real, no sólo comparar en JS antes del comando.
2. Plan explícito de comandos individuales con confirmación de concesiones/retiros y resultado por comando. Si falla uno, mostrar los ya confirmados y pendientes; no declarar éxito total ni prometer rollback global.
3. Ante conflicto o pérdida de ACK/resultado de commit incierto, detener reintentos, refrescar estado canónico y reconciliar intención vs realidad. No reenviar ciegamente grants ni compensar con revocaciones automáticas; una recuperación explícita vuelve a validar autoridad vigente.
4. Rebase de cambios todavía deseados sobre snapshot actual y nueva confirmación humana. Un grant/delegación revocado mientras se edita no puede recuperarse desde el snapshot antiguo del navegador.

Estas son obligaciones del adapter futuro, no garantías implementadas por el prototipo ni un nuevo writer autorizado. El conflicto visual del prototipo rechaza su propuesta completa en memoria; no representa la semántica transaccional del futuro batch de comandos reales.

### Readers, privacidad e historia pendientes

No se localizó directorio staff paginado, adapter de búsqueda/alta, snapshot administrativo versionado ni endpoint de historial staff conectado. [AuditReadService](../../modules/platform/audit/read/AuditReadService.php) existe como lector genérico dormido; no acredita una vista de historia de asesores operativa. El origen futuro sigue siendo gobierno/auditoría canónicos, con proyección minimizada, no otra tabla de historia.

El diseño futuro debe limitar búsqueda/paginación y campos por actor; impedir enumeración general de personas/cuentas y filtraciones de existencia fuera del ámbito autorizado. No devolver credenciales, cookies, factores, documentos privados ni información de otras cuentas. Revisar permisos del propio reader, estados ausentes/suspendidos/revocados, actividad histórica y política de retención antes de conectarlo. No inventar emails de cuentas reales en fixtures.

## Fixtures y estados de revisión

Todos los nombres son ficticios, definidos en el prototipo, sin consulta a Identity:

| Fixture | Estado inicial / finalidad |
|---|---|
| Aurora Naranjo | Activo, un permiso: ejemplo A. |
| Bruno Lago | Activo, tres permisos independientes: ejemplo B. |
| Cora Vela | Activo, las 16 claves operativas asignadas en memoria, incluidas seis sólo lectura; no implica flujos activos ni poderes de gobierno: ejemplo C. |
| Diego Roble | Activo, cero permisos: ejemplo D. |
| Elisa Monte | Suspendido, conserva dos permisos y permite previsualizar reactivación: ejemplo E. |
| Mar Solano | Cuenta ficticia usada para demostrar denegación de autoadministración; ningún perfil real. |

En «Escenarios de revisión», Master con delegación parcial permite sólo `media_review_read`, `media_review_approve` y `physician_accreditation_review` entre los checkboxes elegibles; sin autorización de gestión todo queda deshabilitado. Son contextos simulados, no identidades/clases emitidas por servidor. El fixture de todas las claves no concede edición a un Master fuera de su delegación.

La selección cubre G; edición/revertir H; confirmación I; feedback concesión/retiro J; conflicto K; directorio vacío/loading/error L; búsqueda sin resultado M; capacidades sin flujo sólo lectura N; historia ficticia O. El guardado, suspensión/reactivación y cambio de selección con borrador requieren confirmación. La QA siguiente debe comprobar estos estados, no deducirlos sólo de su presencia en fuente.

## QA y evidencia del prototipo

**Resultado: PASS del prototipo sintético.** Ocho recorridos Chromium/WebKit, cuatro viewports por motor y **728 comprobaciones**. El [resumen ejecutable](../../prototypes/ase-vis01/evidence/browser/summary.json) registra estados, peticiones, resultados y SHA-256 del candidato. Se probó antes del commit: `commit_at_test` es el HEAD de preflight, no un supuesto commit de implementación. Los digests de UI, catálogo, servidor y QA se contrastan con el árbol final antes de publicar; el hash exacto de entrega se informa en el cierre Git.

| Verificación requerida | Evidencia / estado |
|---|---|
| Chromium y WebKit, `1440×900`, `1366×768`, `820×1180`, `390×844` | PASS: ocho recorridos, 91 checks cada uno; 40 capturas de matriz, confirmación, historia simulada, conflicto y error. |
| Estados A–O y directorio loading/error | PASS: uno/tres/todos/cero grants, suspensión, autoridad/delegación exactas, selección, borrador, concesión/retiro, conflicto/refresh, vacío/sin resultados, futuro sólo lectura e historia ficticia. |
| Sin overflow, targets, teclado/foco, labels/checkboxes, status y confirmación | PASS DOM/teclado: targets >=44px, labels/descripciones asociados, foco modal y retorno tras cancelar/guardar/revertir/suspender/reactivar/conflicto. Smoke móvil sólo `INTERIM_MOBILE_SMOKE_ONLY`; sin auditoría física con lector de pantalla. |
| Catálogo exportado idéntico a `all()` | PASS: exportación PHP pura reproducible, 18 definiciones exactas y 16 checkboxes independientes; dos autoridades fuera de la matriz. |
| Sólo GET/HEAD de archivos estáticos; sin API/persistencia | PASS: navegador sólo solicita GET locales allowlisted, sin storage/cookies ni escrituras. CSP bloquea conexiones/formularios; servidor rechaza POST con 405 y rutas de aplicación/repositorio con 404. |
| Rollback y source/original runtime preservados | PASS: abrir/detener sólo servidor propio 8141 libera ese puerto y conserva 8140; recargar descarta simulaciones. 2.992 archivos fuente idénticos, HEAD/branch/worktree originales intactos y listeners 8443/8091/8092/6385 con los mismos procesos. |
| Safari físico del Director | `NOT_RUN`; revisión visual pendiente |

Capturas: [desktop Chromium](../../prototypes/ase-vis01/evidence/browser/chromium-1440x900-directory-matrix.png), [tablet WebKit](../../prototypes/ase-vis01/evidence/browser/webkit-820x1180-directory-matrix.png), [móvil WebKit](../../prototypes/ase-vis01/evidence/browser/webkit-390x844-directory-matrix.png). [Aislamiento/rollback](../../prototypes/ase-vis01/evidence/isolation.json), [enlaces de contexto](../../prototypes/ase-vis01/evidence/context-links.json) y [toda la evidencia](../../prototypes/ase-vis01/evidence/). Durante QA se corrigieron el ciclo de Tab/Shift+Tab modal, retorno de foco tras render, una carrera de reapertura rápida y altura nativa de selectores WebKit. La regresión de reapertura confirma que un evento `close` anterior no cancela la siguiente confirmación.

**Diagnóstico de herramienta de captura:** el driver Playwright instalado inyecta un estilo vacío `body {}` al capturar WebKit; la CSP estricta lo rechaza. Se conservaron cinco avisos por viewport WebKit, cada uno correlacionado con esa mutación exacta durante la captura. El resumen conserva ruta/líneas/hash del driver y diagnósticos; otros errores de consola, script o CSP siguen siendo fatales. No se modificó librería, servidor ni CSP para eludirlo. No hubo errores de aplicación en los recorridos. Esto no acredita aceptación física Safari.

Las pruebas cubren mocks, no ejecutan las suites SQL de gobierno ni acreditan una nueva regresión clínica. La evidencia histórica ASE-ALIGN01 V2 permanece en IW01, incluido el caveat AUTH02A del README; no se cambia ni relaja ese contrato.

## Preview, expiración y descarte seguro

Preview verificado y conservado: `http://127.0.0.1:8140/`, sólo loopback, separado de 8443/8091/8092. Expira `2026-10-25T00:00:00Z`; después no debe servir el prototipo sin reapertura explícita. El servidor propio sólo permite rutas estáticas autorizadas, nunca expone el repo completo, PHP ni secretos. No usa cookies como autoridad ni se conecta a datos reales. Puerto real 8140; PID actual en `/tmp/mxmed-ase-vis01-preview.pid`, comando/log propios en `/tmp/mxmed-ase-vis01-preview.log`. La entrega informa el commit servido tras publicar.

Reabrir desde **el worktree del prototipo**, con puerto libre >=8140 y nueva expiración explícita cuando corresponda:

```sh
cd /Users/circulodigital/Documents/GitHub/mxmed-ase-vis01-prototype
python3 prototypes/ase-vis01/serve.py --port 8140 --expires 2026-10-25T00:00:00Z
```

Detener únicamente el PID registrado del `serve.py` propio después de confirmar su comando; nunca detener listeners originales por número de puerto. Cerrar la pestaña o reiniciar la página descarta estado simulado. Para retirar archivos locales, primero detener ese proceso, comprobar worktree limpio y rama publicada y retirar **sólo** `/Users/circulodigital/Documents/GitHub/mxmed-ase-vis01-prototype` con `git worktree remove`; conservar el commit/branch para revisión. No tocar/resetear/checkout la rama fuente ni el checkout hermano. No hay cambios reales de permisos que deshacer.

## CTX-SYNC01 y siguiente gate

| Campo | Alcance |
|---|---|
| `CONTEXT_BASELINE` | `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; mapas CTX-MAP02/03 e IW01/ASE-ALIGN01 conservados. |
| `AFFECTED_CAPABILITY_IDS` | ID-07 y COM-10 como navegación contextual; las 18 claves de catálogo anteriores sólo se representan. ID-04/05, PUB-06 y MED quedan como consumidores referenciados, sin cambio funcional. |
| `AFFECTED_JOURNEY_IDS` | `NONE` en ejecución: J01/J02/J03/J08/J09 sólo referenciados para límites de consumidores; no modificados ni ejecutados. |
| `AFFECTED_DATA_OBJECTS` | `NONE` modificado; `internal_staff`, `internal_operator_grants`, `internal_capability_delegations`, `auth_accounts` y auditoría sólo contrato futuro, sin lectura de filas ni SQL. |
| `AFFECTED_CONTEXT_FILES` | Este contrato, registro visual, estado compacto, párrafo sucesor COM-10 e IW01. |
| `CONTEXT_CHANGES_IMPLEMENTED` | Prototipo aislado y gate explícito; no UI administrativa real, adapter, activación ni nuevos counts del inventario. |
| `CONTEXT_VERIFICATION` | Fuente/contrato/catálogo contrastados; 728 checks browser, capturas, aislamiento/rollback y enlaces locales verificados. Antes del push se cotejan los digests del candidato con el árbol del commit. |
| `CONTEXT_SYNC_STATUS` | `PASS_UPDATED`: contrato, registro, estado y sucesores contextuales contrastados con el prototipo, QA y diff; enlaces/anchors válidos. |
| `DOCUMENTATION_COMMIT` | Hash del commit de prototipo/documentación en reporte final; no autoinsertar hash futuro en su propio contenido. |
| `REMAINING_CONTEXT_GAPS` | Aprobación visual, integración autorizada y adapter/readers/historia/versionado seguro futuros; operación real separada. |

Siguiente autorización: revisión y aprobación visual del Director de esta versión. Sólo después procede solicitar alcance de integración al workspace existente y su adapter canónico; no hay autorización operacional por publicar este prototipo.
