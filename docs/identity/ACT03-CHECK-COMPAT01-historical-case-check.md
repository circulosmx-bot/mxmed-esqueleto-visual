# ACT03-CHECK-COMPAT01 — compatibilidad histórica del CHECK de casos

Baseline: `e3182791fd414c1195e3a7bb64bbb60d20d00530`, ASE-INT03B-IMP02 terminado. Feature aislada `feature/act03-governance-check-compat01`; oficial f92f37f y prototipo312ee388 preservados. Resultado: READY_MXMED_ACT03_CHECK_COMPAT01_ISOLATED_REPAIR_COMPLETE. 408 comprobaciones SQL PASS; no activación operacional.

## Defecto y estrategia versionada

El primer ALTER de [ACT03 histórico](../../modules/identity/db/migrations/2026_10_17_07_professional_source_provisioning.sql) sustituye el CHECK acumulativo de ACT02 por nueve operaciones. Omite `PUBLIC_PROFILE_CORRECTION_REVIEW` y `PUBLIC_PROFILE_CORRECTION_APPLY`: con filas previas, MySQL rechaza ese ALTER con 3819; sin ellas, restringe futuras correcciones. Una migración posterior al fallo no permite avanzar. Los resultados históricos de PREP01/IMP01/IMP02 se conservan.

[DeploymentPackage::professionalSourceMigrations](../../modules/identity/Operational/DeploymentPackage.php) publica ahora, para instalaciones nuevas de ACT03, las diez etapas ACT02 originales y dos etapas sucesoras:

1. [2026_10_18_02 — CHECK compatible](../../modules/identity/db/migrations/2026_10_18_02_professional_source_check_compat01.sql): once operaciones, unión explícita y verificada desde los CHECK versionados de gobierno, ACT02 y ACT03. Se ejecuta en el punto anterior a los objetos ACT03; no después de un fallo ocultado.
2. [2026_10_18_03 — objetos ACT03](../../modules/identity/db/migrations/2026_10_18_03_professional_source_objects_compat01.sql): cuerpo original desde el primer CREATE, idéntico byte por byte. Conserva las cinco tablas, vínculo C4, FKs, índices y siete triggers; sólo sustituye la ruta del primer CHECK.

No se edita el SQL histórico, no se omite ninguna dependencia ni se registra ACT03 histórico como completado cuando falló. Las operaciones se definen en SQL revisable; el verificador deriva la unión desde fuentes reales y comprueba que el cuerpo sucesor no diverja. No hay allowlist alternativa en un runtime ni fallback que deshabilite integridad.

El sucesor CHECK incluye una guarda temporal: exige FK de sesión activa y CHECK existente enforced, y rechaza esquemas con operaciones INTERNAL antes de sustituirlo. Evita reducir accidentalmente el CHECK Master de 17 a 11, incluso invocando el archivo directamente. No es un tracker ni reemplaza el preflight completo; el objeto temporal desaparece al terminar la conexión si la guarda falla.

## Orden para cada estado verificado

| Estado físico y evidencia de ejecución | Ruta permitida en el ensayo |
|---|---|
| ACT02 completo, CHECK de diez operaciones, objetos ACT03 ausentes, receipts completos ligados al target/fuentes | Sucesor CHECK 02 → objetos 03. Incluye correcciones históricas existentes. |
| ACT03 histórico completo, CHECK de nueve, objetos/triggers exactos y ejecución histórica comprobada | Sólo sucesor CHECK 02. Nunca replay de los objetos. |
| Compatible completo, once operaciones y ejecución 02/03 o histórico+02 comprobada | No-op verificado; ninguna sentencia DDL repetida. |
| ACT03 completo más migración Master comprobada, CHECK de 17 | Conservar 17; no ejecutar CHECK 02 ni objetos. |
| Metadata parcial, deriva, recibos faltantes/fallidos, dependencias no verificadas | BLOCKED en ese target; no inferir completitud por nombre de archivo o marcador. |

La [migración Master existente](../../modules/identity/db/migrations/2026_10_18_01_internal_master_governance_operations.sql) se conserva sin cambios. Se aplica **después** de completar/verificar ACT03 compatible cuando se ensaya esa extensión, aunque su nombre cronológico sea anterior al sucesor. No ordenar todos los SQL lexicográficamente. Sin esa extensión, las seis operaciones Master siguen rechazadas por SQL; con ella, se admiten exactamente 17 nombres. Admitir un nombre en SQL no concede autoridad para ejecutar el comando.

