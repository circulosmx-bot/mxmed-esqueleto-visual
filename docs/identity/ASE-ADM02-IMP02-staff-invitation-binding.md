# ASE-ADM02-IMP02 — invitación preautorizada y vínculo Identity

2026-10-18. Padre verificado local/upstream/remoto `2330be886bc984d8ce89c5952113d540bdf1f3cb`, limpio. Feature `feature/ase-adm02-staff-invitation-binding`, worktree `mxmed-ase-adm02-staff-invitation-binding`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y visual `312ee388f93c892e6caf7e845b11e2b42f9c3565` preservados. **UI-0, fundación aislada; ninguna invitación real ni activación staff.**

## Alcance y decisiones conservadas

La solicitud IMP02 autoriza invitación, matching autenticado, consumo/vínculo atómicos, recuperación y QA sintética. Reutiliza el expediente [IMP01](ASE-ADM02-IMP01-staff-case-foundation.md) y conserva los siete principios de [POL01](ASE-ADM02-POL01-staff-admission-policy.md). Sucede únicamente la ausencia del writer de binding; no habilita revisión humana, aprobación de admisión, register, grants, MFA, documentos ni UI.

| Eje | Estado |
|---|---|
| PRODUCT_POLICY | Director autoriza email exacto e intención; solicitante autentica/verifica su cuenta y acepta sólo su invitación. Master no recibe autoridad de admisión. TTL/canal/plantillas/legal/retención/custodia reales siguen sin ratificar. |
| SOURCE_IMPLEMENTATION | Invitaciones y recibos propios mínimos, writer de binding dentro de Service IMP01, UnitOfWork compartida, puerto de entrega específico. Sin segundo writer de cuentas/staff ni expansión del token genérico. |
| CONNECTIVITY | Seis endpoints preparados con gate default off. Composición HTTP permanece indisponible incluso con flag mientras falte política productiva; no adapter positivo de prueba seleccionable por HTTP/env. Flujo positivo probado por CLI canónico y lectura/corrección/confirmación posterior por HTTP IMP01. |
| VERIFICATION | [QA reproducible](../evidence/ase-adm02-imp02/qa.json): MySQL/Valkey/TLS nuevos propios, registro/verificación/login reales sobre datos sintéticos, estado SQL y audit inspeccionados. Alcance y límites abajo. |
| OPERATIONAL_STATUS | IMPLEMENTED_BUT_INACTIVE. Sin merge, despliegue, operación real, notificación externa ni cambio de previews originales. |

## Propósito, destinatario y datos

[InvitationService](../../modules/identity/StaffAdmission/InvitationService.php), [Input](../../modules/identity/StaffAdmission/InvitationInput.php), [Policy](../../modules/identity/StaffAdmission/InvitationPolicy.php). `recipient_reference` de IMP01 sigue siendo una referencia administrativa opaca, nunca prueba de email o identidad humana. El Director introduce `recipient_email`; se normaliza exclusivamente con IdentityAccount::normalizeEmail, con límite técnico de 190 bytes. No se busca/crea cuenta durante emisión ni se revela si existe.

Destinatario persistido: HMAC-SHA256 con clave dedicada de al menos32 bytes, dominio `staff-admission-recipient`, case_id y email normalizado; key-id explícito. Separación por caso impide trasplantar el digest. El recibo usa HMAC con otro dominio para el payload canónico ordenado: ni email ni token aparecen en claro en recibos. Clave sólo inyectada; no default, env mágico ni clave en Git. Rotación/key-id desconocido deniega; una futura política de rotación/retención debe definir reconciliación antes de producción.

El token usa OneTimeTokenCodec::issue:32 bytes CSPRNG,43 caracteres base64url; sólo SHA-256 persistido, apropiado para secreto aleatorio de alta entropía. Propósito exacto `STAFF_ADMISSION_ACCOUNT_BINDING`; no es sesión, password reset, invitación de operador médico ni verificación email. OneTimeTokenRepository exige account_id y no sirve para intención pre-cuenta; sus propósitos/records/NotificationMessage permanecen idénticos.

[Migración nueva18_05](../../modules/identity/db/migrations/2026_10_18_05_staff_admission_invitations.sql), después de18_04:

| Objeto | Invariantes |
|---|---|
| auth_staff_admission_invitations | ID servidor, caso/versión FK histórica, creador/credencial, propósito, recipient digest/key-id, token digest único, referencia de validez, emisión/vencimiento/última observación, estado, entrega y consumo cuenta/credencial/fecha. Unique active_case permite una invitación ACTIVE por caso. Sin email ni token raw. |
| auth_staff_admission_invitation_operations | PK actor+UUID, intención HMAC, invitación/caso/versión, comando, evento audit y fecha. Recibo inmutable, no segundo almacén de auditoría ni perfil. |

