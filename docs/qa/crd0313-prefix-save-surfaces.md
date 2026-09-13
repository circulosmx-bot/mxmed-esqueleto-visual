# CRD03.13 — Public prefix propagation and save surfaces

The public physician profile now composes its displayed name from persisted `identity.prefix` and `identity.display_name`. The presentation helper strips exactly one leading prefix from the existing Admin catalog before adding the current prefix. It never changes stored data on load. The private/public DTO retains the separate fields, and verified-name policy remains unchanged.

An explicit grouped Save includes the prefix-free controlled name when verified-name controls resolve a valid presentation, including a prefix-only edit of a resolvable legacy name. Unresolved legacy names retain their existing handling. Admin preview already responds to local prefix edits; the public profile uses persisted values.

## Real defect and propagation evidence

Before implementation, the actual port 18143 runtime reproduced a successful `Dra.` → `Lic.` Save whose public hero still showed `Dra. Leticia Muñoz Romo`. The hero consumed the historical display name directly, and grouped Save omitted the canonical name for an unchanged, prefixed legacy record.

After implementation, a temporary `Dra.` baseline was saved through the existing Admin controls. Changing the prefix to `Lic.` immediately updated the local preview without PATCH or a public-name change. Explicit Save sent one grouped PATCH with separate `prefix="Lic."` and `display_name="Leticia Muñoz Romo"`. The actual public page reloaded as `Lic. Leticia Muñoz Romo`, and Admin reloaded with the corresponding controls. No mocked H1 was used.

The audited profile consumers are H1, logo and generic-avatar alternatives, booking full-name data and summaries, confirmation copy, short physician reference, SEO title/description/H1/Open Graph, and the current-profile breadcrumb candidate label. Raw DTO identity, listing/search authority and URL construction remain unchanged. JSON-LD is currently disabled/null and remains so.

The initial Director state already had persisted `Lic.` with historical `Dra. Leticia Muñoz Romo`. Both before-fix reproduction and after-fix QA used conditional exact restoration, including the original timestamp. All fourteen observed table counts/hashes and full private profile response data match their initial snapshots. Existing media assets and review records are unchanged.

## Timing and visual validation

- Dirty detection remains immediate. Only the reminder default changes from 2,000 to 4,000 ms; controlled-clock checks prove hidden at 3,999 ms and visible at 4,000 ms. Additional edits reset pending inactivity, reverting cancels it, and navigation protection works immediately before the reminder appears.
- Tray and modal content use `rgba(255,255,255,0.75)` with wrapper opacity 1. Text and actions remain opaque. Tray has 12 px padding/radius, a restrained shadow and optional 4 px blur; modal has optional 6 px blur and retains the existing 0.5 dimming backdrop. The save button remains `#198754` with white text.
- Desktop 1440×900 and 1366×768 retain 32 px tray right/bottom margins; 390×844 retains 16 px plus safe-area support. Buttons remain reachable, modal focus works, text remains readable, and no horizontal overflow or console errors were introduced. Screenshots were inspected and remain outside Git.
- `ExplicitSaveNavigationBrowserTest.mjs` passes the existing tab/Sidebar/Header/logout matrix, non-navigation exemptions, original destination, three modal actions, discard without PATCH, failed Save/retry/duplicate lock, native dirty/clean unload and responsive checks. Navigation guard implementation is unchanged.
- `ExplicitProfileSaveBrowserTest.mjs`, `ExplicitProfileSaveRealRuntimeBrowserTest.mjs` and `VerifiedPublicNameUiBrowserTest.mjs` pass grouped-save, hydration, edit/revert, concurrent draft, name constraints, resolvable/unresolved legacy cases and responsive regressions. Real-runtime save is disabled in those regressions; mandatory real prefix Save is covered separately above.
- `PublicNamePresentationTest.php`, `PublicBookingDoctorReferenceTest.php`, `PublicDisplayNamePolicyTest.php`, `ProfileThemeCatalogTest.php`, `ProfileThemeIntegrationContractTest.php`, `DirtyTrackerTest.mjs` and `ExplicitSaveSurfaceTest.mjs` pass. PHP/JS syntax and diff whitespace checks pass.
- Both local HTTP runtimes return the current HTML/CSS/application/save-surface files byte-for-byte. Application assembly contains 674 runtime files plus nine scaffold files; every inventory hash matches, and the new public-name service is included.

Evidence and eight requested screenshots: `/tmp/mxmed-crd0313/`. No screenshots, media or temporary database snapshots are committed. Bio150, media workflows, administrative contacts, credentials, Header/Sidebar implementations and SIG01 deferred status remain unchanged.

Admin review: http://127.0.0.1:18143/index.html?review=crd0313&qa_tools=hide

Public review: http://127.0.0.1:18143/profiles/doctor.php?doctor_id=1&mxmed_plan=standard
