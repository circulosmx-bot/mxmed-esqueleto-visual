# CLIN-AUTH01 — clinical role and professional-authority audit

Status: **COMPLETE AUDIT / REMEDIATION REQUIRED**

Containment status (2026-10-15): the anonymous legacy route family and the reviewed unverified prescription, physician-consent, and versioned professional-document transitions were contained by `CLIN-AUTH02A_URGENT_CONTAINMENT.md`. The broader professional-operation contract remains pending CLIN-AUTH02B.

Audited source: `8951aeac5d8b46bf3ea91b54aeb827325d2e7b77`

Audit date: 2026-10-15

Mode: source inspection, existing isolated tests, in-memory synthetic role probes, and anonymous read-only requests with a nonexistent synthetic patient ID. No clinical or Identity data was changed.

## Executive summary

The current clinical boundary does not distinguish authenticated account, profile team membership, commercial entitlement, and qualified professional authority. `CanonicalClinicalIdentityResolver` accepts any one active `owner`, `administrator`, or `collaborator` membership and then checks only the plan capability. It never proves that the account is the licensed professional represented by the profile or that the actor has a separately delegated clinical operation.

Two **CRITICAL** finding families are confirmed:

1. Legacy history, physical-exam, and clinical-case routes omit Identity and physician/patient scope. An anonymous request reaches patient existence/data logic; case-active returns `200` even for a nonexistent synthetic patient. With a known valid identifier, the same control flow reads or mutates clinical state without an authentication guard.
2. A non-professional profile member admitted by the resolver can create, sign, and emit Prescription V2 and can supply the doctor-side signature needed to finalize informed consent. The signature contracts bind content, document version, profile, and account correctly, but the bound account is never proven to be the physician. Possession or drawing of a signature artifact is therefore treated as signer authority after membership admission.

The newer encounter, longitudinal, document, binary, and QR paths contain valuable safeguards: canonical session validation, active membership, exact profile route scope, active physician/patient link, encounter ownership, creator-bound drafts, version/idempotency checks, content fingerprints, one-time QR state, document lineage, and terminal immutability. These controls limit cross-patient, cross-physician, replay, stale-signature, and rewrite attacks. They do not cure the missing professional-authority decision.

The smallest safe correction is to close the unguarded legacy routes first, then add one fail-closed professional-operation decision to the existing canonical resolver composition. Commercial capability remains a prerequisite, never evidence of medical qualification. Prescription routes must request `prescriptions`, and final professional acts must revalidate an account-to-professional authority binding at the server operation boundary.

## Existing authority architecture

The actual request chain is:

```text
__Host-mxmed_session
  -> CanonicalHttpSessionResolver
  -> AccountMembershipRepository::activeForAccount
  -> CanonicalDoctorMembershipPolicy::select
       exactly one active profile membership
       role in owner|administrator|collaborator
  -> subscription read model
  -> ExistingCapabilityAuthorizationService
       clinical_record or prescriptions
  -> clinical_request_identity / clinical_require_doctor_context
  -> patient link / encounter owner / document-specific guards
  -> writer
```

The missing step is a server-owned decision such as `ACTOR_CLINICALLY_AUTHORIZED_FOR(operation, doctor_id)`. Neither ownership nor profile membership currently carries an account-level professional credential or a bounded delegated clinical capability.

Key evidence:

- Membership admission includes all three profile roles and no professional check: `modules/identity/http/CanonicalClinicalIdentityResolver.php:37-50,64-84`.
- Role codes are only `owner`, `administrator`, and `collaborator`: `modules/identity/contracts/MembershipRole.php:10-27`.
- Clinical capability selection is route-name based: `api/clinical/index.php:481-491`.
- New clinical scopes use active patient links and exact encounter doctor ownership: `api/clinical/index.php:439-458,494-524,3787-3798`.
- Printable identity prefers verified profile credentials when present but accepts profile-recorded license data and, for some outputs, no verified credential: `api/_lib/clinical_portable_order.php:35-89,120-129`.
- IW01 platform governance is separate and grants no default clinical access: `docs/product-completion/iw01-internal-governance-foundation.md:1-25`.
- GOV03A changes ownership governance without granting clinical authority: `docs/identity/IDENTITY-GOV03A-governance-runtime.md:5-21`.

