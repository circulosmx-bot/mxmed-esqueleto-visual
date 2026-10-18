# ASE-INT02A — grants individuales gobernados para asesores

Baseline padre `09d3aefcf20266641d81aa128cec05f4a9e4ca03`, rama `feature/ase-int01-internal-team-readonly`. Implementación exclusiva en `feature/ase-int02a-governed-advisor-grants`, worktree `mxmed-ase-int02a-governed-grants`. Fuente oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, prototipo aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565` y padre preservados.

El Director revisó la interfaz aislada y autorizó continuar con esta fase acotada de backend. No autoriza cambios visuales, operación real, merge ni promoción. Clasificación **UI-0 / BACKEND_COMPLETED_FRONTEND_DEFERRED_WITH_EXPLICIT_GATE**. [ASE-INT01](ASE-INT01-R2-internal-team-readonly.md), sus aprobaciones y evidencia histórica permanecen intactos en el padre. [IW01/ASE-ALIGN01 V2](../product-completion/iw01-internal-governance-foundation.md) sigue siendo el contrato canónico de gobierno.

## Cinco ejes

| Eje | Estado de esta entrega |
|---|---|
| PRODUCT_POLICY | Sólo grant/revoke individuales para ADVISOR existente y activo; Director o Master con management y delegación exacta. Políticas ADM y D01–D08 sin cambios. |
| SOURCE_IMPLEMENTATION | Adapter `Identity\Team\MutationHttp`; precondición opcional dentro del mismo `InternalGovernanceService::execute`. Sin segundo writer, tabla nueva ni motor de autenticación. |
| CONNECTIVITY | API → Identity/sesión/CSRF canónicos → writer/auditoría existentes. Conexión efectiva probada exclusivamente en infraestructura sintética nueva. UI sin handlers ni guardado activo. |
| VERIFICATION | 122 checks aislados PASS; siete carreras con conexión SQL bloqueadora independiente y writer HTTP, espera observada en `performance_schema.data_lock_waits`. Fuente vinculada por SHA-256. |
| OPERATIONAL_STATUS | Gate nuevo deshabilitado por defecto; sin despliegue, merge, promoción, cuentas/grants reales ni cambios al preview del padre. |

## Contrato HTTP exacto

[Adapter](../../modules/identity/Team/MutationHttp.php), [comando](../../api/internal/team/capability.php) y [bootstrap](../../api/internal/team/csrf.php).

- `POST /api/internal/team/capability.php`: objeto JSON plano con **exactamente** tres strings: `operation` (`grant` o `revoke`), `target` (referencia de cuenta ASCII `[A-Za-z0-9_.:-]`, 1–64 caracteres), `capability` (clave exacta del catálogo). Sin query, path adicional, campos repetidos/desconocidos, actor/rol/scope enviados por cliente, arrays, grupos o comandos masivos. Cuerpo máximo 1.024 bytes; `application/json`, opcional `charset=utf-8`.
- `GET /api/internal/team/csrf.php`: sin body/query; sólo cuenta/sesión/staff vigentes Director o Master con management. Emite `CsrfTokenService::issueAuthenticated` ligado al digest de la sesión; no nuevo motor de tokens. Advisor, anónimo y cuenta sin autoridad no reciben token.
- Ambos requieren composición Identity canónica y `MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED=1`. La variable es un gate explícito de despliegue, no una autorización; no se configura en entornos originales.
- HTTPS validado por `GovernanceTransport`: TLS nativo o firma del terminador mediante `MXMED_GOVERNANCE_PROXY_KEY`; un header de esquema reenviado no basta. Ningún secreto o token se registra en logs/evidencia.
- POST requiere `Origin` exactamente igual al de la composición y `X-CSRF-Token` autenticado vigente. Un `Sec-Fetch-Site` presente debe ser `same-origin`. Bootstrap permite ausencia de Origin sólo con `Sec-Fetch-Site: same-origin` y Referer bajo el origen exacto.
- `private, no-store`, no-cache, CORP same-origin, nosniff y CSP heredados de Team; ninguna apertura CORS.

Ejemplo de **forma**, sin token ni ejecución:

```json
{"operation":"grant","target":"referencia_del_asesor","capability":"media_review_read"}
```

Respuesta exitosa: `result=changed`, operación/target/capability, `stored_grant=ACTIVE|REVOKED`, `refresh_required=true`, `consumer_authority=independently_required`. Se emite sólo después del commit canónico. Un grant ya activo o revoke sin cambio devuelve **409 `conflict_refresh_required`**, nunca nuevo éxito. 400 input, 401 sesión, 403 autoridad/target/CSRF/origen, 405 método, 413 tamaño, 415 tipo; infraestructura/auditoría/resultado incierto → 503 genérico y refresh requerido. No reintentos automáticos ni detalle SQL en respuesta.

## Autoridad bajo locks y compatibilidad

El adapter no infiere identidad de la petición: usa `CanonicalHttpSessionResolver`, composición y validación actuales de credencial, estado, expiración y revocación. `ReadService::authority` protege el bootstrap y hace el rechazo inicial; **no es la garantía transaccional de mutación**.

El quinto argumento opcional de [InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php), `ACTIVE_ADVISOR_OPERATIONAL`, sólo lo fija código servidor. Se admite exclusivamente con grant/revoke y catálogo `kind=operational`, `delegable=true`. Sin ese argumento permanecen las operaciones y semántica de los callers IW01 existentes.

Dentro de la transacción del writer, después de bloquear cuentas/staff del par actor/target en orden:

1. Target existente: cuenta `active`, relación `ADVISOR/ACTIVE` y distinto del actor. Director/Master no pueden modificar un Master o Director mediante este endpoint.
2. Credencial del actor se relee con `FOR UPDATE` y se compara con la versión de sesión; sesión canónica revalidada después de la espera de locks.
3. Cuenta/staff del actor activos. Director conserva gobierno raíz; Master requiere `internal_advisors_manage` actual, target Advisor y delegación exacta activa/no revocada. Esas lecturas son `FOR UPDATE` para este scope, no snapshots viejos de MVCC. Advisor nunca administra grants.
4. Mismo writer de grant/revoke, conflictos e historia; mismo `INTERNAL_GOVERNANCE_CHANGED` y `JoinedPdoCanonicalAuditTransactionAdapter`. Fallo de auditoría revierte grant y head en la transacción propia. No se añaden compensaciones ni claims de transacción entre requests.

Las siete carreras verificadas cambian categoría del target, suspensión del target, estado de su cuenta, suspensión del actor, management, delegación y versión de credencial mientras el writer espera. Todos los requests se denegaron después del commit de la conexión bloqueadora. Los resultados registran IDs de transacciones/threads distintos. No se promete atomicidad distribuida entre Valkey y SQL: la vigencia de sesión se comprueba canónicamente tras locks; el ciclo de sesión puede tocar su store.

## Grants almacenados, uso y consumidores

El catálogo existente permanece idéntico: 18 entradas, 16 operativas delegables y dos reservadas excluidas del endpoint. No se inventan grupos ni comodines. Almacenar una capability no configura ni habilita su consumidor: los cuatro `profile_*` siguen sin consumidor genérico conectado; correcciones conserva sus restricciones de clase, proveedores su UI pendiente y acreditación/fuentes sus gates de caso, ámbito, MFA y runtime. Ownership, firma clínica, admisión, nombramientos y delegaciones no se habilitan.

`register`, `appoint_master`, `remove_master`, `suspend`, `reactivate`, `delegate` y `revoke_delegation` no tienen acceso mediante este adapter. Su uso en regresiones sólo verifica los callers canónicos históricos sobre fixtures sintéticas.

## UI diferida con gate explícito

**UI_STILL_READ_ONLY=true; GROUP_SAVE_NOT_ENABLED=true.** JS, template y cuatro CSS del equipo son byte-idénticos al padre; el read model sigue indicando `mutations_enabled=false` para la UI. No se cambia copy ni se monta un handler.

Deuda acotada `ASE-INT02A-UI-CONNECTION`, responsable: Director para autorización y equipo de implementación para una fase posterior. Pantalla: Equipo y permisos original. Razón: este alcance autoriza seguridad backend exclusivamente. Riesgo: presentar un bulk-save atómico inexistente o éxito falso ante pérdidas de respuesta. Estado temporal: consulta, botones de cambio deshabilitados. Desbloqueo: autorización expresa del comportamiento y QA de reconciliación parcial.

Conexión futura exacta:

**Checkboxes agrupados aprobados → confirmación explícita por operación → POST individual autorizado → resultado canónico → nueva lectura autoritativa → presentación de éxito/fallo parcial.**

El padre de grupo seguirá siendo una representación visual de claves individuales. Nada de autosave, API de grupo, retry automático tras timeout, revocaciones compensatorias ocultas ni restauración automática de grants retirados por otro administrador. Con acknowledgement perdido se relee antes de decidir si aún procede otra operación. La atomicidad actual es por request; varios requests pueden tener éxito parcial.

## QA sintética y disposición

[Runner](../../scripts/ase-int02a-qa.py), [fixture CLI](../../modules/identity/tests/AdvisorMutationFixture.php), [router aislado](../../scripts/ase-int02a-router.php) y [resultado final](../evidence/ase-int02a/qa.json).

Cada ejecución crea un datadir MySQL nuevo, schema `mxmed_gate4d_preview_aseint02a_<aleatorio>`, Valkey privado sin persistencia y puertos loopback nuevos. No consulta/copía schema ni filas de instancias existentes. Ningún pointer ASE-INT01 se reutiliza. TLS firmado utiliza las primitivas canónicas; el router sólo expone los tres endpoints Team de la prueba, sin UI ni otros writers. Sesiones obtenidas mediante autenticación canónica con cuentas/password exclusivamente sintéticos; tokens capturados fuera de Git.

El root de bootstrap pertenece sólo al MySQL recién inicializado y se usa por socket propio. HTTP usa **ase_writer**, no root: SELECT en cuenta/credencial/staff/delegaciones, SELECT/INSERT/UPDATE en grants, SELECT/INSERT en auditoría/heads, EXECUTE/LOCK TABLES acotados a la base propia y SHOW_ROUTINE para el verificador de rutina. El definer de auditoría tiene cuenta bloqueada, SELECT/UPDATE únicamente en heads y EXECUTE en esa base. La fixture usa otro usuario exclusivo de esa instancia. No se otorgan privilegios a usuarios existentes. Un despliegue posterior requiere revisión específica del principal y transporte; estos permisos de ensayo no son una receta productiva.

**Resultado final: 122 PASS.** Incluye A–X: grant/revoke Director/Master, independencia de otra capability, denegaciones actor/target/estado, sesión/credencial/CSRF/origen, métodos/JSON/tamaño/duplicados, claves reservadas/grupos, replay 409, lectura posterior a revocación, fallo nativo de auditoría con rollback, siete carreras reales y regresiones IW01. Tablas clínicas/membresías ausentes del fixture; catálogo/UI intactos; ningún mensaje externo.

Se conservan cuatro intentos iniciales fallidos en `docs/evidence/ase-int02a/failed-*.json`: primero rechazo HTTP del grant; diagnóstico nativo identificó falta de permiso de locking en el usuario sintético, después EXECUTE del definer y visibilidad de definición de rutinas para la fixture de compatibilidad. Se corrigió sólo la preparación de privilegios de QA y el uso del principal fixture para regresiones históricas; no se relajó autoridad ni auditoría para obtener PASS. Las ejecuciones fallidas detuvieron todos sus procesos propios y conservaron temporalmente sus directorios privados; la [disposición posterior](../evidence/ase-int02a/disposal.json) registra su retiro verificado, sin reescribir sus resultados.

La ejecución PASS detuvo sus propios procesos y eliminó su directorio. Rama oficial, padre, prototipo, pointer y listeners protegidos permanecieron iguales antes/después. No se reinició 8091/8092/8443/6385 ni 8140/8141. No hay infraestructura ASE-INT02A activa al cierre. La evidencia de ASE-INT01 no se modifica ni reinterpreta; Safari físico NOT_RUN y móvil final pendiente no se convierten en aprobación de esta fase backend.

## CTX-SYNC01

| Campo | Registro |
|---|---|
| CONTEXT_BASELINE | Padre `09d3aefc`; oficial `f92f37f1`; visual `312ee388`; CTX-MAP02/03 e IW01/ASE-ALIGN01 V2 contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-07 y COM-10; ID-02/ID-03 sólo reuso de sesión/CSRF. Ninguna capability de catálogo nueva. |
| AFFECTED_JOURNEY_IDS | J09 workspace/gobierno; J08 sólo límites comerciales preservados. |
| AFFECTED_DATA_OBJECTS | Lectura auth_accounts/auth_account_credentials/internal_staff/internal_capability_delegations; writer existente internal_operator_grants; auditoría platform_audit_events/platform_audit_stream_heads. Store canónico de sesión. Sin tablas/migraciones productivas nuevas. |
| AFFECTED_CONTEXT_FILES | Este contrato, estado, router, aviso sucesor ASE-INT01, IW01, inventario ID-07, interacciones J09, datos y trazabilidad COM-10; evidencia ASE-INT02A nueva. |
| CONTEXT_CHANGES_IMPLEMENTED | Adapter exacto, target bajo lock, separación de uso/consumidor, conflictos/auditoría, QA/descarte y deuda UI con gate. Historia, IDs y políticas pendientes preservados. |
| CONTEXT_VERIFICATION | Diff completo y archivos nuevos revisados; evidencia con hashes de fuente, 122 checks, siete carreras, aislamiento, enlaces/anchors locales y coherencia de cinco ejes. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED |
| DOCUMENTATION_COMMIT | Mismo commit de esta feature; resolver con `git log -- docs/identity/ASE-INT02A-governed-advisor-grants.md` y reporte final. |
| REMAINING_CONTEXT_GAPS | Adapter UI individual/confirmación/reconciliación, admisión y Master separados, siete políticas ADM, D01–D08, readers personales/evidencia/comercial, aceptación visual pendiente y autorización operacional. |

NEXT_AUTHORIZATION_REQUIRED: fase explícita para conectar UI con confirmación y fallos parciales. Master/admisión/documentos/finanzas y cualquier merge/despliegue/activación requieren autorizaciones separadas.

## Sucesor UI aislado ASE-INT02B

[ASE-INT02B](ASE-INT02B-team-permission-ui.md) sucede la deuda `ASE-INT02A-UI-CONNECTION` mediante autorización expresa del Director. Conecta la matriz a estos endpoints sin cambiar writer, locks, catálogo, schema individual o auditoría. Borrador, confirmación, secuencia, ACK incierto y refresh verificados en QA sintética nueva. Las afirmaciones UI readonly anteriores describen este hito padre; evidencia 122/siete carreras preservada. Gate sigue apagado por defecto; ninguna activación operacional.

## Extensión aislada ASE-INT03A

[ASE-INT03A](ASE-INT03A-advisor-lifecycle.md) reutiliza esta frontera `MutationHttp` con endpoint separado y allowlist exacta suspend/reactivate, scope servidor en el mismo writer y dos campos de payload. El contrato de capability de tres campos y su scope no cambian. No amplía callers históricos ni expone otros comandos; QA dirigida y gate default off.
