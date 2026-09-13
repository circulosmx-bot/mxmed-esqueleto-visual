# DG05C — Prefix and designation shared row

## Scope and resulting layout

The starting branch was `design/physician-crd03-credentials-ui-v1` at `0b8c1d4db91cb6a1096e3b405aa75b033b0d1940`, matching the remote. Its only pending modifications were the Director-approved DG05B changes in `index.html` and `assets/css/style.css`; those changes were retained for this combined closeout.

DG05B media remains unchanged: equal 420 × 160 px photo/logo cards, 120 × 120 px previews, stacked on the left, with publication/review status and functional actions. The permanent media heading/badge/copy stays absent, and the empty dynamic review-feedback host is retained. On mobile the cards remain equal at 316 × 160 px.

Names and the existing conditional public-name summary occupy the upper right. Below that composition, the original prefix and designation controls now share a full-width grid with 30/70 proportions after accounting for its 16 px gap. At 1440 px the controls measure approximately 376.19/877.81 px; at 1366 px, 354/826 px. Prefix and designation stack in that order below 768 px. The designation helper stays immediately beneath its input in its own column; the prefix column has natural height and does not reserve helper space.

The shared row removes the standalone prefix from the upper identity block. Eliminating duplicated Bootstrap vertical gutter spacing before Bio provides a real 22 px gain: the textarea top moves from 747.77 to 725.77 px at 1440 × 900. The region from the media start through the Bio textarea's bottom decreases from 569.17 to 547.17 px. Media dimensions and the Bio textarea's own dimensions remain unchanged.

## Validation

- Real Chrome at 1440 × 900, 1366 × 768 and 390 × 844 passes: desktop 30/70 alignment, mobile stacking, labels/badges, helper containment, unchanged equal media cards, no overlap/clipping or horizontal overflow. Tablet 820 px also keeps the fields on one row without overlap or overflow. Requested screenshots were inspected.
- Real photo/logo change controls open their original file inputs without selecting a file or uploading. Media preview URLs, status and visible copy match DG05B. An expanded observations DOM fixture remains contained without overlapping the technical note or logo.
- Keyboard order follows names, surname checkbox, photo change/withdraw, logo change/delete, prefix and designation. The review group remains exposed to the accessibility tree; the designation helper retains its `aria-describedby` association and belongs to the designation column. Prefix column height is 70 px, designation approximately 86.17 px.
- `ExplicitSaveNavigationBrowserTest.mjs` passes with `MXMED_CRD0312_REAL_SAVE=0`: 4000 ms idle/reset/revert, navigation destinations, all modal actions, mocked grouped save, failure/retry/duplicate lock and three viewports.
- `PrefixConfirmationBrowserTest.mjs` passes with persistence mocked: changed-only confirmation, local public-name preview, cancel/Escape, grouped save, navigation handoff, failure/retry and three viewports.
- Comparing the DG05B snapshot with the final HTML confirms only one professional-row wrapper, field reparenting and the CSS cache query changed. All functional elements, attributes, options, other messages and inline scripts are preserved. Approved media markup matches exactly; approved DG05B CSS is intact with scoped additions. HTTP HTML/CSS and relevant identity/media JS match the checkout.
- Fourteen observed database table counts/hashes and the complete private profile data match before/after exactly. Leticia, all existing media and gallery assets remain untouched. Layout QA sent zero real API mutations and raised no browser exceptions; persistence paths in regression QA were mocked.

No backend, authority, public-name/prefix logic, save model, save surfaces, media workflow, Header/Sidebar, tabs, credentials, contacts or theme behavior changed. SIG01 remains deferred and unchanged. Screenshots and browser evidence remain temporary in `/tmp/mxmed-dg05c/`.

Review: http://127.0.0.1:18143/index.html?review=dg05c-prefix-designation-shared-row&qa_tools=hide