## Role and professional-authority result

| Synthetic actor | Resolver/model result | Clinical implication |
|---|---|---|
| Verified professional, active membership, required plan capability | Allowed | Expected positive path, but the resolver does not inspect the verification fact. |
| Owner without established professional authority | Allowed | Can reach all capability-matched clinical operations. Ownership substitutes for access but must not substitute for qualification. |
| Administrator without professional authority | Allowed | Same clinical request context as owner; no downstream role restriction was found. |
| Collaborator without professional authority | Allowed | Same clinical request context as owner; this is explicitly proven by the existing resolver test. |
| Assistant with only approved preparatory functions | Not representable | The current clinical plane has no preparatory-operation grant; membership plus plan grants too much, absence of membership grants nothing. |
| Plan entitlement without an active profile membership | Denied `403` | Membership remains required. |
| Cross-physician account | Denied on protected paths | Exact route doctor, patient link, encounter owner, document owner, or creator checks fail closed. Unguarded legacy routes bypass this protection. |
| Revoked or suspended member | Denied `403` | `activeForAccount` and membership policy exclude it. |
| Expired, revoked, or invalid session | Denied `401` | Canonical session resolution remains authoritative on protected paths. |

The historical A1 RBAC source retained in Git history (`08b224`, `docs/fuentes-historicas/mxmed-2025/03-modulo-a1-roles-permisos-seguridad-rbac.pdf`) treated diagnosis, evolution notes, prescription creation/edit/signature, consent creation/edit, and hospital documents as non-delegable. It allowed only explicitly authorized basic/non-sensitive capture and issued-document reprint with audit. Current product guidance likewise states that practice operators have no Clinical, case, document, history, or prescription access by default (`MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325.md:336-361`). The runtime conflicts with both boundaries.

## Operation-by-operation access matrix

Legend: **I** = canonical Identity session; **M** = exactly one active profile membership; **C** = commercial capability; **P** = active physician/patient link; **E** = encounter owned by resolved doctor; **A** = same creating account; **F** = content/version/artifact fingerprint; **Q** = one-time QR session. `none` means the final server chain contains no such guard.

