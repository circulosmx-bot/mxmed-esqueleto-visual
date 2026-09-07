# Public next-available global search — 2026-09-07

Baseline: `program/mxmed-product-completion-v1`, `d7611884240a0b35e7b3f1a21bf43322695776eb`.
Baseline worktree clean; local/upstream/remote HEAD equal, ahead/behind 0/0.

## Implementation

Only the dedicated next-available modal becomes global. Its client aggregator receives the existing public-profile controller's plan-filtered consultorios, restricted to `is_public` and `is_active`. It does not enumerate admin offices or invent plan rules. The existing public endpoint decides whether each office has available slots using its existing schedule/override/collision calculation. No calculation is duplicated.

Each office retains a queue and cursor. Before taking the next global result, every non-exhausted empty queue is refilled. Results merge by `start_at`, then numeric consultorio ID, then end time. This prevents a sparse office's later page from hiding an earlier appointment. Identity is doctor + consultorio + start time; simultaneous appointments at different offices remain distinct. Global history supports pages of three and previous navigation. One-result lookahead avoids a spurious empty final page.

The search uses a fixed 90-calendar-day window from the opening day in America/Mexico_City, including that day and excluding day +90. The per-office endpoint may internally scan farther when refilling; results outside the original window are never emitted. This does not adopt admin's 180-day horizon.

The normal three-day Agenda caller, context, navigation, booking handoff and all booking backend/OTP/identity code remain untouched. The modal's information strip now says:

> Las citas mostradas corresponden a la disponibilidad actual del médico en sus consultorios.

## Focused validation

- `node modules/agenda/tests/PublicNextAvailableGlobalTest.mjs` — PASS: multiple eligible offices, chronological merge, Friday/Saturday/Monday, cross-office pagination, previous/next stability, queue refill, deterministic simultaneous-office ties, deduplication, exclusion of IDs not in the supplied eligible set, 90-day boundary (day 89 included, day 90 excluded), empty results and cancellation.
- `node modules/agenda/tests/PublicNextAvailableGlobalBrowserTest.mjs` — PASS against live local profile/API. Requires Chrome CDP on 9348 and public server on 8092 (environment overrides supported).
- `node --check assets/js/public-profile-next-available.js` — PASS.
- `php -l profiles/doctor.php` — PASS.
- `git diff --check` — PASS.

Runtime context: doctor 1, `mxmed_plan=professional`. The browser harness sets only the dedicated search's clock to Friday September 11 at 18:29 Mexico City time, reproducing the reported boundary deterministically. Availability responses are live and are neither mocked nor rewritten. Screenshots therefore represent a controlled search origin, not the execution day's first available appointments.

Observed pages:

1. Friday 11, Star Médica: 18:30, 19:00, 19:30.
2. Saturday 12, MAC Norte: 09:00, 09:30, 10:00.
3. Subsequent pages preserve all remaining Saturday results.
4. Mixed page: Saturday 12 MAC Norte 13:30; Monday 14 Star Médica 16:00 and 16:30.

Previous and next navigation restore identical pages, including after crossing the second boundary. Choosing Saturday 09:00 passes office `3`, `2026-09-12 09:00:00`, and `2026-09-12 09:30:00` to the unchanged booking callback. The actual confirmation UI displays MAC Norte and 09:00; continuation reaches the subject/patient-data flow. No reservation is submitted and no OTP is requested. Browser network requests were GET-only; no JavaScript exceptions occurred.

Normal Agenda card contents were unchanged by searching/paginating. Its original availability request still omits consultorio_id and uses the existing single-office server fallback. Default Free profile has neither Agenda nor search trigger; no plan behavior changed.

Responsive checks at 1440×900, 1366×768, and 390×844 passed: no horizontal dialog/card overflow. Mobile retains existing vertical scrolling. CSS and booking modal layout are unchanged.

## Evidence

- [1440×900 mixed results](desktop-1440-mixed.png)
- [1366×768 mixed results](desktop-1366-mixed.png)
- [390×844 mixed results](mobile-mixed.png)
- [MAC Norte booking confirmation](desktop-1440-booking.png)
- [Runtime assertions and GET request trace](result.json)

No schema, migrations, admin, reserve backend, pending OTP, SES, patient identity, concurrency or confirmation-success changes. No database writes or AWS operations were performed.