El repositorio no contiene un instalador ACT03 con modo apply/tracking operativo. No se añadió uno: el manifiesto publica el orden para estado nuevo; la selección automática del [harness](../../scripts/act03-check-compat01-qa.py) está restringida al ensayo. Compara todos los CREATE TABLE, CHECK/FK/UNIQUE, expresiones generadas, triggers y rutina de metadata con referencias compiladas desde fuentes en otro datadir nuevo, además de receipts de ejecuciones reales ligados a UUID/datadir/DB/hash. Los receipts de QA no son aprobaciones firmadas ni historia de una instalación real.

## Allowlist efectiva

Once operaciones antes de instalar Master:

```text
FIRST_DIRECTOR_BOOTSTRAP
INITIAL_OWNER
MFA_RECOVERY
OWNER_REMOVAL
OWNER_REPLACEMENT
PHYSICIAN_AUTHORITY_CHANGE
PHYSICIAN_ENROLLMENT_REVIEW
PHYSICIAN_EVIDENCE_REVIEW
PROFESSIONAL_SOURCE_PROVISION
PUBLIC_PROFILE_CORRECTION_APPLY
PUBLIC_PROFILE_CORRECTION_REVIEW
```

La extensión Master añade únicamente:

```text
INTERNAL_ADVISOR_MANAGEMENT_GRANT
INTERNAL_ADVISOR_MANAGEMENT_REVOKE
INTERNAL_CAPABILITY_DELEGATE
INTERNAL_CAPABILITY_DELEGATION_REVOKE
INTERNAL_MASTER_APPOINT
INTERNAL_MASTER_REMOVE
```

La verificación inserta cada nombre contra CHECKs efectivos enforced; también rechaza UNKNOWN_OPERATION, `all` y `admin.everything`. No sustituye la lista acumulativa por los nueve nombres históricos.

## Variantes, preservación y DDL parcial

A: prerrequisitos limpios → paquete compatible. B: ACT02 completo sin correcciones → compatible. C: ACT03 histórico completo → reparación CHECK → extensión Master. D: ambas correcciones insertadas antes del upgrade; reproduce primero 3819 histórico y después completa el sucesor conservando filas. E: ACT03 histórico con caso/auditoría previos → reparación CHECK. F: correcciones previas → compatible → casos Master nuevos, coexistiendo. G: repetición legítima no-op con metadata/receipts, bloqueo de replay y de evidencia incompleta; preserva Master 17 y rechaza downgrade directo. H: interrupción reproducida después del CHECK histórico y primer CREATE; in-place bloqueado y recuperación verificada en otro datadir.

H no declara rollback transaccional de DDL: se observa que CHECK y tabla sobreviven a `ROLLBACK`. Recupera un preestado completo conocido con DDL de sus fuentes exactas y backup de todas sus filas; carga todos los INSERT del backup en orden FK sobre un datadir nuevo. Nunca ejecuta el prólogo de mysqldump que puede deshabilitar FKs. Compara schema y todas las filas originales antes de continuar por la ruta compatible. No modifica ni borra historia para reparar el target parcial. Sin backup íntegro y evidencia comparable, esa recuperación continúa BLOCKED y exige decisión separada; no se fabrica una reparación general de parciales.

Regresión acotada: filas/propiedad/operación/status/timestamps/audit intactos; FK y CHECK activos; UUID y target activo únicos; lifecycle OPEN→APPROVED→CONSUMED y liberación de target; auditoría append-only; archivos/journal de fuentes inmutables y binding protegido. Los siete guards conservan sus definiciones físicas exactas. Migraciones no crean staff/grants/delegaciones/factores MFA. Las fixtures son casos y datos ficticios; no se abre ningún archivo personal ni se enrola MFA.

## Límites operacionales y fingerprints

Sólo MySQL nuevo por socket con `--skip-networking`; sin Valkey, HTTP, navegador, servicios externos ni conexión a mxmed/QA05/OP02/UI03. No se repiten las 812 pruebas UI: UI, servicios Master, sesión, MFA, writers, permisos, fuentes clínicas y suscripciones de producto no cambian.

