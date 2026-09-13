# CRD03.5 profile utility actions

The existing verified-data and visibility modal triggers now share the Datos Personales action group with the existing public-profile link, in that order. The public link still uses its live profile/plan URL and opens a new tab. No triggers were cloned. The old visibility wrapper and visible public-name/verified-identity summary were removed entirely.

Only markup, utility styling, and the existing public-link placement selector changed. Name controls, name generation, grouped PATCH, API validation, modal contents, media behavior, Header and Sidebar implementation remain unchanged. The existing preview-rendering helper already tolerates absent presentation elements; public-name generation is independent of that output. Leticia's nonconforming current name remains untouched until explicit selection. Gender editing remains blocked and the description retains its existing 150-character contract. SIG01 remains deferred unchanged.

## Observed density

Chrome CSS pixels, after hydration and modal dismissal, with the same viewport and existing Leticia profile. Tabs-to-media is the media card top minus the tabs bottom; name height is the name editor bounding box. First-screen content bottom is the document coordinate of the description textarea bottom, measured from scroll zero (not an artificial fixed container height).

| Viewport | Tabs → media before / after | Name height before / after | Description bottom before / after |
| --- | --- | --- | --- |
| 1440×900 | 42 / 6 | 205.625 / 143.672 | 967.031 / 869.078 |
| 1366×768 | 42 / 6 | 205.625 / 143.672 | 967.031 / 869.078 |
| 390×844 | 57 / 22 | 385.156 / 299.672 | 2322.656 / 2238.172 |

Both desktop action groups stay on one row. Mobile preserves order while wrapping, with no page horizontal overflow. The description textarea is now fully inside the 1440×900 viewport; its label moves into the 1366×768 first screen. The existing mobile Sidebar and media stacking remain unchanged.

## Validation

- ProfileUtilityActionsBrowserTest: real Leticia, three viewports, exact labels/order, unique controls, removed rows/meta, original legacy state, zero attempted writes, both modal focus/Escape/return, public link opens a new tab, Header account dropdown, Sidebar Agenda and Información navigation.
- VerifiedPublicNameUiBrowserTest: isolated synthetic fixture; all six name/surname combinations verified through mocked grouped PATCH rather than removed preview text; separate prefix, nonconforming on-load preservation, legacy fallback and server rejection UI.
- MediaIdentityModalBrowserTest: canonical identity and active verified credentials, neutral legacy, single thumbnail/candidate priority, public pointers, focus trap/close/Escape/return and mobile scroll.
- ProfessionalIdentityEditingBrowserTest and BioShortBrowserTest: gender UI absent, internal authority retained, exact description label/placeholder, Unicode/paste/150 boundary, empty value becomes null, grouped PATCH excludes gender, public description fit and three viewports. Writes mocked.
- GenderWriteAuthorityTest and BioShortPersistenceTest: fresh disposable MySQL 8.4 on port 3331; blocked gender-only/mixed requests, other field persistence, 150 acceptance, 151 rejection and atomicity. Disposable database and container cleaned afterward.
- JS syntax, git diff --check and runtime packaging pass. No PHP source was changed.

Full local database snapshots before/after are byte identical. Private profile responses are identical excluding only meta.generated_at. Existing eight READY gallery assets and current review submission remain untouched. No Director media was created, replaced or removed.

Temporary evidence: /tmp/mxmed-crd035/ contains before-1440.png, before-1366.png, before-390.png, before-metrics.json, metrics.json and all five required crd035 screenshots. Screenshots are not committed.

Director review: http://127.0.0.1:18143/index.html?review=crd035 — Mi Perfil → Información → Datos generales.
