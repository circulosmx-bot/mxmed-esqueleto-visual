# CRD03.14 — Approved save green and visible glass surfaces

The floating Save and modal Save-and-continue actions use the Director's exact `#00C040` with white text, `#00A936` hover and `#00972F` active states. Existing keyboard focus remains visible. Changes are limited to scoped CSS, the stylesheet review version, visual expectations in two existing browser tests and this QA record.

## Computed-style audit

The actual CRD03.13 runtime had 75% white modal content and already-transparent header/body/footer and nested wrappers. No Bootstrap or product rule was repainting them opaque white. Perceived solidity came from the pale underlying form, limited separation and a 6 px blur. The tray similarly had a weak shadow, no border and 4 px blur.

Both surfaces now retain `rgba(255,255,255,0.75)` and use 8 px backdrop blur with its WebKit declaration, a 1 px `rgba(0,49,82,0.12)` border and `0 8px 26px rgba(0,49,82,0.16)` shadow. The modal's header/body/footer are explicitly transparent in this modal's scope only. The existing 0.5 dark backdrop is unchanged. Wrapper, text and action opacity remains 1; no component-wide opacity is applied.

## Contrast treatment

Flat white text on the required green is only 2.44:1. A one-pixel dark-green text halo (`#007326`) keeps the exact white foreground/green background and gives 6.03:1 text-to-halo contrast. This follows the contextual halo technique described in [W3C G18](https://www.w3.org/WAI/WCAG22/Techniques/general/G18). The contrast pass depends on that halo, rather than on the flat color pair.

The warning keeps `#C85C5C` and its exact copy. It uses 19 px bold text with a light 90% white support immediately behind the label. Even compositing that support over black gives at least 3.24:1, meeting the 3:1 threshold for this large bold text under [WCAG 1.4.3](https://www.w3.org/WAI/WCAG22/Understanding/contrast-minimum.html). Modal body text has at least 8.38:1 against the darkest possible 75% white surface. None of these bounds depends on blur. Existing reserved content spacing accommodates the resulting tray height.

## Real runtime validation

- Real Leticia Bio input, actual four-second editing inactivity and immediate dirty/reminder lifecycle passed at 1440×900, 1366×768 and 390×844. No PATCH was sent: Save was not performed. The original Bio was reverted locally after each case.
- Real navigation opened the existing guard; Continue editing preserved the draft. Computed styles confirmed exact green and white text, hover/active colors, keyboard focus, both 75% backgrounds and 8 px blur, transparent modal children and fully opaque text/actions.
- Screenshots place genuine existing theme colors beneath the modal and the existing preview beneath the fixed tray. Underlying shapes/colors remain slightly visible through the surfaces. Requested screenshots and additional responsive captures were visually inspected and remain in `/tmp/mxmed-crd0314/`, outside Git.
- Desktop tray margins remain 32 px; mobile remains 16 px plus safe-area support. All three widths have no horizontal overflow. No browser exceptions or console errors were introduced.
- `ExplicitSaveNavigationBrowserTest.mjs` passes the existing complete guard matrix, three actions, exact destinations, save failure/retry, discard without PATCH, duplicate lock, native unload, timer/reset/stability and three viewports. Real save is disabled.
- `ExplicitProfileSaveBrowserTest.mjs` passes grouped Save, edit/revert, failure/retry, concurrent draft, no autosave/storage changes and all three viewports. Requests are synthetic in that regression; Director writes are zero.
- All observed table counts/hashes and the full private Leticia response data match the initial snapshot exactly. No data, media, schema or authority changes were made.
- Application runtime JS/PHP, including CRD03.13 prefix propagation and the 4,000 ms default, are byte-identical to the starting commit. Header/Sidebar implementations and SIG01 deferred status remain unchanged.
- Application packaging contains 674 runtime files plus nine scaffold files; inventory hashes and packaged source match. The real HTTP runtime serves the current stylesheet and HTML. Diff whitespace checks pass.

Review: http://127.0.0.1:18143/index.html?review=crd0314&qa_tools=hide
