# ASE-INT02B — matriz de permisos conectada a comandos individuales

Padre verificado `05e428cf4354b1f722090c7532ced3784e0d4985`, rama `feature/ase-int02a-governed-advisor-grants`. Trabajo exclusivo en `feature/ase-int02b-team-permission-ui`, worktree `mxmed-ase-int02b-team-permission-ui`. Fuente oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`; visual aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565`; ASE-INT01 `09d3aefcf20266641d81aa128cec05f4a9e4ca03`. Ninguno se modifica ni promueve.

La solicitud ASE-INT02B autoriza conectar las interacciones aprobadas de la matriz, probarlas con datos sintéticos y publicar sólo esta feature. Clasificación **UI-2**, limitada a comportamiento, disponibilidad y mensajes de confirmación/resultado. No constituye autorización operacional ni aprobación nueva de políticas pendientes. Sucede la deuda `ASE-INT02A-UI-CONNECTION` del [backend padre](ASE-INT02A-governed-advisor-grants.md), preservando sus contratos y evidencia.

## Cinco ejes

| Eje | Estado |
|---|---|
| PRODUCT_POLICY | Grant/revoke operativo individual para ADVISOR activo. Director o Master con management y delegación exacta. Grupos sólo seleccionan claves elegibles; no crean autoridad. |
| SOURCE_IMPLEMENTATION | [Proyección](../../modules/identity/Team/ReadService.php) añade disponibilidad y claves editables; [pantalla](../../assets/js/internal-team.js) y [editor](../../assets/js/internal-team-editor.js) coordinan borrador, confirmación, secuencia y reconciliación. Writer, catálogo, locks, schema POST y auditoría permanecen iguales al padre. |
| CONNECTIVITY | GET read → GET CSRF → confirmación → POST individual → GET read. Conexión comprobada exclusivamente en runtime sintético nuevo. |
| VERIFICATION | Evidencia reproducible enlazada abajo: APIs reales, estado SQL, auditoría, carreras con dos conexiones, Chromium/WebKit y comparación visual. Safari físico NOT_RUN; móvil INTERIM_SMOKE_ONLY. |
| OPERATIONAL_STATUS | Gate ausente por defecto. Sin merge, despliegue, cambios de datos reales ni modificación de servicios originales. Infraestructura propia dispuesta al cierre. |

## Disponibilidad y ámbito

`ReadService` proyecta `authority.mutations_enabled` desde `MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED=1` y `items[].editable_capabilities` para el target exacto. Sólo cuenta activa con relación ADVISOR/ACTIVE, distinta del actor; claves operativas delegables y, para Master, delegación exacta vigente. No revela identidad del actor ni grants ocultos.

El cliente exige además bootstrap CSRF autenticado exitoso antes de habilitar controles. Error de bootstrap, lectura o autoridad cierra el canal. Un flag del navegador no habilita backend. El token permanece únicamente en memoria; `fetch` usa credenciales del mismo origen y Referer del mismo origen para el contrato existente de bootstrap. No almacenamiento local, URL, telemetría ni evidencia con tokens. Timeout de lectura/transporte: 15 segundos, incluido cuerpo de respuesta; no implica rollback de un comando enviado.

La proyección de disponibilidad **no sustituye** las comprobaciones del [MutationHttp](../../modules/identity/Team/MutationHttp.php) y del writer. No cambia `InternalGovernanceService::execute`, su precondición estricta, el catálogo ni el contrato de POST. Cada solicitud lleva exactamente operación, cuenta confirmada y clave individual. Backend revalida sesión, CSRF/origen, identidad, cuenta/staff, management, delegación y target bajo los locks canónicos.

Las cinco familias siguen presentes. Cuatro consumidores genéricos de perfiles y dos correcciones no habilitadas para Advisors permanecen en consulta. Gobierno reservado, edición de delegaciones, admisión, documentos, MFA, nombramientos/suspensiones Master y finanzas no se conectan. Las claves existentes de proveedores conservan sus controles propios; esta pantalla no realiza sus operaciones ni pagos.

## Borrador, confirmación y navegación

Cada selección lee el target exacto y crea un Set local de grants visibles. Grupo selecciona/deselecciona sólo claves editables; override individual y estado mixto se conservan. En Master los permisos no expuestos son UNKNOWN, deshabilitados e indeterminados: jamás se convierten en revokes. Revertir descarta únicamente el borrador; no envía compensaciones.

