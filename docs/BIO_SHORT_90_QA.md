# Bio breve: 90-character contract

Validated locally on 2026-09-08, from clean synchronized branch
`program/mxmed-product-completion-v1`, HEAD
`bdc74f378c4b36d159dd85117fa5eba3c92e0ed1`.

The canonical editor and private PATCH accept up to 90 Unicode code points
(the same counting unit as PHP `mb_strlen`, not bytes or UTF-16 units).
Whitespace is trimmed for persistence. Null and empty remain null.
Oversized paste inserts only the available characters and shows the inline
limit message. Existing oversized values hydrate in full and block saving
until corrected. No backend truncation is used for this limit.

Read-only database inspection found **5** existing values over 90 characters,
each 94 characters, in profiles 990101–990105. These were not rewritten.
The count remained 5 after QA.

## Validation

- `php modules/profiles/tests/BioShortPersistenceTest.php`: PASS. Exact 90
  accented/supplementary Unicode characters save and reload; null/empty retain
  semantics; 91 characters reject the entire mutation including accompanying
  fields. All integration test writes, including timestamps, roll back.
- Direct local HTTP PATCH with a 91-character bio: **422**, structured
  `validation_error`, field `bio_short`, `max_characters: 90`, exact approved
  Spanish message. No valid HTTP mutation was performed.
- `node modules/profiles/tests/BioShortBrowserTest.mjs`: PASS. Admin hydration,
  counter initialization, accents, supplementary Unicode, oversized paste,
  visible limit message, native typing at 90/91 and deletion verified.
- PHP syntax checks and `node --check assets/js/app.js`: PASS.

## Public measurements

The browser substitutes representative text only in the DOM, without saving
it. Samples contain 38, 75, 78 (the Director's example), and 90 characters.
PHP supplies `mxpp-bio--long` only for lengths 76–90. This reduces the desktop
base font by exactly 8%; mobile retains its existing readable font size.
No clamping or ellipsis hides valid text.

| Viewport | Rendered lines for 38 / 75 / 78 / 90 | Collision / horizontal overflow |
| --- | --- | --- |
| 1440 × 900 | 1 / 2 / 2 / 2 | None |
| 1366 × 768 | 1 / 2 / 2 / 2 | None |
| 390 × 844 | 1 / 2 / 2 / 2 | None |
| 320 × 740 | 1 / 2 / 2 / 3 | None |

At 320 px, the 90-character sample needs three lines. Readability and full
content take precedence over forcing two lines on narrow mobile screens,
as authorized by the contract. Measurements establish the two-line desktop
result for these representative strings, not every possible Unicode glyph
combination of the same length.

Screenshots reviewed at all four sizes and machine measurements are generated
by the browser test in `/tmp/mxmed-bio-short-qa/` (override with `QA_OUTPUT`).
The public screenshots use the 90-character DOM sample, not persisted data.

Only canonical Bio breve validation, its editor feedback, and its public hero
typography changed. Booking, Sobre mí rendering, theme rules, legacy free-text
fields, services, schema, and migrations are unchanged.