Tres triggers impiden edición/borrado de recibos y cambios de intención, extensión de expiración, retroceso de tiempo observado, reactivación de estados terminales o cambio de consumidor. CHECKs/FKs refuerzan propósito, estados y consumo completo. No edición del SQL18_04 ni de filas históricas. No ALTER/backfill ni cambio de DeploymentPackage/orden [COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md). Ensayo incremental sobre IMP01 ya ejercitado, exclusivamente en instancia fresca desechable; no prueba upgrade de datos reales ni rollback DDL transaccional. Retención/borrado gobernado, incluyendo triggers y backups, sigue pendiente antes de live.

## Autoridad y transacción

Director ACTIVE con cuenta verificada/sesión/credencial vigentes puede issue/reissue/revoke. `internal_advisors_manage`, delegación existente o clase Master no autorizan admisión; Advisor y solicitante no emiten. Un cambio de email exige reissue explícito del Director con invitation_id anterior y nueva operation_id. Nunca se infiere desde el perfil del navegador.

[UnitOfWork](../../modules/identity/StaffAdmission/UnitOfWork.php) extrae sin relajar el boundary IMP01: resolver sesión canónica → iniciar PDO → locks cuenta/staff/credencial actor → cuenta activa/email verificado/credencial actual → caso → invitación. Los comandos de caso y de invitación comparten ese límite; no transacción exterior. Actor lock serializa operation-id y caso lock serializa edición/revocación/reemisión/aceptación.

Accept exige cookie canónica, case_id/invitation_id/token/version exactos, estado ACTIVE, propósito/key-id correctos, email actual normalizado de auth_accounts igual al destinatario HMAC, caso PREPARING no vinculado ni revocado, versión todavía autorizada. Nombres iguales o posesión de email/token sin sesión no dan autoridad. Cualquier relación internal_staff existente, activa o suspendida, deniega con conflicto minimizado: no segunda relación, reactivación, promoción/democión ni grant.

`Service::bindInvitationWithinTransaction` es el único writer nuevo de esos campos del expediente existente. Bajo la misma transacción: consume invitación, establece bound_account_id/binding_credential_version/binding_reference, registra auditoría canónica y recibo. No modifica versión/perfil: el binding acredita cuenta/destinatario, no confirmación de información. Confirm/correct posteriores usan exactamente el versionado IMP01. Credencial rotada invalida acceso de solicitante hasta una futura reconciliación autorizada; no refrescar binding silenciosamente.

**Chequeo final:** inmediatamente antes de PDO::commit, resolver de nuevo la sesión en Valkey, comparando actor y sessionId; SessionService vuelve a comprobar credencial/estado. El ensayo revoca la sesión mientras espera el lock y demuestra rollback de binding/consumo. No hay atomicidad distribuida SQL/Valkey: una revocación posterior al último chequeo puede intersectar el commit; cualquier acción siguiente revalida. No se promete eliminar esa ventana mediante un lock SQL.

## Expiración, reemisión y entrega

InvitationPolicy exige duración y referencia explícitas; no existe TTL producto predeterminado. Fixture CLI usa **120 segundos, sólo sintético**, con reloj controlable. Emisión y comprobación toman el máximo del reloj inyectado, UTC de SQL y última observación persistida. Expiración observada se confirma como EXPIRED con audit antes de devolver denegación genérica; estados terminales no reviven por replay o retroceso del reloj de aplicación. La exactitud del reloj SQL del despliegue sigue siendo dependencia operativa. Fallo audit revierte su mutación y deniega, nunca acepta.

Reissue bajo lock reemplaza ACTIVE anterior y emite nueva intención/token; REVOKED/EXPIRED permiten una nueva emisión explícita, sin reactivar su fila. CONSUMED/REPLACED no se reutilizan. Versión de perfil cambiada exige nueva intención sobre versión actual. Issue sólo sirve si el caso nunca tuvo invitaciones; no regeneración ciega después de error.

[InvitationDeliveryPort](../../modules/identity/StaffAdmission/InvitationDeliveryPort.php) es específico de staff y no tiene adapter productivo. Token/email sólo pasan transitoriamente al puerto tras el commit; respuesta de emisión nunca devuelve token. Sólo el fixture CLI implementa un sink inerte fuera del repo, directorio0700/archivos0600, email example.test, sin proveedor/red externa.

- INVITATION_CREATED: invitación/recibo/audit confirmados en SQL.
- DELIVERY_PENDING: no confirmación durable de entrega; incluye fallo o ACK incierto.
- DELIVERY_CONFIRMED: el puerto confirmó y una segunda transacción auditada registró ese hecho. En QA significa exclusivamente escritura del sink, nunca correo enviado.
- ACCOUNT_CASE_BOUND: aceptación consumida y vínculo confirmado; staff_accreditation sigue PENDING.

