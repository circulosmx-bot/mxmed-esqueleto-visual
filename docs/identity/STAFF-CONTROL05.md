# STAFF-CONTROL05 — incorporación pendiente comprensible

Baseline verificado `f134ee3454000d97dc1df991b81f72eee7559274`, STAFF-CONTROL04 limpio. Rama aislada `feature/mxmed-staff-control05-admission-simplification`; worktree homónimo. Solicitud expresa UI-2 de corrección contextual del panel Director e índice de desarrollo; no rediseño general ni once módulos nuevos. [Contrato anterior](STAFF-CONTROL04.md), [invitación](ASE-ADM02-IMP02-staff-invitation-binding.md) y [revisión/admisión](ASE-ADM02-FUNCTIONAL-CONSOLIDATION.md) conservan sus autoridades. Aceptación visual PENDING.

## Decisiones y fuente

- `PRODUCT_POLICY`: facultad prevista, admisión y permiso efectivo siguen siendo decisiones distintas. El panel muestra situación canónica y siguiente acción elegible; no crea un estado de negocio paralelo.
- `SOURCE_IMPLEMENTATION`: `staff-workflow.js` proyecta caso, invitación, conjunto, revisión y `available_actions` del servidor. Sin binding/evidencia no ofrece controles futuros. READ conduce a «Revisar incorporación»; ACCEPT sólo aparece tras lectura protegida; ADMIT sigue independiente. Contraseña, MFA, versiones y autoridad se revalidan en los servicios existentes.
- `CONNECTIVITY`: editar preparación sólo llama al writer de versiones; nunca emite invitaciones. Si cambió la versión o venció la invitación, el reader informa que ya no es utilizable y la emisión sustitutiva exige correo y confirmación explícitos. El comando `reissue` conserva sus recibos y reemplaza el token anterior. Ninguna reemisión automática.
- Cancelar sólo una incorporación pendiente usa el revoke canónico: conserva versiones, cuentas y auditoría. El caso REVOKED invalida su invitación por la precondición de aceptación; no se reescribe el estado histórico de la fila emitida. El reader `usable=false` muestra su inutilidad efectiva. La guarda transaccional rechaza cancelación de un caso vinculado a cualquier relación staff existente, incluso suspendida o dada de baja; no es el comando de baja de personal.
- `Intentions::catalog()` limita esta preparación a las once claves ya conectadas de Media/Requests. Se conservan sus identificadores globales y registros históricos; no se crean grants ni se activa una selección antigua. Las funciones pendientes se muestran sin checkbox. Activación final sigue en el writer canónico, con confirmación individual y revisión vigente.
- `VERIFICATION=PASS_TARGETED_SYNTHETIC`; `OPERATIONAL_STATUS=OWNED_SYNTHETIC_ONLY`. Original/8091, WORKSPACE02/03 y STAFF-CONTROL04 protegidos; producción deshabilitada.

## Simulación y recuperación

El worker existente registra hitos sólo después de resultados canónicos comprobados: solicitud, cuenta preparada/verificada, invitación aceptada, datos confirmados, evidencia presentada y REVIEW_REQUIRED. Son observaciones de una operación local, no estados nuevos del expediente. Nunca aprueba revisión ni admisión.

`simulation_status` es una lectura autorizada Director/caso, con CSRF y composición owned, y no encola trabajos. Se devuelve sólo identidad estable de operación, estado, etapas y mensaje; no tokens, rutas privadas ni credenciales. La UI acusa el clic inmediatamente, consulta mientras el diálogo permanece abierto y recupera el mismo trabajo tras recargar o perder la respuesta. Un error detiene el sondeo con una instrucción explícita de consulta, sin retry de mutación. Cierre del diálogo detiene el sondeo. Un fallo del worker permanece RECONCILIATION_REQUIRED, conserva las etapas confirmadas y no simula éxito.

## Inventario de quince áreas

