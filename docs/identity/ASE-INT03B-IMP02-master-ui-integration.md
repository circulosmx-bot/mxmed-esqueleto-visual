# ASE-INT03B-IMP02 — conexión privilegiada en Equipo

Estado: READY_MXMED_ASE_INT03B_IMP02_MASTER_UI_INTEGRATION_COMPLETE, limitado a integración aislada y QA sintética. Padre ca8a6181db01eb32ee4e19b07fa46c0b53c4f1c7; rama feature/ase-int03b-master-ui, worktree propio. Oficial f92f37f y visual312ee388 preservados.

## Ficha UI-2 autorizada

La solicitud IMP02 autoriza expresamente conectar el comportamiento aprobado en la pantalla Equipo: controles de designación, gestión y delegación, mismo modal y familias. Antes sólo consulta; ahora cada acción exacta requiere confirmación, caso del servidor, contraseña Director, TOTP, ejecución única y reconciliación caso/UUID/audit. Razón: conectar fundación IMP01 sin duplicar writer. Alternativa sin cambio: controles inactivos. Riesgo: acciones privilegiadas, mitigadas por gates/autoridad canónicos y prueba sintética. Rollback: gate off y revertir conexión aislada, nunca compensar un cambio confirmado. No cambio CSS, layout, navegación ni UI-3. No se requiere nueva autorización del concepto ya aprobado.

Appointment producto permanece BLOCKED_PENDING_STAFF_IDENTITY_REVIEW. Reader real ausente, botón visible no disponible con razón española; no adapter sintético seleccionable por HTTP. Route D BLOCKED_HISTORICAL_ACT03_CHECK permanece intacta. No despliegue, merge ni readiness operacional. COM recurrente, D01–D08 y siete gaps ADM preservados.

## Conexión y disponibilidad

[ReadService](../../modules/identity/Team/ReadService.php) añade `items[].master_actions`: estado, motivo de appointment ausente y operaciones/capability exactas. Sólo Director, target distinto del actor y gate Master habilitado. MASTER_ADMIN válido admite reducción; aumentos requieren staff ACTIVE/cuenta active. UNKNOWN cierra acciones. Proyección sólo orientativa: cada POST conserva checks del caso/writer bajo locks. No nueva capability, writer ni enum.

[Bootstrap existente](../../modules/identity/MasterGovernance/Http.php) añade estado de factor MFA desde `enrollment_context` canónico, cuenta exacta y bloqueo vigente; no expone ciphertext ni secretos. Composición/schema/keys/autorización siguen siendo obligatorios. No enrolamiento HTTP. El cliente necesita bootstrap exitoso además de proyección. GET de navegador sin Origin exige Referer bajo origen exacto y Fetch-Site same-origin; POST conserva Origin exacto, HTTPS firmado y CSRF. No relajación CORS. Gate Advisor no habilita Master.

La propuesta Master aparece inactiva con explicación española. Email verificado, cuenta ACTIVE y factor MFA no se presentan como revisión interna. No hay selector de adapter sintético, flags del navegador ni excepción de cuenta. Los datos personales y documentos siguen sin fuente conectada. Los cuatro poderes se conservan separados: designación, gestión de Advisors, delegación exacta y uso operativo.

## Secuencia y UI-2

[Controlador Master](../../assets/js/internal-team-master.js), [pantalla](../../assets/js/internal-team.js) y [modal existente](../../partials/identity/internal-team.php): seleccionar target/acción → mostrar consecuencias y acknowledgement → releer target → POST open → mostrar intención servidor/cuenta/categoría/estado/capability/caso/UUID/expiración → POST confirm con contraseña propia → POST verify TOTP → informar MFA_VERIFIED sin anunciar mutación → ejecutar una vez → GET read del caso/UUID/audit → refresh de target y proyección.

Los endpoints son los ocho existentes de IMP01; no cambia el cuerpo exacto de ningún POST. La UI usa `csrf`, `open`, `confirm`, `verify`, `execute`, `read`, `cancel` y `expire`. Case y UUID no son secretos y permiten localizar el resultado, pero no conceden autoridad. Expiración presentada en UTC. Master y Advisor no reciben botones habilitados por un rol visual.

Gestión se mantiene en el bloque aprobado separado de permisos operativos. Checkbox de gestión/delegación abre una revisión individual; el estado marcado vuelve al valor servidor hasta confirmar resultado. Familias contraíbles conservadas; padres de delegación no ejecutan grupos. Cada key requiere caso/password/proof nuevo; una delegación no concede uso. Democión explica ADVISOR, revocación gestión/delegaciones, conservación grants/historia/suspensión y no reactivación de cuenta disabled; ninguna inferencia comercial.

