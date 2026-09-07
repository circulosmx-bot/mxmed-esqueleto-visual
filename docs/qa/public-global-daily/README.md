# Public global daily availability — 2026-09-07

Baseline: `program/mxmed-product-completion-v1`, `1d2f7456350cb3c905a903316b9a25e0993bb856`; clean, synchronized, upstream ahead/behind 0/0.

## Behavior and authority

The normal public Agenda now represents all publicly eligible offices. `mode=global_days` returns the next three nonempty global days, complete per-day totals and result-owned office IDs/names. Each card displays at most ten slots and only shows `Ver todos los horarios (N)` when N exceeds ten. Slot labels identify the office. Selection highlighting includes the office ID as well as the date/start, so distinct simultaneous offices remain distinct.

`mode=day&date=YYYY-MM-DD` returns exactly one date, including a zero-total empty day. It ignores caller-supplied office restrictions: the public profile controller supplies the doctor's eligible offices. The new composition service filters public/active flags and uses the existing profile Agenda capability contract. Free and unpublished profiles cannot use these modes. The localhost-only QA-plan resolver was extracted from the page into a shared helper without changing its plan mapping/host rules. Production requests cannot activate the QA override on a nonlocal host.

Both modes call the existing `publicDayAvailability()` per office/date, which retains the established schedule/override/holiday/collision calculation and default 30-minute slots. No scheduling algorithm or booking backend was replaced. Results sort by start, natural office ID, then end. Deduplication uses doctor + office + start and never merges different offices solely because their times coincide.

Dates must lie between today and today +89 inclusive in America/Mexico_City. Started/past slots are excluded. Main next/previous navigation remains three-day navigation; the dedicated next-available modal and its production script remain unchanged. Exact-date navigation recalculates only the requested date across eligible offices, not the whole 90-day horizon.

The new native dialog shows the date, start time and office for every slot, retaining end time internally. Day navigation fetches fresh exact-date data without reloading. It supports empty dates, Enter activation, Escape dismissal, focus return, a focus cycle, and date announcements. Close/reopen guards prevent a queued old close event from aborting a newer fetch. Choosing passes the result unchanged into the existing booking handoff; closing does not create a reservation.

## Live acceptance evidence

Runtime: existing public server 8092/API 8091, doctor 1, QA plan `professional`. No schedule or fixture changes and no clock override in daily QA.

Tuesday 2026-09-08: CMQ 10 morning slots + Star Médica 8 afternoon slots + MAC Norte 0 = **18**. Preview renders 10 and the exact 18-count action. The modal renders all 18; result 11 is Star Médica 16:00–16:30 internally, displayed as `16:00 h`. Selecting it opens confirmation for Star Médica at 16:00. Selecting a CMQ preview opens confirmation for CMQ.

Main pagination reaches Saturday September 12 with ten MAC Norte slots and no full-day action. Previous restores the earlier cards. Modal navigation reaches Sunday September 13 with the empty message, then returns to Tuesday's 18 slots. Boundary navigation disables previous at today and next at day 89. A per-page identity marker survives date navigation, proving no reload.

The current day's count can differ because elapsed slots are excluded. The archived preview correctly shows remaining afternoon availability on September 7.

## Checks

PASS:

- `php modules/agenda/tests/PublicDailyAvailabilityTest.php`: exact-day composition, eligible offices only, complete totals, sorting/deduplication, simultaneous-office ties, internal end/context, empty day, elapsed times, invalid dates, 90-day bounds, three useful days, Free/private denial and local-vs-remote QA override.
- `node modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`: live preview/action/modal totals, CMQ and Star Médica selection, previous/next date and main navigation, empty date, date bounds, keyboard/focus/rapid reopen, no reload, Free and dedicated next-available.
- `node modules/agenda/tests/PublicNextAvailableGlobalTest.mjs`: existing dedicated global-search tests.
- `node modules/agenda/tests/PublicNextAvailableGlobalBrowserTest.mjs`: dedicated search still crosses Friday/Saturday and passes MAC Norte booking context. Its live Monday expectation was updated to 09:00 CMQ because the Director enabled CMQ mornings before this task; production next-available code is untouched.
- PHP syntax for all changed PHP files; JS syntax for the new modal; `git diff --check`.

Visual and geometry QA passed at **1440×900, 1366×768, 390×844, 320×740**. Slots remain at least 44px high, with no horizontal dialog/result overflow. Mobile uses vertical scrolling. Office labels in previews are 12px and separated beneath the time.

Browser network assertions recorded GET requests only and zero JavaScript exceptions. Booking confirmation handoff was exercised without submitting a reservation or requesting OTP.

## Screenshots and runtime trace

- [Global compact preview and 18-count action](desktop-1440-preview.png)
- [1440×900 full day](desktop-1440-modal.png)
- [1366×768 full day](desktop-1366-modal.png)
- [390×844 full day](mobile-390-modal.png)
- [320×740 full day](mobile-320-modal.png)
- [Star Médica booking confirmation](desktop-1440-booking.png)
- [Empty day on mobile](mobile-320-empty.png)
- [Runtime assertions and API request trace](result.json)

No admin configuration/conflict/reassignment changes. No schedule, database/schema/migration, reservation backend, OTP/SES, identity, patient-type, post-confirmation, AWS or Route53 changes. The requested 72/28 and 40/60 requester form layout remains pending and untouched.