| ENDPOINT_OR_ACTION | HTTP_METHOD | BACKEND_HANDLER | ACTUAL_CALL_CHAIN | IDENTITY_REQUIRED | PROFILE_MEMBERSHIP_REQUIRED | ROLE_RESTRICTION | PROFESSIONAL_AUTHORITY_REQUIRED | COMMERCIAL_ENTITLEMENT_REQUIRED | PATIENT_SCOPE | ENCOUNTER_SCOPE | SIGNATURE_REQUIREMENT | FINAL_SERVER_GUARD | ACTUAL_OUTCOME | RISK_LEVEL | EVIDENCE_FILE_AND_LINE |
|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|---|
| READ legacy history | GET `patients/{patient_id}/history` | `clinical_history_fetch_draft` | route -> patient exists -> fetch by patient ID | No | No | none | No | No | none | n/a | none | patient existence only | Anonymous caller can read a valid patient's draft history | **CRITICAL** | `api/clinical/index.php:4940-5018` |
| SAVE_DRAFT legacy history | PUT same | `clinical_history_upsert_draft` | route -> payload -> rollout gate -> upsert by patient ID | No | No | none | No | No | none | n/a | none | payload shape/write-window only | Anonymous caller can replace a valid patient's history draft | **CRITICAL** | `api/clinical/index.php:5021-5105` |
| READ legacy physical exam | GET `patients/{patient_id}/physical-exam` | `clinical_record_entry_fetch_draft` | route -> patient exists -> fetch by patient ID/type | No | No | none | No | No | none | n/a | none | patient existence only | Anonymous caller can read a valid patient's physical-exam draft | **CRITICAL** | `api/clinical/index.php:5108-5186` |
| PREPARATORY_CAPTURE legacy physical exam | PUT same | `clinical_record_entry_upsert_draft` | route -> payload -> rollout gate -> upsert | No | No | none | No | No | none | n/a | none | payload shape/write-window only | Anonymous caller can replace physical-exam draft | **CRITICAL** | `api/clinical/index.php:5189-5273` |
| READ cases/active/items | GET `patients/{id}/cases[/active]`, `cases/{id}/items` | case fetch helpers | route -> direct case queries | No | No | none | No | No | none | none | none | numeric/patient ID only | Anonymous clinical case metadata/items are reachable | **CRITICAL** | `api/clinical/index.php:6125-6172,6761-6832,7017-7103` |
| EDIT cases | POST/PATCH `patients/{id}/cases`, `cases/{id}`, `cases/{id}/activate`, `cases/{id}/items` | case create/rename/activate/assign helpers | route -> direct mutation | No | No | none | No | No | none | none | none | input/uniqueness only | Anonymous caller can create, rename, activate, or assign case items | **HIGH** | `api/clinical/index.php:6835-6876,6879-7014,7106-7201` |
| READ timeline/longitudinal history | GET `patients/{id}/timeline`, `longitudinal-summary`, `longitudinal-history` | timeline/summary queries | I -> M -> `clinical_record` C -> P | Yes | Yes | owner/admin/collab all accepted | No | `clinical_record` | P | doctor-filtered encounters | none | P + doctor-filtered SQL | Scoped read succeeds for every admitted role | **HIGH** | `api/clinical/index.php:4465-4588,6220-6279,6294-6337` |
| READ vital trends/references | GET `patients/{id}/longitudinal/measurements` or `vital-references` | `ClinicalMeasurementTrends` / reference resolver | I -> M -> `clinical_record` C -> P | Yes | Yes | none among three roles | No | `clinical_record` | P | doctor-filtered observations | none | P | Scoped, but no professional/delegated-read distinction | **MEDIUM** | `api/clinical/index.php:4397-4423,6175-6201` |
| START consultation | POST `patients/{id}/encounters` | encounter create / integrity service | I -> M -> `clinical_record` C -> P -> appointment match | Yes | Yes | none among three roles | No | `clinical_record` | P | optional appointment doctor/patient match | none | P + appointment scope + idempotency | Owner/admin/collab can start consultation | **HIGH** | `api/clinical/index.php:6445-6493,6565-6757` |
| CONTINUE consultation | GET `patients/{id}/encounters/active`, `encounters/{key}` | encounter readers | I -> M -> `clinical_record` C -> P/E | Yes | Yes | none among three roles | No | `clinical_record` | P | E | none | P + E | Any admitted role can resume/read | **HIGH** | `api/clinical/index.php:6347-6439,7732-7775` |
| SAVE_DRAFT consultation reason/evolution | PUT `encounters/{key}/sections/reason_evolution` | `ClinicalEncounterIntegrityService::saveSection` | I -> M -> C -> E/P -> row version | Yes | Yes | none | No | `clinical_record` | P | E, open lifecycle | none | E/P + version | Any admitted role can author clinical narrative | **HIGH** | `api/clinical/index.php:7300-7308`; `api/_lib/clinical_encounter_integrity.php:238-258` |
| DIAGNOSIS / longitudinal problems | POST/PATCH/action `patients/{id}/longitudinal/problems...` | `ClinicalLongitudinalProblems::mutate` | I -> M -> C -> service P -> mutation | Yes | Yes | none | No | `clinical_record` | P in service | promotion may bind encounter; other changes patient-level | none | P + idempotency/state | Any admitted role can create/change diagnosis/problem state | **HIGH** | `api/clinical/index.php:4323-4358`; `api/_lib/clinical_longitudinal_problems.php:35-76` |
| SAVE_DRAFT assessment | PUT `encounters/{key}/sections/assessment` | `saveSection` | I -> M -> C -> E/P -> version | Yes | Yes | none | No | `clinical_record` | P | E | none | E/P + version | Any admitted role can author assessment | **HIGH** | `api/clinical/index.php:7300-7308` |
| SAVE_DRAFT plan | PUT `encounters/{key}/sections/plan` | `saveSection` | I -> M -> C -> E/P -> version | Yes | Yes | none | No | `clinical_record` | P | E | none | E/P + version | Any admitted role can author treatment plan | **HIGH** | `api/clinical/index.php:7300-7308` |
| PREPARATORY_CAPTURE vitals | POST/PATCH/reuse/void `encounters/{key}/observations...` | integrity observation commands | I -> M -> C -> E/P -> provenance/version | Yes | Yes | none | No explicit prep grant | `clinical_record` | P | E | none | E/P + version/idempotency | Safe data mechanics; delegation scope is not modeled | **MEDIUM** | `api/clinical/index.php:7311-7366` |
| FINALIZE consultation/note | POST `encounters/{key}/finalize` | integrity `finalize` + final note | I -> M -> C -> E/P -> terminal transaction | Yes | Yes | none | No | `clinical_record` | P | E, open only | none | E/P + immutable first close | Any admitted role can finalize the professional note | **HIGH** | `api/clinical/index.php:7205-7284`; `api/_lib/clinical_encounter_integrity.php:238-258` |
| AMEND finalized encounter | POST `encounters/{key}/amendments` | `appendAmendment` | I -> M -> C -> E/P -> idempotent append | Yes | Yes | none | No | `clinical_record` | P | E | none | append-only/versioned correction | Any admitted role can author clinical amendment | **HIGH** | `api/clinical/index.php:7369-7379` |
| PREPARATORY_CAPTURE note/mobile token | POST `note-capture-tokens`; bearer phone actions | note-capture token service | desktop I/M/C/P -> scoped short token -> consume | At creation/desktop consume | Yes | none | No explicit prep grant | `clinical_record` | P | optional bound encounter/document | artifact/token binding by context | TTL, one-time state, exact desktop context | Device bearer is bounded; initiating member is still overbroad | **MEDIUM** | `api/clinical/index.php:7982-8216,8304-8599` |
| CREATE clinical document/attachment | POST `encounters/{key}/documents` or `doctors/{doctor}/patients/{patient}/documents` | document/multipart writers | I -> M -> C -> P/E -> type/operation policy -> storage | Yes | Yes | none | No | `clinical_record` | P | E when encounter-bound | type-specific only | P/E + MIME/hash/idempotency | All admitted roles can create clinical documents/attachments | **HIGH** | `api/clinical/index.php:7382-7556,9742-9852`; `api/_lib/clinical_multipart_document_service.php:159-334` |
| SIGN/EMIT professional clinical documents | POST doctor/patient document writers and type-specific QR sessions | interconsulta, certificado, nota, multimedia, free-document, alta, informe, responsiva writers/bindings | I -> M -> `clinical_record` C -> P -> type-specific F/Q -> generated document | Yes for desktop actor | Yes | none | **No account-level qualification check** | `clinical_record` | P | type-specific optional E/appointment | type-specific content/artifact binding | F/Q and profile/account context | Signature integrity is enforced, but an admitted delegate can occupy doctor authority | **CRITICAL** | `api/clinical/index.php:9330-9636,9742-10123`; `api/_lib/clinical_interconsulta_qr.php:111-270`; `api/_lib/clinical_free_document_binding.php:50-91` |
| EMIT medical/lab/imaging order | POST encounter/patient documents, order type | V1 document writer / portable snapshot | I -> M -> `clinical_record` C -> P/E -> operation policy | Yes | Yes | none | No | `clinical_record` | P | open E for canonical encounter order | none required | server-derived class, E/P, open state | Any admitted role can issue an order | **HIGH** | `api/_lib/clinical_encounter_integrity.php:64-85,238-258`; `api/_lib/clinical_portable_order.php:120-129` |
| SAVE diagnostic result | POST document result type | V1 result writer | I -> M -> C -> P/E -> originating order/provenance | Yes | Yes | none | No | `clinical_record` | P | E and valid originating order | none | result context and lineage | Any admitted role can add results despite good lineage checks | **HIGH** | `api/_lib/clinical_encounter_integrity.php:238-258`; `api/clinical/index.php:7382-7728` |
| AMEND/REPLACE clinical document | POST `documents/{token}/amendments`; PATCH/replace scoped doctor routes | revision services | I -> M -> C -> P/E -> operation policy | Yes | Yes | none | No | `clinical_record` | P | same E; open/closed, never voided | signed rewrite blocked; revision instead | lineage + reason + idempotency | Any admitted role may amend eligible documents | **HIGH** | `api/clinical/index.php:10544-10610`; `api/_lib/clinical_encounter_integrity.php:98-102,238-258` |
| READ prescription context/history/drafts | GET context or `doctors/{doctor}/patients/{patient}/prescriptions/history` or `/drafts` | prescription context/history | I -> M -> C -> P; history/drafts also A | Yes | Yes | none | No | context=`prescriptions`; V2 history/drafts=`clinical_record` | P | optional E | none | P + A for V2 history/drafts | Scoped but inconsistent entitlement and no delegated-read policy | **MEDIUM** | `api/clinical/index.php:4223-4243,9127-9135,9638-9677` |
| SAVE_DRAFT Prescription V2 | POST `doctors/{doctor}/patients/{patient}/prescriptions` intent `draft` | `clinical_prescription_v2_write` | I -> M -> **`clinical_record` C** -> exact doctor/P -> A | Yes | Yes | none | No | **`clinical_record`, not `prescriptions`** | P | optional exact E | none | P + actor/patient/encounter normalization | Owner/admin/collab can prepare independent draft; capability mismatch confirmed | **HIGH** | `api/clinical/index.php:9127-9135,9638-9650,9679-9726`; `api/_lib/clinical_prescription_v2.php:431-577,669-715` |
| EDIT/AMEND Prescription V2 draft | POST same intent `draft` with `draft_ref` | `clinical_prescription_v2_write` | prior chain -> A/version | Yes | Yes | none | No | `clinical_record` | P | immutable optional E | prior signature kept only if fingerprint unchanged | A + version/fingerprint | Creator isolation works; creator can be non-professional | **HIGH** | `api/_lib/clinical_prescription_v2.php:669-715,739-773` |
| SIGN Prescription V2 locally/registered | POST same intent `apply_signature` | binding classifier | prior chain -> A -> F -> registered profile checksum if used | Yes | Yes | none | **No** | `clinical_record` | P | optional exact E | valid artifact + F; registered checksum by profile | doctor ID + account ID, not professional qualification | Delegate can sign as “Médico responsable” | **CRITICAL** | `api/_lib/clinical_prescription_v2.php:716-733`; `api/_lib/clinical_prescription_binding.php:158-211` |
| SIGN Prescription V2 by QR | POST `prescription-qr-sessions...` then apply | QR session + binding claim | creator I/M/`prescriptions` C/P/A -> token Q/F -> apply via V2 route | Creator yes; phone bearer token | Yes at creation/apply | none | **No** | QR creation=`prescriptions`; apply=`clinical_record` | P | document context | Q + F + actor/profile/document/version | Q + F + A | Exact actor and document preserved; actor may be delegate | **CRITICAL** | `api/_lib/clinical_prescription_qr.php:111-175,177-261`; `api/_lib/clinical_prescription_binding.php:192-209` |
| EMIT Prescription V2 | POST same intent `finalize` | `clinical_prescription_v2_write` | I -> M -> `clinical_record` C -> P/A -> valid F -> generated | Yes | Yes | none | **No** | **`clinical_record`** | P | optional E | valid bound doctor signature | F/version/A; no qualified-signer guard | Delegate can emit a prescription under profile doctor identity | **CRITICAL** | `api/_lib/clinical_prescription_v2.php:734-779`; `api/clinical/index.php:9127-9135` |
| VOID issued prescription | POST `.../prescriptions/{uuid}/void` | lifecycle void | I -> M -> `clinical_record` C -> P -> A -> status | Yes | Yes | none | No | `clinical_record` | P | source context | none new | A + generated/signed state + atomic lifecycle | Original creator can void; creator may be delegate | **HIGH** | `api/clinical/index.php:9663-9672`; `api/_lib/clinical_prescription_lifecycle.php:61-113` |
| REISSUE prescription | POST `.../prescriptions/{uuid}/reissue`, then sign/finalize | lifecycle reissue + V2 write | same -> A -> draft lineage -> new F -> atomic original void | Yes | Yes | none | No | `clinical_record` | P | copied source context | new signature required before finalize | A + lineage + new binding | Good lineage; no professional reauthorization | **CRITICAL** | `api/_lib/clinical_prescription_lifecycle.php:116-197`; `api/_lib/clinical_prescription_v2.php:774-779` |
| SAVE_DRAFT informed consent | POST `doctors/{doctor}/patients/{patient}/documents`, consent status `draft` | `clinical_consent_create` | I -> M -> `clinical_record` C -> P -> A/version | Yes | Yes | none | No | `clinical_record` | P | optional exact E/appointment | none for draft | P + A + version + attachment scope | Any admitted role can author consent draft | **HIGH** | `api/_lib/clinical_consent_write.php:107-153,223-272` |
| SIGN/FINALIZE informed consent | same, status `granted` | consent signature classifier/emission assertion | prior chain -> patient F + doctor F -> generated | Yes for desktop actor | Yes | none | **No** | `clinical_record` | P | optional exact E | patient and “doctor” bound signatures | F/QR provenance + A; authority is doctor ID + account ID | Delegate can provide doctor signature and finalize | **CRITICAL** | `api/_lib/clinical_consent_write.php:7-40,325-370`; `api/_lib/clinical_consent_signature_binding.php:134-214` |
| READ private attachment binary | GET `documents/{token}/binary/{variant}` | `ClinicalPrivateBinaryRetrieval` | I -> M -> `clinical_record` C -> manifest/resource scope | Yes | Yes | none | No separate sensitive-read grant | `clinical_record` | repository verifies physician ownership | document-bound | n/a | manifest/hash/doctor scope | Protected from anonymous/cross-doctor access; all admitted roles can read | **HIGH** | `api/clinical/index.php:10625-10661`; `api/_lib/clinical_private_binary_retrieval.php:21-105` |

