# CRD03.16 — Opaque save surfaces and modal button order

The floating save tray now uses solid `#81E2DF`; the unsaved-navigation modal content uses solid `#FFFFFF`. Both surface blur declarations are removed, including WebKit. Existing restrained border/shadow and Bootstrap page dimming remain. The tray's warning, typography, green button rules, spacing and bottom-right margins are unchanged.

Scoped CSS `order` presents Discard, Continue editing, Save and continue from left to right on desktop and from top to bottom on mobile. The original DOM and Tab sequence remain Save, Discard, Continue editing, preserving existing keyboard/focus behavior and action wiring. Button labels, modal copy and green/red/teal semantics are unchanged. The only HTML change versions the stylesheet URL.

Real local browser QA passed at 1440×900, 1366×768 and 390×844: clean tray hidden, actual Bio input, four-second delayed appearance, solid computed surface colors with opacity 1 and no backdrop filter, requested visual order, original DOM/physical Tab sequence, Continue editing and draft reversion. Screenshots were inspected; no horizontal overflow or browser exceptions occurred. Zero PATCH requests were sent.

`ExplicitSaveNavigationBrowserTest.mjs` passed the complete existing timing/navigation/three-action/discard/failure-retry/duplicate/native-unload/responsive matrix with real save disabled. Its only changes are opaque background expectations. Runtime JS/PHP, dirty tracking, grouped PATCH, 4,000 ms delay, prefix/public-name propagation and backend authorities are unchanged.

All fourteen observed database table counts/hashes and the full private Leticia response data match the initial snapshots exactly. HTTP HTML/CSS match the checkout. Application packaging has 674 runtime files plus nine scaffold files; all source/inventory hashes match. Diff whitespace checks pass.

Temporary screenshots and QA evidence: `/tmp/mxmed-crd0316/` (not committed).

Review: http://127.0.0.1:18143/index.html?review=crd0316&qa_tools=hide
