# CRD03.18 — Prefix save confirmation and 50% tray alpha

## Observed baseline

Branch `design/physician-crd03-credentials-ui-v1` started clean at `7d3626fd54720aaba49cc96edf20c9c16069fe45`, matching the remote. The actual original prefix was `Lic.`, with the historical stored display name `Dra. Leticia Muñoz Romo`.

Before source edits, a real UI Save submitted `prefix=Mtro.` and canonical `display_name=Leticia Muñoz Romo` in the existing grouped PATCH. Private rehydration, `profiles_doctors.prefix`, the public API and a fresh public page all reflected `Mtro. Leticia Muñoz Romo`. The reported propagation defect was **not reproduced** on this baseline; no broken propagation layer or source repair was identified. The existing presentation composer and verified-name policy remain unchanged. Original prefix, display name and timestamp were restored with a guarded local update.

## Resulting behavior

The floating tray's only visual change is its background alpha: `rgba(129,226,223,0.50)`. Warning color/typography, green button and states, dimensions, margins, spacing and the 4,000 ms timer remain unchanged. The three-button navigation modal's markup, copy, styles and visual order are unchanged; its final screenshots match CRD03.17 byte for byte.

A changed prefix remains a local draft until a save attempt. Both Save and Save-and-continue use the dedicated “Cambiar prefijo profesional” confirmation with the actual transition, Cancel and “Sí, cambiar y guardar”. Empty/cleared prefixes have natural copy. Cancellation/Escape sends no PATCH, retains drafts and abandons the exit. Confirmation reuses the existing grouped PATCH once; duplicate decisions/clicks cannot submit twice. Failed saves retain drafts and block navigation. Navigation handoff waits for the previous modal to hide; no visible modal stacking occurs. Focus returns after controls are enabled. Unchanged-prefix saves follow the existing flow directly.

## Validation

- `PrefixConfirmationBrowserTest.mjs`: real local DOM with persistence mocked; changed-only gating, local preview, cancel/Escape, first-decision/duplicate lock, grouped payload, navigation handoff/cancellation, failure/retry/draft retention, empty/cleared-prefix copy, focus return and three viewports pass.
- `ExplicitSaveNavigationBrowserTest.mjs`: full existing timer/navigation/action/failure/duplicate/native-unload/responsive matrix passes with real save disabled. Its only change is the tray alpha expectation.
- `PublicNamePresentationTest.php` and `ExplicitSaveSurfaceTest.mjs` pass. Runtime JS syntax and diff whitespace checks pass.
- Real local E2E temporarily established `Dra.` through a confirmed grouped Save, then drafted `Mtro.`. Before Save the public API remained `Dra.`; cancellation sent zero PATCH; confirmation sent one grouped PATCH. DB/private/public API and freshly rendered hero became `Mtro. Leticia Muñoz Romo`, with no double prefix. Reloaded Admin was clean. Leticia was restored exactly in test cleanup.
- Real four-second tray reveal and computed alpha, unchanged warning/button/interaction states, original three-button modal and physical Tab sequence pass at 1440×900, 1366×768 and 390×844. Screenshots were inspected; no horizontal overflow or browser exceptions occurred.
- Fourteen observed database table counts/hashes and full private Leticia data match the starting snapshots exactly after restoration. Backend/API, verified identity, display-name policy, media, contacts, credentials, gender, Bio/theme logic, Sidebar/Header and SIG01 remain unchanged.
- HTTP HTML/CSS/JS match the checkout; application assembly contains 674 runtime files plus nine scaffold files, matching source/inventory hashes.

Temporary evidence and requested screenshots: `/tmp/mxmed-crd0318/` (not committed).

Admin: http://127.0.0.1:18143/index.html?review=crd0318&qa_tools=hide

Public: http://127.0.0.1:18143/profiles/doctor.php?doctor_id=1&mxmed_plan=standard

SIG01: deferred and unchanged.
