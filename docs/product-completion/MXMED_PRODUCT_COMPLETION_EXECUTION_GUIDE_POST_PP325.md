# MXMED Product Completion Execution Guide — post PP-325

## 0. Live status board

```text
DOCUMENT=MXMED_PRODUCT_COMPLETION_EXECUTION_GUIDE_POST_PP325
DOCUMENT_MODE=PERSISTENT_EXECUTION_CHECKLIST
CURRENT_CHECKPOINT=POST_PP328_C3_TECHNICAL_PASS
CURRENT_PHASE=C3_PHYSICAL_VALIDATION_PENDING

C0=CLOSED
C1=CLOSED
C2=CLOSED
C3=TECHNICAL_PASS
C4=NOT_STARTED
C5=NOT_STARTED
C6=NOT_STARTED
C7=NOT_STARTED
C8=NOT_STARTED
C9=NOT_STARTED
C10=NOT_STARTED
C11=NOT_STARTED
C12=NOT_STARTED

NEXT_IMPLEMENTATION_PHASE=C3_ISOLATED_PHYSICAL_VALKEY_VALIDATION
NEXT_REQUIRED_ACTION=C3_ISOLATED_PHYSICAL_VALKEY_VALIDATION
C3_IMPLEMENTATION_AUTHORIZED=false

AUDIT_INFRASTRUCTURE=CLOSED
AUDIT_REOPEN_REQUIRES_MATERIAL_REGRESSION=true
MP01B_MP01C_CHAPTER_CLOSED=true
A1_G1_CHAPTER_CLOSED=true
MEDICAL_INITIAL_LAUNCH_SCOPE=RATIFIED
COMMERCIAL_PROFILES=POST_LAUNCH
MONTH_VIEW=OUT_OF_INITIAL_LAUNCH
PLATFORM_BACKOFFICE_REQUIRED_BEFORE_LAUNCH=true
DIRECTOR_REVIEW_REQUIRED=true
GIT_PROMOTION_AUTHORIZED=false
```

This board is the first field updated after an authorized phase changes state. A phase may use only: `NOT_STARTED`, `DISCOVERY`, `DIRECTOR_DECISION_REQUIRED`, `READY_TO_IMPLEMENT`, `IMPLEMENTING`, `TECHNICAL_PASS`, `UX_REVIEW`, `BLOCKED`, `BACKLOG`, `CLOSED`.

## 1. Authority, purpose and status semantics

This guide is the persistent product-completion checklist after PP-325. It consolidates:

1. current code at `d0f6a37793f25ce99739a4549131d4a5a40b6eea` on `program/mxmed-product-completion-v1`;
2. PP-325 and the closed audit chapters;
3. the Option C read-only Product Design + Implementation Brief;
4. the Director-ratified C1 authority in Appendix A.

Authority classes used throughout:

- `CURRENT`: physically observed code/document state at the checkpoint; not an assertion about unqueried infrastructure.
- `RATIFIED`: binding Director product/security/UX authority.
- `FUTURE`: required implementation or evidence, not yet authorized or complete.
- `NOT_AUTHORIZED`: prohibited in the current activity or until a specific future authorization exists.

This document prevents static-only, backend-only and mock-only false closeout. A checked code artifact is not a product pass until its frontend, UX, physical dependencies, negative cases, E2E, evidence and applicable Director review gates pass.

Controlling-authority reconciliation:

- The audit sink/infrastructure is closed by PP-325 and must be reused, not reopened absent material regression.
- C1 communications authority is later and controlling for post-PP325 provider selection: the provider vendor is not ratified. Older documentation naming AWS SES is historical/non-execution authority for this guide.
- `PLAN_MAESTRO_UPDATE_RECOMMENDED=true`; the Plan Maestro is intentionally not modified until review and promotion are separately authorized.

## 2. Ratified launch scope and macro critical path

```text
INITIAL_LAUNCH_SCOPE=MEDICAL_ONLY
COMMERCIAL_PROFILES=POST_INITIAL_MEDICAL_LAUNCH
NEXT_PRODUCT_ACTIVITY=OPTION_C_PRODUCTIVE_IDENTITY_AND_AUTHORIZATION_TRUST_BOUNDARY
BASIC_PLAN_GALLERY_ENTITLEMENT=STANDARD_AND_ABOVE
GALLERY_COMMERCIAL_COMMITMENT_VERIFICATION_PENDING=true
MONTH_VIEW_LAUNCH_SCOPE=OUT
PRODUCTIVE_EMAIL_OTP_PROVIDER=SEPARATE_AUTHORIZED_SUBPHASE_REQUIRED_BEFORE_IDENTITY_CLOSEOUT
MOBILE_UI3_AND_RELEASE_GATE=SEPARATE_FINAL_CRITICAL_PATH_PHASE
```

Ratified macro sequence:

```text
OPTION_C_IDENTITY_AND_AUTHORIZATION
→ PRODUCTIVE_EMAIL_OTP_PROVIDER
→ PUBLIC_MEDICAL_DISCOVERY_PROFILE_BOOKING
→ AGENDA_CORE_CLOSEOUT
→ PAYMENT_ACTIVATION
→ MOBILE_UI3_AND_RELEASE_GATE
```

The controlling operational interpretation is:

```text
OPTION_C_CORE_IS_NOT_OPTION_C_PRODUCT_CLOSEOUT=true
OPTION_C_CORE=C2_TO_C3_TO_C4_TO_C5_TO_C6_TO_C7_TO_APPLICABLE_C9
OPTION_C_CORE
→ C8_PRODUCTIVE_EMAIL_OTP_PROVIDER
→ C10_IDENTITY_E2E_AND_DIRECTOR_UX_ACCEPTANCE
→ C11_PLATFORM_OPERATIONS_BACKOFFICE_MVP
→ C12_DOCUMENTATION_AND_GIT_CLOSEOUT
→ OPTION_C_PRODUCT_CLOSEOUT
→ PUBLIC_MEDICAL_DISCOVERY_PROFILE_BOOKING
```

C8 remains separately authorized and must pass before `OPTION_C_PRODUCT_CLOSEOUT`. Its separate macro position is preserved and does not permit closing Option C without it. Commercial profiles stay post-launch. Month Agenda stays out. The recognized AI actor model does not mean an AI feature is implemented.

## 3. Product tracks and cross-cutting concerns

### Track A — Medical client product

| Item | Scope | Phase | Status | Close condition |
|---|---|---:|---|---|
| A-01 | Productive physician registration and contact verification | C2/C7/C8 | NOT_STARTED | Email and phone verification real; no pre-verification login |
| A-02 | Customer session/cookie/store | C3 | TECHNICAL_PASS | Server-authoritative approved production store adapter, fail-closed behavior, ratified TTL/cookie, rotation and revocation proven |
| A-03 | Professional data and accreditation | C4/C7/C11 | NOT_STARTED | Human review, states/router, evidence and traceability complete |
| A-04 | Claim/new-profile/ownership/transfer | C4/C11 | NOT_STARTED | Immutable origin, anti-duplicate, owner invariant and approvals proven |
| A-05 | Profiles authority cutover | C6 | NOT_STARTED | No productive header/open authority; exact membership/scope negatives pass |
| A-06 | Subscriptions authority cutover and Standard+ gallery | C6 | NOT_STARTED | Owner/capability exact; commercial commitment verified before entitlement coding |
| A-07 | Patients boundary | C6/C9 | NOT_STARTED | Canonical authority active and cross-profile/patient negatives pass |
| A-08 | Clinical/Cases boundary | C6/C9 | NOT_STARTED | Last/high-risk cutover; exact patient/profile/resource authority proven |
| A-09 | Identity UX/E2E | C7/C10 | NOT_STARTED | 18 flows, real states, 320–430 px, accessibility and Director acceptance |

### Track B — Practice operators

| Item | Scope | Phase | Status | Close condition |
|---|---|---:|---|---|
| B-01 | Human operator independent account/invitation | C4/C5/C8 | NOT_STARTED | Existing account reuse, no shared/temp credential, exact target approval |
| B-02 | Multi-profile independent assignments | C5 | NOT_STARTED | Server selector, no inheritance, independent revoke/validity/capabilities |
| B-03 | Scheduling-minimum patient access | C5/C6/C9 | NOT_STARTED | Only name/contact/appointment administration; Clinical denied by default |
| B-04 | AI practice-operator boundary | C5/C9 | NOT_STARTED | Actor/assignment/entitlement/kill switch/escalation contract proven; product implementation separately authorized |
| B-05 | MXMed call-center delegated service | C5/C9 | NOT_STARTED | Physician authorization, real human attribution, separate plane/context |
| B-06 | Agenda concurrency shared by all actors | Future Agenda gate | NOT_STARTED | Atomic conflict protection and one authoritative slot state |

### Track C — Internal MXMed Platform Operations Backoffice

| Item | Scope | Phase | Status | Close condition |
|---|---|---:|---|---|
| C-01 | Internal identities, six role families and assignments | C11 | NOT_STARTED | MFA, expiry, step-up, reason and separation-of-duties proven |
| C-02 | Account/profile/ownership/claim support | C11 | NOT_STARTED | Workflow policy, minimized projection, queue and traceability complete |
| C-03 | Platform profile ingestion and lifecycle | C11 | NOT_STARTED | Ownerless/owned CRUD lifecycle, anti-duplicate and provenance proven |
| C-04 | Subscription/payment read and diagnosis | C11 | NOT_STARTED | Read-only initial billing scope; no unratified financial mutation |
| C-05 | Audit review | C11 | NOT_STARTED | Scoped/read-only; review access itself audited; source immutable |
| C-06 | Backoffice UX and all 16 workflows | C11 | NOT_STARTED | Separately authorized, Director reviewed, required before medical launch |

### Track D — Public patient experience

| Item | Scope | Phase | Status | Close condition |
|---|---|---:|---|---|
| D-01 | Public medical discovery/profile | Macro phase after Option C | NOT_STARTED | Public truth, privacy and entitlement states productized |
| D-02 | Public availability | Agenda closeout | NOT_STARTED | Same server-authoritative state as every actor |
| D-03 | Hold→data→OTP→confirmation | Agenda/provider | NOT_STARTED | Hold TTL/expiry/atomic acquisition and SMS OTP E2E proven |
| D-04 | Public responsive UX | Mobile/release gate | NOT_STARTED | Mobile journey and failure/restart behavior accepted |

### Track E — Post-launch commercial product

| Item | Scope | Phase | Status | Close condition |
|---|---|---:|---|---|
| E-01 | Commercial profiles | Post-launch | BACKLOG | Separately ratified model and authority |
| E-02 | Commercial organization memberships | Post-launch | BACKLOG | No leakage into medical ownership model |
| E-03 | Advanced billing operations | Post-launch | BACKLOG | Financial mutation policy and approvals separately ratified |

### Cross-cutting ledger

