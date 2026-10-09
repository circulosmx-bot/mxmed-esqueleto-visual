"""CONS-TPL04 browser, API, and copied-document proof on a disposable database."""
import json
import os
import re
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = Path(os.environ['FLOW_R1_QA_ROOT'])
OUT = Path(os.environ.get('CONS_TPL04_SCREENSHOTS', '/tmp/cons-tpl04-screenshots'))
OUT.mkdir(parents=True, exist_ok=True)
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
with (Path(__file__).resolve().parents[1] / 'db/migrations/2026_10_06_29_consent_templates.sql').open('rb') as migration:
    subprocess.run(['mysql', DB], stdin=migration, check=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', DB, '-e', query], text=True).strip()


def check(code, condition):
    assert condition, code
    print(f'{code}=PASS', flush=True)


def document_snapshot():
    return sql('SELECT document_uuid,SHA2(payload_json,256),SHA2(COALESCE(rendered_text,\'\'),256),status,version '
               'FROM clinical_documents ORDER BY id')


CONTENT_OLD = {'title': 'Procedimiento original QA', 'procedimiento': 'Técnica original QA',
               'template_key': 'procedimiento', 'objetivo': 'Objetivo original QA',
               'riesgos': 'Riesgos explicados QA', 'risk_comunes': 'Dolor QA',
               'risk_poco_frecuentes': 'Sangrado QA', 'risk_raros_graves': 'Complicación QA',
               'beneficios_esperados': 'Beneficio QA', 'alternativas': 'Alternativa QA',
               'consecuencias_no_aceptar': 'Consecuencia QA', 'autorizacion_contingencias': True}
CONTENT_NEW = {**CONTENT_OLD, 'title': 'Procedimiento actualizado QA',
               'procedimiento': 'Técnica nueva QA'}
ROUTE = BASE + '/api/clinical/index.php/doctors/1/consent-templates'

with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    anonymous = pw.request.new_context()
    subprocess.run(['php', '-d', f'session.save_path={ROOT / "sessions"}', '-r',
                    'session_id("cons-tpl04-other");session_start();$_SESSION["doctor_id"]="2";'
                    '$_SESSION["user_id"]="other-doctor";session_write_close();'], check=True)
    other = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=cons-tpl04-other'})

    def request(ctx, method, url, body=None):
        response = ctx.fetch(url, method=method,
                             data=json.dumps(body, ensure_ascii=False) if body is not None else None,
                             headers={'Content-Type': 'application/json'} if body is not None else {})
        return response.status, response.json()

    created_count = 0
    deleted_count = 0
    status, created = request(api, 'POST', ROUTE, {'template_name': 'Plantilla A QA', 'content': CONTENT_OLD})
    check('SEED_A', status == 201)
    created_count += 1
    uuid_a = created['data']['uuid']

    browser = pw.webkit.launch()
    page = browser.new_page(viewport={'width': 1440, 'height': 900},
                            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    native_dialogs = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('dialog', lambda dialog: (native_dialogs.append(dialog.type), dialog.dismiss()))

    def initialize_page():
        page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
        page.wait_for_function('typeof window.setActivePatientId === "function"')
        page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
        page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
        expect(page.locator('#p-expediente')).to_have_attribute('data-patient-id', 'p_plan02ux_review')
        page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
        page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
        page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()

    def capture(name):
        for width, height in [(1366, 768), (1440, 900)]:
            page.set_viewport_size({'width': width, 'height': height})
            page.screenshot(path=str(OUT / f'{name}-{width}x{height}.png'))
            check(f'{name}_{width}x{height}_FIT', page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1'))
        page.set_viewport_size({'width': 1440, 'height': 900})

    def managed_row(name):
        return page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has=page.get_by_text(name, exact=True))

    def open_flow(target='selector'):
        page.locator('[data-action="documents-open-consent"]').click()
        if int(sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'")):
            expect(page.locator('#modalConsentDraftPrompt')).to_be_visible()
            page.locator('#ci_draft_discard_btn').click()
        expect(page.locator('#modalConsentTemplateFlow')).to_be_visible()
        # The current UI has one library for using and managing personal templates.
        page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
        expect(page.locator('#ci_tpl_modal_title')).to_have_text('PLANTILLAS DE CONSENTIMIENTO')
        expected_rows = int(sql("SELECT COUNT(*) FROM clinical_consent_templates WHERE status='active'"))
        expect(page.locator('#modalConsentTemplateFlow .ci-template-row')).to_have_count(expected_rows)

    def open_more(row):
        row.locator('[data-tpl-action="more"]').click()
        expect(row.locator('[role="menu"]')).to_be_visible()

    def use_template(name):
        open_flow('selector')
        page.locator('#ci_tpl_selector_list .ci-template-row').filter(has_text=name).locator('[data-tpl-action="use"]').click()
        expect(page.locator('#modalConsentimientoInformado')).to_be_visible()

    def close_consent():
        page.locator('#modalConsentimientoInformado .btn-close').click()
        expect(page.locator('#modalConsentimientoInformado')).to_be_hidden()

    initialize_page()
    use_template('Plantilla A QA')
    check('T22_TEMPLATE_TO_DRAFT_COPY', page.locator('#ci_title').input_value() == CONTENT_OLD['title'])
    page.locator('#ci_next').click()
    page.locator('#ci_save').click()
    page.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Borrador guardado')")
    draft1_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' LIMIT 1")
    draft1_before = sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{draft1_uuid}'")
    check('DRAFT1_CREATED', CONTENT_OLD['procedimiento'] in draft1_before)

    use_template('Plantilla A QA')
    page.locator('#ci_next').click()
    page.locator('#ci_preview').click()
    expect(page.locator('#ci_review_panel')).to_be_visible()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()
    canvas = page.locator('#ci_signature_canvas')
    canvas.scroll_into_view_if_needed()
    box = canvas.bounding_box()
    page.mouse.move(box['x'] + 20, box['y'] + 20)
    page.mouse.down()
    page.mouse.move(box['x'] + 95, box['y'] + 50, steps=8)
    page.mouse.up()
    expect(page.locator('#ci_signature_status')).to_contain_text('vinculada')
    doctor_canvas = page.locator('#ci_doctor_signature_canvas')
    doctor_canvas.scroll_into_view_if_needed()
    doctor_box = doctor_canvas.bounding_box()
    page.mouse.move(doctor_box['x'] + 20, doctor_box['y'] + 20)
    page.mouse.down()
    page.mouse.move(doctor_box['x'] + 95, doctor_box['y'] + 50, steps=8)
    page.mouse.up()
    expect(page.locator('#ci_doctor_signature_status')).to_contain_text('vinculada')
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    expect(page.locator('#ci_review_signature_status')).to_contain_text('Paciente: Firma válida para esta versión')
    expect(page.locator('#ci_review_signature_status')).to_contain_text('Médico: Firma válida para esta versión')
    with page.expect_response(lambda response: response.request.method == 'POST'
                              and f'/patients/p_plan02ux_review/documents' in response.url) as emitted_response:
        page.locator('#ci_emit').click()
    check('T24_CONSENT_EMIT_HTTP', emitted_response.value.status in (200, 201))
    expect(page.locator('#modalDocumentPostEmission')).to_be_visible(timeout=20000)
    final_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='consentimiento_informado' AND status<>'draft' LIMIT 1")
    final_before = sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{final_uuid}'")
    check('T24_CONSENT_EMISSION', CONTENT_OLD['procedimiento'] in final_before and 'local_canvas' in final_before)
    page.locator('#modalDocumentPostEmission [data-post-emission="close"]').click()
    before_edit_documents = document_snapshot()

    open_flow('manage')
    row_a = managed_row('Plantilla A QA')
    capture('A-management')
    row_a.locator('[data-tpl-action="edit"]').click()
    page.locator('#ci_tpl_title').fill(CONTENT_NEW['title'])
    page.locator('#ci_tpl_procedimiento').fill(CONTENT_NEW['procedimiento'])
    page.locator('#modalConsentTemplateFlow [data-tpl-action="save"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla guardada', exact=True)).to_be_visible()
    check('T01_EDIT_TEMPLATE_PERSISTS', request(api, 'GET', ROUTE + '/' + uuid_a)[1]['data']['content'] == CONTENT_NEW)
    check('T03_EDIT_DOES_NOT_AFFECT_EXISTING_DRAFT', sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{draft1_uuid}'") == draft1_before)
    check('T04_EDIT_DOES_NOT_AFFECT_EXISTING_FINAL', sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{final_uuid}'") == final_before
          and document_snapshot() == before_edit_documents)
    page.locator('#modalConsentTemplateFlow .btn-close').click()

    use_template('Plantilla A QA')
    check('T02_EDIT_AFFECTS_FUTURE_USE', page.locator('#ci_title').input_value() == CONTENT_NEW['title']
          and page.locator('#ci_procedimiento').input_value() == CONTENT_NEW['procedimiento'])
    page.locator('#ci_next').click()
    page.locator('#ci_save').click()
    page.wait_for_function("document.querySelector('#ci_action_feedback')?.textContent?.includes('Borrador guardado')")
    check('DRAFT2_NEW_COPY', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft'") == '2'
          and CONTENT_NEW['procedimiento'] in sql("SELECT payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' AND status='draft' ORDER BY id DESC LIMIT 1"))
    before_delete_documents = document_snapshot()
    before_delete_document_count = int(sql('SELECT COUNT(*) FROM clinical_documents'))
    before_delete_uploads = sql('SELECT COUNT(*) FROM clinical_binary_uploads')
    check('DOCUMENTS_SAVED_BEFORE_DELETE', before_delete_document_count >= 3)

    status, created_b = request(api, 'POST', ROUTE, {'template_name': 'Plantilla B archivada QA', 'content': CONTENT_OLD})
    check('SEED_B', status == 201)
    created_count += 1
    uuid_b = created_b['data']['uuid']
    archived = request(api, 'POST', ROUTE + '/' + uuid_b + '/archive', {'expected_version': 1})
    check('T19_ARCHIVE_STILL_WORKS', archived[0] == 200 and archived[1]['data']['status'] == 'archived')
    duplicate = request(api, 'POST', ROUTE + '/' + uuid_a + '/duplicate', {})
    check('T20_DUPLICATE_STILL_WORKS', duplicate[0] == 201 and duplicate[1]['data']['content'] == CONTENT_NEW)
    created_count += 1

    open_flow('manage')
    row_a = managed_row('Plantilla A QA')
    open_more(row_a)
    check('T05_DELETE_ACTION_VISIBLE_IN_MANAGEMENT', row_a.locator('[data-tpl-action="delete"]').is_visible()
          and row_a.locator('[data-tpl-action="archive"]').is_visible()
          and row_a.locator('[data-tpl-action="edit"]').is_visible())
    check('ACCESS_DESTRUCTIVE_NAME', page.get_by_role('menuitem', name='Eliminar plantilla Plantilla A QA').count() == 1)
    capture('B-destructive-placement')
    row_a.locator('[data-tpl-action="delete"]').click()
    expect(page.locator('#ci_tpl_modal_title')).to_have_text('ELIMINAR PLANTILLA')
    check('T06_DELETE_DIALOG_SHOWS_TEMPLATE_NAME', page.locator('#ci_tpl_delete_name').inner_text() == '«Plantilla A QA»')
    check('T07_DELETE_BUTTON_DISABLED_WITHOUT_CONFIRMATION', page.locator('[data-tpl-action="delete-confirm"]').is_disabled())
    check('ACCESS_DIALOG_SEMANTICS', page.locator('#modalConsentTemplateFlow').get_attribute('aria-labelledby') == 'ci_tpl_modal_title'
          and page.locator('label[for="ci_tpl_delete_confirm"]').count() == 1
          and page.evaluate("document.activeElement?.id === 'ci_tpl_delete_confirm'"))
    page.keyboard.press('Tab')
    expect(page.locator('[data-tpl-action="delete-cancel"]')).to_be_focused()
    page.keyboard.press('Shift+Tab')
    expect(page.locator('#ci_tpl_delete_confirm')).to_be_focused()
    check('ACCESS_KEYBOARD_NAVIGATION', True)
    capture('C-delete-dialog-empty')
    for wrong in ['eliminar', 'Eliminar', 'ELIMINA', 'delete']:
        page.locator('#ci_tpl_delete_confirm').fill(wrong)
        check('T08_WRONG_CONFIRMATION_REJECTED_' + wrong, page.locator('[data-tpl-action="delete-confirm"]').is_disabled())
    page.locator('#ci_tpl_delete_confirm').fill(' ELIMINAR ')
    check('T09_EXACT_ELIMINAR_ENABLES_DELETE', page.locator('[data-tpl-action="delete-confirm"]').is_enabled())
    capture('D-delete-dialog-confirmed')
    page.keyboard.press('Escape')
    expect(page.locator('#ci_tpl_modal_title')).to_have_text('PLANTILLAS DE CONSENTIMIENTO')
    expect(managed_row('Plantilla A QA').locator('[data-tpl-action="more"]')).to_be_focused()
    check('ACCESS_ESCAPE_FOCUS_RETURN', page.evaluate("document.activeElement?.dataset.tplAction === 'more' && document.activeElement?.dataset.tplUuid"),)
    row_a = managed_row('Plantilla A QA')
    open_more(row_a)
    row_a.locator('[data-tpl-action="delete"]').click()
    page.locator('[data-tpl-action="delete-cancel"]').click()
    expect(page.locator('#ci_tpl_modal_title')).to_have_text('PLANTILLAS DE CONSENTIMIENTO')
    expect(managed_row('Plantilla A QA').locator('[data-tpl-action="more"]')).to_be_focused()
    check('ACCESS_CANCEL_FOCUS_RETURN', True)
    row_a = managed_row('Plantilla A QA')
    open_more(row_a)
    row_a.locator('[data-tpl-action="delete"]').click()
    page.locator('#ci_tpl_delete_confirm').fill('ELIMINAR')
    # Concurrent modification must cause a version conflict without removing the row.
    stale_update = request(api, 'PUT', ROUTE + '/' + uuid_a, {'template_name': 'Plantilla A QA',
        'content': CONTENT_NEW, 'expected_version': 2})
    check('CONCURRENT_UPDATE', stale_update[0] == 200 and stale_update[1]['data']['version'] == 3)
    page.locator('[data-tpl-action="delete-confirm"]').click()
    expect(page.locator('.ci-template-delete-error')).to_be_visible()
    check('DELETE_FAILURE_PRESERVES_TEMPLATE', request(api, 'GET', ROUTE + '/' + uuid_a)[0] == 200
          and page.locator('#ci_tpl_modal_title').inner_text() == 'ELIMINAR PLANTILLA')
    page.locator('[data-tpl-action="delete-cancel"]').click()
    expect(page.locator('#ci_tpl_modal_title')).to_have_text('PLANTILLAS DE CONSENTIMIENTO')
    row_a = managed_row('Plantilla A QA')
    open_more(row_a)
    row_a.locator('[data-tpl-action="delete"]').click()
    page.locator('#ci_tpl_delete_confirm').fill('ELIMINAR')
    page.locator('[data-tpl-action="delete-confirm"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla eliminada', exact=True)).to_be_visible()
    check('T10_DELETE_ACTIVE_TEMPLATE', request(api, 'GET', ROUTE + '/' + uuid_a)[0] == 404
          and sql(f"SELECT COUNT(*) FROM clinical_consent_templates WHERE template_uuid='{uuid_a}'") == '0')
    deleted_count += 1
    capture('E-delete-success')
    check('T15_DELETE_DOES_NOT_CHANGE_EXISTING_DRAFT', sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{draft1_uuid}'") == draft1_before)
    check('T16_DELETE_DOES_NOT_CHANGE_EXISTING_FINAL', sql(f"SELECT payload_json FROM clinical_documents WHERE document_uuid='{final_uuid}'") == final_before
          and document_snapshot() == before_delete_documents)
    check('T17_DELETE_DOES_NOT_CHANGE_SIGNATURES_OR_ATTACHMENTS', 'local_canvas' in final_before
          and document_snapshot() == before_delete_documents
          and sql('SELECT COUNT(*) FROM clinical_binary_uploads') == before_delete_uploads)
    check('CLINICAL_DOCUMENT_ROWS_CHANGED_BY_DELETE', int(sql('SELECT COUNT(*) FROM clinical_documents')) == before_delete_document_count)

    page.locator('#modalConsentTemplateFlow [data-tpl-action="archived-toggle"]').click()
    row_b = page.locator('#modalConsentTemplateFlow .ci-template-row').filter(has_text='Plantilla B archivada QA')
    expect(row_b).to_be_visible()
    open_more(row_b)
    check('ARCHIVED_DELETE_ACTION', row_b.locator('[data-tpl-action="delete"]').is_visible()
          and not row_b.locator('[data-tpl-action="archive"]').count())
    capture('G-archived-management')
    row_b.locator('[data-tpl-action="delete"]').click()
    page.locator('#ci_tpl_delete_confirm').fill('ELIMINAR')
    page.locator('[data-tpl-action="delete-confirm"]').click()
    expect(page.locator('#modalConsentTemplateFlow').get_by_text('Plantilla eliminada', exact=True)).to_be_visible()
    check('T11_DELETE_ARCHIVED_TEMPLATE', request(api, 'GET', ROUTE + '/' + uuid_b)[0] == 404)
    deleted_count += 1
    page.locator('#modalConsentTemplateFlow [data-tpl-action="back"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="selector"]').click()
    check('T12_DELETED_TEMPLATE_REMOVED_FROM_SELECTOR', page.locator('#ci_tpl_selector_list').get_by_text('Plantilla A QA', exact=True).count() == 0)
    page.locator('#ci_tpl_search').fill('Plantilla A QA')
    check('T13_DELETED_TEMPLATE_REMOVED_FROM_SEARCH', page.locator('#ci_tpl_selector_list').get_by_text('Plantilla A QA', exact=True).count() == 0)
    page.locator('#modalConsentTemplateFlow .btn-close').click()
    initialize_page()
    open_flow('selector')
    page.locator('#ci_tpl_search').fill('Plantilla A QA')
    check('T14_DELETED_TEMPLATE_ABSENT_AFTER_RELOAD', page.locator('#ci_tpl_selector_list').get_by_text('Plantilla A QA', exact=True).count() == 0)
    page.locator('#modalConsentTemplateFlow .btn-close').click()

    check('T18_CROSS_PHYSICIAN_DELETE_REJECTED', request(other, 'DELETE',
          BASE + '/api/clinical/index.php/doctors/2/consent-templates/' + duplicate[1]['data']['uuid'],
          {'expected_version': 1})[0] == 404
          and request(other, 'DELETE', ROUTE + '/' + duplicate[1]['data']['uuid'],
          {'expected_version': 1})[0] == 403
          and request(anonymous, 'DELETE', ROUTE + '/' + duplicate[1]['data']['uuid'],
          {'expected_version': 1})[0] == 401)
    check('DELETE_REQUIRES_VERSION', request(api, 'DELETE', ROUTE + '/' + duplicate[1]['data']['uuid'], {})[0] == 400)
    check('T21_EDIT_STILL_WORKS_AFTER_DELETE_FEATURE', request(api, 'PUT', ROUTE + '/' + duplicate[1]['data']['uuid'],
          {'template_name': 'Copia editada QA', 'content': CONTENT_NEW, 'expected_version': 1})[0] == 200)
    check('T25_M6_GUARD_REGRESSION', request(api, 'POST', BASE + '/api/clinical-documents.php?action=save',
          {'context': {'patient_id': 'p_plan02ux_review'}})[0] == 409)
    check('NO_BROWSER_ERRORS', errors == [])
    check('NO_NATIVE_CONFIRM_DIALOG', native_dialogs == [])
    check('NO_PATIENT_DOCUMENT_DELETE', int(sql('SELECT COUNT(*) FROM clinical_documents')) == before_delete_document_count)
    print(f'TEMPLATE_ROWS_CREATED_FOR_QA={created_count}', flush=True)
    print(f'TEMPLATE_ROWS_DELETED_FOR_QA={deleted_count}', flush=True)
    print('CLINICAL_DOCUMENT_ROWS_CHANGED_BY_DELETE=0', flush=True)
    print('PATIENT_DOCUMENTS_DELETED_BY_TEMPLATE_DELETE=0', flush=True)
    print('RESIDUAL_QA_FILE_COUNT_BEFORE_GATE_CLEANUP=' + str(sum(1 for path in (ROOT / 'private').rglob('*') if path.is_file())
          if (ROOT / 'private').exists() else 0), flush=True)
    print('SCREENSHOTS=' + str(OUT), flush=True)
    print('QA_DB=' + DB, flush=True)
    print('QA_ROOT=' + str(ROOT), flush=True)
    print('QA_PORT=' + BASE.rsplit(':',1)[-1], flush=True)
    browser.close()
