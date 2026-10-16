# GOV03B-DEC01 — elegibilidad de owner y recuperación extraordinaria de la raíz

```text
STATUS=PROPOSED_NOT_RATIFIED
DATE=2026-10-16
SOURCE_HEAD=10a73b893e9eac10a2ea08df2b7b51e56c5586d5
BRANCH=ux/consultation-step2-vitals-r1
SCOPE=DOCUMENTATION_ONLY
IMPLEMENTATION_AUTHORIZED=false
MIGRATION_AUTHORIZED=false
GOVERNANCE_ACTIVATION_AUTHORIZED=false
```

Esta propuesta cierra la reconciliación documental de los dos bloqueos de GOV03B-FIX01. **No ratifica ninguna política nueva ni constituye una autorización nominativa.** Las recomendaciones, plazos y estructuras futuras de este documento tienen `STATUS=PROPOSED_NOT_RATIFIED`. La aprobación de la política, la autorización de implementar, la migración y cada operación real son decisiones distintas.

Los dos registros breves para el Director son [DECISION_1: elegibilidad](GOV03B-DEC01-decision-1-owner-eligibility.md) y [DECISION_2: recuperación extraordinaria](GOV03B-DEC01-decision-2-root-recovery.md). No se eligen beneficiarios, revisores, custodios, cuentas, llaves ni credenciales en esta entrega.

## 1. Fuentes y precedencia comprobadas

| Fuente existente | Regla o límite que se reutiliza |
|---|---|
| [Guía de producto, §§5–8, §13 y C4](../product-completion/MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md) | Alta médica, correo y teléfono, datos profesionales, identidad oficial + cédula validada, acreditación humana: un revisor normal y segundo en conflicto/transferencia/excepción. Claim conserva perfil origen; una coincidencia de cédula no concede autoridad. C4 está documentado como no iniciado/no autorizado. |
| [ADM01/ADM02, DG05C3](../product-completion/dg05c3-designation-governance-readiness.md) | ADM01 aceptado: no hay admisión global vigente y consultable. ADM02 diferido por el Director; clasificación de perfiles heredados pendiente. `admission_approved` es procedencia, no admisión efectiva. |
| [VID01](../../modules/profiles/services/VerifiedPhysicianIdentityService.php) y [esquema VID01](../../modules/profiles/db/2026_09_11_create_profiles_verified_identities.sql) | Identidad oficial del perfil y procedencia, `verified_identity_id` único por perfil. `verified_by_account_id` identifica al revisor, nunca al titular de la cuenta beneficiaria. No hay ciclo vigente de vinculación cuenta/persona. |
| [CRD02](../../modules/profiles/db/CRD02.md), [servicio](../../modules/profiles/services/VerifiedDoctorCredentialService.php) | Cédula textual `PROFESSIONAL`, `VERIFIED/ACTIVE`, procedencia y revisión; especialidades separadas. Auditoría unida a la mutación. No prueba quién controla la cuenta ni implementa una consulta automática a una autoridad profesional externa. |
| [IW01](../product-completion/iw01-internal-governance-foundation.md), [InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php), [catálogo](../../modules/identity/services/InternalCapabilityCatalog.php) | Director/Master/Advisor son clases de gobierno, no permisos de producto. Grants operativos y delegaciones son distintos. Masters no modifican al Director. El catálogo actual carece de capacidad de admisión/vinculación profesional. `provider_claim_review` es para organizaciones proveedoras, no médicos. |
| [QA02E](IDENTITY-QA02E-internal-governance-bootstrap-preparation.md), [FIX01](IDENTITY-GOV03A-governance-runtime.md) | R3, solicitante independiente, dos aprobadores, aceptación exacta del beneficiario, MFA/reauth, auditoría atómica. La preparación técnica no designa personas ni activa servicios. |
| [CLIN-AUTH02A](../clinical/CLIN-AUTH02A_URGENT_CONTAINMENT.md) | Contención vigente: ownership, plan, imagen de firma y membresía no acreditan al profesional; firmas/emisiones profesionales permanecen denegadas. |
| [CLIN-AUTH02B1, §§3–5 y D01–D03/D07/D10](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md) | Diseño todavía pendiente: vinculación independiente, versión/vigencia/revocación, fuentes VID01/CRD02, sin rol médico nuevo. Owner administrativo no médico no está aprobado. No se ratifican aquí firma, cobertura, QR ni delegación clínica. |
| [DEC-012E/F](../MXMED_DECISIONES_V2_APIS_DATOS_PERMISOS_PRIVACIDAD_RETENCION.md) | Auditoría transversal; soporte y break-glass separados, mínimos, temporales, con caso/MFA y deshabilitados hasta aprobación productiva. |

