# OP-IMP01 — fundamento canónico de operadores no médicos

2026-10-15. Baseline publicado: `a18cc367da33c7a7cc07a45808a7fc53569eaf47`, rama `ux/consultation-step2-vitals-r1`. Implementación y ensayo **exclusivamente desechables**. Ninguna migración, invitación, asignación, sesión o grant real se instala mediante esta entrega. No hay endpoint de invitación/aceptación ni panel nuevo en Safari.

## Decisiones ratificadas y precedencia

La instrucción OP-IMP01 ratifica Q01/Q02/Q03/Q04/Q12 de [OP-POL02](OP-POL02_NONMEDICAL_OPERATOR_IMPLEMENTATION_READINESS.md). Sustituye su carácter pendiente en el documento histórico; Q05–Q11 siguen limitando las fases posteriores. Se conserva [ROLE-POL01 y OP-POL02](../product-completion/MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md), [GOV03A](IDENTITY-GOV03A-governance-runtime.md), la contención [CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md) y el contrato [CLIN-AUTH02B1](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md).

| Decisión | Regla instalada en el fundamento desechable |
|---|---|
| Q01 | Un owner, dos personas delegadas como máximo; administrator y collaborator usan las mismas plazas. Invitación pendiente reserva; activo y suspendido ocupan; revocado, cancelado, rechazado o vencido liberan. |
| Q02 | Email, siete días de reloj servidor, token aleatorio de 256 bits, hash ligado a invitación/perfil/oferta, uso único. Cuenta Identity activa y email exacto verificado; aceptación/rechazo explícitos. Cambio de oferta cancela y reexpide; token anterior no aumenta permisos. |
| Q03 | Cambio gobernado cancela pendientes y marca los grants actuales `review_required` antes del mismo commit. Nuevo owner confirma expresamente; revisión no reactiva una membresía suspendida. Sin autoridad clínica heredada. |
| Q04 | Dos plazas para todos los planes. Entitlement limita efectividad por función; no añade plazas ni borra historia. Recuperar plan no restaura membresía/grants revocados. Renovación delegada sigue pendiente. |
| Q12 | CanonicalAuditWriter, catálogo cerrado, actor/sesión reales, perfil, membresía, revisión, motivo, tiempo y correlación. Auditoría crítica y cambios comparten transacción; fallo revierte ambos. Sin tokens/contactos/contraseñas/contenido clínico en metadata. |

## Composición y límite de activación

[DisposableOperatorFoundation](../../modules/identity/operators/DisposableOperatorFoundation.php) tiene constructor privado. Su único factory exige `PHP_SAPI=cli`, base `mxmed_op01_<10 hex>`, perfiles sintéticos `op01_…` y contactos `@example.invalid`. El origen de comandos es exclusivamente `https://op01.example.invalid`, sin listener o DNS requerido. Acepta únicamente el adaptador final de notificaciones en memoria, nunca SMTP/archivo/proveedor real. No existe flag de entorno que active esta composición en normal-local, QA05 o HTTP. No se cambió el selector/composición de Identity ni `api/agenda/index.php`/UI.

Los comandos reciben sesión canónica de SessionService y CSRF autenticado ligado a su digest. Verifican origen exacto, estado actual de cuenta/contacto y credential_version, owner exacto por perfil y revisión optimista; no admiten identidad/owner/plan como autoridad desde el body. `inspectInvitation` muestra al invitado verificado el médico/perfil, rol y oferta exactos antes de responder. Cuenta preexistente se reutiliza; registrar/verificar sigue siendo tarea del flujo Identity, no del servicio de operadores.

`permits` es una decisión interna del fundamento, **no ejecuta acciones de Agenda**. El perfil real del recurso debe obtenerlo su repositorio en el futuro consumidor. El servicio consulta el read-model comercial canónico en su propia conexión; no admite un plan elegido por el cliente. Cinco capacidades exactas están preparadas:

```text
agenda.appointments.read
agenda.appointments.create
agenda.appointments.reschedule
agenda.appointments.cancel
agenda.availability.read
```

Todo lo demás queda fuera del catálogo: confirm/no-show/waitlist/settings, perfil/medios, fiscal, renovación, captura y clínica no están disponibles. Rol administrador no sustituye un grant; un permiso no implica los otros.

## Reutilización y alternativa mínima a Agenda legacy

