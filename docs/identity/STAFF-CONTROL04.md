# STAFF-CONTROL04 — incorporación y revisión entre interfaces

Baseline: `8d0dfef72d45253fc9827e22a1f451f6abd3c981`. Rama aislada `feature/mxmed-staff-control04-cross-surface`. Solicitud expresa del Director: incorporación, acceso manual, quince áreas de soporte e índice de seis interfaces. UI-2 funcional y UI-3 acotada al índice solicitado; aprobación visual PENDING. Sin promoción de 8091.

## Incorporación

`PRODUCT_POLICY`: preparar, invitar, confirmar datos, revisar identidad, admitir y conceder facultades son decisiones distintas. `PREPARING` con ausencia de binding representa preparación/invitación; la invitación conserva ACTIVE/CONSUMED/REVOKED/EXPIRED y fecha propia. `PERSON_CONFIRMED` no implica admisión. Sólo `auth_staff_admission_completions` junto con `internal_staff.status=ACTIVE` acredita alta. La UI muestra candidatos pendientes en el directorio predeterminado y conserva el filtro de pendientes. Revocar preparación conserva versiones e historia.

`SOURCE_IMPLEMENTATION`: [Geography](../../modules/identity/StaffAdmission/Geography.php) adapta `catalog_cp_colonias`, la fuente que consulta Consultorio. El catálogo actual modela **municipio**, no un catálogo separado de ciudades. La UI lo identifica como ciudad/municipio. Estado y municipio son las claves naturales existentes; `catalog_cp_colonias.id` identifica una colonia postal, no un ID de ciudad. No se inventan códigos ni se duplica el catálogo. Selección dependiente, vaciado de ciudad al cambiar estado, validación exacta en servidor y persistencia en las mismas versiones personales. No se modifica Consultorio. La importación de QA reutiliza el dump geográfico versionado y la normalización del importador existente: 32 estados y 2,478 municipios; no extrae personas ni bases reales.

[Intentions](../../modules/identity/StaffWorkflow/Intentions.php) guarda revisiones inmutables en `auth_staff_capability_intentions`, ligadas al caso, versión personal, referencia de destinatario, catálogo y Director actuales. Lista vacía revoca las intenciones anteriores. No escribe grants. La activación explícita individual posterior utiliza `InternalGovernanceService`, revalida cuenta vinculada, admisión, revisión positiva vigente, versión actual, estado staff y catálogo bajo la transacción del writer. No activa automáticamente una configuración anterior. Los recibos `auth_staff_intention_activations` conservan la relación con el evento canónico y evitan que un replay reactive un grant revocado después. La matriz normal de permisos sigue siendo una autoridad independiente; las intenciones no limitan las decisiones posteriores del Director.

`CONNECTIVITY`: sólo `staff-control04-router.php`, después del preflight owned existente, habilita `MXMED_STAFF_CONTROL04`. El endpoint normal continúa cerrado. La migración se instala únicamente en la base nueva desechable; su archivo no autoriza DDL real.

## Simulación

El botón «Simular incorporación» encola una solicitud exacta en el directorio privado de la instancia. El worker local usa registro/verificación Identity canónicos con sink inerte y una sesión Chromium independiente del solicitante. Acepta la invitación exacta, confirma los datos y presenta imágenes geométricas artificiales por los consumidores existentes. No copia cookies del Director ni crea staff por SQL. Un correo que ya existe no se simula ni se toma prestado. La misma solicitud no se reenvía al pulsar varias veces. Una incertidumbre se detiene para conciliación.

El resultado del worker es `REVIEW_REQUIRED`, no ACTIVE. El Director consulta las imágenes, acepta evidencia y aprueba admisión con sus acciones, contraseña y MFA vigentes. Después revisa y guarda las intenciones para la versión confirmada y concede cada permiso explícitamente. Esta separación preserva autoría y no afirma verificación real de una persona.

## Matriz de soporte y autoridades

`D` = Director; `M` = Master; `A` = Advisor; `O` = owner médico; `C` = colaborador/operador. En todos los casos, sesión vigente, recurso exacto y restricciones del dominio se revalidan en servidor. D/M no heredan facultades de uso por su clase. M sólo puede delegar claves de su ámbito; A sólo usa grants efectivos donde el consumidor admite su clase. O/C no se convierten en staff. Una pestaña visible no concede acceso.

