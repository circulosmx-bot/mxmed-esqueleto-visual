# ASE-VIS01 — prototipo reversible de asesores y permisos

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