Se revisaron además **los originales históricos mediante Git**, sin recuperarlos al árbol de trabajo: commit `f295a98e00fd6d90c7ce03449ff24e259caee24b`, `docs/MXMED_APROBACION_DECISIONES_PLANES_CAPACIDADES_OWNERSHIP_LIFECYCLE.md` (§§23–26; catálogo DEC-011A–E) y `docs/MXMED_REQUISITOS_PLANO_CONTROL_OPERADORES_ROLES_GOBIERNO.md` (§§9–10, 15–17 y enmienda §31). DEC-011A separa planos; B exige controles R3; C prohíbe suplantación; D protege al último Director y exige revocación; E exige casos. Son requisitos aprobados, no evidencia de una recuperación raíz ya implementada. El diseño histórico de `initiator + approver` no se usa para reducir los dos aprobadores más solicitante que FIX01 exige para ownership.

## 2. Lo que existe y lo que falta

La cadena requerida es **cuenta Identity exacta → titular humano comprobado → acreditación humana vigente → perfil exacto y claim resuelto**. Ninguna entidad existente completa esa cadena. VID01 y CRD02 aportan hechos del perfil; `auth_accounts` y sesión prueban control de cuenta; `auth_account_memberships` describe relación administrativa. **Faltan la admisión efectiva C4/ADM02 y la vinculación explícita del titular**, no otra copia de cédulas o un nuevo maestro de personas.

El onboarding ejecutable revisado es el alta/contacto de Identity: [RegistrationService](../../modules/identity/services/RegistrationService.php), [API Identity](../../api/identity/index.php) y páginas de [acceso](../../public/identity/acceso.php), [verificación de correo](../../public/identity/verificar-correo.php) y recuperación. No se encontró allí una revisión profesional/claim médico. [PrivateApplicationAccessResolver](../../modules/identity/http/PrivateApplicationAccessResolver.php) valida sesión, membresía/perfil y capabilities; no demuestra el router completo de acreditación de la guía. Los controladores públicos declaran `claim_source_ready=false`. La secuencia histórica correo + teléfono antes de datos/acreditación sigue siendo requisito: no se declara verificación telefónica completada ni se autoriza omitirla. Reanudar el mínimo C4/ADM02 deberá resolver ese prerrequisito y la admisión/claim necesarios, sin activar aquí el panel médico ni extender el alcance a un proveedor SMS o al onboarding completo.

[CanonicalInitialOwnerEligibilityAdapter](../../modules/identity/adapters/CanonicalInitialOwnerEligibilityAdapter.php) deniega siempre. [GovernanceCaseSecurity](../../modules/identity/services/GovernanceCaseSecurity.php) llama al puerto de elegibilidad al capturar/revalidar el caso, y [ProfileMembershipRoleChangeService](../../modules/identity/services/ProfileMembershipRoleChangeService.php) lo llama dentro de la transacción final. [R3ProfileGovernanceService](../../modules/identity/services/R3ProfileGovernanceService.php) ya conserva solicitante, membresía/perfil/versiones y aprobaciones independientes. Esos puntos de extensión se conservan; no se sustituye la denegación por una comparación de correo, nombre, cédula, rol o `assignment_source`.

[FirstDirectorBootstrapService](../../modules/identity/services/FirstDirectorBootstrapService.php) sólo crea el **primer** Director: cuenta activa/verificada, credencial actual, sesión y reauth, ninguna membresía profesional de ningún estado, ausencia de cualquier fila DIRECTOR, unicidad y auditoría atómica. Incluso un Director suspendido ocupa `director_slot`. No existe un servicio para reemplazar esa raíz; `InternalGovernanceService` protege al Director y no proporciona esa transición.

El [adaptador externo actual](../../modules/identity/adapters/Ed25519ExternalTrustAnchorAdapter.php) verifica **una sola pública Ed25519 configurada**. Su envelope v2 atestigua dos ratificadores, MFA y reauth, pero no verifica dos firmas independientes ni conoce un padrón de custodios/llaves revocables. La v2 dura como máximo quince minutos y liga caso/operación/target/organización/ambiente y, cuando corresponde, perfil/membresía/snapshot. [La autorización de despliegue](../../modules/identity/http/GovernanceDeploymentAuthorization.php) pinnea fuente, esquema y fingerprints: controla despliegue, **no crea confianza institucional**. Un archivo y su hash cambiables por el mismo administrador no sustituyen una aprobación externa.

