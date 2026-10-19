# ASE-ADM02-OPER09 — ventana real y primer Advisor funcional

Baseline: `f486c42bd5272246a4a6b110d18affba641b29ff`, PANEL08 limpio. Rama aislada `feature/ase-adm02-oper09-first-functional-advisor`. No merge, despliegue ni nueva interfaz Advisor. Aceptación visual del Director **PENDING**.

## Problema y corrección acotada

El lanzador anterior emulaba un contenido de 1366×900. En Chrome for Testing visible sobre macOS, la ventana nativa fue limitada a 870 px de alto, incluyendo sus controles. `innerHeight` y `visualViewport.height` seguían informando 900; el pie del formulario completo llegaba a 871. La captura del viewport emulado y el hit-test del DOM no acreditaban su accesibilidad física. [Mediciones antes](../evidence/ase-adm02-oper09/modal-root-cause.json).

El nuevo [control de revisión](../../scripts/ase-adm02-oper09-review.py) usa `no_viewport=True`, mide el área disponible y ajusta **la ventana nativa**, incluyendo el marco de Chrome. El cliente obtiene su altura real. Conserva el formulario completo, el cuerpo desplazable y sus controles existentes; no añade otra biblioteca modal ni rediseña las siete secciones. La corrección es compartida por las dos ventanas de revisión. También se cierra el `<ul>` de conjuntos documentales que faltaba: el navegador estaba anidando botones y párrafos posteriores dentro de esa lista, estrechándolos en móvil. Se conserva la misma estructura visual y acciones.

La composición aislada conecta el índice y siete endpoints **existentes** de Media con su autoridad Identity/Staff/grants original. Equipo mantiene su frontera separada. Una denegación tipada de Team ahora conserva su 401/403/400/404, en lugar de convertirse por el router sintético en `isolated_service_unavailable` 503. No se modifica el endpoint productivo ni la autoridad de los servicios.

## Recorrido canónico y alcance de la evidencia

Director: login Identity → cuatro pasos de Agregar integrante → invitación exacta. Irene Ficticia Oper09: registro y verificación de correo mediante los servicios canónicos con entrega local inerte → login independiente → aceptación en UI → corrección/confirmación → dos caras artificiales → envío. Director: READ, ACCEPT y ADMIT mediante UI, contraseña, TOTP vigente y proofs individuales. Sin INSERT/UPDATE manual de admisión, grants, estado staff, revisión o MFA.

El Director concede `media_review_read` mediante la matriz, guarda y recarga. El Advisor abre la interfaz original Media, su lote y la imagen REVIEW real. El original SOURCE permanece denegado con 403. Revocación por UI produce 403 en la misma sesión; suspensión por UI también. La reactivación y el regrant explícitos dejan la cuenta ACTIVE y útil. Logout canónico invalida la sesión; reusar su cookie ya revocada falla en servidor y no cierra la sesión del Director.

Los probes complementarios llaman los servicios reales: delegación Master y activación comercial de proveedor sin grant se deniegan antes de cualquier operación. El Advisor no puede consultar Equipo, otro staff ni abrir revisión de identidad o autoconcederse permisos. No se afirma cobertura de todos los servicios financieros del sistema.

La única imagen Media es un raster artificial preparado como entrada de prueba mediante el servicio existente de candidatos, en almacenamiento propio. Un lote ficticio envejecido es presentado por el ejecutor canónico de inactividad y auditado. Esa preparación de entrada no constituye autorización del Advisor. Sólo se instala el schema Media ya existente en el datadir nuevo. No publicación ni medio real.

La lectura Media R0 existente no genera un evento de auditoría por cada byte leído; sí se verifican la auditoría de envío del candidato, admisión, grants y ciclo de staff. No se inventó un segundo writer de auditoría.

## Capacidad efectiva para la siguiente etapa

“Disponible ahora” significa **ejercitable por este Advisor en este preview**. La presencia en catálogo o elegibilidad de checkbox no prueba conectividad. No se implementa ninguna entrada nueva con esta tabla.