## Prescription V2 and signature conclusions

The following protections work and must remain unchanged:

- Prescription contract normalization rejects client actor, patient, and encounter mismatches (`clinical_prescription_v2.php:431-450`).
- Draft ownership and optimistic versioning bind edits to the creating account (`clinical_prescription_v2.php:691-715`).
- Signatures bind document UUID, version, content fingerprint, artifact digest, profile doctor, and actor account (`clinical_prescription_binding.php:158-211`).
- QR handoff preserves the exact creator, patient, document, version, fingerprint, review HTML, TTL, and one-time claim (`clinical_prescription_qr.php:33-108,111-261`). The phone does not need the desktop Identity cookie; possession of the short-lived token is the bounded handoff authority.
- Generic document edit/replication paths reject prescription types, and issued documents use lifecycle/revision rules rather than in-place generic rewrite (`api/clinical/index.php:10869-10878,11680-11689`).
- Void/reissue is creator-scoped and atomically preserves lineage (`clinical_prescription_lifecycle.php:61-197`).

The signer flaw is independent of these safeguards. `physician_snapshot.user_id` is the membership account, while name and license are taken from the physician profile. The binding authority is `doctor_id|account_id`; no code proves that this account owns the verified professional credential. A local canvas signature or a QR drawing therefore proves artifact possession and document consent, not medical qualification.

