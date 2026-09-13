# CRD03.11 — Floating save visual hierarchy

Visual changes only: the exact warning “Tienes cambios sin guardar” appears above the existing save button, with an 8 px gap and a transparent wrapper. Warning typography is 13 px, weight 600, line-height 1.25, color #C85C5C. Button colors are #198754 / #157347 hover / #146c43 active, with white text and the existing visible focus ring.

The existing desktop bottom/right 24 px placement and mobile 16 px safe-area placement are preserved. Both elements remain hidden when clean. No application JavaScript, dirty tracker, grouped PATCH, media, Header, Sidebar, identity authority, or signature code changed. SIG01 remains deferred.

## Validation

- Real Director runtime: 1440×900, 1366×768 and 390×844; exact text/colors, vertical geometry, no outer card, accessible button/focus, no horizontal overflow or browser exceptions.
- Real Leticia: clean load, trusted Bio input, revert, theme edit, successful explicit save, hidden clean state, and restoration through the existing reset/save controls. Original theme and timestamp restored; all 14 database table hashes and full private profile response match the initial snapshot exactly (excluding response generation time).
- Existing browser save regression: no autosave/storage writes, one grouped PATCH, saving/double-submit, success, error/retry, concurrent draft and reload. Real runtime regression additionally covers canonical name hydration, one baseline, all field reverts and QA visible/hidden.
- Existing theme presentation regression and dirty tracker tests passed. Existing tests only update the expected warning copy and save-button color.
- Screenshot artifacts are temporary and excluded from Git: /tmp/mxmed-crd0311/crd0311-dirty-save-1440.png, crd0311-dirty-save-1366.png, crd0311-dirty-save-mobile.png, crd0311-clean-no-save.png.

Review: http://127.0.0.1:18143/index.html?review=crd0311&qa_tools=hide
