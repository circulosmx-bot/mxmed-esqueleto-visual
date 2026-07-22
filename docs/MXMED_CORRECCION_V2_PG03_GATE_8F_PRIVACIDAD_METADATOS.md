# Corrección Gate 8F: privacidad de metadatos

## Resultado

PASS_ACTIVITY_8_GATE_8F_METADATA_PRIVACY_CORRECTED

Clasificación: UI-0. Esta corrección modifica únicamente dominio puro, su
arnés y este documento. No conecta el resolver al runtime ni cambia
comportamiento productivo.

## Baseline y diagnóstico ejecutable

El baseline protegido es
64c698aaa4391b2d7df2f8bdb0fc32f0713919f3, hijo del Gate 8E postvalidado
3877e26078aec32e2a9e4b0c58d7872b8033a27b. El programa oficial
ee625b0b57c0caa623c4b156cfa2734a6881cf85 permanece sin integrar.

El diagnóstico confirmó cuatro exposiciones:

- AnaPerez podía aceptarse como operation_id;
- AnaPerez podía aceptarse como actor_real_id;
- female podía aceptarse como outcome_code;
- AnaPerez podía aceptarse como reason_code.

Por ello las fronteras de request, auditoría y razón de decisión no fallaban
cerradas para metadatos que podían transportar PII o palabras libres.

## Causa raíz y corrección

La causa fue reutilizar PatientIdentityPolicy::identifier(...), un validador
genérico, para contextos con semántica y privacidad distintas. Ese método se
preserva byte-equivalente y no se endurece globalmente.

Se agregaron seis fronteras explícitas:

- operationId;
- correlationId;
- actorReference;
- resultState;
- decisionReason;
- assertStatusReasonCoherence.

Operation admite solamente los namespaces operation y op. Correlation admite
correlation, corr, request y req. Actor admite account, acct, operator, doctor,
system, support, user y profile.

Los namespaces requieren un separador de guion, guion bajo o dos puntos, cuerpo
ASCII opaco, longitud total máxima de 128 y al menos un dígito después del
namespace. Los valores se conservan exactamente; no se aplica trim, lowercase,
truncamiento, enmascaramiento, sustitución ni redacción.

Se rechazan espacios, controles, arroba, signo más, fechas con forma
YYYY-MM-DD y secuencias de ocho o más dígitos. Así, un namespace no convierte
un nombre, fecha, email o teléfono en un identificador admisible.

## Catálogos cerrados

Audit outcome acepta exclusivamente:

- already_canonical;
- mapped_from_legacy;
- create_minimal_required;
- review_required;
- ambiguous;
- not_found;
- invalid_candidate_set.

Decision reason acepta exclusivamente:

- already_canonical;
- canonical_patient_not_found;
- candidate_not_eligible;
- unique_strong_identity_match;
- multiple_strong_candidates;
- identity_evidence_conflict;
- weak_identity_evidence;
- no_identity_candidate;
- invalid_candidate_set.

Además, cada status tiene un conjunto cerrado de razones coherentes. Una razón
válida con un status incorrecto falla con identity_status_reason_mismatch;
nunca se corrige ni reemplaza.

Los códigos específicos son invalid_operation_id, invalid_correlation_id,
invalid_actor, invalid_identity_outcome, invalid_decision_reason e
identity_status_reason_mismatch. Todos se entregan mediante
PatientIdentityDomainException y ningún rechazo produce un request, decision o
audit event parcial.

## Compatibilidad determinista

La captura anterior y posterior usa:

- operation: operation-gate8f;
- correlation: correlation-gate8f;
- actor real: account-gate8f;
- actor efectivo: operator-gate8f;
- timestamp: 2026-07-21T11:00:00-06:00;
- decisión equivalente: operation-legacy-priority-01.

Permanecen estables:

- canonical request fingerprint:
  3c82cc7861cd1bb1cc224ae5fa5f63efa29e8e7128ddad3888275e99a6af9cad;
- candidate set digest:
  7630c10fb9a4838e592774c5b4aac5152bbc2cf3a6aebf5de77d252041cf0969;
- decision digest:
  d52d50fc8abb02ac03d6770156d27d9ce859748b19c2976e0b07057b682b4fa4;
- audit event ID:
  bc0a2db7648fa9548b1504eab112f06296d01c06e3bd4dcb3a1eb27260ba3e4a;
- serializaciones de request, decision y audit event.

No se digieren ni transforman retroactivamente los IDs ya válidos.

## Dominio preservado

PatientIdentityResolver y su algoritmo permanecen byte-equivalentes. También
permanecen intactos los tres tiers fuertes, los tres tiers débiles,
PatientIdentityCandidateSet, PatientIdentityEvidence, PatientDuplicateReview,
PatientMergePolicy y PatientIdentityMutationPlan.

La creación mínima sigue siendo sólo un plan. El merge sigue deshabilitado.
No se selecciona el primer candidato ni se incorpora matching probabilístico.

## PP-309 y evidencia histórica

docs/PLAN_MAESTRO_MXMED.md y PP-309 permanecen intactos. PP-309 conserva una
aparición, 4869 bytes, un salto terminal y el hash SHA-256 normalizado:

2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70

El arnés conserva la regex acumulativa y sigue siendo compatible con una
PP-310 futura sin establecer un guard permanente de ausencia.

La evidencia original de Gate 8F en
/tmp/mxmed-activity08-gate8f-patient-identity-duplicates-v2/ no se modifica,
borra, regenera ni sustituye. Quedan invalidadas únicamente sus afirmaciones
absolutas sobre bloqueo de PII en metadatos. El resto se preserva como
evidencia histórica. La nueva evidencia correctiva reemplaza solamente esas
afirmaciones.

## Fronteras

Gate 8B continúa siendo la autoridad de actores. Gate 8E continúa verificando
contacto y flujo público, no identidad. Persistencia, migración, retención,
backfill y rollout continúan diferidos a Gate 8G. La resolución de identidad no
es un encounter Clinical y no puede reasignar documentos.

La corrección realiza cero cambios de runtime, rutas, SQL, repositorios,
controladores, APIs, schemas, pacientes, vínculos duplicados, merges, contactos,
documentos Clinical o AWS.

## Pruebas y simulaciones

El arnés Gate 8F conserva toda la regresión funcional y agrega casos válidos
para namespaces y catálogos, más de 30 rechazos distribuidos entre operation,
correlation, actor real, actor efectivo, outcome, reason e incoherencia
status/reason. Verifica fail-closed, ausencia de objetos parciales y ausencia de
redacción silenciosa.

La validación incluye Gates 8A a 8F, regresiones de plataforma, identidad y
suscripciones, lint de los 14 archivos de identidad, pureza estática, PP-310
sintética y rollback dry-run.

## Safe return, rollback y Git

Rollback futuro:

git revert --no-edit <gate8f_metadata_privacy_correction_commit>

Retorno alterno:

git switch -c recovery/activity08-gate8f-metadata-privacy
64c698aaa4391b2d7df2f8bdb0fc32f0713919f3

El rollback se valida sólo en un worktree detached. El commit es independiente,
aditivo y reversible, con mensaje
fix(patients): bloquea PII en metadatos gate 8F. No se reescribe historial, no
se integra el programa y no se crea checkpoint.

## Estado final

Gate 8F queda IMPLEMENTED_READY_FOR_FINAL_POSTVALIDATION. Gate 8G permanece
NOT_STARTED; Actividad 8 IN_PROGRESS; Actividad 9 BLOCKED; contador 7/22;
readiness NO_GO_LEGACY_BLOCKERS_PRESENT.
