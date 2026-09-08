# Public daily agenda final stability and time hierarchy QA

Task: MXMED public daily agenda final stability, centering and time hierarchy polish.

Pre-head: `b9649636997c65a924c510998f66802a284f425b`

Scope validated:

- Public daily availability card presentation only.
- Full-day public `Horarios disponibles` modal presentation only.
- No backend, schema, migration, booking, OTP, identity, plan gating, admin, or availability engine changes.

Checks run:

- `php -l profiles/doctor.php`
- `node --check assets/js/public-profile-daily.js`
- `git diff --check`
- `QA_OUTPUT=/tmp/daily-final-polish-after node modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`

QA result:

- Preview and modal times are visual first-level elements at 1.55rem, with the visible `h` suffix removed.
- Preview chip grid, chip text and consultorio label are centered.
- Full-day modal title remains `Horarios disponibles` and is centered with the date/day navigation group.
- Full-day modal has a fixed 680px maximum-viewport-aware height. A day with 18 slots and a day with 2 slots open at the identical position and dimensions; the sparse view retains lower empty space.
- Modal chips are centered, preserve the same visual family as preview chips, and do not clip time or consultorio labels.
- Selecting a preview or modal slot still opens booking with the exact consultorio and internal start/end slot context.
- Four viewports passed: 1440x900, 1366x768, 390x844, 320x740.

Evidence:

- `desktop-1440-preview.png`
- `desktop-1440-modal.png`
- `desktop-1440-few-modal.png`
- `desktop-1366-preview.png`
- `desktop-1366-modal.png`
- `mobile-390-preview.png`
- `mobile-390-modal.png`
- `mobile-320-preview.png`
- `mobile-320-modal.png`
- `result.json`