| Concern | Current | Required gate |
|---|---|---|
| IDENTITY_AND_AUTHORIZATION | Prepared core; preview/transitional consumers | Productive composition plus all consumer cutovers |
| ENTITLEMENTS | Existing candidates; gallery decision ratified with verification pending | Commercial authority reconciliation; operator/AI/call-center gates |
| NOTIFICATIONS_AND_OTP | Preview/rejecting/dev ports | C8 product provider, purpose isolation and delivery E2E |
| AGENDA_CONCURRENCY | Existing reserve/confirm not yet adjudicated against ratified hold contract | Explicit public slot concurrency proof |
| AUDIT_TRACEABILITY | Closed infrastructure | Reuse canonical producers for each new physical flow; no reopen |
| RESPONSIVE_UX | Current Identity prototype overflows at 390 px | C7/C10 and final UI3/release gate |
| E2E_AND_RELEASE | Productive E2E absent | C10, C11 and final release gates |

## 4. Global actor and authorization-plane model

| Actor | Plane | Authority source | Default allowed | Explicitly denied/default absent |
|---|---|---|---|---|
| Public booking patient | PUBLIC_BOOKING | Purpose-bound booking/OTP context | Public profile, availability, own hold/booking | Professional, platform, other-patient authority |
| Physician / primary owner | PRACTICE_AUTHORIZATION | Accredited account + active primary ownership/membership | Exact owned profiles and entitled services | Other profiles, internal platform authority |
| Human practice operator | PRACTICE_AUTHORIZATION | Independent account + per-profile assignment | Explicit appointment/location/schedule capabilities | Ownership; Clinical by default; platform roles |
| AI practice operator | PRACTICE_AUTHORIZATION | Non-human actor + explicit assignment + entitlement | Explicit bounded automation capabilities | Ownership; Clinical by default; implicit activation |
| Call-center service/agent | CALLCENTER_SERVICE_AUTHORIZATION | Physician-authorized platform-managed delegated service + real agent | Explicit practice service capabilities | Internal administration; shared agent accounts |
| Internal platform operator | PLATFORM_AUTHORIZATION | Internal account + role assignment + MFA/step-up | Role/workflow-specific backoffice functions | Clinical/patient data by default; ownership |
| Platform administrator | PLATFORM_AUTHORIZATION | Privileged internal role with two-person controls | Role/security governance | Self-grant, wildcard authority, ownership inheritance |

Hard boundaries:

- The platform, practice and call-center planes are distinct.
- A person with call-center and platform duties receives separate assignments and separate effective contexts.
- Platform authority never flows into call-center/practice authority.
- Practice/AI/call-center assignments do not create ownership.
- Client headers/body/query/local state never create actor, role, assignment or resource scope.
- All effective actors and real actors are server-derived and traceable.

Controlling boundary-pass rule:

```text
AI_OPERATOR_BOUNDARY_PASS_DOES_NOT_IMPLY_AI_PRODUCT_IMPLEMENTED=true
CALLCENTER_OPERATOR_BOUNDARY_PASS_DOES_NOT_IMPLY_CALLCENTER_SERVICE_IMPLEMENTED=true

WHEN_AI_PRODUCT_IMPLEMENTATION_AUTHORIZED_IS_FALSE_BOUNDARY_PASS_MEANS=
ACTOR_CONTRACT|AUTHORIZATION_SEPARATION|ASSIGNMENT_COMPATIBILITY|ENTITLEMENT_CONTRACT|FAIL_CLOSED_NON_ACTIVATION|TRACEABILITY_CONTRACT|NEGATIVE_SECURITY_BEHAVIOR

WHEN_CALLCENTER_IMPLEMENTATION_AUTHORIZED_IS_FALSE_BOUNDARY_PASS_MEANS=
ACTOR_CONTRACT|AUTHORIZATION_SEPARATION|ASSIGNMENT_COMPATIBILITY|ENTITLEMENT_CONTRACT|FAIL_CLOSED_NON_ACTIVATION|TRACEABILITY_CONTRACT|NEGATIVE_SECURITY_BEHAVIOR

BOUNDARY_PASS_DOES_NOT_AUTHORIZE=
AI_SERVICE_IMPLEMENTATION|MODEL_OR_PROVIDER_INTEGRATION|AI_RUNTIME|CALLCENTER_PRODUCT_IMPLEMENTATION|CALLCENTER_STAFFING_OR_RUNTIME|NEW_EXTERNAL_PROVIDER|NEW_PRODUCT_UI_OUTSIDE_SEPARATELY_AUTHORIZED_SCOPE
```

## 5. Identity and account lifecycle

Canonical physician lifecycle:

```text
PUBLIC_PHYSICIAN_REGISTRATION
→ EMAIL_VERIFICATION
→ PHONE_VERIFICATION
→ PROFESSIONAL_PRIMARY_DATA
→ PHYSICIAN_ACCREDITATION
→ REQUEST_ORIGIN_AND_PROFILE_MATCH_RESOLUTION
→ PROFILE_OWNERSHIP_OR_NEW_PROFILE_RESOLUTION
→ ACTIVE_PROFILE_MEMBERSHIP
→ MEDICAL_PANEL
```

Registration is physicians-only. Practice operators are invitation-only. Initial fields are email, password, password confirmation, terms and privacy acceptance. Professional name, one or more license numbers, specialty and primary geography are collected after contact verification. Documentary accreditation follows primary professional data.

No login is permitted before both email and phone verification. Before accreditation, the private area is account/onboarding only. The medical panel requires both accredited physician and active profile ownership.

State-based post-login router order is binding:

1. email verification;
2. phone verification;
3. professional primary data;
4. physician accreditation;
5. request origin/match resolution;
6. profile ownership;
7. active membership count.

Routes must cover every result ratified in Appendix A, including accreditation pending/action-required/rejected, claim/conflict states, profile-creation resolution and 0/1/multiple active profiles. The original claim profile is never reselected later.

Security lifecycle:

- no remember session or long-lived auth session;
- customer idle 3,600 s, absolute 43,200 s, touch 300 s, maximum five sessions;
- platform policy is stricter and finalized in C11;
- rate limit + temporary security lock + manual disable are distinct controls;
- any security state change revokes affected sessions;
- password reset is dedicated, never auto-login, returns to login and revokes prior sessions;
- credential recovery is not ownership recovery.

Product authority ratifies store behavior, not a mandatory technology:

```text
SESSION_STORE_AUTHORITY=SERVER_AUTHORITATIVE_PRODUCTION_SESSION_STORE
SESSION_STORE_ADAPTER_REQUIREMENT=APPROVED_PRODUCTION_SESSION_STORE_ADAPTER
VALKEY_CURRENT_CANDIDATE=true
FINAL_SESSION_STORE_TECHNICAL_ADJUDICATION=C3
```

Valkey adapters remain current architecture/evidence and may be selected only by the C3 adjudication. No alternative technology is selected here. Any approved store must fail closed, remain server-authoritative, enforce ratified TTLs, rotation and revocation, and preserve secure cookie/session semantics.

## 6. Physician accreditation

Base accreditation requires official identity plus professional-license validation. Additional evidence is requested only when required. A license match is a strong identifier, not automatic accreditation or ownership.

Initial medical launch uses human review:

- one approver for normal cases;
- second approval for conflict, transfer and exception;
- future automation is allowed conceptually but not ratified or implemented.

Accreditation reminders are required. C8/UX must ratify cadence, maximum and stop policy. At minimum reminders stop after submission, approval, account disable or completion of reminder policy.

Required evidence includes reviewer identity/role, evidence type/provenance, decision/reason, before/after state, outcome and correlation. Raw documents and sensitive identifiers must follow minimized storage/access policy.

## 7. Profile matching and anti-duplicate policy

Binding precedence:

```text
1. EXACT_PROFESSIONAL_LICENSE_MATCH
2. COMPOSITE_PROFESSIONAL_MATCH
3. NO_MATCH
```

Composite means normalized name + specialty + geographic context + other available professional attributes. Multiple license numbers per physician are supported, and every exact number is strong. Fuzzy/name matching never outranks exact license.

Resolution:

- ownerless existing profile → claim;
- active owner + same existing account → credential recovery;
- active owner + same physician but unrecoverable different account → ownership recovery/transfer;
- active owner + ambiguous/conflicting identity → conflict review;
- no match → new profile request continues.

Exact license with name/specialty discrepancy is a strong match with data discrepancy and requires human review. One license across incompatible profiles is a data-integrity conflict; no automatic profile selection.

A new profile is created only after accreditation and after a final anti-duplicate check using the same precedence. If a match appears, creation stops and the existing profile is resolved.

## 8. Ownership, claim, recovery and transfer

Profile primary-owner cardinality is zero-or-one. Ownerless published profiles are normal and claimable. One account may own multiple profiles. Platform/practice/AI/call-center operators and delegated administrators are not owners.

Distinct flows:

- existing-profile claim;
- new-profile request;
- account credential recovery;
- ownership recovery/transfer;
- ownership conflict review.

Request origin is immutable. A direct claim permanently preserves the originating profile ID. Approved accredited claim on an ownerless profile creates the primary-owner assignment.

An active owner is never silently replaced. Normal transfer requires current-owner approval and an accredited new owner. Exceptional transfer requires reaccreditation, second platform approval, mandatory reason and audit. The last primary owner cannot be removed without an atomic successor assignment.

Required state machines must preserve history, rejection/conflict and terminal outcomes. Session authority is rotated/revalidated after grant, transfer or revocation.

## 9. Platform-curated ownerless medical profiles

Platform profile ingestion continues for ownerless and owned profiles. Authorized PROFILE_OPERATIONS staff may create, edit, curate, publish, unpublish, suspend, reactivate, logically delete and restore when policy allows.

These operations:

- never create ownership;
- preserve separate creation-origin, publication, ownership and accreditation dimensions;
- require exact-license then composite anti-duplicate checks;
- preserve profile provenance;
- require actor, role, action, target, timestamp, reason when required, before/after, result and correlation ID;
- use logical delete/unpublish with history by default;
- allow physical purge only under a separate exceptional authorization.

Ownerless profiles may be published and later claimed. Owned profiles remain operable by authorized platform staff without converting staff into owners.

## 10. Practice operators and optional workspaces

Human practice operators use independent accounts and invitation-only entry. An existing account is reused. Every assignment requires target-profile authority approval; a physician may propose sharing an operator but cannot grant authority to another physician's profile.

An account may have multiple independent profile assignments. No permission inheritance exists. A server-validated profile selector is required.

Practice workspace is optional and explicitly created. Same address or shared operator does not create one. Workspace membership does not grant profile access. It may be introduced later without recreating accounts/assignments.

Assignment dimensions:

- actor type;
- profile;
- optional location scope;
- schedule scope;
- explicit capabilities;
- status;
- start/end/validity window;
- entitlement.

Supported states include pending, active, suspended, revoked, expired and suspended-by-entitlement. Entitlement loss suspends effective access without deleting history. Limits come from entitlement/platform policy, never hardcoded.

Scheduling-minimum patient access may include patient name, contact data and appointment administrative data. Clinical, Cases, documents, history and prescriptions are denied by default. Future clinical staff access requires separate explicit capability.

Profile-level revoke affects only that profile. Optional workspace revoke applies only when a workspace exists. Account-security suspension blocks all assignments without deleting them. Handoff overlap and multiple coexisting operators are supported. Location changes do not silently revoke assignment.

Every assignment mutation and business action records real actor attribution and full traceability.

