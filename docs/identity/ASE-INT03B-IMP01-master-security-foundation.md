# ASE-INT03B-IMP01 — fundación privilegiada Master

Padre exacto `e71f3618772ff652a40bb597d45c199d5e851d75`; implementación exclusiva en `feature/ase-int03b-master-privileged-backend`, worktree `mxmed-ase-int03b-master-privileged-backend`. Oficial `f92f37f1958af5140a96c04cb52c5c1ce7af8e01` y visual `312ee388f93c892e6caf7e845b11e2b42f9c3565` protegidos. Estado: **READY_MXMED_ASE_INT03B_IMP01_MASTER_SECURITY_FOUNDATION_COMPLETE** para fundación aislada y verificación sintética acotada; no disponibilidad operacional de nombramientos reales.

## Decisiones aprobadas y límite real

La solicitud ASE-INT03B-IMP01 aprueba expresamente las cuatro recomendaciones de [PREP01](ASE-INT03B-PREP01-master-privileged-authorization.md): confirmación/contraseña actual/MFA del Director; promoción inicial sólo de Advisor ACTIVE con revisión interna de identidad completada; reducción de autoridad para Masters suspendidos o con cuenta disabled sin conceder/activar; implementación y ensayo sintéticos acotados, incluida compatibilidad del CHECK. El concepto UI-2 está aprobado; su conexión queda para IMP02. No cambia el layout ni habilita controles Master.

`StaffIdentityReviewPort` devuelve snapshot tipado cuenta/versión de credencial/versión de revisión bajo transacción. Composición producto usa siempre `UnavailableStaffIdentityReview`: **PRODUCT_MASTER_APPOINT_READINESS=BLOCKED_PENDING_STAFF_IDENTITY_REVIEW**. No existe selector HTTP/env de éxito ni equivalencia con email, MFA o identidad médica. Sólo el fixture CLI, con guards de datadir propio, tiene adapter de revisión sintética. Las siete políticas ADM y D01–D08 permanecen separadas.

## Fuente y verificación

Se reutilizan writer, casos, Identity, reauth, MFA y auditoría canónicos. Seis operaciones y separación de clase/management/delegación/uso según PREP01. Gate `MXMED_MASTER_GOVERNANCE_ENABLED` default off, autorización privada de despliegue existente con acción específica y hash fuente adicional. No altera documentos/configuración originales de despliegue. Toda prueba usa una instancia MySQL nueva y Valkey/TLS propios. Los fallos del harness se preservan en `docs/evidence/ase-int03b-imp01/`; no contienen secretos.

El CHECK se deriva de la unión de operaciones de las migraciones fuente (once históricas) más seis nuevas. No reescribe ACT03: la ruta ACT02 con correcciones → ACT03 debe detenerse si su CHECK histórico rechaza filas. La migración nueva no se usa para ocultar ni saltar ese fallo.

## Operaciones y límites de autoridad

| Operación de caso/MFA | Comando del writer | Capability |
|---|---|---|
| INTERNAL_MASTER_APPOINT | appoint_master | null |
| INTERNAL_MASTER_REMOVE | remove_master | null |
| INTERNAL_ADVISOR_MANAGEMENT_GRANT | grant | internal_advisors_manage |
| INTERNAL_ADVISOR_MANAGEMENT_REVOKE | revoke | internal_advisors_manage |
| INTERNAL_CAPABILITY_DELEGATE | delegate | Una key operacional delegable exacta |
| INTERNAL_CAPABILITY_DELEGATION_REVOKE | revoke_delegation | Una key operacional delegable exacta |

Sólo DIRECTOR/ACTIVE con cuenta activa, sesión vigente y credencial actual puede abrir, confirmar, verificar y consumir. No self-targeting. Appointment exige relación ADVISOR/ACTIVE previa, cuenta activa y snapshot de revisión interna aprobado de la cuenta/versión exacta. No concede grants ni delegaciones. Aumentos exigen MASTER_ADMIN/ACTIVE y cuenta activa. Reducciones permiten Master suspendido/cuenta disabled sin activarlos. Democión vuelve a ADVISOR, revoca management y delegaciones activas, conserva grants operativos, estados e historia. Revocar management por sí solo conserva las delegaciones registradas, que no autorizan administrar sin management vigente.

