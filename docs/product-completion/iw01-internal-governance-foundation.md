# IW01 internal governance foundation

Director, Master Administrator and Advisor are governance classes, not operational permission bundles. The Director is the root governance authority but must hold each explicit operational capability to use a product module. No global administrator bypass exists.

`InternalGovernanceService` resolves the actor through the canonical HTTP session resolver and references `auth_accounts`. It registers an internal relationship for an existing account; it creates no credentials or session system. This backend service has no HTTP endpoint. A future adapter must enforce method and CSRF validation before calling it.

Only the Director appoints/removes Masters and establishes/revokes per-capability delegations. A Master requires an active internal relationship, `internal_advisors_manage`, and an explicit Director delegation for each operational capability they grant or revoke. Masters can manage Advisors only: no self-management, Master management, Director modification or recursive delegation. Demotion revokes management grants and delegations, retaining operational grants and history.

`internal_operator_grants` remains the sole operational assignment authority. `internal_capability_delegations` records authority to administer a capability, which never implies authority to use it. The original IW01 catalog contained six Media Review capabilities and the advisor-management capability; subsequent GOV/C4/PUB-COR/ACT03/provider contracts extended the system-defined [InternalCapabilityCatalog](../../modules/identity/services/InternalCapabilityCatalog.php). Unknown keys, wildcards and arbitrary database strings cannot create executable authority.

Internal suspension changes only `internal_staff.status`. Both generic capability resolution and governance mutations require ACTIVE internal status. Canonical accounts, credentials, sessions, physician memberships and historical records remain intact. Reactivation respects revoked grants. Media Review remains the first consumer of explicit capabilities; its own authentication, capability names, publication and replacement behavior are unchanged.

## Sucesor acotado ASE-INT01 R2

[Integración aprobada de lectura](../identity/ASE-INT01-R2-internal-team-readonly.md) sucede la postergación total de frontend sólo para la sección Equipo en rama/preview aislados. Reusa Identity y catálogo18/16; Director lee Advisors/Masters, Master ACTIVE+management sólo Advisors con grants dentro de sus delegaciones. UI no llama execute; su adapter de commands sigue sin conectar. Perfil/evidencia staff/comercial no conectados, sin nuevos permisos ni cambios al writer. Una sesión puede tocar su store por lifecycle canónico; no datos de gobierno.

## Migration and deployment prerequisite

Apply `modules/identity/db/migrations/2026_09_10_01_internal_governance.sql` to the canonical identity database after the existing account and operator-grant migrations, before deploying the changed capability resolver. Deployment without this prerequisite fails closed. The migration backfills every existing grant holder, including historical/revoked holders, as an ACTIVE Advisor only when no staff row exists. It does not modify grants, account status, or existing staff state. Inactive accounts and revoked grants remain denied. There is no runtime missing-row fallback.

The migration seeds no Director, Master or delegation. A separately authorized productive Director bootstrap is required before productive governance administration. A unique generated slot permits at most one Director. No productive account was assigned in IW01; all exercised assignments are disposable synthetic fixtures.

Governance mutations lock actor and target account/staff rows in sorted order, recheck authority, mutate and append `INTERNAL_GOVERNANCE_CHANGED` through the existing canonical writer on the same PDO transaction. Audit failure rolls the mutation back. The canonical audit tables/routines and identity tables must be available to that connection with existing canonical audit configuration. Actor, target, operation, capability, staff state, time and correlation are retained. There is no parallel audit store or read-event audit expansion.

## Deferred product authority

Future Call Center authorization is ACTIVE_INTERNAL_USER + CALL_CENTER_OPERATIONAL_CAPABILITY + DOCTOR_HAS_CALL_CENTER_SERVICE_ACTIVE. There is no advisor-to-doctor assignment. Future V1 permits finding enabled doctors, viewing availability, creating appointments and adding patients to the waitlist. Cancellation, rescheduling, schedule/block changes and service activation remain excluded. No Call Center keys, entitlement, endpoint or schema is implemented.

Generic OWN/TEAM/GLOBAL scope persistence is deferred until an implemented module needs it. The existing authorization boundary is the extension point; current grants remain compatible.

IW01 ends at this backend foundation. IW02 and all frontend implementation are deferred. The next phase requires a separate Director-led visual definition and approval.

## ASE-ALIGN01 V2 — política aprobada de capacidades individuales

