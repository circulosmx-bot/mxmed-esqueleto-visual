# CRD03.10 — Current public name and real runtime explicit save

Starting branch: `design/physician-crd03-credentials-ui-v1`; accepted baseline: `e62c0dbe5428e323001b23f5c813ba579dea7566`. Initial worktree was clean and remote HEAD matched.

## Real reproduction before source changes

The Director runtime at `127.0.0.1:18143/index.html?review=crd039`, both with and without `qa_tools=hide`, returned:

- Current public display name: `Dra. Leticia Muñoz Romo`.
- Verified identity: given_names `Leticia`, first_surname `Muñoz`, second_surname `Romo`, full_name `Leticia Muñoz Romo`.
- Canonical policy current_display_name: `Dra. Leticia Muñoz Romo`; allowed_given_name_presentations: `["Leticia"]`; existing status: `LEGACY_NONCONFORMING`.
- Nombre(s) selected value: empty; visible option: `Selecciona una presentación`.
- One dirty-tracker factory/controller instance. Script order was dirty-tracker, app, Header account, Datos Generales. Baseline captured after identity, name policy, prefix, designation, Bio and canonical theme hydration. The floating component was present in the DOM, initially hidden.

Persisted baseline and initial normalized form state were identical:

```json
{
  "bio_short": "Especialista en alteraciónes del sistema endocrino y enfermedades metabólicas.",
  "display_name": "Dra. Leticia Muñoz Romo",
  "givenPresentation": "",
  "prefix": "Dra.",
  "professional_designation": "Endocrinóloga",
  "profile_theme_key": null,
  "showSecondSurname": true
}
```

Trusted Chrome keyboard input changed designation to `EndocrinólogaX`. The tracker returned dirty=true, hidden=false, display=block and visibility=visible. The button rectangle was x=1253.016, y=828, width=162.984, height=48 at 1440×900. Without qa_tools=hide, elementFromPoint at the button center returned `mxmed_dev_role_switcher`; with hide, it returned `mxpi-save-btn`. No mutation request was sent.

REAL_RUNTIME_DIRTY_ROOT_CAUSE=QA_OVERLAY_OCCLUSION: the QA panel at z-index 2300 covered the correctly visible floating action at z-index 1040. No missing script, listener, controller, baseline, input observation or initialization duplication was observed. The Director-visible symptom was reproduced; a broken dirty comparison was not reproduced.

## Source correction

Name hydration compares policy-supplied allowed presentations combined with canonical surnames against public_name_policy.current_display_name (falling back to the identity_public read field only when absent). A known Dr./Dra. prefix or the independently hydrated professional prefix can precede the canonical candidate. This is exact candidate matching with existing Unicode/spacing comparison, not arbitrary free-text parsing. The current matched presentation becomes selected, and a placeholder appears only when no allowed current presentation can be resolved. All real policy-supplied options remain available.

The exact persisted legacy display_name is preserved on load and on sibling saves. Name dirty state and legacy name payload inclusion compare actual name-control values against the captured baseline; changing/reverting a checkbox cannot cause an unrelated later Bio save to rewrite the legacy name. The existing public summary now previews controlled name/prefix edits rather than relying on a removed historical preview node. No verified identity, public-name server policy or prefix authority is changed.

The initial private-profile read starts once after shell initialization; identity and theme hydrate before one normalized baseline capture. Existing mounted-draft and late-read guards remain. Selecting the original default theme when stored_key is null restores that local null baseline instead of creating an unintended explicit default-key change. Reset still remains local until explicit Save.

The existing floating component adds exact status text `Cambios sin guardar` next to `Guardar cambios`. Production-equivalent placement stays right/bottom 24px on desktop; mobile uses 16px horizontal margins and 16px plus safe-area bottom spacing. Mobile stacks status above the button and reserves 128px plus safe-area bottom content padding. Existing grouped save, Guardando…, duplicate-submission guard, success baseline, four-second success feedback, failure/retry and concurrent-edit preservation remain.