| Función / ID canónico | Consumidor existente y entrada propuesta | Disponible ahora | Prueba efectiva / límite exacto |
|---|---|---|---|
| Consultar medios — `media_review_read` | [Inbox](../../internal/media-review/index.php), [reader HTTP](../../modules/media/http/MediaReviewHttpController.php); entrada existente `/internal/media-review/index.php` | SÍ | ALLOW con grant; DENY tras revoke/suspend/logout |
| Descargar originales — `media_review_source_download` | [Intervention](../../modules/media/services/MediaReviewInterventionService.php), `source-download.php`; botón existente de detalle | NO, sin grant | DENY 403 verificado; positivo no ejecutado |
| Aprobar medios — `media_review_approve` | [Approval](../../modules/media/services/ProfilePhotoApprovalService.php); acción original de detalle | NO, sin grant | Consumidor existente; NOT_TESTED positivo; publicación fuera de este ensayo |
| Cargar corrección — `media_review_corrected_upload` | [Intervention](../../modules/media/services/MediaReviewInterventionService.php); acción original de detalle | NO, sin grant | NOT_TESTED; endpoint de mutación no expuesto en esta composición |
| Mejorar logo — `media_review_improve` | [UI Media](../../assets/js/media-review-inbox.js), `improvement-preview.php`; acción original de logo | NO, sin grant | NOT_TESTED; no logo ni pipeline de mejora preparado aquí |
| Solicitar otra imagen — `media_review_request_replacement` | [Replacement](../../modules/media/services/MediaReplacementService.php); acción original de detalle | NO, sin grant | NOT_TESTED; mutación no conectada en este preview |
| Fuentes profesionales — `professional_source_provision` | [SourceProvisioningService](../../modules/identity/ProfessionalSources/SourceProvisioningService.php); superficie original de acreditación | NO en este preview | Contrato permite Advisor con grant/caso/MFA; infraestructura/expediente no preparados, NOT_TESTED |
| Acreditación — `physician_accreditation_review` | [PhysicianOnboardingHttp](../../modules/identity/Accreditation/PhysicianOnboardingHttp.php); superficie original de acreditación | NO en este preview | Contrato rutinario separado, MFA/ámbito exacto; NOT_TESTED |
| Claims — `provider_claim_review` | [HealthcareOrganizationClaimService](../../modules/agenda/services/HealthcareOrganizationClaimService.php); entrada Advisor pendiente de verificar | NO en este preview | Servicio con grant; ruta UI no acreditada aquí, NOT_TESTED |
| Suscripción proveedora — `provider_subscription_activate` | [ProviderOrganizationSubscriptionService](../../modules/subscriptions/services/ProviderOrganizationSubscriptionService.php); sin nueva entrada propuesta | NO, sin grant | DENY canónico verificado; servicio interno sin ruta HTTP pública ni checkout/pago |
| Revisar correcciones — `public_profile_correction_review` | [ReportReviewService](../../modules/profiles/Corrections/ReportReviewService.php) | NO | Reservado Director/Master; contrato PANEL08 conservado |
| Aplicar correcciones — `public_profile_correction_apply` | [ApplicationAuthority](../../modules/profiles/Corrections/ApplicationAuthority.php) | NO | Reservado Director/Master; MFA Advisor no habilitado |
| Crear perfil — `profile_create` | [ProfileGovernancePolicy](../../modules/identity/services/ProfileGovernancePolicy.php) | NO | Sin consumidor operativo staff; ninguna entrada nueva |
| Curar perfil — `profile_curate` | Mismo contrato | NO | Sin consumidor operativo staff |
| Publicación — `profile_publication_manage` | Mismo contrato | NO | Sin consumidor operativo staff |
| Asistencia — `profile_support_assist` | Mismo contrato | NO | Sin consumidor operativo staff |

Las capacidades de gobierno `internal_advisors_manage` y `profile_initial_ownership_approve` conservan sus contratos propios; no son permisos operativos incluidos en esta demostración. D01–D08, ownership, clínica, Agenda y billing intactos.

## Revisión local

Dos procesos Chromium independientes, sin viewport emulado ni cookies del Director transferidas al Advisor. Las contraseñas son temporales, privadas y no están en Git/HTML. El comando `credentials-director` o `credentials-advisor` copia la contraseña correspondiente sólo cuando el revisor lo ejecuta; `mfa` obtiene el código ficticio vigente.

```sh
python3 scripts/ase-adm02-oper09-review.py /tmp/mxmed-ase-adm02imp01-4ta1d2ac open
python3 scripts/ase-adm02-oper09-review.py /tmp/mxmed-ase-adm02imp01-4ta1d2ac credentials-advisor
python3 scripts/ase-adm02-oper09-review.py /tmp/mxmed-ase-adm02imp01-4ta1d2ac logout-advisor
python3 scripts/ase-adm02-oper09-review.py /tmp/mxmed-ase-adm02imp01-4ta1d2ac stop
```

