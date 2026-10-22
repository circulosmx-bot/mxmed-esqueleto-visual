# ADVISOR-OPERATOR-SUPPORT01 — soporte de operadores desde el Asesor

Baseline: `46f8960d7e567d9a90bbba3a1d836926e9caabd8`, STAFF-WORKSPACE-VIS01 y validación STAFF-GRANTS-E2E01 sin cambios de fuente. Rama aislada `feature/mxmed-advisor-operator-support01`. Decisión: solicitud explícita del Director; pestaña Operadores y colaboradores solamente. Aceptación visual pendiente.

## Política y alcance autorizado

**PRODUCT_POLICY:** un titular y hasta dos colaboradores. Administrador es un nivel dentro de esas posiciones; no una tercera plaza. Pending/active/suspended cuentan por persona, las invitaciones pendientes no vencidas reservan plaza y revoked libera conservando historia. Staff de plataforma no ocupa posición ni suplanta al titular.

Ficha UI-2: la pestaña existente estaba restringida por falta de consumidor. Ahora presenta diagnóstico contextual mediante el mismo shell, tabs, componentes y estilos; se añade el control individual correspondiente en la matriz del Director. Sin este cambio seguiría sin consulta de soporte. Riesgo principal: confundir funciones delegadas con acceso efectivo; el panel distingue ambas y no ofrece botones de mutación. Rollback: revertir esta feature y conservar fuentes/auditoría; el catálogo deja de reconocer el grant nuevo, sin borrar registros. No se modifica 8091.

## Implementación y autoridad

**SOURCE_IMPLEMENTATION:** [AdvisorSupportReader](../../modules/identity/operators/AdvisorSupportReader.php) lee sólo las fuentes canónicas. [InternalCapabilityCatalog](../../modules/identity/services/InternalCapabilityCatalog.php) añade `physician_operator_support_read`, operacional y explícitamente delegable. Director usa el writer y auditoría existentes; no se asigna desde SQL ni desde un flag de cliente.

**CONNECTIVITY:** `Operadores y colaboradores` → GET `/api/internal/workspace/requests.php?mode=operators&doctor_id=…` → lector. El boundary HTTP existente mantiene sesión, transporte, error seguro y auditoría de denegación. `EvidenceAuthority::transaction` revalida sesión/cuenta/credencial; se comprueban staff y grants vigentes bajo locks y el ámbito de perfil permitido por Requests o Media. La capacidad nueva no descubre médicos adicionales. También se respeta `WorkflowScope` cuando está configurado. El endpoint normal conserva su composición cerrada; sólo el router owned inyecta el servicio en este ensayo.

`PendingSummary` proyecta disponibilidad de la pestaña; no añade tareas ni modifica conteos. Las aplicaciones sin perfil no habilitan soporte. Una nueva petición tras revocación devuelve denegación; refresh, foco y polling existentes actualizan el panel. Se limpian datos previos al consultar y se descartan respuestas de otro contexto. No puede borrarse información que el usuario ya leyó, pero la sesión abierta no conserva autoridad para nuevas consultas.

| Información | Fuente y semántica |
|---|---|
| Titular | `auth_account_memberships`, owner activo de perfil, cuenta activa y verificada; ausencia/ambigüedad explícitas |
| Relaciones | Membresías del perfil y `auth_accounts`; estados de cuenta, contacto y relación separados |
| Alias/posición | `auth_profile_operator_presentations`; sin derivar nombres de account_id o correo |
| Invitación | `auth_profile_operator_invitations`; accepted permanece aceptada aunque su vencimiento histórico haya pasado; pending vencida se proyecta vencida sin escribirla |
| Vínculo | `accepted_membership_id`, nunca similitud de nombre/correo |
| Funciones | Última revisión de `auth_profile_operator_grant_revisions` y claves de `auth_profile_operator_grants` |
| Acceso | Se distingue vigencia de delegación de permiso efectivo en el consumidor, que aún valida plan, relación, sesión, cuenta y titular |
| Cupo | Misma regla de `ProfileOperatorService::seats`; personas distintas más reservas, no roles comerciales adicionales |
| Eventos | `platform_audit_events`, perfil exacto, whitelist de eventos de operadores y proyección de operación/fecha/referencia |

Fechas en español de México y zona America/Mexico_City, identificadas CDMX. No se exponen correo de destinatario, tokens, secretos, factores ni datos clínicos. Hasta 100 invitaciones y eventos recientes, con límite indicado; inventario de más de 500 relaciones falla cerrado, no aparece como vacío. Las tablas ausentes producen indisponibilidad, no un falso estado vacío.

**Acciones disponibles:** consulta, actualización y orientación hacia el titular o el flujo Identity correspondiente. `support_actions=[]`. `ProfileOperatorService::command` y `overview` siguen siendo owner-only; no se invocan con la identidad del asesor ni se modifica OperatorHttp. No existe delegación canónica de mutaciones staff en esta entrega.

## Verificación y limitaciones

**VERIFICATION:** [53 comprobaciones dirigidas](../evidence/advisor-operator-support01/focused.json): concesión por UI, consulta, revocación con sesión abierta, restauración explícita, ausencia de ámbito con sólo el grant nuevo, invitaciones pending/accepted/cancelled/expired, relaciones suspended/revoked, cupo, comparación con overview canónico del titular, conservación de auditoría, denegaciones, CSRF, seis entradas del índice, teclado y Chromium/WebKit. Chromium: 1366, 820 y 390 px; WebKit: 390 px. Ninguna suite histórica ni STAFF-GRANTS-E2E01 se repitió.

