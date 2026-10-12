# DEV-WIDGET04 — restore the historical floating widget

Baseline: 8b54e82fdafa74b31184acf8c449e846f727d2eb.
Suppression commit: 2f02df39a52fe9bda7cce686de81eba7f7b96320.
Exact pre-hide reference: a8e3087ff406230a27d21df8ee650fd45691eccb.

`git show 2f02df39` establishes that suppression only added the CSS rule and
stylesheet link. `assets/js/app.js::renderRoleSwitcher` remains the historical
shell: white Bootstrap panel, same classes, max-width 390px, fixed right/bottom
12px, z-index 2300, same select sizes and hierarchy. There was no header or
collapse button in the reference. Added only a small Contraer QA / Mostrar QA
button, preserving the shell rather than replacing it with a details/card UI.

## Recovered controls

1. Rol QA: Médico (`doctor`), Operador 01 (`operator_1`), Operador 02 (`operator_2`).
   Historical local role simulation remains; it grants no canonical Identity session.
2. CLASIFICACIÓN: Real del perfil plus the historical 134 classification options.
   The adjacent classification-simulator.js inserted this control before Plan QA
   in the pre-hide reference (introduced by 10e7f5e3). Its navigation-only state
   and sessionStorage semantics are unchanged. Initialization now waits for the
   server-authorized widget rather than racing asynchronous authorization.
3. Plan QA: Real, Estándar, Óptimo, Profesional. The historical select now writes
   through DEV-WIDGET03 and separately GETs server truth. Persisted browser plan
   overrides are not read by this control or normal-local subscription UI.
4. Entidad QA real: same label/select location; fixed disabled option
   `1 — Leticia Muñoz Romo`. The fixture-selection writer was removed. Its old
   arbitrary QA fixture choices create legacy session_scope and conflict with
   the approved doctor/1 boundary; they are intentionally not restored.

Gratuito/Básico are omitted because DEV-WIDGET03 only authorizes four scenarios.
No user-managed/advisor-managed/claim/ownership selector existed in this reference.
Rol QA is not a management enum. Administration remains a server read-only value
in the existing status-text slot, alongside real plan and server capabilities.

## Visibility and backend preservation

The original widget initializes only on the canonical HTTPS root/index, after a
successful guarded endpoint response. `qa_tools=hide` suppresses it. CSS exposes
only the server-authorized shell. No widget is rendered for a forbidden endpoint,
staging/production, other origins or public profile paths.

The endpoint, scenario storage, read model, capability resolver, conflict/reset
logic and guards from DEV-WIDGET03 are unchanged in this task. New DEV-WIDGET03
right-rail JS/CSS and index links are removed. No 292px page reservation remains.
The packaging manifest no longer references the removed assets.

## Validation

Run explicitly against the running normal-local server:
`python3 modules/subscriptions/tests/NormalLocalFloatingWidgetBrowserTest.py --live-normal-local`

The test replays exact pre-hide app.js and classification JS in an isolated fresh
browser context, with the suppression stylesheet removed, to capture a historical
reference. This is a reconstructed reference, not an archival screenshot. All
non-GET historical requests are blocked. Screenshots crop the widget only.

Current UI at 1440x900 and 1366x768: all four scenarios verified against actual
endpoint capabilities; all three roles; 134 classification choices available,
selection/reset; fixed target; collapse/expand; no horizontal overflow; old rail
absent. No fixture-session request. Final Real. Subscription/account/credential/
membership/patient-link checksums unchanged. Dedicated local certificate pinned
for the isolated Chromium process; no machine trust changes.

Guard tests and CurrentSubscriptionFeatureAccessReadModelTest pass. Additional
browser checks verify no widget when the endpoint denies authorization, on the
public path or with qa_tools=hide; persisted browser plan values are ignored.

Director: open https://localhost:8443/, reload, use Plan QA. Contraer QA leaves a
compact Mostrar QA button in the original lower-right corner. Real resets the
scenario. This does not implement Director login or the clinical Identity bridge.
