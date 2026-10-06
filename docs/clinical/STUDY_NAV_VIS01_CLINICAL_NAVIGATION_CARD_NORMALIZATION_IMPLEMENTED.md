# STUDY-NAV-VIS01 — Clinical navigation card normalization

Baseline: `1c0ab69285a1370b9591a12723c55be81e2a7778` on `ux/consultation-step2-vitals-r1`.

## Step 6 reference inspected

The three existing `flow-action-card` buttons in Consultation Step 6 are defined in `index.html` and styled in `assets/css/expediente-paciente-visual-normalization.css`. The desktop reference has a minimum card height of **84 px**, padding **3 px 14 px**, a **10 px** radius and **1 px** border. Its first icon occupies a **76 × 76 px** container with a **72 px** glyph. The title is **18 px / 22 px**, weight **650**; supporting copy is **15 px / 20 px**, with **5 px** top spacing. The icon/text gap is **16 px** and the right chevron is **22 px**. Hover lightens the background and strengthens the border; `:focus-visible` has a **3 px** outline and **2 px** offset. At desktop heights below 800 px, Step 6 reduces its card/icon/title/copy sizes; at mobile width it uses one column and a 98 px minimum card height.

Study Navigation uses the same visual hierarchy at a density suited to its five root families and multi-column subfamilies. It keeps its existing MXMED colors and icons and has no dependency on Step 6 DOM or behavior.

## Adopted Study Navigation tokens

| Level | Minimum height | Icon container | Glyph | Title | Supporting copy |
| --- | ---: | ---: | ---: | ---: | ---: |
| Root family | 98 px desktop; 94 px mobile | 56 × 56 px | 36 px | 17 px, weight 650 | 13 px / 1.35 |
| Primary subfamily | 78 px | 48 × 48 px | 30 px | 16 px, weight 650 | 12 px / 1.35 where present |
| Secondary navigation | 68 px | 44 × 44 px | 27 px | 14 px, weight 650 | Existing copy only |

Cards have a 10 px radius, 1 px border, larger internal padding, and a centered right chevron. Hover adds a restrained border/background/shadow change; keyboard focus uses a 3 px visible outline. The full button remains the click target. Primary subfamilies and the four secondary Laboratory groups share one scoped card style, with size tokens for their hierarchy. No descriptions were invented for title-only cards. The desktop navigation grid retains multiple columns; mobile uses the existing one-column rule. The existing low-height desktop internal scroll container remains available at 1366×768.

The style is scoped to navigation cards within `.vis06-category-screen` and `.lab-cat02a-screen`. Final study rows, catalog search, the orders-in-preparation panel, routing, data and writers are outside that scope.

## Real runtime QA

Used the authenticated local runtime at `http://127.0.0.1:18148/` with every API write blocked. The browser gate visited the root selector, Laboratory primary and secondary groups, Imagenología, Patología, Funcionales, Procedimientos diagnósticos, and Dental. It checked minimum rendered dimensions, icon container, centered chevron, full-card pointer affordance, visible keyboard focus, and no page-level horizontal overflow. Navigation returned to the root after each family. Screenshots were captured at 1440×900 and 390×844, with the local Director QA panel hidden only for the capture.

| Viewport | Root | Five medical families | Laboratory secondary | Dental | Overflow / accessibility |
| --- | --- | --- | --- | --- | --- |
| 1440×900 | Pass | Pass | Pass | Pass | Pass |
| 1366×768, compact sidebar | Pass | Pass | Pass | Pass | Pass |
| 1366×768, expanded sidebar | Pass | Pass | Pass | Pass | Pass |
| 390×844 | Pass | Pass | Pass | Pass | Pass |

Command: `STUDY_NAV_VIS01_SESSION=director-lon07c-review python3 modules/clinical/qa/study_nav_vis01_live_browser.py`.

The separate six-family final-study selector regression also passed at all four viewports with `STUDY_COVERAGE02_SESSION=director-lon07c-review python3 modules/clinical/qa/study_coverage02_live_browser.py`. The VIS01 CSS selectors do not target those dense study rows or the ORD-COMP preparation panel.