Se reutiliza dialog nativo, labels, estilos `wizard-field`, acknowledgement y botones existentes. Foco al input de la etapa; Tab/Shift+Tab recorren explícitamente los controles visibles habilitados del modal para conservar foco también en WebKit; Escape cancela cuando es seguro y sólo cierra un resultado terminal ya confirmado/cancelado, sin intentar cancelar una mutación consumida. Durante envío o resultado desconocido no cierra/traslada el flujo. Al cerrar restaura foco a control conservado o encabezado del target actualizado. No CSS/layout nuevo ni rediseño.

## Borradores, secretos y reconciliación

[Editor Advisor](../../assets/js/internal-team-editor.js) sólo expone estado dirty/busy/uncertain para coordinar navegación. Los borradores se guardan, revierten o conservan cancelando; nunca se descartan automáticamente al iniciar gobierno. El caso activo bloquea cambios de miembro, filtro, página, búsqueda y enlaces internos; `beforeunload` advierte salida, sin prometer impedir cierre forzado del navegador.

Password y código usan inputs privados y se vacían al enviar. Challenge/proof/CSRF sólo viven en memoria del flujo; se limpian al consumir, fallar o cerrar. No localStorage/sessionStorage, logs, analytics, URLs o metadata audit con secretos. Capturas QA enmascaran inputs y no usan HAR/traces con cuerpos. No captura de credenciales reales.

La respuesta execute nunca basta: read debe validar caso exacto, operation UUID/intent digest, target/capability y resultado CONSUMED con evento audit canónico. La lectura backend verifica correlación y actor/target. Timeout/503/respuesta inválida/ACK perdido no provocan retry: se conserva referencia exacta y se consulta; si no se resuelve aparece **ESTADO PENDIENTE DE CONFIRMAR** con consulta explícita. OPEN no se atribuye como éxito; se solicita cancelar/revisar antes de un caso nuevo. No compensación automática. Rechazo/expiración limpia proof y requiere autorización nueva.

## Verificación autorizada y límites

[Runner](../../scripts/ase-int03b-ui-qa.py) usa lifecycle de infraestructura nueva y regresión de seguridad dirigida IMP01 más [navegadores](../../scripts/ase-int03b-ui-browser.py). Prefijo propio ase-int03bui, MySQL/Valkey/TLS nuevos con credenciales explícitas, sólo cuentas ficticias. No clon/consulta de bases reales. Fixture CLI admite únicamente este nuevo prefijo junto al anterior, sin ser accesible en HTTP. Autorización privada de QA generada para su fingerprint/hash, nunca en runtime original.

Positivos HTTP/UI: management grant/revoke, delegation/revoke y democión. Appointment positivo sólo backend CLI con adapter sintético, heredando verificación nativa; **no positivo full-stack appointment**. Presentación producto confirma bloqueo y razón. La presentación positiva appointment se prueba por separado en DOM mediante el mismo formatter puro de consecuencias, sin sesión, endpoint, proof ni capacidad de ejecución; no acredita éxito privilegiado. Route D se vuelve a observar bloqueada en rehearsal; no reparación ni bypass de CHECK.

Estados UI: NO_AUTHORITY, MASTER_GATE_DISABLED, MISSING_IDENTITY_REVIEW, MISSING_MFA_FACTOR, READY_FOR_EXACT_ACTION, PENDING_CONFIRMATION, REAUTHENTICATION_REQUIRED, MFA_CHALLENGE_PENDING, MFA_VERIFIED, EXECUTION_PENDING, CONFIRMED, CONFLICT, EXPIRED, CANCELED, UNKNOWN_OUTCOME y READ_UNAVAILABLE. No enums SQL. Safari físico NOT_RUN; móvil INTERIM_SMOKE_ONLY. Cierre: **812 checks PASS**, nueve carreras con locks observados, ocho combinaciones motor/viewport y 64 capturas enmascaradas. Cinco operaciones positivas HTTP/UI en cada combinación; nombramiento HTTP no habilitado.

## Alcance preciso de las inyecciones de fallo

Los positivos ordinarios de navegador usan fetch del producto sin sustituir respuestas ni alterar sus datos: el observador de open comprueba EXECUTION_PENDING y botones bloqueados, captura loading y continúa la petición original. Para ACK perdido, Playwright reenvía la petición real al mismo origen conservando cookie/cuerpo/CSRF/proof y completa Origin, Fetch-Site y Referer que el navegador aún no había adjuntado en el punto de routing; después aborta la respuesta al cliente. No fabrica una respuesta de éxito ni omite checks backend. En UNKNOWN_OUTCOME se bloquea además read, luego una consulta explícita obtiene el caso consumido y su audit; execute se contabiliza una sola vez.

Para fallo audit se instala un trigger exclusivamente en MySQL descartable, se regenera la autorización privada de ese fingerprint y se reinicia sólo su PHP. Así se alcanza el rollback transaccional real en lugar de un rechazo anterior por schema cambiado: proof VERIFIED, caso OPEN y ningún cambio audit de gobierno. Se retira el trigger propio y se vuelve a vincular la autorización de QA antes de cancelar. Nada de esto modifica configuración ni schema originales.

