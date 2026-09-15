# IP01F — Compact formation header summary

Reuses the existing credential card/list in a shared grid with the formation
heading. Desktop (>=992px) presents title then summary on the same band; narrow
viewports stack them. The redundant credential heading is removed from markup
and its presentation-only assignments/dependency are removed from the renderer.
The live list retains an accessible label. Credential data sources, row rendering,
verified authority, empty/error messages and save behavior are unchanged.

The formation field grid preserves two columns on desktop after moving its old
first child; only the final summary field spans both columns. Labels, chips and
Add styles are unchanged.

Read-only QA on 18143 covers 1440×900,1366×768,820×1180,390×844. Before/after
computed credential text, size, weight, color and line height match exactly;
formation title styles match, no overflow or runtime exceptions. Screenshots
confirm shared desktop alignment and clean mobile stacking. Formation height:
472.80→378.80px desktop; 735.59→699.59px tablet; 743.59→707.59px mobile.
Artifacts: /tmp/ip01f-qa/{before,after}, with corresponding measurement logs.
No data edits/saves. Pending tab visual trial is untouched and excluded.
