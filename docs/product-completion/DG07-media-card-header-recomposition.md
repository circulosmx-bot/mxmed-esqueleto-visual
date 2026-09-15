# DG07 — Media card header recomposition

The existing photo and logo title elements now occupy the first grid row of
their respective media cards. Their previews, review candidates, actions and
supporting copy occupy the following content rows. No media DOM, state, events,
API calls or persistence code changed.

The media titles reuse the shared section-heading component with a scoped 20%
reduction: text 20px to 16px, icon 50px to 40px and gap 8px to 6px. Both titles
use `white-space: nowrap`; their current Spanish labels fit without clipping at
all tested widths. The photo format helper retains its exact copy and subordinate
style.

Read-only browser QA on the existing local review runtime covers 1440x900,
1366x768, 820x1180 and 390x844. At each viewport both headers measure 16px/40px,
remain one line inside their cards, and all visible previews and action groups
begin below the header. Public and candidate photo thumbnails remain side by
side where present; the loaded logo and its actions remain visible. No horizontal
overflow or runtime exceptions occurred. Screenshots and measurements are under
`/tmp/dg07-qa/after` and `/tmp/dg07-qa/qa.log`.

The save/dirty regression tests pass. The review used no form input or media
mutation. The separately authorized tab visual trial remains untouched and is
excluded from this change.
