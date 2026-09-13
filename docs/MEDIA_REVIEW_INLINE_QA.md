# CRD03.2 — Inline owner media review

Candidates render only in their matching photo, personal-logo or gallery surface.
Public media remains primary. Candidate thumbnails use the owner API's REVIEW
`preview_url`. OPEN is “Pendiente de enviar”, SUBMITTED is “En revisión” and
NEEDS_WORK is “Requiere cambios”. Observations expand through native details.

The existing upload, withdrawal and manual batch-submit requests, CSRF tokens,
server capacity and publication authority are unchanged. The public gallery
renderer preserves candidate nodes when its independent refresh completes.
A single polite live region announces review changes. Empty/401 states remove
all review UI; permission and service errors remain contextual, with retry for
service/network/parse failures.

## Local validation — 2026-09-12

- `OwnerMediaReviewInlineBrowserTest.mjs`: read-only Director runtime on 18143;
  all state changes, uploads, withdrawals and submissions intercepted in-browser.
  Purpose routing, state labels, CSRF, disabled submit, observations/replacement,
  gallery refresh, empty/401 zero height, 403 and error retries pass.
- Viewports: 1440×900, 1366×768 and 390×844; no horizontal overflow in either tab;
  photo/logo candidate thumbnails remain 88×88.
- Leticia: public photo/logo URLs unchanged, one SUBMITTED photo candidate and
  eight READY gallery assets unchanged. Full before/after database snapshots match.
- Standalone review height: 253.1875px → 0px. Photo/logo card: 193.640625px →
  210.125px. Datos Generales: 1823.4375px → 1530.734375px (292.703125px recovered).
- `OwnerMediaReviewHttpTest.mjs`: disposable archived checkout with current UI
  files; test-only ports 3330/6390/18148 replace occupied 3309/6387/8128.
  Owner/reviewer browser and HTTP flows, all three uploads, private previews,
  CSRF/authorization negatives, manual submit, NEEDS_WORK replacement, approval,
  historical media, corrected files and gallery capacity 16 pass.
- ProfilePhotoApproval, MediaReviewIntervention, PhysicianLogoApproval,
  PhysicianLogoReview, GalleryReview, MediaReplacement/Atomic,
  LogoImprovement/Atomic/ReviewInput pass. ReviewBatch/Atomic and EmptyReviewBatch
  pass separately after the inherited policy assertion stopped the aggregate suite.
- BioShortBrowser, CredentialPresentationBrowser, VerifiedPublicNameUiBrowser,
  PublicProfilePortrait (50 cases) and PublicProfileBrandingRender pass.
- JS syntax, diff whitespace and application assembly pass; new CSS included in
  runtime manifest. Temporary screenshots and synthetic test fixtures stay outside
  the repository. Sidebar/Header implementation, backend, storage and SIG01 unchanged.

## Inherited failures, reproduced on the starting commit

`b3c46f5b7e3b27a0154789227239839a77babe51` reproduces these same failures:

- MediaReviewInterventionPolicyTest: `pre_MR6_policies_changed`.
- GalleryReviewPolicyTest: `prior_36_policies_changed`.
- PublicProfileHeroConsultorioContactLayoutTest: requested Material Symbols glyph assertion.

No unrelated repairs are included.

## Review

URL: `http://127.0.0.1:18143/index.html?review=crd032`.
Artifacts: `/tmp/mxmed-crd032/`, including before snapshot/screenshot and requested
`crd032-*.png` screenshots. The Director runtime must retain its local authenticated
session shim; this phase adds no authentication mechanism to product source.
