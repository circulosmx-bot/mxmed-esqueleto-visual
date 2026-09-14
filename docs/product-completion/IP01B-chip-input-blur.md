# IP01B — Professional chip input gestures

The six professional chip inputs (cert, cursos, dipl, miem, enf, trt) share
`commitChipInput` for Enter, Add and blur. The operation trims outer whitespace,
retains capitalization, validates the existing maximum and native validity,
avoids exact normalized duplicates within the category, and clears accepted
input synchronously. Blur followed by Add therefore cannot append twice.
IME composition Enter is not intercepted. Tab keeps normal focus movement.

Whitespace-only input creates nothing. Overlength/invalid text remains in the
input; existing counters and disabled Add behavior remain. Duplicate input
clears without adding another item. Existing stored duplicates are not rewritten.

Only the in-memory draft changes. IP01A baseline comparison, grouped explicit
save, four-second reminder, navigation guard, backend and schema are unchanged.
Service inputs and summary textarea are outside this gesture behavior.

## Validation

Extended `ProfessionalInformationBrowserTest.mjs` exercises all six categories:
Enter, Add, blur, Tab, real pointer blur-before-click, empty, duplicate, invalid,
draft dirty, explicit save/reload and add/remove baseline restoration. Actual
navigation after pending input invokes the existing guard; discard/reload proves
no persistence. Touch and overflow checks cover 1440×900, 1366×768, 820×1180 and
390×844. The original IP01A regression suite also passes, including failed save,
retry and unsaved navigation. No runtime exceptions or tracked professional
localStorage writes occurred. Physical/API tests and deterministic save-surface
and dirty-tracker tests pass.

Run the existing physical test, guarded synthetic router and HTTP seed before
the browser test, following IP01A setup. This run used only `ip01a_test_blur`;
Director profile and media were not mutated. Screenshots:
`/tmp/ip01b-qa/screenshots/ip01b-{1440,1366,820,390}.png`.

The separately authorized, uncommitted tab trial in `assets/css/style.css` is
preserved and excluded from this commit.