## 11. AI practice operator

AI is a recognized non-human practice actor, not an implemented feature. Future implementation requires separate authorization and entitlement reconciliation.

Required boundary:

- explicit profile assignment and entitlement;
- explicit capabilities, location and schedule scope, status and validity;
- no ownership;
- no default Clinical access;
- independent from human assignments;
- traceability for every action;
- kill switch;
- human escalation.

Human, AI and call-center operators may coexist and have overlapping schedules. Authorization and service orchestration remain distinct. All concurrent appointment mutations require server-side conflict protection.

## 12. MXMed call-center delegated practice service

The call-center service requires explicit physician authorization and an entitlement/service contract. It acts as a platform-managed practice operator on `PRACTICE_DELEGATED_SERVICE`, not internal platform administration.

MXMed may rotate authorized human agents without a new physician invitation, but every action identifies the real human actor; shared accounts are forbidden. Disabling the service revokes only call-center access.

A dual-duty person receives separate role assignments and separate effective contexts. Platform-admin authority cannot flow into call-center practice authority.

## 13. Internal platform roles and governance

Ratified role families:

- PLATFORM_ADMIN;
- IDENTITY_CLAIMS_OPERATOR;
- PROFILE_OPERATIONS;
- CUSTOMER_SUPPORT;
- BILLING_OPERATIONS;
- AUDIT_REVIEWER.

There is no normal superadmin. Platform roles have no default Clinical/patient access. Claims/accreditation review is distinct, with one normal reviewer and two for conflict/transfer/exception; self-approval is forbidden.

Customer Support may trigger safe recovery and revoke sessions when policy allows, but cannot view/set password, view raw token, impersonate or grant ownership. Billing initial scope is read/diagnostic only. Audit Reviewer is scoped/read-only and cannot mutate audit.

Every internal role mutation requires fresh step-up and reason. Self-grant is false; grantor cannot be beneficiary. PLATFORM_ADMIN grants and break-glass require a second approver; privileged approver differs from grantor. Temporary roles and expiry are supported.

Platform MFA is TOTP initially, with recovery codes. SMS is not primary admin MFA. Sensitive actions require a fresh MFA challenge. Passkey/WebAuthn and corporate SSO remain future-compatible. Break-glass has a separate control policy.

### C11 minimum backoffice workflows

1. BO-F01 search account.
2. BO-F02 search physician/profile.
3. BO-F03 inspect account verification state.
4. BO-F04 inspect ownership state.
5. BO-F05 approve/reject accreditation or ownership claim.
6. BO-F06 issue/inspect/revoke invitations.
7. BO-F07 suspend/reactivate profile under controlled authority.
8. BO-F08 inspect subscription state.
9. BO-F09 inspect payment state without secrets.
10. BO-F10 support account recovery without passwords/tokens/impersonation.
11. BO-F11 inspect authorized audit history.
12. BO-F12 assign/revoke internal roles.
13. BO-F13 require reason/confirmation/step-up for sensitive actions.
14. BO-F14 view role-scoped operational queues.
15. BO-F15 view immutable action outcome/history.
16. BO-F16 create, curate, publish and manage the lifecycle of medical profiles.

Workflows may be intentionally merged in UI only if all sixteen functional/security requirements, policies, evidence and close criteria remain explicit. C11 is separately authorized, after Identity core and required before medical launch.

## 14. Public booking concurrency

Canonical slot states are `AVAILABLE`, `HELD`, `BOOKED`.

```text
FIRST_VALID_SERVER_SIDE_ATOMIC_ACQUISITION_WINS=true
CLIENT_CLICK_TIMESTAMP_IS_NOT_AUTHORITY=true
PUBLIC_BOOKING_REQUIRES_TEMPORARY_EXCLUSIVE_HOLD=true
SERVER_HOLD_EXPIRATION_IS_AUTHORITATIVE=true
UNBOUNDED_HOLD_EXTENSION=DISALLOWED
ACTIVE_HOLD_CANNOT_BE_OVERRIDDEN_BY_OTHER_NORMAL_ACTORS=true
```

Hold TTL is configurable. Countdown is feedback only. Expired holds return availability without background cleanup being required for correctness. Confirmation after expiration is rejected and booking restarts. One active public hold per booking context is default.

Public journey: temporary hold → data → OTP → confirmation. Authorized operators may use atomic direct booking. All actors use the same authoritative availability and atomic conflict protection. Every hold/booking transition is traceable.

The current Agenda reserve/confirm implementation is not presumed compliant. A future gate must report:

```text
AGENDA_PUBLIC_SLOT_CONCURRENCY_IMPLEMENTATION_PROVEN=true|false
```

## 15. Notification, verification, OTP and provider boundary

Ratified channels:

- email verification: email;
- password recovery: email;
- invitation: email;
- phone verification: SMS;
- public booking OTP: SMS.

Notification orchestration may be shared, but purpose-specific ports are required. Identity and Agenda isolate token, TTL, rate limit, template, delivery history, retry policy and purpose. Underlying providers may be shared or different. Vendor selection remains unresolved until separately authorized C8.

C8 must define secrets, adapter, templates, privacy, retry/bounce, idempotency, delivery outcomes and reminder cadence without raw-token logging or account enumeration. Preview/dev/rejecting adapters never count as productive delivery.

## 16. UX, responsive and visual authority

Preserve MXMed logo, typography, colors, form controls, button language and card style where appropriate. Existing simple Identity UI is `REUSE_WITH_CHANGES`; complex onboarding may use expanded multi-step layout. No total redesign; current card geometry is not mandatory.

Mobile contract:

- minimum width 320 px; target 320–430 px;
- horizontal overflow forbidden;
- every critical Identity flow works on mobile;
- touch and keyboard operation required;
- primary CTA remains reachable;
- critical errors are never breakpoint-hidden;
- legal copy wraps.

Current 390 px prototype overflow is a known gap. C7 fixes Identity; C10 obtains Director UX acceptance. Final global UI3 remains a separate critical-path phase and must not be pulled forward silently.

## 17. Option C phase checklist C0–C12

### C0 — Discovery and authority reconciliation

- `STATUS=CLOSED`
- `OBJECTIVE=` Map current Identity, UX, consumers, actors, backoffice and provider boundary.
- `USER_VISIBLE_OUTCOME=` None; authoritative discovery bundle.
- `BACKEND_SCOPE=` Read-only inventory.
- `FRONTEND_SCOPE=` Five screens, 18 flows, 23 states and captures.
- `DATA_MODEL_SCOPE=` Source/migration reading only.
- `DEPENDENCIES=` PP-325 checkpoint.
- `DIRECTOR_DECISIONS=` Inputs prepared for C1.
- `IMPLEMENTATION_AUTHORIZATION=CLOSED_READ_ONLY_ACTIVITY`
- `PASS_CRITERIA=` Evidence-based bundle and no product mutation.
- `NEGATIVE_CRITERIA=` No invented physical state; no audit reopen.
- `UX_REVIEW_REQUIRED=false`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Option C brief/matrices/captures/adjudication.
- `ROADMAP_GATE_UNLOCKED=C1`

### C1 — Director functional and UX ratification

- `STATUS=CLOSED`
- `OBJECTIVE=` Ratify account, accreditation, ownership, actor, security, communications, UX and roadmap authority.
- `USER_VISIBLE_OUTCOME=` Binding future product contract; no runtime activation.
- `BACKEND_SCOPE=` Authority only.
- `FRONTEND_SCOPE=` UX/mobile authority only.
- `DATA_MODEL_SCOPE=` Cardinalities/states as future authority.
- `DEPENDENCIES=` C0.
- `DIRECTOR_DECISIONS=` Appendix A fully ratified.
- `IMPLEMENTATION_AUTHORIZATION=CLOSED_AUTHORITY_ACTIVITY`
- `PASS_CRITERIA=` All C1 blocks consolidated without contradiction.
- `NEGATIVE_CRITERIA=` No old D identifiers treated as authority; no implementation.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Ratification text and this reviewed guide.
- `ROADMAP_GATE_UNLOCKED=C2_READY_FOR_SEPARATE_AUTHORIZATION`

### C2 — Productive Identity composition

- `STATUS=CLOSED`
- `OBJECTIVE=` Separate fail-closed productive composition from preview.
- `USER_VISIBLE_OUTCOME=` Productive Identity API foundation, not product closeout.
- `BACKEND_SCOPE=` Config/secret/DB/store/provider ports, environment selection and stable API contracts.
- `FRONTEND_SCOPE=` None beyond contract compatibility.
- `DATA_MODEL_SCOPE=` Existing schema verification; changes only if separately authorized.
- `DEPENDENCIES=` C1; explicit C2 authorization and environment inventory.
- `DIRECTOR_DECISIONS=` None beyond Appendix A; any new material decision returns to Director.
- `IMPLEMENTATION_AUTHORIZATION=CLOSED_AUTHORITY_ACTIVITY`
- `PASS_CRITERIA=` Production never calls preview; missing dependency fails closed; all intended routes composed.
- `NEGATIVE_CRITERIA=` Preview/local/in-memory fallback; provider false success; product code outside scope.
- `UX_REVIEW_REQUIRED=false`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Exact path inventory, tests, config negatives and safe return.
- `ROADMAP_GATE_UNLOCKED=C3_AND_C4_PREPARATION`

Physical close evidence:

```text
C2_STATUS=CLOSED
DIRECTOR_C2_LOCAL_REVIEW=PASS
PRODUCTIVE_COMPOSITION_PRESENT=true
PRODUCTIVE_PREVIEW_REACHABLE=false
LOCAL_DEV_PREVIEW_PRESERVED=true
PRODUCTIVE_FAIL_CLOSED=true
C2_NEGATIVE_CONTRACT_PASS=true
C2_CHANGED_PATH_COUNT=5

SESSION_STORE_NOT_ACTIVATED=true
PROVIDER_NOT_ACTIVATED=true
DATABASE_NOT_MUTATED=true
CONSUMERS_NOT_CUT_OVER=true
FRONTEND_NOT_IMPLEMENTED=true

IDENTITY_CORE_TECHNICAL_PASS=false
REGISTRATION_NOTIFICATION_FAILURE_SEMANTICS_REVIEW_REQUIRED=true
```

Current registration semantics may commit a pending account before the notification attempt and invalidate the verification token when delivery fails. This is not a C2 blocker; C8 must reconcile retry, recovery and provider semantics before Identity product closeout.

### C3 — Session, cookie and store

- `STATUS=TECHNICAL_PASS`
- `OBJECTIVE=` Activate canonical customer session and prepare stricter platform session policy.
- `USER_VISIBLE_OUTCOME=` Real login/logout/expiry/revocation/session security.
- `BACKEND_SCOPE=` Productive Valkey session-store source candidate, account-state refresh, rotation, CSRF, lock/disable and session APIs; isolated physical real-store validation remains pending.
- `FRONTEND_SCOPE=` Current-session bootstrap, expiration/revocation and session management if scoped.
- `DATA_MODEL_SCOPE=` No DB session store recommended; exact need adjudicated before change.
- `DEPENDENCIES=` C2, authorized production session-store/config infrastructure and security authority.
- `DIRECTOR_DECISIONS=` Platform timeout finalization deferred to C11; customer values ratified.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Host-only secure cookie; ratified TTL/max; fail-closed store/account state; password/security changes revoke.
- `NEGATIVE_CRITERIA=` Remember session; long-lived auth; fixation; stale authority; memory fallback.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Approved production-store tests, technology adjudication, cookie inspection and outage/replay/expiry evidence.
- `ROADMAP_GATE_UNLOCKED=C4_C5_C6_AUTHENTICATED_CONTEXT`

