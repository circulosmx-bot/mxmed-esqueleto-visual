"""DOC-HELP02 / RESP-UX02 browser checks against the disposable clinical gate."""
import os
import subprocess
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
WIDTH, HEIGHT = map(int, os.environ.get('DOC_HELP02_VIEWPORT', '1440x900').split('x'))
CAPTURES = Path(os.environ.get('DOC_HELP02_CAPTURES', tempfile.gettempdir()))
CAPTURES.mkdir(parents=True, exist_ok=True)


def check(name, condition):
    assert condition, name
    print(f'{name}=PASS', flush=True)


def shot(page, name):
    path = CAPTURES / f'doc-help02-{WIDTH}x{HEIGHT}-{name}.png'
    page.screenshot(path=str(path))
    print(f'SCREENSHOT={path}', flush=True)


with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    page = browser.new_page(viewport={'width': WIDTH, 'height': HEIGHT},
                            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-responsiva"]').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    field = page.locator('#rm_clinical_situation')
    trigger = page.locator('[data-example-for="rm_clinical_situation"]')
    check('E14', field.input_value() == '')
    trigger.click()
    dialog = page.locator('#modalResponsivaMedica .mxeh-overlay:not([hidden])')
    selected = dialog.locator('.mxeh-section.is-current')
    body = selected.locator('p').inner_text()
    check('E13', dialog.get_by_text('Llenar toda').count() == 0)
    check('A01', selected.count() == 1 and 'Campo consultado' in selected.inner_text())
    check('A02', dialog.locator('.mxeh-section.is-context').count() == 4)
    check('A03', all(section.locator('p').inner_text() for section in dialog.locator('.mxeh-section').all()))
    check('A04', dialog.locator('.mxeh-current:visible').count() == 1)
    check('E14_OPEN', field.input_value() == '')
    shot(page, 'selected-example')
    selected.locator('.mxeh-use').click()
    check('E01', field.input_value() == body)
    check('E02', all(page.locator('#rm_' + key).input_value() == '' for key in
          ('indicated_conduct', 'relevant_risk', 'additional_manifestation')))
    check('E03', field.input_value() == body and 'Campo consultado' not in field.input_value())
    check('E04', not dialog.is_visible() and field.evaluate('(e)=>document.activeElement===e'))
    field.fill('Texto original')
    check('E05', field.input_value() == 'Texto original')
    trigger.click()
    selected.locator('.mxeh-use').click()
    check('E06', dialog.locator('.mxeh-confirm').is_visible())
    shot(page, 'replace-confirmation')
    dialog.locator('.mxeh-cancel').click()
    check('E07', field.input_value() == 'Texto original')
    selected.locator('.mxeh-use').click()
    dialog.locator('.mxeh-replace').click()
    check('E08', field.input_value() == body)
    check('V02', page.locator('#rm_wizard .save-ok:visible').count() == 0)
    check('V06', field.evaluate('(e)=>parseFloat(getComputedStyle(e).paddingRight) < 30'))
    shot(page, 'narrative-no-check')

    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    page.locator('#rm_signer_role').select_option('tutor')
    char = page.locator('#rm_signer_character')
    rel = page.locator('#rm_signer_relationship')
    check('R01', 'Tutor legal' in char.locator('option').all_inner_texts())
    char.select_option('Tutor legal')
    rel.select_option('Madre')
    check('R04', rel.input_value() == 'Madre')
    shot(page, 'representative-selects')
    char.select_option('__other__')
    check('R02', page.locator('#rm_signer_character_other').is_visible())
    page.locator('#rm_signer_character_other').fill('Tutora por resolución')
    shot(page, 'character-other')
    rel.select_option('__other__')
    check('R05', page.locator('#rm_signer_relationship_other').is_visible())
    page.locator('#rm_signer_relationship_other').fill('Vínculo no listado')
    shot(page, 'relationship-other')
    page.locator('#rm_signer_role').select_option('paciente')
    check('R11', not char.is_visible() and not rel.is_visible())
    page.locator('#rm_signer_role').select_option('tutor')
    check('R12', char.input_value() == '__other__' and rel.input_value() == '__other__'
          and page.locator('#rm_signer_character_other').input_value() == 'Tutora por resolución'
          and page.locator('#rm_signer_relationship_other').input_value() == 'Vínculo no listado')
    page.locator('#rm_signer_name').fill('Representante QA')
    for _ in range(2):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_be_visible(timeout=15000)
    expect(page.locator('#rm_content_review')).to_contain_text(body, timeout=15000)
    check('E10', body in page.locator('#rm_content_review').inner_text())
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    for selector in ('#rm_signer_signature_canvas', '#rm_doctor_signature_canvas'):
        page.locator(selector).scroll_into_view_if_needed()
        page.wait_for_timeout(200)
        box = page.locator(selector).bounding_box()
        page.mouse.move(box['x'] + 30, box['y'] + 30)
        page.mouse.down()
        page.mouse.move(box['x'] + 150, box['y'] + 75, steps=10)
        page.mouse.up()
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#rm_save').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    db = os.environ['FLOW_R1_QA_DB']
    persisted = subprocess.check_output(['mysql', '--raw', '-N', '-B', db, '-e',
        "SELECT CONCAT_WS('|',JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.signer.character')),JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.signer.relationship'))) FROM clinical_documents WHERE document_type='responsiva_medica'"], text=True).strip()
    check('R03_R06', persisted == 'Tutora por resolución|Vínculo no listado')
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    page.locator('#t-consent .vis06-row').filter(has_text='Responsiva médica').get_by_role(
        'button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(2):
        page.locator('#rm_next').click()
    check('R08_R10', char.input_value() == '__other__' and rel.input_value() == '__other__'
          and page.locator('#rm_signer_character_other').input_value() == 'Tutora por resolución'
          and page.locator('#rm_signer_relationship_other').input_value() == 'Vínculo no listado')
    check('E09', field.input_value() == body)
    page.locator('#rm_prev').click()
    page.locator('#rm_additional_info summary').click()
    page.locator('[data-example-for="rm_additional_manifestation"]').click()
    page.locator('#modalResponsivaMedica .mxeh-overlay:not([hidden]) .mxeh-section.is-current .mxeh-use').click()
    check('E11_E12', page.locator('#rm_additional_manifestation').input_value() != ''
          and 'vinculada a esta versión' not in page.locator('#rm_signer_signature_status').inner_text())
    page.locator('#rm_next').click()
    char.select_option('Tutor legal')
    rel.select_option('Madre')
    check('R13_R14', 'vinculada a esta versión' not in page.locator('#rm_signer_signature_status').inner_text())
    page.locator('#modalResponsivaMedica .btn-close').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden()
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow').get_by_role('button', name='Empezar en blanco').click()
    page.locator('#ci_next').click()
    consent = page.locator('#modalConsentimientoInformado')
    check('C01', consent.locator('.mxeh-trigger').count() >= 8 and consent.locator('.ci-example-trigger').count() == 0)
    consent.locator('.mxeh-trigger').first.click()
    consent_dialog = consent.locator('.mxeh-overlay:not([hidden])')
    check('C04', consent_dialog.locator('.mxeh-section.is-current').count() == 1)
    check('CONS_HELP02_ENABLED', consent_dialog.locator('.mxeh-section.is-current .mxeh-use:visible').count() == 1)
    check('C03', 'Extirpación de una lesión cutánea superficial' in consent_dialog.inner_text())
    shot(page, 'consent-shared-help')
    consent_dialog.locator('.mxeh-close').click()
    check('C05', consent.locator('.mxeh-trigger').first.evaluate('(e)=>document.activeElement===e'))
    check('NO_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('NO_PAGE_ERRORS', not errors)
    browser.close()
