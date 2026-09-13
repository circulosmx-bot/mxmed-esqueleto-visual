# CRD03.6 public-name density

The name editor's visual heading and its wrapper were removed. The same public-profile anchor now starts in the utility group where navigation already placed it; its attributes, live URL authority, order and behavior remain unchanged. No application JS, API, naming policy or persistence code changed.

The dynamic current-name value remains populated by the existing read model, with the label “Nombre público en tu perfil:”. Its existing conditional visibility is preserved, including Leticia's legacy/nonconforming state. The full line uses #073b5a, the existing name-heading blue. Before editing, Chrome computed its font as 11.84px (.74rem). The new 1.11rem computes to 17.76px: exactly 1.5×. The physician value retains its existing stronger weight. No banner, badge or replacement heading was added.

## Observed measurements

CSS pixels after real Leticia hydration, same local runtime and viewport, scroll zero for document Y. Name height is #mxpi-name-editor; controls top is .mxpi-verified-name__controls; line height is the rendered #mxpi-current-name bounding box.

| Viewport | Name height before → after | Controls top Y before → after | Controls top gap before → after | Public-name line height before → after |
| --- | --- | --- | --- | --- |
| 1440×900 | 143.672 → 125.094 | 510.516 → 484.234 | 38.281 → 12 | 15.391 → 23.094 |
| 1366×768 | 143.672 → 125.094 | 510.516 → 484.234 | 38.281 → 12 | 15.391 → 23.094 |
| 390×844 | 299.672 → 304.188 | 1566.313 → 1540.031 | 37.281 → 11 | 15.391 → 46.188 |

Controls rise by 26.281px and retain their 42px height and labels. Desktop name blocks shrink by 18.578px. Mobile text naturally wraps to two lines; block growth is 4.516px (1.5%), with no truncation, ellipsis or page overflow. Remaining container padding belongs to the controls rather than an empty heading. No fixed heights or control-size reductions were introduced.

## QA

- PublicNameDensityBrowserTest: actual Leticia at 1440×900, 1366×768 and 390×844; computed 1.5× font scale, existing blue, exact dynamic copy, heading/wrapper absence, recovered control gap, unchanged field heights/labels, legacy name state, unchanged top utilities and description; all attempted profile writes blocked in the test browser.
- VerifiedPublicNameUiBrowserTest: synthetic isolated fixture; six given-name/surname combinations through mocked grouped PATCH, separate prefix, legacy/nonconforming name preservation and rejection. Also verifies that the new label renders a different dynamic fixture name.
- PublicDisplayNamePolicyTest: ordered subsets, required first surname, optional second surname, canonical Unicode spelling, fallback and legacy status.
- ProfileUtilityActionsBrowserTest: CRD03.5 order, uniqueness, both modals and focus return/Escape, existing public link opens a new tab, Header account dropdown and Sidebar Agenda/Información navigation.
- ProfessionalIdentityEditingBrowserTest and BioShortBrowserTest: CRD03.4 authority and description preservation, no gender controls or gender PATCH payload, null placeholder behavior, Unicode/150 boundary, public fit and three viewports. Saves mocked.
- MediaIdentityModalBrowserTest: canonical identity and active verified credentials, neutral legacy, existing thumbnail priority/public authority, modal focus trap/return/Escape and mobile scrolling.
- OwnerMediaReviewInlineBrowserTest: photo/logo/gallery review status and actions, candidate routing, submit/withdraw CSRF, replacement, error handling and three viewports. Mutations mocked.
- GenderWriteAuthorityTest plus BioShortPersistenceTest: disposable MySQL 8.4 on localhost:3331, blocked gender-only/mixed PATCH, unrelated editable fields, 150 persistence, null, 151 rejection and atomicity. Disposable database/container cleaned afterward.
- JS syntax, git diff --check and runtime packaging pass. No PHP source changed.

Full Director database snapshots before/after are byte identical. Private profile responses are identical excluding only meta.generated_at. Leticia's display name, profile values, public media, existing review submission and eight READY gallery assets remain untouched. No personal media was used to manufacture test states.

Temporary evidence: /tmp/mxmed-crd036/before-metrics.json, metrics.json, before-name-block-{1440,1366,390}.png and the four required crd036 screenshots. Screenshots are not committed.

Director review: http://127.0.0.1:18143/index.html?review=crd036 — Mi Perfil → Información → Datos generales.

SIG01 remains deferred unchanged. Stop for Director visual review after one focused commit and push.
