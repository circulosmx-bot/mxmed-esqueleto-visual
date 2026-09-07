# Administrative schedule conflict UX guard — 2026-09-07

Baseline `5c60e90faceb514c7c54d8c2ff10b6fa08754bea`, branch `program/mxmed-product-completion-v1`, clean and synchronized (0/0).

## Change

The existing ScheduleController validator now enumerates all relevant conflicts using its unchanged half-open overlap predicate. Responses retain the original first-conflict fields and add `conflicts[]`; each item contains `overlap_window` with max(start)/min(end). Same-office rejection remains intact. Inactive schedules and other doctors remain excluded by existing repository/validator authority. Persistence code is unchanged.

The administrative editor reuses its existing loaded-state validator for immediate feedback, checking active rows and only state hydrated for the current doctor. It renders an inline alert with every conflicting office, weekday and actual intersection, the simultaneous-attendance explanation, and a native keyboard-focusable “Volver” button returning focus to the editor. It does not open repeated dialogs or offer reassignment.

Both queuePersist and persistSchedule stop known-invalid attempts. A stale client draft may reach the server, which still rejects it authoritatively and supplies all conflict details. Rejection retains the editable draft and does not trigger retries. Repeated identical feedback is not rebuilt/reannounced. A changed draft during an in-flight request supersedes its old feedback and receives one debounced save after the request finishes; values are never restored back and forth.

The existing validation-before-persistence boundary is preserved. This change does not add transaction locks, atomic reassignment, or a new guarantee against two writers that both validate simultaneously. Those remain outside this UX task.

## Focused QA

PASS:

- `php modules/agenda/tests/ScheduleConflictUxGuardTest.php`: invokes the real controller `update()` with an in-memory ScheduleRepository adapter. Exact overlap, partial overlap (11:00–12:00), both containment directions, adjacent intervals, different weekdays, multiple offices, inactive schedules, other-doctor exclusion and strict actor scope. Invalid requests never call persistence, even with no JavaScript involved; valid adjacency/different-weekday requests reach only the memory adapter.
- `node modules/agenda/tests/ScheduleConflictUxBrowserTest.mjs`: runs the actual production editor functions extracted from app.js in a controlled browser editor fixture with the admin stylesheet and an in-memory save adapter. Checks immediate intersections, all conflicts, zero known-invalid save attempts, corrected-save success, stale-server rejection, no retry loop, no draft oscillation, corrections during in-flight save, 1366×768/320×740 geometry, and Volver focus return. The fixture intentionally never sends schedule writes to the live API.
- `php modules/agenda/tests/Cut01BScheduleScopeSentinelTest.php`: PASS.
- PHP/JS syntax and `git diff --check`: PASS.

Screenshots show the controlled editor fixture, not a mutation of the Director's live schedule:

- [Desktop](desktop.png)
- [Narrow](narrow.png)
- [Browser assertions](result.json)

No schedule data, appointments, holds, public Agenda, booking, OTP/SES, identity, database schema, migrations, AWS or tags changed. Automatic reassignment, interval subtraction, validity dates and support for more than two windows were not implemented.
