# ASE-ADM02-PANEL05 — interacción y recuperación del panel

Base exacta `b356b06e5a7650fafaea82d08ac396611b3d4b09`, [PANEL04](ASE-ADM02-PANEL04.md) limpio y con upstream. Rama/worktree nuevos: `feature/ase-adm02-panel05-interaction-fixes`, `mxmed-ase-adm02-panel05`. UI-2 y ajustes visuales dirigidos autorizados expresamente por PANEL05: seis problemas, sin rediseño general. Aceptación visual del Director **PENDING**; no promoción a 8091.

## Diagnóstico y corrección

- Modal: los handlers anteriores bloqueaban cierre mientras `busy`, la petición no tenía timeout y faltaba backdrop. Cerrar/Escape/backdrop ahora llaman al mismo dismissal; se confirma descarte no guardado, se conserva el borrador canónico y se restaura foco/scroll. Durante envío o incertidumbre se puede cerrar; se conserva la identidad de operación y reapertura no reenvía. Timeout no equivale a rollback.
- Invitación: [reproducción real sobre PANEL04](../evidence/ase-adm02-panel05/baseline-reproduction.json), cuatro pasos con persona artificial y destino reservado `example.org`: prepare/issue HTTP200, recibo comprometido, `DELIVERY_PENDING`, mientras la UI anunciaba preparación. La restricción `@example.test` estaba en el adaptador **después del commit**. No se reprodujo el mensaje genérico de la captura con ese único factor y no se le atribuye una causa histórica no demostrada. La UI anterior ocultaba cualquier error HTTP bajo ese mensaje y no reconciliaba. Ahora dominio explicado/validado desde Persona y validado en composición sintética antes del writer; feedback distingue creación, entrega sintética pendiente, denegación, validación e incertidumbre. No afirma envío a un buzón.
- Recuperación: lecturas canónicas `case_result`, `invitation_result`, `invitation_status` sobre IDs estables, sesión/CSRF/Director vigentes y locks del UnitOfWork existente. Recibos de otra cuenta no se exponen. Ausencia de recibo sólo devuelve `NOT_RECORDED` a Director y no autoriza reenvío automático. Se comprueban también caso e invitación existente; reintento explícito conserva el ID, y el writer existente comprueba digest/versión/estado/unicidad. Una petición retrasada aún puede llegar después de una lectura negativa: conservar el ID evita duplicación; no se promete cancelación distribuida.
- Browser storage: sólo acción e IDs de operación/caso, por cuenta en sessionStorage. Nunca perfil, email, contraseña, cookies, tokens ni MFA. Payload de preparación sólo en memoria. Tras reload se recupera el resultado por el reader autorizado; sin commit se solicita entrada/acción explícita. Recibos canónicos y casos persistidos son la fuente de verdad.
- Categorías: Master primero y Asesores segundo, peso primario/selección visible; estado debajo como control secundario. Se conserva valor canónico del selector y se presenta popup compacto con el patrón button/listbox ya usado por el selector de clasificación del proyecto, sin librería nueva. Ratón, flechas, Home/End, Enter/Escape, salida Tab y foco. Listado/Add y secciones PANEL04 conservados.
- Búsqueda: «Buscar integrante» / «Nombre del integrante»; nombres de la versión personal canónica ligada a una admisión completada, sólo en la composición con esa fuente y para Director autorizado. Categoría/estado/autoridad y búsqueda se aplican en SQL antes de paginar. Master conserva búsqueda por referencia en su ámbito, sin acceso nuevo a datos personales. Pendientes busca sólo casos visibles por creator/binding; sin búsqueda global Identity. Limpiar, vacío y cambios de categoría/estado coherentes.
- Permisos: writer/editor existentes, sin nueva autoridad. ACTIVE permite claves implementadas/delegadas y guardado canónico; suspendidos/removidos siguen restringidos. Mensaje contextual y retorno a activos. No se habilitan consumidores pendientes, auto-grants ni poderes Master.

## Límites de fuente y operación

`PRODUCT_POLICY`: sólo interacción autorizada; no roles, política comercial, autoservicio de asesores ni envíos reales nuevos.

`SOURCE_IMPLEMENTATION`: [asistente](../../assets/js/staff-workflow.js), [panel](../../assets/js/internal-team.js), [read model](../../modules/identity/Team/ReadService.php), [composición workflow](../../modules/identity/StaffWorkflow/Http.php), servicios canónicos de [casos](../../modules/identity/StaffAdmission/Service.php) e [invitaciones](../../modules/identity/StaffAdmission/InvitationService.php). Sin migración, writer alternativo o modificación de MFA/grants.

`CONNECTIVITY`: sólo router sintético existente; endpoint normal permanece cerrado. Nuevas lecturas autorizadas no entregan token/recipiente ni documentos privados. Delivery local inert, no proveedor externo.