## Caso, proof y transacción

[Cases](../../modules/identity/MasterGovernance/Cases.php) deriva del servidor caso/UUID, operación, target, capability/null, actor/sesión/credential version/token digest, snapshot de categoría/estados/updated_at, grants/delegaciones y review version, expiración y digest canónico. JSON se ordena recursivamente para no depender del orden de claves de MySQL. El navegador confirma el digest; no aporta versiones, rol, review, estado de aprobación o resultado. Caso expira a cinco minutos; una confirmación y un challenge por caso. Cambios materiales requieren caso/revisión nuevos.

[Policy](../../modules/identity/MasterGovernance/Policy.php) cierra el vocabulario y ordena cuentas/staff y credenciales antes de caso/challenge/proof/factor. MFA exige Director en emisión, verificación y consumo; factor ACTIVE previamente enrolado, contraseña canónica reciente (<5 minutos), intent digest y bindings exactos. Conserva TOTP, attempts/lock, hash de nonce/proof, expiración y replay. El enrollment sigue en GovernanceStaffMfaCeremony con registrar independiente; nunca se hace implícitamente desde estos endpoints.

[InternalGovernanceService](../../modules/identity/services/InternalGovernanceService.php) sigue siendo el único writer de staff/grants/delegaciones. executePrivileged deriva el comando del caso; execute histórico conserva contrato. El mismo PDO y transacción abarcan locks/revalidación, consumo del proof, mutación, audit canónico y case CONSUMED/result. Un fallo de audit revierte también proof, resultado y stream head. No transacción externa anidada, writer alternativo, retry de deadlock ni compensación automática.

El punto final de autoridad está después de adquirir locks y consumir/revalidar proof, inmediatamente antes del SQL de gobierno: assertLive(recent) consulta sesión Valkey y cuenta/credencial canónicas, seguido de Director bajo lock. Logout completado antes de ese punto deniega. Un logout posterior puede coincidir con el commit SQL: **no existe garantía atómica SQL+Valkey ni fencing global**. La prueba observa waits reales y completa logout antes de liberar al writer.

Resultados se reconcilian leyendo caso del mismo Director, operation UUID y evento canónico correlacionado con actor/target (ID físico SHA-256 del UUID de evento). No se infiere éxito del estado actual del target. Una sesión nueva del mismo Director puede leer/cancelar su caso inutilizable; confirmar/verificar/ejecutar siguen ligados a la sesión original. Cancelar/expirar invalida challenges pendientes/proofs verificados sin mutar al target.

## API preparada, sin conexión UI

Prefijo `/api/internal/team/master/`. [Composición](../../modules/identity/MasterGovernance/Composition.php) exige gate propio `MXMED_MASTER_GOVERNANCE_ENABLED=1`, runtime Identity canónico, autorización de despliegue vigente/pinned para `master_governance`, fingerprint de esquema, hashes de fuente y claves MFA/audit. El gate Advisor no habilita Master. La autorización creada por QA es exclusivamente privada y sintética; no cambia configuración ni autorizaciones originales.

| Endpoint | Método | Campos exactos |
|---|---|---|
| csrf.php | GET | Sin query/body |
| read.php | GET | Query case_id |
| open.php | POST | operation, target, capability, reason |
| confirm.php | POST | case_id, intent_digest, email, password |
| verify.php | POST | case_id, challenge_id, challenge, code |
| execute.php | POST | case_id, proof_id, proof |
| cancel.php / expire.php | POST | case_id |

[Boundary HTTP](../../modules/identity/MasterGovernance/Http.php): HTTPS verificado, Origin exacto/Fetch-Site same-origin, sesión canónica y CSRF autenticado para cada POST, JSON plano cerrado, sin duplicados/keys extra, máximo2048 bytes y no-store, sin CORS. GET usa resolución de sesión observeOnly: no touch ni revocación persistida; binding se valida sin escritura. Ningún endpoint recibe autoridad confiable del navegador. Password/TOTP/proof no van a URL, audit o almacenamiento de navegador. Errores devuelven `retry=false`; ante incertidumbre leer caso/audit, nunca repetir ciegamente.

