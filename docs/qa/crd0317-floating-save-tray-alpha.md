# CRD03.17 — Floating save tray alpha and warning color

Only two visual properties change: the floating tray background returns to `rgba(129,226,223,0.75)` and its warning returns to the historical `#C85C5C`. Container opacity remains 1 so the green button and warning remain solid. No blur is added. Typography (14.25 px warning, 18.4 px save label), dimensions, layout, spacing, margins, border/shadow and all green button states are unchanged. HTML only versions the stylesheet URL.

Source comparison proves every modal rule, its DOM/order and runtime JS/PHP are unchanged. The modal screenshots at all three sizes are byte-identical to CRD03.16.

Real local browser QA passed at 1440×900, 1366×768 and 390×844: clean tray hidden, actual Bio input, real four-second delayed appearance, computed 0.75 background alpha, red warning, unchanged green button/hover/active/focus, margins and no horizontal overflow. Screenshots were inspected. Modal styling, visual order and original physical Tab sequence still pass. Drafts were reverted locally; zero PATCH requests and zero browser exceptions occurred.

The complete `ExplicitSaveNavigationBrowserTest.mjs` timing/navigation/three-action/discard/failure-retry/duplicate/native-unload/responsive matrix passed with real save disabled. Its only change is the tray background expectation. Fourteen observed table counts/hashes and the full private Leticia response data match the initial snapshots exactly. No persistent edits were made.

HTTP HTML/CSS match the checkout. Application assembly passed with 674 runtime files plus nine scaffold files; all inventory/source hashes match. Diff whitespace checks pass.

Temporary screenshots/evidence: `/tmp/mxmed-crd0317/` (not committed).

Review: http://127.0.0.1:18143/index.html?review=crd0317&qa_tools=hide
