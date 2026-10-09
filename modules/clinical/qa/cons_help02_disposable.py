"""CONS-HELP02 field insertion and consent lifecycle on disposable data."""
import os
import re
import subprocess
import tempfile
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
with open('modules/clinical/db/migrations/2026_10_06_29_consent_templates.sql') as migration:
    subprocess.run(['mysql', DB], input=migration.read(), text=True, check=True)
WIDTH, HEIGHT = map(int, os.environ.get('CONS_HELP02_VIEWPORT', '1440x900').split('x'))
OUT = Path(os.environ.get('CONS_HELP02_CAPTURES', tempfile.gettempdir()))
OUT.mkdir(parents=True, exist_ok=True)


def check(code, value):
    assert value, code
    print(f'{code}=PASS', flush=True)


def shot(page, name):
    path = OUT / f'cons-help02-{WIDTH}x{HEIGHT}-{name}.png'
    page.screenshot(path=str(path))
    print(f'SCREENSHOT={path}', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


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
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    page.locator('#ci_next').click()
    modal = page.locator('#modalConsentimientoInformado')
    page.locator('#ci_template').select_option('procedimiento')
    field = page.locator('#ci_procedimiento')
    trigger = modal.locator('[data-example-for="ci_procedimiento"]')
    keys = ('procedimiento', 'objetivo', 'risk_common', 'risk_infrequent', 'risk_rare_serious',
            'beneficios_esperados', 'alternativas', 'consecuencias_no_aceptar')
    full = ('full_procedimiento', 'full_objetivo', 'full_risk_common', 'full_risk_infrequent',
            'full_risk_rare_serious', 'full_beneficios', 'full_alternativas', 'full_consecuencias')
    check('FIELD_MAP_16', all(modal.locator(f'[data-example-for="ci_{key}"]').count() == 1 for key in keys + full))
    check('C11', field.input_value() == '')
    trigger.click()
    dialog = modal.locator('.mxeh-overlay:not([hidden])')
    selected = dialog.locator('.mxeh-section.is-current')
    body = selected.locator('p').inner_text()
    check('C01_C02', selected.locator('.mxeh-use:visible').count() == 1
          and dialog.locator('.mxeh-section.is-context .mxeh-use:visible').count() == 0)
    check('EDUCATIONAL_NOTE', 'Caso ficticio únicamente como referencia de redacción' in dialog.inner_text())
    check('C11_OPEN', field.input_value() == '')
    shot(page, 'selected-example')
    selected.locator('.mxeh-use').focus()
    page.keyboard.press('Enter')
    check('C03_C04', field.input_value() == body and 'Campo consultado' not in field.input_value())
    check('C05_C06', not dialog.is_visible() and field.evaluate('(e)=>document.activeElement===e'))
    field.fill('Texto original')
    check('C07', field.input_value() == 'Texto original')
    trigger.click()
    selected.locator('.mxeh-use').click()
    check('C08', dialog.locator('.mxeh-confirm').is_visible()
          and dialog.locator('.mxeh-replace').evaluate('(e)=>document.activeElement===e'))
    shot(page, 'replacement')
    dialog.locator('.mxeh-cancel').click()
    check('C09', field.input_value() == 'Texto original')
    selected.locator('.mxeh-use').click()
    dialog.locator('.mxeh-replace').click()
    check('C10', field.input_value() == body)
    check('ONE_FIELD', page.locator('#ci_objetivo').input_value() == '')
    page.locator('#ci_title').fill('Consentimiento sintético CONS-HELP02')
    page.locator('#ci_save').click()
    page.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Borrador guardado')", timeout=20000)
    check('C12_DRAFT', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'") == '1')
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    expect(modal).to_be_visible()
    if page.locator('#ci_next').is_visible():
        page.locator('#ci_next').click()
    check('C16', field.input_value() == body and page.locator('#ci_full_procedimiento').input_value() == body)
    page.locator('#ci_mode_full').click()
    expect(page.locator('#ci_full_view')).to_be_visible()
    full_objective = page.locator('#ci_full_objetivo')
    check('FULL_EMPTY', full_objective.input_value() == '')
    modal.locator('[data-example-for="ci_full_objetivo"]').click()
    full_body = dialog.locator('.mxeh-section.is-current p').inner_text()
    dialog.locator('.mxeh-section.is-current .mxeh-use').click()
    check('FULL_MAPPING', full_objective.input_value() == full_body
          and page.locator('#ci_objetivo').input_value() == full_body)
    page.locator('#ci_preview').click()
    expect(page.locator('#ci_review_html')).to_contain_text(body, timeout=15000)
    check('C13', full_body in page.locator('#ci_review_html').inner_text())
    shot(page, 'canonical-preview')
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()
    canvas = page.locator('#ci_signature_canvas')
    canvas.scroll_into_view_if_needed()
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 25, box['y'] + 25)
    page.mouse.down()
    page.mouse.move(box['x'] + 140, box['y'] + 65, steps=8)
    page.mouse.up()
    expect(page.locator('#ci_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    page.locator('#ci_review_back_edit').click()
    expect(page.locator('#ci_full_view')).to_be_visible()
    modal.locator('[data-example-for="ci_full_alternativas"]').click()
    dialog.locator('.mxeh-section.is-current .mxeh-use').click()
    check('C14_C15', page.locator('#ci_full_alternativas').input_value() != ''
          and 'vinculada a esta versión' not in page.locator('#ci_signature_status').inner_text())
    check('C19', page.locator('#ci_emit').is_hidden())
    check('NO_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('NO_PAGE_ERRORS', not errors)
    browser.close()
