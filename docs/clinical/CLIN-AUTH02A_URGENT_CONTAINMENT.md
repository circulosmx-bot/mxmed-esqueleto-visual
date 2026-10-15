# CLIN-AUTH02A — urgent clinical authorization containment

Status: **CONTAINMENT COMPLETE / PROFESSIONAL AUTHORITY CONTRACT REQUIRED**

Source baseline: `d5856957ff573278957814a5a6f53d5e1fab40d6`

Date: 2026-10-15

This change closes the anonymously reachable legacy route family and makes the reviewed professional-only prescription, physician-consent, and versioned professional-document transitions fail closed. It does not add roles, infer qualification from membership, migrate data, or create an account-to-professional relationship.

## Reproduction and exposure boundary

Before remediation, anonymous requests with a nonexistent synthetic patient reached clinical handlers: history and physical examination returned `404`, cases list returned `404`, and active case returned `200`. The protected timeline returned `401`. Source inspection confirmed that the matching `PUT`, `POST`, and `PATCH` handlers reached mutation code without an Identity guard. This established source and local HTTP reachability plus mutation possibility; it did not establish public Internet exposure, and no valid patient identifier or clinical record was used.

After remediation, every audited legacy method returns `401` before parsing resource identifiers or opening the clinical database when no canonical Identity session exists.

## Legacy route matrix

| Route | Methods | Identity | Profile/patient scope | Resource scope | Result |
|---|---|---|---|---|---|
| `patients/{patient_id}/history` | `GET`, `PUT` | canonical `clinical_record` | exact active doctor-patient link | route patient | contained; M6 legacy-write guard retained |
| `patients/{patient_id}/physical-exam` | `GET`, `PUT` | canonical `clinical_record` | exact active doctor-patient link | route patient | contained; M6 legacy-write guard retained |
| `patients/{patient_id}/cases/active` | `GET` | canonical `clinical_record` | exact active doctor-patient link | route patient | contained |
| `patients/{patient_id}/cases` | `GET`, `POST` | canonical `clinical_record` | exact active doctor-patient link | route patient | contained |
| `cases/{case_id}` | `PATCH` | canonical `clinical_record` | case resolves internally to an exactly linked patient | case patient | contained |
| `cases/{case_id}/activate` | `POST` | canonical `clinical_record` | case resolves internally to an exactly linked patient | case patient | contained |
| `cases/{case_id}/items` | `GET`, `POST` | canonical `clinical_record` | case resolves internally to an exactly linked patient | appointment/encounter must match doctor and patient; document must match patient plus a doctor-owned encounter, appointment, or creator membership | contained |

Unauthorized patient, case, and case-item resources return `404`. Existing case-item rows are filtered through the same scope check used for new assignments. State-changing calls with a supplied untrusted `Origin` return `403`; only exact same-origin requests and the canonical normal-local ports `8091`, `8092`, and `8443` are accepted. The secure Identity cookie remains `SameSite=Lax`, and JSON browser writes remain subject to CORS preflight. The existing M6 cohort and write-window boundaries remain in place and precede legacy writes.

The server-rendered History and Encounter consumers now forward only the validated `__Host-mxmed_session` cookie to the same-host or loopback API peer. They do not use `PHPSESSID` as a clinical authority fallback.

## Professional authority source decision

`profiles_verified_identities` and `profiles_doctor_credentials` contain trustworthy profile-level identity and credential facts. They do not bind an authenticated `auth_accounts.account_id` to the verified professional represented by the profile. `auth_account_memberships` binds the account to the profile but describes team membership only. Owner, administrator, collaborator, plan entitlement, profile name/license, and signature artifacts are therefore not accepted as professional proof.

The new reusable boundary returns `account_professional_binding_unavailable` for every current account. There is no production or QA bypass and no positive synthetic authority path. CLIN-AUTH02B must define and govern the missing binding before professional-only transitions can be restored.

## Sensitive transition matrix

