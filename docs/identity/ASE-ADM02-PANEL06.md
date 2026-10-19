# ASE-ADM02-PANEL06 — usabilidad y perfil personal del equipo

Base: PANEL05 `e4f4fb046acba6a75f20682d772cc2d4a197e8a8`, limpio y coincidente con origin al abrir el worktree aislado `mxmed-ase-adm02-panel06`. Rama `feature/ase-adm02-panel06-final-usability`. Esta entrega sucede las limitaciones de interacción/perfil de [PANEL05](ASE-ADM02-PANEL05.md); conserva su asistente, búsqueda, categorías y restricciones.

## PRODUCT_POLICY

Solicitud explícita PANEL06: corrección UI-2 del panel existente, perfil personal contextual, fotografía privada/avatar local y login/logout Identity; exclusivamente infraestructura propia y datos ficticios. No autoriza alta real, correo externo, aplicación de DDL a bases existentes, exposición pública, cambio de MFA ni ampliación de autoridad Master/Advisor. La aceptación visual sigue pendiente del Director.

## SOURCE_IMPLEMENTATION / CONNECTIVITY

- Diálogos nativos: borde relativo a viewport/dvh, desplazamiento interno y controles alcanzables; backdrop con pointerdown y click ambos fuera, Escape y botones pasan por el controlador de dominio. El bloqueo de scroll cuenta diálogos abiertos, preservando anidamiento. Cambios personales/asistente piden confirmación de descarte; versiones ya guardadas se conservan. Confirmaciones de permisos no descartan el borrador exterior.
- Recuperación: actualización personal, carga de retrato y preparación conservan identificadores de operación por actor; sólo identificadores, nunca datos personales ni secretos, en sessionStorage. Los recibos canónicos resuelven respuestas perdidas antes de permitir reintento explícito. La fotografía huérfana cifrada se conserva y bloquea una repetición incierta. Cerrar durante una petición no cancela ni repite la escritura. Casos Master inciertos pueden ocultarse y retomarse sin reutilizar prueba ni ejecutar de nuevo; su gate sigue cerrado en el ensayo.
- `StaffAdmission::Service` mantiene el único writer de perfil: `update` admite `changes` con lista cerrada, valida sólo campos enviados y fusiona la versión previa bajo lock/expected_version. Confirmación/corrección histórica conserva campos nuevos. Cada edición crea versión inmutable, procedencia y auditoría y vuelve a requerir confirmación personal; no concede acceso ni modifica credenciales.
- Género explícito sin inferencia, teléfono de contacto E.164 **no verificado**, avatar opcional y preferencia de presentación viven en `auth_staff_admission_versions`. El correo se lee de `auth_accounts` dentro de la relación autorizada; no puede enviarse como campo de edición. No se lee ni copia el contacto cifrado de expedientes médicos C4: no es una fuente autorizada de contacto staff. No se crea sistema alternativo de identidad.
- Retrato: mismo `StaffMedia::Service`, validación real PNG/JPEG, optimización de imagen existente, cifrado/almacenamiento privado, auditoría, versiones y recibos. Composición aislada opta explícitamente por permitir Director ACTIVE → caso completado de staff no REMOVED y cuenta activa. `account_id` conserva al sujeto; `source_actor_account_id` identifica al editor. La autoridad por defecto permanece propia del solicitante; Master no obtiene acceso a retratos de otros integrantes. Evidencia documental sigue propósito separado y MFA; nunca se usa como fotografía. Los tres iconos SVG predefinidos son locales e independientes del género; fotografía/avatar/placeholder tienen elección explícita reversible.
- Login: router aislado habilita sólo página/assets originales de Identity y login/current-session/logout, sin rutas de registro/recuperación ni proveedores. Logout canónico revoca sesión, vacía estado visible y redirige a login con destino permitido. BFCache y señales de cierre revalidan sesión antes de mostrar contenido retenido. No hay contraseña prellenada ni auto-login posterior a logout. MFA de operaciones sensibles se conserva; no se introduce una política nueva de MFA al login.

## Diagnóstico exacto de permisos

