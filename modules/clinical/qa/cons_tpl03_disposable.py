"""CONS-TPL03 browser and HTTP proof. Run with consultation_flow_r1_disposable_gate.sh."""
import base64
import json
import os
import re
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
OUT = Path(os.environ.get('CONS_TPL03_SCREENSHOTS', '/tmp/cons-tpl03-screenshots'))
OUT.mkdir(parents=True, exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
MIGRATION = Path(__file__).resolve().parents[1] / 'db/migrations/2026_10_06_29_consent_templates.sql'
with MIGRATION.open('rb') as source:
    subprocess.run(['mysql', DB], stdin=source, check=True)

def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', DB, '-e', query], text=True).strip()

def check(name, valid):
    assert valid, name
    print('PASS ' + name, flush=True)

def capture(page, state, focus_selector=None):
    for width, height in [(1366, 768), (1440, 900)]:
        page.set_viewport_size({'width': width, 'height': height})
        if focus_selector: page.locator(focus_selector).scroll_into_view_if_needed()
        page.screenshot(path=str(OUT / f'{state}-{width}x{height}.png'))
        check(f'{state} horizontal fit {width}x{height}',
              page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
    page.set_viewport_size({'width': 1440, 'height': 900})

CONTENT_A = {
    'title': 'Consentimiento para liposucción',
    'procedimiento': 'Técnica de aspiración controlada.',
    'template_key': 'procedimiento',
    'objetivo': 'Remodelación corporal.',
    'riesgos': 'Riesgos explicados.',
    'risk_comunes': 'Equimosis.',
    'risk_poco_frecuentes': 'Infección.',
    'risk_raros_graves': 'Tromboembolismo.',
    'beneficios_esperados': 'Mejoría del contorno.',
    'alternativas': 'Tratamiento no quirúrgico.',
    'consecuencias_no_aceptar': 'Persistencia del contorno.',
    'autorizacion_contingencias': True,
}
ROUTE = BASE + '/api/clinical/index.php/doctors/1/consent-templates'

with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    def request(method, url, body=None):
        response = api.fetch(url, method=method, data=json.dumps(body, ensure_ascii=False) if body is not None else None,
                             headers={'Content-Type': 'application/json'} if body is not None else {})
        return response.status, response.json()
    status, created_a = request('POST', ROUTE, {'template_name': 'Liposucción', 'content': CONTENT_A})
    check('seed personal template A', status == 201)
    source_uuid = created_a['data']['uuid']
    status, archived = request('POST', ROUTE, {'template_name': 'Plantilla archivada', 'content': CONTENT_A})
    archived_uuid = archived['data']['uuid']
    check('seed archived template', request('POST', ROUTE + '/' + archived_uuid + '/archive', {'expected_version': 1})[0] == 200)

    browser = pw.webkit.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 900},
                            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    writes = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('request', lambda req: writes.append(json.loads(req.post_data)) if req.method == 'POST'
            and '/consent-templates' in req.url and req.post_data else None)
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id', 'p_plan02ux_review', timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_be_visible()
    capture(page, 'A-choice')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#ci_tpl_search')).to_be_visible()
    check('T01 new template action with existing template',
          page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]').inner_text() == '+ Nueva plantilla'
          and page.locator('#modalConsentTemplateFlow [data-tpl-action="manage"]').is_visible())
    check('T07 archived excluded', page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla archivada').count() == 0)
    capture(page, 'B-selector-existing')

    def create_from_selector(name, title, procedure):
        page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]').click()
        expect(page.locator('#ci_template_editor_form')).to_be_visible()
        page.wait_for_function("document.activeElement?.id === 'ci_tpl_name'")
        check('create editor focus', page.evaluate("document.activeElement?.id === 'ci_tpl_name'"))
        page.locator('#ci_tpl_name').fill(name)
        page.locator('#ci_tpl_title').fill(title)
        page.locator('#ci_tpl_procedimiento').fill(procedure)
        if name == 'Mamoplastia': capture(page, 'D-create-from-selector')
        page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
        expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla guardada', exact=True)).to_be_visible()
        expect(page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text=name)).to_be_visible()

    create_from_selector('Mamoplastia', 'Consentimiento mamoplastia', 'Prótesis mamaria e incisión.')
    check('T02 second template immediately available', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE status='active'") == '2')
    create_from_selector('Blefaroplastia', 'Consentimiento blefaroplastia', 'Corrección palpebral.')
    check('T03 multiple personal templates', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE status='active'") == '3')
    capture(page, 'B-selector-multiple')
    search = page.locator('#ci_tpl_search')
    check('search accessible label', page.locator('label[for="ci_tpl_search"]').inner_text() == 'Buscar plantilla')
    search.fill('MAMOPLASTIA')
    check('T04 search name case insensitive', page.locator('#ci_tpl_selector_list .ci-template-row').count() == 1
          and 'Mamoplastia' in page.locator('#ci_tpl_selector_list').inner_text())
    search.fill('protesis')
    check('T05 search procedure accent insensitive', page.locator('#ci_tpl_selector_list .ci-template-row').count() == 1
          and 'Mamoplastia' in page.locator('#ci_tpl_selector_list').inner_text())
    capture(page, 'C-selector-search')
    search.fill('')
    create_action = page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]')
    create_action.focus()
    expect(create_action).to_be_focused()
    page.keyboard.press('Enter')
    expect(page.locator('#ci_template_editor_form')).to_be_visible()
    check('keyboard activates new template action', True)
    page.locator('#modalConsentTemplateFlow [data-tpl-action="editor-back"]').click()
    # Force distinct timestamps in the disposable DB, then reload selector.
    sql("UPDATE clinical_consent_templates SET updated_at='2026-10-06 10:00:00' WHERE template_name='Liposucción'")
    sql("UPDATE clinical_consent_templates SET updated_at='2026-10-06 11:00:00' WHERE template_name='Mamoplastia'")
    sql("UPDATE clinical_consent_templates SET updated_at='2026-10-06 12:00:00' WHERE template_name='Blefaroplastia'")
    page.locator('#modalConsentTemplateFlow [data-tpl-action="back"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#ci_tpl_selector_list .ci-template-row')).to_have_count(3)
    names = page.locator('#ci_tpl_selector_list .ci-template-row__copy strong').all_inner_texts()
    check('T06 updated ordering', names == ['Blefaroplastia', 'Mamoplastia', 'Liposucción'])
    check('all rows personal source', page.locator('#ci_tpl_selector_list [data-template-source="personal"]').count() == 3)
    page.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text='Liposucción').locator('[data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    check('T08 original template selected', page.locator('#ci_title').input_value() == CONTENT_A['title'])
    page.locator('#ci_next').click()
    page.locator('#ci_motivo').fill('Diagnóstico exclusivo del paciente QA')
    page.locator('#ci_title').fill('X' * 181)
    page.locator('#ci_procedimiento').fill('Edición individual del procedimiento para paciente QA.')
    page.locator('#ci_firmante_nombre').fill('Firmante privado QA')
    page.locator('#ci_enable_witnesses').check()
    page.locator('#ci_testigo_1_nombre').fill('Testigo privado QA')
    page.locator('#ci_confirm_informed').check()
    canvas = page.locator('#ci_signature_canvas')
    canvas.scroll_into_view_if_needed()
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 25, box['y'] + 25)
    page.mouse.down()
    page.mouse.move(box['x'] + 95, box['y'] + 55, steps=8)
    page.mouse.up()
    png = base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/lN8AAAAASUVORK5CYII=')
    page.locator('#ci_identity_files').set_input_files({'name':'identidad-qa.png','mimeType':'image/png','buffer':png})
    expect(page.locator('#ci_save_as_template')).to_be_visible()
    capture(page, 'E-active-consent', '#ci_save_as_template')
    snapshot = lambda: page.evaluate("""() => ({title:document.querySelector('#ci_title').value,
      procedimiento:document.querySelector('#ci_procedimiento').value,
      motivo:document.querySelector('#ci_motivo').value,
      signer:document.querySelector('#ci_firmante_nombre').value,
      witness:document.querySelector('#ci_testigo_1_nombre').value,
      accepted:document.querySelector('#ci_confirm_informed').checked,
      signature:document.querySelector('#ci_signature_canvas').toDataURL(),
      files:[...document.querySelector('#ci_identity_files').files].map(file=>file.name)})""")
    before = snapshot()
    documents_before = sql('SELECT COUNT(*) FROM clinical_documents')
    page.locator('#ci_save_as_template').click()
    expect(page.locator('#ci_save_template_overlay')).to_be_visible()
    page.wait_for_function("document.activeElement?.id === 'ci_save_template_name'")
    check('save dialog focus', page.evaluate("document.activeElement?.id === 'ci_save_template_name'"))
    page.keyboard.press('Tab')
    check('dialog Tab to cancel', page.evaluate("document.activeElement?.id === 'ci_save_template_cancel'"))
    page.keyboard.press('Tab')
    check('dialog Tab to save', page.evaluate("document.activeElement?.id === 'ci_save_template_confirm'"))
    page.keyboard.press('Tab')
    check('dialog focus cycles', page.evaluate("document.activeElement?.id === 'ci_save_template_name'"))
    capture(page, 'F-save-as-dialog')
    page.keyboard.press('Escape')
    expect(page.locator('#ci_save_template_overlay')).to_be_hidden()
    check('dialog Escape preserves underlying consent', page.locator('#modalConsentimientoInformado').is_visible()
          and snapshot() == before)
    page.locator('#ci_save_as_template').click()
    expect(page.locator('#ci_save_template_overlay')).to_be_visible()
    page.locator('#ci_save_template_name').fill('Nueva plantilla desde paciente')
    with page.expect_response(lambda response: response.request.method == 'POST' and '/consent-templates' in response.url) as rejected:
        page.locator('#ci_save_template_confirm').click()
    check('T18 validation failure', rejected.value.status == 400)
    expect(page.locator('#ci_save_template_error')).to_be_visible()
    check('T18 preserves form, signature, attachment and name', snapshot() == before
          and page.locator('#ci_save_template_name').input_value() == 'Nueva plantilla desde paciente'
          and sql('SELECT COUNT(*) FROM clinical_documents') == documents_before)
    page.locator('#ci_save_template_cancel').click()
    check('cancel returns focus', page.evaluate("document.activeElement?.id === 'ci_save_as_template'"))
    page.locator('#ci_title').fill('Consentimiento individual corregido')
    before = snapshot()
    page.locator('#ci_save_as_template').click()
    page.locator('#ci_save_template_name').fill('Nueva plantilla desde paciente')
    with page.expect_response(lambda response: response.request.method == 'POST' and '/consent-templates' in response.url) as accepted:
        page.locator('#ci_save_template_confirm').click()
    check('T09 save current consent', accepted.value.status == 201)
    expect(page.locator('#ci_save_template_status')).to_have_text('Plantilla guardada')
    capture(page, 'G-save-as-success')
    check('T17 current consent unchanged', snapshot() == before
          and page.locator('#modalConsentimientoInformado').is_visible()
          and sql('SELECT COUNT(*) FROM clinical_documents') == documents_before)
    saved_payload = writes[-1]
    keys = set(CONTENT_A)
    check('save-as exact whitelist', set(saved_payload) == {'template_name','content'}
          and set(saved_payload['content']) == keys)
    check('T10-T16 patient/signature/attachment/encounter/doctor data excluded',
          'Diagnóstico exclusivo del paciente QA' not in json.dumps(saved_payload, ensure_ascii=False)
          and 'Firmante privado QA' not in json.dumps(saved_payload, ensure_ascii=False)
          and 'Testigo privado QA' not in json.dumps(saved_payload, ensure_ascii=False)
          and 'identidad-qa.png' not in json.dumps(saved_payload, ensure_ascii=False)
          and 'p_plan02ux_review' not in json.dumps(saved_payload, ensure_ascii=False))
    new_uuid = sql("SELECT template_uuid FROM clinical_consent_templates WHERE template_name='Nueva plantilla desde paciente'")
    check('T20 new identity from template', new_uuid != source_uuid)
    check('T21 source template unchanged', request('GET',ROUTE+'/'+source_uuid)[1]['data']['content'] == CONTENT_A)
    saved_content = request('GET',ROUTE+'/'+new_uuid)[1]['data']['content']
    check('T12 motive not copied', 'motivo' not in saved_content)
    check('T13-T16 excluded keys absent', set(saved_content) == keys)
    # The save-as operation did not create a draft. The existing clinical action still does.
    page.locator('#ci_save').click()
    page.wait_for_function("() => !!document.querySelector('#ci_action_feedback')?.textContent?.trim()", timeout=20000)
    check('T22 template to draft', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'") == '1')
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_be_visible()
    page.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    check('T23 draft resume', page.locator('#ci_title').input_value() == before['title'])
    page.locator('#modalConsentimientoInformado .btn-close').click()
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_be_visible()
    page.locator('#ci_draft_discard_btn').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text='Nueva plantilla desde paciente')).to_be_visible()
    check('T19 new template available later', True)
    capture(page, 'H-later-template-list')
    page.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text='Nueva plantilla desde paciente').locator('[data-tpl-action="use"]').click()
    page.locator('#ci_next').click()
    page.locator('#ci_confirm_informed').check()
    page.locator('#ci_emit').click()
    page.wait_for_function("() => document.querySelector('#ci_action_feedback')?.textContent?.includes('Consentimiento emitido')", timeout=20000)
    check('T24 direct emit', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status<>'draft'") == '1')
    legacy_status, legacy_body = request('POST',BASE+'/api/clinical-documents.php?action=save',
                                         {'context':{'patient_id':'p_plan02ux_review'}})
    check('T25 M6 guard', legacy_status == 409 and legacy_body.get('error') == 'M6_LEGACY_WRITE_BLOCKED')
    check('no uncaught browser errors', errors == [])
    print('QA_ROWS=' + json.dumps({'templates':sql('SELECT COUNT(*) FROM clinical_consent_templates'),
         'documents':sql('SELECT COUNT(*) FROM clinical_documents')}, ensure_ascii=False), flush=True)
    print('SCREENSHOTS=' + str(OUT), flush=True)
    browser.close()