## Rehearsal de esquema y bloqueo histórico

[Migración nueva](../../modules/identity/db/migrations/2026_10_18_01_internal_master_governance_operations.sql) sustituye sólo el CHECK de operation mediante ALTER atómico, con once nombres históricos derivados de SQL versionado más seis nuevos. No cambia enums, FKs, índices, filas históricas ni otros CHECKs; no desactiva enforcement. No crea staff/reviews/grants ni modifica migraciones históricas.

| Variante | Ensayo y resultado esperado |
|---|---|
| A | Prerrequisitos limpios + bindings → migración nueva |
| B | Paquete ACT02 ordenado → migración nueva |
| C | ACT02 → ACT03 → migración nueva |
| D | ACT02 + casos históricos de corrección → ACT03: **STOP**, error3819 del CHECK no acumulativo |
| E | ACT03 + casos previos/audit → migración nueva, conservando filas/historia |

La ruta D no aplica la nueva migración ni salta ACT03. Conserva dos casos de corrección y CHECK previo tras el fallo. Plan futuro acotado: revisar/aprobar un sucesor explícito de instalación ACT03 con CHECK acumulativo y metadatos/fingerprint propios, ensayarlo sobre ese estado conservado y sus consumers antes de autorizar despliegue. No está autorizado reescribir historia, eliminar filas o ejecutar DDL real. **No todas las rutas de upgrade pasan.**

## Evidencia y límites de verificación

[Harness](../../scripts/ase-int03b-qa.py) y [extensión dirigida](../../scripts/ase-int03b-extra-qa.py) crean MySQL con datadir/credenciales nuevos, Valkey y TLS en puertos aleatorios no protegidos. Prerrequisitos vienen de SQL versionado, nunca de clon de base real. [Fixture CLI](../../modules/identity/tests/MasterGovernanceFixture.php) exige entorno propio y no es seleccionable desde producto HTTP. El adapter de prueba bloquea review exacta bajo FOR UPDATE; producto usa siempre el adapter indisponible.

Evidencia final en [qa.json](../evidence/ase-int03b-imp01/qa.json), [rehearsal](../evidence/ase-int03b-imp01/migration-rehearsal.json) y [límites](../evidence/ase-int03b-imp01/security-boundaries.json). Los reportes failed-* registran intentos incompletos del harness, no son PASS finales; preservan el fallo y hashes de fuente, sin secretos. La verificación final debe corresponder a hashes actuales.

Cobertura: seis positivos independientes, estados de reducción, negativos de actor/target/review/reauth/challenge/proof/factor, rollback audit, waits SQL independientes, duplicado concurrente y logout Valkey, HTTP real cerrado/default-off y producto appointment fail-closed. Regresión de API Advisor grant/revoke y lifecycle; once primitivas MFA históricas y CHECKs de contratos ownership/C4/corrections/source. **No se ejecutan workflows clínicos/ownership completos ni QA contra persistent DB.**

Reconciliación se prueba ignorando la respuesta exitosa y leyendo caso/UUID/audit, además de replay/duplicado sin segundo commit. **No se inyecta pérdida de ACK de red real.** La UI no se conecta en esta fase y no añade ningún retry. No hay validación visual nueva ni aceptación operacional.

## CTX-SYNC01