Fixture separado `qa_target@example.test`: `media_review_read` preexistente en su bootstrap sintético; `physician_operator_support_read` concedido/restaurado mediante Director. Irene conserva exactamente `physician_request_read` y `media_review_read` y todos sus registros previos en la instancia anterior.

Las invitaciones, aceptaciones, cambios de estado y eventos se generan con el servicio OP01 y autenticación canónica de sus actores en otro schema vacío **del mismo datadir nuevo owned**. Se transfieren únicamente sus filas sintéticas exactas y hashes de auditoría al schema de revisión, sin recrear eventos ni cambiar IDs canónicos. Se omiten IDs auto incrementales locales de perfiles; doctor_id permanece idéntico. No se amplía ninguna factory de producto. El guard del SQL de presentaciones se adapta sólo al nombre exacto del schema recién verificado; el archivo de migración no cambia. Se corrige el delimitador de su trigger en el adaptador CLI. Los intentos fallidos de preparación y sus diagnósticos quedan privados, sin reutilizar fuentes parciales.

El panel médico y el panel operador **no están habilitados en este runtime**. Se compararon registros y el resultado real de `ProfileOperatorService::overview`, no sus pantallas. El índice conserva seis entradas y señala ese límite. Safari físico no probado. La consulta básica continúa sin un nuevo evento individual de actividad: se conserva la limitación aceptada, sin logging universal.

**OPERATIONAL_STATUS:** infraestructura nueva desechable, HTTPS loopback, datos ficticios, notificación en memoria, sin envío externo, activación de producción, merge ni cambios del runtime original. Caducidad de infraestructura de 24 horas; autenticación conserva su vigencia original.

## Revisión manual

- Director: `https://127.0.0.1:60907/internal/media-review/index.php?section=equipo`
- Asesor: `https://127.0.0.1:60907/internal/staff-workspace/index.php`
- Expiración: **22 de octubre de 2026, 19:57:49, CDMX (UTC−06:00)**.
- Cuentas sintéticas: `qa_director@example.test` y `qa_target@example.test`.
- Perfil inicial: `op01_support`, con Lucía, una invitación pendiente y una revocada. `op01_support_states` contiene suspensión/baja; `op01_support_history` aceptación histórica y vencimiento; Bruno demuestra el estado vacío.

Comando base:

```sh
python3 scripts/advisor-operator-support01-review.py /tmp/mxmed-ase-adm02imp01-6rfw0b87 open
```

Ejecutar desde este worktree. Reemplazar `open` por `credentials-director` o `credentials-advisor` copia la contraseña sintética; `mfa-director` copia el código vigente; `stop` señala al runner propietario para detener sólo esta infraestructura. No se importan cookies, se prolongan tokens ni se activan privilegios al abrir ventanas. La pestaña de consulta no requiere una ceremonia nueva de contraseña/MFA; no cambia los controles privilegiados existentes.

Prueba manual: Director selecciona el asesor de prueba → ACCESO Y FUNCIONES → Operadores y colaboradores. Asesor consulta los tres perfiles indicados. Director revoca sólo el permiso de soporte y guarda; Asesor actualiza y queda sin consulta, mientras Imágenes sigue disponible. Si se desea volver a la demostración, el Director debe conceder explícitamente el permiso y confirmar. No editar a Irene para esta prueba.

Reproducción técnica: `python3 scripts/advisor-operator-support01-qa.py` prepara recursos nuevos, usa sólo setup histórico, ejecuta los checks dirigidos y conserva la instancia hasta su caducidad. No ejecutar contra schemas anteriores ni sustituir el target por una base real. Evidencia detallada y capturas de esta entrega: `/tmp/mxmed-ase-adm02imp01-6rfw0b87/support-evidence`.

## CTX-SYNC01

```ini
CONTEXT_BASELINE=46f8960d7e567d9a90bbba3a1d836926e9caabd8; mapas CTX-MAP02/03 conservados
AFFECTED_CAPABILITY_IDS=OP-01,OP-02,OP-03; physician_operator_support_read
AFFECTED_JOURNEY_IDS=J07
AFFECTED_DATA_OBJECTS=auth_account_memberships,auth_accounts,auth_profile_operator_invitations,auth_profile_operator_grant_revisions,auth_profile_operator_grants,auth_profile_operator_presentations,internal_operator_grants,platform_audit_events
AFFECTED_CONTEXT_FILES=docs/ESTADO_ACTUAL_MXMED.md; docs/README.md; docs/contexto/INVENTARIO_FUNCIONAL_MXMED.md; docs/contexto/INTERACCIONES_MXMED.md; docs/contexto/DATOS_Y_MIGRACIONES_MXMED.md; docs/identity/ADVISOR-OPERATOR-SUPPORT01.md
CONTEXT_CHANGES_IMPLEMENTED=Consumidor de soporte acotado, grant, fuentes, UI, pruebas y limites operacionales documentados
CONTEXT_VERIFICATION=Diff completo, enlaces y paths; evidencia dirigida y correspondencia lector/catalogo/UI
CONTEXT_SYNC_STATUS=PASS_UPDATED
DOCUMENTATION_COMMIT=Commit mixto de esta entrega; consultar git log -1 -- docs/identity/ADVISOR-OPERATOR-SUPPORT01.md
REMAINING_CONTEXT_GAPS=Sin autoridad de mutacion staff; sin comparacion de pantallas medico/operador; aceptacion visual pendiente
```
