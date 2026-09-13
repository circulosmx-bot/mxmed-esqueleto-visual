# CRD03.3 — Single Admin thumbnail and verified-data modal

The Admin photo/logo slot displays the candidate REVIEW preview when available;
otherwise it displays the existing published media. The published image stays in
its original source-controlled slot, hidden while a candidate occupies the visible
slot. Its public pointer, deletion handler and publication authority are unchanged.
Published photo/logo badges read “Publicada”/“Publicado”. Candidate labels remain
“Pendiente de enviar”, “En revisión” and “Requiere cambios”. No “Cambio propuesto”
caption remains. Withdrawal, observations, replacement and manual batch submission
retain the existing routes and CSRF contracts. Public deletion controls appear only
when the published thumbnail is the visible representation.

Gallery submissions are distinct backend items; this presentation does not invent
replacement relationships or duplicate a public item under another purpose.

The identity controls begin with “Nombre en el perfil”. The parent heading and its
public badge are removed. The existing public-profile link remains in the compact
name heading row. Name policy, selector, surname controls, preview and grouped PATCH
are unchanged.

“Ver datos verificados” opens the existing Bootstrap modal framework. Modal identity
uses only `verified_identity.full_name`. Credential rows use only canonical
`verified_credentials` with matching type, VERIFIED status and ACTIVE lifecycle.
Institution appears only when supplied. Empty canonical credentials fall back to a
separate neutral “Credenciales registradas” section; legacy licenses never receive
verification marks. No birth date, gender, Bio, designation, provenance or internal
timestamp is rendered in the modal. Opening it makes no additional request or mutation.

## Local QA — 2026-09-12

- `MediaIdentityModalBrowserTest.mjs`: Leticia single visible photo/logo thumbnail,
  exact candidate preview, published fallback badges, identity heading removal,
  canonical/legacy distinction, absent provenance, eligibility filtering, 0..N
  specialties and optional institution; Bootstrap focus trap, X, Escape, return
  focus, mobile bounds and long content internal scrolling; zero modal mutations.
- Viewports 1440×900, 1366×768, 390×844: no horizontal overflow. Owner inline-state
  test covers OPEN/SUBMITTED/NEEDS_WORK, submit/withdraw CSRF, observations/replacement,
  gallery refresh, empty/401 and permission/service/network/parse retry behavior.
- Leticia full local database before/after snapshots match: one submitted photo
  candidate, current published photo/logo and all eight gallery assets preserved.
  Public rendered portrait remains the approved photo, despite the Admin candidate.
- Media block height: 210.125px → 193.640625px.
- Removed identity parent header height: 34px → 0px (39.44px including its margin).
  Inline verified-detail content is absent and reserves 0px.
- Owner HTTP/browser E2E and media service/atomic tests pass in a disposable checkout
  with synthetic files and test-only ports 3330/6390/18148. The two inherited policy
  assertions run separately; the external aggregate harness skips them so remaining
  batch/atomic/withdrawal tests still execute. No product test is changed to bypass them.
- VerifiedPhysicianIdentityTest (VID01/VID02), PublicDisplayNamePolicyTest and
  DoctorCredentialPhysicalTest pass on a separate disposable MySQL 8.4 server (3331).
- VerifiedPublicNameUiBrowserTest (including grouped PATCH), CredentialPresentation
  browser, BioShortBrowser, PublicProfilePortrait (50 cases) and branding render pass.
- JS syntax, PHP lint, diff check, current HTTP source equality and runtime packaging
  pass. No backend/source authority, storage, Sidebar/Header implementation or SIG01
  changes are included.

## Inherited test failures reproduced on the exact starting HEAD

`550a7cfc819adafbb086c0510ed5d9a3489bd186` reproduces:

- MediaReviewInterventionPolicyTest: `pre_MR6_policies_changed`.
- GalleryReviewPolicyTest: `prior_36_policies_changed`.
- PublicProfileHeroConsultorioContactLayoutTest: requested Material Symbols assertion.
- DoctorCredentialHydrationTest: Header hydration timing changes between its snapshots;
  the unchanged baseline also fails the Header equality assertion. The focused
  credential browser and real Director Header regressions pass.

No unrelated fixes are included.

## Review artifacts

Admin: `http://127.0.0.1:18143/index.html?review=crd033`.
Public: `http://127.0.0.1:18143/profiles/doctor.php?doctor_id=1&mxmed_plan=standard&review=crd033`.
Temporary screenshots, logs and before/after snapshots: `/tmp/mxmed-crd033/`.