## 3. Propuesta de elegibilidad, pendiente de DECISION_1

### Emisión y prueba

Reanudar **un único flujo C4/ADM02**, con su cola/revisión humana y claim. Su autoridad de admisión es una decisión institucional por perfil; su autoridad de vinculación es una relación de Identity con ese mismo expediente de revisión. No se crea una segunda acreditación en GOV03B ni se toma un flujo de claims de proveedores como equivalente.

Reutilizar la identidad VID01 y las credenciales CRD02 existentes por ID, sin volver a copiar nombres/cédulas. El expediente debe comprobar documentos oficiales y validar la cédula por la fuente documentada que corresponda; además, una comprobación atendida de la persona y de su control de la cuenta, ligada a desafío de servidor y a su aceptación/reautenticación canónica reciente. Esta comprobación atendida es una **propuesta de método**, no un proveedor contratado ni una declaración de validación legal. Un enlace recibido, una sesión o conocer una cédula no bastan. Las referencias a documentos se guardan en evidencia privada con acceso/retención aprobados, no en logs ni en Git.

El emisor debe tener cuenta y staff activos, sesión vigente, MFA/reauth recientes y una **capacidad específica de revisión de admisión/vinculación**, por ratificar y registrar; el nombre de clase no la concede. Revisor distinto del beneficiario; un revisor en caso normal y dos en conflicto/transferencia/excepción conforme a la guía. Una revisión de acreditación no cuenta automáticamente como endorsement R3: cada aprobación de ownership debe cumplir separadamente todos sus controles de independencia/capacidad/MFA. La segunda revisión excepcional tampoco sustituye el consentimiento del owner actual cuando el contrato de transferencia lo exige.

### Evidencia mínima propuesta

| Dimensión | Referencias/estado exigidos |
|---|---|
| Sujeto y perfil | `account_id`, `verified_identity_id`, `profile_doctor_id`, `professional_credential_id`; VID01/CRD02 del mismo perfil. Un ID enviado por cliente nunca es prueba. |
| Acreditación C4/ADM02 | `admission_id`, `decision_id`, `case_id`, versión de política y revisión, estado `APPROVED`, evidencia de identidad/cédula aceptada, revisor(es), capacidad/rol efectivos al decidir, fecha UTC, motivo, correlation/audit event IDs. |
| Titularidad de cuenta | `binding_id`, versión, cuenta/perfil inmutables, comprobación atendida referenciada, aceptación y sesión/versión de credencial del titular; prueba fresca consumida, sin contraseña/cookie/código almacenados. |
| Claim/solicitud | ID, origen inmutable, perfil exacto, resolución aprobada y versión. Una solicitud nueva debe resolver el anti-duplicado antes de fijar perfil. `claim_source_ready=false` actual no se vuelve verdadero por documentar esta propuesta. |
| Vigencia | `valid_from`, `reverify_at`, vencimiento cuando aplique, estado y revisión de las fuentes aceptadas. Rige el vencimiento más temprano. |
| Incidentes/cambios | Actor, caso, motivo y fecha de suspensión/revocación; `authority_version` monotónica y auditoría canónica. No borrar decisiones previas. |

Propuesta concreta de vigencia para ratificación: **revisión máxima a doce meses**, o antes si vence una fuente o hay cambio material; sin gracia después de `reverify_at`. No es un plazo histórico ya aprobado. Estados de admisión propuestos: `PENDING/APPROVED/REJECTED/SUSPENDED/REVOKED/EXPIRED`; vinculación: `PENDING/ACTIVE/SUSPENDED/REVOKED/REJECTED/EXPIRED`, coherentes con 02B1. Sólo admisión `APPROVED` y binding `ACTIVE` vigentes satisfacen elegibilidad. Revocación terminal; nueva decisión referenciada para reincorporación. No fabricar fechas/revisores para perfiles heredados: permanecen no elegibles hasta revisión real.

### Proyección y revalidación final

Proyección propuesta **sólo de lectura**, request-scoped, por `(account_id, doctor_id)`: permitido/denegado, reason code estable, IDs/versiones de admisión/binding/claim/VID01/CRD02, `valid_until` y versión de política. No incluye documentos o cédulas completas ni se guarda como autoridad en PHP, cookie o navegador. Cuenta, admisión, binding o fuente ausentes, ambiguos, suspendidos, revocados, vencidos o inaccesibles deniegan.