| Área | Fuente, reader y writer existentes | Capacidades y autoridad D/M/A | Autoridad O/C | Conexión, pendientes, audit e impacto público |
|---|---|---|---|---|
| Resumen | `PhysicianRequests/PendingSummary`, `Media/AdvisorWorkspaceService`; sin writer | `physician_pending_summary_read` independiente; ámbito previo Media/Requests, no amplía cartera | Sin acceso staff por membresía | Conectado; contadores accionables/visibles separados; sólo lecturas, sin publicación |
| Solicitudes | `PhysicianRequests/RequestService`, `PublicEnrollmentService`, `ReviewedEnrollmentService` | `physician_request_read`, `physician_request_evidence_read`, `physician_claim_resolve`, `physician_new_profile_resolve`; grants exactos, revisión/MFA por decisión | Solicitante exacto tiene continuación propia; no autoridad staff | Conectado; origins/decisiones/respuestas; audit de solicitudes; aprobación no publica ni asigna owner automáticamente |
| Perfil | `PublicProfileController/Repository`; API privada y servicios de fuentes gobernadas | `profile_create`, `profile_curate`, `profile_publication_manage`, `profile_support_assist` no tienen consumidor general staff conectado | Resolver médico, perfil y writer exactos; owner no equivale firma | Consumidor de soporte faltante; no guarda simulada, ni pendientes inventados. DTO público conserva sus gates |
| Acreditación | `PhysicianEvidenceService`, `ProfessionalAuthorityIssuer`, `EvidenceAuthority`; fuentes `VerifiedPhysicianIdentityService`/`VerifiedDoctorCredentialService` | `physician_accreditation_review`, `professional_source_provision`; casos/reauth/MFA y límites de clase propios; no grant universal | Solicitante/acreditación exactos; no se hereda de owner/C | Consumidor original preparado fuera de esta composición; falta conexión segura al workspace. Audit de acreditación, sin ownership/publicación automática |
| Consultorios | `ConsultoriosController/Repository` y DTO público | No reader/writer staff seguro localizado; ninguna clave amplia añadida | Contexto médico y consultorio exacto; límites originales preservados | No conectado; catálogo geográfico compartido no concede acceso a consultorios. Sin pendientes/audit nuevos |
| Servicios y atención | `PublicProfilePlanCapabilities`, fuentes Agenda/Subscriptions y DTO público | No consumidor interno de edición | Entitlements y contexto médico, no permisos derivados del plan | No conectado; no se crean servicios ni datos clínicos |
| Imágenes | `AdvisorWorkspaceService`, `MediaReviewBatchService`, servicios de aprobación y archivos privados | `media_review_read`, `media_review_approve`, `media_review_source_download`, `media_review_corrected_upload`, `media_review_improve`, `media_review_request_replacement`; claves independientes | Upload médico separado; C no hereda facultades de revisión | Conectado; submissions/REVIEW; audit Media canónico. Aprobación publica derivado permitido, no publica el perfil oculto |
| Agenda | `ConsultoriosController`, `AvailabilityController`, gateway Agenda | Sin reader diagnóstico staff autorizado conectado | O por contexto/entitlement; C sólo cinco grants exactos Agenda | No conectado; no exposición de pacientes, encounters, recetas o documentos. No métricas ni disponibilidad inventadas |
| Operadores y colaboradores | `ProfileOperatorService`, `OperatorPresentationRepository`, `OperatorPanelReadiness`, `OperatorHttp` | No consumidor diagnóstico interno autorizado; no asignación de plaza para soporte | O administra; C acepta vínculo propio y usa `agenda.appointments.read/create/reschedule/cancel`, `agenda.availability.read` | No conectado; preserva owner + dos plazas. Invitations/memberships/grant revisions/audit existentes; sin efecto público implícito |
| Plan y servicios contratados | `CurrentSubscriptionReadModelService`, `CurrentSubscriptionRepository`, `PublicCommercialPlanSource`; `CreateSubscriptionWithAcceptanceService` | No reader/writer staff de plan médico conectado; `provider_subscription_activate` aplica a otro dominio | Contexto médico/entitlement exacto, C no hereda poder comercial | No conectado; no fechas de renovación, pagos o términos inventados. Los gates de visibilidad siguen la fuente comercial |
| Facturación y pagos | `PatientBillingProfilesService`, `InvoiceDraftService`, `InvoiceRepository`, fuentes Subscriptions/Stripe | Sin consumidor fiscal interno; clase/grant Agenda no lo concede | Contexto médico + relación/paciente/documento exactos; C sin grants fiscales operativos | No conectado; sin registros financieros ficticios, PAC, refunds/capturas o reconciliación simulada. Auditoría fiscal existente permanece separada |
| Opiniones | `PublicProfilePlanCapabilities`/DTO público; sin writer de moderación staff acreditado | Ningún permiso nuevo | Publicación sólo según fuente existente | No conectado; no reseñas ni contadores artificiales |
| SEO | `PublicProfileController`, guards de publicación y metadata | Sin consumidor editor staff | Fuente pública derivada del perfil elegible | No conectado; sin edición de schema ni publicación indirecta |
| Soporte de cuenta | Identity `current-session`, `SessionService`, `CredentialAuthenticationService`; diagnóstico de sesión propia | Ningún reader general de cuentas médicas staff autorizado | Cada cuenta autentica su propia sesión | Soporte de terceros no conectado. Nunca expone contraseñas, sesiones, semillas MFA ni impersonación |
| Historial | `PhysicianRequests/HistoryReader`, actividad Media y `Team/ActivityProjection`; writers de cada dominio | Mismos grants/ámbitos que sus fuentes; no lector universal de auditoría | Historia médica/operador separada bajo su contexto | Conectado para Requests/Media; conserva actor/eventos; leer no muta ni publica |

