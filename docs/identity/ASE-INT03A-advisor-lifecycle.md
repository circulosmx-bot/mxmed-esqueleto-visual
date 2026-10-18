# ASE-INT03A — suspensión y reactivación gobernadas de Advisors

**2026-10-18.** Padre `36eeb51655a1c24248405feae579cefc06ff3b44`, rama `feature/ase-int02b-team-permission-ui`, limpio y coincidente con remoto. Implementación exclusiva en `feature/ase-int03a-advisor-lifecycle`, worktree `mxmed-ase-int03a-advisor-lifecycle`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, visual aprobado `312ee388f93c892e6caf7e845b11e2b42f9c3565` y cinco worktrees protegidos conservados. El cwd inicial `mxmed-cursor-pilot` no era un repositorio Git; se verificó y utilizó el worktree padre correcto antes de crear esta rama.

## Alcance y cinco ejes

La solicitud explícita ASE-INT03A autoriza conectar únicamente `suspend` y `reactivate` para Advisors existentes, con QA sintética y publicación sólo de esta feature. No autoriza operación real, merge, admisión ni nombramiento Master. Sucede esa desconexión de [ASE-INT02B](ASE-INT02B-team-permission-ui.md) sin repetir su implementación o suite completa.

| Eje | Estado |
|---|---|
| PRODUCT_POLICY | Director ACTIVE, o Master ACTIVE con `internal_advisors_manage` vigente; sólo Advisor distinto del actor, cuenta activa y estado staff de partida exacto. Suspensión no borra grants/historia; reactivación no restaura revocados. D06 sigue pendiente. |
| SOURCE_IMPLEMENTATION | Scope servidor opcional en el mismo `InternalGovernanceService::execute`; endpoint lifecycle mediante el mismo `MutationHttp`; disponibilidad en `ReadService` y botón/modal existentes. Ningún segundo writer, enum, catálogo o schema nuevo. |
| CONNECTIVITY | GET read/CSRF → confirmación → POST lifecycle individual → lectura canónica. Sólo comprobado en infraestructura nueva de propiedad del ensayo. |
| VERIFICATION | 223 checks PASS, seis carreras SQL reales, ocho combinaciones Chromium/WebKit, 36 capturas y 36 eventos lifecycle inspeccionados. Safari físico NOT_RUN; móvil INTERIM_SMOKE_ONLY. |
| OPERATIONAL_STATUS | Gate default off, sin deploy, merge, servicios originales reiniciados ni datos reales modificados. Recursos propios detenidos y dispuestos. |

## Contrato HTTP y writer único

[Endpoint](../../api/internal/team/lifecycle.php): `POST /api/internal/team/lifecycle.php`, objeto JSON plano de exactamente dos strings:

```json
{"operation":"suspend","target":"referencia_exacta_del_asesor"}
```

`operation` admite sólo `suspend|reactivate`; `target` conserva `[A-Za-z0-9_.:-]{1,64}`. Rechaza arrays, capability, actor/role/scope, claves desconocidas o repetidas, query/path adicionales y otros comandos. `register`, `appoint_master`, `remove_master`, `delegate` y `revoke_delegation` no se exponen. El contrato grant/revoke de tres campos del endpoint anterior permanece igual.

[MutationHttp](../../modules/identity/Team/MutationHttp.php) comparte Identity, sesión/credencial, CSRF autenticado ligado a sesión, Origin/Fetch-Site, transporte TLS canónico, tamaño 1.024 bytes, método, JSON, privacidad y errores. El selector de lifecycle es argumento PHP fijado por el endpoint, nunca dato del navegador. Reutiliza `MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED`; sólo el runtime propio de QA lo habilita. Sin ese gate o sin composición canónica: 503 y controles deshabilitados.

ACK exitoso después del commit: `result=changed`, operación, target exacto, `staff_status=ACTIVE|SUSPENDED`, `refresh_required=true`, `consumer_authority=independently_required`. Estado ya satisfecho: 409, sin otro evento de éxito. Actor/target no elegible: 403; sesión inicialmente inválida: 401; input: 400; método: 405; tamaño: 413; tipo: 415; infraestructura/auditoría/resultado incierto: 503 genérico. No credenciales ni SQL en errores/evidencia.

[InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php) añade `ADVISOR_LIFECYCLE` como quinto argumento opcional, exclusivo de estos dos comandos. La misma transacción bloquea cuentas y relaciones staff del par actor/target en orden estable. Bajo locks:

