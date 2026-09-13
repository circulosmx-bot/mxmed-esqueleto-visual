# DG05C3 professional designation: governance discovery and local UI

Status: Director-approved visual closeout. Advisor governance remains deferred and blocked by missing canonical physician admission authority. This closeout includes only the approved compact layout, contextual guidance and documentation; no advisor backend edit path, capability, admission authority or audit event was activated.

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