Decisión del Director del **2026-10-17**, sobre baseline `57e0b013fafa3feda69911c96fea501c2f42a118`. Sustituye íntegramente cualquier instrucción ASE-ALIGN01 anterior. Extiende esta fundación sin crear otro login, tabla de usuarios, store de permisos o bundle por rol. [Trazabilidad de asesores y alcance vigente](../contexto/TRAZABILIDAD_COMERCIAL_Y_ASESORES_MXMED.md#ase-align01-v2--capacidad-individual-y-acreditación-rutinaria).

- **ADVISOR es una clase interna, no una plantilla de permisos.** Cada asesor ACTIVE recibe o pierde capacidades operativas explícitas de manera individual mediante los grants canónicos; una capacidad no implica otra. No hay un límite arbitrario de asesores de plataforma ni de combinaciones de capacidades delegables. Los ejemplos numéricos del Director no son cuotas ni subtipos.
- **Gobierno separado del uso.** DIRECTOR conserva la raíz de gobierno. MASTER_ADMIN sólo administra asesores y capacidades donde el contrato permite, con `internal_advisors_manage`, target ADVISOR y delegación exacta vigente. Ser delegador no concede uso; poder usar no concede delegar. ADVISOR no puede asignarse grants ni promoverse y MASTER_ADMIN no puede administrarse a sí mismo. Esta microfase no cambia las operaciones de raíz DIRECTOR previamente contempladas por IW01 ni convierte sus grants explícitos en bypass de uso.
- **Revocación y atribución.** Cada cambio retiene actor, cuenta exacta del asesor, capability exacta, estado e historia y auditoría canónica en la misma transacción. Cada petición relee autoridad vigente; revocar una capacidad no revoca otra ni reescribe acciones históricas legítimas.
- **Poderes reservados.** «Todas las funciones» significa todas las operaciones apropiadas que sean individualmente delegables. No autoriza nombrar Director/Master, administrar delegaciones, recuperar extraordinariamente autoridad, transferir excepcionalmente owner, firmar clínica ni elevar privilegios por pertenecer a ADVISOR.
- **Límites distintos.** Las dos plazas de operadores no médicos de Agenda son por médico. No limitan asesores internos ni se comparten sus cinco grants de Agenda con el personal de plataforma.
- **UI futura aprobada conceptualmente.** Una matriz/checklist debe representar identidad, relación staff activa, grants vigentes e historial por capacidad, con servidor autoritativo. Marcar/desmarcar sólo reflejará una concesión/revocación auditada del servidor. ASE-ALIGN01 V2 no implementa esa pantalla, un dashboard comercial ni un adapter genérico de gestión de asesores.

### Alineación rutinaria autorizada, MFA y activación

Sólo tres operaciones están incluidas; su capacidad exacta se revalida en el servicio consumidor además de MFA:

| Operación rutinaria | Capacidad operativa individual requerida |
|---|---|
| `PHYSICIAN_ENROLLMENT_REVIEW` | `physician_accreditation_review` |
| `PHYSICIAN_EVIDENCE_REVIEW` | `physician_accreditation_review` |
| `PROFESSIONAL_SOURCE_PROVISION` | `professional_source_provision` |

Un ADVISOR ACTIVE elegible puede ser **sujeto** de la ceremonia MFA existente, no registrar su propio factor. El **registrador** sigue siendo otra cuenta humana ACTIVE DIRECTOR/MASTER_ADMIN, autorizada por allowlist de despliegue, autenticada y recientemente reautenticada. Se preservan cifrado, unicidad/activación de factor, intentos, expiración, replay, auditoría y recuperación. [GovernanceStaffMfaCeremony](../../modules/identity/services/GovernanceStaffMfaCeremony.php) y [StaffMfaService](../../modules/identity/services/StaffMfaService.php) siguen siendo los servicios canónicos.

El proof de uso permanece ligado a cuenta, sesión, versión de credencial, operación exacta, caso, beneficiario y perfil, con expiración y consumo único. Un MFA válido no autoriza por sí solo una mutación: el consumidor relee cuenta/staff, grant, actor independiente, caso, scope y versiones/fuentes. `FIRST_DIRECTOR_BOOTSTRAP`, `MFA_RECOVERY`, `OWNER_REPLACEMENT`, `OWNER_REMOVAL`, `PHYSICIAN_AUTHORITY_CHANGE`, correcciones y operaciones ajenas no se amplían para ADVISOR. Se conserva el único revisor humano legítimo, independiente del solicitante; no se exige otro revisor redundante.

**Estado de esta fase:** implementación y QA aislada completas para las tres operaciones rutinarias aprobadas; **normal-local permanece inactivo**. Se reutilizan el workspace `/internal/media-review/index.php?section=acreditacion`, onboarding/C4 y ACT03. La activación sigue requiriendo autorización firmada, identidad/capacidad/cohorte exactas, grants/MFA, fingerprints, schema/fuentes, llaves/almacenamiento y principal SQL restringido. Los límites de cohorte de un despliegue no son una cuota de asesores de plataforma. Esta decisión no concede grants reales, no enrola MFA real, no crea recibos de activación reales, no habilita normal-local, no asigna owner y no habilita firma clínica. Captación comercial, cartera, cupones, Call Center y facturación conservan sus contratos separados.

## Validation histórica IW01

La entrega IW01 documentó `node modules/media/tests/OwnerMediaReviewHttpTest.mjs --keep`, las comprobaciones de `InternalGovernanceTest.php` sobre migración/backfill, Identity, gobierno, suspensión y rollback de auditoría, y regresiones de sesión/auditoría. Es evidencia histórica, no un comando ejecutado ni una aceptación repetida en ASE-ALIGN01 V2. El test histórico tiene un destino fijo `3309/mxmed`; no acredita aislamiento seguro para esta microfase. La cobertura nueva indicada abajo ejecuta el `InternalGovernanceService` real en una base nueva de propiedad del runner y conserva las nueve regresiones Identity/gobierno de Operator Foundation, sin reutilizar aquel destino.

`AuditMp01HStaticReadinessTest.php` is unchanged. In clean exported source trees, both baseline 77376d0d1aec9e9e4178037ad0e087914b4e6695 and IW01 reproduce the known `hidden_productive_wiring_zero` failure. Local generated application copies must be excluded when comparing this recursive static source scan.

### Verificación ASE-ALIGN01 V2

[AdvisorRoutineCapabilityIntegrationTest](../../modules/identity/tests/AdvisorRoutineCapabilityIntegrationTest.php), [runner desechable existente](../../scripts/gov03b-disposable-qa.py) (`--suite resolution --act03 --ase-align01`) y [QA del workspace original](../../scripts/ase-align01-browser-qa.py) ejercen cuentas, grants, MFA, fuentes y casos **sintéticos**, con schema-only clone, principal/Valkey/transporte/almacenamiento privados propios y eliminación al terminar. No apuntan los writers a `mxmed`, QA05, OP02 o UI03. El servicio canónico de gobierno se ejercita realmente; no se sustituye por checkbox, bundle de rol ni escritura de grants fuera del modelo para acreditar su autoridad.

**Resultado final: implementación y QA aislada PASS.** Ejecución `final-acceptance` de 2026-10-17: 139 checks ASE, 132 ACT03, 50 ACT02 y dos rechazos de schema parcial, 16 FREE, 89 PUB-ENR02/03 y 36 de fuente comercial. La cobertura incluye el servicio real de gobierno, uno/varios/diez asesores sintéticos, grants solapados e independientes, revocación con dos conexiones SQL y snapshot anterior, ceremonia MFA con registrador independiente, proof exacto y denegaciones sensibles, auditoría/rollback, concurrencia y fuentes canónicas. GOV03B/ceremonia/Operator Foundation conservaron su regresión aislada adicional, incluidas las nueve regresiones Identity/gobierno previas.

Chromium y WebKit pasaron **seis recorridos pareados**, uno por motor en `1440×900`, `1366×768` y `820×1180`: asesor sólo de fuentes → fuentes verificadas; asesor sólo de revisión → enrollment/C4 con el mismo revisor legítimo y sin adquirir la otra capacidad. Se comprobaron documentos privados, actor real, reauth/MFA, respuestas de servidor, ausencia de owner/firma, acciones no autorizadas denegadas y mensaje de fuentes coherente tras el commit. Doce capturas y checks DOM conservan navegación/layout originales; la sincronización espera el resultado renderizado por el módulo, sin reintentos que oculten fallos. La limpieza confirmó eliminación de base/usuario propios y fixtures persistentes intactas. No se declara Safari físico ni activación operacional.

Límite clínico preservado: `AUTH02A` sin modificar falla la assertion 29 en el árbol actual por una diferencia histórica de README. Su contrato completo NoTouchPDO pasó en un checkout privado con ese README histórico como fixture de compatibilidad y los bytes actuales de producción clínica, cinco pins SIM02 y fixture/test UX exactos sin cambios. No se relajó un pin ni se cambió fuente clínica; el checkout temporal se eliminó. Ese resultado compatible se distingue de una ejecución directa verde sobre el README actual.

## Sucesor acotado ASE-INT02A

[ASE-INT02A](../identity/ASE-INT02A-governed-advisor-grants.md) conecta POST individual grant/revoke mediante el mismo `execute`, con quinto argumento opcional servidor `ACTIVE_ADVISOR_OPERATIONAL`. Cuenta/staff ADVISOR activo, actor distinto y catálogo operativo delegable se verifican bajo locks; credencial/sesión y management/delegación actuales se revalidan. Callers sin ese argumento conservan el contrato histórico; regresiones sintéticas incluidas. Sucede la ausencia total de HTTP sólo para este adapter aislado con CSRF/TLS/origen, no para otros comandos. Auditoría y conflicto canónicos preservados; UI sólo consulta y operación real inactiva.

## Conexión acotada ASE-INT02B

[ASE-INT02B](../identity/ASE-INT02B-team-permission-ui.md) conecta las selecciones aprobadas al adapter individual ASE-INT02A. No modifica `execute`, catálogo, locks ni auditoría. Grupos son selección local; cada comando mantiene transacción propia, autoridad vigente y target ADVISOR activo. UI-2 autorizada, QA sintética nueva, gate default off. Nombramientos, delegación administrativa, admisión y operación real permanecen fuera.

## Lifecycle estricto ASE-INT03A

[ASE-INT03A](../identity/ASE-INT03A-advisor-lifecycle.md) agrega scope servidor opcional `ADVISOR_LIFECYCLE` a `execute`: sólo suspend/reactivate, target Advisor no-self, cuenta activa y estado inicial exacto bajo locks; sesión/credencial y management vigentes revalidados. Reutiliza UPDATE y auditoría; callers sin scope conservan semántica previa. No elimina grants, restaura revocados ni habilita cuentas; no conecta Master/admisión ni decide D06.

## Preparación Master ASE-INT03B-PREP01

[Contrato de seguridad propuesto](../identity/ASE-INT03B-PREP01-master-privileged-authorization.md): distingue comandos directos existentes de HTTP privilegiado aún ausente. Preserva clase/management/delegación/uso y democión canónica; documenta allowlist MFA, CHECK de casos, Director-only y consumo/audit atómicos pendientes. Sólo documentación; no altera writer, esquema, grants ni gates.

## ASE-INT03B-IMP01 — sucesor backend

[Fundación implementada](../identity/ASE-INT03B-IMP01-master-security-foundation.md): seis comandos Director-only, caso exacto, contraseña/MFA y consumo/writer/audit en un PDO. Conserva clase, gestión, delegación y uso independientes; democión conserva grants operativos y estados. Promoción producto bloqueada por reader de revisión interna ausente; QA positiva sólo con adapter CLI sintético. Gate default off y UI Master inactiva.

## ASE-INT03B-IMP02 — conexión UI aislada

[UI privilegiada IMP02](../identity/ASE-INT03B-IMP02-master-ui-integration.md) conecta controles existentes a casos/contraseña/MFA/comandos exactos y reconciliación canónica. Clase, management, delegación y uso siguen separados. Appointment producto bloqueado por review interna ausente; democión preserva estado/historia/grants. Sin operación real.

## Sucesor de política staff ASE-ADM02-POL01

[Contrato de admisión e identidad](../identity/ASE-ADM02-POL01-staff-admission-policy.md) registra siete principios aprobados por el Director; detalles legales/retención/delegación e implementación pendientes. No altera IW01 ni register: la operación string de execute sigue creando ADVISOR ACTIVE para cuenta activa, sin revisión documental. El futuro canal de admisión requiere guarda transaccional en el mismo writer y resolver orden grants/MFA antes de live; no invocación prematura ni segundo writer. Legacy conserva estado/audit sin etiqueta de identidad verificada. Authority de revisión staff y lectura ID no proviene de management o grants médicos; Master appointment sigue bloqueado sin snapshot autoritativo.
