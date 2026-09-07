# Public booking requester layout QA

Baseline: 84edb64e6b809508d94239b539ecb7139045520a.

Only requester DOM order and scoped desktop CSS changed. Row 1 is approximately 70/30 (156px minimum keeps the existing relationship label on one line); row 2 approximately 41/59, excluding the gap. Mobile retains the existing single-column grid and follows DOM/keyboard order: name, relationship, phone, email.

Chrome local visual review: 1440x900, 1366x768, 390x844, 320x740. Before/after metrics confirm identical modal height, scroll height, 38px control heights and bottom-action positions at every size. No horizontal overflow. At 1440x900 all actions are immediately visible. At 1366x768 the baseline already scrolls (795px content in a 734px viewport); this is unchanged. Mobile also retains existing scrolling. Actions screenshots show the lower modal after scrolling; the unrelated fixed DEV plan overlay is hidden only in browser evidence, not in source.

Focused validation passed:
- `node modules/profiles/tests/PublicBookingSubjectContractTest.mjs`: self/other, required relationship, serialization, stale cleanup and backend validator without DB/network.
- `node modules/profiles/tests/PublicBookingSubjectBrowserTest.mjs`: desktop/compact/mobile/small, direct/next entry, requester fields and progression with mutation endpoints intercepted locally. No real reservation or OTP delivery.
- `php -l profiles/doctor.php` and `git diff --check`.

Requester names, types, attributes and payload keys are unchanged; only two existing label nodes moved. No JavaScript/backend/OTP/identity/schema modifications. Existing server authority for OTP recipient remains unchanged; no live SES verification was performed.