| Operation | Entitlement | Scope/binding retained | Professional decision | Current result |
|---|---|---|---|---|
| Prescription draft/edit | `prescriptions` | patient link, creator, encounter, version, idempotency | not required by this containment | available |
| Prescription preview/history | `prescriptions` | patient link and creator/read scope | read-only | available |
| Local/registered signature apply | `prescriptions` | fingerprint, document/version, actor/profile | required at transition | `403` |
| Prescription QR creation | `prescriptions` | creator, patient, document/version | required before token creation | `403` |
| Prescription QR signature upload | bearer token | TTL, review, actor/profile/document/fingerprint | revalidated from server session actor | `403` |
| Prescription emission/finalize | `prescriptions` | signature and fingerprint | required before idempotent writer | `403` |
| Prescription void | `prescriptions` | creator, patient, lifecycle | required before transaction | `403` |
| Prescription reissue | `prescriptions` | creator, patient, lineage | required before transaction | `403` |
| Unsigned consent draft and patient/representative preparation | `clinical_record` | patient/encounter/version/identity attachments | physician proof not required | available |
| Physician signature placed in consent draft | `clinical_record` | document/signature binding | required | `403` |
| Physician consent QR creation/upload | `clinical_record` | QR review, TTL, actor/document/fingerprint | required at both boundaries | `403` |
| Consent grant/final emission | `clinical_record` | patient and physician signature binding | required before staging or document transaction | `403` |
| Other versioned professional documents: free document, multimedia consent, responsiva, certificate, medical note, discharge, report, referral | `clinical_record` | existing patient, creator, version, fingerprint, and type-specific signer rules | required for physician signature, remote-sign QR, and issuance | `403`; unsigned drafts remain available |

The patient or representative signature remains a separate flow and is not treated as physician authority, including the signer-side QR flows for responsiva and multimedia consent. Existing issued documents are read-only and unchanged. REC-REG02/03/04C fingerprints, QR replay rules, document versions, and the canonical prescription writer are unchanged.

## Functional impact

Legitimate new prescribing can continue through draft, edit, preview, and history, but cannot currently be signed, emitted, voided, or reissued. Prescription remote-sign tokens cannot be created or completed. Consent preparation and patient/representative signing remain available, while the physician signature and final grant are unavailable. Unsigned drafts of the other versioned professional documents remain available; their physician signature, remote-sign QR creation/completion, and issuance are unavailable. The denial is explicit `403 PROFESSIONAL_AUTHORITY_REQUIRED` with a Spanish message and occurs before persistent mutation.

General diagnosis, encounter-note, order, amendment, and sensitive-read delegation remain governed by the existing coarse membership/capability context. Their finer operation policy was not invented in this containment and remains CLIN-AUTH02B work.

## Verification

- Disposable HTTP/CLI database gate: anonymous legacy methods `401`; exact-scope reads `200`; cross-profile/patient `404`; hostile and arbitrary local-port origins `403`; canonical local origin accepted.
- Professional contract gate: unverified owner, administrator, collaborator, and plan-only actors denied; no proof fails closed; prescription, consent, and other professional-document transitions are guarded before mutation; unsigned drafts remain available; physician and patient consent authorities remain distinct.
- Canonical Identity resolver regression passes, including revoked/ambiguous membership behavior.
- Prescription and consent fingerprint golden vectors pass unchanged.
- Encounter integrity pure suite passes all 56 checks.
- No normal-local patient identifier was used for mutation. QA05 was not accessed. No membership, credential, migration, or stored document changed.

## CLIN-AUTH02B decisions

1. Define the governed account-to-professional binding, its evidence, verifier, status, validity, revocation, and profile scope.
2. Decide whether coverage/substitution is supported and how the acting and represented professionals are recorded.
3. Ratify the operation policy for diagnosis, assessment/plan, consultation finalization, clinical amendments, orders, sensitive reads, and bounded preparatory delegation.
4. Define how existing legitimate physicians obtain the binding without deriving it from owner status or legacy profile text.
5. Add positive HTTP fixtures only after that canonical contract exists; until then `AUTH02A_15` remains `BLOCKED_BY_AUTHORITY_CONTRACT`.

```text
NORMAL_LOCAL_CLINICAL_DATA_UNCHANGED=true
QA05_DATA_UNCHANGED=true
MEMBERSHIPS_UNCHANGED=true
SQL_MIGRATION_APPLIED=false
```