YES significa conexión existente para Asesor bajo permisos actuales; PARTIAL limita la cobertura de fuente, no concede autoridad. Estas áreas no son quince módulos terminados. La matriz de autoridades completa de [STAFF-CONTROL04](STAFF-CONTROL04.md#matriz-de-soporte-y-autoridades) sigue vigente; esta tabla concreta lo que puede usarse desde el panel.

| Área | Fuente existente | Conectado para Asesor | Lectura disponible | Escritura/decisión disponible | Claves canónicas | Límite o integración faltante | Auditoría existente |
|---|---|---|---|---|---|---|---|
| Resumen | `PhysicianRequests/PendingSummary`, `Media/AdvisorWorkspaceService` | YES | Conteos de seguimiento autorizado | Ninguna | `physician_pending_summary_read` + ámbito Media/Requests | No descubre cartera ni concede detalle | Fuentes auditadas; navegación sin eventos de decisión |
| Solicitudes | `PhysicianRequests/RequestService`, enrollment canónico | YES | Solicitud y evidencia exactas | Resolver reclamo FREE/perfil nuevo, pedir información | `physician_request_read`, `physician_request_evidence_read`, `physician_claim_resolve`, `physician_new_profile_resolve` | Resolución requiere contraseña/MFA; no transfiere ownership ni publica | Decisiones, evidencias y respuestas canónicas |
| Perfil | `PublicProfileController/Repository`, writers privados | NO | Ninguna desde este panel | Ninguna | `profile_create`, `profile_curate`, `profile_publication_manage`, `profile_support_assist` no seleccionables aquí | Falta integración autorizada para Asesor | Fuentes originales sin writes nuevos |
| Acreditación | `PhysicianEvidenceService`, `ProfessionalAuthorityIssuer` | NO | Ninguna desde este panel | Ninguna | `physician_accreditation_review`, `professional_source_provision` fuera de esta selección | Revisión profesional independiente; no equivale a resolver solicitud | Audit de acreditación separado |
| Consultorios | Agenda `ConsultoriosController/Repository` | NO | Ninguna | Ninguna | No grant staff conectado | Contexto médico/consultorio exacto | Agenda original; sin writes staff nuevos |
| Servicios y atención | `PublicProfilePlanCapabilities`, Agenda/Subscriptions | NO | Ninguna | Ninguna | Ninguna nueva | Entitlements y ámbito médico independientes | Fuentes originales |
| Imágenes | `AdvisorWorkspaceService`, `MediaReviewBatchService` | YES | Derivados y original privado autorizado | Aprobar, corregir, proponer mejora, pedir reemplazo | `media_review_read`, `media_review_approve`, `media_review_source_download`, `media_review_corrected_upload`, `media_review_improve`, `media_review_request_replacement` | Acción/imagen exactas; no publica perfil oculto ni cambia plan | Audit Media y versiones de assets |
| Agenda | `AvailabilityController`, gateway Agenda | NO | Ninguna | Ninguna | Ningún grant staff diagnóstico | Contexto médico/operador; no expone pacientes | Agenda original |
| Operadores y colaboradores | `ProfileOperatorService`, `OperatorHttp` | NO | Ninguna | Ninguna | Grants `agenda.*` de operador no son facultades staff | Relación/plazas y permisos propios | Invitations/memberships/revisiones existentes |
| Plan y servicios contratados | `CurrentSubscriptionReadModelService`, `PublicCommercialPlanSource` | NO | Ninguna | Ninguna | `provider_subscription_activate` corresponde a otro dominio | Sin acceso staff a plan médico ni pagos | Subscriptions original |
| Facturación y pagos | `PatientBillingProfilesService`, `InvoiceDraftService` | NO | Ninguna | Ninguna | Ninguna nueva | Fiscal, paciente y documento exactos; sin PAC/cobros | Billing original |
| Opiniones | DTO público y `PublicProfilePlanCapabilities` | NO | Ninguna | Ninguna | Ninguna nueva | No hay moderación Asesor conectada | Sin cobertura nueva |
| SEO | `PublicProfileController`, metadata pública | NO | Ninguna | Ninguna | Ninguna nueva | Publicación derivada; no editor staff | Fuentes públicas existentes |
| Soporte de cuenta | Identity `SessionService`, autenticación | NO | Sólo sesión propia fuera de este módulo | Ninguna sobre terceros | Ninguna nueva | No diagnóstico general, secretos ni impersonación | Identity original |
| Historial | `PhysicianRequests/HistoryReader`, actividad Media/Team | PARTIAL | Eventos Requests/Media autorizados | Ninguna | Grants de lectura de sus fuentes | No auditoría universal ni historia clínica/comercial | Proyección de audit canónico |

## Índice y contextos

Conserva seis entradas y las rutas verificadas: Director `/internal/media-review/index.php?section=equipo`, Asesor `/internal/staff-workspace/index.php`, médico/operador `/private-app.php`, público `/profiles/doctor.php`, proveedor `/provider/index.php`. Sólo las dos primeras tienen contexto operativo aquí. Las otras cuatro dicen «Interfaz existente; contexto de prueba todavía no habilitado» y explican la relación/servicio ausente.

Cada entrada operativa muestra el comando exacto `open-director` o `open-advisor` del launcher owned para abrir o enfocar su ventana independiente. El lock informa ventana abierta, no certifica una sesión autenticada todavía vigente; Identity revalida al entrar. No hay ejecución de shell por HTTP, enlaces con cookies, elevación ni intercambio de identidad. El índice sigue apagado fuera de la composición de desarrollo.

## Verificación y revisión

52 checks nuevos PASS: [principal](../evidence/staff-control05/qa.json) (19, incluidos seis de composición), [aceptación complementaria](../evidence/staff-control05/supplement.json) (27) y [Solicitudes](../evidence/staff-control05/requests-smoke.json) (6). No se heredan los 53 PASS del baseline ni se declara verificada la operación productiva. Rollback: cerrar únicamente el runner propio y conservar la rama baseline; sin migración nueva ni cambios a datos originales. Safari físico y aceptación visual pendientes.


Se verificaron: estado pendiente sin acciones futuras; once facultades seleccionables y rechazo servidor de otra clave; persistencia sin grants; edición sin invitación duplicada y reemisión explícita; lector sin cola; respuesta de simulación perdida después del commit recuperada por estado; seis hitos y REVIEW_REQUIRED sin staff; revisión/admisión con MFA y grants individuales; cancelación con rechazo de aceptación autenticada y cuenta conservada; rechazo de cancelación para staff admitido; denegación Advisor a estado/intenciones Director; Media/Requests/historial y límites entre médicos. Chromium nativo y Chromium/WebKit en 1366×768 y 390×844 conservaron scroll, pie del modal y ancho de índice. Sin errores JS en el ensayo positivo.

El ensayo de pérdida de respuesta inicialmente usó un transporte de interceptación que recibió 403; no hubo cola ni binding. Se sustituyó por descarte de la respuesta en el mismo navegador **después de una respuesta 200 de la solicitud legítima**, sin alterar headers, sesiones ni protección del servidor. La nueva prueba pasó. Un intento inicial del runner coincidió con cambio de hash durante la preparación y falló cerrado antes del login; se verificó y vinculó la fuente propia antes del recorrido positivo, sin tocar el baseline. El launcher rechazó correctamente metadata de candidato con permisos demasiado amplios; los archivos propios y el umask del ensayo se corrigieron a privados antes de abrir la ventana final.

La aprobación Media se verificó mientras Aurora seguía hidden. Después, el fixture existente de Solicitudes preparó sus datos y la dejó active/FREE expresamente para el ensayo; no es un efecto de Media ni una prueba de publicación real. No se ejercieron writers de ownership, funciones clínicas, pagos o proveedor. Los motores y contratos WORKSPACE02/03 permanecen sin cambios; sólo se repitió el smoke acotado de solicitudes/ámbito/historial.

[Manifiesto final](../evidence/staff-control05/final-verification.json): hashes entregados, sintaxis, enlaces pertinentes, worktrees/listeners protegidos y ventanas nativas. Los datos del ensayo quedan disponibles hasta el cierre owned, no son un registro de personas reales.

## Acceso a la revisión

Preview exclusivamente loopback: **https://127.0.0.1:55370/internal/media-review/index.php?section=equipo**. Asesor: **https://127.0.0.1:55370/internal/staff-workspace/index.php**. Índice: **https://127.0.0.1:55370/development/interfaces.php**.

Vencimiento **2026-10-21 13:21:19 UTC−06:00, America/Mexico_City**. No se amplían TTL de Identity ni vencimientos canónicos de invitaciones. El entorno STAFF-CONTROL04 se conserva separado; se reutilizó su infraestructura de scripts para componer una instancia owned nueva de la rama correctiva.

En Director queda Lucía Pendiente Ficticia Preparada: invitación registrada y cuenta sin vincular, con facultad prevista sin acceso. Candela Revisión Ficticia quedó vinculada, con evidencia artificial, lista para revisión protegida. Irene Ficticia Oper09 fue admitida mediante el recorrido canónico; dos permisos Media y cuatro de solicitudes/resumen se confirmaron explícitamente durante QA. El caso cancelado permanece en registros e historia, sin cuenta eliminada.

Desde el worktree `mxmed-staff-control05-admission-simplification`:

```sh
python3 scripts/staff-control05-review.py /tmp/mxmed-ase-adm02imp01-519h5duy open
python3 scripts/staff-control05-review.py /tmp/mxmed-ase-adm02imp01-519h5duy status
python3 scripts/staff-control05-review.py /tmp/mxmed-ase-adm02imp01-519h5duy credentials-director
python3 scripts/staff-control05-review.py /tmp/mxmed-ase-adm02imp01-519h5duy mfa-director
```

Cuenta Director `qa_director@example.test`; Asesor `oper09_advisor@example.test`. Contraseña y código se copian sólo al portapapeles; no están en Git. Para Asesor usa `credentials-advisor` y `mfa-advisor` en su ventana independiente. Un código ya consumido no se reutiliza; espera el intervalo siguiente. `open-director`/`open-advisor`, visibles en el índice, abren o enfocan su ventana; no intercambian cookies ni cambian el rol de la ventana actual.

Cierre desde cualquier directorio:

```sh
python3 /Users/circulodigital/Documents/GitHub/mxmed-staff-control05-admission-simplification/scripts/staff-control05-review.py /tmp/mxmed-ase-adm02imp01-519h5duy stop
```

Sólo señala al runner propietario para su limpieza. No mata procesos por puerto ni cambia el entorno original. Tras vencimiento/descarte, `python3 scripts/staff-control05-qa.py --keep-review` crea otra instancia y anuncia nueva URL; no reutiliza una sesión vencida.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=STAFF-CONTROL04 f134ee3454000d97dc1df991b81f72eee7559274
AFFECTED_CAPABILITY_IDS=ID-01,ID-02,ID-03,ID-07,MED-01,MED-02,MED-03,COM-10; OP/ORG sólo referencias de límites
AFFECTED_JOURNEY_IDS=J01,J02,J09; J07,J08 fronteras sin activación
AFFECTED_DATA_OBJECTS=casos/versiones/invitaciones/operaciones staff; intenciones y grants existentes; metadata owned de simulación; sin DDL nuevo
AFFECTED_CONTEXT_FILES=ESTADO_ACTUAL_MXMED; README; MXMED_REGISTRO_CONTRATOS_VISUALES; INVENTARIO_FUNCIONAL_MXMED; INTERACCIONES_MXMED; DATOS_Y_MIGRACIONES_MXMED; TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED; identity/STAFF-CONTROL05
CONTEXT_CHANGES_IMPLEMENTED=estado y siguiente acción; edición/reemisión; facultades disponibles y futuras; progreso/recuperación; cancelación efectiva; índice por contexto
CONTEXT_VERIFICATION=diff completo, fuente, enlaces pertinentes, hashes y QA dirigida de esta rama
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=incluido en commit aislado; hash en entrega final
REMAINING_CONTEXT_GAPS=once áreas pendientes de integración Advisor y cuatro contextos de prueba no habilitados, fuera del alcance; aceptación visual y Safari físico pendientes
```
