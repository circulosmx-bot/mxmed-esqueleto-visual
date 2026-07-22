# MXMed PG-03 — Gate 8F: identidad de paciente, duplicados y no-merge

## Resultado

`PASS_ACTIVITY_8_GATE_8F_PATIENT_IDENTITY_DUPLICATES_IMPLEMENTED`

## Resumen

Gate 8F define una autoridad canónica, versionada, determinista y fail-closed
para resolver referencias de paciente, evaluar evidencia opaca, detectar
duplicados potenciales, declarar revisión humana o creación mínima eventual y
mantener cualquier merge deshabilitado. Es dominio puro; no está conectado al
runtime.

## Baseline y preflight

- Rama: `feature/mxmed-pg03-agenda-foundations-v2`.
- HEAD inicial Gate 8E postvalidado: `3877e26078aec32e2a9e4b0c58d7872b8033a27b`.
- Gate 8D postvalidado: `4d44d14abe743bba0424c1a7856b231c4a9a3dc1`.
- Programa oficial sin integrar: `ee625b0b57c0caa623c4b156cfa2734a6881cf85`.
- Preflight: `PASS_ACTIVITY_8_GATE_8F_PREFLIGHT_READY`.
- Bundle: `/tmp/mxmed-activity08-gate8f-preflight-v2/activity08-before-gate8f.bundle`.

El inventario encontró 13 superficies de identidad, cero faltantes, una señal de
creación directa, cero clases PatientIdResolver, cuatro señales de duplicados,
cero señales runtime de merge, 124 atributos de matching, 26 señales de clave
legacy, 15 referencias Clinical patient ID, 94 señales de contacto crudo, 28
lecturas enmascaradas y tres deferimientos Gate 8E→Gate 8F. No se crearon
pacientes, links, merges o datos; no se ejecutó SQL ni AWS.

## Fuentes históricas e interpretación segura

Permanecen byte-equivalentes:

- `docs/clinical/CONTRATO_PATIENT_ID_RESOLVER_V1.md`;
- `docs/clinical/DECISION_IDENTITY_BRIDGE_PATIENT_ID.md`;
- `modules/patients/db/ready_schema.sql`.

`patients_patients.patient_id` sigue siendo la única identidad canónica. Una
legacy key es transitoria y Gate 8F recibe únicamente su hash opaco. La frase
histórica “siempre retorna canonical” describe el final eventual del proceso;
no autoriza a esta capa a escoger o crear pacientes. La creación real y toda
persistencia siguen diferidas.

## Clasificación y contrato

Implementación UI-0 en `Patients\Identity`:

- contract ID `pg03-patient-identity-duplicates`;
- versión `1`;
- canonical source `patients_patients.patient_id`;
- owner `modules/patients`;
- Gate 8B `pg03-server-authoritative-actors`;
- Gate 8E `pg03-public-agenda-otp-privacy`;
- probabilistic matching `false`;
- automatic/manual merge `false`;
- raw legacy/contact/name aceptado `false`;
- clinical encounter `false`.

## Canonical ID y legacy hash

`CanonicalPatientId` exige `^p_[A-Za-z0-9][A-Za-z0-9_.:-]{0,61}$`, máximo 64,
sin espacios, controles, trim/lowercase silencioso o generación interna.

`LegacyPatientReference` acepta sólo `^[a-f0-9]{64}$`, producido por un
adaptador confiable. Gate 8F no recibe la clave raw, no reconstruye nombre/fecha/
sexo y no expone ese hash como identidad pública.

## Evidence, candidates y candidate set

`PatientIdentityEvidence` contiene únicamente referencias de 64 hex para name,
birthdate, phone y email, más sex allow-list. No genera HMAC ni lee secretos.

Cada `PatientIdentityCandidate` contiene canonical ID, evidence, versión ≥1 y
`identity_eligible`. `PatientIdentityCandidateSet` rechaza IDs repetidos, ordena
por canonical ID, conserva decisiones ante permutaciones y produce digest
SHA-256 canónico; nunca deduplica o elige el primer elemento.

## Request y fingerprint

`PatientIdentityResolutionRequest` acepta fuentes exactas `public_verified`,
`private_authenticated`, `legacy_bridge` e inputs `canonical_patient_id` o
`legacy_patient_key_hash`. No permite ambas referencias ni ninguna. Actor real y
efectivo llegan ya resueltos por Gate 8B y el timestamp es RFC3339 explícito.

El fingerprint ordena contract ID, policy, operation, source, input type,
referencia, evidence cerrada, actores y occurred_at. No lee headers, sesión,
claims, query, globals o entorno.

## Algoritmo y niveles

Los tiers exactos en precedencia son:

1. `contact_birthdate_exact` — fuerte;
2. `contact_name_exact` — fuerte;
3. `name_birthdate_sex_exact` — fuerte;
4. `name_birthdate_exact` — débil;
5. `contact_only` — débil;
6. `name_only` — débil;
7. `no_match`.

Todos los candidatos se evalúan y ordenan por tier e ID. No se suman scores ni
se aplica probabilidad. Un fuerte único en el mejor tier produce
`mapped_from_legacy`; un débil produce `review_required`; varios fuertes del
mismo tier producen `ambiguous`; sin señal produce `create_minimal_required`.

## Canonical, contradicciones y ambigüedad

Input canónico existente/elegible produce `already_canonical`; no elegible
produce `review_required`; ausente produce `not_found`. No evalúa evidence legacy
ni crea paciente.

