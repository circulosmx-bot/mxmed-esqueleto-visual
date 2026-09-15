# Personal information — Shared section heading system

The three professional headings and five requested personal headings opt into
one `mx-section-heading` utility. Shared tokens preserve the live master:
Carlito/IBM Plex Sans stack, 20px, weight 700, #06AEB8, 24px line height, 8px gap,
center alignment. Material Symbols Rounded use 50px/400, line-height 50px and
FILL 0 / wght 100 / GRAD -50 / opsz 24. Scoped important utility properties
supersede legacy card-specific title selectors; helpers do not opt in.

Added existing-library symbols: account_box (photo), image (logo), palette
(theme), contact_phone (contact), draw (signature). No new icon library or
containers. Labels, helpers, previews, actions and workflow code are unchanged.

Read-only browser QA compares every target's computed heading/icon styles with
the current professional master at 1440×900,1366×768,820×1180,390×844: exact match.
No document overflow or runtime exceptions. Reviewed desktop/mobile screenshots
confirm centered icons and intact media/help hierarchy. Existing local review
data was neither entered nor saved. Artifacts: /tmp/personal-headings-qa/after
and qa.log; original master measurements in master.log.

Only marked title typography/icon markup changes. Larger titles naturally take
more space than previous small titles; card layout, controls and spacing rules
are retained. Pending tab trial remains untouched and excluded.
