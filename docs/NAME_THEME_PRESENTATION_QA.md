# CRD03.7 name typography, media copy and theme swatches

The current public-name label now has its own inline span. The existing dynamic strong element keeps its ID, read-model hydration, font size and weight. The label computes to 15.096px (85% of the measured CRD03.6 17.76px); the physician value remains 17.76px. Both use the exact Director color #2596be. No naming logic, selection policy, legacy visibility, save behavior or authority changed.

Media copy is exactly: “Los cambios de imágenes requieren revisión; mientras tanto la versión actual permanece visible.” Its existing informational styles remain unchanged: 11.68px on both desktop viewports, 13.44px on mobile. No second explanation or alert was added.

Theme buttons retain their role=radio, accessible names, titles, catalog order, selected state, roving tabindex, arrow handlers, click handlers, preview and save/reset behavior. The visible color is now a centered 38×20px rectangle with 5px corners, inside a 44×44px button. Selection retains the existing ring/check vocabulary; keyboard focus remains outlined. Existing theme names in accessible labels, hover titles and the current feedback text remain unchanged. CSS uses each existing --swatch-color rather than altering any catalog values.

## Observed layout

At 1440×900 and 1366×768 the public-name line stays on one line; at 390×844 it wraps naturally to two lines. Name-block height stays at its CRD03.6 value: 125.094px desktop and 304.188px mobile. Controls retain 42px height, their recovered top gap, labels and authority. Theme rectangles remain 38×20px at all three viewports; mobile wraps the existing grid without horizontal page overflow. No Header, Sidebar, utility, credential, consultorio, Agenda or SIG01 implementation changed.

## Validation

- PublicNameDensityBrowserTest: retained CRD03.6 density/control heights and label associations, absence of removed heading, legacy name preservation, split typography, exact color, three viewports and zero attempted writes. Line counting now tolerates the deliberately different inline font sizes.
- VerifiedPublicNameUiBrowserTest and PublicDisplayNamePolicyTest: synthetic name combinations through mocked grouped PATCH, canonical spelling, given-name/surname authority, nonconforming preservation, dynamic label/value and rejection behavior.
- NameThemePresentationBrowserTest: actual Leticia hydration, precise font/color/copy, unchanged media text hierarchy, all twenty theme names/keys/colors, visible 38×20/5px swatches, 44px targets, ring/check selection, keyboard focus/arrows, no autosave, mocked grouped theme PATCH and reset. Sixty public previews (all twenty keys at 1440/1366/390) retain canonical accent resolution and no overflow. Director mutations are intercepted rather than sent.
- ProfileThemeCatalogTest and ProfileThemeIntegrationContractTest pass unchanged.
- ProfileThemePersistenceTest passes on a fresh disposable MySQL 8.4 instance at localhost:3331: approved key saves/reloads, arbitrary color is rejected without mutation, reset reloads null. The existing GenderWriteAuthorityTest harness was extended only in a temporary external copy to invoke this suite in its synthetic database. It also passes blocked gender-only/mixed requests and BioShortPersistenceTest (150 Unicode characters, null, 151 rejection and atomicity). Database/container cleaned afterward; no repository harness or backend changes.
- OwnerMediaReviewInlineBrowserTest and MediaIdentityModalBrowserTest preserve photo/logo/gallery routing, public/candidate authority, states/CSRF/replacement, canonical verified credentials, neutral legacy, focus/Escape/return and mobile scrolling.
- ProfileUtilityActionsBrowserTest retains exact CRD03.5 order, uniqueness, modal behavior, public link and Header/Sidebar Agenda navigation.
- JS syntax, git diff --check and runtime packaging pass. No PHP source changed.

Before/after full Director database snapshots are byte identical. Private profile responses are identical excluding only meta.generated_at, including the complete theme catalog. Leticia's display name, stored theme, public media, current review submission and eight READY gallery assets remain untouched. No Director personal media was created or used to manufacture QA states.

Temporary screenshots and metrics live in /tmp/mxmed-crd037/: before.json, before-name.png, before-theme.png, metrics.json and all six required crd037 screenshots. Screenshots are not committed.

Director review: http://127.0.0.1:18143/index.html?review=crd037 — Mi Perfil → Información → Datos generales.

SIG01 remains deferred unchanged. Stop for Director review after one focused commit and one push.