Source technical-pass evidence:

```text
DIRECTOR_C3_LOCAL_REVIEW=PASS
C3_SOURCE_IMPLEMENTATION_READY=true
PRODUCTIVE_VALKEY_CLIENT_PRESENT=true
TLS_SOURCE_CONTRACT_PASS=true
ACL_SOURCE_CONTRACT_PASS=true
ATOMIC_SESSION_STORE_SOURCE_PASS=true
STRICT_SESSION_SERIALIZATION_PASS=true
SERVER_CLOCK_TTL_PASS=true
MAX_FIVE_SESSIONS_PASS=true
SIXTH_SESSION_POLICY=REVOKE_OLDEST_SESSION
SESSION_LIST_BACKEND_PASS=true
SESSION_REVOKE_BACKEND_PASS=true
COOKIE_CONTRACT_PASS=true
CSRF_CONTRACT_PASS=true
SESSION_HTTP_STATE_CONTRACT_PASS=true
SESSION_AUDIT_WIRING_PASS=true
AWS_SOURCE_CONTRACT_PASS=true
C3_NEGATIVE_CONTRACT_PASS=true

PHYSICAL_VALKEY_TEST_DEFINED=true
PHYSICAL_VALKEY_TEST_EXECUTED=false
REAL_STORE_TLS_PROOF=false
REAL_STORE_ACL_PROOF=false
REAL_STORE_ATOMICITY_PROOF=false
REAL_STORE_MAX_FIVE_CONCURRENCY_PROOF=false
REAL_STORE_OUTAGE_PROOF=false

C3_PHASE_CLOSED=false
C4_IMPLEMENTATION_AUTHORIZED=false
NEXT_REQUIRED_ACTION=C3_ISOLATED_PHYSICAL_VALKEY_VALIDATION
```

Real-store tests, cookie inspection, outage/replay/expiry evidence and the remaining physical proofs above are still required before `C3=CLOSED`.

### C4 — Ownership, claim and invitation foundation

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Implement professional data, accreditation, matching, claims, invitations, ownership and transfer.
- `USER_VISIBLE_OUTCOME=` Verified/accredited physician reaches correct claim/new-profile/ownership state.
- `BACKEND_SCOPE=` Immutable origin, matching, review/approval, primary-owner invariant, transfer and router.
- `FRONTEND_SCOPE=` Multi-step onboarding, status/correction/conflict/selector/invitation flows.
- `DATA_MODEL_SCOPE=` Professional identity/licenses, accreditation, requests, claims/invitations, ownership/approval/provenance.
- `DEPENDENCIES=` C2/C3; profile authority; C8 needed for delivery closeout.
- `DIRECTOR_DECISIONS=` Reminder cadence/max/stop details remain C8/UX parameters.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` All Appendix A states/transitions, anti-duplicate and approval rules physically proven.
- `NEGATIVE_CRITERIA=` License-only auto-grant; duplicate profile; target reselection; silent owner replacement; self-approval.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Migration/tests/state matrices/negative conflicts/audit attribution.
- `ROADMAP_GATE_UNLOCKED=C5_C6_AND_C11_INPUTS`

### C5 — Practice-operator authorization model

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Canonical human/AI/call-center practice actor assignments.
- `USER_VISIBLE_OUTCOME=` Independent operators safely act on exact assigned profiles.
- `BACKEND_SCOPE=` Account/actor binding, per-assignment state/scope/capability/entitlement, revoke, selectors and real-actor attribution.
- `FRONTEND_SCOPE=` Invitation/assignment/profile selection/handoff/kill-switch and status UX as authorized.
- `DATA_MODEL_SCOPE=` Human/non-human/service assignments, validity and entitlement; optional workspace explicitly separate.
- `DEPENDENCIES=` C3/C4; Agenda capability catalog; provider for invitation completion.
- `DIRECTOR_DECISIONS=` AI implementation remains separately authorized; no inference from actor ratification.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Independent multi-profile assignments; scheduling-minimum only; all three planes distinct.
- `NEGATIVE_CRITERIA=` Hardcoded limits; permission inheritance; Clinical default; client role/scope; shared call-center account.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Actor matrix, cross-profile negatives, revocation/expiry/entitlement and attribution tests.
- `ROADMAP_GATE_UNLOCKED=C6_AND_OPERATOR_PORTION_OF_C9`

### C6 — Consumer adapters

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Cut over Profiles, Subscriptions, Agenda, Patients and Clinical to canonical authority in controlled order.
- `USER_VISIBLE_OUTCOME=` Every private resource uses one validated session/actor/membership/capability boundary.
- `BACKEND_SCOPE=` Server-built adapters; exact resource scope; shadow comparison/safe return per module.
- `FRONTEND_SCOPE=` Remove header/query/local authority; handle 401/403/profile selection truthfully.
- `DATA_MODEL_SCOPE=` Canonical profile/doctor mappings only when proven necessary.
- `DEPENDENCIES=` C3–C5; one module at a time; Clinical last.
- `DIRECTOR_DECISIONS=` Standard+ gallery only after commercial commitment verification.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` All five consumer gates pass and legacy authority retires only after postvalidation.
- `NEGATIVE_CRITERIA=` Productive transitional/open/header/session_scope authority; simultaneous multi-module cutover; cross-resource access.
- `UX_REVIEW_REQUIRED=false`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Before/shadow/after metrics, negative matrix, rollback and exact path inventory per consumer.
- `ROADMAP_GATE_UNLOCKED=IDENTITY_CORE_TECHNICAL_PASS_CANDIDATE_AFTER_C9`

### C7 — Identity frontend integration

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Productize all account/onboarding/ownership/operator Identity UX.
- `USER_VISIBLE_OUTCOME=` Real mobile/desktop journeys and state-based destinations.
- `BACKEND_SCOPE=` Stable state/error/destination contracts.
- `FRONTEND_SCOPE=` Existing UI reuse-with-changes, multi-step onboarding, all states, accessibility and responsive behavior.
- `DATA_MODEL_SCOPE=` None beyond APIs.
- `DEPENDENCIES=` C2–C6; honest rejecting/provider-unavailable behavior before C8.
- `DIRECTOR_DECISIONS=` Copy and complex onboarding review points.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` No broken routes, prototypes, mock states or 320–430 px overflow; CTA/errors accessible.
- `NEGATIVE_CRITERIA=` Static-only success; client authority; hidden critical error; total redesign without review.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Browser captures, keyboard/accessibility checks and backend-correlated states.
- `ROADMAP_GATE_UNLOCKED=C8_AND_C10_UX_CANDIDATE`

### C8 — Email/OTP provider subphase

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Productize email/SMS delivery with isolated purposes.
- `USER_VISIBLE_OUTCOME=` Email/phone verification, recovery, invitation and public OTP deliver honestly.
- `BACKEND_SCOPE=` Vendor selection, adapters, secrets, templates, delivery history, retry/bounce and reminder orchestration.
- `FRONTEND_SCOPE=` Sent/pending/failure/retry/reminder states.
- `DATA_MODEL_SCOPE=` Purpose-scoped delivery/verification state if ratified; never raw token.
- `DEPENDENCIES=` Separate C8 authorization; C2/C4/C5; provider procurement/config.
- `DIRECTOR_DECISIONS=` Vendor, reminder cadence/max, retry/bounce and final copy.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Productive delivery and failure E2E; all purpose isolation fields proven.
- `NEGATIVE_CRITERIA=` Vendor presumed; shared token/TTL/rate/template/history/retry; raw token/secret log; false delivery success.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=true`
- `EVIDENCE_REQUIRED=` Provider sandbox/staging receipts, privacy review, retries/bounces and secret handling.
- `ROADMAP_GATE_UNLOCKED=C10_AND_PROVIDER_CLOSE_GATE`

### C9 — Negative security tests

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Prove fail-closed boundaries for all actors, states, consumers and outages.
- `USER_VISIBLE_OUTCOME=` Safe denial and recovery behavior.
- `BACKEND_SCOPE=` Session/CSRF/rate/lock, ownership/matching, actors/planes, consumers, concurrency and provider negatives.
- `FRONTEND_SCOPE=` Neutral error/disclosure mapping.
- `DATA_MODEL_SCOPE=` Isolated authorized fixtures only.
- `DEPENDENCIES=` C2–C6; repeat provider cases after C8.
- `DIRECTOR_DECISIONS=` None unless tests reveal a material product ambiguity.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Cross-account/profile/assignment/plane/purpose/replay/outage/concurrency negatives all deny.
- `NEGATIVE_CRITERIA=` False pass, permissive fallback, wildcard, client claim, audit bypass.
- `UX_REVIEW_REQUIRED=false`
- `PROVIDER_REQUIRED=false`
- `EVIDENCE_REQUIRED=` Deterministic matrix plus physical integration evidence where applicable.
- `ROADMAP_GATE_UNLOCKED=IDENTITY_CORE_TECHNICAL_PASS_CANDIDATE`

### C10 — Identity E2E and Director UX acceptance

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Close full Identity product journeys, not backend only.
- `USER_VISIBLE_OUTCOME=` Registration through accredited ownership/dashboard plus operator/recovery journeys work end to end.
- `BACKEND_SCOPE=` Productive-like composition/store/provider/consumers/audit.
- `FRONTEND_SCOPE=` All flows/states/mobile/accessibility and approved copy.
- `DATA_MODEL_SCOPE=` Controlled environment fixtures under separate authority.
- `DEPENDENCIES=` C2–C9, including productive provider.
- `DIRECTOR_DECISIONS=` Final UX acceptance.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Real E2E, negatives, traceability, responsive and Director UX pass.
- `NEGATIVE_CRITERIA=` Mock/preview delivery; backend-only; missing actor/state/mobile journey.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=true`
- `EVIDENCE_REQUIRED=` Journey matrix, screenshots/video as approved, logs/results sanitized and Director signoff.
- `ROADMAP_GATE_UNLOCKED=C11_SEPARATE_PHASE`

### C11 — Platform Operations Backoffice MVP

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Implement six internal roles, governance and all 16 medical-launch workflows.
- `USER_VISIBLE_OUTCOME=` Authorized MXMed staff safely operate accounts, accreditation/claims, profiles, support, billing reads and audit.
- `BACKEND_SCOPE=` Internal plane/session/MFA/TOTP/recovery codes/roles/expiry/approval and workflow policies/read models/commands.
- `FRONTEND_SCOPE=` Dashboard, queues, search/detail, claims, profiles, support, billing, audit, roles, confirmation/step-up/history.
- `DATA_MODEL_SCOPE=` Internal role assignments/expiry/approval and workflow state/projections.
- `DEPENDENCIES=` Identity core/C10, C8 notifications and separate C11 authorization.
- `DIRECTOR_DECISIONS=` Final screen IA, confirmation and operational policy thresholds.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED_SEPARATE_PHASE`
- `PASS_CRITERIA=` 16 workflows, six roles, TOTP/step-up/two-person controls, no default clinical data and Director UX pass.
- `NEGATIVE_CRITERIA=` Superadmin/wildcard, customer/practice context accepted, self-grant, impersonation, secrets, direct SQL normal operations.
- `UX_REVIEW_REQUIRED=true`
- `PROVIDER_REQUIRED=true`
- `EVIDENCE_REQUIRED=` Role/workflow matrix, SoD negatives, MFA/step-up, action audit and E2E.
- `ROADMAP_GATE_UNLOCKED=MEDICAL_LAUNCH_OPERATIONAL_READINESS`

