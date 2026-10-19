# ASE-ADM02-PANEL08 — interacciones finales de Equipo

## Baseline y autorización

Baseline PANEL07 `ec8024dfd4c16237877f92a25f4351e2ec0bdc16`, worktree hermano intacto. Rama aislada `feature/ase-adm02-panel08-final-interactions`. UI-2: petición expresa PANEL08 autoriza contención estructural, imagen opcional en Persona y disponibilidad precisa de seis permisos. No rediseña el panel. Aceptación visual del Director pendiente; no merge ni activación.

## Implementación y límites

- **PRODUCT_POLICY:** conserva cuatro pasos, binding Identity, confirmación del solicitante, revisión MFA y admisión independientes. Retrato no es documento de identidad. Extensión expresamente autorizada: Director activo creador puede preparar únicamente el retrato de su caso PREPARING aún sin cuenta vinculada. Esa autoridad termina al binding; el solicitante conserva su autoridad propia. El permiso administrativo previo para integrantes admitidos no cambia.
- **SOURCE_IMPLEMENTATION:** `team-dialogs.js` conserva nodos y handlers, separa encabezado/cuerpo/pie. El cuerpo flex puede encogerse (`min-height:0`) y recibe el único scroll. La envoltura se limita al visual viewport/dvh y el pie queda fuera del scroll. Antes, todo el diálogo y sus acciones compartían overflow, con encabezado sticky y reglas dispersas; no había un cuerpo desplazable independiente.
- **CONNECTIVITY:** Persona permite Sin imagen/Avatar/Fotografía; preview local, selección independiente del género, resumen en Revisión. Caso primero, retrato mediante StaffMedia, presentación mediante update versionado y finalmente invitación inerte. El foco personal se aplica después de habilitar los campos; el asistente abre con foco en su encabezado para no desplazar el inicio en móviles bajos; los selectores ignoran eventos scroll atrasados si su ancla no cambió de posición. No datos personales ni bytes en sessionStorage; sólo referencias de recuperación y selección no sensible. Respuesta incierta conserva operation_id y consulta media_result antes de continuar. El cierre conserva operaciones pendientes.
- **DATOS:** [migración nueva](../../modules/identity/db/migrations/2026_10_19_02_staff_preparation_portrait.sql) permite account_id NULL exclusivamente para retrato. Es procedencia inmutable de captura; el caso exacto lleva el binding posterior. No se reescribe AAD ni historia. Las versiones previas siguen descifrando con su contexto original. Evidencia exige cuenta y conserva autoridad original. Sólo instalada en datadir nuevo sintético.
- **VERIFICATION:** 111 comprobaciones funcionales/seguridad PASS y18 de zoom nativo PASS, con38 recorridos medidos y capturas inicial/fondo. No hereda PASS de PANEL07.
- **OPERATIONAL_STATUS:** sólo ensayo propietario loopback; original8091, datos reales y runtimes previos protegidos. Rollback por descarte de esta rama/preview; ninguna migración real aplicada.

## Matriz exacta: Advisor ACTIVE seleccionado

Director editable significa elegibilidad efectiva en la UI para ese Advisor; no basta que el writer genérico permita almacenar un grant. Los seis siguen visibles y UNAVAILABLE. Si no hay hijos editables, el selector de grupo no se presenta; Expandir/Contraer permanece operativo.

| Grupo | ID y nombre | Consumidor real | Director editable | Persistencia UI | Enforcement | Estado final | Bloqueo exacto |
|---|---|---|---|---|---|---|---|
| Correcciones de perfiles | `public_profile_correction_review` — Revisar informes de perfiles | ReportReviewService::authority; section=correcciones | NO | BLOCKED | PASS: denegación efectiva | UNAVAILABLE | DIRECTOR/MASTER_ADMIN; contrato PUB-COR01 y MFA excluyen Advisor |
| Correcciones de perfiles | `public_profile_correction_apply` — Aplicar correcciones autorizadas | ApplicationAuthority::grants; section=correcciones | NO | BLOCKED | PASS: denegación efectiva | UNAVAILABLE | DIRECTOR/MASTER_ADMIN, caso tipado y MFA; no autoridad Advisor |
| Administración de perfiles | `profile_create` — Crear perfiles médicos | NONE | NO | BLOCKED | NOT_AVAILABLE | UNAVAILABLE | Sólo declaración de política, sin ruta staff de creación |
| Administración de perfiles | `profile_curate` — Curar perfiles médicos | NONE | NO | BLOCKED | NOT_AVAILABLE | UNAVAILABLE | Sólo declaración de política, sin ruta staff de curación |
| Administración de perfiles | `profile_publication_manage` — Gestionar publicación | NONE | NO | BLOCKED | NOT_AVAILABLE | UNAVAILABLE | Sólo declaración de política, sin ruta staff de publicación |
| Administración de perfiles | `profile_support_assist` — Asistir administración de perfil | NONE | NO | BLOCKED | NOT_AVAILABLE | UNAVAILABLE | Sólo declaración de política, sin ruta staff de asistencia |