`auth_accounts`, credenciales, SessionService, CSRF, normalización de email y generador criptográfico existentes siguen siendo únicos. `OneTimeTokenPurpose::OPERATOR_INVITATION` identifica sólo este propósito; **no se reutilizan tokens de verificación/reset ni sus filas**. La membresía se crea mediante AccountMembershipRepository; suspensión/reactivación/revocación se añaden al mismo repositorio con transición condicionada por estado, perfil y procedencia. No se crea otro repositorio de usuarios, contraseñas, membresías o contador de plazas.

Se revisó `agenda_operators`/`agenda_operator_permissions`: requieren login/alias propios, guardan contraseña temporal, usan identificadores legacy sin FK a cuenta/membresía y banderas amplias. Alterarlos y dejarlos bajo el writer legacy no garantiza procedencia ni revisión histórica. Se prepara la alternativa mínima: invitaciones nuevas y grants versionados **referenciando la membresía canónica**, sin copiar permisos/credenciales legacy ni crear otra asignación. No hay fila adicional de operador ni contador persistido en este fundamento.

OP-IMP02 necesitará reconciliación explícita del legado y desactivar sus writers antes de uso real. Un vínculo de proyección `agenda_operators → membership` requerirá migración posterior: nunca asociar por similitud de email/nombre ni convertir `patients`/`billing` en autoridad. Las filas legacy no autorizan este servicio. La prueba demuestra que MAX_ALLOWED=3 y roles duplicados no eluden el contador canónico; no afirma haber reconciliado personas reales.

## Persistencia preparada y recuperación

Motor local observado: **MySQL 9.6.0**, InnoDB, utf8mb4_unicode_ci, prepared statements nativos. Los metadatos de `mxmed` y QA05 confirman cuentas, membresías y ownership approvals existentes; no existen allí endorsements R3, governance cases ni tablas de este fundamento. R3 y el fundamento se instalan sólo en las bases sintéticas del ensayo. No se encontró ledger de migración en `mxmed` por metadatos de tablas; no se inventa un ledger ni se marca una migración aplicada. Los nombres `2026_10_15_02_*` son nuevos en el directorio Identity; el anterior es `2026_10_15_01_identity_governance_r3_runtime.sql`.

[Forward](../../modules/identity/db/migrations/2026_10_15_02_profile_operator_foundation.sql) crea cuatro tablas, todas ensayadas sólo con padres sintéticos:

| Tabla | Función / integridad |
|---|---|
| auth_profile_operator_invitations | FK a perfil, owner emisor y membresía aceptada; oferta inmutable por invitación; hash único, contacto pendiente único por perfil; CHECK de estados, aceptación, cierre y TTL exacto de siete días. |
| auth_profile_operator_grant_revisions | FK a membresía/invitación/owner; revisión histórica única; una revisión actual por membresía; estados active/review_required/superseded/revoked y vigencia. No es otra membresía. |
| auth_profile_operator_grants | Claves individuales con FK a revisión y CHECK del catálogo de cinco. Sin wildcard ni flag general de módulo. |
| auth_profile_operator_commands | Receipt de idempotencia por perfil/cuenta/clave, digest de payload y correlación servidor. Sin token en resultado/persistencia de comandos. |

El forward se ensaya también por segunda vez. [Recovery](../../modules/identity/db/migrations/2026_10_15_02_rollback_profile_operator_foundation.sql) exige las cuatro tablas instaladas y vacías: un guard temporal CHECK aborta si hay cualquier historia. Se ensayan tanto recuperación vacía como rechazo de recuperación poblada. No es una autorización para borrar historia real. Tras fallo, cerrar la conexión elimina el guard temporal; una repetición en la misma conexión exige retirarlo. Ante despliegue poblado, deshabilitar consumidores y conservar tablas/auditoría; evaluar recuperación con evidencia separada. El ensayo elimina sus bases completas en `finally`.

## Cupo, concurrencia y puntos de orden

Todo comando toma primero `SELECT doctor_id FROM profiles_doctors … FOR UPDATE`; las verificaciones de owner, reservas, membresías y grants se ejecutan dentro de esa transacción. Cuenta personas por account_id con membresías pending/active/suspended, más reservas de invitación pendientes no vencidas. No identifica personas por un email mutable. La creación rechaza contactos ya asignados; aceptación cierra la reserva y crea la membresía dentro de la misma transacción. Varias etiquetas técnicas de una persona no añaden plazas; una membresía ambigua tampoco concede acceso efectivo. El owner activo no consume plaza. Sólo después de cerrar reserva y crear membresía/grants/auditoría se confirma aceptación: no hay doble conteo visible.

