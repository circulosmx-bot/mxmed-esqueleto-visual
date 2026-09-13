# DG05 — Compact media and identity layout

Baseline: clean `design/physician-crd03-credentials-ui-v1` at `2c425e03d944b1e1cc1c584d344dd902713138e8`, matching the remote.

The existing media card and name editor now share a desktop grid inside `mx-public-identity-card`. Media occupies 340 px on the left, with photo and logo stacked; identity fills the right column with three stacked fields and the existing conditional public-name summary immediately beneath them. Previews are 90 × 90 px. Below 992 px, identity precedes media in the single-column layout and DOM reading order. Prefix, designation, Bio and theme remain full-width below this region; contact and signature retain their sections.

All existing media/name markup, IDs, attributes, labels, messages, controls and inline scripts are preserved. Identity remains inside its delegated input/save parent. Only one layout wrapper, reparenting and the CSS cache query were added to HTML. CSS additions are scoped to this Datos Generales composition; earlier CSS and all JavaScript/backend source remain unchanged. Ordinary review candidates use CSS subgrid to keep the upload and withdrawal actions beside the thumbnail, while preserving the accessible review group. Expanded observations retain natural card height.

## Observed measurements

At 1440 × 900, media cards narrow from approximately 634 px to 340 px; the identity column is 914 px (840 px at 1366 × 768). The top region, measured from the media/name start to the prefix label's parent, decreases from 338.64 to 296.05 px: **12.58%**. The first name control moves up 207.64 px and the prefix control moves up 31.59 px. Measurements compare the unchanged, clean Leticia profile; its public-name summary keeps the existing visibility rules and appears when a name draft differs.

## Validation

- Real Chrome rendering at 1440 × 900, 1366 × 768 and 390 × 844 passes: desktop columns, vertical stacks, mobile identity-first order, no horizontal overflow, and no clipping or overlap. Requested screenshots were captured and inspected.
- Real photo/logo change buttons open their original file inputs. File chooser interception selects no file and sends no upload. Preview URLs, current publication/review states and all media copy match the baseline. An expanded observations DOM fixture stays within its photo card without overlapping the format note or logo.
- A local surname draft shows the original public-name summary below the identity stack with unchanged color/font; reverting the draft returns to clean state without saving. No API mutation or browser exception occurs in this layout QA.
- `ExplicitSaveNavigationBrowserTest.mjs` passes with `MXMED_CRD0312_REAL_SAVE=0`: 4000 ms reminder/reset/revert, navigation destinations, all three modal actions, mocked grouped persistence, failure/retry/duplicate lock and three viewports.
- `PrefixConfirmationBrowserTest.mjs` passes with persistence mocked: changed-only confirmation, preview, cancellation/Escape, grouped save, navigation handoff, failure/retry and three viewports.
- Physical Tab order passes: given name → surname checkbox → photo change/withdraw → logo change/delete → prefix. The review group remains exposed with its state label in the accessibility tree.
- HTML tag/attribute/text inventories and inline script comparison pass; previous CSS is intact. HTTP-served HTML/CSS and relevant identity/media JS match this checkout.
- Fourteen observed database table counts/hashes and the complete private profile data match before/after exactly. Leticia's identity, credentials, media, eight gallery assets, theme, prefix, contacts and profile remain unchanged. No real save/upload/delete is performed.

Evidence and screenshots are temporary in `/tmp/mxmed-dg05/` and are not committed. SIG01 remains deferred and unchanged. No later phase begins.

Review: http://127.0.0.1:18143/index.html?review=dg05-media-identity-layout&qa_tools=hide