`VERIFICATION`: **88 checks de integración PASS y 5 checks adicionales PASS**; [runner](../../scripts/ase-adm02-panel05-qa.py) reutiliza infraestructura propietaria y preparación canónica existente, con [checks dirigidos](../../scripts/ase-adm02-panel05-browser.py). No ejecuta suites históricas. Chromium/WebKit en tres tamaños, ACK perdido antes/después de commit, cierre en vuelo, negativa canónica, búsqueda/paginación/privacidad y permisos/consumer.

`OPERATIONAL_STATUS`: sólo ensayo sintético loopback nuevo, fuentes/runtimes/worktrees anteriores preservados. La reproducción agregó un caso artificial identificable al preview PANEL04 sin editar su fuente ni servicios. Aceptación visual, Safari físico, producción y pendientes comerciales heredados no se declaran verificados. Rollback: conservar PANEL04 y cerrar exclusivamente el runner propio; sin merge ni deploy.

## Evidencia y revisión disponible

[Evidencia principal](../evidence/ase-adm02-panel05/qa.json) y [comprobaciones adicionales](../evidence/ase-adm02-panel05/smoke.json). Se preservan tres intentos fallidos del harness: [permisos del archivo privado de aprobación](../evidence/ase-adm02-panel05/failed-attempt-1.json), [expectativa HTTP de acceso denegado](../evidence/ase-adm02-panel05/failed-attempt-2.json) y [transporte de inyección de ACK](../evidence/ase-adm02-panel05/failed-attempt-3.json). El primero fue bloqueado por la guarda existente hasta restaurar modo0600. El segundo esperaba403 donde el router aislado mantiene503 genérico. En el tercero, `route.fetch()` de Playwright no llevaba Sec-Fetch-Site; el servidor rechazó correctamente antes del write. La inyección conserva ahora metadata same-origin del navegador sin sustituir cookie/CSRF/autoridad. No se relabelan esos intentos como PASS ni se repiten las admisiones/fixtures.

Inspección de capturas reales: filtro abierto [Chromium1440](../evidence/ase-adm02-panel05/screens/chromium-1440-status-expanded.png), [WebKit390](../evidence/ase-adm02-panel05/screens/webkit-390-status-expanded.png), panel [1366](../evidence/ase-adm02-panel05/screens/chromium-1366-final.png) y asistente [390](../evidence/ase-adm02-panel05/screens/chromium-390-wizard.png). Menú abierto190×186px, sin overflow horizontal. Dismissal/foco/scroll/confirmación verificados en ambos motores a1440×900,1366×768,390×844. Safari físico y aceptación visual siguen pendientes.

Preview: `https://127.0.0.1:50775/internal/media-review/index.php?section=equipo`, hasta **2026-10-19 18:19:50 UTC−06:00**. Loopback, infraestructura0700 propia, datos exclusivamente artificiales. Asesores/Activos y Bruno ACTIVE seleccionado, controles autorizados disponibles. Fernanda conserva caso/documentos pendientes de revisión; hay borradores e invitaciones sintéticos para inspección. Duración de infraestructura24h; no prolonga tokens, invitaciones ni MFA.

Reabrir sesión legítima y Chromium aislado: `python3 /tmp/mxmed-panel05-review-82a246q_/control.py open`. Finalizar sólo este preview: `python3 /tmp/mxmed-panel05-review-82a246q_/control.py stop`. Guía privada y solicitante en contexto separado, sin contraseñas en producto ni Git. La URL sola no autentica otro navegador.

[Verificación final](../evidence/ase-adm02-panel05/verification.json): pins, sintaxis, enlaces, fuentes protegidas y listeners originales. La actualización final de la restricción de versión de credencial del lector solicitante se hizo en el handoff inactivo entre preparación de fixtures y QA; aprobación local propia se volvió a fijar con hashes exactos antes de liberar las pruebas. Sin cambios a fuentes oficiales ni servicios previos.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | b356b06e, PANEL04 y mapas vigentes consultados. |
| AFFECTED_CAPABILITY_IDS | ID-07, COM-10; ID-01/02 reutilizados sin ampliación. |
| AFFECTED_JOURNEY_IDS | J09, navegación/alta/recuperación y edición autorizada del equipo. |
| AFFECTED_DATA_OBJECTS | Casos/versiones/completions, recibos de operaciones e invitaciones, internal_staff y grants; sin schema nuevo. |
| AFFECTED_CONTEXT_FILES | PANEL05, sucesor PANEL04, estado/router, inventario, interacciones, datos. |
| CONTEXT_CHANGES_IMPLEMENTED | Dismissal, recuperación explícita, feedback sintético, búsqueda autorizada y filtros. |
| CONTEXT_VERIFICATION | 88 checks + 5 adicionales PASS; Chromium/WebKit, tres viewports; diff, sintaxis, enlaces y hashes revisados. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Mismo commit aislado que implementación/evidencia; hash en entrega. |
| REMAINING_CONTEXT_GAPS | Aceptación visual pendiente; límites comerciales y de operación heredados preservados, fuera de estas seis correcciones. |
