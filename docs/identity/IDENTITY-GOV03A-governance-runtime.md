# IDENTITY-GOV03A / GOV03B-FIX01 — gobierno de Identity

**Actualización vinculante [GOV-SIM02](GOV-SIM02-physician-ownership-session.md):** owner exclusivamente médico legítimo; nuevos casos iniciales rutinarios usan una aprobación staff con capacidad/MFA/reauth y aceptación independiente, sin evidencia externa/root. El resto de este documento conserva el estado histórico FIX01 salvo esas correcciones. Los casos legacy y recuperaciones conservan sus protecciones. Emisor C4/ADM02 y firma clínica siguen inactivos; nueva proyección/esquema sólo preparados. Revisión obligatoria de doce meses y recuperación extraordinaria V3: `DEFERRED_NOT_APPROVED`.

Propuestas posteriores, **sin ratificar ni implementar**: [GOV03B-DEC01](GOV03B-DEC01-policy-reconciliation.md) reconcilia elegibilidad C4/ADM02 y recuperación extraordinaria de la raíz. Los bloqueos y el estado runtime descritos aquí siguen vigentes; esa documentación no autoriza migraciones, custodias o autoridades reales.

Estado actualizado después de OP-ACT02A y OP-ACT02B. La fuente inicial de FIX01 es `4bb40493faf38195b760254e3fd7c12776891175`. Esta entrega corrige contratos y ensaya exclusivamente cuentas, llaves, bases y sesiones sintéticas desechables. No autoriza crear autoridades humanas ni habilitar normal-local.

| Dimensión | Estado |
|---|---|
| Código de seguridad GOV03B | Implementado y sujeto a los resultados de QA de esta entrega; no equivale a operación real disponible |
| Esquema GOV03A / Operadores OP-ACT02A | **Instalado**: 8 + 7 tablas, inicialmente vacías; no repetir sus seis migraciones |
| Esquema adicional GOV03B | Preparado y probado sólo en bases desechables; **no instalado en mxmed** |
| Autoridad humana interna | No establecida; no se seleccionan personas por pruebas o fixtures |
| Runtime de gobierno / operadores | Deshabilitados; no cambian secretos ni procesos normales |
| Primer owner | No aprobado ni asignado; el beneficiario sigue sin designación autorizada |
| Acreditación / firma clínica | Sin nuevo permiso; CLIN-AUTH02A permanece cerrado |

## Transporte y entrada

El proxy TLS local eliminaba `X-Forwarded-*` mientras `governance.php` exigía ese header o `HTTPS=on`. Ahora el terminador TLS puede emitir una prueba HMAC de petición mediante una **llave de despliegue separada** `MXMED_GOVERNANCE_PROXY_KEY` (mínimo 32 caracteres). El cliente no puede suministrar esa prueba: el proxy elimina todos sus headers `X-MXMED-TLS-*` antes de construirla. Se ligan timestamp, nonce, método, URI exacta, Host, Origin, Referer, Cookie, CSRF y SHA-256 del cuerpo. Ventana máxima 30 segundos y peer loopback. `X-Forwarded-Proto`, Host o un flag por sí solos no acreditan TLS. PHP cli-server no acepta `HTTPS=on` heredado como prueba de transporte. TLS nativo de un servidor que realmente lo termina conserva su señal de servidor.

`GovernanceHttpSecurity` reutiliza `CanonicalHttpSessionResolver`, `SessionService` y `CsrfTokenService`: POST, origen exacto configurado (o Referer del mismo origen), cookie `__Host-mxmed_session`, cuenta/credencial vigentes y CSRF ligado a sesión. No hay mutación por GET. **Bootstrap y enrollment no tienen rutas HTTP**. La API sigue apagada antes de resolver Identity cuando falta su flag. No hay interfaz nueva.

La bandera `MXMED_IDENTITY_GOVERNANCE_ENABLED=1` nunca basta. `GovernanceHttpComposition` exige además:

- El esquema GOV03B completo, sin creación diferida de tablas/columnas.
- `MXMED_STAFF_MFA_ENCRYPTION_KEY`: base64 de 32 bytes; `MXMED_STAFF_MFA_KEY_ID`: identificador de custodia/rotación.
- `MXMED_GOVERNANCE_TRUST_ANCHOR_PUBLIC_KEY`: pública Ed25519 del emisor humano aprobado (obligatoria para bootstrap/recuperaciones, no para casos iniciales rutinarios); `MXMED_GOVERNANCE_ORGANIZATION_ID`: audiencia exacta. El ambiente se obtiene de Identity.
- `MXMED_AUDIT_IP_HMAC_SECRET` y `MXMED_AUDIT_IP_HMAC_KEY_VERSION` válidos, más tablas y rutinas canónicas del PDO conectado.
- Un archivo privado fuera del repositorio, padre 0700/archivo 0600, sin symlinks, indicado por `MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION`, y su pin `MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256`.

El documento de despliegue v2 requiere `state=approved_for_governance`, referencia de autorización, vencimiento vigente de máximo 24 horas, `allowed_actions`, `target_account_ids`, DB/UUID/versión/usuario reales, fingerprint de esquema, `source_hash`, organización/ambiente y fingerprints de la pública externa y de la llave MFA junto con `mfa_key_id`. No contiene secretos. La ceremonia MFA necesita `mfa_registrar_account_ids` nominativos. Bootstrap necesita también `operation_id` y `case_reference` exactos. Es un gate operacional adicional, nunca un grant ni un emisor de acreditación. No se genera ni aprueba automáticamente. Los journals de OP-ACT02A **no son** este documento.

## Primera autoridad y ceremonia CLI

Entrada existente preparada: `php scripts/governance-ceremony.php --action bootstrap --input /ruta/privada/ceremonia.json`. Las otras acciones son `mfa_begin`, `mfa_activate` y `mfa_cancel`; begin requiere `--secret-output /ruta/privada/nueva.json`. La entrada debe estar en un directorio privado fuera del repositorio y ser 0600. No pasar cookies, contraseñas, evidencia o códigos por argumentos. No hay modo anónimo, credenciales por defecto, seeds de roles ni bootstrap al detectar esquema.

La CLI exige `MXMED_GOVERNANCE_CEREMONY_ENABLED=1`, autorización privada pinneada por acción/target y configuración criptográfica/audit válida. Resuelve la sesión canónica y reautentica al titular mediante `CredentialAuthenticationService`. El input contiene `target_account_id`, `session_token`, `email`, `password` y, para bootstrap, `operation_id`, `case_reference`, `signed_evidence`. La autorización y la evidencia deben coincidir en operación y caso.

`FirstDirectorBootstrapService` bloquea cuenta/credencial, exige cuenta activa y correo verificado y rechaza **cualquier** membresía profesional, aun no activa. Revalida la sesión/versión/reauth antes del consumo. El candidato no es el médico collaborator por defecto. La auditoría conserva los ratificadores externos y también la cuenta, sesión, versión de credencial y reautenticación de aceptación del candidato; no guarda su cookie ni contraseña. La evidencia externa, el único Director y `INTERNAL_GOVERNANCE_CHANGED` se guardan/consumen **en una sola transacción**: si falla auditoría, el envelope no queda staged por esta entrada. El índice de Director único y la operación/evidencia únicas conservan la exclusión concurrente.

No se implementa reemplazo extraordinario del primer Director si ya existe o no quedan custodios elegibles: el servicio rechaza repetir bootstrap. Falta una política humana de recuperación de esa raíz; no se infiere una ruta break-glass ni se cambian tablas manualmente.

## MFA de staff

**ASE-ALIGN01 V2 (2026-10-17):** un ADVISOR activo puede ser sujeto de esta
ceremonia si tiene un grant individual vigente de `physician_accreditation_review`
o `professional_source_provision`. El registrador sigue siendo otra cuenta
DIRECTOR/MASTER_ADMIN activa, nominada por despliegue, autenticada y
reautenticada independientemente; su autorización se verifica también al activar.
La activación auditada conserva al sujeto como actor y sólo admite su propio
factor y la sesión/credencial exactas de la ceremonia. Fallar auditoría revierte
la activación. Esto no permite al asesor administrar grants ni registrar factores
ajenos o sin registrador.

