# C4-ADM02-IMP01 — discrepancias previas al emisor

Fecha: 2026-10-16. Baseline y upstream: `97db0129100a5c13c9df0d1cb49dc975ea4adb37`, rama `ux/consultation-step2-vitals-r1`, worktree `mxmed-step2-vitals-r1` inicialmente limpio.

```text
BOUNDED_SOURCE_IMPLEMENTATION_AUTHORIZED=true
RESULT=BLOCKED
CANONICAL_ADMISSION_ISSUER=NOT_IMPLEMENTED
NORMAL_LOCAL_ACTIVATION_AUTHORIZED=false
CLINICAL_SIGNING_ACTIVATED=false
```

La solicitud autoriza implementar fuente y ensayar con datos sintéticos; no autoriza migraciones o autoridades reales. También ordena detener el componente afectado si el esquema o contrato existente no permite un emisor seguro. Esta entrega documenta esa parada: **no contiene un emisor, un escritor de binding o una conexión nueva a ownership**. No convierte el ensayo GOV-SIM02 en acreditación humana. Los cambios son exclusivamente documentales.

## Discrepancias comprobadas

| Componente | Evidencia | Consecuencia |
|---|---|---|
| Expediente protegido | C4 exige identidad oficial, validación profesional y relación exacta cuenta–persona–perfil. [VID01](../../modules/profiles/services/VerifiedPhysicianIdentityService.php) y [CRD02](../../modules/profiles/services/VerifiedDoctorCredentialService.php) aprovisionan hechos del perfil; sus referencias no son un expediente de evidencia del titular de una cuenta Identity. [MediaReviewAccessService](../../modules/media/services/MediaReviewAccessService.php) sólo permite `DOCTOR_PROFILE_PHOTO`, `PHYSICIAN_PERSONAL_LOGO`, `DOCTOR_GALLERY`. El enum instalado de submissions coincide. | No existe una fuente protegida reutilizable que resuelva/bloquee documentos oficiales y la relación humana exacta, revisión, disponibilidad, vencimiento y revocación. No reinterpretar fotos o referencias libres como acreditación. Detener el emisor y su adaptador positivo de evidencia. |
| Contacto y origen | La [guía C4, §§5–8](../product-completion/MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md) mantiene verificación telefónica antes de acreditación y origen de claim inmutable. La [reconciliación anterior](GOV03B-DEC01-policy-reconciliation.md) identifica esos pendientes. El inventario de `mxmed` no encuentra columnas de teléfono verificado ni tablas de verificación telefónica de Identity. | Una sesión/email activo no demuestra ese prerrequisito. Falta resolver su autoridad e integración para el mínimo C4; no afirmar teléfono o claim verificados ni inventar una exención para legado. No se propone un proveedor SMS ni onboarding completo. |
| Confirmación profesional | La [migración preparada](../../modules/identity/db/migrations/2026_10_16_02_physician_account_binding.sql) contiene `acceptance_reference` textual, sin hash de desafío, sesión/versión de credencial confirmantes, revisión exacta, vencimiento o consumo único. La aceptación de ownership existente pertenece a otra operación, dependiente de elegibilidad C4. | No tratar un texto, un correo de invitación o la aceptación posterior de owner como confirmación profesional independiente. Hace falta una adaptación explícita coordinada con el expediente protegido, sin reutilizar pruebas de otra operación. |
| Caso y MFA | [Gobierno instalado](../../modules/identity/db/migrations/2026_10_15_01_identity_governance_r3_runtime.sql): `ck_auth_governance_case_operation` admite sólo `INITIAL_OWNER`, `OWNER_REPLACEMENT`, `OWNER_REMOVAL`, `FIRST_DIRECTOR_BOOTSTRAP`, `MFA_RECOVERY`. [StaffMfaService](../../modules/identity/services/StaffMfaService.php) usa esa lista; sus pruebas referencian el caso exacto. | Falta la operación de acreditación para ligar MFA. Agregar un valor no implementa revisión/auditoría/lifecycle. No disfrazar acreditación como `INITIAL_OWNER` ni consumir proofs de recuperación. |
| Escritor y auditoría | Las dos tablas preparadas describen estado actual y referencias. No existe escritor que establezca/consuma evidencia, confirme beneficiario, aplique CAS/versiones y audite cada transición en el mismo PDO. La proyección sólo lee esos hechos. | No insertar filas para completar una prueba del emisor. Los ensayos de ownership no prueban esas mutaciones ausentes; historial, revocación y eventos deben definirse junto con el expediente y sus pruebas. |

Los tres últimos puntos son trabajo técnico pendiente autorizado por la solicitud; no constituyen por sí solos una prohibición de adaptar migraciones. La parada se debe a la falta de fuente/contrato seguro de evidencia protegida y a los prerrequisitos de contacto/origen no resueltos. No se preparó DDL especulativo ni una interfaz siempre aprobatoria para sustituirlos.

