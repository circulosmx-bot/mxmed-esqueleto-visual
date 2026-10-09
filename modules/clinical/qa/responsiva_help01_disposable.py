"""RESP-HELP01 educational help and non-mutation browser QA on disposable data."""
import os
import subprocess
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright


BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
WIDTH, HEIGHT = map(int, os.environ.get('RESP_QA_VIEWPORT', '1440x900').split('x'))
CAPTURES = Path(os.environ.get('RESP_HELP01_CAPTURES', tempfile.gettempdir()))
CAPTURES.mkdir(parents=True, exist_ok=True)


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(name + '=PASS', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def shot(page, name):
    target = CAPTURES / f'resp-help01-{WIDTH}x{HEIGHT}-{name}.png'
    page.screenshot(path=str(target))
    print('SCREENSHOT=' + str(target), flush=True)


def open_help(page, field, selected, capture=None):
    trigger = page.locator(f'[data-example-for="{field}"]')
    trigger.click()
    dialog = page.locator('.mxeh-overlay:not([hidden])')
    expect(dialog).to_be_visible()
    expect(dialog.locator('.mxeh-focus-label')).to_contain_text(selected)
    check('H07_' + field, dialog.locator('.mxeh-section.is-current').count() == 1
          and dialog.locator('.mxeh-section.is-current .mxeh-current').inner_text() == 'Campo consultado')
    if capture:
        shot(page, capture)
    return dialog, trigger


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
    errors, posts = [], []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('request', lambda request: posts.append(request.url) if request.method == 'POST' else None)
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-responsiva"]').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    check('SIX_HELP_FIELDS', page.locator('#rm_wizard .mxeh-trigger').count() == 6)
    check('H16', not page.locator('#rm_additional_info').evaluate('(node)=>node.open'))
    situation = page.locator('#rm_clinical_situation')
    situation.fill('Situación sintética sin firma')
    initial_value = situation.input_value()
    initial_posts = len(posts)
    dialog, trigger = open_help(page, 'rm_clinical_situation', 'Situación clínica', 'clinical-situation')
    check('H01', dialog.locator('.mxeh-section').count() == 5)
    check('H08', all(dialog.get_by_text(text, exact=False).count() for text in (
        'Paciente consciente y orientado', 'Se recomienda traslado',
        'Se explica que posponer', 'Por decisión propia', 'un familiar lo acompañará')))
    check('H19_FOCUS_ENTER', dialog.locator('.mxeh-close').evaluate('(node)=>document.activeElement===node'))
    page.keyboard.press('Escape')
    check('H19_FOCUS_RETURN', trigger.evaluate('(node)=>document.activeElement===node'))
    check('H10', situation.input_value() == initial_value)
    page.locator('#rm_next').click()
    check('H16_AFTER_HELP', not page.locator('#rm_additional_info').evaluate('(node)=>node.open'))
    dialog, _ = open_help(page, 'rm_declaration_text', 'Declaración y argumento', 'declaration')
    check('H04', dialog.locator('.mxeh-section').count() == 5)
    dialog.locator('.mxeh-close').click()
    page.locator('#rm_additional_info summary').click()
    check('H17', page.locator('#rm_additional_info').evaluate('(node)=>node.open'))
    for field, selected, key in (
        ('rm_indicated_conduct', 'Indicación médica', 'H02'),
        ('rm_relevant_risk', 'Riesgo relevante informado', 'H03'),
        ('rm_additional_manifestation', 'Manifestación adicional', 'H05')):
        dialog, _ = open_help(page, field, selected, key.lower())
        check(key, dialog.locator('.mxeh-section').count() == 5)
        dialog.locator('.mxeh-close').click()
        check('H17_' + field, page.locator('#rm_additional_info').evaluate('(node)=>node.open'))
    check('H10_NO_AUTOFILL', all(page.locator('#' + field).input_value() == '' for field in (
        'rm_indicated_conduct', 'rm_relevant_risk', 'rm_additional_manifestation')))
    page.locator('#rm_indicated_conduct').fill('Indicación sintética guardada')
    page.locator('#rm_next').click()
    page.locator('#rm_signer_role').select_option('tutor')
    page.locator('#rm_signer_name').fill('Representante sintético')
    page.locator('#rm_signer_relationship').fill('Madre')
    dialog, _ = open_help(page, 'rm_signer_character', 'Carácter del representante', 'representative-character')
    check('H06', dialog.locator('.mxeh-section').count() == 2)
    check('H09', 'calidad o capacidad' in dialog.inner_text() and 'vínculo' in dialog.inner_text()
          and 'Madre' in dialog.inner_text())
    dialog.locator('.mxeh-close').click()
    check('H11', len(posts) == initial_posts)
    check('H12', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación sintética sin firma')
    check('PREVIEW_STILL_ZERO_ROWS', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    draw(page, '#rm_signer_signature_canvas')
    draw(page, '#rm_doctor_signature_canvas')
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#rm_save').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    before = sql("SELECT CONCAT_WS('|',document_uuid,version,SHA2(CAST(payload_json AS CHAR),256)) FROM clinical_documents WHERE document_type='responsiva_medica'")
    before_fingerprint = sql("SELECT JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.signatures.signer.binding.content_fingerprint')) FROM clinical_documents WHERE document_type='responsiva_medica'")
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    page.locator('#t-consent .vis06-row').filter(has_text='Responsiva médica').get_by_role(
        'button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    check('H18', page.locator('#rm_additional_info').evaluate('(node)=>node.open')
          and page.locator('#rm_indicated_conduct').input_value() == 'Indicación sintética guardada')
    before_help_posts = len(posts)
    dialog, _ = open_help(page, 'rm_clinical_situation', 'Situación clínica', 'resumed-draft')
    dialog.locator('.mxeh-close').click()
    check('H15', len(posts) == before_help_posts and before == sql(
        "SELECT CONCAT_WS('|',document_uuid,version,SHA2(CAST(payload_json AS CHAR),256)) FROM clinical_documents WHERE document_type='responsiva_medica'"))
    for _ in range(5):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    check('H13', before_fingerprint == sql(
        "SELECT JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.signatures.signer.binding.content_fingerprint')) FROM clinical_documents WHERE document_type='responsiva_medica'"))
    check('H14', 'vinculada a esta versión' in page.locator('#rm_signer_signature_status').inner_text()
          and 'vinculada a esta versión' in page.locator('#rm_doctor_signature_status').inner_text())
    page.locator('#modalResponsivaMedica .btn-close').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden()
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_be_visible(timeout=15000)
    page.locator('#modalConsentTemplateFlow').get_by_role('button', name='Empezar en blanco').click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible(timeout=15000)
    page.locator('#ci_next').click()
    expect(page.locator('#ci_step_2')).to_be_visible()
    consent_trigger = page.locator('#modalConsentimientoInformado .ci-example-trigger').first
    check('CONSENT_HELP_TRIGGERS', page.locator('#modalConsentimientoInformado .ci-example-trigger').count() >= 8)
    consent_trigger.click()
    consent_dialog = page.locator('#modalConsentimientoInformado .ci-example-overlay:not([hidden])')
    expect(consent_dialog).to_be_visible()
    check('CONSENT_HELP_CONTENT_UNCHANGED', 'Extirpación de una lesión cutánea superficial' in consent_dialog.inner_text())
    consent_dialog.locator('.ci-example-close').click()
    check('CONSENT_HELP_FOCUS_RETURN', consent_trigger.evaluate('(node)=>document.activeElement===node'))
    check('NO_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('NO_PAGE_ERRORS', not errors)
    browser.close()