La nueva ruta cambia `DeploymentPackage::sourceHash()` en esta feature y el CHECK reparado cambia la metadata/fingerprint respecto a ACT03 histórico. [Verificación final](../evidence/act03-check-compat01/final-verification.json) registra hashes de fuente padre/feature; no genera firmas, pins, receipts ni autorizaciones reales. Una futura instalación exige preflight físico aprobado del target exacto, tracking verificable, backup/restauración, ventana/principal/definers revisados y **nueva autorización** ligada a fuente/schema vigentes. No utilizar hashes de QA como aprobación operacional. Los prerrequisitos ensayados son la clausura SQL necesaria y las diez etapas ACT02; no acreditan todos los gates de un runtime normal-local.

BLOCKED_PENDING_STAFF_IDENTITY_REVIEW permanece; appointment real deshabilitado. D01–D08 y las siete políticas ASE-ADM01 siguen pendientes. La reparación sintética de D no habilita Master, admisión, revisión documental, owner ni firma clínica. No merge, promoción o despliegue.

## CTX-SYNC01

| Campo | Registro |
|---|---|
| CONTEXT_BASELINE | IMP02 e3182791; PREP01, IMP01, IMP02, ACT02/03, GOV03A, datos/migraciones y CTX-SYNC01 contrastados. |
| AFFECTED_CAPABILITY_IDS | ID-04/05/06/07 como dependencias de persistencia; ninguna capability nueva. |
| AFFECTED_JOURNEY_IDS | J01/J02/J09: persistencia compatible, sin cambiar flujos ni autoridad. |
| AFFECTED_DATA_OBJECTS | CHECK de auth_governance_cases; mismos objetos ACT03/evidencia C4, sin nueva estructura staff. |
| AFFECTED_CONTEXT_FILES | Estado, router, contrato COMPAT01, ACT02, ACT03, IMP01/02, GOV03A, inventario, interacciones y datos/migraciones. |
| CONTEXT_CHANGES_IMPLEMENTED | Defecto, sucesor 02/03, orden físico, 11/17 operaciones, tracking/replay, recuperación parcial, fingerprints y restricciones. |
| CONTEXT_VERIFICATION | 408 comprobaciones SQL, ocho variantes, metadata/filas/guards, hashes de fuentes, diff completo y enlaces revisados; ver evidencia final. |
| CONTEXT_SYNC_STATUS | PASS_UPDATED. |
| DOCUMENTATION_COMMIT | Mismo commit aislado de implementación; hash al cierre. |
| REMAINING_CONTEXT_GAPS | Preflight/backup/receipts de instalaciones reales no inspeccionados; parciales sin backup no recuperables automáticamente; identity-review, D01–D08 y siete políticas ADM pendientes. |

PRODUCT_POLICY: sin decisiones nuevas de autoridad. SOURCE_IMPLEMENTATION: sucesor SQL y referencia mínima del paquete. CONNECTIVITY: orden preparado, sin apply operacional. VERIFICATION: sólo SQL sintético dirigido. OPERATIONAL_STATUS: inactivo.

## Evidencia final y límites de cierre

- [QA: 408 PASS, variantes A–H, receipts y disposición de diez datadirs nuevos](../evidence/act03-check-compat01/qa.json).
- [Referencia física compilada desde SQL: 99 objetos ACT02 y 111 ACT03](../evidence/act03-check-compat01/schema-reference.json); incluye definiciones de tablas, triggers y rutina, sin filas personales.
- [Verificación final: nueve worktrees protegidos, listeners/pointer, hashes y archivos](../evidence/act03-check-compat01/final-verification.json).
- [Primer ensayo fallido](../evidence/act03-check-compat01/failed-20261018T154615Z.json): detectó una comparación demasiado estricta del harness después de agregar filas nuevas de regresión. El cierre distingue igualdad completa inmediatamente después de migrar de preservación de cada fila histórica tras coexistir registros nuevos. Sus dos datadirs también quedaron dispuestos.

A/B/D/E/H terminan con CHECK 11, sin extensión Master; C/F/G terminan con 17 y evidencia de su migración. Todos rechazan unknown/wildcards y conservan los casos/auditoría originales. H PASS significa restauración verificada a un target nuevo; **el estado parcial original continúa BLOCKED para replay/reparación in-place**. No se declara seguro un parcial arbitrario ni una instalación real no inspeccionada.