### C12 — Documentation and Git closeout

- `STATUS=NOT_STARTED`
- `OBJECTIVE=` Reconcile guide/Plan Maestro/contracts/evidence and promote reviewed product completion.
- `USER_VISIBLE_OUTCOME=` None new; reproducible authority and release evidence.
- `BACKEND_SCOPE=` No new behavior.
- `FRONTEND_SCOPE=` No new behavior.
- `DATA_MODEL_SCOPE=` None.
- `DEPENDENCIES=` C10/C11 and all Option C gates pass.
- `DIRECTOR_DECISIONS=` Final closeout and Git promotion authorization.
- `IMPLEMENTATION_AUTHORIZATION=NOT_AUTHORIZED`
- `PASS_CRITERIA=` Docs match physical state, exact Git scope reviewed/promoted, clean/synchronized branch.
- `NEGATIVE_CRITERIA=` Stale board, missing evidence, uncommitted change, backend-only close.
- `UX_REVIEW_REQUIRED=false`
- `PROVIDER_REQUIRED=true`
- `EVIDENCE_REQUIRED=` Final manifests/hashes/tests/reviews/commit and upstream proof.
- `ROADMAP_GATE_UNLOCKED=OPTION_C_PRODUCT_CLOSEOUT`

## 18. Closeout gates

### Identity core technical pass

This may be declared only after authorized C2–C6 implementation plus the applicable C9 negatives. It means composition, customer sessions, ownership/accreditation foundation, operator boundaries and consumer authority are technically proven. It does not mean provider, complete frontend, E2E, Director UX, backoffice, documentation or Git promotion are complete.

### Option C product closeout

Every value must be true:

```text
FUNCTIONAL_CONTRACT_PASS=true
PRODUCTIVE_COMPOSITION_PASS=true
SESSION_COOKIE_STORE_PASS=true
OWNERSHIP_AUTHORIZATION_PASS=true
PRACTICE_OPERATOR_BOUNDARY_PASS=true
AI_OPERATOR_BOUNDARY_PASS=true
CALLCENTER_OPERATOR_BOUNDARY_PASS=true
PLATFORM_OPERATOR_BOUNDARY_PASS=true
PROFILE_CONSUMER_PASS=true
AGENDA_CONSUMER_PASS=true
SUBSCRIPTIONS_CONSUMER_PASS=true
PATIENTS_CONSUMER_BOUNDARY_PASS=true
CLINICAL_CONSUMER_BOUNDARY_PASS=true
FRONTEND_PASS=true
PRODUCTIVE_PROVIDER_PASS=true
END_TO_END_PASS=true
NEGATIVE_SECURITY_PASS=true
AUDIT_EVENT_PASS=true
MINIMUM_RESPONSIVE_PASS=true
DIRECTOR_UX_ACCEPTED=true
PLATFORM_BACKOFFICE_MVP_PASS=true
DOCUMENTATION_UPDATED=true
GIT_PROMOTION_COMPLETE=true
```

No backend-only closeout is allowed. AI and call-center boundary passes prove only actor contract, authorization separation, assignment/entitlement compatibility, fail-closed non-activation, traceability and negative security behavior. They do not assert or authorize either product implementation while the corresponding implementation authorization remains false.

## 19. Remaining macro roadmap

| Order | Macro phase | Current status | Entry gate | Exit gate |
|---:|---|---|---|---|
| 1 | Option C core: C2→C3→C4→C5→C6→C7→applicable C9 | C2 closed; C3 TECHNICAL_PASS; physical validation pending | Separate authorization for isolated C3 physical Valkey validation | `IDENTITY_CORE_TECHNICAL_PASS` candidate; not product closeout |
| 2 | C8 Productive email/OTP provider | NOT_STARTED | Separate provider authorization after applicable core readiness | Real email/SMS purpose isolation and delivery E2E |
| 3 | C10 Identity E2E and Director UX acceptance | NOT_STARTED | Core plus C8 passes | E2E, responsive and Director UX accepted |
| 4 | C11 Platform Operations Backoffice MVP | NOT_STARTED | Identity core complete; separate C11 authorization | Six roles and 16 workflows pass before medical launch |
| 5 | C12 Documentation and Git closeout | NOT_STARTED | C10/C11 and all technical/product evidence pass | Documentation updated and Git promotion complete |
| 6 | Option C product closeout | NOT_STARTED | C2–C12 applicable gates all true | Every closeout field in Section 18 is true |
| 7 | Public medical discovery/profile/booking | NOT_STARTED | Option C product closeout | Public discovery/profile/booking UX and authority pass |
| 8 | Agenda core closeout | NOT_STARTED | Identity/booking authority ready | Concurrency, actor and Agenda product gates |
| 9 | Payment activation | NOT_STARTED | Agenda/product authority and payment authorization | Real payment E2E, reconciliation and release gates |
| 10 | Mobile UI3/release gate | NOT_STARTED | All preceding critical-path phases | Final responsive/E2E/Director release acceptance |
| 11 | Commercial profiles | BACKLOG | Post initial medical launch | Separate authority |

## 20. Director approval gates

- `G-DOC`: review this guide before Git promotion.
- `G-C2`: explicit authorization before any productive composition code/config.
- `G-C3-INFRA`: explicit DB/approved production session-store/config/secret authority and technology adjudication before physical integration; Valkey is the current candidate, not ratified product technology.
- `G-C4-DATA`: exact migrations/state machines and UX scope approved before mutation.
- `G-C5-ACTORS`: exact human/AI/call-center implementation scope; AI product remains separate.
- `G-C6-CONSUMER`: one consumer at a time, preflight/shadow/safe return/postvalidation.
- `G-C7-UX`: browser review points and approved copy/state contract.
- `G-C8-PROVIDER`: vendor, channels, secrets, retries, reminders, privacy and external calls.
- `G-C10-UX`: Director E2E/visual acceptance.
- `G-C11`: separate backoffice authorization and later UX acceptance.
- `G-C12-GIT`: exact docs/evidence/path set and Git promotion authority.
- `G-MACRO`: separate authorization for every later macro phase.

## 21. Evidence, activity and Git discipline

Every future activity header must declare:

```text
CURRENT_PHASE=
CURRENT_CHECKLIST_ITEM=
CURRENT_AUTHORITY=
FILES_OR_MODULE_FAMILIES=
MUTATION_CLASS=
AUTHORIZED_MUTATIONS=
PROHIBITED_MUTATIONS=
PASS_CRITERIA=
FIRST_BLOCKER=
DIRECTOR_REVIEW_REQUIRED=true|false
```

Every activity must preserve prestate, exact authorized paths, tests proportional to risk, negative cases, sanitized evidence, safe return and poststate. Status advances only from physical evidence; a plan, interface, dormant class, feature flag, static screen, mock/provider preview or passing isolated unit test cannot alone create `TECHNICAL_PASS` or `CLOSED`.

No activity may silently reopen audit, expand commercial scope, activate Month, refactor unrelated code, rewrite Stripe, perform global UI3 early or change Appendix A. Those require explicit Director authority.

Git rules per activity: inspect HEAD/branch/worktree/upstream; no add/commit/push/merge/rebase unless explicitly authorized; publish exact path/mode/bytes/SHA and tests; update the live board only in an authorized documentation phase.

## 22. Current implementation inventory anchor

At the guide checkpoint:

- Identity component inventory: 55 logical components; 19 reusable as-is, 22 with changes, 8 preview-only, 7 transitional, 28 dormant/flagged and 11 missing productive components.
- Productive composition: missing; HTTP composition is preview-only.
- Identity screens: five UI3 preview screens; links/states incomplete and 390 px overflow observed.
- Ownership: membership primitive prepared; productive accreditation/claim/invitation/transfer absent.
- Practice operators: Agenda transitional records/UI; canonical Identity account binding absent.
- Platform backoffice: not implemented.
- Provider: productive vendor/adapter absent and vendor undecided.
- Audit infrastructure: closed and reusable.

These counts are discovery anchors, not permanent acceptance targets. Future activity must refresh only the affected checklist item with evidence and must not rewrite history.

## Appendix A — Ratified C1 authority ledger

Every identifier below is unique and binding. Original discovery IDs `D-01…D-23` are not authority identifiers.

### C1-ID-01

```text
PUBLIC_PROFESSIONAL_REGISTRATION=PHYSICIANS_ONLY
PRACTICE_OPERATOR_ENTRY=INVITATION_ONLY
```

### C1-ID-02

```text
INITIAL_REGISTRATION_FIELDS=EMAIL|PASSWORD|PASSWORD_CONFIRMATION|TERMS_ACCEPTANCE|PRIVACY_ACCEPTANCE
PROFESSIONAL_PRIMARY_DATA_COLLECTION=POST_CONTACT_VERIFICATION
PROFESSIONAL_PRIMARY_DATA=PROFESSIONAL_NAME|PROFESSIONAL_LICENSE_NUMBER_OR_NUMBERS|SPECIALTY|PRIMARY_GEOGRAPHIC_CONTEXT
DOCUMENTARY_ACCREDITATION=AFTER_PRIMARY_PROFESSIONAL_DATA
```

### C1-ID-03

```text
EMAIL_VERIFICATION_REQUIRED=true
PHONE_VERIFICATION_REQUIRED=true
LOGIN_BEFORE_EMAIL_VERIFICATION=false
LOGIN_BEFORE_PHONE_VERIFICATION=false
ACCOUNT_ONBOARDING_AFTER_CONTACT_VERIFICATION=true
FULL_MEDICAL_PANEL_BEFORE_ACCREDITATION=false
```

### C1-ID-04

```text
PRE_ACCREDITATION_PRIVATE_AREA=ACCOUNT_AND_ONBOARDING_ONLY
MEDICAL_PANEL_REQUIRES=PHYSICIAN_ACCREDITED_AND_PROFILE_OWNERSHIP_ACTIVE
```

### C1-ID-05

```text
REQUEST_ORIGIN=EXISTING_PROFILE_CLAIM|NEW_PROFILE_REQUEST
REQUEST_ORIGIN_IMMUTABLE=true
DIRECT_CLAIM_ORIGIN_PROFILE_ID_PERSISTENT=true
DIRECT_PROFILE_CLAIM_KEEPS_ORIGINAL_PROFILE_THROUGHOUT_PROCESS=true
LATER_ORIGIN_PROFILE_RESELECTION=false
```

