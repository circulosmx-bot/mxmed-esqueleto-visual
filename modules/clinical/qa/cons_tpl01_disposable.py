"""CONS-TPL01 disposable authority and browser proof. Run through consultation_flow_r1_disposable_gate.sh."""
import json
import os
import re
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(os.environ['FLOW_R1_QA_ROOT'])
OUT = Path(os.environ.get('FLOW_R1_ARTIFACTS', '/tmp/cons-tpl01-screenshots'))
OUT.mkdir(parents=True, exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
MIGRATION = Path(__file__).resolve().parents[1] / 'db/migrations/2026_10_06_29_consent_templates.sql'
for _ in range(2):
    subprocess.run(['mysql', DB], stdin=MIGRATION.open('rb'), check=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', DB, '-e', query], text=True).strip()


def check(name, value):
    assert value, name
    print('PASS ' + name, flush=True)


check('migration twice', sql("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='clinical_consent_templates'") == '1')
check('isolated schema', all(token not in sql('SHOW COLUMNS FROM clinical_consent_templates').lower()
    for token in ['patient_id', 'encounter_id', 'appointment_id', 'signature', 'attachment', 'lineage']))

subprocess.run(['php', '-d', f'session.save_path={ROOT / "sessions"}', '-r',
                'session_id("cons-tpl-other");session_start();$_SESSION["doctor_id"]="2";$_SESSION["user_id"]="other-doctor";session_write_close();'], check=True)
ROUTE = f'{BASE}/api/clinical/index.php/doctors/1/consent-templates'
OTHER = f'{BASE}/api/clinical/index.php/doctors/2/consent-templates'
CONTENT = {
    'title': 'Biopsia de piel', 'procedimiento': 'Descripción reusable de biopsia.',
    'template_key': 'procedimiento', 'objetivo': 'Obtener muestra diagnóstica.',
    'riesgos': 'Riesgos descritos por el médico.', 'risk_comunes': 'Dolor local.',
    'risk_poco_frecuentes': 'Sangrado.', 'risk_raros_graves': 'Complicación grave.',
    'beneficios_esperados': 'Diagnóstico.', 'alternativas': 'Observación.',
    'consecuencias_no_aceptar': 'Demora diagnóstica.', 'autorizacion_contingencias': True,
}

with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    other = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=cons-tpl-other'})
    anonymous = pw.request.new_context()

    def request(ctx, method, url, body=None):
        response = ctx.fetch(url, method=method, data=json.dumps(body) if body is not None else None,
                             headers={'Content-Type': 'application/json'} if body is not None else {})
        return response.status, response.json()

    check('anonymous denied', request(anonymous, 'GET', ROUTE)[0] == 401)
    check('cross-route denied', request(other, 'GET', ROUTE)[0] == 403)
    empty_status, empty_response = request(api, 'GET', ROUTE)
    check('empty selector', empty_status == 200 and empty_response['data'] == [])
    bad = request(api, 'POST', ROUTE, {'template_name': 'Bad', 'content': {**CONTENT, 'patient_id': 'leak'}})
    check('patient field rejected', bad[0] == 400)
    status, created = request(api, 'POST', ROUTE, {'template_name': 'Biopsia', 'content': CONTENT})
    check('create', status == 201 and created['data']['status'] == 'active')
    uuid = created['data']['uuid']
    check('owner list', len(request(api, 'GET', ROUTE)[1]['data']) == 1)
    check('other doctor isolation', request(other, 'GET', OTHER)[1]['data'] == []
          and request(other, 'GET', OTHER + '/' + uuid)[0] == 404
          and request(other, 'PUT', OTHER + '/' + uuid,
                      {'template_name': 'Hack', 'content': CONTENT, 'expected_version': 1})[0] == 404
          and request(other, 'POST', OTHER + '/' + uuid + '/duplicate', {})[0] == 404)
    check('UUID alone denied', request(anonymous, 'GET', ROUTE + '/' + uuid)[0] == 401)
    check('read', request(api, 'GET', ROUTE + '/' + uuid)[1]['data']['content'] == CONTENT)
    check('no clinical document from template', sql('SELECT COUNT(*) FROM clinical_documents') == '0')

    browser = pw.webkit.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 900}, extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id', 'p_plan02ux_review', timeout=25000)
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    def capture(state):
        for width, height in [(1440,900),(1366,768)]:
            page.set_viewport_size({'width':width,'height':height})
            page.screenshot(path=str(OUT / f'{state}-{width}x{height}.png'))
            check(f'{state} horizontal fit {width}x{height}', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        page.set_viewport_size({'width':1440,'height':900})

    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_have_class(re.compile(r'\bshow\b'))
    check('new consent choice before form', page.locator('#ci_tpl_modal_title').inner_text() == 'Nuevo consentimiento')
    capture('choice')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').focus()
    check('selector keyboard focus', page.evaluate("document.activeElement?.dataset.tplAction === 'selector'"))
    page.keyboard.press('Enter')
    expect(page.locator('#modalConsentTemplateFlow [data-tpl-action="use"]')).to_have_count(1)
    check('active template selector', page.locator('#modalConsentTemplateFlow').get_by_text('Biopsia', exact=True).count() == 1)
    capture('selector')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_have_class(re.compile(r'\bshow\b'))
    check('template content copied into form', page.locator('#ci_title').input_value() == CONTENT['title']
          and page.locator('#ci_procedimiento').input_value() == CONTENT['procedimiento'])
    check('patient-specific input excluded', page.locator('#ci_motivo').input_value() == ''
          and not page.locator('#ci_confirm_informed').is_checked()
          and page.locator('#ci_identity_files_list').locator('li').count() == 0)
    capture('copied-form')
    page.locator('#ci_next').click()
    page.locator('#ci_save').click()
    page.wait_for_function("() => !!document.querySelector('#ci_action_feedback')?.textContent?.trim()", timeout=20000)
    check('template creates canonical draft', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'") == '1')
    draft_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1")
    draft_payload = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + draft_uuid + "'"))
    check('draft copy, no live template', draft_payload['form_snapshot']['title'] == CONTENT['title']
          and 'source_template_id' not in draft_payload)
    check('no signature or attachment copied', not draft_payload.get('signer_identity_attachments')
          and not draft_payload['signatures'].get('patient'))
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_have_class(re.compile(r'\bshow\b'))
    check('draft priority before template', not page.locator('#modalConsentTemplateFlow').is_visible())
    capture('draft-priority')
    page.locator('#ci_draft_discard_btn').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_have_class(re.compile(r'\bshow\b'))
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_have_class(re.compile(r'\bshow\b'))
    page.locator('#ci_next').click()
    page.locator('#ci_confirm_informed').check()
    page.locator('#ci_emit').click()
    page.wait_for_function("() => !!document.querySelector('#ci_action_feedback')?.textContent?.trim()", timeout=20000)
    check('template direct emit canonical', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status<>'draft'") == '1')
    final_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status<>'draft' LIMIT 1")
    final_payload = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + final_uuid + "'"))
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_have_class(re.compile(r'\bshow\b'))
    page.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    expect(page.locator('#modalConsentimientoInformado')).to_have_class(re.compile(r'\bshow\b'))
    check('resume draft content preserved', page.locator('#ci_title').input_value() == CONTENT['title']
          and page.locator('#ci_risk_common').input_value() == CONTENT['risk_comunes'])
    page.locator('#ci_procedimiento').fill('Edited after resume')
    page.locator('#ci_save').click()
    page.wait_for_function("() => !!document.querySelector('#ci_action_feedback')?.textContent?.trim()", timeout=20000)
    check('resume retains canonical draft UUID', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'") == '1'
          and sql("SELECT version FROM clinical_documents WHERE document_uuid='" + draft_uuid + "'") == '2')
    draft_payload = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + draft_uuid + "'"))
    print('UI_READY', flush=True)
    page.screenshot(path=str(OUT / 'initial-1440x900.png'))
    check('browser no JS errors', not errors)

    updated = dict(CONTENT, title='Biopsia actualizada')
    status, edit = request(api, 'PUT', ROUTE + '/' + uuid,
                           {'template_name': 'Biopsia revisada', 'content': updated, 'expected_version': 1})
    check('edit versioned', status == 200 and edit['data']['version'] == 2)
    check('stale edit conflict', request(api, 'PUT', ROUTE + '/' + uuid,
          {'template_name': 'Stale', 'content': CONTENT, 'expected_version': 1})[0] == 409)
    status, duplicate = request(api, 'POST', ROUTE + '/' + uuid + '/duplicate', {})
    copy_uuid = duplicate['data']['uuid']
    check('duplicate independent', status == 201 and copy_uuid != uuid
          and duplicate['data']['content'] == updated)
    check('deterministic list', [item['template_name'] for item in request(api, 'GET', ROUTE)[1]['data']]
          == ['Biopsia revisada', 'Copia de Biopsia revisada'])
    second_content = dict(updated, title='Segundo procedimiento')
    check('independent duplicate edit', request(api, 'PUT', ROUTE + '/' + copy_uuid,
          {'template_name':'Copia de Biopsia revisada','content':second_content,'expected_version':1})[0] == 200
          and request(api, 'GET', ROUTE + '/' + uuid)[1]['data']['content']['title'] == updated['title'])
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_have_class(re.compile(r'\bshow\b'))
    page.locator('#ci_draft_discard_btn').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_have_class(re.compile(r'\bshow\b'))
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#modalConsentTemplateFlow [data-tpl-action="use"]')).to_have_count(2)
    page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='Copia de Biopsia revisada').locator('[data-tpl-action="use"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_have_class(re.compile(r'\bshow\b'))
    check('multiple templates load correct content', page.locator('#ci_title').input_value() == second_content['title'])
    page.locator('#modalConsentimientoInformado .btn-close').click()
    status, archived = request(api, 'POST', ROUTE + '/' + uuid + '/archive', {'expected_version': 2})
    check('archive', status == 200 and archived['data']['status'] == 'archived'
          and len(request(api, 'GET', ROUTE)[1]['data']) == 1
          and len(request(api, 'GET', ROUTE + '?include_archived=1')[1]['data']) == 2)
    check('archived cannot edit/use via active list', request(api, 'PUT', ROUTE + '/' + uuid,
          {'template_name': 'No', 'content': CONTENT, 'expected_version': 3})[0] == 409)
    check('draft unchanged after edit/archive', json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + draft_uuid + "'")) == draft_payload)
    check('final unchanged after edit/archive', json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_uuid='" + final_uuid + "'")) == final_payload)
    check('still no patient link', sql('SELECT COUNT(*) FROM clinical_consent_templates') == '2'
          and sql('SELECT COUNT(*) FROM clinical_documents') == '2')

    def open_flow():
        page.locator('[data-action="documents-open-consent"]').click()
        expect(page.locator('#modalConsentDraftPrompt')).to_have_class(re.compile(r'\bshow\b'))
        page.locator('#ci_draft_discard_btn').click()
        expect(page.locator('#modalConsentTemplateFlow')).to_have_class(re.compile(r'\bshow\b'))

    open_flow()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="manage"]').click()
    expect(page.locator('#modalConsentTemplateFlow .ci-template-row')).to_have_count(2)
    check('management shows archived and active', page.locator('#modalConsentTemplateFlow .ci-template-row').count() == 2
          and page.locator('#modalConsentTemplateFlow').get_by_text('Archivada', exact=True).count() == 1)
    capture('manage')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]').click()
    expect(page.locator('#ci_template_editor_form')).to_be_visible()
    check('editor labels accessible', page.locator('label[for="ci_tpl_name"]').count() == 1
          and page.locator('label[for="ci_tpl_title"]').count() == 1
          and page.locator('label[for="ci_tpl_autorizacion_contingencias"]').count() == 1)
    capture('create')
    page.locator('#ci_tpl_name').fill('UI plantilla')
    page.locator('#ci_tpl_title').fill('Procedimiento UI')
    page.locator('#ci_tpl_procedimiento').fill('Descripción reutilizable UI')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('UI plantilla', exact=True)).to_be_visible()
    check('UI create template', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE template_name='UI plantilla'") == '1')
    ui_row = page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='UI plantilla')
    ui_row.locator('[data-tpl-action="edit"]').click()
    expect(page.locator('#ci_template_editor_form')).to_be_visible()
    capture('edit')
    page.locator('#ci_tpl_name').fill('UI plantilla editada')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('UI plantilla editada', exact=True)).to_be_visible()
    check('UI edit template', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE template_name='UI plantilla editada' AND version=2") == '1')
    ui_row = page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='UI plantilla editada')
    ui_row.locator('[data-tpl-action="duplicate"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Copia de UI plantilla editada', exact=True)).to_be_visible()
    check('UI duplicate template', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE template_name='Copia de UI plantilla editada'") == '1')
    copy_row = page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='Copia de UI plantilla editada')
    copy_row.locator('[data-tpl-action="archive"]').click()
    expect(page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='Copia de UI plantilla editada').get_by_text('Archivada')).to_be_visible()
    check('UI archive template', sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE template_name='Copia de UI plantilla editada' AND status='archived'") == '1')
    capture('archive')
    page.locator('#modalConsentTemplateFlow .btn-close').click()

    # Selector zero state keeps creation optional and the blank path available.
    sql("UPDATE clinical_consent_templates SET status='archived' WHERE status='active'")
    open_flow()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_contain_text('No tienes plantillas de consentimiento guardadas.')
    check('zero state actions', page.locator('#modalConsentTemplateFlow [data-tpl-action="create"]').is_visible()
          and page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').is_visible())
    capture('zero')
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    expect(page.locator('#modalConsentimientoInformado')).to_have_class(re.compile(r'\bshow\b'))
    check('blank path after zero state', page.locator('#ci_title').input_value() == '')
    page.locator('#modalConsentimientoInformado .btn-close').click()
    check('UI no page errors', not errors)
    template_count = sql('SELECT COUNT(*) FROM clinical_consent_templates')
    document_count = sql('SELECT COUNT(*) FROM clinical_documents')
    check('QA rows expected', template_count == '4' and document_count == '2')
    legacy_status, legacy_body = request(api, 'POST', BASE + '/api/clinical-documents.php?action=save',
                                         {'context': {'patient_id':'p_plan02ux_review'}})
    check('M6 legacy writer remains blocked', legacy_status == 409
          and legacy_body.get('error') == 'M6_LEGACY_WRITE_BLOCKED'
          and sql('SELECT COUNT(*) FROM clinical_documents') == document_count)
    subprocess.check_call(['mysql', DB, '-e', 'DROP TABLE clinical_consent_templates'])
    subprocess.run(['mysql', DB], stdin=MIGRATION.open('rb'), check=True)
    check('rollback and reapply in disposable DB', sql('SELECT COUNT(*) FROM clinical_consent_templates') == '0'
          and sql('SELECT COUNT(*) FROM clinical_documents') == document_count)
    browser.close()

print('TEMPLATE_ROWS_CREATED_FOR_QA=4')
print('CLINICAL_DOCUMENT_ROWS_CREATED_FOR_QA=2')
print('SCREENSHOTS=' + str(OUT))
