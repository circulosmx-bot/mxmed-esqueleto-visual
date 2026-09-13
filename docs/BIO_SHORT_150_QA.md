# Bio breve: 150-character contract (CRD03.1)

The physician editor, grouped private PATCH and sanitizer accept up to 150
Unicode code points, counted with `Array.from` / PHP `mb_strlen`. Persistence
trims whitespace. Null/empty semantics remain unchanged. A 151-character PATCH
returns `validation_error`, `field: bio_short`, `max_characters: 150`, rejecting
the complete mutation before writing. Oversized paste inserts only available
characters. Existing oversized values hydrate in full and cannot be saved until
corrected; no existing Bio is rewritten on load.

The public Bio starts at its normal responsive size. After layout/fonts are
ready and on debounced viewport resize, the scoped helper measures line-box
height and reduces font size by 0.5 px only when needed. Desktop targets two
lines, mobile three; the 16 px floor takes precedence. Full text remains
selectable and visible, including any extra natural lines at that floor.
`scrollHeight` is deliberately not used because Baloo glyph overhang can exceed
the line-box height even when the text fits. No character-count typography
classes, ellipsis, clipping, scaling or typography observers are used.

## Focused QA

Use an isolated/disposable database for mutation tests:

- `BioShortPersistenceTest.php`: 150 accented/supplementary Unicode characters,
  exact readback, empty/null, 151 rejection, grouped identity fields, blocked
  system fields, oversized legacy hydration; all writes roll back.
- `BioShortBrowserTest.mjs`: standalone temporary Chrome profile, read-only
  Leticia hydration, DOM-only 100/120/140/150-character stress text, narrow/wide
  glyphs and Spanish accents, responsive fit, complete text, no overflow,
  media/header and credential fallback; grouped PATCH is mocked.
- Actual HTTP PATCH is validated separately on an isolated synthetic physician.
- The two technical status controls are removed completely. Existing null-safe
  JS consumers remain compatible; publication/candidate backend authority stays
  blocked and outside editable payloads.

Local observations at 1440 × 900: Leticia's unchanged 78-character Bio remains
24 px / two lines; natural 120 characters remain 24 px / two lines; natural 150
characters fit at 20.5 px / two lines. At 1366 × 768 the corresponding sizes are
23.905 / 23.905 / 20.405 px, all two lines. A 150-character all-W string needs
three desktop lines at 16 px; the full string remains visible. At 390 × 844,
natural 150 characters need four lines at 16 px. Readability is preserved.

The original Leticia database/profile/media snapshot is unchanged. No existing
Bio over 150 characters was found in that database. Public QA uses the existing
local `mxmed_plan=standard` preview matching her active subscription. The
inherited public plan resolver otherwise defaults to free and suppresses Bio;
plan resolution is outside this refinement and remains unchanged.

Temporary screenshots and machine measurements: `/tmp/mxmed-crd031/`.
The inherited hero-icon static test fails identically at the starting HEAD;
SIG01 remains deferred and unchanged. Credentials, public consumers, media,
consultorios, booking, signature, Sidebar and Header are unchanged.