La exclusión funciona para los comandos del nuevo fundamento y la transición gobernada, no autoriza coexistencia con writers legacy que no toman ese lock. Un cupo ya inconsistente falla cerrado. Los deadlocks/errores revocan la transacción; no se reintenta una mutación parcialmente aplicada ni se reporta éxito optimista.

Idempotencia devuelve el receipt histórico original y no reenvía token ni reactiva derechos. Payload distinto con la misma clave se rechaza. Una aceptación con otra clave/token ya cerrado se rechaza. La autorización de cada acción se vuelve a resolver desde estado actual, no desde el receipt.

## Owner gobernado y auditoría

ProfileOperatorOwnershipBoundary se invoca en ProfileMembershipRoleChangeService y R3ProfileGovernanceService dentro de la transacción existente, conservando aprobación independiente, MFA/step-up y consumo de evidencia. R3 recovery toma el mismo lock de perfil antes de la transición. Un cambio con datos de operadores y sin boundary configurado lanza `operator_ownership_boundary_required`; la composición productiva no recibe automáticamente el adaptador desechable.

La prueba realiza una recuperación GOV03A real sintética con dos aprobadores independientes, además de la carrera de aceptación contra su commit. Si gana aceptación, el grant se marca para revisión; si gana el cambio de owner, el token queda cancelado y aceptación se deniega. Otro caso determinista comprueba cancelación pendiente. Se prueba fallo del audit dentro del hook: roles, caso, invitaciones y grants se revierten juntos. Nuevo owner debe revisar, y suspensión sigue vigente hasta reactivación separada.

Se reutilizan INVITATION_CREATED/ACCEPTED/REVOKED, extendiendo únicamente su whitelist de metadata para operador. Se agrega el evento específico `PROFILE_OPERATOR_DELEGATION_CHANGED` (operación ROLE_LIFECYCLE, módulo ROLE) para grants, suspensión/reactivación/revocación y review. El catálogo genérico SENSITIVE_ADMIN_ACTION permanece vacío; no se elude ni se duplica. El catálogo completo tiene 51 eventos/políticas coherentes. El servicio sólo emite acciones cerradas, atribuye cuenta/sesión y membresía del actor, target, owner emisor, revisión, lista de capacidades, motivo y UUID de correlación del servidor.

El writer/sealer/mapper son los canónicos. El puerto SQL **sólo de tests** se une a la transacción del schema desechable; no llama las rutinas productivas calificadas a `mxmed`. OP-IMP02 requiere composición productiva y contrato de dispatch/outbox: aquí la notificación es exclusivamente memoria posterior al commit. No hay garantía de entrega durable/reintento SMTP, ni se anuncia envío real. Sesión se revalida antes del commit; la atomicidad demostrada es SQL/auditoría y orden de perfil. No se afirma una transacción distribuida entre Valkey y SQL.

## Denegación transversal y deuda antes de activación

AccountMembershipRepository expone procedencia. CanonicalDoctorMembershipPolicy rechaza `owner_operator_invitation` para clínica y shell completo, incluso con rol owner/administrator. FailClosedAuthorizationService rechaza grants comerciales amplios para esa procedencia en el perfil; ninguna acreditación profesional se crea. Pruebas llaman los resolvers reales con cuentas/sesiones sintéticas y verifican 403/contexto nulo aun con plan óptimo. Director y QA05 conservan sus fuentes de asignación anteriores.

**No se declara prueba exhaustiva de todos los endpoints HTTP/aliases/descargas/background legacy.** Por ello invitación y aceptación sólo existen en el factory CLI desechable; no se habilita un operador real. OP01_28/29 demuestran denegación canónica y este cierre de composición, no la seguridad de consumidores legacy aún no conectados. No se cambió Recetas, fingerprints, autoridad profesional, pagos o productos.

Antes de OPERADORES funcional en Safari (OP-IMP02): consumidores Agenda por operación y scope real, APIs de owner/aceptación canónicas con controles de frecuencia y dispatch durable; prueba HTTP completa de clínica/pacientes/adjuntos/diagnósticos/recetas/credenciales/staff/fiscal/suscripción, aliases/downloads/hidratación/background/switching; reconciliación legacy y cierre de writers; ensayo/autorización separados de migración y composición real; por último interfaz existente con contador servidor y permisos efectivos. Ningún bloqueo de esa activación se presenta como función ya entregada.

## Evidencia reproducible