Un permiso de lectura previo no autoriza el commit. Al abrir el caso y al asignar owner, el puerto debe releer las mismas fuentes **en el PDO/transacción canónicos**, tomar locks y comparar las versiones aprobadas; fijar el orden global de locks con los escritores de revocación durante el diseño físico. Cambiar fuente/decisión/perfil/claim entre aprobación y ejecución exige nuevo caso, no adoptar silenciosamente otra versión. Validar cuenta/credencial/sesión y aceptación fresca, membresía activa exacta, perfil sin owner y cuotas; conservar solicitante + dos aprobadores R3, prueba del beneficiario, consumo y auditoría en esa transacción. Revocación concurrente debe tener un punto de orden comprobado; SQL y Valkey **no** constituyen una transacción distribuida. Si no se demuestra la garantía de revocación, no liberar la implementación.

Suspender/revocar admisión o binding deniega inmediatamente nuevas decisiones y consumos, invalida los casos/evidencias dependientes y obliga reverificación; conservar historia. Una suspensión profesional **no transfiere ownership** ni borra membresías: la respuesta administrativa se gobierna por su propio caso. Owner elegible no se vuelve firmante: CLIN-AUTH02A queda intacto; integrar autoridad profesional para actos clínicos requiere el contrato/implementación separados de 02B1.

## 4. Propuesta de recuperación raíz, pendiente de DECISION_2

### Recuperación normal y excepcional

Normal: hay autoridades institucionales suficientes y vigentes para ejecutar el procedimiento existente de cuenta/MFA/R3. Primero evaluar esa vía; una persona disponible sin permisos, independencia o MFA no constituye quorum. La recuperación de contraseña no recupera por sí sola rol, factor, trust anchor ni ownership. El R3 de MFA actual requiere solicitante y dos aprobadores internos elegibles; no afirmar que cualquier Master puede modificar al Director.

Extraordinaria: no se puede reunir autoridad interna suficiente; incidente justificado y prueba de esa ausencia, verificados por custodios externos. **La ausencia no es una condición de autoactivación.** Recuperar el mismo titular tras identidad fuerte y recovery canónico es preferible; si el titular no puede continuar, un sucesor exacto requiere autorización nominativa explícita adicional dentro del incidente, sin promoción automática. Ninguna persona se selecciona en esta propuesta.

### Custodia y quorum recomendados

**Tres custodios institucionales independientes preautorizados; dos firmas Ed25519 distintas de dos custodios vigentes sobre el mismo payload.** Cada custodio tiene llave propia, custodia separada, respaldo cifrado controlado y factor independiente; preferir soporte que impida exportación de la privada cuando esté disponible. Sin privada común en el Mac, repositorio, logs o backups de la aplicación. Registro previo aprobado por la organización: identidad institucional, nombramiento/alcance, pública/fingerprint, vigencia y revocación, versión del manifiesto/política. No registrar custodios al recibir el envelope que ellos mismos pretenden autorizar.

Los dos ratificadores no son beneficiario, autoridad comprometida ni iniciador/ejecutor de la ceremonia. El ejecutor nominativo independiente no obtiene grants de plataforma por ejecutar el servicio acotado. Cada custodio usa identidad fuerte y reauth+MFA recientes (propuesta: **cinco minutos**) en su sistema institucional independiente; las atestaciones deben estar ligadas al digest exacto, llave, actor y tiempo y venir de una fuente previamente confiada. Booleanos autofirmados sin evidencia de esa fuente no prueban esos hechos. No se depende del MFA o sesión del Director comprometido. El beneficiario acepta con su propia sesión Identity/credencial vigente tras recovery legítimo; no se autentica como él.

Nuevo envelope de recuperación **v3, aún inexistente**: operación `ROOT_AUTHORITY_RECOVERY`, UUID/incidente/motivo, organización/ambiente, identidad DB/instalación, política y manifiesto de confianza/versiones/epoch, autoridad anterior + versión esperada, cuenta objetivo + credencial/aceptación, acciones exactas y digest de efectos, nonce, emisión/expiración y dos firmas independientes con IDs de llave y referencias de aprobación. Sin scope clínico o de perfil. Máximo propuesto quince minutos, una sola ejecución; revalidar custodia/estado/revocaciones al consumo. No aceptar v2 como recuperación ni reducir quorum al perder una llave. La política exacta de autorización institucional previa, nominaciones, métodos de prueba y custodias debe ratificarse antes de implementar.

