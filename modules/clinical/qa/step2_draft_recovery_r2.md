# Step 2 draft recovery R2

Start source: `be2e819b393140d26d2a1f4e6905c12a1818b050`.

## Cause and repair

The old load branch placed every meaningful nonambiguous draft in
`availableMeasurementDraft`. The paint pass then disabled the entire capture
form until the physician chose recovery or discard. This was a recovery decision
state, not a missing spinner binding.

A meaningful draft classified as available now restores immediately, stays dirty,
and shows **Captura recuperada** in the existing capture heading. The existing
cancel action becomes **Descartar captura**, clears only the unsaved draft and
returns to the same measurement's empty reference state. No writer runs during
restoration. Filtered edit options are rebuilt before restoring an existing row's
code. Status resets on discard, save, reset and context load.

`availableMeasurementDraft` is now populated by load only for unavailable drafts.
Those retain the explicit blocking panel and discard action. Ambiguous creates
continue through the existing branch before normal recovery and keep their
original key and retry payload.

Exact revised capture-control condition:

```js
const unsafeDraftPending = !!availableMeasurementDraft && unavailableMeasurementDraft;
control.disabled = mode !== 'open' || busy || measurementLocked || createPending || unsafeDraftPending;
```

The submit control retains its separate dirty, numeric validity and mandatory
Origen checks. It permits the existing explicit retry with a retained create key.
The draft's origin alone does not disable controls.

## Verification

`step2_draft_recovery_r2_browser.py` uses the actual Director controller and
compares the accepted capture form with R2 at 1440×900, 1366×768, 820×1180 and
390×844. It checks HR, temperature and BP restoration, header/stepper/title/writer/
fields/chips/footer rectangles and page extent; discard and immediate reference
arrows; unrelated context clicks; and five full capture/reload/edit/discard/type
switch/reload cycles per viewport, plus Chromium at 1440×900. It rejects clinical
network writes, duplicate controllers, JavaScript errors and canonical DB changes.

`step2_draft_recovery_r2_logic.py` checks unavailable same-code and missing-edit-row
drafts; canonical-preserving discard; partial drafts; editing a restored row;
and lost POST response followed by reload and exact-key/exact-payload retry with
one canonical row. Its writer is isolated from the real API.

The prior R1 reload gates now expect the R2 product decision for safe drafts.
VITALREF03 logic, the Step 2 create/edit/void writer gate and the R4 functional
browser gate cover preserved reference, SERVER_AT_SAVE, reuse, used-type filtering,
idempotency and navigation behavior.

Evidence: `/Users/circulodigital/.codex/artifacts/step2-draft-recovery-r2`.
The runtime refresh verifies HTTP 200, the source-head response header and served
asset bytes against the final commit. Review fixture files and canonical review
observations are unchanged.
