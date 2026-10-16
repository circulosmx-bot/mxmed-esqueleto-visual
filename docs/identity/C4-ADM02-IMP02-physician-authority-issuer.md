# C4-ADM02-IMP02 — emisor de admisión y vinculación profesional

2026-10-16. Worktree `mxmed-step2-vitals-r1`, rama `ux/consultation-step2-vitals-r1`, inicio local/upstream/remoto `ab14912456c257c7612335f5b3ea2dcfb224568f`, limpio. Implementación de fuente y ensayo sintético desechable autorizados; ninguna activación, DDL o mutación normal-local.

```text
RESULT=READY_MXMED_C4_ADM02_IMP02_ISSUER_SOURCE_COMPLETE_PENDING_OPERATIONAL_ACTIVATION
NORMAL_LOCAL_ACTIVATED=false
OWNER_ASSIGNMENT_AUTOMATIC=false
CLINICAL_SIGNING_ACTIVE=false
```

El emisor completa el camino rutinario de **claim del perfil existente exacto** usando la [fundación C4-EV01](C4-EV01-protected-evidence-foundation.md). No sustituye el onboarding C4 completo, los procedimientos de perfil nuevo/conflicto/transferencia, el nombramiento real de owner o la autorización clínica. El estado bloqueado de [IMP01](C4-ADM02-IMP01-issuer-readiness.md) queda como evidencia histórica del contrato que faltaba entonces.

## Una revisión, confirmación independiente y emisión atómica

[PhysicianAdmissionComposition](../../modules/identity/Accreditation/PhysicianAdmissionComposition.php) es una composición privada explícita: mismo PDO Identity, contexto de sesión canónica, servicio MFA existente, almacenamiento privado y cipher inyectados. Construye el servicio C4 original, el único [ProfessionalAuthorityIssuer](../../modules/identity/Accreditation/ProfessionalAuthorityIssuer.php), la proyección y el adaptador existentes. Ningún flag, router, login, página o bootstrap normal-local selecciona esta composición. No incorpora verificadores sintéticos; éstos existen únicamente en el harness CLI desechable.

El servicio de evidencia conserva su intake/revisión/confirmación. Su dependencia opcional [ProfessionalIssuanceWorkflow](../../modules/identity/Accreditation/ProfessionalIssuanceWorkflow.php) registra el handoff **dentro de la confirmación consumida**, usando `ProtectedPhysicianEvidencePort::requireConfirmed` y el mismo PDO/transacción. El default sin esta dependencia conserva C4-EV01 sin emitir autoridad. El handoff congela solicitud, revisión, cuenta y perfil; su clave estable es el UUID de solicitud. No guarda token, contraseña, teléfono, nombres, cédula o documentos.

`issue(actor, commandId)` recibe el actor canónico y la referencia al workflow. Sólo la fila interna inmutable aporta solicitud/revisión/cuenta/perfil al puerto; el cliente no aporta esos hechos. `stage` permite un checkpoint `PENDING`; `issue` también puede crear y activar ambas filas en una sola transacción. Ambas operaciones vuelven a exigir evidencia CONFIRMED, origen exacto, correo/contacto/versión, fuentes VID01/CRD02, revisión humana y grant/MFA/credencial actuales del revisor, documentos privados, confirmación propia consumida, vigencia y ausencia de conflicto. Se reutilizan la revisión y aceptación existentes, sin otro upload, revisor rutinario o aprobación de acreditación.

La admisión conserva IDs de perfil/VID01/CRD02, cuenta, request/revisión, actor de revisión, caso, decisión/auditoría y fechas reales de la fuente. El binding referencia esa admisión/versión, la misma identidad, revisión de fuentes y evidencia/aceptación. No se crea otro maestro profesional. `approved_at` atribuye la decisión humana original; el journal registra por separado el momento/actor efectivo de staging, activación o decisión posterior. No se inventa una renovación anual: `valid_until` se hereda del documento con vencimiento más temprano; puede ser null.