| Opción propuesta | Seguridad y continuidad |
|---|---|
| **2 de 3, recomendada** | Un custodio/llave perdido permite usar los dos sanos; una llave comprometida no puede producir la segunda firma. Dos coludidos sí comprometen la raíz. Separar dominios de custodia y revisar periódicamente. |
| 3 de 5 | Tolera dos indisponibles, exige tres coludidos; mayor coste operativo y de revisión. Requiere ratificar otro padrón/quorum. |
| 2 de 2 | Evita acción individual, pero perder un custodio detiene toda recuperación. Alternativa sólo si se acepta esa indisponibilidad. |
| Una llave con dos nombres atestados | Modelo de emisor v2 actual; no resiste compromiso del único firmante. No recomendado para recuperación extraordinaria. |

### Pérdida, compromiso y revocación

Con un custodio/llave perdido o comprometido, los **dos custodios sanos restantes** autorizan suspensión/revocación, aumento de epoch y reemplazo/rotación del padrón con evidencia y auditoría. La llave afectada no cuenta para quorum y el custodio sustituto no vota su propio nombramiento. Revocar invalida autorizaciones pendientes de ese keyset/epoch, sin reescribir auditoría ni firmas históricas. Revalidar epoch al commit para impedir carrera entre revocación y consumo. No restaurar manifiestos/epochs anteriores desde backup para recuperar una firma.

Si se pierde quorum (por ejemplo, un custodio comprometido y otro inaccesible), **detener recuperación**: no degradar a 1 de 1, no usar llave maestra ni SQL. La reconstitución de confianza requiere un procedimiento organizacional extraordinario separado, con autoridad legal/institucional previamente establecida y nueva autorización humana; no se elige esa autoridad aquí ni se afirma que exista un mecanismo automático. Se puede mantener indisponibilidad segura. Una identidad institucional que no se puede probar tampoco se sustituye por correo o acceso al servidor.

Una vez establecido legítimamente el compromiso, ejecutar **contención gobernada inmediata**: bloquear autoridad afectada, revocar grants/delegaciones/factores/pruebas comprometidos y sesiones por sus servicios canónicos, con caso y auditoría. Esa contención es una transición auditada propia; no se revierte porque después falle la restauración. No deshabilitar arbitrariamente cuentas médicas ni otros usuarios fuera del incidente. La protección histórica del último Director necesita una **excepción expresa por incidente**, no un DELETE de la única fila ni una modificación SQL manual.

### Ejecución y atomicidad propuestas

Entrada backend de recuperación específica, normalmente deshabilitada, sin endpoint de emergencia público. Autorización de despliegue separada, nominativa y ligada al incidente/fuente/esquema/target; propuesta de ventana operativa máxima **treinta minutos**, siempre subordinada al envelope de quince y reauth de cinco. Conservar el bootstrap inicial cerrado si alguna vez existe Director; no reutilizarlo como recovery ni cambiar su guard.

El servicio futuro debe comprobar el incidente y la falta de quorum normal, las firmas/roster/epochs, target e identidad/aceptación, sesiones/credenciales y ausencia de membresías profesionales en un candidato interno nuevo. Sin reset de password directo: si procede, usar primero recovery canónico. Recuperar MFA exige ceremonia acotada al caso con prueba de posesión de **factor nuevo**, sin revelar/restaurar el secreto comprometido. El enrollment normal requiere staff/registrador activos y no cubre ausencia total de autoridad: se necesita una extensión específica gobernada por el quorum externo, que sólo prepare factor `PENDING` sin conceder acceso. No relajar la ceremonia normal con un flag.

En la transacción final: locks de incidente/trust epoch/cuentas/staff/factores, estado/versiones exactos, dos ratificaciones vigentes, consumo único, factor probado, actualización raíz y auditoría canónica en el mismo PDO. Para el mismo titular, conservar identidad y revocar los factores/grants afectados, activar sólo el factor nuevo aprobado. Para sucesor distinto, desactivar/demover la autoridad anterior y asignar al target como una sustitución atómica, respetando el slot único; el anterior puede conservar fila `ADVISOR/SUSPENDED` e historia auditada, nunca derechos heredados. Este cambio del último Director **requiere ratificación explícita**, incluida la democión de su clase histórica. El nuevo Director no hereda grants operativos ni permisos médicos; cualquier grant posterior necesita su vía propia.