El uso del factor tiene un contrato separado y cerrado en
`StaffRoutineOperationPolicy`: `PHYSICIAN_ENROLLMENT_REVIEW` y
`PHYSICIAN_EVIDENCE_REVIEW` requieren `physician_accreditation_review`;
`PROFESSIONAL_SOURCE_PROVISION` requiere `professional_source_provision`.
Se revalida el grant al emitir/verificar challenge y consumir proof, además de
la autorización independiente del servicio consumidor. Dentro de la transacción
se usa una lectura actual con bloqueo: una snapshot REPEATABLE READ anterior
no puede conservar autoridad revocada por otra conexión. Las demás operaciones,
incluidos bootstrap, recuperación, ownership, cambios de autoridad profesional
y correcciones, conservan las restricciones previas. Esta alineación de fuente
no autoriza enrollment MFA real ni activa normal-local.

`GovernanceStaffMfaCeremony` compone `StaffMfaService`. Las mutaciones MFA y los casos R3 rechazan la construcción/ejecución sin sus dependencias canónicas; no existe un fallback v1 sin reautenticación. Requiere sujeto y registrador distintos, ambos staff activo de clase elegible, sesiones canónicas actuales y reautenticación independiente. Además, el registrador debe estar nombrado en la autorización de despliegue. Una cuenta médica no se transforma en staff mediante MFA. Las operaciones de nombramiento/grants siguen perteneciendo al escritor `InternalGovernanceService`; esta CLI no inventa autoridad para efectuarlas ni las siembra.

El inicio cifra el secreto y guarda el vínculo al sujeto, registrador, sesiones y versiones, con expiración de cinco minutos. No permite otro factor ACTIVE/PENDING sin cancelación/recuperación explícita. La entrega clara ocurre sólo en el artifact privado exclusivo, nunca en stdout, logs, URLs de terceros ni Git. El URI otpauth es material secreto para uso presencial/controlado por el titular, no un enlace público. Si falla la entrega del archivo, no se reporta éxito: inspeccionar el factor PENDING y cancelarlo por la ceremonia aprobada antes de reintentar.

La activación requiere la misma sesión/versión del sujeto, sesiones originales todavía válidas y posesión TOTP actual. Cinco fallos bloquean ese factor PENDING; no se reinicia el contador automáticamente. Cancelación auditada y otra ceremonia independiente son necesarias para intentarlo de nuevo. El consumo del factor/time-step impide replay. TOTP usa seis dígitos/30 segundos; las pruebas de acción admiten ±30 segundos, duran cinco minutos y se ligan al caso/operación/actor/sesión/versión/beneficiario/perfil. Cinco fallos de verificación bloquean el factor de acción durante 15 minutos, incluso al vencer/cambiar el challenge o crear otro nonce/caso; el contador se guarda atómicamente bajo el lock del factor. Verificar MFA exige además la reautenticación reciente guardada en el challenge; un TOTP no la sustituye.

Cambiar `key_id` o la llave sin la operación de custodia aprobada falla cerrado; el documento pinnea ambos. No se ofrece un re-encriptador sin autorización. Conservar acceso controlado a versiones históricas y backups; para pérdida/compromiso usar recuperación gobernada y reinscripción, sin revelar ni restaurar un secreto conocido.

## Solicitante, elegibilidad y caso exacto

`INITIAL_OWNER` ahora se abre por un solicitante institucional independiente, activo y verificado, con sesión/versión vigentes y la capacidad explícita ya existente `profile_initial_ownership_approve`. No puede ser el beneficiario. En casos GOV-SIM02 puede ser el único revisor/aprobador autorizado; en recuperaciones/legacy rige la separación anterior. Una cuenta collaborator ordinaria no adquiere esa capacidad al presentar un ID. El caso conserva cuenta, perfil y membresía exactos, snapshot del estado/updated_at y procedencia, y sesión del solicitante en `security_context` v2. Los registros v1 no se convierten automáticamente en aprobaciones v2.

**Bloqueo de producto conservado:** VID01 verifica identidad del perfil y CRD02 verifica credenciales del perfil; ninguna prueba vincula canónicamente al titular de una cuenta Identity con el profesional y una acreditación/claim vigentes C4/ADM02. La guía aprobada exige identidad oficial, licencia validada y acreditación humana para el owner médico. `CanonicalInitialOwnerEligibilityAdapter` deniega expresamente por `ownership_accreditation_account_binding_contract_unavailable`. Se exige este puerto en apertura y en el escritor transaccional; no existe un flag para aceptarlo. Ni correo verificado, identidad del perfil, cédula coincidente, rol o aprobación externa inventan acreditación.