```sh
php modules/identity/tests/OperatorFoundationTest.php
php modules/platform/tests/PhysicianCredentialAuditPolicyTest.php
php modules/platform/tests/AuditMp01FSemanticProducerTest.php
php modules/platform/tests/AuditMp01FStaticContractsTest.php
php modules/agenda/tests/Cut01AAuthorityCompositionRootsTest.php
```

La primera suite crea únicamente bases `mxmed_op01_*` vacías, usa emails `.invalid`, perfiles `op01_…`, contraseñas sintéticas y sesiones Identity en memoria. No copia datos reales. Procesos PHP independientes compiten por los locks reales de InnoDB, con barrera de inicio. No se usa doctor_id=1. Compara CHECKSUM de **todas** las tablas de `mxmed` y schemas persistentes `*qa05*` antes/después; no imprime filas/contactos/hashes de credenciales/tokens. El test de credenciales compara digests completos sin GROUP_CONCAT truncable.

| QA | Evidencia ejecutada |
|---|---|
| OP01_01–05 | Owner+dos, tercero denegado, combinaciones CC/CA/AC/AA, mismos grants y grants distintos sin implicación. |
| OP01_06–10 | Reserva pendiente, suspendido conserva, transición canónica revocada libera, expiración servidor y cancelación liberan. |
| OP01_11–12 | Tres invitaciones concurrentes → dos éxitos; cuatro aceptaciones concurrentes sobre dos tokens → dos membresías. |
| OP01_13–16 | Un uso, 604800 segundos, cambio de oferta reexpide y bloquea token viejo, contacto exacto verificado/cuenta reutilizada. |
| OP01_17–19 | Owner exclusivo; admin/collaborator/extraño no gestionan ni invitan ni amplían grants propios. |
| OP01_20–22 | GOV03A dual real sintético, hook obligatorio, rollback de owner/audit, carrera commit/aceptación, cancelación y revisión/reactivación explícitas. |
| OP01_23–27 | Cinco planes sin tercera plaza, MAX3, roles duplicados y cambios de email no eluden plazas; credenciales sin cambios, grant vacío/revocado/vencido deniega, otro perfil denegado. |
| OP01_28–29 | Catálogo cerrado, autorización amplia rechazada, resolvers clínicos/shell reales deniegan procedencia no médica con 403 y sin hidratación; aceptación fuera del entorno aislado cerrada. |
| OP01_30–33 | Writer y cadena canónicos, actor real, auditoría falla y revierte sin notificación, sesión/CSRF/origen/expiración/revocación/version mismatch, replay idempotente/conflicto. |
| OP01_34–37 | Checksums normal-local/QA05 iguales, sólo notificaciones en memoria, ambas bases desechables eliminadas. |
| OP01_38 | Nueve suites existentes Identity/GOV03A pasan. Cuatro suites adicionales de audit/Agenda pasan aparte. |

Las nueve suites internas son CanonicalClinicalIdentityResolverTest, C3ProductiveSessionContractTest, PrivateSession02ContractTest, Qa05AdminEnvironmentContractTest, GovernanceGov03ATest, GovernanceHttpContractTest, ProfileMembershipRoleChangeServiceTest, ProfileOwnershipPersistenceTest y GovernedAuthorityPreparationTest.

PhysicianCredentialAuditPolicyTest se adapta al evento adicional y a la extensión exacta de metadata. Conserva el digest de las políticas históricas tras retirar sólo esa extensión. El digest QA02B anterior ya era obsoleto en el HEAD publicado: se comprobó contra `git show a18cc367:modules/platform/services/CanonicalAuditPolicyRegistry.php` y se fijó el digest real de ese baseline; no se relajó la comparación ni se cambió la política profesional.

```text
OP01_01_THROUGH_38=PASS_DISPOSABLE
EXISTING_IDENTITY_GOV03A_SUITES=9_PASS
ADDITIONAL_AUDIT_AGENDA_SUITES=4_PASS
NORMAL_LOCAL_DB_UNCHANGED=true
QA05_PERSISTENT_DATA_UNCHANGED=true
REAL_MEMBERSHIPS_UNCHANGED=true
REAL_INVITATIONS_SENT=false
NORMAL_LOCAL_MIGRATION_APPLIED=false
REAL_PERMISSION_GRANTS_CREATED=false
CROSS_MODULE_HTTP_DENIAL_PROOF=INCOMPLETE_REAL_ACCEPTANCE_DISABLED
OPERADORES_SAFARI_READY=false
```
