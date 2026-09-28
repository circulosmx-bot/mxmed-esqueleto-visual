# Consultation shell R4 and Step 2 void

Accepted starting source: `51a9c74413a1ebd2a67a46f03299259270c12b9f`.
The branch was clean and matched its remote before work began.

## Remove diagnosis and repair

The reported failure belongs to the R4 browser-only Director fixture. Its invented
observation `99000` was absent from the canonical encounter. The existing UI opened
confirmation and submitted patient scope and row version to the correct POST void
route. The fixture returned `403 DIRECTOR_VISUAL_REVIEW_READ_ONLY`.

The real canonical UI command passes against an owned disposable database. Creation
uses `SERVER_AT_SAVE`; successful void increments the row version, persists
`invalidated_at`, actor and reason, retains the original row/value/time/provenance,
removes the chip after readback, and restores its selector type. Failed writes keep
the chip. Stale versions, mismatched patient/encounter and anonymous commands are
rejected without changing the row.

Production measurement JavaScript, API, integrity service and schema are unchanged.
The R4 fixture alone now simulates void of its existing browser rows. Its GET
projection follows the canonical split between active `observations` and
`invalidated_observations`. Browser session storage retains simulated audit state
across reload. The removed type can be reused again, including a second void of a
reused example. No invented void/reuse POST reaches the network or clinical storage.
Real writes remain blocked in that fixture.

## Shared shell

One desktop CSS allocation serves all seven steps. At heights 900/880, each of the
two gaps shrinks by 12px: stepper moves up 12px; title moves up 24px in total. At
height 768, each gap shrinks by 10px: stepper moves up 10px; title moves up 20px.
Recovered height is assigned to the central capture panel. The accepted footer
anchor is unchanged. Motivo and Valoración textareas inherit the recovered height.

Exploración, Documentos and Finalizar status nodes now live inside their panels,
retaining their existing attributes and live announcements. They no longer expand
the Consultation header. Steps 6/7 also inherit the same low-height shell padding.
Their content and controls remain available through the central panel's overflow.

Patient subheader and canonical Consultation header coordinates are unchanged.
Circle size, active circle, connector thickness, number/label typography, horizontal
step distribution, title size, header controls, footer actions and step order are
preserved. Narrow-screen Step 2 spacing remains unchanged.

## WebKit coordinates

Coordinates are CSS pixels in document space after fonts settle. Footer baseline
means the bottom of the shared navigation box. All seven steps share each row.

| Viewport | Consultation header top | Stepper centerline | Title top | Footer baseline | Stepper/title delta |
| --- | ---: | ---: | ---: | ---: | --- |
| 1440×900 | 245 | 362.734375 | 439.390625 | 831.8125 | −12 / −24 |
| 1440×880 | 233 | 346.734375 | 423.390625 | 811.8125 | −12 / −24 |
| 1440×768 | 211 | 322.734375 | 393.390625 | 731.8125 | −10 / −20 |
| 1366×768 | 211 | 322.734375 | 393.390625 | 731.8125 | −10 / −20 |

Maximum header, stepper, title and footer drift: **0px**, Steps 1–7 at every listed
viewport. Base Steps 1–5 have no page scroll at all four sizes. Document/terminal
content can scroll inside the allocated panel. Combined removal/reuse leaves the
shell fixed. Tablet 820×1180 and phone 390×844 checks cover chip width and the
previous-values modal. Seven 1440×900 captures and JSON measurements are saved
outside the repository under `.codex/artifacts/consultation-shell-r4`.

## Focused verification

Run from the repository root; WebKit/Playwright, PHP and local disposable MySQL
support are required. Review tests require the guarded Director runtime on 18148.

```sh
SHELL_R4_ARTIFACTS=/tmp/mxmed-consultation-shell-r4 bash modules/clinical/qa/consultation_shell_r4_disposable_gate.sh
SHELL_R4_ARTIFACTS=/tmp/mxmed-consultation-shell-r4 python3 modules/clinical/qa/consultation_shell_r4_browser.py
STEP2_QA_FUNCTIONAL_ONLY=1 STEP2_VITALS_REVIEW_BASE='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide' python3 modules/clinical/qa/step2_vitals_r4_browser.py
python3 modules/clinical/qa/step2_vitals_r1_logic.py
bash modules/clinical/qa/step2_prior_reuse_r3a_disposable_gate.sh
php modules/clinical/qa/vitalref01_registry_test.php
python3 modules/clinical/qa/vitalref01_logic.py
bash modules/clinical/qa/vitalref01_disposable_gate.sh
```

The R4 functional-only option retains its existing interactions while the shell
R4 browser gate supplies the intentional new seven-step geometry checks. Coverage
includes failed save, observation edit/void, used-type filtering, historical reuse,
lost-response idempotent retry, SERVER_AT_SAVE, reference safety/context races,
Plan/Receta preparation, Documentos/Finalizar navigation, VIS32 exit/resume, and
retention of unsaved drafts. Browser gates require no JavaScript errors.