Auditoría conserva actor institucional real, ejecutor real, ambos ratificadores, target, antes/después, casos, versiones/epochs, payload/signature hashes y referencias de evidencia, resultado y correlación; el esquema de metadatos/eventos debe aprobarse con el catálogo canónico. No registrar tokens, TOTP, privadas o documentos. Si falla auditoría, rollback total de esa restauración y de sus consumos, manteniendo contención previa. No sustituir al actor por un Director ficticio. Revisión independiente propuesta dentro de **24 horas**, cierre con resolución y notificación segura; canal/retención y responsables quedan por aprobar.

La ventana de emergencia expira y se cierra después del uso; la designación institucional restaurada puede persistir conforme a su nombramiento explícito. No se crea un superadmin temporal con acceso general ni una capacidad de clínica. La revocación SQL de autoridad debe denegar nuevas peticiones antes de completar purga de sesiones Valkey; definir un marcador/epoch autoritativo y reintentos auditados. No prometer atomicidad SQL–Valkey: si la denegación efectiva no es demostrable, no activar la restauración.

### Amenazas y límites

Dos firmas protegen ante una llave comprometida; no ante dos custodios coludidos ni ante captura simultánea de sus dominios. MFA/reautenticación limitan robo de sesión, no sustituyen verificación humana de incidente/target. Bindings/nonce/TTL/versiones impiden cambio de beneficiario, ambiente y replay; locks/epoch impiden consumo concurrente con revocación. Auditoría unida impide éxito sin evidencia, pero un administrador con control total del host/DB puede alterar código, pins y estado: las firmas, custodia y copias de evidencia externas independientes son necesarias, y este contrato no afirma resistencia absoluta frente a ese adversario. Caída de evidencia/audit/trust authority deniega; no usar disponibilidad como permiso.

## 5. Diferencias y decisiones nuevas que requieren ratificación

| Contrato previo aprobado | Implementación actual | Enmienda mínima propuesta, aún no aprobada |
|---|---|---|
| C4: acreditación humana; VID01/CRD02 como hechos del perfil; secuencia de contacto/onboarding | Sin admisión efectiva ni titular de cuenta vinculado; adapter rechaza; acceso privado no implementa router C4 completo | Dos proyecciones relacionadas del único expediente C4/ADM02: admisión por perfil y binding exacto; método atendido, capacidad emisora, versiones/revocación y revisión máxima de doce meses. Resolver prerrequisitos históricos, sin dar por cumplidos teléfono/claim ni activar el panel médico. |
| Un revisor normal, segundo excepcional; ownership privilegiado R3 | R3 exige solicitante y dos aprobadores, aceptación exacta | Mantener ambos contratos: acreditar no concede owner ni descuenta endorsements. No ampliar elegibilidad a owner no médico ni conceder firma. |
| Último Director protegido; mecanismo de recuperación gobernado obligatorio | Slot único incluso suspendido; bootstrap rechaza Director existente; MFA recovery depende de autoridad interna | Incidente raíz separado y excepción explícita a protección del último Director sólo para contención/restauración/sucesión aprobadas, sin eliminar historia. |
| Doble control, MFA, reauth, caso y break-glass temporal | Un emisor Ed25519 v2 atestigua dos ratificadores; no padrón revocable ni recovery de raíz | 2 de 3 custodios preautorizados, dos firmas propias v3, manifiesto/epochs/revocación, ceremonia MFA excepcional acotada; 5/15/30 minutos y revisión 24 h propuestos. |
| Staff no implica permisos operativos ni clínicos | Director/Master/Advisor/grants separados | Se conserva; no catálogo de 12 roles implementado por esta decisión, ni nuevo poder de firma ni bypass de operadores. |

Ratificación de DECISION_1 debe resolver fuentes/método de identidad, capacidad y autoridad emisora, estados/revisión de doce meses, clasificación heredada, claim/perfil y autorización de reanudar C4/ADM02. Ratificación de DECISION_2 debe resolver 2/3 frente a alternativas, custodia y autoridad organizacional original, independencia, métodos de MFA/reauth externos, ventanas, contención/último Director, sucesión, continuidad sin quorum y revisión posterior. **Aprobar el documento no nombra personas ni autoriza una migración u operación.**

## 6. Esquema v2 y mínimo adicional propuesto

Se revisó [2026_10_16_01_governance_security_bindings.sql](../../modules/identity/db/migrations/2026_10_16_01_governance_security_bindings.sql): nueve columnas en cuatro tablas, tres FKs y tres CHECKs. Consulta de `information_schema` en normal-local: **0 de las 9 columnas presentes**; `V2_MIGRATION_STATUS=NOT_APPLIED`. Las quince tablas de OP-ACT02A sí existen; no repetirlas. No se creó ni ejecutó SQL en DEC01.