Las pruebas positivas inyectan evidencia sintética únicamente desde un helper en tests, restringido a bases desechables/cuentas `example.invalid`. Esa inyección no existe en la composición runtime. **No hay un camino positivo real al owner hasta implementar/aceptar el emisor y binding faltantes bajo GOV-SIM02**, en una autorización separada; FIX01 no duplica C4/ADM02 ni habilita firma profesional.

El Director interno no recibe grants por bootstrap. El escritor interno actual sólo concede la capacidad de gobierno a `MASTER_ADMIN`; no se inventa una autoasignación al Director. El procedimiento debe designar un solicitante y dos aprobadores independientes que sí cumplan las capacidades existentes, o ratificar separadamente cualquier cambio de esa política. No promover personas para cumplir una fixture.

## Evidencia externa, aprobación y aceptación

El adapter Ed25519 acepta contrato **v2** con los claims anteriores de organización/ambiente, operación UUID, target, caso, dos ratificadores externos distintos del target/solicitante, dos referencias independientes, MFA/reauth atestados, autorización y vencimiento máximo de 15 minutos. Ahora exige también `operation`, `profile_doctor_id`, `membership_id`, `membership_version` y `requester_account_id`. Los campos profesionales son nulos para bootstrap; MFA_RECOVERY tiene su alcance de staff sin perfil. No se confía en los booleanos del navegador: sólo en los bytes firmados por el emisor aprobado. No se guardan envelope ni firma claros.

Para un caso ya abierto, su solicitante usa `POST cases/{id}/external-evidence` con el envelope. Se verifica antes de INSERT la coincidencia exacta con el caso/operación/cuenta/perfil/membresía/snapshot. Un caso legacy o de recuperación no puede reunir endorsements sin esa evidencia válida. Los nuevos casos iniciales rutinarios GOV-SIM02 no la requieren. El consumo final revalida audiencia, estado, vencimiento y bindings y consume esa misma evidencia una sola vez dentro de la transacción privilegiada. `REVOKED`, `EXPIRED` o `CONSUMED` no autorizan replay.

En recuperación/legacy, dos aprobadores distintos conservan cuenta verificada, staff ACTIVE, clase elegible, grant explícito, sesión/credencial actuales y factor ACTIVE. Cada uno reingresa su contraseña al pedir `cases/{id}/mfa-challenge`, usa `mfa/verify` con su TOTP y entrega el proof una vez a `cases/{id}/endorse`. Se rechazan solicitante, beneficiario y owner afectado en recovery. Las sesiones, versiones, autoridad y MFA se revalidan al consumo final. Caso: 30 minutos; aprobación: hasta 10 minutos o vencimiento del caso; reauth/MFA/step-up: cinco minutos. Rige siempre el vencimiento más temprano.

Sólo el beneficiario acepta el caso exacto con `cases/{id}/beneficiary-step-up`, su propia contraseña y sesión. `GovernanceRuntime` construye el comando desde el snapshot aprobado, sin adoptar silenciosamente un updated_at nuevo. `ProfileMembershipRoleChangeService` sigue siendo el escritor único: cuenta, perfil bloqueado, membresía existente/CAS, una membresía médica activa, cero owners, elegibilidad, dos aprobadores, evidencia externa y proof de aceptación; consume evidencias, cambia sólo rol y agrega los tres eventos canónicos y la finalización en la misma transacción. No se crea otra membresía.

## Recovery, revocación y auditoría

`OWNER_REPLACEMENT` revalida snapshot del owner y sustituto activos del mismo perfil, elegibilidad, solicitante y dos aprobadores independientes. El sustituto existente pasa a owner y el anterior a administrator atómicamente; no se reactiva una membresía revocada. `OWNER_REMOVAL` conserva otro owner activo o rechaza. No se autoriza exceder dos plazas por llamar administrator a una persona.

El hook canónico de operadores es obligatorio si existen invitaciones/grants que revisar; se conserva `ProfileOperatorOwnershipGuard` y se invoca `ownershipChanged` dentro de la transacción. No se activa el runtime de operadores para satisfacer una prueba.

`MFA_RECOVERY` liga el factor objetivo al caso, requiere solicitante y dos aprobadores no afectados y consume caso/endorsements al revocar el factor. Las recuperaciones agregan ahora `INTERNAL_GOVERNANCE_CHANGED` mediante el mismo escritor canónico, además de los eventos append-only de gobierno. Fallar auditoría revierte la mutación y el consumo. No se altera el catálogo histórico ni los eventos existentes.

