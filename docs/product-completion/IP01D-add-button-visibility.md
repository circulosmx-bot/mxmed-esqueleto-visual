# IP01D — Professional Add visibility

Scoped CSS changes only the six professional `.chip-add` buttons. The plus
increases from 12px to 22px (1.833×), retaining its 800 weight. The label stays
12px/600, with the existing 6px icon/text gap. A light #effafb fill, darker
#00738f text and .78 disabled opacity (previously .55) improve visibility without
turning Add into a primary/save action. Hover uses #e2f3f5. Heights remain 32px
on desktop and 42px on mobile; padding, radius, focus ring and semantics remain.

No product JavaScript, markup, chip style, backend or persistence changes.
The browser regression exercises all six categories with Enter, Add, blur and
Tab, duplicates/validation, baseline reversion, explicit save/reload, ordering,
discard and failed saves. Additional visual checks verify 22px plus / 12px label
and contained button content at 1440×900, 1366×768, 820×1180 and 390×844.
IP01C containment/accessibility checks remain in the suite.

QA uses only guarded `ip01a_test_add` synthetic records. Director data is not
mutated. Screenshots and logs: `/tmp/ip01d-qa/`. The pending tab trial remains
untouched in style.css and is excluded from this commit.