Los nombres de servicio son mapas a la fuente, no pruebas de activación operacional. Los contratos [WORKSPACE03](ASE-ADVISOR-WORKSPACE03.md), [Operadores](OP-IMP01_OPERATOR_FOUNDATION.md), [matriz fiscal](../contexto/STRIPE_Y_SERVICIOS_EXTERNOS_MXMED.md) y [router](../README.md) mantienen los límites completos.

## Índice de interfaces

Composición persistente durante la revisión, compacta y colapsable, dentro del flujo con sticky y scroll propio. Los diálogos nativos conservan su top layer. `/development/interfaces.php` es la entrada del índice owned; el archivo interno rechaza acceso directo sin composición. No se emite en producción.

| Interfaz | Ruta descubierta | Estado en esta instancia |
|---|---|---|
| Director | `/internal/media-review/index.php?section=equipo` | Disponible para Director autenticado |
| Asesor | `/internal/staff-workspace/index.php` | Disponible con sesión independiente y grants efectivos |
| Usuario médico | `/private-app.php` | Fuente existente; contexto médico no habilitado aquí |
| Operador de usuario médico | `/private-app.php`, consumidor `p-ag-operadores` para administración owner y workspace Agenda según contexto | Fuente existente; no se crea aplicación ni relación para completar el índice |
| Perfil público | `/profiles/doctor.php` | Fuente existente; ruta pública completa no habilitada en esta instancia |
| Proveedor | `/provider/index.php` | Portal existente; relación/servicio organizacional no habilitados aquí |

Alcance entre superficies `PARTIAL`: Director→Advisor y decisión Media→estado/historial canónicos se verifican donde existen consumidores; owner→operador, administración médica→perfil completo, pagos y proveedor requieren sus composiciones/contextos y quedan explícitamente no verificados. No se redirigen entradas indisponibles a pantallas ajenas.

## Verificación y operación

`VERIFICATION=PASS_TARGETED_SYNTHETIC`. `OPERATIONAL_STATUS=OWNED_SYNTHETIC_ONLY`. Sin cuentas reales, correos externos, cambios al original/8091, merge, despliegue ni activación productiva. Safari físico NOT_RUN; móvil es smoke interino. La aceptación visual del Director sigue pendiente.

La prueba manual sobre la preview WORKSPACE03 saludable logró dos logins y logouts en Chromium con la contraseña obtenida por el comando documentado y comprobación del portapapeles. `/tmp` y `/private/tmp` resolvieron al mismo entorno. Posteriormente se reprodujo el fallo entrando sin `next`: la autenticación era válida pero el retorno predeterminado exigía un perfil médico y mostraba `professional_access_required`. El retorno default Director y la ruta del solicitante se corrigen sólo cuando la composición owned emite el marcador de revisión sintética; la aplicación médica conserva su gate. `circulodigital@msn.com` y su contraseña real no se importan a la base aislada.


## Evidencia de cierre — 20 de octubre de 2026

53 comprobaciones registradas PASS: [principal](../evidence/staff-control04/qa.json) (14), [login, geografía, autoridad y responsive](../evidence/staff-control04/supplement.json) (24), [concurrencia, simulación y replay](../evidence/staff-control04/boundaries.json) (9) y [regresión acotada de Solicitudes](../evidence/staff-control04/requests-smoke.json) (6). No se repitieron suites históricas. El primer intento del ensayo principal adelantó la reapertura del diálogo a la finalización visible de la simulación; se corrigió la espera del test y se retomó el caso canónico, sin reset ni duplicación. El ensayo adicional completó una segunda simulación desde el botón sin recuperación. La inscripción MFA del Asesor fue rechazada correctamente antes de recibir una facultad elegible; se realizó después del grant explícito por UI, con la ceremonia existente y registrador independiente.

La fuente PHP/JS/Python modificada pasa sintaxis. Las capturas de Chromium/WebKit cubren 1440×900, 1366×768 y 390×844, sin overflow horizontal ni errores JS. [Manifiesto final](../evidence/staff-control04/final-verification.json) liga hashes finales, evidencia y preservación de worktrees/listeners protegidos. No acredita Safari físico, aceptación visual ni activación real.

