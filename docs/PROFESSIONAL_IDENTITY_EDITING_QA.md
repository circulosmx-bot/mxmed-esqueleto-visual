# CRD03.4 — Admission-controlled gender and professional-description guidance

The physician-facing form no longer contains a gender control or replacement
status/help row. Prefix remains editable, with its existing options and authority;
professional designation remains editable with its existing 120-character limit.
Desktop uses a narrow prefix column and the remaining row for designation; mobile
stacks prefix, designation and the professional-description field.

`gender` and `gender_label` move from PrivateProfileController EDITABLE_FIELDS to
BLOCKED_FIELDS. The existing blocked-field policy remains: HTTP 200 success,
`blocked_fields_ignored`, and no write when only blocked fields are provided.
The grouped browser PATCH omits both keys completely. Internal hydration still
reads stored gender and sets the existing profile-gender dataset/event for current
avatar/presentation consumers. No data deletion, backfill or admission workflow change.

The field label is exactly:

“Describe brevemente tu actividad profesional para el encabezado de tu perfil”

Its empty-field placeholder is exactly:

“Ej. Resume en una frase tu actividad profesional y los principales servicios que brindas.”

The placeholder is an HTML attribute only. Empty input saves null; no guidance is
assigned as field content. UI/server/sanitizer limits remain 150 Unicode characters.
Public Bio rendering and auto-fit are unchanged.

## Focused validation — 2026-09-12

- ProfessionalIdentityEditingBrowserTest: read-only Leticia hydration; current
  stored gender, prefix, designation and Bio preserved; exact copy; no gender UI;
  desktop 1440×900 and 1366×768 use the full row with narrow prefix; 390×844 stacks
  naturally; no horizontal overflow. Mocked grouped saves exclude both gender keys,
  preserve their values, and send null for empty Bio rather than the placeholder.
- GenderWriteAuthorityTest: explicit disposable MySQL 8.4 (3331), random isolated
  database and local authenticated physician session. Direct gender-only attempts
  (including null/invalid values) are ignored with the established policy and leave
  the full row unchanged. Prefix, designation, Bio and grouped name/theme updates
  persist exactly without modifying either gender field; mixed blocked/editable
  requests apply only allowed fields. Temporary server/database are removed afterward.
- BioShortPersistenceTest on that synthetic database: 150 Unicode save/readback,
  null/empty, 151 rejection, atomic grouped save; five editable fields and four
  ignored system/admission fields; transaction rolled back.
- BioShortBrowserTest: public full-text/auto-fit matrix and real Header hydration;
  updated regression expectation excludes gender from the grouped payload.
- VerifiedPublicNameUiBrowserTest: verified-name policy, grouped PATCH and new
  two-field professional-row order pass.
- MediaIdentityModalBrowserTest: single thumbnail, published-pointer preservation,
  modal canonical/legacy distinction, focus trap/X/Escape/return focus and mobile pass.
- OwnerMediaReviewInlineBrowserTest: state/submit/withdraw/replacement/CSRF/privacy
  presentation and gallery refresh pass. CredentialPresentationBrowserTest passes.
- JS syntax, PHP lint, diff check, current HTTP source equality and application
  packaging pass. New browser/PHP tests and this note are excluded from runtime.
- Full Leticia database snapshots before/after match; no profile/media value changed.

Known inherited policy/icon assertions and the isolated Header-hydration timing test
remain outside this phase and unmodified; see MEDIA_IDENTITY_MODAL_QA.md. The real
Director Header hydration assertions pass in this phase. No unrelated repairs.

## Director review

Admin: `http://127.0.0.1:18143/index.html?review=crd034`.
Public: `http://127.0.0.1:18143/profiles/doctor.php?doctor_id=1&mxmed_plan=standard&review=crd034`.
Temporary screenshots/logs/snapshots: `/tmp/mxmed-crd034/`.
SIG01 remains deferred and unchanged.
