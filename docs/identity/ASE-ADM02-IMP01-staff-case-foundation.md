# ASE-ADM02-IMP01 — caso canónico y perfil personal versionado

2026-10-18. Padre exacto `f58e227da39eca2ae6a7d8b6e6a48615737d3ab5`, `feature/ase-adm02-staff-admission-policy`, limpio y coincidente con remoto. Implementación sólo en `feature/ase-adm02-staff-case-foundation`, worktree `mxmed-ase-adm02-staff-case-foundation`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01`, visual `312ee388f93c892e6caf7e845b11e2b42f9c3565` y once worktrees previos protegidos. **UI-0; fundación aislada completa, sin operación real.**

## Alcance autorizado y continuidad

La solicitud ASE-ADM02-IMP01 autoriza implementar y probar sólo caso, datos personales mínimos, procedencia, confirmación exacta, versiones/conflictos y recuperación. [POL01](ASE-ADM02-POL01-staff-admission-policy.md) conserva los siete principios aprobados y detalles pendientes; esta fase no ratifica textos legales, retención, custodia, catálogo extranjero ni delegación Master. [IW01](../product-completion/iw01-internal-governance-foundation.md), [INT01 R2](ASE-INT01-R2-internal-team-readonly.md), [INT02A](ASE-INT02A-governed-advisor-grants.md), [INT02B](ASE-INT02B-team-permission-ui.md), [INT03A](ASE-INT03A-advisor-lifecycle.md), [Master backend](ASE-INT03B-IMP01-master-security-foundation.md), [Master UI](ASE-INT03B-IMP02-master-ui-integration.md), [GOV03A](IDENTITY-GOV03A-governance-runtime.md) y [COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md) mantienen sus fronteras y evidencia.

ASE-ADM01 se recuperó en la fase POL01 desde el [prototipo inmutable aprobado](https://github.com/circulosmx-bot/mxmed-esqueleto-visual/blob/312ee388f93c892e6caf7e845b11e2b42f9c3565/docs/identity/ASE-ADM01-staff-admission-personal-profile.md); sus canales/procedencia se contrastaron con POL01 completo. Sin reconstruir inventarios CTX-MAP02/03 ni alterar D01–D08 comerciales.

| Eje | Estado |
|---|---|
| PRODUCT_POLICY | Director prepara; solicitante confirma/corrige sólo caso vinculado legítimamente. Master no recibe autoridad nueva. Siete principios aprobados, detalles pendientes. |
| SOURCE_IMPLEMENTATION | Service/Input/Audit/Composition/Http propios del dominio staff admission, tres tablas y cuatro triggers nuevos; Identity/sesión/gobierno/grants existentes preservados. |
| CONNECTIVITY | Ocho endpoints acotados con gate default off y autorización privada de despliegue. Ningún frontend nuevo; no hay writer de binding producto. |
| VERIFICATION | 101 comprobaciones en MySQL/Valkey/TLS nuevos propios, tres escenarios con conexiones SQL independientes; dos regresiones de política audit. [Evidencia](../evidence/ase-adm02-imp01/qa.json). |
| OPERATIONAL_STATUS | IMPLEMENTED_BUT_INACTIVE. Sin datos/cuentas/staff/MFA/envíos/archivos personales reales, DDL real, merge, deploy o activación. |

## Fuentes reutilizadas y objetos nuevos mínimos

Inspección: IdentityAccountRepository conserva cuenta/email/status, AccountCredentialRepository credencial/versionado; RegistrationService/EmailVerificationService y consents/tokens no se invocan. CanonicalHttpSessionResolver y SessionService suministran cuenta/sesión/credencial vigentes; IdentityHttpComposition mantiene PDO/CSRF/authentication. OperatorPresentationRepository almacena alias por membresía de operador médico y PrivateProfileRepository datos de perfil médico: ninguno debe poseer un expediente personal de staff. No tablas de pacientes/médicos usadas como maestro staff.

[Migración nueva](../../modules/identity/db/migrations/2026_10_18_04_staff_admission_foundation.sql), dependencias: auth_accounts existente, motor InnoDB con FK/CHECK activos; audit canónico instalado antes de usar el servicio. No se agrega a DeploymentPackage ni se altera el orden COMPAT01. Sin IF NOT EXISTS que oculte instalaciones parciales, backfill o aplicación real. DDL MySQL no es rollback transaccional: una instalación parcial exige preflight/restauración autorizados; el ensayo usa instancia nueva y elimina exclusivamente su propio datadir.

| Objeto físico | Justificación y restricciones |
|---|---|
| auth_staff_admission_cases | Un caso para ASSISTED/REMOTE_PREAUTHORIZED, ID opaco servidor, creador, recipient_reference UUID opaco (no email ni prueba de cuenta), categoría propuesta ADVISOR/MASTER_ADMIN, versión/puntero actual, estado, timestamps y binding nullable. Cuenta ligada por FK. Unique active_recipient impide dos intenciones activas para la misma referencia; no dedupe por nombre ni búsqueda pública. |
| auth_staff_admission_versions | Snapshot completo mínimo por caso/versión: nombres, primer/segundo apellido, estado/ciudad, procedencia/actor/credencial/fecha. PK compuesta, FK anterior del mismo caso y FK puntero actual; CHECK de dominio/versiones/binding en el modelo correspondiente. Triggers bloquean UPDATE/DELETE de versiones. No se sobrescribe historia. |
| auth_staff_admission_operations | Resultado por actor+operation_id, digest de intención, caso/versión, command, audit_event_id y tiempo. UNIQUE caso/versión y FK a versión; triggers inmutables. Es recibo de idempotencia, no segunda auditoría canónica. El audit_event_id enlaza lógicamente al evento generado dentro de la misma transacción; no impone FK al almacén audit con lifecycle propio. |

No tabla adicional de perfil global, journal o procedencia: cada snapshot contiene actor, acción fuente y enlace anterior; auditoría canónica conserva evento/correlación. Historia de snapshots y recibos no autoriza retención perpetua: antes de live se requiere política y mecanismo de eliminación gobernada que trate también triggers, backups y metadata mínima; no se inventó TTL.

## Datos, procedencia y estados exactos

Campos admitidos: `given_names`, `first_surname`, `second_surname`, `operation_state`, `operation_city`. Cadenas UTF-8 sin controles/markup, máximo técnico 120 bytes por campo; valores vacíos permitidos en preparación, nombres y primer apellido necesarios para confirmar. Segundo apellido y región opcionales. Límite técnico de payload no es política de retención. Móvil no implementado: exigibilidad/propósito aún pendientes; email sólo se lee desde Identity tras binding. No campos de contraseña, token, documento, ID gubernamental, biometría ni retrato; fotografía queda para referencia privada futura.

Provenance de cada snapshot completo: `ADMIN_PREPARED`, `APPLICANT_CORRECTED`, `APPLICANT_CONFIRMED`, `INTENTION_REVOKED`. Update/correct presentan los cinco valores completos de forma explícita; cada valor de esa versión queda atribuido al actor/acción/tiempo. Confirm conserva esos valores y registra aceptación personal de la versión anterior, **no aceptación legal ni revisión de identidad**. Revoke conserva valores mediante vínculo anterior y marca revocación de intención; no atribuye su autoría original al revocador.

Estados físicos acotados: `PREPARING`, `PERSON_CONFIRMED`, `REVOKED`. Case y profile comparten secuencia monotónica: cada comando exitoso produce nueva versión, incluida confirmación/revocación. Una edición vuelve a PREPARING e invalida confirmación corriente sin alterar la previa. Revocado no se edita/reabre; sus recibos siguen consultables por autoridad vigente. Nada genera APPROVED, reviewVersion, identity_verified ni readiness Master.

## Autoridad, locks y readers

[Service](../../modules/identity/StaffAdmission/Service.php) no depende de InternalGovernanceService, grants, notificaciones, evidencia ni MFA. Cada llamada resuelve sesión canónica, abre transacción propia y bloquea cuenta → staff → credencial del actor; valida cuenta activa/email verificado/credencial exacta. Luego bloquea caso y relee autoridad. Revalida sesión viva antes del commit; transacción exterior denegada.

- Director ACTIVE con cuenta/sesión vigentes prepara, actualiza administrativamente, revoca e inspecciona casos. Categoría en prepare es intención explícita del Director, nunca una asignación; update no puede modificarla, igual que creador, destinatario y binding.
- Master sin autoridad de admisión específica no prepara ni administra casos; `internal_advisors_manage` no sirve. Advisor tampoco administra casos ajenos. Un miembro puede actuar únicamente como persona en su propio caso exactamente vinculado, sin facultad de revisión/aprobación.
- Confirm/correct requieren bound_account_id igual al actor, binding_credential_version igual a credencial actual y binding_reference no vacía. Director no sustituye esa comprobación para confirmar por otra persona.
- Producto no expone método o endpoint para establecer binding. Prepare siempre lo deja NULL; fixture positivo sólo CLI restringido a instancia nueva/DB/datadir propios. No selector HTTP/env de autoridad sintética. Principal SQL del ensayo sólo puede INSERT columnas de preparación y UPDATE versión/estado/puntero/fecha, nunca campos binding.
- Read sólo caso exacto autorizado: snapshot actual, hasta 20 referencias históricas y `history_truncated`, fechas y email Identity cuando existe binding. No búsqueda/listado general. Solicitante no recibe recipient_reference ni configuración de credencial; no acceso al directorio por tener caso.
- Result exige actor original de la operación y autoridad actual del caso; devuelve caso/command/versión/UUID audit/committed. No contiene valores personales ni digest privado. No reautoriza una mutación antigua.

Un UUID de destinatario representa una intención administrativa, no matching de cuenta. Hasta ADM-IMP02 no puede probar destinatario humano ni crear binding desde normal HTTP; todos los casos nuevos quedan sin confirmar. Rotación de credencial invalida binding actual hasta futura reconciliación legítima. Sin creación/reset/merge implícitos de Identity.

## HTTP, gates y transporte

[Http](../../modules/identity/StaffAdmission/Http.php), [Composition](../../modules/identity/StaffAdmission/Composition.php), [Input](../../modules/identity/StaffAdmission/Input.php). Prefijo `/api/internal/staff-admission/`:

| Endpoint | Método / entrada exacta |
|---|---|
| csrf.php | GET sin query/body; bootstrap Director, sin datos personales |
| read.php | GET `case_id` único; autoridad exacta y CSRF ligado a sesión para esa persona |
| result.php | GET `operation_id` único; resultado ligado al actor |
| prepare.php | POST operation_id, recipient_reference, channel, proposed_category y cinco campos personales |
| update.php / correct.php | POST operation_id, case_id, expected_version entero y cinco campos personales; Director / persona ligada respectivamente |
| confirm.php / revoke.php | POST operation_id, case_id, expected_version; persona ligada / Director respectivamente |

Método exacto, sin PATH_INFO/query extra, JSON objeto plano con claves exactas/no duplicadas, tamaño máximo 2048 bytes, UUIDv4 minúsculo y case ID `adm_` generado servidor. Sin actor/role/account/binding del navegador. Mutaciones requieren CSRF autenticado; GovernanceTransport exige TLS confiable o prueba proxy canónica, Origin exacto y Sec-Fetch-Site same-origin; GET admite Referer del origen exacto cuando falta Origin. Private/no-store, sin CORS, errores minimizados; no cuerpos/cookies/SQL personales en logs de aplicación.

Gate nuevo `MXMED_STAFF_ADMISSION_FOUNDATION_ENABLED=1` necesario, default off. No basta: Identity canónica, GovernanceDeploymentAuthorization action `staff_admission_foundation`, pin y archivo privado fuera del repo, expiración, instancia/principal/schema/source/organización/crypto ligados; hash adicional de fuente staff y referencias staff_privacy_reference/staff_retention_reference/staff_custody_reference obligatorias. QA genera sólo autorización sintética privada y temporal; ningún archivo de aprobación real se modifica. Las referencias reales necesitan ratificación legal/custodia/retención; feature flag no la sustituye.

No invitation, binding, upload/download, revisión, aprobación, grants, registro staff ni activación endpoints. Controles/layout/copy/CSS de R2/COM/ADM y 8091 intactos. No nueva readiness operacional por presencia de API.

## Atomicidad, idempotencia, auditoría y recuperación

Expected_version se compara dentro del lock de caso; actualización concurrente termina en conflicto, no overwrite. Actor lock serializa la misma operation_id. Clave primaria actor+UUID y digest de action/payload/versiones: misma intención devuelve recibo existente, otra intención o caso devuelve conflicto. Distinta operation_id con mismo recipient_reference activo también conflicto; cambiar referencia describe otra intención, no permite deduplicar personas por nombre. Revocado deniega replay mutante previo; consultar resultado histórico sigue permitido.

Ante ACK perdido, consultar result con el UUID original y refrescar case. Se informa committed sólo desde el recibo persistido; no retry/compensación automático. Caso/snapshot/recibo y auditoría se confirman en **un PDO/transacción**, sin promesa distribuida. Falla audit revierte también cambios de versión y stream heads; respuesta de error no contiene éxito parcial supuesto.

[Audit](../../modules/identity/StaffAdmission/Audit.php) usa CanonicalAuditWriter y JoinedPdoCanonicalAuditTransactionAdapter. Dos eventos específicos, append-only al catálogo canónico: `STAFF_ADMISSION_CASE_CHANGED` (ADMIN_DECISION o USER_REQUEST) y `STAFF_ADMISSION_CASE_READ` (USER_REQUEST). Metadata cerrada: operation/case_id/version/provenance; sin nombres/contactos/documentos/digests. Actor/sesión, request/correlation, recurso, tiempo y cadena audit canónicos. Lecturas de detalle/result auditadas antes de responder; bootstrap CSRF no entrega caso ni genera cambio ficticio. No reutilización de evento clínico/ownership ni tienda de auditoría paralela.

Clase de retención audit `ROLE_ADMIN` existente, sin fijar intervalo nuevo. Los 57 eventos/políticas previos permanecen idénticos; catálogo total59, source module ADMIN y correlación SENSITIVE_ADMIN_OPERATION existentes. [Test específico](../../modules/platform/tests/StaffAdmissionAuditPolicyTest.php) fija digest de las57 y rechaza metadata personal/secretos. [Regresión credenciales](../../modules/platform/tests/PhysicianCredentialAuditPolicyTest.php) actualiza sólo expectativa de append y conserva pins históricos.

## QA ejecutada y límites

[Runner reproducible](../../scripts/ase-adm02-foundation-qa.py), [checks](../../scripts/ase-adm02-foundation-checks.py), [fixture CLI](../../modules/identity/tests/StaffAdmissionFixture.php), [router allowlist](../../scripts/ase-adm02-router.php). Desde esta feature: `python3 scripts/ase-adm02-foundation-qa.py`. Inicializa MySQL propio vacío, Valkey privado, TLS firmado loopback con puertos dinámicos distintos de originales, principal HTTP restringido y auditoría/rutinas canónicas. Verifica datadir antes de DDL; no clona cuentas ni consulta mxmed/QA05/OP02/UI03. Datos inventados y dominios example.test, ninguna entrega externa.

**101 checks PASS**, incluidos A–Z aplicables. Preparación Director; cero nuevos accounts/staff/grants/delegaciones; procedencia; confirmación exacta ligada vs unbound/ajena; Master/Advisor/anon/sesión revocada/expirada/credencial obsoleta/cuenta disabled denegados; CSRF/origen/transporte/JSON/método/payload; campos de actor/binding/role/documento no admitidos; versiones/historia/invalidación; SQL FK/CHECK/triggers; duplicado/conflicto/ACK; falla audit y no acceso real. Retrato/documents no tienen endpoint.

Tres escenarios independientes: (1) admin y applicant disputan la misma versión: dos waits SQL observados en performance_schema, sólo uno hace commit; (2) dos creates de igual actor/operation-id producen un solo recibo/caso; (3) suspensión de Director mientras espera lock deniega su mutación. No son una prueba de carga ni cobertura distribuida.

Master negativo ejecuta `Cases::open(INTERNAL_MASTER_APPOINT)` con sesión Director sintética, target Advisor ACTIVE y **UnavailableStaffIdentityReview real**, obteniendo `staff_identity_review_source_unavailable` antes de insertar caso. Composición HTTP/port/snapshot/writer/MFA Master permanecen byte-idénticos al padre. No se repite suite Master ni se acredita nombramiento positivo.

Dos fallos de harness preservados en [evidencia](../evidence/ase-adm02-imp01/): parser intentaba interpretar HTML del proxy413 como JSON; restauración del fixture de suspensión omitía updated_at. Se corrigió el manejo del transporte en QA y la restitución exacta del fixture; no se relajaron guardas producto ni assertions. Reportes fallidos conservados; sus procesos detenidos y directorios exclusivamente propios retirados después de verificar propiedad/ausencia de procesos. Resultado final y hashes adicionales en [verificación de cierre](../evidence/ase-adm02-imp01/final-verification.json).

Limitaciones: sin UI/browser nueva ni Safari físico (UI-0), entrega/handshake de invitación, binding producto, textos legales, móvil/foto/evidencia, revisión/aprobación, MFA/setup ni activación. Sin ensayo de upgrade sobre base persistente; sólo instalación fresca. Los triggers de historia requieren futuro contrato de eliminación antes de live. No afirma que confirmación personal acredite identidad. Once worktrees, listeners originales y pointer ASE-INT01 preservados; recursos propios del ensayo dispuestos.

## CTX-SYNC01 y siguiente autorización

| Campo | Valor |
|---|---|
| CONTEXT_BASELINE | f58e227da39eca2ae6a7d8b6e6a48615737d3ab5; oficial f92f37f1; visual312ee388; POL01 completo, contratos ASE/IW01/GOV/COMPAT y mapas pertinentes. |
| AFFECTED_CAPABILITY_IDS | ID-07 / COM-10 contexto; reuso ID-01/02/03, sin capability/grant nuevo. |
| AFFECTED_JOURNEY_IDS | J09 fundación parcial; J08 comercial preservado. |
| AFFECTED_DATA_OBJECTS | auth_staff_admission_cases/versions/operations nuevos; eventos audit nuevos. Identity/staff/grants/delegaciones existentes sólo referencia/autoridad, sin cambio de writers. |
| AFFECTED_CONTEXT_FILES | Este contrato, POL01 sucesor, estado/router, mapas datos/interacciones/inventario, IW01 y registro visual. |
| CONTEXT_CHANGES_IMPLEMENTED | Fuente/API/schema, autoridad/binding pendiente, procedencia/versiones, auditoría/recuperación/QA y limitaciones sincronizados. |
| CONTEXT_VERIFICATION | 101 checks aislados, tres carreras, dos tests audit, lint, hashes de fuente/preservación, enlaces/anchors/diff y gate default-off; sin DB real. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED, limitado a fundación sintética UI-0. |
| DOCUMENTATION_COMMIT | Commit de implementación/documentación informado al cierre; no insertar hash futuro en el mismo documento. |
| REMAINING_CONTEXT_GAPS | ADM-IMP02 binding/invitación; políticas legales/retención/custodia/delegación; IMP03 evidencia, IMP04 revisión/port, IMP05 grants/MFA/handoff, D01–D08. |

Siguiente autorización: ADM-IMP02 acotado para invitación preautorizada y handoff Identity, con autoridad/expiración/entrega/privacidad definidas. UI, documentos, revisión, admisión aprobada y operación real requieren fases independientes. Esta entrega no modifica register histórico ni habilita appointment Master.