`cases/{id}/expire` y `cases/{id}/revoke` requieren actor interno autorizado y auditoría. Cerran caso y evidencias no consumidas, liberando claves de objetivo activas sin eliminar historia. Un challenge vencido se marca EXPIRED al solicitar uno nuevo; no se recicla su nonce ni proof. Revocación de staff/grant, rotación de credencial o invalidación de sesión impiden consumo aunque el caso siga APPROVED. No hay reparaciones SQL manuales.

## Esquema y autorizaciones pendientes

`modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql` agrega nueve columnas en cuatro tablas existentes: contexto del caso, enrollment, reauth del challenge y seis campos de evidencia externa. Agrega FKs y CHECKs para esos bindings; no backfill, seeds ni nueva autoridad. OP-ACT02A no instaló estos campos. Hace falta autorización separada de backup, rehearsal/preflight de target, aplicación controlada y verificación; **no ejecutarla en mxmed en FIX01**. Runtime rechaza esquema parcial o documento con fingerprint anterior. No se habilita con una migración automática.

Checklist humano, por separado antes de cada operación real:

1. Nombrar al primer Director interno legítimo y confirmar cuenta sin membresías médicas; no suponer que sea la cuenta de revisión.
2. Ratificar emisor, organización/ambiente, pública externa y dos ratificadores externos con MFA/reauth reales.
3. Nombrar solicitante y dos aprobadores internos distintos, grants exactos y operadores de enrollment independientes; usar el gobierno interno existente con autorización nominativa.
4. Ratificar beneficiario/perfil/membresía y el contrato/evidencia cuenta-profesional-acreditación C4/ADM02. Sin ese contrato, detener owner.
5. Aprobar custodia/rotación de llaves, recuperación de factores y continuidad de autoridades. La recuperación extraordinaria del primer Director sigue requiriendo política expresa.
6. Aprobar esquema GOV03B, configuración y documento de despliegue por alcance; después bootstrap, enrollment, caso y transición específicos. Ninguna prueba constituye esa aprobación.
7. Autorizar operadores más adelante, con receipt/origen/canal reales y dos plazas/cinco grants, conservando `private-app.php`, Agenda y paneles originales. Ningún SES/invitación real en esta entrega.

## QA reproducible

Con conexión administrativa privada que permite leer sólo el esquema fuente de `mxmed` y crear/borrar bases propias:

- `python3 scripts/gov03b-disposable-qa.py --connection /privado/conexion.json --evidence /privado/qa --suite contracts`
- Mismo comando con otro directorio y `--suite ceremony`: composición Identity real, Valkey efímero propio, CLI real, credenciales/llaves sólo sintéticas.
- `python3 scripts/gov03b-transport-qa.py`: proxy real con certificado y listeners efímeros propios, validación TLS y adulteración de petición.
- `php modules/identity/tests/GovernanceGov03ATest.php` conserva la entrada histórica y ejecuta la cadena v2 actualizada; puede recibir `GOV03B_QA_ADMIN_CONNECTION`.

Los clones importan **cero filas normales**, reasignan definers a su principal restringido y ejecutan únicamente sus dos rutinas audit R2. Se prueba denegación de lectura/escritura/CALL hacia mxmed, rollback físico de auditoría, dos procesos finalizando y consumo único. Se destruyen base, principal SQL y Valkey propios. Los checksums normales, sesiones/configuración y QA05/OP02/UI03 se verifican aparte, sin logins sobre cuentas reales. La QA positiva de ownership prueba el contrato con evidencia sintética, no disponibilidad de acreditación productiva ni aceptación humana.

La contención clínica y la política de contactos profesionales ordinarios no se modifican: GOV03B no convierte ownership o staff en autoridad médica ni redefine el alcance de `profile/contact-points`.

## Extensión propuesta Master — ASE-INT03B-PREP01

[Contrato dirigido](ASE-INT03B-PREP01-master-privileged-authorization.md): MFA y casos actuales no autorizan nombramientos Master. Propone seis operaciones exactas y contexto staff tipado en casos existentes, conservando intactos ownership/recovery, enrollment y evidencia externa. Registra composición transaccional pendiente, límite SQL/Valkey y CHECKs no acumulativos de paquetes posteriores. No ejecuta migraciones ni amplía autoridad o ceremonias reales; no sucede las autorizaciones operacionales pendientes de este documento.