**Sí se necesita esquema adicional si se ratifican las propuestas.** La v2 es binding técnico de seguridad, no acreditación ni registro de custodios. Las siguientes son estructuras lógicas propuestas, no nombres físicos finalizados ni archivos de migración ejecutables:

| Superficie mínima futura | Reutilización y cambio necesario |
|---|---|
| Estado actual de admisión por perfil C4/ADM02 | Una autoridad, con decisión/caso/versiones/vigencia/revisor(es)/referencias; historia en auditoría canónica. No duplicar admisión en Identity. |
| Relación cuenta–profesional | Reutilizar propuesta de `auth_account_professional_bindings` de 02B1; referencias a cuenta/VID01/CRD02/perfil y decisión de admisión, versiones/vigencia/ciclo. Sin copiar datos de identidad. Un binding efectivo por cuenta/perfil y una cuenta efectiva por identidad; prevenir personas incompatibles y altas concurrentes con constraints/locks, no sólo índice por status. |
| Confianza externa | Manifiestos aprobados, claves/custodios con status/vigencia/revocación, policy/keyset epoch monotónico; relación de dos ratificaciones verificables por evidencia y `key_id` distintos. No reutilizar `internal_staff` para custodios externos. |
| Caso raíz y evidencia v3 | Extender casos/evidencia existentes con operación raíz tipada, versión 3 permitida explícitamente, referencia incidente, manifiesto/epoch, autoridad anterior y target/versiones, efectos autorizados y consumos. Usar contexto v2 como contenedor no suple constraints ni semántica. Aprobar checks/campos/índices que faltan; no crear otra copia de las quince tablas. |
| Revocación y sucesión raíz | Versión/epoch de autoridad serializable y guard de revocación consultado por consumidores; historial de cambio en audit. Conservar slot único; writer de sucesión separado y transaccional. Factor pendiente puede reutilizar persistencia MFA, pero no suponer que el servicio actual autoriza enrollment excepcional. |

Preparar el DDL físico **sólo después** de ratificar contratos: ensayo aislado, constraints/carreras/FKs, backup y autorización de target antes de cualquier normal-local. No modificar silenciosamente el CHECK v2 para aceptar recovery: hoy sólo permite contract_version=2 y operaciones existentes. Implementar owner puede separarse de root, pero cada uno necesita su esquema y dependencias aprobados. Ninguno queda habilitado al instalar v2.

## 7. Matriz de aceptación futura

Todos los positivos son **sintéticos en bases desechables**, nunca cuentas o membresías reales. Esta matriz es un contrato por verificar; **no son tests ejecutados en DEC01**. FIX01 ya tiene evidencia separada de sus guardas/atomicidad v2 y una falla estática de audit preexistente (`f07_policy_count`, esperado 44/actual 52), no corregida ni ocultada aquí.

| ID | Escenario / estímulo | Resultado y evidencia exigida en implementación futura |
|---|---|---|
| E01 | Beneficiario sin acreditación, sólo VID01/cédula/owner/plan | Denegado; cero rol/consumos/audit de éxito. |
| E02 | Cuenta, VID01/CRD02, claim o membresía de otro perfil | Denegado; no selección automática ni fuga de otro perfil. |
| E03 | Admisión/binding vencidos o `reverify_at` alcanzado | Denegado al leer y al commit, aunque estado no haya sido materializado como EXPIRED. |
| E04 | Binding suspendido/revocado entre caso y ejecución | Denegado; locks/versiones y carrera de revocación probados, cero owner. |
| E05 | Titular sintético acreditado/vinculado, claim y perfil exactos, R3 completo | Aceptado sólo en disposable; un owner, consumos únicos, audit y versiones exactas; misma membresía, sin cuenta nueva. |
| E06 | Nuevo owner intenta firma clínica usando sólo ownership | Denegado por contención 02A; no imagen/token/acto firmado nuevo. |
| R01 | Operación cotidiana llama recovery / usa bootstrap con Director incluso suspendido | Recovery indisponible sin incidente/aprobación; bootstrap siempre rechaza Director existente. |
| R02 | Una firma, dos firmas de la misma persona/llave, autofirma o target como ratificador | Denegado sin consumo ni cambio; no importar un padrón del propio envelope. |
| R03 | Firma falsificada, payload modificado o v2 usado para recovery | Denegado antes de staging/efectos. |
| R04 | Custodio/llave suspendidos, revocados, vencidos o epoch anterior | Denegado al consumo; probar carrera revocación/commit. |
| R05 | Evidencia expirada, reauth vieja o replay secuencial/concurrente | Denegado; máximo un éxito y una mutación/evidencia consumida. |
| R06 | Incidente inventado, aún existe quorum normal o sustitución de target/ambiente/efectos | Denegado; ausencia de autoridad normal no autoactiva recuperación. |
| R07 | Recuperación/sucesión sintética legítima, dos custodios independientes y MFA nuevo probado | Único Director efectivo; antiguo comprometido denegado; factor/autoridad/consumos/audit atómicos; sin grants heredados. |
| R08 | Fallo inyectado de auditoría o mutación final | Rollback de toda restauración y consumos; ninguna autoridad parcialmente activada. Contención previa auditada sigue vigente. |
| R09 | Un custodio perdido / uno comprometido / quorum perdido | Dos sanos pueden actuar; comprometido no cuenta; sin quorum permanece denegado y no baja umbral. |
| R10 | Fallo de Valkey/purga de sesiones o sesión vieja tras contención | Epoch/estado SQL deniega nuevas acciones; reintentos trazables; sin restauración si no se prueba garantía de revocación. |
| P01 | Solicitudes reales y entorno después de QA | Runtime normal sigue deshabilitado; checksum/DDL/autoridades/QA05/OP02/UI03 iguales; sin efectos clínicos ni invitaciones. |