Fallo de entrega conserva creación canónica. Replay devuelve recibo, jamás reenvía ni genera otro token. Consultar result; si entrega sigue incierta, Director decide reissue explícito que invalida el anterior. No outbox plaintext, recuperación del raw token desde SQL ni compensación de cuenta/staff. Entrega no comparte transacción con SQL: puede haber entrega del token ya reemplazado o confirmación perdida; aceptación sigue validando estado actual.

## Identity y confirmación personal

Cuenta nueva usa RegistrationService, contraseña elegida por el solicitante y consents canónicos terms/privacy_notice; EmailVerificationService consume su token para activar Identity. No auto-registro desde sesión Director. En QA los documentos/versiones son explícitamente ficticios; no legal real aprobado. Cuenta existente usa CredentialAuthenticationService/SessionService y conserva contraseña, credencial y cuenta, sin reset/merge. Una cuenta puede tener distintas intenciones administrativas; cada token queda restringido a un solo caso y no crea relación staff.

Después de binding legítimo, reader IMP01 permite sólo caso propio; correct/confirm conservan snapshots/procedencia originales. Estado PERSON_CONFIRMED significa **PERSON_INFORMATION_CONFIRMED**, no IDENTITY_REVIEW_COMPLETED, STAFF_ADMISSION_APPROVED, STAFF_ACTIVE ni MASTER_ELIGIBLE. No cambia categoría, invitador, destinatario ni grants. [Master](ASE-INT03B-IMP01-master-security-foundation.md) conserva UnavailableStaffIdentityReview y falla cerrado; gobierno [IW01](../product-completion/iw01-internal-governance-foundation.md)/GOV03A y UI INT02/03 intactos.

## Recibos, límites y HTTP preparado

UUIDv4 estable por actor y digest HMAC de acción/payload normalizado. Duplicado exacto autorizado devuelve recibo; conflicto deniega. Result sólo al actor original con autoridad actual: Director para sus operaciones administrativas; solicitante para su vínculo exacto y credencial vigente. Incluye outcome histórico y estado/entrega/expiración actuales; no promete que una invitación creada siga utilizable. ACK incierto se reconcilia por operation-id antes de decidir otra acción; sin retries automáticos.

Aceptación consume RateLimitService TOKEN_CONSUME existente (5/900s, seguridad canónica, no TTL de invitación) con dimensión `staff-admission:<actor>`, hash canónico. Transacción separada antes del dominio conserva intentos aunque binding falle; storage indisponible deniega. Respuestas genéricas frente a token/caso desconocido, expirado, reemplazado, destinatario equivocado y rate limit. Autenticación ausente sigue401; formato inválido400. Los intentos fallidos no consultan un directorio público.

[InvitationHttp](../../modules/identity/StaffAdmission/InvitationHttp.php) prepara prefijo `/api/internal/staff-admission/invitation-`:

| Sufijo | Método y campos exactos |
|---|---|
| csrf.php | GET sin query, bootstrap autenticado sin descubrimiento de casos |
| result.php | GET operation_id |
| issue.php | POST operation_id, case_id, expected_version, recipient_email |
| reissue.php | POST campos issue + invitation_id anterior |
| revoke.php | POST operation_id, case_id, expected_version, invitation_id |
| accept.php | POST campos revoke + token; nunca account_id/role del cliente |

TLS/proxy canónico, Origin/Fetch-Site, sesión, CSRF autenticado, JSON plano sin claves repetidas/ajenas, máximo2048 bytes, no-store/private y errores sin SQL. Gate `MXMED_STAFF_ADMISSION_INVITATIONS_ENABLED` default off. InvitationComposition conserva autorización privada/pin, schema fingerprint, referencias privacy/retention/custody y source hash de IMP01; el hash adicional cubre todos los archivos de dominio/endpoints y ambas migraciones.

**Bloqueo intencional producto:** falta contrato de validez/canal/legal de producción y adapter de entrega aprobado. La composición siempre lanza `staff_invitation_production_policy_unavailable` después de los gates anteriores, incluso con flag1. Ni env QA ni adapter de matching positivo pueden abrirla. Aceptación HTTP positiva también queda indisponible en esta fase; se ensaya el servicio real por CLI y se informa esta limitación, como permite el alcance solicitado. Una futura conexión necesita política ratificada, composición de claves/canal, principal mínimo específico y aprobación privada ligada a schema/fuentes; no basta encender un flag.

## Auditoría y QA

Reutiliza STAFF_ADMISSION_CASE_CHANGED para cambios de intención/vínculo del mismo expediente y STAFF_ADMISSION_CASE_READ para result; operaciones/provenance `invitation_issue/reissue/revoke/accept/expire/delivery`, metadata cerrada case_id/version/operation/provenance. Director administrativo vs solicitante mantienen ADMIN_DECISION/USER_REQUEST. Evento canónico, actor/sesión/correlación, UUID enlazado por recibo; no email/token/digest en metadata. No extensión del catálogo: las59 políticas y sus pins históricos siguen intactos. Falla audit revierte consumo, vínculo, recibo y stream head en el mismo PDO.

