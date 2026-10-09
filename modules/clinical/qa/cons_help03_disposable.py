"""Type-aware consent help checks against the disposable clinical database."""
import os
import json
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
WIDTH, HEIGHT = map(int, os.environ.get('CONS_HELP03_VIEWPORT', '1440x900').split('x'))
OUT = Path(os.environ.get('CONS_HELP03_CAPTURES', tempfile.gettempdir()))
OUT.mkdir(parents=True, exist_ok=True)


def check(code, value):
    assert value, code
    print(f'{code}=PASS', flush=True)


def shot(page, name):
    path = OUT / f'cons-help03-{WIDTH}x{HEIGHT}-{name}.png'
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
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    page.locator('#ci_next').click()
    modal = page.locator('#modalConsentimientoInformado')
    field = page.locator('#ci_procedimiento')
    trigger = modal.locator('[data-example-for="ci_procedimiento"]')
    dialog = modal.locator('.mxeh-overlay')
    cases = [
        ('', 'tipo de procedimiento sin seleccionar', 'Selecciona un tipo de procedimiento', False, 'empty'),
        ('procedimiento', 'Procedimiento invasivo', 'lesión cutánea superficial', True, 'invasive'),
        ('procedimiento_no_invasivo', 'Procedimiento no invasivo', 'férula externa', True, 'splint'),
        ('procedimiento_diagnostico', 'Procedimiento diagnóstico', 'tomografía computarizada', True, 'diagnostic'),
        ('procedimiento_terapeutico', 'Procedimiento terapéutico', 'herida superficial', True, 'wound'),
        ('anestesia', 'Anestesia / sedación', 'sedante', True, 'sedation'),
        ('transfusion', 'Transfusión', 'glóbulos rojos', True, 'transfusion'),
        ('tratamiento_farmacologico', 'Tratamiento farmacológico', 'apixabán', True, 'anticoagulant'),
        ('investigacion', 'Investigación clínica', 'protocolo concreto', False, 'research'),
        ('otro', 'Otro', 'acto concreto', False, 'other'),
    ]
    for index, (value, label, snippet, insertable, name) in enumerate(cases, 1):
        page.locator('#ci_template').select_option(value)
        before = field.input_value()
        trigger.click()
        check(f'S{index:02d}', label in dialog.locator('.mxeh-category').inner_text()
              and snippet.casefold() in dialog.locator('.mxeh-section.is-current p').inner_text().casefold()
              and dialog.locator('.mxeh-section').count() == 8
              and (dialog.locator('.mxeh-use:visible').count() == 1) == insertable
              and field.input_value() == before)
        if name in ('invasive', 'diagnostic', 'transfusion', 'research', 'other'):
            shot(page, name)
        dialog.locator('.mxeh-close').click()
    check('ONE_OVERLAY', dialog.count() == 1 and trigger.count() == 1)
    page.locator('#ci_template').select_option('procedimiento_diagnostico')
    trigger.click()
    visible_text = dialog.locator('.mxeh-section.is-current p').inner_text()
    page.locator('#ci_template').evaluate("e=>{e.value='transfusion';e.dispatchEvent(new Event('change',{bubbles:true}))}")
    check('SESSION_FROZEN', dialog.locator('.mxeh-section.is-current p').inner_text() == visible_text
          and 'Procedimiento diagnóstico' in dialog.locator('.mxeh-category').inner_text())
    dialog.locator('.mxeh-use:visible').click()
    check('VISIBLE_INSERT_SOURCE', field.input_value() == visible_text)
    trigger.click()
    check('NEXT_OPEN_REFRESH', 'Transfusión' in dialog.locator('.mxeh-category').inner_text()
          and 'glóbulos rojos' in dialog.locator('.mxeh-section.is-current p').inner_text().lower())
    dialog.locator('.mxeh-close').click()
    field.fill('Texto escrito por el médico')
    page.locator('#ci_template').select_option('otro')
    check('TYPE_CHANGE_NO_HELP_MUTATION', field.input_value() == 'Texto escrito por el médico')
    page.locator('#ci_mode_full').click()
    expect(page.locator('#ci_full_view')).to_be_visible()
    page.locator('#ci_template').select_option('transfusion')
    modal.locator('[data-example-for="ci_full_procedimiento"]').click()
    check('FULL_PARITY', 'Transfusión' in dialog.locator('.mxeh-category').inner_text()
          and 'glóbulos rojos' in dialog.locator('.mxeh-section.is-current p').inner_text().lower())
    shot(page, 'full-capture')
    check('NO_OVERFLOW', page.evaluate('document.documentElement.scrollWidth <= innerWidth'))
    check('NO_PAGE_ERRORS', not errors)
    # A personal template changes the canonical selector. Help reads that value,
    # then still reads it after the saved draft is resumed.
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    content = {
        'title': 'Plantilla diagnóstica QA', 'procedimiento': 'Narrativa original de la plantilla.',
        'template_key': 'procedimiento_diagnostico', 'objetivo': 'Objetivo original QA.',
        'riesgos': 'Riesgos QA.', 'risk_comunes': 'Comunes QA.',
        'risk_poco_frecuentes': 'Infrecuentes QA.', 'risk_raros_graves': 'Graves QA.',
        'beneficios_esperados': 'Beneficios QA.', 'alternativas': 'Alternativas QA.',
        'consecuencias_no_aceptar': 'Consecuencias QA.', 'autorizacion_contingencias': True,
    }
    response = api.post(BASE + '/api/clinical/index.php/doctors/1/consent-templates',
                        data=json.dumps({'template_name': 'Diagnóstico CONS-HELP03', 'content': content}),
                        headers={'Content-Type': 'application/json'})
    check('TEMPLATE_SEEDED', response.status == 201)
    second = browser.new_page(viewport={'width': WIDTH, 'height': HEIGHT},
                              extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    second.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    second.wait_for_function('typeof window.setActivePatientId === "function"')
    second.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    second.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    second.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    second.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    second.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    second.locator('[data-action="documents-open-consent"]').click()
    second.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    second.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text='Diagnóstico CONS-HELP03').locator('[data-tpl-action="use"]').click()
    second.locator('#ci_next').click()
    check('TEMPLATE_KEY_APPLIED', second.locator('#ci_template').input_value() == 'procedimiento_diagnostico'
          and second.locator('#ci_procedimiento').input_value() == content['procedimiento'])
    second.locator('#modalConsentimientoInformado [data-example-for="ci_procedimiento"]').click()
    check('TEMPLATE_HELP_FOLLOWS_KEY', 'Procedimiento diagnóstico' in second.locator('.mxeh-overlay:not([hidden]) .mxeh-category').inner_text()
          and second.locator('#ci_procedimiento').input_value() == content['procedimiento'])
    second.locator('#modalConsentimientoInformado .mxeh-close').click()
    second.locator('#ci_save').click()
    second.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Borrador guardado')", timeout=20000)
    second.locator('[data-action="documents-open-consent"]').click()
    second.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    if second.locator('#ci_next').is_visible():
        second.locator('#ci_next').click()
    second.locator('#modalConsentimientoInformado [data-example-for="ci_procedimiento"]').click()
    check('DRAFT_HELP_FOLLOWS_SAVED_KEY', second.locator('#ci_template').input_value() == 'procedimiento_diagnostico'
          and 'Procedimiento diagnóstico' in second.locator('.mxeh-overlay:not([hidden]) .mxeh-category').inner_text()
          and second.locator('#ci_procedimiento').input_value() == content['procedimiento'])
    browser.close()
