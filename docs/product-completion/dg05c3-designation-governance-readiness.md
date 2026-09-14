# DG05C3 professional designation: governance discovery and local UI

Status: Director-approved visual closeout. Advisor governance remains deferred and blocked by missing canonical physician admission authority. This closeout includes only the approved compact layout, contextual guidance and documentation; no advisor backend edit path, capability, admission authority or audit event was activated.

## ADM01 accepted governance checkpoint

Accepted audit baseline: `design/physician-crd03-credentials-ui-v1` at `8cf28b1db4e2d951420133c3f3550e3b3d3b44e6`.

```text
ADM01_STATUS=CLOSED_ACCEPTED
CANONICAL_ADMISSION_AUTHORITY_FOUND=false
NEXT_GOVERNANCE_PHASE=ADM02_IMPLEMENT_CANONICAL_PHYSICIAN_ADMISSION_AUTHORITY
ADM02_STATUS=DEFERRED_BY_DIRECTOR
DEFER_REASON=Continue physician Admin UI visual refinement before resuming internal governance implementation.
CURRENT_PRIORITY=PHYSICIAN_ADMIN_UI_VISUAL_REFINEMENT
ADVISOR_DESIGNATION_EDIT_STATUS=DEFERRED_UNTIL_ADMISSION_AUTHORITY
LEGACY_BACKFILL_DECISION=DEFERRED_TO_DIRECTOR
```

ADM01 found no persisted, auditable, efficiently queryable current-state authority for whole-physician admission. Profile existence, `profile_status`, publication/public eligibility, verified identity, verified credentials, membership/ownership, account activation, plan/subscription and media approval are separate authorities and do not establish admission.

`source_type=admission_approved` is identity/credential provenance, not global admission authority. No production caller of `VerifiedPhysicianIdentityService::provisionFromTrustedAuthority` establishes admission; the identity calls using that source are tests. No existing capability semantically authorizes admission decisions or advisor professional-designation edits. The product execution guide describes human accreditation in C4, but that phase is documented as not started and not authorized; ADM02 must align with that contract rather than create a parallel decision authority.

Deferred sequence: continue physician Admin visual refinement; later, with Director authorization, resume ADM02 to define and implement canonical admission authority; after its implementation and acceptance, continue Internal Advisor Admin construction; only then wire governed advisor editing of the same canonical field, `profiles_doctors.professional_designation`. No shadow designation fields or ownership bypass through the physician self-edit endpoint are permitted.

Future advisor authorization requires an active auth account, active internal/staff state, an explicit capability and physician admission `APPROVED`. Missing, ambiguous or unavailable admission authority must fail closed. Governance class or role name alone is not an operational permission.

Non-authorized design direction: one current-state record per `doctor_id`, with `status`, `decided_at`, `decided_by_internal_account_id`, `created_at` and `updated_at`; provenance/reference only if justified. Preferred initial states are `PENDING`, `APPROVED` and `REJECTED`, with existing canonical audit infrastructure for history. No table name is finalized and no schema implementation is authorized.

Before ADM02 implementation, the Director must decide how existing physician records are classified. No blind legacy backfill is authorized: existence, visibility, payment, identity, credentials, ownership, account state or unrelated dates must not fabricate approval, decision timestamps or deciding actors. Resume only under separate Director authorization resolving that classification and the admission contract. This checkpoint changes documentation only; it activates no governance or product behavior.

## Current authority inventory

- `InternalCapabilityCatalog` defines six Media Review operational capabilities and `internal_advisors_manage`. None authorizes physician profile/designation editing.
- `internal_operator_grants` is the sole operational assignment authority. `InternalOperatorGrantRepository::activeCapabilities` joins active canonical accounts and ACTIVE internal staff, excludes revoked grants and filters through the executable catalog. Governance class alone grants no product access.
- `internal_staff` owns Director/Master/Advisor classes and internal suspension. `internal_capability_delegations` authorizes Masters to administer explicitly delegated capabilities; it does not grant operational use.
- `InternalGovernanceService` and `InternalOperatorAuthority` are the existing session/governance extension points. There is no general internal physician profile mutation service or HTTP route. Internal credential provisioning is a separate authority and remains untouched.
- Physician designation editing already uses the existing private profile PATCH/controller/repository and writes `profiles_doctors.professional_designation`. PublicProfileRepository/PublicProfileController consume that same field.
- `CanonicalAuditWriter` plus `JoinedPdoCanonicalAuditTransactionAdapter` is the existing privileged mutation audit infrastructure. Internal governance and credential actions demonstrate joined update/audit transactions with rollback on audit failure. There is no existing advisor designation audit producer.