### C1-ID-06

```text
PROFILE_MATCH_PRECEDENCE=1_EXACT_PROFESSIONAL_LICENSE_MATCH|2_COMPOSITE_PROFESSIONAL_MATCH|3_NO_MATCH
COMPOSITE_PROFESSIONAL_MATCH=NORMALIZED_NAME+SPECIALTY+GEOGRAPHIC_CONTEXT+OTHER_AVAILABLE_PROFESSIONAL_ATTRIBUTES
EXACT_PROFESSIONAL_LICENSE_MATCH_HAS_HIGHER_AUTHORITY_THAN_FUZZY_NAME_MATCH=true
MULTIPLE_PROFESSIONAL_LICENSE_NUMBERS_SUPPORTED=true
EACH_EXACT_LICENSE_NUMBER_IS_STRONG_PROFESSIONAL_IDENTIFIER=true
LICENSE_MATCH_ALONE_DOES_NOT_GRANT_ACCREDITATION=true
LICENSE_MATCH_ALONE_DOES_NOT_GRANT_OWNERSHIP=true
```

### C1-ID-07

```text
MATCH_RESOLUTION=RATIFIED_MAPPING_BELOW
UNCLAIMED_EXISTING_PROFILE=CLAIM
ACTIVE_OWNER_AND_SAME_EXISTING_ACCOUNT=ACCOUNT_CREDENTIAL_RECOVERY
ACTIVE_OWNER_AND_SAME_PHYSICIAN_BUT_UNRECOVERABLE_ACCOUNT=OWNERSHIP_RECOVERY_OR_TRANSFER
ACTIVE_OWNER_AND_AMBIGUOUS_OR_CONFLICTING_IDENTITY=OWNERSHIP_CONFLICT_REVIEW
NO_MATCH=NEW_PROFILE_REQUEST_CONTINUES
```

### C1-ID-08

```text
EXACT_LICENSE_MATCH_WITH_NAME_OR_SPECIALTY_DISCREPANCY=STRONG_MATCH_WITH_DATA_DISCREPANCY_HUMAN_REVIEW
SAME_LICENSE_ASSOCIATED_WITH_INCOMPATIBLE_MULTIPLE_PROFILES=DATA_INTEGRITY_CONFLICT_HUMAN_REVIEW
NO_AUTOMATIC_PROFILE_SELECTION_WHEN_DATA_INTEGRITY_CONFLICT=true
```

### C1-ID-09

```text
NEW_PROFILE_CREATED_ONLY_AFTER_ACCREDITATION=true
NEW_PROFILE_APPLICATION_BEFORE_ACCREDITATION=REQUEST_ONLY
FINAL_ANTI_DUPLICATE_CHECK_REQUIRED_BEFORE_PHYSICAL_PROFILE_CREATION=true
FINAL_ANTI_DUPLICATE_CHECK_PRECEDENCE=EXACT_PROFESSIONAL_LICENSE_THEN_COMPOSITE_PROFESSIONAL_MATCH
IF_MATCH_APPEARS_BEFORE_CREATION=DO_NOT_CREATE_DUPLICATE_RESOLVE_EXISTING_PROFILE
```

### C1-ID-10

```text
POST_LOGIN_ROUTING=STATE_BASED_ONBOARDING_ROUTER
ROUTER_PRECEDENCE=EMAIL_VERIFICATION|PHONE_VERIFICATION|PROFESSIONAL_PRIMARY_DATA|PHYSICIAN_ACCREDITATION|REQUEST_ORIGIN_AND_MATCH_RESOLUTION|PROFILE_OWNERSHIP|ACTIVE_MEMBERSHIP_COUNT
EMAIL_NOT_VERIFIED=EMAIL_VERIFICATION
PHONE_NOT_VERIFIED=PHONE_VERIFICATION
PROFESSIONAL_DATA_INCOMPLETE=PROFESSIONAL_DATA
ACCREDITATION_NOT_STARTED=ACCREDITATION_ONBOARDING
ACCREDITATION_INCOMPLETE=ACCREDITATION_ONBOARDING
ACCREDITATION_PENDING=ACCREDITATION_STATUS
ACCREDITATION_ACTION_REQUIRED=ACCREDITATION_CORRECTION
ACCREDITATION_REJECTED=ACCREDITATION_RESULT_SUPPORT
ACCREDITED_AND_EXISTING_CLAIM_PENDING=CLAIM_STATUS
ACCREDITED_AND_OWNERSHIP_CONFLICT=OWNERSHIP_REVIEW_STATUS
ACCREDITED_AND_NEW_REQUEST_AND_NO_MATCH=PROFILE_CREATION_RESOLUTION
ACCREDITED_AND_ZERO_ACTIVE_PROFILES=OWNERSHIP_OR_PROFILE_ONBOARDING
ACCREDITED_AND_ONE_ACTIVE_PROFILE=MEDICAL_DASHBOARD
ACCREDITED_AND_MULTIPLE_ACTIVE_PROFILES=PROFILE_SELECTOR
ACCREDITATION_REMINDER_SEQUENCE_REQUIRED=true
REMINDER_PARAMETERS_FINALIZED_IN=C8_AND_UX
REMINDER_STOP_MINIMUM=ACCREDITATION_SUBMITTED|ACCREDITATION_APPROVED|ACCOUNT_DISABLED|REMINDER_POLICY_COMPLETED
```

### C1-SEC-01

```text
REMEMBER_SESSION=false
LONG_LIVED_AUTH_SESSION=false
```

### C1-SEC-02

```text
CUSTOMER_IDLE_TIMEOUT_SECONDS=3600
CUSTOMER_ABSOLUTE_TIMEOUT_SECONDS=43200
SESSION_TOUCH_INTERVAL_SECONDS=300
MAX_CONCURRENT_CUSTOMER_SESSIONS=5
PLATFORM_OPERATOR_STRICTER_SESSION_POLICY_REQUIRED=true
```

### C1-SEC-03

```text
LOGIN_SECURITY_POLICY=HYBRID
RATE_LIMIT=true
TEMPORARY_SECURITY_LOCK=true
MANUAL_ACCOUNT_DISABLE=true
LOCKED_AND_DISABLED_ARE_DISTINCT=true
SESSION_REVOCATION_ON_SECURITY_STATE_CHANGE=true
```

### C1-SEC-04

```text
PASSWORD_RECOVERY=DEDICATED_FLOW
PASSWORD_RESET_AUTO_LOGIN=false
PASSWORD_RESET_RETURNS_TO_LOGIN=true
PASSWORD_CHANGE_REVOKES_PRIOR_SESSIONS=true
ACCOUNT_CREDENTIAL_RECOVERY_DISTINCT_FROM_PROFILE_OWNERSHIP_RECOVERY=true
```

### C1-OWN-01

```text
PRIMARY_OWNER_PER_PROFILE=ZERO_OR_ONE
UNCLAIMED_PUBLISHED_PROFILE_ALLOWED=true
ONE_ACCOUNT_MAY_OWN_MULTIPLE_PROFILES=true
PLATFORM_OPERATOR_IS_NOT_OWNER=true
PRACTICE_OPERATOR_IS_NOT_OWNER=true
DELEGATED_ADMINISTRATOR_IS_NOT_OWNER=true
```

### C1-OWN-02

```text
BASE_ACCREDITATION=OFFICIAL_IDENTITY+PROFESSIONAL_LICENSE_VALIDATION
ADDITIONAL_EVIDENCE_ONLY_WHEN_REQUIRED=true
LICENSE_MATCH_ALONE_DOES_NOT_GRANT_ACCREDITATION_OR_OWNERSHIP=true
```

### C1-OWN-03

```text
INITIAL_LAUNCH_ACCREDITATION_REVIEW=HUMAN_REQUIRED
NORMAL_CASE_APPROVERS=1
CONFLICT_OR_TRANSFER_REQUIRES_SECOND_APPROVAL=true
FUTURE_AUTOMATION_ALLOWED_BUT_NOT_RATIFIED=true
```

### C1-OWN-04

```text
PUBLISHED_OWNERLESS_PROFILE_IS_A_NORMAL_SUPPORTED_STATE=true
OWNERLESS_PROFILE_CAN_BE_CLAIMED=true
ORIGINAL_REQUEST_ORIGIN_AND_TARGET_PROFILE_MUST_BE_PRESERVED=true
APPROVED_ACCREDITED_CLAIM_ON_OWNERLESS_PROFILE=PRIMARY_OWNER_ASSIGNMENT
```

### C1-OWN-05

```text
ACTIVE_OWNER_NEVER_SILENTLY_REPLACED=true
SAME_EXISTING_ACCOUNT=CREDENTIAL_RECOVERY
SAME_PHYSICIAN_DIFFERENT_UNRECOVERABLE_ACCOUNT=OWNERSHIP_RECOVERY_TRANSFER
AMBIGUOUS_OR_CONFLICTING_IDENTITY=OWNERSHIP_CONFLICT_REVIEW
```

### C1-OWN-06

```text
NORMAL_OWNERSHIP_TRANSFER_REQUIRES_CURRENT_OWNER_APPROVAL=true
NEW_OWNER_MUST_BE_ACCREDITED=true
EXCEPTIONAL_TRANSFER_REQUIRES_REACCREDITATION=true
EXCEPTIONAL_TRANSFER_REQUIRES_SECOND_PLATFORM_APPROVAL=true
MANDATORY_TRANSFER_REASON=true
AUDIT_REQUIRED=true
LAST_PRIMARY_OWNER_CANNOT_BE_REMOVED_WITHOUT_ATOMIC_SUCCESSOR_ASSIGNMENT=true
```

### C1-OWN-07

```text
PLATFORM_PROFILE_INGESTION_CONTINUES=true
AUTHORIZED_PLATFORM_STAFF_MAY=CREATE|EDIT|CURATE|PUBLISH|UNPUBLISH|SUSPEND|REACTIVATE|LOGICALLY_DELETE
AUTHORIZED_PLATFORM_STAFF_PROFILE_ACTIONS=CREATE|EDIT|CURATE|PUBLISH|UNPUBLISH|SUSPEND|REACTIVATE|LOGICALLY_DELETE
PLATFORM_PROFILE_OPERATIONS_APPLY_TO=OWNERLESS_PROFILES|OWNED_PROFILES
PLATFORM_PROFILE_OPERATION_DOES_NOT_CREATE_OWNERSHIP=true
OWNERLESS_PUBLISHED_PROFILES_ARE_ALLOWED=true
OWNERLESS_PUBLISHED_PROFILES_ARE_CLAIMABLE=true
PROFILE_PUBLICATION_STATE_IS_DISTINCT_FROM_OWNERSHIP_STATE=true
PLATFORM_PROFILE_CREATION_REQUIRES_ANTI_DUPLICATE_MATCH=true
ANTI_DUPLICATE_PRECEDENCE=EXACT_PROFESSIONAL_LICENSE_THEN_COMPOSITE_PROFESSIONAL_MATCH
PROFILE_PROVENANCE_MUST_BE_PRESERVED=true
EVERY_PLATFORM_PROFILE_MUTATION_REQUIRES_TRACEABILITY=true
TRACEABILITY_MINIMUM=ACTOR|ROLE|ACTION|TARGET_PROFILE|TIMESTAMP|REASON_WHEN_REQUIRED|BEFORE_STATE|AFTER_STATE|RESULT|CORRELATION_ID
DEFAULT_DELETE_BEHAVIOR=LOGICAL_DELETE_OR_UNPUBLISH_WITH_HISTORY_PRESERVED
PHYSICAL_PURGE=EXCEPTIONAL_SEPARATE_AUTHORIZED_POLICY
PROFILE_DIMENSIONS=PROFILE_CREATION_ORIGIN|PROFILE_PUBLICATION_STATE|PROFILE_OWNERSHIP_STATE|PROFILE_ACCREDITATION_STATE
```

