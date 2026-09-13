# CRD03.15 — Compact turquoise save surfaces

The actual CRD03.14 runtime measured 16 px for the floating Save label and 19 px for its warning. The new sizes are exactly 18.4 px (1.15×) and 14.25 px (0.75×). The warning keeps its copy and bold emphasis, with dark reddish `#5E1E28` ink on the glass instead of a white label background.

Both tray and unsaved-navigation modal content use `rgba(129,226,223,0.75)`, retaining 8 px backdrop blur/WebKit declaration, soft shadow, restrained container border and transparent modal sections. Component opacity remains 1. The green actions use solid `#00C040` with white text and no border or text halo; hover/active remain darker green. Keyboard-only focus indication remains available. Tray spacing remains 8 px with 12 px padding; desktop margins remain 32 px and mobile 16 px plus safe area.

## Contrast limitation and supporting text

Removing the text halo as requested restores the flat white/green pair's measured 2.44:1 contrast. This does not meet WCAG AA text contrast; no AA pass is claimed for either green label. The requested green, white text and no-stroke treatment are preserved exactly.

Warning and destructive-action/error text use dark reddish ink; the neutral action uses dark neutral ink with its existing hover treatment. Against the darkest possible 75% turquoise surface, warning/destructive text has at least 4.59:1 and modal/neutral text at least 5.71:1. Their readability does not depend on blur. Action labels, order and behavior remain unchanged.

## Validation

- Real Bio typing with no fetch mocks: clean tray hidden, edited tray hidden initially, visible after actual four-second inactivity. Computed styles prove the two exact font sizes, green/white colors, zero button border, no text shadow, turquoise alpha, blur, transparent modal children and opaque text/actions.
- 1440×900, 1366×768 and 390×844 pass. No horizontal overflow; desktop/mobile margins are preserved. Existing theme/preview colors remain subtly perceptible through both surfaces in inspected screenshots. Hover/active and keyboard-focus styles were checked.
- Real Save-and-continue passed on port 18143: one actual grouped PATCH saved a temporary Bio edit and continued to the exact Formación tab. Prefix was unchanged. Conditional local QA restoration returned Bio, historical display name and original timestamp exactly; all other fields were checked before restoration.
- All fourteen observed table counts/hashes and the complete private Leticia response data match the initial snapshot exactly. No permanent test edit or media change remains.
- `ExplicitSaveNavigationBrowserTest.mjs` passes the complete existing navigation/three-action/failed-save/retry/discard/duplicate/native-unload/timer/responsive matrix with real save disabled.
- `ExplicitProfileSaveBrowserTest.mjs` passes the existing grouped-save, edit/revert, concurrency, no autosave/storage and responsive regressions with synthetic requests.
- Every runtime JS/PHP file is unchanged from the CRD03.14 starting commit, including dirty tracking, the 4,000 ms delay, grouped save, navigation guards and CRD03.13 public prefix propagation. Header/Sidebar implementations, media authorities and SIG01 deferred status remain unchanged.
- Real HTTP HTML/CSS match the checkout. Application assembly contains 674 runtime files plus nine scaffold files, with matching source/inventory hashes. Diff whitespace checks pass.

Temporary scripts, JSON evidence and desktop/mobile screenshots remain outside Git in `/tmp/mxmed-crd0315/`.

Review: http://127.0.0.1:18143/index.html?review=crd0315&qa_tools=hide