1. Target distinto del actor, cuenta `active`, relación existente `ADVISOR`, estado canónico ACTIVE/SUSPENDED. Master y Director no son targets válidos ni para Director.
2. Credencial del actor releída `FOR UPDATE`; sesión canónica revalidada después de posible espera. Cuenta y staff del actor activos.
3. Director conserva autoridad raíz. Master requiere `internal_advisors_manage` actual con lectura bloqueante; no necesita una delegación operativa para estas dos acciones de gestión. Advisor no puede ejecutarlas.
4. `suspend` exige ACTIVE; `reactivate` exige SUSPENDED. Otro estado de partida produce conflicto. Una cuenta Identity desactivada nunca se habilita ni se confunde con suspensión staff.
5. Ejecuta el mismo UPDATE de estado ya existente y el mismo evento `INTERNAL_GOVERNANCE_CHANGED`, con actor, target, operación y before/after, unidos por `JoinedPdoCanonicalAuditTransactionAdapter`. Fallo del append revierte estado, evento y head.

Los callers históricos sin scope mantienen exactamente sus ramas y privilegios previos. El scope `ACTIVE_ADVISOR_OPERATIONAL` de grants conserva su contrato. No cambia la lógica SQL de lifecycle, grants, revocaciones, auditoría, cuentas, ownership ni historia comercial; se añade únicamente la precondición estricta para este adapter.

## Preservación y autoridad operativa

Se conserva cada fila de grants, incluidas las revocadas, con sus IDs y timestamps. No elimina cuenta, atribución ni historia; no modifica staff ajeno. El [repositorio de autoridad operativo](../../modules/identity/repositories/InternalOperatorGrantRepository.php), sin cambios, exige cuenta activa y staff ACTIVE al resolver capacidades. El ensayo ejecuta el mismo `InternalOperatorAuthority` utilizado por Media: tras suspender deniega la siguiente resolución; tras reactivar vuelve a considerar sólo grants activos existentes.

Reactivar no prueba preparación del consumidor ni acreditación de identidad. La fixture también comprueba que `StaffMfaService` continúa rechazando un challenge sin contexto de seguridad canónico; no es un ensayo completo de ceremonia MFA ni una concesión de proof. Servicios MFA, consumidor, catálogo y admisión permanecen byte-idénticos al padre. Registros legacy conservan «revisión aún no registrada» si no existe fuente conectada. ASE-ADM01 no se convierte en una migración retroactiva. ASE-COM01 D06 (efectos de salida/suspensión sobre comisiones futuras), D01–D08 y políticas ADM permanecen sin decisión nueva.

## Ficha UI-2 autorizada

**Pantalla/componente:** header del integrante en Equipo y permisos, botones existentes SUSPENDER ASESOR / REACTIVAR ASESOR y modal aprobado. **Antes:** deshabilitados. **Ahora:** disponibilidad autoritativa `lifecycle_operations` por target y bootstrap CSRF validado. **Razón:** conexión expresamente solicitada en ASE-INT03A. **Alternativa sin cambio:** mantener botones readonly, que dejaría incompleto el objetivo autorizado. **Backend:** único comando individual con scope bloqueante. **Riesgo:** estado concurrente o ACK perdido; se resuelve sin retry ni compensación. **Rollback:** gate ausente deja API/UI cerradas; la feature no sustituye fuente ni runtimes protegidos. Reversión futura de fuente mediante Git requiere su alcance propio. **Recomendación:** revisar esta feature aislada antes de cualquier promoción; no constituye autorización de deploy.

El editor reutiliza el coordinador y modal de [ASE-INT02B](../../assets/js/internal-team-editor.js). Un borrador de permisos pendiente impide iniciar lifecycle y solicita guardarlo o usar Revertir; nunca lo descarta silenciosamente. Busy compartido bloquea doble envío y cambio de target. La UI relee antes de confirmar y después de aceptar; diferencia material detiene el comando. Confirmación en español identifica cuenta/acción, bloqueo operativo o gates de reactivación, conservación de grants/historia y ausencia de decisión de identidad/comisiones. Cancelar/Escape no muta; foco inicial en Cancelar.

Sólo ACK canónico y refresh consistente muestran **CAMBIO DE ACCESO CONFIRMADO**. Si falla la lectura, mantiene **ESTADO PENDIENTE DE CONFIRMAR** y edición bloqueada. 409 es conflicto; pérdida de sesión/management detiene el flujo. 503, timeout o respuesta perdida son inciertos. La consulta explícita posterior actualiza el estado visible sin atribuir el cambio al comando incierto ni reenviarlo. Una reactivación posterior requiere una nueva acción y confirmación, nunca compensación automática. La tarjeta seleccionada y detalle reflejan la lectura canónica; permisos de Advisors suspendidos siguen deshabilitados.

No cambios en header, directorio, layout, CSS o template. Tres CSS idénticos al visual `312ee388`; comparación de geometría, grid, fuentes, color y radios en los ocho tamaños/motores. Copy de disponibilidad y resultados pertenece a esta UI-2 autorizada. No claim pixel0 ni nueva aprobación física Safari. Admisión, designaciones Master, delegación administrativa, documentos, MFA y comercial siguen desconectados en esta pantalla.