Un revisor humano con `physician_accreditation_review`, MFA/reauth y aceptación independiente **ya está aprobado**; no hace falta otra decisión sobre quorum. No se requiere otro maestro de persona, copia de cédula, motor de membresías, segundo revisor rutinario, revisión obligatoria de doce meses o recuperación raíz V3.

## Normal-local: lectura, no activación

`mxmed`: 128 tablas; ninguna de las dos tablas nuevas instalada; `auth_governance_cases.security_context` ausente. Una cuenta Identity, cero staff, cero grants internos, cero owners activos, una identidad VID01 y cero credenciales CRD02. No se deduce elegibilidad ni titularidad a partir de estos conteos. Las quince tablas de OP-ACT02A conservan su estado, sin replay.

Las migraciones `2026_10_16_01_governance_security_bindings.sql` y `2026_10_16_02_physician_account_binding.sql` permanecen preparadas, byte por byte, sin aplicar en normal-local. `GovernanceHttpComposition` conserva el adaptador sin proyección y rechaza. No se conectó una lectura positiva de filas cuya emisión y confirmación faltan. `clinical_professional_authority.php` conserva su denegación; ninguna credencial, rol o fixture habilita firma.

## Reconciliación futura de legado

1. Inventariar el perfil exacto sin mutarlo: existencia/publicación/comercialización, VID01/CRD02 y membresías son dimensiones separadas.
2. Sin decisión humana C4 vigente, clasificar como **perfil heredado que requiere revisión**, nunca admitido por antigüedad, pago, publicación, coincidencia textual o membresía.
3. Reutilizar VID01/CRD02 verificados y sus referencias/versiones. Resolver contacto, origen y conflictos por su autoridad. No repetir automáticamente revisión de documentos ya aceptados; tampoco dar por demostrado el titular de otra cuenta.
4. Implementado el expediente, un revisor autorizado acredita la relación exacta y el beneficiario confirma control de su cuenta con prueba propia, breve y de un uso.
5. Mostrar por separado: acreditación humana; binding confirmado; owner aprobado en su transacción independiente; firma profesional habilitada por su integración independiente. Suspender/revocar admisión o binding bloquea decisiones futuras sin borrar ownership histórico ni transferir perfil.

Es un procedimiento pendiente, **no una herramienta ejecutada**, un backfill o una autoridad nueva.

## Frontera clínica posterior

El futuro consumidor de sólo lectura debe revalidar sesión/cuenta/versión, membresía owner exacta, VID01/CRD02 vigentes, admisión/binding aprobados y confirmados, autoridad profesional, entitlement, operación y paciente/documento/versión/firma/integridad. La mutación clínica conserva su auditoría/concurrencia. Esa lectura no crea un permiso cacheado ni activa firma. [CLIN-AUTH02B1](../clinical/CLIN-AUTH02B1_PROFESSIONAL_AUTHORITY_CONTRACT.md) y CLIN-AUTH02A siguen vigentes. Operadores no heredan facultades profesionales; cuotas, grants de Agenda y creador inmutable se preservan.

## Validación y límites

Evidencia privada: `~/Library/Application Support/MXMED/c4-adm02-imp01/20261016-review/`.

- Preflight y snapshots normal-local: conteos/checksums/DDL/rutinas/triggers, configuración, hashes de sesiones y QA05/OP02/UI03 antes/después. Sólo `SELECT`/`SHOW` y lectura de Valkey en entornos persistentes.
- Ocho regresiones existentes: sesión GOV-SIM02, C3, sesión privada, roles, HTTP de gobierno, Agenda, resolver clínico y CLIN-AUTH02A: PASS.
- `gov03b-disposable-qa.py --suite contracts`: PASS con schema-only clone, principal SQL propio restringido, cuentas sintéticas y auditoría nativa. Verifica rollback por fallo de auditoría, aislamiento de `mxmed`, MFA, replay, concurrencia, un revisor rutinario y aceptación independiente. Elimina DB y usuario al terminar.
- **Esos positivos pertenecen al workflow GOV-SIM02**, cuyo helper inserta admisión/binding sintéticos. No prueban un nuevo emisor C4. La matriz solicitada de acreditación/confirmación/lifecycle no se ejecutó: no puede informarse como PASS.
- No se inició Valkey para un emisor inexistente. Sesiones reales, procesos normales y entornos persistentes se preservan. QA física Safari del Director: NOT_RUN.

## Próximo alcance

Resolver el contrato del expediente protegido: prueba oficial y relación cuenta–persona–perfil, captura/custodia/acceso limitado, procedencia, revisión humana, vigencia/revocación y referencias inmutables. Resolver la autoridad de contacto y origen/claim o una excepción de producto explícita. Después completar el único emisor, adaptar caso/MFA/confirmación y lifecycle/auditoría, ensayar con fuentes sintéticas declaradas y conectar la proyección cuando la emisión sea válida. Mantener denegación si falta un hecho.

No se necesita activar normal-local para resolver pendientes de fuente. DDL real, nombramiento/grants de revisores y beneficiarios concretos, ownership y firma requieren autorizaciones separadas. No resetear los objetos OP-ACT02A ni fixtures persistentes.
