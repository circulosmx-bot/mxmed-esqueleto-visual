"""Browser regression for Responsiva capture hierarchy on disposable clinical data."""
import os
import subprocess
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright


BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
WIDTH, HEIGHT = map(int, os.environ.get('RESP_QA_VIEWPORT', '1440x900').split('x'))
CAPTURES = Path(os.environ.get('RESP_FORM01_CAPTURES', tempfile.gettempdir()))
CAPTURES.mkdir(parents=True, exist_ok=True)


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(name + '=PASS', flush=True)


def rows():
    return int(subprocess.check_output([
        'mysql', '-N', '-B', DB, '-e',
        "SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'"
    ], text=True).strip())


def shot(page, name):
    target = CAPTURES / f'resp-form01-{WIDTH}x{HEIGHT}-{name}.png'
    page.screenshot(path=str(target))
    print('SCREENSHOT=' + str(target), flush=True)


def draw(page, selector):
    page.locator(selector).scroll_into_view_if_needed()
    page.wait_for_timeout(200)
    box = page.locator(selector).bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 30)
    page.mouse.down()
    page.mouse.move(box['x'] + 150, box['y'] + 75, steps=10)
    page.mouse.up()


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
    check('F01', page.locator('#rm_step_1').is_visible() and page.locator('#rm_clinical_situation').is_visible())
    check('F02', all(page.locator('#' + field).get_attribute('readonly') is not None
                     for field in ('rm_date', 'rm_patient_name', 'rm_patient_age', 'rm_patient_sex', 'rm_doctor_name')))
    check('F04', not page.locator('#rm_additional_info').evaluate('(el)=>el.open'))
    shot(page, 'context')
    page.locator('#rm_clinical_situation').fill('Situación clínica sintética FORM01')
    page.locator('#rm_next').click()
    check('F03', page.locator('#rm_declaration_text').is_visible() and page.locator('#rm_declaration_text').get_attribute('rows') == '7')
    shot(page, 'declaration-collapsed')
    page.locator('#rm_additional_info summary').click()
    check('F05', page.locator('#rm_indicated_conduct').is_visible())
    page.locator('#rm_indicated_conduct').fill('Indicación sintética')
    page.locator('#rm_relevant_risk').fill('Riesgo sintético')
    page.locator('#rm_additional_manifestation').fill('Manifestación sintética')
    check('F07', all(page.locator('#' + field).input_value() == value for field, value in (
        ('rm_indicated_conduct', 'Indicación sintética'),
        ('rm_relevant_risk', 'Riesgo sintético'),
        ('rm_additional_manifestation', 'Manifestación sintética'))))
    shot(page, 'additional-expanded')
    page.locator('#rm_next').click()
    check('F08', not page.locator('#rm_signer_name').is_visible() and not page.locator('#rm_signer_character').is_visible()
          and page.locator('#rm_patient_signer_context').is_visible())
    shot(page, 'patient-signer')
    page.locator('#rm_signer_role').select_option('tutor')
    check('F09', all(page.locator('#' + field).is_visible() for field in
                     ('rm_signer_name', 'rm_signer_character', 'rm_signer_relationship')))
    page.locator('#rm_signer_name').fill('Representante sintético')
    page.locator('#rm_signer_character').fill('Tutora')
    page.locator('#rm_signer_relationship').fill('Madre')
    shot(page, 'representative')
    page.locator('#rm_signer_role').select_option('paciente')
    check('F10_HIDE', not page.locator('#rm_signer_name').is_visible())
    page.locator('#rm_signer_role').select_option('tutor')
    check('F10_RESTORE', page.locator('#rm_signer_name').input_value() == 'Representante sintético'
          and page.locator('#rm_signer_character').input_value() == 'Tutora'
          and page.locator('#rm_signer_relationship').input_value() == 'Madre')
    check('F11', page.locator('#rm_signer_character').get_attribute('id') !=
          page.locator('#rm_signer_relationship').get_attribute('id'))
    page.locator('#rm_next').click()
    check('F12', page.locator('#rm_professional_header_shown').is_checked()
          and page.locator('#rm_professional_header_hidden').is_visible())
    shot(page, 'professional-header')
    page.locator('#rm_professional_header_hidden').check()
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación clínica sintética FORM01')
    check('F13', rows() == 0 and page.locator('#rm_content_review').is_visible())
    shot(page, 'preview')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    check('F14', rows() == 1 and page.locator('#rm_signer_signature_canvas').is_visible())
    check('F15', page.locator('#rm_signer_signature_status').is_visible()
          and page.locator('#rm_doctor_signature_status').is_visible())
    shot(page, 'signatures')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_7')).to_be_visible(timeout=15000)
    check('F16', page.locator('#rm_final_review').is_visible())
    check('F17_GATE', page.locator('#rm_emit').is_disabled())
    shot(page, 'final-review')
    page.locator('#rm_save').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    page.locator('#t-consent .vis06-row').filter(has_text='Responsiva médica').get_by_role(
        'button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    check('F06', page.locator('#rm_additional_info').evaluate('(el)=>el.open'))
    check('F07_RESUME', all(page.locator('#' + field).input_value() == value for field, value in (
        ('rm_indicated_conduct', 'Indicación sintética'),
        ('rm_relevant_risk', 'Riesgo sintético'),
        ('rm_additional_manifestation', 'Manifestación sintética'))))
    check('F09_RESUME', page.locator('#rm_signer_role').input_value() == 'tutor'
          and page.locator('#rm_signer_character').input_value() == 'Tutora'
          and page.locator('#rm_signer_relationship').input_value() == 'Madre')
    check('F12_RESUME', page.locator('#rm_professional_header_hidden').is_checked())
    check('NO_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('NO_PAGE_ERRORS', not errors)
    if not page.locator('#rm_step_2').is_visible():
        page.locator('#rm_next' if page.locator('#rm_step_1').is_visible() else '#rm_prev').click()
    shot(page, 'resumed-additional')
    while not page.locator('#rm_step_5').is_visible():
        page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación clínica sintética FORM01')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    draw(page, '#rm_signer_signature_canvas')
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    draw(page, '#rm_doctor_signature_canvas')
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_emit')).to_be_enabled(timeout=15000)
    check('F15_BOTH_SIGNED', True)
    check('F16_FINAL_PREVIEW', 'Situación clínica sintética FORM01' in page.locator('#rm_final_review').inner_text())
    page.locator('#rm_emit').click()
    expect(page.locator('#modalDocumentPostEmission')).to_be_visible(timeout=15000)
    check('F17', rows() == 1)
    with page.expect_popup() as view:
        page.locator('#modalDocumentPostEmission [data-post-emission="view"]').click()
    view.value.wait_for_load_state('domcontentloaded')
    expect(view.value.locator('.responsiva-doc-sheet')).to_be_visible(timeout=15000)
    view.value.close()
    with page.expect_popup() as printable:
        page.locator('#modalDocumentPostEmission [data-post-emission="print"]').click()
    printable.value.wait_for_load_state('domcontentloaded')
    expect(printable.value.locator('.responsiva-doc-sheet')).to_be_visible(timeout=15000)
    printable.value.close()
    page.locator('#modalDocumentPostEmission [data-post-emission="close"]').click()
    expect(page.locator('#modalDocumentPostEmission')).to_be_hidden()
    check('F18', True)
    browser.close()