Primero se comprobó que la aprobación Media de Aurora no cambiaba su estado hidden. Después, el fixture canónico WORKSPACE02 preparó solicitudes y cambió expresamente Aurora a active/FREE como dato de ensayo; ese cambio **no** es efecto de la aprobación Media. El Asesor realizó una solicitud de aclaración con contraseña/MFA, reflejada en Requests e Historial y fuera de su cola accionable mientras espera al solicitante. No se ensayó un flujo público completo.

## Guía de revisión y acceso

Instancia owned nueva: `/tmp/mxmed-ase-adm02imp01-5y4ngn1r`; sólo HTTPS loopback. La anterior ya no existía. Vence **2026-10-21 09:25:58 UTC−06:00 (America/Mexico_City)**, sin extensión artificial de sesiones Identity ni de la expiración del runner.

- Director: `https://127.0.0.1:50439/internal/media-review/index.php?section=equipo`.
- Asesor: `https://127.0.0.1:50439/internal/staff-workspace/index.php`.
- Índice: `https://127.0.0.1:50439/development/interfaces.php`, también colapsable en ambas pantallas.
- Director sintético: `qa_director@example.test`; Asesor: `oper09_advisor@example.test`. Usa las ventanas independientes del launcher; no comparte cookies ni cambia de rol.

Desde el worktree de esta rama:

```sh
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r open
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r status
```

Para login manual, pulsa «Cerrar sesión» y abre `/public/identity/acceso.php` en la ventana del Director. Ejecuta el comando siguiente, pega el portapapeles en Contraseña e introduce su correo sintético. Para el Asesor selecciona el enlace «Asesor» en la guía visible del login y usa `credentials-advisor` en su propia ventana. Nunca uses tu contraseña habitual.

```sh
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r credentials-director
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r credentials-advisor
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r mfa-director
python3 scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r mfa-advisor
```

Los comandos copian sólo el secreto/código sintético requerido al portapapeles. Cada acción protegida requiere un código vigente no consumido; espera el siguiente intervalo si acabas de utilizar uno. No se almacenan contraseñas ni semillas en Git.

Revisión sugerida: Lucía Ficticia Pendiente muestra preparación e intención sin cuenta staff; su invitación tiene vigencia canónica propia de una hora. Si vence antes de revisarla, consulta su estado y prepara la invitación de nuevo mediante el control existente, sin modificar TTL. Candela Ficticia Revision ya tiene datos confirmados y evidencia artificial, pendiente de revisión/admisión por el Director. Irene Ficticia Oper09 está admitida y tiene dos grants Media confirmados, más cuatro de Requests/resumen concedidos explícitamente durante el smoke; no recibe permisos clínicos ni comerciales. En Asesor selecciona Aurora para ver Solicitudes/Imágenes/Historial y las quince pestañas. Las demás áreas indican con precisión su consumidor faltante.

Para cerrar todos los servicios de esta revisión, desde cualquier directorio:

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-staff-control04-cross-surface/scripts/staff-control04-review.py /tmp/mxmed-ase-adm02imp01-5y4ngn1r stop
```

El comando solicita cierre al runner propietario; éste elimina sólo su infraestructura desechable. No termina listeners por número de puerto ni toca 8091. Tras el vencimiento no se reutiliza esta sesión: el script `python3 scripts/staff-control04-qa.py --keep-review` crea una instancia sintética nueva y anuncia su directorio/URL, conservando el preflight de aislamiento.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=WORKSPACE03 8d0dfef72d45253fc9827e22a1f451f6abd3c981
AFFECTED_CAPABILITY_IDS=ID-01,ID-02,ID-03,ID-07,MED-01,MED-02,MED-03,COM-10; OP-01..03 y ORG-01..03 sólo mapeados
AFFECTED_JOURNEY_IDS=J01,J02,J04,J09; J07,J08 fronteras sin activación
AFFECTED_DATA_OBJECTS=catalog_cp_colonias; casos/versiones/bindings/completions staff; auth_staff_capability_intentions; auth_staff_intention_activations; internal_operator_grants; audit; decisiones Requests/Media
AFFECTED_CONTEXT_FILES=ESTADO_ACTUAL_MXMED; README; MXMED_REGISTRO_CONTRATOS_VISUALES; INVENTARIO_FUNCIONAL_MXMED; INTERACCIONES_MXMED; DATOS_Y_MIGRACIONES_MXMED; TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED; identity/STAFF-CONTROL04
CONTEXT_CHANGES_IMPLEMENTED=política, fuente, conexión, QA y operación separadas; catálogo/ledgers/login/simulación/matriz/índice y límites sincronizados
CONTEXT_VERIFICATION=paths y anchors pertinentes, símbolos de matriz, diff completo, hashes de fuente y resultados dirigidos
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega final
REMAINING_CONTEXT_GAPS=cuatro superficies sin contexto operativo en esta instancia; consumidores amplios no conectados; Safari físico no probado; aceptación visual pendiente
```