[Runner](../../scripts/ase-adm02-invitation-qa.py), [checks](../../scripts/ase-adm02-invitation-checks.py), [fixture CLI](../../modules/identity/tests/StaffInvitationFixture.php). Reutiliza harness propietario IMP01 y su namespace temporal ase-adm02imp01; cada ejecución crea datadir/DB/puertos nuevos y verifica datadir antes de DDL. No usa bases mxmed/QA05/OP02/UI03. Principal fundación mantiene prohibición SQL de binding; principal invitación separado sólo puede actualizar campos binding e invitaciones/recibos/rates/audit, sin crear cuentas/staff/grants. Registro canónico sintético usa principal fixture del mismo datadir, no una cuenta real. No importación de datos.

**201 comprobaciones PASS, siete escenarios concurrentes** (tres IMP01 y cuatro de invitación/sesión), más dos regresiones audit y lint PHP/Python. [Verificación final](../evidence/ase-adm02-imp02/final-verification.json) y [limpieza](../evidence/ase-adm02-imp02/cleanup.json).

A–AL cubren preparación/emisión exactas, entrega inerte/fallida, nuevo registro/consents/verificación/login reales y cuenta existente, matching/normalización, negativas de sesión/cuenta/staff/destinatario/nombre, vencimiento/retroceso/revocación/reemisión, deriva/revocación del caso, tokens cruzados, rebind, idempotencia/ACK, lectura/corrección/confirmación HTTP propia y campos prohibidos, Director actual/Master/Advisor, rollback audit, límites/no enumeración, SQL constraints, regresión IMP01/Master y defaults cerrados. Concurrencia usa procesos y conexiones SQL independientes, con esperas observadas; no prueba de carga ni claim de atomicidad distribuida.

Los fallos previos del harness quedan en evidencia: router no propagaba return; principal nuevo carecía inicialmente de LOCK TABLES y SHOW_ROUTINE requeridos por locking/audit; la carrera de primer bucket rate limit podía denegar antes de alcanzar el lock de caso. Se corrigió el harness/principal y se prepara el bucket canónico antes del escenario que exige observar dos esperas; el servicio de límites sigue fallando cerrado, sin bypass. No se relajó matching ni auditoría. Limpieza de recursos propios y verificación final se registran junto a QA.

Limitaciones: no positivo HTTP de invitación por políticas pendientes; no UI/browser/Safari nuevo (UI-0), documentos reales, revisión/aprobación, MFA, staff activation, canal externo, legal/retención/custodia reales, ensayo de upgrade persistente ni despliegue. Los archivos de secretos sintéticos se sobrescriben y retiran antes del descarte del datadir; esto no afirma borrado forense de bloques/snapshots del filesystem.

## CTX-SYNC01

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | 2330be886bc984d8ce89c5952113d540bdf1f3cb; oficial f92f37f1; visual312ee388; POL01/IMP01 y contratos ASE/IW01/GOV/COMPAT. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10; reuso ID-01/02/03, sin nuevas capabilities/delegaciones. |
| AFFECTED_JOURNEY_IDS | J09 parcial de admisión; J08 comercial/D01–D08 preservado. |
| AFFECTED_DATA_OBJECTS | Dos tablas invitación/recibos nuevas; binding en caso IMP01; Identity y audit reutilizados. Staff/grants sin cambios. |
| AFFECTED_CONTEXT_FILES | Este contrato; sucesores en POL01/IMP01; estado/router; inventario/datos/interacciones; IW01. |
| CONTEXT_CHANGES_IMPLEMENTED | Propósito, destinatario, ciclo, matching canónico, cuenta nueva/existente, transacción, confirmación, autoridad, audit/recuperación, delivery sintético y bloqueos de producción. |
| CONTEXT_VERIFICATION | QA enlazada, regresiones audit, lint, diff completo, hashes históricos/UI, enlaces y preservación de doce worktrees/listeners/pointer. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED para fundación aislada; sin afirmación de operación real. |
| DOCUMENTATION_COMMIT | Commit de código/documentación informado al cierre; sin hash futuro autorreferente. |
| REMAINING_CONTEXT_GAPS | TTL/canal/plantillas/legales/retención/custodia/catálogo extranjero/delegación Master; composición productiva y futura UI; IMP03 evidencia, IMP04 revisión, IMP05 grants/MFA/handoff. |

Siguiente autorización: ratificar decisiones pendientes y aprobar la fase posterior concreta. Esta entrega no autoriza invitaciones reales, merge, despliegue ni apertura operacional de admisión.
