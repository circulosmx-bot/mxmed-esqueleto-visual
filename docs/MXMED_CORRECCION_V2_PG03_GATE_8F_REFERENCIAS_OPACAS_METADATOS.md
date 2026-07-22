# Segunda corrección Gate 8F: referencias opacas de metadatos

## Resultado y clasificación

PASS_ACTIVITY_8_GATE_8F_OPAQUE_METADATA_REFERENCES_CORRECTED

Clasificación UI-0. La corrección se limita al dominio puro, al arnés Gate 8F
y a este documento. No tiene impacto visual ni conecta el resolver al runtime.

## Baseline

El HEAD inicial protegido es
f4b8f0689f603e0c397de5dbad7de80fcdbdb32e. Su parent Gate 8F implementado es
64c698aaa4391b2d7df2f8bdb0fc32f0713919f3 y Gate 8E postvalidado es
3877e26078aec32e2a9e4b0c58d7872b8033a27b. El programa oficial
ee625b0b57c0caa623c4b156cfa2734a6881cf85 permanece sin integrar.

La primera corrección introdujo namespaces, catálogos cerrados de outcome y
reason, y coherencia status/reason. Estas últimas fronteras siguen siendo
válidas e intactas.

## Diagnóstico del bypass

Namespace más un dígito no era una referencia opaca suficiente. El diagnóstico
confirmó la aceptación de ocho bypass:

- operation-AnaPerez1;
- operation-ana_perez1;
- operation-1990_01_20;
- operation-1990.01.20;
- operation-4491234;
- account-AnaPerez1;
- doctor-1990.01.20;
- doctor-4491234.

Esto permitía nombres con un número agregado, variantes de fecha y fragmentos
numéricos de siete dígitos. La causa raíz era validar forma y heurísticas de PII
en vez de exigir una referencia opaca cerrada.

## Contrato opaco vinculante

Los metadatos aceptan exclusivamente:

namespace + separador + SHA-256 hex lowercase

La expresión autoritativa es:

\A(?:<namespaces>)[_:-][a-f0-9]{64}\z

con delimitación final estricta D.

Los namespaces se conservan por contexto:

- operation: operation, op;
- correlation: correlation, corr, request, req;
- actor: account, acct, operator, doctor, system, support, user, profile.

Los separadores permitidos son guion, guion bajo y dos puntos. Después del
separador deben existir exactamente 64 caracteres de a-f o 0-9 en minúsculas.

El sufijo es una referencia producida previamente por un adaptador confiable.
No es un ID de negocio crudo ni texto transformado. Gate 8F no recibe el valor
original, no conoce la PII, no calcula el digest desde PII y no completa hashes.

Se abandonan las heurísticas de PII. La forma cerrada rechaza por construcción:

- nombres y nombres normalizados, incluso con dígitos;
- fechas con guion, guion bajo o punto;
- teléfonos y fragmentos numéricos;
- emails;
- hashes uppercase;
- hashes de 63 o 65 caracteres;
- caracteres non-hex;
- espacios y controles;
- namespaces desconocidos;
- separadores ausentes o dobles;
- texto antes del namespace o después del hash.

No existe trim, lowercase, hash, redacción, enmascaramiento, sustitución,
normalización o truncamiento silencioso. Cada fallo lanza
PatientIdentityDomainException con el código específico y no produce request,
decision ni audit event parcial.

## Superficies preservadas

Sólo cambia el contenido funcional privado de namespacedMetadata en
PatientIdentityPolicy. Permanecen byte-equivalentes:

- identifier y reference;
- operationId, correlationId y actorReference;
- resultState y decisionReason;
- assertStatusReasonCoherence;
- timestamp, canonical, digest, catálogos y toArray;
- PatientIdentityResolutionRequest;
- PatientIdentityAuditEvent;
- PatientIdentityResolutionDecision;
- PatientIdentityResolver y su algoritmo;
- PatientIdentityCandidate y PatientIdentityCandidateSet;
- PatientIdentityEvidence;
- CanonicalPatientId y LegacyPatientReference;
- PatientDuplicateReview;
- PatientIdentityMutationPlan;
- PatientMergePolicy;
- el documento de la primera corrección;
- Plan Maestro y PP-309.

Los siete outcomes, nueve reasons y su mapa de coherencia permanecen intactos.
Los tiers, ambigüedad, contradicciones, creación mínima declarativa y merge
deshabilitado no cambian.

## Compatibilidad canónica opaca

La baseline
/tmp/mxmed-activity08-gate8f-opaque-metadata-correction-preflight-v2/canonical-opaque-baseline.json
usa referencias válidas de 64 hex para operation, correlation y ambos actores.

Se comparan exactamente:

- operation ID y correlation ID;
- actor real y efectivo;
- request fingerprint;
- candidate set digest;
- decision digest;
- audit event ID;
- serialización de request;
- serialización de decision;
- serialización del audit event.

La baseline permanece estable porque la nueva regex sólo elimina inputs que no
cumplían el contrato opaco; no transforma referencias válidas.

## PP-309 y evidencia

PP-309 permanece con una aparición, 4869 bytes, un salto terminal y hash
normalizado:

2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70

PP-310 real permanece ausente y el arnés conserva compatibilidad acumulativa
sin un guard permanente de ausencia.

La evidencia original Gate 8F no fue modificada. La evidencia de la primera
corrección tampoco fue modificada. De esa primera corrección sólo queda
invalidada la afirmación de que un sufijo namespaced libre con dígito bloqueaba
toda PII. Los catálogos outcome/reason y la coherencia status/reason continúan
válidos. La nueva evidencia reemplaza únicamente el contrato del sufijo.

## Fronteras operativas

Gate 8B sigue siendo autoridad de actores. Gate 8E verifica contacto, no
identidad. Persistencia, migración, retención, backfill y rollout permanecen
diferidos a Gate 8G. La resolución no es un encounter Clinical ni reasigna
documentos.

La corrección realiza cero cambios de runtime, rutas, repositorios,
controladores, APIs, schemas o assets. Ejecuta cero SQL, crea cero pacientes y
links, realiza cero merges, modifica cero contactos y documentos Clinical, y
realiza cero escrituras AWS.

## Pruebas y simulaciones

El arnés usa gate8fMetadataReference sólo dentro de la prueba para construir
referencias opacas deterministas. Mantiene toda la regresión funcional previa,
revalida namespaces, separadores, catálogos y coherencia, y ejecuta más de 40
valores inválidos en operation, correlation, actor real y actor efectivo.

También verifica ausencia de objetos parciales, ausencia de transformación,
baseline canónica exacta, pureza de los 14 archivos, Gates 8A a 8F, regresiones,
lint, PP-310 sintética y rollback dry-run.

## Safe return, rollback y Git

Rollback futuro:

git revert --no-edit <opaque_metadata_reference_correction_commit>

Retorno alterno:

git switch -c recovery/activity08-gate8f-opaque-metadata
f4b8f0689f603e0c397de5dbad7de80fcdbdb32e

El commit usa el mensaje
fix(patients): exige referencias opacas gate 8F. Es independiente, aditivo y
reversible. No reescribe historia, no integra el programa y no crea checkpoint.

## Estado final

Gate 8F queda IMPLEMENTED_READY_FOR_FINAL_POSTVALIDATION. Gate 8G permanece
NOT_STARTED; Actividad 8 IN_PROGRESS; Actividad 9 BLOCKED; contador 7/22;
readiness NO_GO_LEGACY_BLOCKERS_PRESENT.
