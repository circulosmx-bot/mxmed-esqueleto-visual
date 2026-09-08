# Public Agenda CTA final polish QA

Task: visual-only refinement of `Encontrar primera cita disponible`.

Pre-head: `9e6ab4246e074e6622c185c1df24353492912598`

Validated presentation:

- The CTA copy remains unchanged.
- The decorative leading icon uses Material Symbols Outlined `search` at weight 600, white, and 3rem desktop / 2.7rem narrow mobile sizing.
- CTA hover, focus and active treatment changes only the teal fill. Text stays white, the white 50%-alpha 2px border stays constant, and no underline is applied.
- CTA semantics and its next-available behavior remain unchanged.
- No clipping or horizontal overflow at 1440x900, 1366x768, 390x844 or 320x740.

Checks run:

- `php -l profiles/doctor.php`
- `node --check modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`
- `git diff --check`
- `QA_OUTPUT=/tmp/daily-cta-final-polish node modules/agenda/tests/PublicDailyAvailabilityBrowserTest.mjs`
- `node modules/agenda/tests/PublicNextAvailableGlobalBrowserTest.mjs`

Evidence:

- `desktop-1440-cta.png`
- `desktop-1366-cta.png`
- `mobile-390-cta.png`
- `mobile-320-cta.png`
- `result.json`
