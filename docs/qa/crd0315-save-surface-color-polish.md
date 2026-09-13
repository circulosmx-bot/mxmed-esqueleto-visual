# CRD03.15 — Save-surface color polish

The floating warning changes only to solid `#FFFFFF`. Its 14.25 px bold typography, copy, spacing, position and turquoise glass stay unchanged. All floating tray/button CSS rules match the starting commit exactly; the green button remains `#00C040`, with its existing sizing and interaction states.

The unsaved-navigation modal returns to `rgba(255,255,255,0.75)` glass, retaining 8 px blur/WebKit declaration, subtle border/shadow, transparent sections and the existing 0.5 backdrop. Save-and-continue remains green/white. Discard uses white with red `#B23E46` text and a restrained red border; Continue editing uses white with teal `#07536E` text and a restrained teal border. Hover/active use light tints. Labels, copy, actions and order are unchanged.

Real runtime visual QA passed at 1440×900, 1366×768 and 390×844: clean tray hidden, actual Bio typing, four-second delayed reveal, white warning, unchanged green Save, neutral glass and the three button semantics. Computed styles and inspected screenshots confirm the surfaces; there is no horizontal overflow or browser exception. Drafts were reverted locally and zero PATCH requests were sent.

`ExplicitSaveNavigationBrowserTest.mjs` passed the complete existing timer/navigation/three-action/discard/failure-retry/duplicate/native-unload/responsive matrix with real save disabled. Its only change is the expected modal background. Runtime JS/PHP is unchanged, preserving dirty tracking, grouped PATCH, 4,000 ms timing, prefix/public-name propagation, media and all backend authorities.

All fourteen observed table counts/hashes and full private Leticia response data match the initial snapshot exactly. No database writes or permanent test edits were made. HTTP HTML/CSS match the current checkout; application packaging contains 674 runtime files plus nine scaffold files with matching source/inventory hashes. Diff whitespace checks pass.

Temporary screenshots/scripts/evidence: `/tmp/mxmed-crd0315-color-polish/`; none are committed.

Review: http://127.0.0.1:18143/index.html?review=crd0315-color-polish&qa_tools=hide
