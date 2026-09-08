# Public agenda CTA and modal navigation QA

Task: public Agenda CTA and full-day modal day-navigation visual refinement.

Pre-head: `0d58987a57c30f4a5ca2ebdd2e06724cbddcfa8e`

Validated presentation:

- The public Agenda CTA reads `Encontrar primera cita disponible`.
- A leading white Material Symbols search icon is prominent. It slightly extends left of the CTA on desktop and adapts inside the button on narrow layouts.
- The full-day modal `Día anterior` and `Día siguiente` controls use `#01afb7`, white text and no border.
- The existing full-day modal geometry, accessibility, navigation and next-available booking handoff remain intact.
- No clipping or horizontal overflow at 1440x900, 1366x768, 390x844 or 320x740.

Checks run:

- `php -l profiles/doctor.php`
- `node --check modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`
- `git diff --check`
- `QA_OUTPUT=/tmp/daily-cta-nav-polish-final node modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`

Evidence:

- `desktop-1440-cta.png`
- `desktop-1440-modal.png`
- `desktop-1366-cta.png`
- `mobile-390-cta.png`
- `mobile-390-modal.png`
- `mobile-320-cta.png`
- `mobile-320-modal.png`
- `result.json`