Cambiar integrante, categoría, página, búsqueda o sección exige descartar explícitamente cuando hay borrador; cancelar conserva el contexto. Salida del documento usa la advertencia nativa `beforeunload` cuando el navegador la admite; no se promete bloquear cierres forzados del sistema. Durante envío se bloquean guardado duplicado, cambios de target y edición.

Antes de confirmar y después de aceptar el modal se relee el target. Cambio material de grants, estado, ámbito o catálogo detiene el flujo y exige nueva propuesta. El modal aprobado muestra referencia de cuenta (nombre personal honestamente no disponible), cantidades de concesiones/retiros, etiquetas españolas y consecuencias sensibles. Cancelar/Escape no envía comandos; foco inicial en Cancelar.

## Resultado individual y reconciliación

La transacción es **por comando**, nunca por grupo o guardado completo. La secuencia mantiene el mismo target y espera la respuesta y lectura de cada comando antes del siguiente. Un resultado exitoso exige ACK canónico con operación/target/clave/estado correspondientes. No hay retry automático ni compensación.

- ACK confirmado: se conserva como éxito individual aunque un comando posterior falle.
- Rechazo 400/401/403/405/409/413/415: solicitud no aplicada; detiene la secuencia. 409 nunca es éxito.
- 503, timeout, respuesta inválida o acuse perdido: resultado incierto; detiene la secuencia y relee sin reenviar.
- Restantes: **No intentado**; no se inventan fallos ni eventos de auditoría.

Después de cada intento, la lectura canónica se compara con los grants esperados y el ámbito confirmado. Una inconsistencia detiene los comandos restantes. Sólo todos los ACK confirmados más lectura consistente permiten **CAMBIOS CONFIRMADOS**. Éxitos anteriores con interrupción muestran **CAMBIOS APLICADOS PARCIALMENTE**. Lectura fallida mantiene **ESTADO PENDIENTE DE CONFIRMAR**, bloquea edición y ofrece consulta explícita.

Una lectura que muestra el grant deseado no atribuye el cambio al comando incierto. El resultado individual sigue incierto y su texto lo declara; la consulta posterior tampoco reescribe el historial como éxito. Tras reconciliar el estado visible se puede preparar un nuevo borrador y confirmar nuevamente. Nunca se restaura automáticamente un grant retirado por otro actor. El conteo del detalle y la tarjeta seleccionada se actualizan con la lectura canónica.

## QA y evidencia

[Runner ASE-INT02B](../../scripts/ase-int02b-qa.py) reutiliza la preparación parametrizada de [ASE-INT02A](../../scripts/ase-int02a-qa.py), [fixture canónica CLI](../../modules/identity/tests/AdvisorMutationFixture.php) y [allowlist propia](../../scripts/ase-int02b-router.php). Sus valores predeterminados conservan el uso de ASE-INT02A; la nueva fase especifica rama, prefijo, baseline, router y destino de evidencia propios. No se reescribe evidencia histórica.

Cada ejecución crea MySQL con `--no-defaults`, datadir nuevo, schema `mxmed_gate4d_preview_aseint02b_<aleatorio>`, Valkey privado y TLS firmado en puertos loopback nuevos. Sólo allí se habilita el gate. Sesiones derivadas de autenticación Identity canónica sintética; HTTP usa el principal restringido `ase_writer`. Fixture y bloqueo concurrente usan conexión propia de esa misma instancia; ninguna conexión a mxmed, QA05, OP02 o UI03. No importación de datos reales ni acceso externo de negocio.

**Resultado: 303 checks PASS, 84 solicitudes individuales del navegador sin concurrencia entre ellas, 83 eventos canónicos inspeccionados, tres carreras SQL reales, ocho combinaciones de navegador/tamaño y 70 capturas.**

Evidencia final: [QA y hashes de fuente](../evidence/ase-int02b/qa.json), [navegadores, respuestas API, auditoría y comparación](../evidence/ase-int02b/browser.json), [capturas](../evidence/ase-int02b/screenshots/), [disposición de intentos fallidos](../evidence/ase-int02b/disposal.json).

Casos A–Z: grant/revoke, grupo/override/mixto/revertir, preservación de grants ajenos y ocultos, Master exacto, Advisor denegado, Master target en consulta, guardas de navegación, expiración TTL real de sesión, pérdida de management/delegación, suspensión actor/target, conflicto por otra escritura canónica, 409, fallo nativo de auditoría 503 y rollback, commit con ACK perdido, refresh fallido, seguimiento explícito sin atribución, parcialidad, doble submit, secuencialidad y schema individual, bootstrap/CSRF fallidos, gate ausente con UI readonly. Se inspeccionan SQL y eventos `INTERNAL_GOVERNANCE_CHANGED`, no sólo mocks de red.

