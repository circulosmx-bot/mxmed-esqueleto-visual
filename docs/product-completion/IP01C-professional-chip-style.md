# IP01C — Professional chip visual refinement

Only the shared professional-information chip CSS changes product presentation.
The six categories use a light #effafb capsule, 1px #b9e4eb border, existing
--mm-acc-contraido navy text and --mm-acc-activo remove icon. The semantic remove
button stays inside a two-column grid, with its original accessible name/action.
No markup, JavaScript behavior, backend or schema was changed.

Chips use 30px minimum single-line height, 16px radius, 8px left / 4px right
padding and 4px internal gap. Their lists retain 8px horizontal / 6px vertical
gaps and the approved spacing below the input. Long labels wrap naturally.
The remove target is 24×24px; hover adds a subtle tint and keyboard focus has a
2px navy outline. Inputs, Add buttons, ordering links and section layout are
unchanged.

Validation uses the guarded synthetic database/router from IP01A. The browser
suite verifies all IP01B gestures and duplicates, invalid input retention,
baseline reversion, explicit save/reload, discard and failure/retry. Added checks
cover contained remove controls, accessible names, 24px targets, keyboard focus
and overflow at 1440×900, 1366×768, 820×1180 and 390×844. Screenshots and measured
section heights are in /tmp/ip01c-qa/{before,after} and corresponding logs.
No Director data/media writes. The separate tab visual trial remains untouched
and excluded from this commit.

With identical synthetic content, Formación section heights before→after:
1440: 664.69→659.91px; 1366: 664.69→659.91px;
820: 1001.08→1030.70px (one additional natural chip row, +3%);
390: 1377.05→1362.70px. Individual chips: 30.80→30px.