The main patient Prescription V2 router also selects the generic `doctors/{doctor_id}/documents` authority string before it recognizes the prescription subroute. It consequently requests `clinical_record`; only template and QR-session routes contain “prescription” and request `prescriptions`. Whether a marketed plan currently exposes `clinical_record=true, prescriptions=false` is not established by this audit, but the backend mismatch is confirmed and must fail closed regardless of plan catalog combinations.

## Findings

### Critical

- **C-01 — anonymous clinical read/write routes.** History, physical exam, and case routes lack Identity, membership, capability, patient link, and encounter/doctor scope. A read-only anonymous normal-local probe with `clin-auth01-nonexistent-patient` returned `404` from history/physical exam/case list, `200` from case-active, while the protected timeline returned `401`. This proves requests reach data handlers before authentication; no real patient identifier or data was used.
- **C-02 — unqualified signer/emitter admitted.** Owner, administrator, and collaborator are all selectable; Prescription V2 and informed-consent finalization bind the selected account without proving it is the treating/qualified professional. The full control flow permits professional signature and emission after a valid membership and commercial capability.

### High

- **H-01 — coarse membership enables clinical authorship.** Consultation start/finalize, diagnosis/problems, assessment, plan, notes, orders, results, documents, amendments, void, and reissue contain no role or professional-operation decision after the resolver.
- **H-02 — Prescription V2 entitlement mismatch.** Patient prescription draft/edit/sign/finalize/void/reissue routes request `clinical_record`, not `prescriptions`.
- **H-03 — professional identity is presentation data, not an authority.** Verified profile credentials are preferred when present, but profile-recorded license values remain accepted and no account-to-credential relationship is required.