- CONTEXT_BASELINE: PREP01 e71f3618772ff652a40bb597d45c199d5e851d75; oficial f92f37f y visual312ee388 preservados.
- AFFECTED_CAPABILITY_IDS: ID-07, COM-10; reutilización ID-02/ID-03.
- AFFECTED_JOURNEY_IDS: J09; frontera comercial J08 preservada.
- AFFECTED_DATA_OBJECTS: auth_governance_cases, auth_staff_mfa_factors/challenges/proofs, internal_staff, internal_operator_grants, internal_capability_delegations, auth_accounts/credentials, sesión Valkey, platform_audit_events/stream_heads; review snapshot tipado sin tabla producto.
- AFFECTED_CONTEXT_FILES: estado, router, este contrato, PREP01, IW01, IDENTITY-GOV03A, inventario funcional, datos/migraciones, interacciones, trazabilidad comercial/asesores, registro visual.
- CONTEXT_CHANGES_IMPLEMENTED: cuatro decisiones, implementación/caso/MFA, transacción, revisión fail-closed, CHECK/rehearsal bloqueado, sesión y UI IMP02.
- CONTEXT_VERIFICATION: 321 checks PASS, nueve carreras observadas, hashes de producto coincidentes (harness: sólo whitespace posterior, AST idéntico), PHP lint/Python syntax, diff y enlaces locales revisados; siete worktrees/listeners/pointer protegidos.
- CONTEXT_SYNC_STATUS: PASS_UPDATED.
- DOCUMENTATION_COMMIT: mismo commit aislado de implementación; hash a reportar tras cierre.
- REMAINING_CONTEXT_GAPS: reader autoritativo de admisión/revisión, conexión UI IMP02, reparación aprobada ruta D, siete políticas ADM y D01–D08 sin ampliar; sin activación real.

PRODUCT_POLICY: cuatro decisiones aprobadas y separación de poderes. SOURCE_IMPLEMENTATION: fundación/backend preparada. CONNECTIVITY: HTTP gated, UI Master desconectada. VERIFICATION: sólo sintética acotada, con bloqueo histórico y límites explícitos. OPERATIONAL_STATUS: inactivo; ninguna promoción real habilitada.

Cierre sintético: **321 checks PASS / nueve carreras observadas**. Rehearsal A/B/C/E PASS; D BLOCKED_HISTORICAL_ACT03_CHECK, no upgrade general PASS. La ejecución final detuvo procesos y eliminó su datadir propio. [Disposición de intentos fallidos](../evidence/ase-int03b-imp01/failed-resources-disposal.json) verifica ownership y ausencia de procesos antes de retirar sólo sus directorios; reportes sanitizados preservados. El campo heredado `ui_still_read_only=false` del runner describe que Advisor ya tiene mutaciones anteriores; las acciones **Master** siguen deshabilitadas (security-boundaries.json).

Revisión staged detectó un espacio final en el harness nuevo: se retiró después de QA sin cambiar su AST ni fuente producto. qa.json conserva el hash realmente probado; [verificación final](../evidence/ase-int03b-imp01/final-verification.json) registra ambos hashes y la única diferencia cosmética, sin atribuir una nueva ejecución.

## ASE-INT03B-IMP02 — conexión UI aislada

[IMP02](ASE-INT03B-IMP02-master-ui-integration.md) sucede la desconexión UI: mismo writer/caso/proof, proyección de disponibilidad y flujo individual con reconciliación. Contrato de seguridad, review producto ausente, límite SQL/Valkey y ruta D bloqueada se preservan. Los controles sólo se habilitan en QA nueva con autoridad canónica; no operación real.

## ACT03-CHECK-COMPAT01 — sucesor de compatibilidad SQL

[COMPAT01](ACT03-CHECK-COMPAT01-historical-case-check.md) atiende el bloqueo histórico de ruta D mediante sucesor previo al ALTER incompatible. Conserva este rehearsal y la migración Master de 17 operaciones; no habilita identity-review ni appointment. Ver evidencia nueva sin reinterpretar el resultado histórico.

## Sucesor documental ASE-ADM02-POL01

[Política de admisión staff](ASE-ADM02-POL01-staff-admission-policy.md) sucede únicamente la condición de siete principios ADM pendientes: el Director los aprobó, con detalles legales/retención/delegación y adaptadores aún pendientes. Define backing autoritativo y transaccional del port actual de tres campos; no lo implementa ni conecta. UnavailableStaffIdentityReview continúa incondicional en HTTP y appointment sigue BLOCKED_PENDING_STAFF_IDENTITY_REVIEW. Evidencia y QA de IMP01 conservadas, sin nueva ejecución.
