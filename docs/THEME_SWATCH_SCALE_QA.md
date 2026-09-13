# CRD03.8 theme swatch scale refinement

Visible profile-theme samples increase from 38×20px to 72×34px (1.89× width, 1.7× height), with the existing 5px corners. Their interactive buttons are now 78×44px. The centered grid caps at 852px, preserving two ordered rows of ten at both reviewed desktop widths; fixed-size tracks fit automatically into three columns at 390px and fewer columns on narrower screens.

CSS column gaps decrease from 9px to 8px and row gaps from 9px to 4px. Combined with the transparent button margins, the visible rectangle gaps are exactly 14px horizontally and vertically at all three reviewed widths. Before, visible horizontal gaps were 86.906px at 1440, 79.5px at 1366 and 21px at 390; visible vertical gaps were 33px. Swatches retain catalog order, 5px corners, ring/check selection, accessible names/titles and focus outline.

The title remains “Color del perfil”. Supporting copy is exactly “Puedes elegir un color para personalizar tu perfil.” Restablecer reuses the DG primary action class: background var(--dg01-primary), computed #00aebe, white text. Existing hover/focus colors are #008f9e; a scoped focus outline and active inset emphasis keep interaction states clear. Its ID, click handler and reset/save semantics remain unchanged.

## Validation

- NameThemePresentationBrowserTest: real Leticia hydration at 1440×900, 1366×768 and 390×844; measured sample dimensions, target sizes, uniform visible spacing, desktop two-row layout and no overflow; exact helper copy and reset default/hover/focus/active styles (waits for the existing color transition); selected ring/check and keyboard focus/arrows; unchanged twenty theme keys, names and colors; preview/reset do not autosave; grouped PATCH intercepted with unchanged fields and null reset behavior. Sixty public theme previews preserve canonical resolution across all three viewports.
- Console error capture covers Runtime exceptions, console.error and Log error events. Both CRD03.7 baseline and CRD03.8 review produced zero errors.
- ProfileThemeCatalogTest and ProfileThemeIntegrationContractTest pass. No theme authority, catalog, persistence, public resolver or application JS source changed.
- JS syntax and git diff --check pass. Runtime packaging passes with 671 runtime files, zero forbidden files, valid inventory hashes and HTTP/packaged source equality. No PHP source changed.

Full Director database snapshots before/after are byte identical; private profile responses are identical excluding only meta.generated_at. Leticia's current display name, stored theme, profile/media/review state and existing gallery assets remain unchanged. Save/grouped PATCH, media, identity, Header and Sidebar wiring remain unchanged.

Temporary evidence: /tmp/mxmed-crd038/before-metrics.json, before-errors.json, before-theme-{1440,1366,390}.png, metrics.json and crd038-theme-{desktop-1440,selected,desktop-1366,mobile}.png. Screenshots are not committed.

Director review: http://127.0.0.1:18143/index.html?review=crd038 — Mi Perfil → Información → Datos generales.