### C1-OPS-01

```text
PRACTICE_OPERATOR_ACCOUNT_IS_INDEPENDENT=true
PRACTICE_OPERATOR_ENTRY=INVITATION_ONLY
EXISTING_OPERATOR_ACCOUNT_IS_REUSED=true
TARGET_PROFILE_AUTHORITY_MUST_APPROVE_ASSIGNMENT=true
ONE_PHYSICIAN_CANNOT_GRANT_ACCESS_TO_ANOTHER_PHYSICIAN_PROFILE=true
SHARED_OPERATOR_PROPOSAL_REQUIRES_TARGET_PROFILE_APPROVAL=true
```

### C1-OPS-02

```text
ONE_OPERATOR_ACCOUNT_MAY_HAVE_MULTIPLE_PROFILE_ASSIGNMENTS=true
PROFILE_ASSIGNMENTS_ARE_INDEPENDENT=true
NO_PERMISSION_INHERITANCE_BETWEEN_PROFILES=true
SERVER_VALIDATED_PROFILE_SELECTOR_REQUIRED=true
```

### C1-OPS-03

```text
PRACTICE_WORKSPACE_REQUIRED=false
PRACTICE_WORKSPACE_OPTIONAL=true
WORKSPACE_REQUIRES_EXPLICIT_CREATION=true
SAME_ADDRESS_DOES_NOT_CREATE_WORKSPACE=true
SHARED_OPERATOR_DOES_NOT_CREATE_WORKSPACE=true
WORKSPACE_MEMBERSHIP_DOES_NOT_GRANT_PROFILE_ACCESS=true
WORKSPACE_CAN_BE_CREATED_LATER_WITHOUT_RECREATING_ACCOUNTS_OR_ASSIGNMENTS=true
```

### C1-OPS-04

```text
PHYSICIAN_DEACTIVATION_ONLY_AFFECTS_ASSIGNMENTS_TO_THAT_PROFILE=true
PHYSICIAN_LOCATION_CHANGE_DOES_NOT_AUTOMATICALLY_REVOKE_OPERATOR=true
CHANGING_ONE_PHYSICIAN_OPERATOR_DOES_NOT_AFFECT_OTHER_PROFILE_ASSIGNMENTS=true
```

### C1-OPS-04A

```text
A_PHYSICIAN_MAY_REPLACE_A_SHARED_OPERATOR_INDEPENDENTLY=true
REVOKING_OPERATOR_FROM_ONE_PROFILE_DOES_NOT_AFFECT_OTHER_PROFILE_ASSIGNMENTS=true
NEW_OPERATOR_ASSIGNMENT_DOES_NOT_REQUIRE_REMOVING_OPERATOR_FROM_OTHER_PHYSICIANS=true
OPERATOR_HANDOFF_OVERLAP_SUPPORTED=true
ASSIGNMENT_START_AND_END_DATES_SUPPORTED=true
MULTIPLE_OPERATORS_PER_PROFILE_MAY_COEXIST=true
```

### C1-OPS-05

```text
PER_PROFILE_ASSIGNMENT_REQUIRED=true
PER_LOCATION_SCOPE_SUPPORTED=true
```

### C1-OPS-06

```text
PER_ASSIGNMENT_CAPABILITY_MODEL_REQUIRED=true
STANDARD_OPERATOR_PATIENT_ACCESS=SCHEDULING_MINIMUM
SCHEDULING_MINIMUM_MAY_INCLUDE=PATIENT_NAME|CONTACT_DATA|APPOINTMENT_ADMINISTRATIVE_DATA
STANDARD_OPERATOR_CLINICAL_ACCESS=false
ARE_DENIED_BY_DEFAULT=CLINICAL|CASES|DOCUMENTS|MEDICAL_HISTORY|PRESCRIPTIONS
CLINICAL_CASES_DOCUMENTS_MEDICAL_HISTORY_PRESCRIPTIONS_DENIED_BY_DEFAULT=true
FUTURE_CLINICAL_STAFF_ACCESS_REQUIRES_SEPARATE_EXPLICIT_CAPABILITY=true
```

### C1-OPS-07

```text
PROFILE_LEVEL_REVOCATION_SUPPORTED=true
WORKSPACE_LEVEL_REVOCATION_SUPPORTED_WHEN_WORKSPACE_EXISTS=true
ACCOUNT_SECURITY_SUSPENSION_BLOCKS_ALL_ASSIGNMENTS_WITHOUT_DELETING_RELATIONSHIPS=true
```

### C1-OPS-08

```text
TEMPORARY_OPERATOR_ASSIGNMENTS_SUPPORTED=true
ASSIGNMENT_VALIDITY_WINDOW_SUPPORTED=true
ASSIGNMENT_STATES=PENDING|ACTIVE|SUSPENDED|REVOKED|EXPIRED|SUSPENDED_BY_ENTITLEMENT
```

### C1-OPS-09

```text
HARDCODED_OPERATOR_LIMIT=DISALLOWED
OPERATOR_LIMIT_MUST_COME_FROM=ENTITLEMENT|PLATFORM_POLICY
OPERATOR_LIMIT_SOURCE=ENTITLEMENT|PLATFORM_POLICY
```

### C1-OPS-10

```text
EVERY_OPERATOR_ASSIGNMENT_MUTATION_REQUIRES_TRACEABILITY=true
EVERY_OPERATOR_BUSINESS_ACTION_REQUIRES_ACTOR_ATTRIBUTION=true
```

### C1-PLAT-01

```text
PLATFORM_ROLE_FAMILIES=PLATFORM_ADMIN|IDENTITY_CLAIMS_OPERATOR|PROFILE_OPERATIONS|CUSTOMER_SUPPORT|BILLING_OPERATIONS|AUDIT_REVIEWER
NORMAL_SUPERADMIN_MODEL=false
MULTIPLE_INTERNAL_ROLES_ONLY_WHERE_POLICY_PERMITS=true
```

### C1-PLAT-02

```text
AUTHORIZATION_PLANES=PLATFORM_AUTHORIZATION_PLANE|PRACTICE_AUTHORIZATION_PLANE|CALLCENTER_SERVICE_AUTHORIZATION_PLANE
AUTHORIZATION_PLANES_MUST_BE_DISTINCT=true
NO_DEFAULT_CLINICAL_OR_PATIENT_ACCESS_FOR_PLATFORM_ROLES=true
```

### C1-PLAT-03

```text
PROFILE_OPERATIONS_MAY=CREATE|EDIT|CURATE|PUBLISH|UNPUBLISH|SUSPEND|REACTIVATE|LOGICALLY_DELETE|RESTORE_WHEN_POLICY_ALLOWS
PROFILE_OPERATIONS_ACTIONS=CREATE|EDIT|CURATE|PUBLISH|UNPUBLISH|SUSPEND|REACTIVATE|LOGICALLY_DELETE|RESTORE_WHEN_POLICY_ALLOWS
PROFILE_OPERATIONS_APPLIES_TO_OWNERLESS_AND_OWNED_PROFILES=true
ANTI_DUPLICATE_MATCH_REQUIRED=true
EVERY_PROFILE_MUTATION_TRACEABLE=true
PLATFORM_PROFILE_OPERATION_DOES_NOT_CREATE_OWNERSHIP=true
BACKOFFICE_ADDITION=BO-F16_CREATE_CURATE_PUBLISH_AND_LIFECYCLE_MEDICAL_PROFILE
```

### C1-PLAT-04

```text
CLAIMS_AND_ACCREDITATION_ROLE_SEPARATE=true
NORMAL_REVIEWER_COUNT=1
CONFLICT_TRANSFER_AND_EXCEPTION_REQUIRE_SECOND_APPROVAL=true
SELF_APPROVAL=false
```

### C1-PLAT-05

```text
CUSTOMER_SUPPORT_CAN_TRIGGER_SAFE_RECOVERY=true
CUSTOMER_SUPPORT_CAN_REVOKE_SESSIONS_WHEN_POLICY_ALLOWS=true
SUPPORT_CANNOT_VIEW_PASSWORD=true
SUPPORT_CANNOT_SET_PASSWORD=true
SUPPORT_CANNOT_VIEW_RAW_TOKEN=true
SUPPORT_IMPERSONATION=false
SUPPORT_CANNOT_DIRECTLY_GRANT_OWNERSHIP=true
```

### C1-PLAT-06

```text
BILLING_INITIAL_SCOPE=READ_AND_DIAGNOSTIC
NO_UNRATIFIED_FINANCIAL_MUTATION=true
AUDIT_REVIEWER_SCOPE=SCOPED_READ_ONLY
AUDIT_REVIEWER_CANNOT_MUTATE_AUDIT=true
```

### C1-PLAT-07

```text
INTERNAL_SELF_GRANT=false
ALL_INTERNAL_ROLE_MUTATIONS_REQUIRE_STEP_UP=true
ALL_INTERNAL_ROLE_MUTATIONS_REQUIRE_REASON=true
PLATFORM_ADMIN_GRANT_REQUIRES_SECOND_APPROVER=true
BREAK_GLASS_REQUIRES_SECOND_APPROVER=true
TEMPORARY_INTERNAL_ROLES_SUPPORTED=true
ROLE_ASSIGNMENT_MAY_HAVE_EXPIRY=true
GRANTOR_CANNOT_BE_BENEFICIARY=true
PRIVILEGED_GRANT_APPROVER_MUST_DIFFER_FROM_GRANTOR=true
```

### C1-PLAT-08

```text
PLATFORM_OPERATOR_MFA_REQUIRED=true
SENSITIVE_ACTION_STEP_UP_REQUIRED=true
SUPPORT_IMPERSONATION=false
BREAK_GLASS_SEPARATE_POLICY_REQUIRED=true
```

### C1-PLAT-09

```text
PLATFORM_BACKOFFICE_PHASE=C11
C11_IS_SEPARATELY_AUTHORIZED=true
C11_RUNS_AFTER_IDENTITY_CORE=true
C11_REQUIRED_BEFORE_MEDICAL_LAUNCH=true
BACKOFFICE_MVP_WORKFLOW_COUNT=16
```

### C1-UX-01

```text
PRESERVE_MXMED_VISUAL_LANGUAGE=true
REUSE_CURRENT=LOGO|TYPOGRAPHY|COLORS|FORM_CONTROLS|BUTTON_LANGUAGE|CARD_STYLE_WHERE_APPROPRIATE
EXISTING_SIMPLE_IDENTITY_UI=REUSE_WITH_CHANGES
COMPLEX_ONBOARDING_MAY_USE_EXPANDED_MULTI_STEP_LAYOUT=true
TOTAL_VISUAL_REDESIGN=false
CURRENT_CARD_GEOMETRY_IS_NOT_MANDATORY=true
```