## QA dirigida, aislamiento y cierre

[Runner nuevo](../../scripts/ase-int03a-qa.py) reutiliza sólo la preparación de MySQL/Valkey/TLS y fixture de ASE-INT02A, con prefijos, rama, router y evidencia propios. No invoca las suites anteriores completas. Añade UPDATE exclusivamente a `internal_staff` para el principal restringido del MySQL recién creado; ningún privilegio sobre instancias originales. El fixture CLI exige fase/prefijo/datadir propios. HTTP usa `ase_writer`, sin root. No conexiones a mxmed/QA05/OP02/UI03, notificaciones ni proveedores externos.

[Resultado y hashes](../evidence/ase-int03a/qa.json): **223 PASS**, preservación de cinco worktrees, listeners 8091/8092/8443/6385/8140/8141 y pointer ASE-INT01. [Browser/audit](../evidence/ase-int03a/browser.json): ocho combinaciones, 36 eventos lifecycle y [36 capturas](../evidence/ase-int03a/screenshots/). Chromium/WebKit en 1440×900, 1366×768, 820×1180 y 390×844; Safari físico NOT_RUN, móvil interino.

Cobertura: Director/Master suspenden/reactivan, actores/targets prohibidos y self, management revocado, sesión expirada, CSRF/origen, payload/método, replay 409, rollback nativo de audit, grants preservados y revocados no restaurados, siguiente autoridad operativa, gate MFA independiente, legacy register/suspend/reactivate y regresión grant/revoke, readonly de permisos suspendidos, borrador, doble clic, cambio de target/autoridad durante modal, pérdida de ACK/refresh y seguimiento explícito, gate default off.

Seis carreras retienen lock en conexión SQL independiente mientras el writer HTTP espera: categoría target, estado target, cuenta target, management actor, suspensión actor y credencial actor. Se observan transacciones/threads distintos en `performance_schema.data_lock_waits`; después del cambio concurrente, rechazo 403 o conflicto 409 sin evento adicional. No se usan éxitos falsos de red: ACK perdido aborta la entrega de un POST realmente ejecutado; auditoría fallida se provoca con trigger sólo en el MySQL nuevo.

Dos intentos tempranos fallaron por un nivel de riesgo inválido en la sonda de QA operativa; se corrigió a `RiskLevel::R0`, el contrato real de Media, sin relajar producto. Se conservan sus JSON históricos y su [disposición verificada](../evidence/ase-int03a/disposal.json). La ejecución PASS eliminó su datadir, Valkey y procesos propios; los directorios fallidos se retiraron sólo tras comprobar ausencia de procesos/archivos abiertos.

## CTX-SYNC01

| Campo | Registro |
|---|---|
| CONTEXT_BASELINE | Padre `36eeb516`; oficial `f92f37f1`; visual `312ee388`; ASE-INT01/02A/02B, IW01, ASE-ADM01 y CTX-MAP02/03 contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-07, COM-10; ID-02/ID-03 por reuso. Sin nuevas capabilities. |
| AFFECTED_JOURNEY_IDS | J09 lifecycle de Advisor; J08 sólo límite D06 preservado. |
| AFFECTED_DATA_OBJECTS | internal_staff (UPDATE existente); auth_accounts/auth_account_credentials/internal_operator_grants (lectura); platform_audit_events/platform_audit_stream_heads (append canónico); store de sesión. Sin migraciones ni grants mutados por lifecycle. |
| AFFECTED_CONTEXT_FILES | Este contrato, estado, router, ASE-INT02A/02B, IW01, inventario ID-07, interacciones J09, datos, trazabilidad COM-10 y registro visual; evidencia nueva propia. |
| CONTEXT_CHANGES_IMPLEMENTED | Scope transaccional, boundary HTTP compartido, UI confirmada, incertidumbre/borrador, conservación y QA dirigida. Historia y políticas pendientes preservadas. |
| CONTEXT_VERIFICATION | Diff completo/nuevos archivos, lint, fuente vinculada por hashes, 223 checks, seis carreras, ocho combinaciones visuales, enlaces y protección de recursos. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED |
| DOCUMENTATION_COMMIT | Mismo commit de implementación; resolver con `git log -- docs/identity/ASE-INT03A-advisor-lifecycle.md` y reporte final. |
| REMAINING_CONTEXT_GAPS | Master/admisión/documentos, MFA y readiness de consumidor independientes, fuentes personales, D01–D08 incluido D06, políticas ADM, Safari físico/móvil final y autorización operacional. |

NEXT_AUTHORIZATION_REQUIRED: revisión/promoción, merge, despliegue o activación real; las fases Master, admisión y comercial requieren alcance separado.