Las 16 claves ya pertenecían al catálogo canónico y tenían writer. No se encontró desconexión entre las diez claves editables del panel y sus consumidores; PANEL06 conserva su persistencia/enforcement y corrige el texto de indisponibilidad. No afirma seis nuevas funciones ni diez implementaciones nuevas.

Para las diez claves siguientes, Director puede administrar un Advisor ACTIVE, no propio y con cuenta activa. Master requiere gestión y delegación exactas; el grant independiente no implica delegación. Cada consumidor mantiene además sus requisitos de recurso/ámbito, sesión y, cuando corresponde, MFA/auditoría.

| Grupo / clave editable | Consumidor existente y enforcement |
|---|---|
| Acreditación / `physician_accreditation_review` | `EvidenceAuthority::reviewer` y `PhysicianOnboardingHttp`: staff ACTIVE, cuenta/credencial vigente, grant exacto, no autorrevisión. |
| Acreditación / `professional_source_provision` | `EvidenceAuthority::sourceReviewer`: grant independiente, staff ACTIVE, ámbito/caso y controles de fuentes profesionales. |
| Medios / `media_review_read` | `MediaReviewAuthority::requireRead`, contexto canónico de operador y AuthorizationBoundary. |
| Medios / `media_review_approve` | ProfilePhotoApprovalService, PhysicianLogoApprovalService y GalleryApprovalService: requisito exacto y auditoría de aprobación. |
| Medios / `media_review_source_download` | `MediaReviewInterventionService::download`: requisito DOWNLOAD, contexto canónico, integridad y auditoría antes de entregar bytes. |
| Medios / `media_review_corrected_upload` | `MediaReviewInterventionService`: requisito CORRECT, transacción, validación y versiones privadas. |
| Medios / `media_review_improve` | `LogoImprovementService`: requisito exacto, ámbito y auditoría; no publicación implícita. |
| Medios / `media_review_request_replacement` | `MediaReplacementService`: requisito exacto y estado revisable. |
| Proveedores / `provider_claim_review` | `HealthcareOrganizationClaimService::requireReviewer`: cuenta activa, staff ACTIVE y grant activo/no revocado. |
| Proveedores / `provider_subscription_activate` | `ProviderOrganizationSubscriptionService::requireOperator`: cuenta activa, staff ACTIVE y grant exacto; suscripción gobernada no implica pago. |

| Clave no editable para Advisor | Motivo preciso |
|---|---|
| `public_profile_correction_review` | ReportReviewService exige DIRECTOR o MASTER_ADMIN además del grant; no autoriza Advisor. |
| `public_profile_correction_apply` | ApplicationAuthority exige DIRECTOR o MASTER_ADMIN, credencial vigente y grant exacto; no autoriza Advisor. |
| `profile_create` | Sólo requisito declarativo en ProfileGovernancePolicy; no ruta/consumidor operativo conectado. |
| `profile_curate` | Sólo requisito declarativo en ProfileGovernancePolicy; no ruta/consumidor operativo conectado. |
| `profile_publication_manage` | Sólo requisito declarativo en ProfileGovernancePolicy; no ruta/consumidor operativo conectado. |
| `profile_support_assist` | Sólo requisito declarativo en ProfileGovernancePolicy; no ruta/consumidor operativo conectado. |

UI: «Reservada a Dirección y administradores maestros» para correcciones; «Función aún no disponible» para las cuatro funciones sin consumidor. SUSPENDED/REMOVED no pueden recibir grants. No se habilitan checkboxes para simular capacidad operativa.

## Datos y migración

[2026_10_19_01_staff_personal_presentation.sql](../../modules/identity/db/migrations/2026_10_19_01_staff_personal_presentation.sql) añade cuatro columnas con defaults neutros a la tabla versionada existente. Conserva triggers inmutables y FK; no nuevas tablas, servicios externos ni índices de identidad. Sólo se aplica al datadir nuevo del ensayo. Una instalación posterior de este código requiere esa migración por su proceso autorizado; este trabajo no migra un runtime existente.

## VERIFICATION