Las carreras independientes retienen lock de cuenta actor/target mientras el writer HTTP espera; `performance_schema.data_lock_waits` registra transacciones/threads distintos. Después se confirma suspensión o revocación por la conexión bloqueadora: el writer deniega. Las pruebas de acuse perdido primero ejecutan el POST real y luego abortan únicamente su entrega al navegador. Las de 503 provocan fallo real de append de auditoría en la instancia desechable.

Chromium y WebKit: 1440×900, 1366×768, 820×1180 y 390×844. Se capturan borrador, modal, progreso, éxito, parcialidad/incertidumbre y conflicto. Tres CSS aprobados byte-idénticos; geometría compartida, grid, fuentes, colores y radios comparados contra `312ee388`. No claim de diferencia de píxeles cero: copy de disponibilidad, confirmación y resultados cambia por el alcance UI-2 expresamente solicitado. No rediseño, estilos nuevos ni aceptación ficticia de Safari físico/móvil final.

Los intentos fallidos se conservan con su resultado: dos errores de preparación de comparación visual (CSS con ruta absoluta y selector genérico de panel no homólogo) y un error de renovación de fixture al intentar autenticar actores deliberadamente deshabilitados. Se corrigió el ensayo, no se relajaron controles del producto. Sus procesos fueron detenidos por el runner; la disposición posterior verifica ownership antes de retirar sus directorios privados.

## CTX-SYNC01

| Campo | Registro |
|---|---|
| CONTEXT_BASELINE | Padre `05e428cf`; oficial `f92f37f1`; ASE-INT01 `09d3aefc`; visual `312ee388`; IW01/ASE-ALIGN01 V2 y CTX-MAP02/03 preservados. |
| AFFECTED_CAPABILITY_IDS | ID-07, COM-10; ID-02/ID-03 por reuso de sesión/CSRF. Sin nuevas claves del catálogo. |
| AFFECTED_JOURNEY_IDS | J09 conexión de matriz/gobierno; J08 sólo preservación de límites comerciales. |
| AFFECTED_DATA_OBJECTS | auth_accounts, auth_account_credentials, internal_staff, internal_capability_delegations, internal_operator_grants, platform_audit_events, platform_audit_stream_heads y store canónico de sesión. Sin schema de producto nuevo. |
| AFFECTED_CONTEXT_FILES | Este contrato, estado, router, sucesores ASE-INT01/02A, IW01, inventario ID-07, interacciones J09, datos, trazabilidad COM-10 y registro visual; evidencia nueva ASE-INT02B. |
| CONTEXT_CHANGES_IMPLEMENTED | Disponibilidad autoritativa, UI individual, grupos sólo locales, borrador/confirmación, ACK y reconciliación, QA real aislada, UI-2 y límites operacionales. Deuda de conexión UI acotada sucedida. |
| CONTEXT_VERIFICATION | Diff y nuevos archivos revisados, lint, evidencia vinculada por SHA-256, pruebas API/SQL/auditoría, ocho combinaciones visuales, carreras reales, enlaces locales y preservación de worktrees/listeners. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED |
| DOCUMENTATION_COMMIT | Mismo commit de implementación/evidencia; resolver con `git log -- docs/identity/ASE-INT02B-team-permission-ui.md` y reporte de entrega. |
| REMAINING_CONTEXT_GAPS | Admisión, documentos, perfiles personales, nombramiento/delegaciones Master, MFA, readers comerciales, D01–D08, siete políticas ADM, Safari físico/móvil final y cualquier activación/merge/despliegue. |

NEXT_AUTHORIZATION_REQUIRED: revisión/promoción y cualquier activación real requieren autorización separada; Master/admisión/comercial y consumidores pendientes mantienen sus gates propios.

## Sucesor acotado ASE-INT03A

[ASE-INT03A](ASE-INT03A-advisor-lifecycle.md) sucede sólo la desconexión de suspensión/reactivación Advisor. Reutiliza botones/modal/coordinador, impide lifecycle con borrador pendiente y conserva readonly de permisos en suspendidos. Writer canónico bajo locks, ACK/refresh y resultados inciertos sin retry. Este contrato y sus 303 checks permanecen como evidencia histórica del padre; no se repite la suite completa ni se activa operación real.