### C1-UX-02

```text
IDENTITY_MOBILE_MIN_WIDTH=320px
IDENTITY_MOBILE_TARGET_RANGE=320_TO_430px
HORIZONTAL_OVERFLOW=DISALLOWED
ALL_IDENTITY_CRITICAL_FLOWS_MUST_WORK_ON_MOBILE=true
TOUCH_OPERATION_REQUIRED=true
KEYBOARD_OPERATION_REQUIRED=true
PRIMARY_CTA_MUST_REMAIN_REACHABLE=true
CRITICAL_ERRORS_MUST_NOT_BE_HIDDEN_BY_BREAKPOINT=true
LEGAL_COPY_MUST_WRAP=true
FINAL_GLOBAL_UI3_REMAINS_SEPARATE=true
```

### C1-COMMS-01

```text
EMAIL_VERIFICATION_CHANNEL=EMAIL
PASSWORD_RECOVERY_CHANNEL=EMAIL
INVITATION_CHANNEL=EMAIL
PHONE_VERIFICATION_PRIMARY_CHANNEL=SMS
PUBLIC_BOOKING_OTP_PRIMARY_CHANNEL=SMS
NOTIFICATION_ORCHESTRATION_MAY_BE_SHARED=true
PURPOSE_SPECIFIC_PORTS_REQUIRED=true
IDENTITY_AGENDA_ISOLATION=TOKEN|TTL|RATE_LIMIT|TEMPLATE|DELIVERY_HISTORY|RETRY_POLICY|PURPOSE
UNDERLYING_PROVIDER_MAY_BE_SHARED_OR_DIFFERENT=true
PROVIDER_VENDOR_NOT_RATIFIED_YET=true
PROVIDER_VENDOR_SELECTION_PHASE=C8_SEPARATE_AUTHORIZATION
```

### C1-MFA-01

```text
PLATFORM_MFA_REQUIRED=true
INITIAL_PLATFORM_MFA_METHOD=TOTP
RECOVERY_CODES_REQUIRED=true
SENSITIVE_ACTION_STEP_UP=FRESH_MFA_CHALLENGE
SMS_IS_NOT_PRIMARY_PLATFORM_ADMIN_MFA=true
SUPPORT_IMPERSONATION=false
PASSKEY_WEBAUTHN_FUTURE_COMPATIBLE=true
CORPORATE_SSO_FUTURE_COMPATIBLE=true
BREAK_GLASS_REQUIRES_SEPARATE_CONTROL=true
```

## Appendix B — Additional ratified actor and concurrency authority

### Multi-actor operator model

```text
SUPPORTED_PRACTICE_OPERATION_ACTORS=HUMAN_PRACTICE_OPERATOR|AI_PRACTICE_OPERATOR|MXMED_CALLCENTER_SERVICE
MULTIPLE_OPERATOR_TYPES_MAY_COEXIST=true
MULTIPLE_HUMAN_OPERATORS_PER_PROFILE_SUPPORTED=true
ASSIGNMENT_DIMENSIONS=STATUS|CAPABILITIES|LOCATION_SCOPE|SCHEDULE_SCOPE|VALIDITY_WINDOW|ENTITLEMENT
PHYSICIAN_ASSIGNMENT_ACTIONS=ACTIVATE|SUSPEND|REACTIVATE|REVOKE|REPLACE
SUSPENSION_DOES_NOT_DELETE_ASSIGNMENT_HISTORY=true
PLAN_OR_SERVICE_ENTITLEMENT_GATES_EFFECTIVE_ACCESS=true
ENTITLEMENT_LOSS_DOES_NOT_DELETE_ASSIGNMENT=true
ENTITLEMENT_LOSS_MAY_RESULT_IN=SUSPENDED_BY_ENTITLEMENT
OPERATOR_TYPES_MAY_HAVE_OVERLAPPING_SCHEDULES=true
AUTHORIZATION_AND_SERVICE_ORCHESTRATION_ARE_DISTINCT=true
CONCURRENT_APPOINTMENT_MUTATIONS_REQUIRE_SERVER_SIDE_CONFLICT_PROTECTION=true
EVERY_ACTION_REQUIRES_REAL_ACTOR_ATTRIBUTION=true
```

### AI operator authority

```text
AI_PRACTICE_OPERATOR_SUPPORTED=true
AI_OPERATOR_IS_NON_HUMAN_ACTOR=true
AI_OPERATOR_REQUIRES_EXPLICIT_PROFILE_ASSIGNMENT=true
AI_OPERATOR_REQUIRES_ENTITLEMENT=true
AI_OPERATOR_CAPABILITIES_ARE_EXPLICIT=true
AI_OPERATOR_IS_NOT_OWNER=true
AI_OPERATOR_CLINICAL_ACCESS_BY_DEFAULT=false
AI_OPERATOR_ACTIONS_REQUIRE_TRACEABILITY=true
AI_OPERATOR_KILL_SWITCH_REQUIRED=true
AI_OPERATOR_HUMAN_ESCALATION_REQUIRED=true
AI_OPERATOR_ASSIGNMENT_INDEPENDENT_FROM_HUMAN_OPERATORS=true
AI_ASSIGNMENT_DIMENSIONS=LOCATION_SCOPE|SCHEDULE_SCOPE|CAPABILITIES|STATUS|ENTITLEMENT
AI_PRODUCT_IMPLEMENTATION_AUTHORIZED=false
```

### MXMed call-center authority

```text
MXMED_CALLCENTER_SERVICE_SUPPORTED=true
CALLCENTER_SERVICE_REQUIRES_EXPLICIT_PROFILE_AUTHORIZATION=true
CALLCENTER_SERVICE_REQUIRES_ENTITLEMENT_OR_SERVICE_CONTRACT=true
CALLCENTER_OPERATOR_ACTS_AS=PLATFORM_MANAGED_PRACTICE_OPERATOR
CALLCENTER_OPERATOR_IS_NOT_PROFILE_OWNER=true
CALLCENTER_AUTHORIZATION_PLANE=PRACTICE_DELEGATED_SERVICE
CALLCENTER_AUTHORIZATION_IS_SEPARATE_FROM=INTERNAL_PLATFORM_ADMINISTRATION
PHYSICIAN_AUTHORIZES_CALLCENTER_SERVICE=true
MXMED_MAY_ROTATE_AUTHORIZED_CALLCENTER_AGENTS=true
CALLCENTER_TEAM_MEMBERS_MAY_ROTATE_WITHOUT_NEW_PHYSICIAN_INVITATION=true
EVERY_CALLCENTER_ACTION_MUST_IDENTIFY_REAL_HUMAN_ACTOR=true
SHARED_CALLCENTER_ACCOUNTS=DISALLOWED
DISABLING_CALLCENTER_SERVICE_REVOKES_ONLY_CALLCENTER_ACCESS=true
DUAL_DUTY_REQUIRES_SEPARATE_ROLE_ASSIGNMENTS_AND_EFFECTIVE_CONTEXTS=true
PLATFORM_ADMIN_AUTHORITY_MUST_NOT_FLOW_INTO_CALLCENTER_PRACTICE_AUTHORITY=true
```

### Public booking concurrency authority

```text
PUBLIC_BOOKING_ACTOR_SUPPORTED=true
CANONICAL_SLOT_STATES=AVAILABLE|HELD|BOOKED
FIRST_VALID_SERVER_SIDE_ATOMIC_ACQUISITION_WINS=true
CLIENT_CLICK_TIMESTAMP_IS_NOT_AUTHORITY=true
PUBLIC_BOOKING_REQUIRES_TEMPORARY_EXCLUSIVE_HOLD=true
PUBLIC_SLOT_HOLD_TTL_CONFIGURABLE=true
SERVER_HOLD_EXPIRATION_IS_AUTHORITATIVE=true
COUNTDOWN_IS_USER_FEEDBACK_ONLY=true
EXPIRED_HOLD_RETURNS_SLOT_TO_AVAILABILITY=true
EXPIRED_BOOKING_MUST_RESTART_AVAILABILITY_FLOW=true
CONFIRM_AFTER_EXPIRATION=REJECTED
ONE_ACTIVE_PUBLIC_HOLD_PER_BOOKING_CONTEXT_BY_DEFAULT=true
UNBOUNDED_HOLD_EXTENSION=DISALLOWED
ACTIVE_HOLD_CANNOT_BE_OVERRIDDEN_BY_OTHER_NORMAL_ACTORS=true
ALL_ACTOR_TYPES_USE_THE_SAME_SERVER_AUTHORITATIVE_SLOT_AVAILABILITY=true
CONCURRENT_SLOT_MUTATION_REQUIRES_ATOMIC_CONFLICT_PROTECTION=true
EXPIRED_HOLDS_MUST_NOT_REQUIRE_BACKGROUND_CLEANUP_FOR_CORRECTNESS=true
EVERY_HOLD_AND_BOOKING_TRANSITION_REQUIRES_TRACEABILITY=true
PUBLIC_PATIENT_BOOKING=TEMPORARY_HOLD_TO_DATA_TO_OTP_TO_CONFIRMATION
AUTHORIZED_OPERATOR_BOOKING=MAY_USE_ATOMIC_DIRECT_BOOKING
PUBLIC_AND_OPERATOR_BOOKING_SHARE_SAME_SLOT_CONCURRENCY_AUTHORITY=true
AGENDA_PUBLIC_SLOT_CONCURRENCY_IMPLEMENTATION_PROVEN=false
```

## Appendix C — Scope locks and next action

```text
C0_DISCOVERY=CLOSED
C1_DIRECTOR_FUNCTIONAL_AND_UX_RATIFICATION=CLOSED
C2_PRODUCTIVE_IDENTITY_COMPOSITION=CLOSED
C2_IMPLEMENTATION_AUTHORIZED=false
C3=TECHNICAL_PASS
C3_PHASE_CLOSED=false
C3_IMPLEMENTATION_AUTHORIZED=false
C4=NOT_STARTED
C4_IMPLEMENTATION_AUTHORIZED=false
C11_IS_SEPARATELY_AUTHORIZED=true
C11_IMPLEMENTATION_AUTHORIZED=false
AUDIT_INFRASTRUCTURE=CLOSED
COMMERCIAL_PROFILES=POST_INITIAL_MEDICAL_LAUNCH
MONTH_VIEW_LAUNCH_SCOPE=OUT
GALLERY_COMMERCIAL_COMMITMENT_VERIFICATION_PENDING=true
PROVIDER_VENDOR_NOT_RATIFIED_YET=true
AI_PRODUCT_IMPLEMENTATION_AUTHORIZED=false
CALLCENTER_IMPLEMENTATION_AUTHORIZED=false
PLATFORM_BACKOFFICE_IMPLEMENTATION_AUTHORIZED=false
GIT_PROMOTION_AUTHORIZED=false
NEXT_REQUIRED_ACTION=C3_ISOLATED_PHYSICAL_VALKEY_VALIDATION
```