[QA dirigida](../evidence/ase-adm02-panel06/qa.json): **816 comprobaciones PASS**. Chromium/WebKit, 1440×900, 1366×768, 390×844, altura limitada 900×400 y ampliación CSS del contenido al 150 % dentro de ambos motores. Verifica campos y controles completos mediante scroll/teclado/click; las capturas [Chromium móvil](../evidence/ase-adm02-panel06/screens/chromium-390-844-1-profile-footer.png) y [WebKit móvil](../evidence/ase-adm02-panel06/screens/webkit-390-844-1-profile-footer.png) incluyen el pie del formulario. No se confunde esta prueba automatizada con aceptación física del Director ni con el ajuste de zoom del menú de Safari.

[Smoke final](../evidence/ase-adm02-panel06/smoke.json): **14 comprobaciones PASS**, incluidos CSRF, versión obsoleta, campo obligatorio, privacidad por caso/rol, recibo de intención sin secretos, recuperación de carga privada sin duplicar y retorno de foco en ambos motores. El ajuste final espera el refresco autorizado, conserva al integrante y su sección y recuerda el click real en WebKit. Se verificó con este smoke sobre la fuente final; no se repitió la matriz completa por un cambio limitado al refresco/foco.

[Verificación estática y hashes finales](../evidence/ase-adm02-panel06/verification.json): sintaxis, enlaces documentales, diff y fuentes entregadas. Los siete intentos previos y cuatro registros de smoke fallido permanecen en la carpeta de evidencia: foco real de diálogos/retorno en WebKit y correcciones del harness (grant ya existente, clasificación de rechazo MFA, espera de búsqueda, sesión de ensayo vencida y edición sin cambios). La caducidad se resolvió mediante nuevo login canónico de actores QA, sin modificar TTL ni reautenticar automáticamente el navegador de producto.

Los diez grants se guardan/revocan dinámicamente; el gate real de lectura Media se verifica en positivo y negativo. Los demás consumidores se contrastaron en fuente; no se volvieron a ejecutar sus módulos/suites históricas. Master mantiene su gate operativo cerrado: sus comandos privilegiados no se activaron para probar presentación.

## OPERATIONAL_STATUS

Sólo preview sintético loopback, hasta 24 horas de infraestructura; no se prolonga la vida de sesiones. La cuenta habitual del Director se consulta exclusivamente en la base ficticia, sin conexión a Identity real ni copia/reset de credenciales. Si no está presente, sólo `qa_director@example.test` y su contraseña sintética privada permiten entrar; la contraseña real no autentica allí.

Cerrar sesión lleva al login existente. Para volver, escribir el correo sintético y su contraseña en blanco inicialmente. La guía local no muestra ni prellena contraseñas. Un comando privado copia únicamente la contraseña sintética al portapapeles cuando el Director lo invoca expresamente; ningún secreto va a Git. El comando de reapertura del ensayo sigue separado del producto y no se ejecuta automáticamente al salir. Stop actúa sólo sobre el marcador de recursos con propiedad verificada.

## CTX-SYNC01

ID-02, ID-03, ID-07, COM-10 y J09; objetos: versiones de admisión, assets/operaciones StaffMedia, sesiones Identity y grants existentes. Estado y tres mapas pertinentes actualizados, enlaces/rutas verificados y fuente/evidencia contrastadas: PASS_UPDATED. No cambia el router documental ni otras capacidades/journeys.

## Revisión disponible

URL: `https://127.0.0.1:56607/internal/media-review/index.php?section=equipo`.
Infraestructura hasta **2026-10-19 18:51:31 UTC−06:00**. Sesiones con TTL canónico normal.

- Reabrir explícitamente el ensayo: `python3 /tmp/mxmed-panel06-review-s7k2qf0h/control.py open`.
- Copiar únicamente la contraseña sintética para login manual: `python3 /tmp/mxmed-panel06-review-s7k2qf0h/control.py copy-password`.
- Terminar: `python3 /tmp/mxmed-panel06-review-s7k2qf0h/control.py stop`.

Estos controles privados verifican dueño/root/HEAD/pins/expiración; sus archivos y secretos no forman parte del repositorio. El stop delega al runner propietario, no mata procesos encontrados por puerto. El preview no es público, no activa producción y conserva las fronteras comerciales de PANEL04.