Cada mutación y auditoría comparten PDO/commit. El lock de perfil, locks de fuentes/solicitud/cuentas, unicidad y CAS serializan emisiones. No se adopta ni reemplaza una admisión/binding efectivos de otro comando. Un resultado idéntico devuelve las mismas referencias sin nuevas filas/eventos; un payload alterado queda bloqueado por el handoff inmutable y por el scope de casos/proofs. Una autoridad posteriormente modificada/revocada no se reactiva al repetir `issue`. No hay transacción distribuida SQL–Valkey: se valida la sesión actual al abrir la operación y antes de commit mediante `EvidenceAuthority`.

## Estados y decisiones controladas

| Autoridad | Transición legal |
|---|---|
| Ambas | Ausente → PENDING, sólo desde evidencia confirmada y workflow confiable. |
| Admisión | PENDING → APPROVED, exclusivamente por `issue` y el mismo expediente confirmado. |
| Binding | PENDING → ACTIVE, atómico con la admisión APPROVED exacta. |
| Ambas | PENDING → REJECTED / REVOKED / EXPIRED. |
| Admisión | APPROVED → SUSPENDED / REVOKED / EXPIRED. |
| Binding | ACTIVE → SUSPENDED / REVOKED / EXPIRED. |
| Ambas | SUSPENDED → REVOKED / EXPIRED. |
| Ambas | REJECTED / REVOKED / EXPIRED son terminales. |

EXPIRED exige que la fecha real de fuente haya vencido. REJECTED exige `REVIEW_WITHDRAWN`, SUSPENDED `SECURITY_REVIEW`, REVOKED `SECURITY_REVOCATION` o `BENEFICIARY_REQUEST`, EXPIRED `SOURCE_EXPIRED`. No hay reactivación por login ni cambio directo de SUSPENDED a positivo. Una reevaluación requiere cerrar la autoridad anterior y otro expediente/revisión/aceptación; no permite transferir cuenta o owner. Resolver un perfil ya ocupado o una transferencia sigue su procedimiento específico pendiente.

`beginChange` reutiliza casos y MFA existentes con operación finita `PHYSICIAN_AUTHORITY_CHANGE`: staff independiente, capacidad explícita `physician_accreditation_review`, cuenta/sesión/versión actual y reauth reciente. El caso de cinco minutos congela tipo/ID/versión/estado objetivo/motivo y cuenta/perfil/evidencia exactos. No revisa documentos otra vez ni pide una segunda aprobación para la emisión ya aceptada.

`completeChange` recibe sólo el caso y su proof MFA. Revalida sesión/reauth, staff/grant, scope y versión; consume el proof, escribe CAS/journal/auditoría y consume el caso en una transacción. Un replay exacto devuelve un **recibo histórico**, nunca autoridad restaurada; otro proof/payload es conflicto. Las reducciones de autoridad siguen disponibles aunque la evidencia original ya no pase el gate positivo. No borran membresías, documentos clínicos, historia ni transfieren un owner. Suspender admisión incrementa su versión e invalida cualquier binding que aún cite la anterior, incluso antes de materializar su estado negativo.

## Enmienda preparada 04 e historia

Las migraciones preparadas **01/02/03 permanecen sin cambios**. La [04](../../modules/identity/db/migrations/2026_10_16_04_physician_authority_issuer.sql) añade las referencias exactas C4 a las dos autoridades, dos tablas auxiliares (handoff y journal) y la operación de caso. Se ensaya en orden 01 → 02 → 03 → 04, sólo sobre schema-only clone nuevo y vacío de datos reales.

La antigua unicidad permanente de admisión por `doctor_id` se sustituye por una clave generada efectiva para PENDING/APPROVED/SUSPENDED. Permite **otra fila** tras un estado terminal; conserva la decisión/revisor/referencias originales y no autoriza automáticamente una reevaluación. El binding añade unicidad de perfil efectivo, manteniendo las anteriores de cuenta/perfil e identidad. Triggers impiden reescribir atribución, scope y evidencia; journal es append-only y se impide borrar autoridades/handoffs. La migración exige autoridades 02 vacías mediante un guard anterior a los ALTER; el runner prueba que rechaza datos existentes antes de cambiar su DDL. No hay backfill, adopción ni transformación implícita de filas antiguas.