Writer existente: [InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php). Catálogo: [InternalCapabilityCatalog](../../modules/identity/services/InternalCapabilityCatalog.php). Elegibilidad UI: [ReadService](../../modules/identity/Team/ReadService.php). Consumidores: [ReportReviewService](../../modules/profiles/Corrections/ReportReviewService.php), [ApplicationAuthority](../../modules/profiles/Corrections/ApplicationAuthority.php). Ausencia de ruta staff: [ProfileGovernancePolicy](../../modules/identity/services/ProfileGovernancePolicy.php). Límites aprobados: [PUB-COR01](../profiles/PUB-COR01-secure-intake-human-review.md), [PUB-COR02](../profiles/PUB-COR02-governed-typed-application.md). No conectar owner/colaborador por equivalencia ni ampliar MFA implícitamente.

## Evidencia dirigida

Scripts: [browser](../../scripts/ase-adm02-panel08-browser.py), [zoom nativo](../../scripts/ase-adm02-panel08-native-zoom.js), [verificación estática](../../scripts/ase-adm02-panel08-verify.py). Cuerpo desplazado mediante rueda real hasta el fondo, captura inicial/final y bounds de último contenido/pie. Chromium/WebKit en1440×900,1366×768,1280×720,390×844,390×480 y zoom nativo125%; no CSS zoom. Safari físico/aceptación del Director no inferidos.

## CTX-SYNC01

CONTEXT_BASELINE=PANEL07 ec8024df
AFFECTED_CAPABILITY_IDS=ID-02,ID-03,ID-07,COM-10
AFFECTED_JOURNEY_IDS=J09
AFFECTED_DATA_OBJECTS=auth_staff_admission_cases,auth_staff_admission_versions,auth_staff_media_assets,auth_staff_media_operations; grants/delegaciones sólo verificados
AFFECTED_CONTEXT_FILES=estado actual, inventario funcional, interacciones, datos/migraciones, contrato PANEL08
CONTEXT_CHANGES_IMPLEMENTED=contención modal; retrato de preparación acotado; matriz de disponibilidad por capability
CONTEXT_VERIFICATION=PASS: 111 checks +18 zoom,38 recorridos, sintaxis/enlaces/pins y protección
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en el commit de implementación
REMAINING_CONTEXT_GAPS=ninguna activación real ni aceptación visual inferida; seis permisos no habilitados para Advisor


## Cierre verificado

Resultado `COMPLETE_ISOLATED_SYNTHETIC`, con seis capacidades **UNAVAILABLE** individualmente justificadas, no implementadas por esta tarea. Los intentos fallidos quedan como evidencia de corrección: foco antes de habilitar campos, scroll atrasado que cerraba listas, inicio de wizard en WebKit móvil y dos supuestos incorrectos de fixtures (delegación Master existente y revocación ya realizada). El resultado final usa la fuente corregida; no suma los intentos al PASS.

- [QA funcional/seguridad111](../evidence/ase-adm02-panel08/qa.json), [zoom18](../evidence/ase-adm02-panel08/native-zoom.json), [38 mediciones modales](../evidence/ase-adm02-panel08/modal-bounds.json), [matriz6](../evidence/ase-adm02-panel08/capabilities.json), [verificación estática](../evidence/ase-adm02-panel08/verification.json).
- Evidencia de fondo: [Chromium móvil](../evidence/ase-adm02-panel08/screens/chromium-390x844-personal-bottom.png), [WebKit móvil bajo](../evidence/ase-adm02-panel08/screens/webkit-390x480-personal-bottom.png), [Chromium125%](../evidence/ase-adm02-panel08/screens/chromium-native125-staff-profile-bottom.png), [WebKit125%](../evidence/ase-adm02-panel08/screens/webkit-native125-staff-profile-bottom.png).
- Preview: `https://127.0.0.1:64960/internal/media-review/index.php?section=equipo`, expira2026-10-19 20:32:27 UTC−06:00 (America/Mexico_City). Infraestructura24h desde su arranque, sin extender sesión Identity.
- Control explícito: `python3 scripts/ase-adm02-panel08-review.py /tmp/mxmed-ase-adm02imp01-uz6hovn5 open`; detener con el mismo comando y `stop` en vez de `open`. `credentials` copia únicamente la contraseña sintética al portapapeles por acción explícita; `mfa` muestra el código sintético vigente. No secretos en producto/Git.
- 22 worktrees, listeners originales y pointer INT01 conservados; sólo nueva base propia. Sin merge/deploy/producción. Aceptación física/visual del Director PENDING.
