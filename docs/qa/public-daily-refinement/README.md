# Public daily Agenda refinement

Baseline: `3bb95a3580e3f18d91ae84b1f6fcaa29bc617e83`.

- Shared 112 × 64 CSS-pixel slot geometry at the default 16px root size for sparse/full day cards and the full-day modal. White chips, matching typography/borders, and the active profile's soft theme background. Office names retain their full text/accessibility and title; visual labels occupy at most two lines.
- “Ver más citas” is right-aligned, secondary, count-free, and retains a 44px minimum tap target; shown only above 10 available slots.
- Modal heading remains “Horarios disponibles”; successful-result count removed. Previous/next-day buttons sit directly below the date in a labelled navigation region. Loading/error/empty states retained.
- Existing `global_days` authority already skips empty dates and returns the next three available days within its 90-day horizon. No backend change was needed. Main pagination was verified against that authority, including the empty Sunday September 13 (the local data also omits September 16).

## Validation

PASS: `php modules/agenda/tests/PublicDailyAvailabilityTest.php`.
PASS: `QA_OUTPUT=/tmp/daily-refinement-after node modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`.
PASS: PHP syntax, JS syntax and `git diff --check`.

Browser review: 1440×900, 1366×768, 390×844, 320×740. Before/after preview and modal screenshots attached. No horizontal overflow; identical sparse/full/modal chip geometry; all 18 slots remain accessible through vertical scrolling when needed. The fixed PLAN QA widget visible outside the native modal is existing development UI.

Browser assertions cover count-free copy, alignment, title, date navigation placement, Enter activation of opener and day navigation, Escape and focus restoration, full-day empty state, main available-day pagination and reverse navigation, Free gating, dedicated next-available continuity, and result-owned handoff to booking (office 2, September 8 16:00–16:30 internally; only start time visible). All runtime requests were GET; no reservation or OTP was sent. Backend, plan rules, patient identity, requester layout, schema and AWS/SES remain unchanged.
