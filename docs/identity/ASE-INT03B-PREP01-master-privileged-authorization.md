# ASE-INT03B-PREP01 — contrato de autorización privilegiada Master

2026-10-18. **Preparación documental completa; flujo Master/MFA no implementado.** Padre verificado `b23d4b924d1170fc250afe3ed01555838ee684c8`, `feature/ase-int03a-advisor-lifecycle`, limpio y coincidente con remoto. Rama de esta entrega `feature/ase-int03b-master-governance-prep`, worktree `/Users/circulodigital/Documents/GitHub/mxmed-ase-int03b-master-governance-prep`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y visual aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565` preservados.

## Alcance y evidencia

| Eje | Estado exacto |
|---|---|
| PRODUCT_POLICY | Director designa/retira Master, concede/revoca management y cada delegación operativa. Clase, gestión, delegación y uso son independientes. Siete políticas ADM y D01–D08 no se ratifican aquí. |
| SOURCE_IMPLEMENTATION | Comandos directos existentes; seguridad privilegiada específica **ausente**, primitivas MFA existentes. Este contrato propone conexiones y cambios mínimos, sin implementarlos. |
| CONNECTIVITY | ASE-INT02A/B y ASE-INT03A conectan sólo Advisors. No hay HTTP Master seguro; controles Master integrados siguen deshabilitados. |
| VERIFICATION | Inspección dirigida de fuente/esquemas/tests/evidencia, enlaces, diff documental y preservación de worktrees/listeners. Cero QA de producto nueva, acceso SQL, DDL/DML o ceremonias MFA. |
| OPERATIONAL_STATUS | Sin activación, merge, despliegue, datos reales ni reinicio. Commit/push exclusivamente documental. |

Fuentes contractuales: [IW01/ASE-ALIGN01 V2](../product-completion/iw01-internal-governance-foundation.md), [ASE-INT01 R2 y aprobación real COM/ADM](ASE-INT01-R2-internal-team-readonly.md), [ASE-INT02A](ASE-INT02A-governed-advisor-grants.md), [ASE-INT02B](ASE-INT02B-team-permission-ui.md), [ASE-INT03A](ASE-INT03A-advisor-lifecycle.md), [GOV03A](IDENTITY-GOV03A-governance-runtime.md). Prototipo y contratos VIS/ADM consultados mediante `git show` en el hash visual inmutable; sus avisos históricos de aprobación visual pendiente quedan sucedidos por INT01, no sus políticas de identidad pendientes.

## Hechos canónicos y autoridad

[InternalGovernanceService::execute](../../modules/identity/services/InternalGovernanceService.php), líneas 16–100 del padre: resuelve sesión, abre su propia transacción y rechaza una exterior. Bloquea por ID ordenado cada cuenta y su staff; exige actor cuenta activa/staff ACTIVE. Director tiene autoridad de gobierno, no bypass operativo. Sólo los scopes Advisor actuales revalidan credencial y sesión después de esperar locks; **los seis comandos Master sin scope no tienen esa protección adicional ni MFA**. No equiparar servicio directo con HTTP autorizado.

| Acción | Director activo | Master activo | Advisor / self |
|---|---|---|---|
| `appoint_master`, `remove_master` | Único actor permitido por writer | Denegado | Denegado; Director raíz protegido |
| `grant` / `revoke` de `internal_advisors_manage` | Target debe ser Master | Denegado: capacidad no delegable y target no Advisor | Denegado |
| `delegate` / `revoke_delegation` | Target Master, clave exacta delegable | Denegado | Denegado |
| Administrar Advisors | Director; límites del comando | Management vigente; grant/revoke además exige delegación exacta | Sin autogestión |
| Usar una función | Grant propio y gates del consumidor | Grant propio y gates del consumidor | Igual; clase no concede uso |

[Catálogo](../../modules/identity/services/InternalCapabilityCatalog.php): 18 claves, 16 operativas delegables; `internal_advisors_manage` y `profile_initial_ownership_approve` son gobierno no delegable. Este contrato sólo comprende la primera para gestión Master. [Repositorio de grants](../../modules/identity/repositories/InternalOperatorGrantRepository.php) relee grants activos/no revocados, cuenta activa y staff ACTIVE; permite lectura bloqueante dentro de transacción. [InternalOperatorAuthority](../../modules/identity/services/InternalOperatorAuthority.php) usa sesión canónica y grants, no deduce permisos de la clase. Delegar no concede uso; tener uso no permite delegar. Casilla de grupo sólo presentación, sin command grupal, wildcard, `all` ni rol superadministrador.

### Elegibilidad: baseline frente a propuesta HTTP

| Target | Servicio directo actual | Precondición propuesta, pendiente de decisiones indicadas abajo |
|---|---|---|
| Advisor ACTIVE, cuenta activa | Promoción permitida; conserva grants existentes | Snapshot exacto, no-self, cuenta/credencial vigentes; estado de revisión staff explícito. ACTIVE/email no equivalen a identidad revisada. Resolver política ADM antes de admitir en flujo real. |
| Cuenta Identity activa sin staff | `appoint_master` inserta Master ACTIVE directamente | Excluir inicialmente de HTTP: legitimidad/matching/admisión no están acreditados por esa ausencia. Requiere contrato ADM y autorización; sin crear cuenta, invitación o perfil implícitos. |
| Master ACTIVE, cuenta activa | Democión permitida; management/delegación permitidos según estado de la asignación | Democión con efectos exactos confirmados; nuevas concesiones sólo a Master ACTIVE de cuenta activa y elegibilidad aprobada. |
| Master SUSPENDED | Democión y comandos management/delegación no comprueban estado target | Proponer permitir sólo reducción de autoridad (democión/revocación), conservando SUSPENDED; concesiones denegadas. Director debe decidir exposición de este alcance. |
| Master con cuenta disabled | Democión y comandos management/delegación tampoco exigen cuenta target activa | Proponer sólo reducción de autoridad, sin reactivar cuenta ni staff. Requiere decisión explícita, no heredar gate de beneficiario activo de ownership. |
| Director o Advisor sobre sí mismo | Nombrar/retirar Master no procede por clase/autoridad. El writer legado permite ciertos grants operativos propios del Director | Todas las seis acciones privilegiadas propuestas rechazan actor=target; no ampliar ni cambiar aquí los grants operativos históricos. |

Democión existente: cambia únicamente clase a ADVISOR; revoca `internal_advisors_manage` activo y **todas** las delegaciones ACTIVE; conserva grants operativos independientes, otros grants históricos, `internal_staff.status`, cuenta, credenciales, sesiones y acciones previas. No elimina filas ni atribución/comisiones; no decide D01–D08. Revocar sólo management no borra delegaciones ni uso: esas delegaciones no bastan para administrar sin management. La futura confirmación debe explicitar ambos efectos, sin automatizar revocaciones adicionales.

## Primer bloqueo y esquema versionado

En [StaffMfaService::issueChallenge](../../modules/identity/services/StaffMfaService.php), líneas 59–70, el primer rechazo de un intento MFA Master con identificadores válidos es `staff_mfa_invalid_operation`, antes de autoridad/reauth/INSERT. No contiene appointment/removal/management/delegación. En un flujo **case-first**, se bloquea antes la apertura: los servicios de casos existentes son específicos y el CHECK SQL tampoco acepta esas operaciones. No existe un único orden HTTP Master vigente.

`operationAllowed` (157–168) admite actualmente DIRECTOR **y MASTER_ADMIN** para las operaciones conocidas; añadir sólo nombres sería insuficiente. Exigir Director para las seis nuevas operaciones al emitir, verificar y consumir, además del writer. No ampliar `StaffRoutineOperationPolicy`. Nunca reutilizar `INITIAL_OWNER`, bootstrap, recovery, otra capacidad o un caso/proof ajeno.

| Orden/dependencia de fuente | Contrato físico relevante |
|---|---|
| Cuentas/credenciales/membresías/perfiles canónicos → [2026_09_08_07 grants](../../modules/identity/db/migrations/2026_09_08_07_create_internal_operator_grants.sql) → [2026_09_10_01 IW01](../../modules/identity/db/migrations/2026_09_10_01_internal_governance.sql) | Grants: FK cuenta, único activo por cuenta/clave, estados ACTIVE/REVOKED y fecha coherente, wildcard denegado. Staff: PK cuenta, clases/estados enum, único Director. Delegaciones: FKs staff para receptor/autorizante (clases validadas por servicio), único activo Master/clave y revocación coherente. IW01 incluye backfill de holders a Advisor; **no ejecutarlo aquí**. |
| [2026_10_14_01 ownership](../../modules/identity/db/migrations/2026_10_14_01_create_profile_ownership_governance.sql) → [2026_10_15_01 runtime R3](../../modules/identity/db/migrations/2026_10_15_01_identity_governance_r3_runtime.sql) | Casos: operation VARCHAR(40), CHECK inicial de cinco operaciones, UUID único y target activo único por operación/cuenta/perfil; beneficiario obligatorio, perfil/membresías nullable; razón 8–500 y estados/fechas coherentes. Challenge tiene FK caso, nonce y actor/caso pendiente únicos. Proof tiene FKs caso/challenge/factor y unicidad hash/challenge/factor-time-step; credencial ≥1 y consumo/fechas coherentes. Challenge/proof **no tienen CHECK de operación**; vínculo semántico se valida en servicio. |
| [2026_10_16_01 bindings](../../modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql) | Agrega `security_context`, `enrollment_context`, `reauthentication_context`; evidencia externa v2 conserva su CHECK propio de cinco operaciones. No convierte evidencia antigua en autorización actual. |
| Paquete ordenado [DeploymentPackage::migrations / professionalSourceMigrations](../../modules/identity/Operational/DeploymentPackage.php) | Identity 16_01→02→03→04→05→06; profiles correcciones 16_01→02; subscriptions FREE; metadata ACT02; finalmente Identity 17_07 ACT03. Los CHECK de casos se reemplazan en 16_03/04/06 y profiles 16_01/02, agregando evidence/authority/enrollment/review/apply. |

**Hallazgo de orden, no verificación de DB instalada:** [17_07 ACT03](../../modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql) reemplaza el CHECK con nueve nombres, agrega `PROFESSIONAL_SOURCE_PROVISION` pero omite `PUBLIC_PROFILE_CORRECTION_REVIEW` y `PUBLIC_PROFILE_CORRECTION_APPLY` que [profiles 16_02](../../modules/profiles/db/2026_10_16_02_correction_applications.sql) había agregado. Con filas de correcciones, la aplicación puede fallar; sin ellas restringiría futuras inserciones. No asumir unión acumulativa ni corregir scripts históricos en esta fase.

### Extensión mínima propuesta; no archivo SQL ni ejecución

| Nuevo identificador exacto (≤40 caracteres) | Único comando canónico asociado |
|---|---|
| `INTERNAL_MASTER_APPOINT` | `appoint_master`, capability null |
| `INTERNAL_MASTER_REMOVE` | `remove_master`, capability null |
| `INTERNAL_ADVISOR_MANAGEMENT_GRANT` | `grant`, `internal_advisors_manage` |
| `INTERNAL_ADVISOR_MANAGEMENT_REVOKE` | `revoke`, `internal_advisors_manage` |
| `INTERNAL_CAPABILITY_DELEGATE` | `delegate`, una clave operativa delegable |
| `INTERNAL_CAPABILITY_DELEGATION_REVOKE` | `revoke_delegation`, esa clave exacta |

Proponer una migración posterior `2026_10_18_01_internal_master_governance_operations.sql`, aún **no creada**, que reemplace exclusivamente `ck_auth_governance_case_operation` por unión explícita de las once operaciones de `issueChallenge` actuales más estas seis. Conservar los CHECK/FK/índices/estados existentes. No agregar enums de clase, tablas de grants, seeds ni ampliar evidencia externa de bootstrap/ownership. El desajuste previo exige preflight del paquete; si hay filas de correcciones antes de ACT03, detener ese upgrade y aprobar una reparación de orden separada, no desactivar CHECKs ni borrar filas.

Ensayo futuro autorizado y aislado: DB vacía propia, principal sin acceso a DB originales, prerequisitos y paquetes en orden explícito; inspeccionar CHECK efectivo después de cada reemplazo. Probar fixture de casos existentes (incluidas correcciones), aplicar extensión, aceptar exactamente 17 nombres, rechazar unknown, conservar FK/unique/estados y probar migración desde variantes ACT02/ACT03. Rehearsal de reversión sólo si no existen casos nuevos; con historia nueva, detener downgrade y conservar esquema compatible. Evidencia/fingerprint y limpieza sólo de recursos propios. Nada de esto se ejecutó en PREP01.

## Composición y ciclo de prueba propuestos

Reutilizar [IdentityHttpComposition](../../modules/identity/http/IdentityHttpComposition.php) para PDO/sesión/authentication/CSRF y [CanonicalHttpSessionResolver](../../modules/identity/http/CanonicalHttpSessionResolver.php) para cookie opaca; no headers de rol, login alterno ni booleano `mfa_ok`. [GovernanceSecurityContext](../../modules/identity/services/GovernanceSecurityContext.php) ya reautentica con credenciales canónicas del **mismo actor/versión**, valida token digest/sesión actual y exige antigüedad menor de cinco minutos. Contraseña/TOTP/proof nunca a URL, log, audit o persistencia del navegador.

[GovernanceStaffMfaCeremony](../../modules/identity/services/GovernanceStaffMfaCeremony.php) es enrollment backend controlado con registrador independiente aprobado; no es la ceremonia de aprobación de un nombramiento. Reutilizar factor ACTIVE previamente inscrito. Falta de factor: cerrar flujo y remitir a ceremonia autorizada separada; no autoenrolar al Director/target.

**Dueño propuesto del contexto:** casos `auth_governance_cases` existentes, con variante tipada `security_context.kind=internal_master_governance`, `version=1` de esa variante, creada sólo en servidor. [GovernanceRuntime](../../modules/identity/services/GovernanceRuntime.php) compone actualmente aceptación de ownership, y [GovernanceCaseSecurity](../../modules/identity/services/GovernanceCaseSecurity.php) exige elegibilidad profesional, membresías, approvers y, según caso, trust externo; no sirven sin extensión específica. Incorporar despacho cerrado por las seis operaciones hacia validación staff dentro de la composición de gobierno existente. Preservar íntegra la ruta v2 ownership/recovery; no marcar Master como ownership rutinario, falsificar membresía ni crear motor paralelo de privilegios.

1. **Intención/preview:** Director selecciona target elegible; servidor relee cuenta/staff/credencial y asignaciones. Crea caso OPEN con UUID `operation_id`, requester Director, beneficiary=target, perfil/membresías null, razón, expiración propuesta cinco minutos y snapshot canónico. Incluye comando, capability exacta/null, estado esperado, IDs/estados de grants/delegaciones relevantes, versiones/fechas y digest determinista; null staff se distingue de staff existente. Target no se deriva de texto libre de UI.
2. **Confirmación:** presentar acción/target/consecuencias exactas. El servidor conserva intención inmutable. Cambiar target/operación/capability/snapshot requiere otro caso y confirmación. El índice activo actual serializa incluso dos delegaciones de claves distintas al mismo target/operación: conservar esa restricción inicialmente, sin cambiar índice ni simular batch.
3. **Reauth/challenge:** tras confirmación, reautenticar al Director con `GovernanceSecurityContext`; releer caso/autoridad, emitir challenge exacto. Proponer almacenar digest de intención también en `reauthentication_context`; sólo el servidor lo genera. Expira al mínimo entre caso y cinco minutos; no extender vida por reemisión. La apertura y enlace del challenge/contexto deben ser atómicos: `issueChallenge` hoy hace INSERT y UPDATE sin transacción propia.
4. **Verificación:** `verifyChallenge` conserva nonce hash, factor ACTIVE, contexto de enrollment, TOTP seis dígitos/30s ±un paso, antireplay y cinco fallos/bloqueo 15 minutos. Consume challenge, avanza time-step y emite proof VERIFIED en transacción propia; proof actual dura cinco minutos. Adaptar validación Master de caso/digest vigente sin debilitar las anteriores. Proof no es autorización duradera.
5. **Consumo:** writer relee todo bajo locks; compara actor, sesión, versión, operación, caso, target=beneficiary, perfil null, digest, capability, snapshot, factor y expiración mínima (caso/challenge/proof/reauth). `consumeProof` exige mismo PDO con transacción activa, bloquea proof, compara hash/bindings y actualiza una sola fila. Añadir comparación del contexto Master **antes** de consumir; un proof de otra capability del mismo target nunca sirve.
6. **Commit/refresh:** consumo proof + caso + única mutación + audit comparten transacción. En esta variante, OPEN→APPROVED→CONSUMED sólo después de confirmación/reauth/MFA válidos, dentro de esa transacción, satisfaciendo `approved_at`/`consumed_at`; no fabricar endorsements ni adoptar las dos aprobaciones de recovery. Refresh canónico después de ACK. El caso conserva resultado/referencia de audit no secreta para reconciliar.

La confirmación única del Director no modifica la política aprobada de autoridad. La interfaz y elegibilidad más estricta propuestas sí requieren decisiones abajo. Los nombres/TTL/contexto anteriores son contrato técnico propuesto, no hechos implementados.

## Transacción, locks y fallos

**Cambio mínimo del writer:** mantener `InternalGovernanceService` como único mutador y dueño del commit. Añadir entrada privilegiada/contexto tipado servidor que ejecute revalidación y `consumeProof` **dentro** de su transacción existente, antes de SQL de gobierno; no consumir en un request anterior y luego invocar `execute`. No aceptar callback/flag arbitrario del cliente ni convertir un `inTransaction()` cualquiera en autorización. Compartir el núcleo de mutación/audit, sin segundo writer. Callers Advisor conservan sus scopes; ninguna nueva ruta Master puede llamar la vía legado sin proof.

Orden propuesto común: cuentas+staff actor/target por ID como el writer actual; credenciales por ID; caso; challenge; proof; factor; grants/delegaciones exactos en orden estable; audit stream al final. Usar lecturas actuales bloqueantes, también para detectar asignación ausente, con la cuenta como mutex y unique como última defensa. `verifyChallenge` hoy bloquea challenge antes de cuenta; `consumeProof` bloquea proof antes de autoridad. La extensión debe coordinar esas rutas para casos Master en el mismo orden; no prometer ausencia de deadlocks por el orden del writer solamente. Error de lock/deadlock revierte y exige nueva decisión, sin reintento privilegiado automático.

Sesión: revalidar canónicamente tras esperar locks y antes del commit; bloquear credenciales y comparar versión actual. [SessionService::bindingIsCurrent](../../modules/identity/services/SessionService.php) consulta store de sesión además de SQL: **un lock MySQL no serializa logout en Valkey**. La implementación debe definir el punto de autorización en la última validación y demostrar que una revocación ya completada antes de ese punto deniega. Si se exige precedencia de cualquier logout anterior al commit, falta coordinación canónica entre revocación y writer (p. ej. fence por cuenta compartido por ambas rutas); diseñarla/probarla en Identity antes de declarar esa garantía. No afirmar atomicidad SQL/Valkey ni resolverlo con otro store de sesiones.

[CanonicalAuditWriter](../../modules/platform/services/CanonicalAuditWriter.php) con [JoinedPdoCanonicalAuditTransactionAdapter](../../modules/platform/repositories/JoinedPdoCanonicalAuditTransactionAdapter.php) ya participa en la transacción del writer, bloquea stream, inserta evento sellado y avanza head por CAS; su `commit` joined no hace commit exterior. Mantener `INTERNAL_GOVERNANCE_CHANGED/ADMIN_DECISION`, actor real y before/after. [PolicyRegistry](../../modules/platform/services/CanonicalAuditPolicyRegistry.php) sólo permite metadata operation/target/capability/before/after: no inventar metadata aceptada. Proponer usar `operation_id` UUID del caso como `TrustedAuditContext.correlationId`, conservar `event_id` devuelto en contexto/resultado del caso en la misma transacción; así no se amplía catálogo ni se registran secretos. Hoy se usan UUID aleatorios no ligados al caso y sólo before/after de staff: la confirmación/snapshot de grants no debe presentarse como audit ya existente.

| Carrera/fallo | Garantía requerida y respuesta |
|---|---|
| Otro Director promueve target / target suspendido durante challenge | Cuenta/staff bloqueados y snapshot exacto; conflicto sin consumo/mutación. El esquema permite un único Director; ensayar dos sesiones concurrentes de esa raíz, sin sembrar dos Directors. |
| Sesión revocada, credencial rotada, actor suspendido o deja de ser Director | Validación actual después de espera, versión bajo lock; denegar con el límite SQL/Valkey anterior explícito. |
| Challenge replay, proof expirado, operación/target/case/capability/contexto equivocados | Unicidad, estado y comparaciones exactas; cero mutación. Revalidar también factor revocado y reauth vencida aunque proof no haya vencido. |
| Cambio concurrente de management/delegación, incluso con snapshot SQL previo | Mutex por cuenta y lecturas actuales; comparar snapshot/versiones relevantes; conflicto o serialización, nunca resucitar una concesión retirada. |
| Falla append/head audit o finalización de caso | Rollback físico de staff/grants/delegaciones/proof/caso/audit. Challenge y avance TOTP previamente confirmados permanecen consumidos: no prometer rollback de verificación anterior. |
| Commit/ACK de resultado desconocido | Mostrar incertidumbre y bloquear reenvío. Consultar caso/UUID/resultado y audit correlacionado más estado actual; cambio de estado solo no prueba autoría. CONSUMED + audit coherente confirma; OPEN sin audit requiere reconciliación antes de nueva intención. Lectura indisponible/inconsistente mantiene UNKNOWN, sin retry ni compensación. |

### Responsabilidades API propuestas, no endpoints disponibles

Extender la composición de gobierno con rutas staff acotadas para **preview/abrir caso**, **confirmar/reauth/challenge**, **verificar MFA**, **ejecutar caso**, **consultar resultado**, **revocar/expirar caso**. Actor siempre de cookie, operación/capability cerradas, case/target/contexto resueltos en servidor; IDs no son autoridad. Reusar [boundary Team](../../modules/identity/Team/MutationHttp.php) en transporte TLS canónico, Origin/Fetch-Site, CSRF ligado a sesión, métodos, JSON estricto, límite de cuerpo y `no-store`; el gate Advisor no habilita Master. Gate Master separado default off, esquema/configuración completos o fail-closed; sin fallback a legacy. GET sin mutaciones, POST individual sin batch. Minimizar datos de target y errores, no enumerar cuentas ni devolver evidencia personal. Aún no fijar URLs públicas como si existieran.

## Ficha UI-2 y decisiones del Director

Entrega actual: documental **UI-0**. Futuro flujo: **UI-2 pendiente**, según [protocolo](../MXMED_PROTOCOLO_CONTROL_CAMBIOS_UI_UX_Y_ENTREGA_SEGURA.md). Deuda `ASE-INT03B-PRIVILEGED-UI`, responsable Director para aprobación y equipo de implementación para evidencia; desbloqueo sólo con decisión explícita y backend/QA completos.

| Campo UI-2 | Propuesta para decisión, no aprobación |
|---|---|
| Pantalla/componente | Equipo original, encabezado **EQUIPO INTERNO Y PERMISOS**, botones Director **PROPONER COMO MASTER / RETIRAR DESIGNACIÓN MASTER** y panel **GESTIÓN Y DELEGACIÓN PARA ASESORES**, separado de **PERMISOS PARA USAR FUNCIONES**. |
| Actual → propuesto | Controles Master deshabilitados → confirmación exacta, reingreso de contraseña, TOTP, espera, vencimiento/cancelación/conflicto, resultado incierto y refresh. Reutilizar modal/shell; estados de seguridad nuevos sin rediseñar layout aprobado. |
| Razón / alternativa | Evitar privilegio por simple click. Alternativa sin cambio: mantener controles deshabilitados y entregar sólo backend aislado bajo gate, sin afirmar integración. |
| Impacto backend / riesgo | Seis intents independientes y proof único; exponer datos mínimos, proteger foco/teclado y no perder borradores operativos. Riesgos: confundir uso/delegación, democión con suspensión, o incertidumbre con éxito. |
| Rollback / recomendación | Gate off y revertir conexión UI en rama aislada; nunca compensar una mutación confirmada. Recomendar UI-2 acotada después de aprobar elegibilidad y ensayar backend. Nuevos cambios de composición visual escalan UI-3. |

**Decisiones pendientes exactas:** (1) aprobar la ficha UI-2 y su alcance de implementación; (2) definir evidencia/condiciones de promoción de Advisor existente y tratamiento de legacy UNKNOWN, manteniendo excluida cuenta sin staff hasta admisión aprobada; (3) decidir exposición de democión/revocación para Master suspendido/cuenta disabled, sin restaurar acceso; (4) autorizar implementación y rehearsal sintéticos de la extensión fuente/esquema, incluida resolución del orden de CHECKs. No se vuelve a pedir aprobación de la autoridad exclusiva del Director ni de la semántica de democión ya aprobadas.

Las siete decisiones ADM siguen separadas: documentos/metadata y obligatoriedad; autoridad de revisión; retención/custodia; términos/aceptación personal; cambio material y revisión; invitación/matching/recuperación; condiciones de activación/MFA. No inventar revisión aprobada desde email/ACTIVE ni un nuevo permiso de admisión. Autorización de personas, MFA/DDL reales, despliegue y activación son posteriores e independientes.

## Secuencia de implementación y QA futuras

1. Resolver decisiones de alcance/eligibilidad/UI; fijar precondiciones, límite de revocación de sesión y versión del contexto. Ratificar paquete/migración sin modificar historia ni otorgar permisos.
2. En otra feature autorizada, implementar caso tipado, allowlist+Director-only MFA, binding de capability/digest y entrada privilegiada al writer; audit/caso/proof atómicos. Ensayar migraciones sólo en recursos sintéticos propios antes de conectar HTTP.
3. QA dirigida con servicios reales: seis comandos positivos; Director/Master/Advisor/no-self; matriz completa de targets; clase sin grants; separación uso/gestión/delegación; democión conserva historia/uso/estado. Rechazar otro proof/operation/target/case/capability, reauth/proof expirados, replay/factor revocado, falta de enrollment y actor no Director en las tres etapas MFA.
4. Dos conexiones/procesos y esperas observadas: promoción/suspensión/rotación/revocación concurrentes, grant/delegación y doble consumo. Fault injection de audit/commit/ACK; comprobar rollback conjunto, audit único y reconciliación sin retry. Probar CHECKs/FKs/índices y regresión de operaciones ownership/C4/correcciones/ACT03 afectadas; no ejecutar suite histórica contra destino fijo.
5. Sólo tras UI-2 aprobada, conectar estados al layout inmutable; Chromium/WebKit en desktop/tablet/móvil, cancelación, expiración, foco/teclado, borrador y resultado incierto. Safari físico sólo si realmente ejecutado. Reportar gates off y limpieza propia; no promover/activar por QA PASS.

Referencias de QA **leídas, no ejecutadas**: [Gov03B](../../modules/identity/tests/GovernanceGov03BTest.php), [ceremonia](../../modules/identity/tests/GovernanceCeremonyIntegrationTest.php), [entrada histórica Gov03A](../../modules/identity/tests/GovernanceGov03ATest.php), [evidencia padre](../evidence/ase-int03a/qa.json). Sus PASS no acreditan este flujo Master futuro; la entrada histórica configura por defecto acceso a `mxmed` y no se invocó.

## CTX-SYNC01 y cierre documental

| Campo | Resultado/alcance |
|---|---|
| CONTEXT_BASELINE | Padre b23d4b924d1170fc250afe3ed01555838ee684c8; oficial f92f37f1; visual 312ee388; IDs CTX-MAP02/03 preservados. |
| AFFECTED_CAPABILITY_IDS | ID-07 y COM-10 documentales; ID-02/03 sólo reuso de auth/sesión. Ninguna clave ejecutable nueva. |
| AFFECTED_JOURNEY_IDS | J09, preparación de gobierno; J08 sólo frontera comercial preservada. Sin journey nuevo implementado. |
| AFFECTED_DATA_OBJECTS | Referenciados, sin acceso: auth_accounts, auth_account_credentials, internal_staff, internal_operator_grants, internal_capability_delegations, auth_governance_cases, auth_staff_mfa_factors/challenges/proofs, platform_audit_events y stream heads; store de sesión canónico. Ownership/endorsements/trust sólo contraste de límites. |
| AFFECTED_CONTEXT_FILES | Este contrato; estado; router; IW01; GOV03A; inventario ID-07; interacciones J09; datos/migraciones; COM-10; registro visual. |
| CONTEXT_CHANGES_IMPLEMENTED | Hechos vs propuesta, dos bloqueos, orden de CHECKs, autoridad/elegibilidad, proof/transacción/locks, democión, UI-2 y decisiones pendientes. Historia conservada. |
| CONTEXT_VERIFICATION | Inspección fuente del padre y prototipo inmutable; diff completo de diez Markdown incluidos nuevos, 44 enlaces locales nuevos/modificados y sus anchors/paths válidos, diff no documental vacío y preservación de seis worktrees, seis listeners y hash del pointer. No QA de producto ni aceptación operacional. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED: contexto acotado coherente con entrega exclusivamente documental; mapas globales no regenerados. |
| DOCUMENTATION_COMMIT | Hash exacto en reporte de cierre, sin hash autorreferente. |
| REMAINING_CONTEXT_GAPS | Implementación/ensayo Master pendientes, decisiones ADM/UI-2/elegibilidad, coordinación sesión/commit y orden de migraciones por verificar en entorno aislado autorizado. No se atribuye schema real inspeccionado. |

Resultado de preparación: `READY_MXMED_ASE_INT03B_MASTER_SECURITY_CONTRACT_COMPLETE`. Próxima autorización: implementación/ensayo sintéticos acotados y decisiones anteriores; no nombramiento real, enrollment, DDL operacional ni activación.

## Sucesor autorizado ASE-INT03B-IMP01

Las cuatro recomendaciones de esta preparación fueron aprobadas expresamente en IMP01, incluido concepto UI-2. [Contrato sucesor](ASE-INT03B-IMP01-master-security-foundation.md) registra implementación backend, evidencia sintética y límites actuales. Este documento conserva el diagnóstico previo; no representa ya ausencia de código Master. Reader real de identidad y conexión UI IMP02 permanecen pendientes, sin activación real.