### Medium

- **M-01 — preparatory delegation is not representable.** The system cannot grant only vitals/basic capture/draft preparation; a team membership receives the whole capability-matched clinical surface.
- **M-02 — profile signature management uses the older PHP `session_scope`.** `GallerySessionScope` accepts doctor/owner-like session aliases and does not call canonical Identity or professional authority. Normal-local reachability under the current Identity-only browser session was not proven in this audit.
- **M-03 — actor coverage gap.** Existing tests prove revoked/multiple membership denial, scope and signature integrity, but do not exercise owner/admin/collaborator against each professional operation with distinct professional-eligibility states.

### Low

- **L-01 — misleading authority labels.** QR and document payloads label the admitted account `doctor` / “Médico responsable” even when its membership role is administrator or collaborator.

## Safeguards confirmed

- Missing, ambiguous, wrong-scope, revoked, and suspended memberships fail closed.
- Protected routes derive profile doctor and actor account from canonical Identity, not request body.
- Exact route doctor, active patient link, and encounter owner checks block protected cross-profile access.
- Document class is server-derived; spoofed class is rejected.
- Closed/voided encounter policies, signed-document rewrite blocks, append-only amendments, idempotency, and optimistic versions preserve clinical history.
- Prescription, consent, and QR fingerprints become stale after content, signer, document, version, or artifact changes; QR claims are one-time.
- Private binary reads validate server-owned manifests and physician scope.