Ejecutar desde este worktree. `open-director` / `open-advisor` reabren sólo la experiencia elegida mediante login normal. `logout-director` / `logout-advisor` solicitan cierre canónico y cierre de sus ventanas propias; el Director también dispone del botón Cerrar sesión. No se renuevan automáticamente sesiones ni MFA. `stop` solicita al runner propietario terminar exclusivamente sus hijos. No borrar datadirs ni matar listeners ajenos.

Infraestructura hasta **2026-10-19 21:01:05 UTC−06:00 (America/Mexico_City)**, 24 h desde su arranque, independientemente de los TTL Identity/MFA. URL Director `https://127.0.0.1:59919/internal/media-review/index.php?section=equipo`; Advisor `https://127.0.0.1:59919/internal/media-review/index.php`. La ruta existente carece todavía de workspace/navegación Advisor propia; es el límite explícito para la siguiente tarea.

## Verificación y CTX-SYNC01

Pruebas dirigidas únicamente: [runner](../../scripts/ase-adm02-oper09-qa.py), [recorrido y modales](../../scripts/ase-adm02-oper09-browser.py), [zoom nativo](../../scripts/ase-adm02-oper09-native-zoom.js), [servicios negativos](../../scripts/ase-adm02-oper09-security.php). Mediciones y screenshots son evidencia técnica, nunca aceptación visual del Director.

CONTEXT_BASELINE=PANEL08 f486c42bd5272246a4a6b110d18affba641b29ff
AFFECTED_CAPABILITY_IDS=ID-01,ID-02,ID-03,ID-07,MED-03,COM-10
AFFECTED_JOURNEY_IDS=J09
AFFECTED_DATA_OBJECTS=Identity/Valkey; casos/versiones/invitaciones/media/reviews/completions staff; internal_staff/internal_operator_grants; media_review_submissions/files/batches/ready_events; audit
AFFECTED_CONTEXT_FILES=estado actual; inventario; interacciones; datos/migraciones; contrato OPER09
CONTEXT_CHANGES_IMPLEMENTED=ventana nativa; consumidor Media existente conectado sólo en ensayo; denegación Team tipada; inventario efectivo
CONTEXT_VERIFICATION=PASS: 57 checks dirigidos; 22 zoom nativo; 44 recorridos modales y probes complementarios; sintaxis, enlaces y 23 worktrees protegidos
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en el commit aislado de implementación
REMAINING_CONTEXT_GAPS=aceptación Director pendiente; workspace Advisor expresamente no implementado; ningún despliegue o test productivo inferido


## Cierre técnico

`COMPLETE_ISOLATED_SYNTHETIC`. [57 checks](../evidence/ase-adm02-oper09/qa.json), [22 checks zoom nativo](../evidence/ase-adm02-oper09/native-zoom.json), [44 recorridos modales](../evidence/ase-adm02-oper09/modal-bounds.json), [selector avatar y descarte protegido](../evidence/ase-adm02-oper09/avatar-modal.json), [consumer real](../evidence/ase-adm02-oper09/operational-result.json), [servicios negativos](../evidence/ase-adm02-oper09/security-services.json), [bytes servidos](../evidence/ase-adm02-oper09/served-assets.json). Los intentos intermedios fallidos se mantuvieron fuera del conteo PASS; no se repitió la suite histórica.

El Advisor queda ACTIVE, con `media_review_read` como único grant. La persona confirma la versión editada. Su admisión y revisión originales permanecen auditadas; la revisión anterior no acredita la nueva versión, y nuevos envíos posteriores a admisión están bloqueados por el contrato existente. Julia Ficticia Pendiente Oper09 permanece sin admisión para inspeccionar el modal de evidencia/MFA. No hay un segundo Advisor creado por esta prueba.

Capturas: [pie nativo y guardado](../evidence/ase-adm02-oper09/screens/native-save-footer.png), [390×600 Chromium](../evidence/ase-adm02-oper09/screens/chromium-390x600-personal.png), [390×600 WebKit](../evidence/ase-adm02-oper09/screens/webkit-390x600-personal.png), [Advisor en consumer Media](../evidence/ase-adm02-oper09/screens/advisor-real-consumer.png). La aceptación física del Director sigue PENDING y Safari físico no se afirma probado. La próxima tarea podrá definir el workspace Advisor usando el inventario; no está implementado aquí.
