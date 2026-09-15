# IP01E — Professional heading accents

A shared CSS rule scopes #06AEB8 to the three `.mx-professional-heading`
subsection titles and their existing Material Symbols icons. Icons change from
20px to 50px (2.5×, literal +150%); title typography stays 20px/700. Existing
flex alignment and 8px gap remain, and icons cannot shrink. No markup, behavior,
backend, schema, chip or Add styles change.

Read-only browser QA on the current 18143 runtime measured all three headers at
1440×900, 1366×768, 820×1180 and 390×844: exact color rgb(6,174,184), 50px icons,
20px text, zero vertical-center difference, no document overflow or runtime
exceptions. Screenshots confirm natural wrapping and no clipping. Larger icons
naturally increase heading height; section/layout structure is unchanged.
No data was entered or saved. Before/after screenshots and measurements:
`/tmp/ip01e-qa/`. The separate pending tab trial is preserved and excluded.