These safeguards are necessary but do not confer professional authority.

## Test evidence

Executed without persistent fixture mutation:

| Check | Result |
|---|---|
| `php modules/identity/tests/CanonicalClinicalIdentityResolverTest.php` | PASS |
| In-memory `CanonicalDoctorMembershipPolicy` probe for owner/admin/collaborator/revoked/suspended | PASS; all three active roles selected, revoked/suspended rejected |
| Anonymous HTTPS probe using nonexistent synthetic patient ID | history `404`, physical exam `404`, cases `404`, cases-active `200`, protected timeline `401` |
| `php modules/clinical/qa/rec_reg02_contract.php` | PASS |
| `php modules/clinical/qa/rec_reg03_contract.php` | PASS |
| `node modules/clinical/qa/rec_reg02_binding.test.mjs` | PASS |
| `php modules/clinical/qa/consent_signature_binding_contract.php` | PASS |
| `node modules/clinical/qa/consent_signature_binding_vectors.mjs` | PASS |
| `php modules/clinical/qa/encounter_integrity_pure_test.php` | PASS, 56 checks |
| `php modules/media/tests/GallerySessionScopeTest.php` | FAIL at unrelated loader-version assertion (`index.html` query); all scope assertions before it passed |

No test authenticated to QA05, changed its fixtures, wrote normal-local clinical data, or used a real patient ID.