## 8. Evidencia documental y siguiente implementación mínima

Preflight verificado en el worktree correcto: branch esperada, HEAD y upstream `10a73b893e9eac10a2ea08df2b7b51e56c5586d5`, árbol inicialmente limpio. Inspección de lectura normal-local: 128 tablas, quince tablas de OP-ACT02A vacías, nueve columnas v2 ausentes, cero staff y membresía existente `collaborator/profile/active`. Flags de gobierno/ceremonia/operadores/delivery ausentes o apagados en configuración y procesos normales inspeccionados. No se usó sesión autenticada ni se invocó login, enrollment, bootstrap, claim o invitación.

La evidencia privada de revisión se conserva fuera del repositorio en `~/Library/Application Support/MXMED/gov03b-dec01/20261016-review-5f6b8f47/`: metadatos y snapshots de checksums/DDL, fingerprints de configuración/sesiones y auxiliares; sin volcar cuentas, passwords, documentos o tokens. Comparación final: las 128 tablas conservan conteos/checksums/DDL; esquema, rutinas, triggers, metadata de audit y membresías iguales; configuración y payloads de sesiones iguales; QA05/OP02/UI03 iguales y procesos normales preservados. Verificados 32 enlaces locales a archivos y diff sin errores de whitespace; alcance exacto de cuatro documentos, cero archivos runtime/SQL. Sólo cambia documentación; no se ejecutan regresiones mutantes ni se declara QA positiva nueva.

Secuencia futura, cada etapa con autorización separada:

1. Ratificar las dos decisiones y reanudar el alcance mínimo C4/ADM02; decidir clasificación de legado. Resolver autoridad institucional y capacidad emisora sin otorgarlas aquí.
2. Implementar admisión/vinculación y proyección en sus módulos canónicos; consumir el puerto de elegibilidad en GOV03B y revalidar versiones transaccionalmente. No integrar firma clínica en este alcance.
3. Implementar trust roster/quorum v3, revocación/epochs, incidente, contención, enrollment excepcional y writer de recuperación/sucesión. Conservar bootstrap inicial y recuperación normal como vías distintas.
4. Ensayar migraciones y la matriz anterior en disposable; preservar OP-IMP01/02, cuotas/cinco grants, Agenda y CLIN-AUTH02A. Verificar rollbacks de audit y carreras reales; resolver por separado la pin estática preexistente, sin falsear PASS global.
5. Sólo después: autorizar backup/target y migración v2 + esquema adicional; nominar humanos y fuentes, custodiar llaves y aprobar operaciones exactas. Activación de operadores sigue siendo un gate separado.

```text
RESULT=READY_MXMED_GOV03B_DEC01_PROPOSALS_FOR_RATIFICATION
POLICIES_RATIFIED=false
RUNTIME_SOURCE_FILES_CHANGED=0
SQL_FILES_CHANGED=0
V2_MIGRATION_STATUS=NOT_APPLIED
ADDITIONAL_MIGRATION_REQUIRED=YES_IF_PROPOSALS_RATIFIED
ALL_REAL_AUTHORITIES_UNCHANGED=true
GOVERNANCE_RUNTIME_ACTIVE=false
OPERATOR_RUNTIME_ACTIVE=false
```