El journal tiene FK al evento físico canónico: el mapper nativo usa SHA-256 del UUID, por eso `audit_reference` guarda el UUID y la clave física se genera con `SHA2(...,256)`. No se cambia el mapper, las rutinas nativas ni la cadena. La primera iteración del ensayo detectó esta distinción y se corrigió antes de aceptación. Fallos previos y su limpieza permanecen en evidencia privada.

## Ownership y futuro consumidor clínico

[ProfessionalAccountBindingProjection](../../modules/identity/repositories/ProfessionalAccountBindingProjection.php) ahora exige el puerto protegido: sin él deniega. Compara admisión/binding, cuenta/persona/perfil/revisión, referencias de revisión/aceptación, hashes y vigencia; después vuelve a consumir `requireConfirmed` bajo locks en el PDO del caller. El [adaptador de owner](../../modules/identity/adapters/CanonicalInitialOwnerEligibilityAdapter.php) sigue sin proyección por defecto en `GovernanceHttpComposition` normal-local.

La QA inyecta esa misma proyección en `GovernanceCaseSecurity` y **ProfileMembershipRoleChangeService existentes**, sin un nuevo escritor de ownership. El positivo usa el emisor real, un staff con las dos capacidades separadas, MFA/reauth y aceptación independiente del médico. La transacción final deniega si el binding fue revocado después de aprobar el caso. Ni confirmar ni emitir autoridad promueven al colaborador.

`requireCurrent(PDO, account, profile)` prepara hechos mínimos del binding exacto para el consumidor clínico posterior. **No es un permiso de firma**: ese consumidor deberá validar además sesión/credencial/cuenta, owner exacto, operación, entitlement, paciente/encuentro/documento/versión/creator/integridad, intención y auditoría antes de commit. No cachear el resultado como autoridad. CLIN-AUTH02A y `clinical_professional_authority.php` permanecen byte por byte; owner acreditado, owner sin binding y operador siguen sin poder firmar/emitir. No se toca Recetas.

## Auditoría y QA ejecutada

Evento cerrado nuevo `PHYSICIAN_AUTHORITY_CHANGED`, operación `PHYSICIAN_AUTHORITY_LIFECYCLE`, módulo AUTH; metadata limitada a operación/versión/estados/caso/motivo/capacidad. Actor/rol/sesión se derivan en servidor. Las primeras **53** políticas publicadas, incluida C4-EV01, permanecen idénticas: SHA-256 `ad2e8bc54009bebf66b434f3f648b3b1bd0cbca47a16e287608dd729dddd057a`. El pin de deployment incluye 04 y los componentes nuevos; no se emite autorización real.

El [runner](../../scripts/gov03b-disposable-qa.py) añade `--suite issuer` → [PhysicianAuthorityIssuerIntegrationTest](../../modules/identity/tests/PhysicianAuthorityIssuerIntegrationTest.php). Crea `mxmed_gate4d_preview_c4issuer_<10hex>`, usuario restringido, Valkey y objetos/claves/SMS sintéticos propios; importa cero filas reales. El principal no puede SELECT/UPDATE/CALL audit en `mxmed`. `finally` elimina DB/usuario/Valkey/archivos secretos propios. No resetea QA05/OP02/UI03 ni usa sus sesiones.

La matriz solicitada se verifica así:

| Gates | Evidencia |
|---|---|
| 01–13 | Confirmación C4 real del ensayo y emisión exacta; faltante, cuenta/perfil incorrectos, teléfono/versión, vencimiento, staff/grant/MFA/credencial, self-review, CRD02 y revisiones VID01/CRD02 deniegan. |
| 14–19 | Unicidad física, dos procesos PHP/Valkey independientes emitiendo el mismo comando, mismo resultado y una sola pareja; replay sin nuevo audit; payload inmutable; fallo nativo en **BINDING_ACTIVE**, después de staging y aprobación de admisión, revierte filas, journal, receipt y audit. |
| 20–25 | Suspensión/revocación/expiry y todos los bordes legales con MFA real del ensayo; terminales no reactivan; historia/atribución/aceptación inmutables; nueva fila de reevaluación conserva la anterior; ningún legado, conflicto o perfil nuevo se autoacredita. |
| 26–31 | Una revisión de dos documentos, aceptación propia consumida, asignación por escritor owner existente y revalidación final negativa; sin binding deniega; owner y operador no firman/expiden. |
| 32–34 | OP-IMP01 01–38, 200 assertions, cuotas/grants/concurrencia; Agenda, resolver y CLIN-AUTH02A; sesiones 3600/43200; CALL audit cross-DB denegado. |
| 35–36 | Snapshots read-only antes/después de `mxmed`, membresía/autoridades, DDL/rutinas/triggers/audit, configuración y hashes de sesiones; checksums QA05/OP02/UI03 iguales. |

Se extraen exclusivamente los helpers sintéticos de C4-EV01 a [C4EvidenceHarness](../../modules/identity/tests/support/C4EvidenceHarness.php); la suite anterior conserva sus asserts y el default sin handoff. El antiguo helper GOV-SIM02 que insertaba admisión/binding a mano se retira: su positivo preparado se sustituye por los positivos/negativos del emisor real, **no por otro verifier siempre aprobatorio**. El suite de gobierno general conserva sus casos de bootstrap, MFA, recuperación, rollback, scope y concurrencia, y ahora comprueba que una proyección sin puerto no autoriza.

Resultado del ensayo final: 101 assertions del emisor y dos del guard de migración; C4-EV01 86; gobierno nativo 88 (incluye salida esperada de un worker perdedor en concurrencia); OP-IMP01 200. Pasaron además las nueve regresiones dirigidas de Identity/C3/sesión privada/roles/HTTP, Agenda, resolver/CLIN-AUTH02A y almacenamiento privado. Los writers/HTTP/UI de OP02 permanecen sin cambios; no se vuelve a ejecutar el script de HTTP que muta/resetea OP02 persistente. Su entorno se preserva por checksum. No se afirma una nueva QA física Safari del Director ni el replay completo OP02 HTTP.

**Deuda histórica de auditoría visible:** `PhysicianCredentialAuditPolicyTest.php` sigue fallando por su pin de 52 eventos (el baseline ya tenía 53, ahora 54); también se documentó previamente la expectativa de metadata de invitación anterior a OP07 y los doubles PDO antiguos. No se cambia ese test ni se declara una suite global verde. La aceptación nueva comprueba políticas publicadas completas, catálogos finitos, SQL/audit nativos, rollback físico y chain heads.

Evidencia privada: `~/Library/Application Support/MXMED/c4-adm02-imp02/20261016-review/`. Datos/cuentas clínicas reales nunca se usan como fixtures mutantes. Los procesos normales y persistentes se conservan.

## Pendiente antes de activar

1. Aprobar SMS real y entrega verificada; custodia/rotación/recuperación de claves; retención/acceso/legal hold/disposición y reconciliación de objetos cifrados huérfanos.
2. Revisar y autorizar por separado el despliegue de 01/02/03/04, staff/capacidades/personas concretas y la composición protegida en el onboarding **original**. No mostrar botones activos antes de tener backend/schema/autoridades aprobados.
3. Completar resolución canónica de perfil nuevo, conflicto/transferencia y reevaluación de un perfil ya ocupado, sin cambiar origen, adoptar otra cuenta o transferir owner automáticamente.
4. Ownership real, activación de operadores y firma/expedición clínica conservan autorizaciones e integraciones separadas. No hay activación normal-local por publicar esta fuente.

Rollback de fuente por Git bajo el procedimiento ordinario; sin rollback SQL normal porque no hubo DDL. No se habilitan fallbacks legacy. La recuperación del ensayo consiste en eliminar únicamente su base y recursos propios, preservando la evidencia privada de resultados.