## Missing admission authority

Repository-wide source/schema searches and read-only local `information_schema` inventory found no canonical physician admission state, admission repository or approval transaction. `profiles_doctors.profile_status` is normalized as draft/pending_review/active/hidden/suspended/removed and is consumed by public eligibility; `is_public_candidate` is also a publication flag. Neither is established as the requested enduring admission authority.

`profiles_verified_identities.source_type=admission_approved` and the equivalent credential provenance label identify record origins. Identity can also originate from internal provisioning, governed correction or synthetic tests. These provenance labels do not define an enduring physician admission lifecycle or a canonical transaction that can own approval-time designation changes. PROFILE_CLAIM_APPROVED has audit catalog entries, but the current public contract explicitly reports `claim_source_ready=false`; there is no executable physician admission transaction to reuse.

Before advisor writes can be implemented, identify or separately define the authoritative admission state and approval transaction, including how previously admitted but currently hidden/suspended profiles are handled. This is a product authority dependency, not a frontend approval requirement. The local UI work does not establish an admission gate.

## Governed implementation awaiting that authority

Reuse IW01 rather than introduce another permission model. No existing capability covers the requested mutation. Proposed narrow executable key: `physician_professional_designation_edit`; not registered or granted. Proposed privileged event: `PHYSICIAN_PROFESSIONAL_DESIGNATION_CHANGED`; not registered or emitted.

The future service must resolve the canonical actor/session, require active account/staff and the explicit capability, lock/recheck account/staff/grants and canonical admission authority, and update only `profiles_doctors.professional_designation`. It must append the actor, doctor, field, previous/new values, timestamp and action context through the canonical writer in the same transaction. The canonical approval transaction may explicitly set a value if its authority owns that action; no synthetic approval bypass or automatic specialty-derived overwrite is permitted. Actor-independent public propagation must continue through the existing canonical field.

Advisor-facing Internal Workspace UI remains deferred; no frontend was built. Advisor grant/status/admission/audit/public mutation tests remain blocked until the canonical admission dependency is available.

## Completed local physician UI and QA

Permanent designation prose was replaced by focus-only contextual guidance tied to the existing `aria-describedby` ID. The absolutely positioned note disappears on blur, reserves no layout height, accepts no pointer events and does not interrupt typing. No JavaScript, backend, save, media or data authority changes were made.

The approved DG05C2 shared desktop-row layout remains. Prefix and designation stay above the names. Both desktop media cards are 460 x 160 px, their previews remain 120 x 120 px, and the complete stack ends exactly at the second surname field. Bio textarea top moved from 656.9375 to 632.59375 px on both 1440 x 900 and 1366 x 768: 24.34375 px gained. On 390 x 844, Bio top moved from 1772.90625 to 1726.03125 px: 46.875 px gained. Controls remain 42 px high. No horizontal overflow.

Temporary browser checks prove hidden/focus/blur guidance, description association, nonblocking hit behavior, unchanged layout when help is visible, mobile containment, media chooser wiring, accessible name draft and exact desktop alignment even when the conditional public-name preview appears. ExplicitSaveNavigationBrowserTest and PrefixConfirmationBrowserTest pass with mocked persistence. A temporary adaptation of ProfessionalDesignationPersistenceTest runs only in an empty disposable schema with one synthetic physician, verifies canonical persistence/public DTO propagation, rolls back test values and removes the owned schema. The real local runtime denies another doctor's designation PATCH with HTTP 403 before mutation.

Temporary screenshots and metrics are under `/tmp/mxmed-dg05c3/`; they are not repository artifacts. Director Leticia's private profile and real database/media snapshots are compared before/after. Advisor security/public propagation tests are not claimed as passing.