Contacto o nombre coincidente con birthdate distinta, fuerte no elegible,
evidencia fuerte compartida, ID repetido, versión inválida o evidence mal formada
fallan cerrado. Una contradicción nunca se convierte en match. La ambigüedad no
elige arbitrariamente y no crea ni fusiona.

## Create minimal y duplicate review

`create_minimal_required` declara el modo eventual `created_minimal_patient`,
pero `mutation_allowed=false`: no ejecuta creación. `PatientDuplicateReview`
contiene ID determinista, reason code cerrado, candidate IDs ordenados/digests,
tier, request fingerprint y `requires_human_review=true`, sin PII o notas.

## Decision y merge policy

`PatientIdentityResolutionDecision` expone status, reason, canonical ID opcional,
modo eventual, tier, review, digests y auditoría. Mutation y merge son siempre
false.

`PatientMergePolicy` mantiene automatic/manual merge, survivor selection,
source deletion, clinical record reassignment, contact/consent consolidation y
endpoint en false. Cualquier solicitud lanza `patient_merge_disabled` con
`MERGE_DISABLED_PENDING_SEPARATE_APPROVAL_AND_IMPLEMENTATION`.

## Auditoría y privacidad

`PatientIdentityAuditEvent` es readonly, determinista y append-only. Usa
fingerprints/digests de candidate IDs y resolved ID, outcome/tier, actores,
policy, timestamp y flags review/create/merge. No incluye legacy raw/hash como
campo, nombre, birthdate, sex, teléfono, email, contact reference, payload,
notas, cookies, headers, tokens, IP, user agent o datos clínicos.

## Plan transaccional

El plan declara 14 pasos exactos desde `begin_transaction` hasta `commit`:
lock de fingerprint, Gate 8B, idempotencia, carga/validación de candidatos,
evaluación exacta, conflictos, merge deshabilitado, existente o plan mínimo,
delegación futura a Patients, auditoría e idempotencia. Cualquier error exige
rollback. No ejecuta creación, actualización, link, mutación clínica ni SQL.

## Gate 8B, Gate 8E, Gate 8G y Clinical

Gate 8B debe aportar actores server-authoritative. Gate 8E sólo verifica contacto
y flujo; `public_verified` no implica identidad y un contacto puede corresponder
a cero, uno o varios pacientes.

Persistencia, schema, índices, bridge, idempotency table, migración, backfill,
retention, rollout, feature flag y observabilidad quedan
`IDENTITY_PERSISTENCE_MIGRATION_RETENTION_ROLLOUT_DEFERRED_TO_GATE_8G`.

`patientIdentityResolutionIsClinicalEncounter() === false`. Gate 8F no crea
encounters ni modifica/reasigna documentos, expedientes, casos, recetas o notas.

## Fail-closed, determinismo y pureza

IDs, referencias, evidence, candidates, sets, requests y merge inválidos lanzan
`PatientIdentityDomainException` con códigos tipados. Todos los timestamps son
explícitos y todos los IDs/digests derivados usan SHA-256 canónico. Los 14 PHP no
usan persistencia, SQL, controladores, repositorios, red, filesystem, entorno,
sesión, reloj global, aleatoriedad o estado compartido.

## Compatibilidad, limitaciones y cero impacto

Gate 8E, contratos Gate 8A, autoridad Gate 8B, disponibilidad Gate 8C y dominio
Gate 8D permanecen byte-equivalentes. El runtime no usa aún este resolver; no se
consultó base real, no se creó paciente, no se evitó duplicado productivo, no
existe bridge, backfill o matching probabilístico y no se migraron documentos.

Runtime, rutas, SQL, pacientes, links, merges, contactos, documentos clínicos y
AWS permanecen en cero.

## Pruebas, PP-309 y evidencia

Gate 8F cubre catálogo, IDs/referencias, evidence, candidate order, canonical,
tres tiers fuertes, tres débiles, contradicciones, ambigüedad, create-minimal,
merge, privacidad, auditoría, Gate 8B/8E/8G, Clinical, plan y pureza. Las
regresiones Gate 8A–8E, plataforma, Identity y Subscriptions también deben pasar.

PP-309 mide 4869 bytes, tiene un salto terminal y hash normalizado
`2939e9301d8117a2e4d1cd470758b07407d07c794861be0735f68a45ac94fa70`.
El arnés normaliza sólo CR/LF terminal y permanece compatible con PP-310 futura.

La evidencia temporal se entrega en
`/tmp/mxmed-activity08-gate8f-patient-identity-duplicates-v2/` con 10 JSON y tres
textos.

## Safe return, rollback y Git

Rollback futuro: `git revert --no-edit <gate8f_commit>`.
Retorno alterno:
`git switch -c recovery/activity08-gate8f 3877e26078aec32e2a9e4b0c58d7872b8033a27b`.
El dry-run sólo se realiza en worktree detached y debe reconstruir el árbol Gate
8E postvalidado, sin commit.

La implementación se entrega como duodécimo commit independiente, aditivo y
reversible, sin reescribir historia, integrar el programa o crear checkpoint.

## Estado final

- Gate 8F: `IMPLEMENTED_READY_FOR_POSTVALIDATION`.
- Gate 8G: `NOT_STARTED`.
- Actividad 8: `IN_PROGRESS`.
- Actividad 9: `BLOCKED`.
- Contador: `7/22`.
- Readiness: `NO_GO_LEGACY_BLOCKERS_PRESENT`.

No integrar. No crear checkpoint. No iniciar Gate 8G.