## Runtime-only QA collision correction

Only the external localhost router `/tmp/mxmed-qa02-20260912/director-router.php` changes QA accommodations. For explicit crd039/crd0310 review URLs without hide, it measures the QA widget with ResizeObserver and offsets the action above it, also adding local bottom scroll padding. Both remain accessible. With qa_tools=hide, it hides the QA panel and leaves the source production positioning unchanged. No QA-widget selector, position variable, router, endpoint or QA hook is shipped in product CSS/JS. Default index.html HTTP bytes without explicit review parameters still match canonical source.

## Validation and safety

`ExplicitProfileSaveRealRuntimeBrowserTest.mjs` uses actual HTTP data/controller integration, without fetch interception or mocked authority responses. Text/textarea edits, checkbox, theme and button clicks use trusted CDP keyboard/mouse input. Select controls use standard select-option automation restricted to the options returned by the real canonical read model; no names are invented. Read-only instrumentation observes controller instances, baseline captures and dirty state without changing decisions.

Real Director QA passed 1440×900, 1366×768 and 390×844, with QA visible and hidden: Leticia selected, Dra. prefix, exact public summary, clean load, designation/Bio/prefix/surname/theme changes and exact reversions, original-default theme reversal, local reset, one baseline capture with no recapture while editing, reload without save, reachable component and no horizontal overflow. Zero Director mutation requests were sent.

A fresh, disposable MySQL 8.4 database on port 3331 used canonical synthetic `Luis Armando Reynoso Femat`, starting with public `Dr. Luis Reynoso Femat`, prefix Dr., and stored medical_blue. Its temporary PHP server used the exact current source and actual private API. Real browser selection of another allowed presentation, prefix/designation/Bio/theme edits sent ONE grouped PATCH only after explicit Save. Reload selected Armando and returned exact persisted values with VALID name policy. Reset differed from its stored theme and triggered dirty correctly. The fixture was restored exactly in finally before the entire disposable database was dropped. This also reran existing grouped persistence, Bio150/151 Unicode/save/reload/null/atomicity, blocked gender and theme catalog/save/reload/reset persistence tests.

Existing regressions passed: VID02 (all six combinations, true unresolved legacy placeholder, prefixed hydration, sibling-save preservation, explicit rejection and legacy fallback), explicit-save success/failure/retry/double-submit/concurrent edits, Bio browser Unicode/paste/150, accepted name density and CRD038 swatches/copy/reset/focus/keyboard/60 public themes, top utility actions/verified modal/Header/account/Sidebar/Agenda, independent photo/logo/gallery review upload/submission/withdrawal and no form dirty. Tests whose accepted expectation was the old empty Leticia selector were updated to assert Leticia; grouped mock scenarios now make a real surname change and return a consistent canonical policy response.

Public profile, backend, credentials, identity authority, schema, contacts, media, gallery assets, Header/Sidebar source and signature logic remain unchanged. Administrative contact explicit-save migration stays DEFERRED; SIG01 remains DEFERRED_UNCHANGED. Director private read-model and full database table snapshots are compared before/after. Screenshots, original reproduction traces and disposable harnesses remain under `/tmp/mxmed-crd0310`, outside Git. Runtime assembly/hash checks and JS syntax/diff checks are required before closeout.

## Review

`http://127.0.0.1:18143/index.html?review=crd0310&qa_tools=hide` (production-equivalent positioning).

`http://127.0.0.1:18143/index.html?review=crd0310` (both local QA and floating actions accessible).

Run real Director regression: `QA_OUTPUT=/tmp/mxmed-crd0310 node modules/profiles/tests/ExplicitProfileSaveRealRuntimeBrowserTest.mjs`.

Actual save mode refuses the Director port, requires MXMED_CRD0310_DISPOSABLE=1 and MXMED_CRD0310_DISPOSABLE_DB_PORT=3331, and requires the canonical Luis Armando synthetic identity from a separately provisioned disposable fixture. It never writes through the default Director mode.