## Unverified risks and decisions required

Unverified:

- whether a current commercial plan can expose `clinical_record` while denying `prescriptions`;
- whether the legacy physician-signature management PHP session is reachable from the canonical normal-local Identity-only flow;
- the practical discoverability of patient/case identifiers outside already authorized UI responses;
- every specialized clinical document's signer semantics beyond the reviewed Prescription V2 and informed-consent paths.

Director decisions required before a broader implementation:

1. Define the account-to-professional authority source: treating physician identity, verified credential state, lifecycle state, permitted jurisdiction/specialty constraints if applicable, and substitution/coverage rules.
2. Decide which preparatory actions may be explicitly delegated: sensitive read, vital capture, history capture, draft notes, prescription draft preparation, order preparation, and consent preparation.
3. Confirm which acts remain professional-only: diagnosis/problem validation, assessment/plan, consultation finalization, clinical amendment, order emission, prescription sign/emit/void/reissue, doctor-side consent signature/finalization, and signed medical documents.
4. Decide whether sensitive clinical reads require a separate delegated grant or only a clinical-team membership plus patient assignment.

## Minimum fail-closed remediation plan

1. **Immediate containment:** route `history`, `physical-exam`, and every case read/write through `clinical_require_doctor_context('clinical_record')` and exact active physician/patient scope; resolve case ID to patient before authorization. Add anonymous, no-membership, cross-profile, revoked-session, and valid-scope HTTP regressions.
2. **One authority extension:** extend the existing canonical clinical context with a server-derived professional-operation decision. Reuse Identity memberships, verified profile credential sources, and existing capability authorization; do not create another account, role catalog, or permissions engine.
3. **Explicit operation policy:** map READ, PREPARATORY_CAPTURE, SAVE_DRAFT, EDIT, AMEND, FINALIZE, SIGN, EMIT, VOID, and REISSUE to professional-only or explicitly delegated capabilities. Default unknown operations to deny.
4. **Prescription correction:** make every patient Prescription V2 route require `prescriptions`; at `apply_signature`, `finalize`, `void`, and `reissue`, revalidate the actual account's professional signer authority. Keep REC-REG02/03/04C fingerprints and existing QR/document bindings unchanged.
5. **Consent and professional documents:** require the same professional authority for doctor-side signature and final emission. Patient/representative signature authority remains separate.
6. **Regression matrix:** exercise verified professional, owner without qualification, administrator, collaborator, bounded assistant, plan-only user, cross-profile user, revoked/suspended member, and invalid session for every operation class.

## Unchanged-data attestation

```text
NORMAL_LOCAL_DATA_UNCHANGED=true
QA05_DATA_UNCHANGED=true
MEMBERSHIPS_UNCHANGED=true
GOVERNANCE_UNCHANGED=true
RUNTIME_AUTHORIZATION_CHANGED=false
SQL_MIGRATION_APPLIED=false
PRESCRIPTION_FINGERPRINT_CONTRACT_CHANGED=false
```