Expiración visual usa avance del reloj del navegador; expiración de caso/proof/reauth se prueba además contra el backend canónico en fixtures nativos. Las nueve carreras usan conexiones independientes y espera de locks observada; no implican atomicidad distribuida SQL/Valkey. Cada flujo browser obtiene un factor sintético mediante el enrolamiento CLI canónico para evitar reutilizar el mismo intervalo TOTP; no hay enrolamiento de personal real ni endpoint de enrolamiento añadido.

Los intentos fallidos se conservan como JSON sanitizados. Detectaron la ubicación del bloqueo MFA en enrollment_context, sincronización del harness móvil/lifecycle, conteo de auditoría por comando, metadatos de la interceptación y foco y restauración al encabezado en WebKit. Se corrigieron antes del cierre; no se reinterpretan como PASS. Los diagnósticos privados no registran valores de inputs y se eliminan junto a sus recursos propios tras verificar ownership y procesos detenidos.

## CTX-SYNC01

| Campo | Registro |
|---|---|
| CONTEXT_BASELINE | IMP01 ca8a6181db01eb32ee4e19b07fa46c0b53c4f1c7; oficial f92f37f y visual312ee388; PREP01, IW01, GOV03A, INT01/02A/02B/03A, R2/COM/ADM y protocolo UI contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-07, COM-10; ID-02/ID-03 reutilizados. |
| AFFECTED_JOURNEY_IDS | J09; J08 preservado como límite comercial. |
| AFFECTED_DATA_OBJECTS | Proyecciones de internal_staff/auth_accounts/grants/delegaciones y factor MFA; casos, challenges/proofs y audit existentes. Sin objetos físicos nuevos. |
| AFFECTED_CONTEXT_FILES | Estado, router, este contrato, IMP01, IW01, GOV03A, inventario, interacciones, datos/migraciones, trazabilidad comercial y registro visual. |
| CONTEXT_CHANGES_IMPLEMENTED | UI-2 individual, bootstrap/disponibilidad, secretos, borrador/contexto, reconciliación, elegibilidad ausente y route D bloqueada. |
| CONTEXT_VERIFICATION | Diff completo, vínculos/anchors, sintaxis, hashes de fuentes probadas y 812 checks sintéticos revisados; verificación final enlazada abajo. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Mismo commit aislado de implementación; hash se reporta al cierre. |
| REMAINING_CONTEXT_GAPS | Fuente autoritativa staff identity-review, reparación route D por separado, activación/despliegue no autorizados; D01–D08 y siete políticas ADM, Safari físico y aceptación móvil final pendientes. |

PRODUCT_POLICY: conexión UI-2 expresamente aprobada. SOURCE_IMPLEMENTATION: controlador y proyección, writer/seguridad reutilizados. CONNECTIVITY: HTTP canónico con gate propio. VERIFICATION: sintética acotada, nunca appointment HTTP positivo ficticio. OPERATIONAL_STATUS: inactivo, PRODUCT_MASTER_READY=false.

## Evidencia de cierre

- [QA canónica: 812 PASS, nueve carreras y rollback/aislamiento](../evidence/ase-int03b-imp02/qa.json).
- [Navegadores: ocho combinaciones y límites de appointment](../evidence/ase-int03b-imp02/browser.json); [índice y hashes de 64 capturas](../evidence/ase-int03b-imp02/screenshots.json).
- [Rehearsal A/B/C/E PASS, D bloqueada](../evidence/ase-int03b-imp02/migration-rehearsal.json); [fronteras de seguridad](../evidence/ase-int03b-imp02/security-boundaries.json).
- [Diez intentos fallidos: disposición verificada de recursos propios](../evidence/ase-int03b-imp02/failed-resources-disposal.json). La ejecución final también eliminó su datadir y detuvo sus procesos. BrokenPipe del proxy durante respuestas abortadas pertenece a la inyección de transporte, sin fallo de la suite final.
- [Verificación final, fuentes y archivos cambiados](../evidence/ase-int03b-imp02/final-verification.json): ocho worktrees/branches/HEADs limpios, listeners protegidos y pointer INT01 iguales al inicio. Fuentes producto/QA sin cambios respecto a la ejecución exitosa; CSS/catalog preservados por comparación con el padre.

Revisión visual directa de contraseña desktop Chromium/WebKit, contraseña móvil y resultado desconocido confirma continuidad de la composición aprobada y mensajes legibles, sin overflow horizontal. Las capturas son de página completa; el backdrop corresponde al viewport desplazado. No representan aceptación física Safari ni aceptación móvil final. No se dejaron servidores de esta fase activos.

## ACT03-CHECK-COMPAT01 — sucesor de compatibilidad SQL

[COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md) sucede sólo el bloqueo de migración D en ensayo SQL separado. Las 812 comprobaciones y la integración UI de este hito permanecen como evidencia histórica; no se repiten ni modifican controles/writers/MFA. Appointment real y activación siguen bloqueados.
