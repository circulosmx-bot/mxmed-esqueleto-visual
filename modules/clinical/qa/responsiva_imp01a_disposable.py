"""RESP-IMP01A browser and canonical draft QA. Run through consultation_flow_r1_disposable_gate.sh."""
import base64
import copy
import hashlib
import json
import os
import re
import subprocess
import uuid

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
PATIENT = 'p_plan02ux_review'


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)


def is_document_save_response(response):
    return (response.request.method == 'POST'
            and response.url.split('?', 1)[0].endswith(f'/patients/{PATIENT}/documents'))


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def saved(uuid_value):
    raw = sql("SELECT CONCAT_WS('\\t',status,version,payload_json) FROM clinical_documents "
              f"WHERE document_uuid='{uuid_value}'")
    status, version, payload = raw.split('\t', 2)
    return status, int(version), json.loads(payload)


def issue_attempt(api, request_body, doc_uuid, version):
    body = copy.deepcopy(request_body)
    body['payload']['status'] = 'issued'
    body['draft_ref'] = doc_uuid
    body['expected_version'] = version
    return api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                    data=body, headers={'Idempotency-Key': 'resp-issue-' + str(uuid.uuid4())})


def draw(page, selector):
    page.locator(selector).scroll_into_view_if_needed()
    page.wait_for_timeout(250)
    box = page.locator(selector).bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 30)
    page.mouse.down()
    page.mouse.move(box['x'] + 150, box['y'] + 75, steps=10)
    page.mouse.up()


with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    width, height = map(int, os.environ.get('RESP_QA_VIEWPORT', '1440x900').split('x'))
    page = browser.new_page(viewport={'width': width, 'height': height},
                            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    page.on('pageerror', lambda e: errors.append(str(e)))
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    baseline_encounter = sql('SELECT COUNT(*) FROM clinical_encounters WHERE encounter_id=1016 AND status="open"')
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-responsiva"]').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    check('T23_PREVIEW_ZERO_ROWS_BEFORE', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    patient_signer_name = page.locator('#rm_signer_name').input_value()
    page.locator('#rm_clinical_situation').fill('Situación clínica QA')
    page.locator('#rm_next').click()
    page.locator('#rm_additional_info summary').click()
    page.locator('#rm_indicated_conduct').fill('Conducta QA')
    page.locator('#rm_relevant_risk').fill('Riesgo QA')
    page.locator('#rm_declaration_text').fill('Declaración QA')
    page.locator('#rm_additional_manifestation').fill('Manifestación QA')
    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación clínica QA', timeout=15000)
    check('T23_PREVIEW_ZERO_ROWS_AFTER', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    check('T25_CONTENT_REVIEW', page.locator('#rm_content_review').get_by_text('Declaración de cierre').count() > 0)
    page.locator('#rm_prev').click()
    check('U01_PREVIEW_BACK_ZERO_ROWS', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación clínica QA', timeout=15000)
    with page.expect_response(is_document_save_response) as first_response:
        page.locator('#rm_next').click()
    first = first_response.value
    check('T01_CREATE_HTTP', first.status in (200, 201))
    body = first.request.post_data_json
    doc_uuid = first.json()['data']['document_id']
    status, version, payload = saved(doc_uuid)
    check('U02_U03_SIGNATURE_PHASE_CANONICAL_DRAFT', status == 'draft' and version == 1
          and bool(re.fullmatch(r'[0-9a-f-]{36}', doc_uuid)))
    check('T01_CANONICAL_DRAFT', status == payload['status'] == 'draft' and version == 1)
    check('T08_NO_SIGNATURES', payload['signature_binding_status'] == {'signer': 'absent', 'doctor': 'absent'})
    zero_issue = issue_attempt(api, body, doc_uuid, version)
    check('IMP01C_ZERO_SIGNATURES_BLOCKED', zero_issue.status == 400
          and zero_issue.json()['message'] == 'RESPONSIVA_SIGNER_SIGNATURE_REQUIRED'
          and saved(doc_uuid)[0] == 'draft')
    check('T05_CONTENT_STORED', payload['content']['clinical_situation'] == 'Situación clínica QA')
    date = payload['report']['emission_date']
    print('FIRST_UUID=' + doc_uuid, flush=True)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_final_signer_status')).to_contain_text('Falta la firma del paciente', timeout=15000)
    expect(page.locator('#rm_final_doctor_status')).to_contain_text('Falta la firma del médico')
    check('IMP01C_ZERO_FINAL_CTA_BLOCKED', page.locator('#rm_emit').is_disabled())
    page.locator('#rm_prev').click()
    page.locator('#rm_prev').click()
    with page.expect_response(is_document_save_response) as repeat_entry_response:
        page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    check('U05_REPEATED_SIGNATURE_ENTRY_SAME_UUID', repeat_entry_response.value.status in (200, 201)
          and repeat_entry_response.value.json()['data']['document_id'] == doc_uuid
          and sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '1')
    with page.expect_response(is_document_save_response) as first_close_response:
        page.locator('#rm_save').click()
    check('U04_U05_REENTER_NO_DUPLICATE', first_close_response.value.status in (200, 201)
          and first_close_response.value.json()['data']['document_id'] == doc_uuid
          and sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '1')
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    row = page.locator('#t-consent .vis06-row').filter(has_text='Responsiva médica')
    expect(row).to_be_visible(timeout=15000)
    check('T06_BORRADOR_VISIBLE', 'Borrador' in row.inner_text())
    resume = row.get_by_role('button', name='Continuar borrador')
    check('T07_CONTINUE_VISIBLE', resume.is_visible())
    resume.click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    check('T05_RESUME_CONTENT', page.locator('#rm_clinical_situation').input_value() == 'Situación clínica QA'
          and page.locator('#rm_indicated_conduct').input_value() == 'Conducta QA'
          and page.locator('#rm_relevant_risk').input_value() == 'Riesgo QA'
          and page.locator('#rm_declaration_text').input_value() == 'Declaración QA'
          and page.locator('#rm_additional_manifestation').input_value() == 'Manifestación QA'
          and page.locator('#rm_signer_name').input_value() == patient_signer_name
          and page.locator('#rm_date').input_value() == date)
    page.locator('#rm_clinical_situation').fill('Situación clínica QA segunda versión')
    with page.expect_response(is_document_save_response) as second_response:
        page.locator('#rm_save').click()
    second = second_response.value
    check('T02_SECOND_SAVE_HTTP', second.status in (200, 201))
    check('T02_SAME_UUID', second.json()['data']['document_id'] == doc_uuid)
    status, version, payload = saved(doc_uuid)
    check('T03_VERSION_ADVANCED', version == 4 and payload['content']['clinical_situation'] == 'Situación clínica QA segunda versión')
    stale = dict(body)
    stale['draft_ref'] = doc_uuid
    stale['expected_version'] = 1
    stale['payload']['content']['clinical_situation'] = 'Attempt stale write'
    conflict = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                        data=stale, headers={'Idempotency-Key': 'resp-stale-' + str(uuid.uuid4())})
    check('T04_STALE_VERSION_REJECTED', conflict.status == 409 and saved(doc_uuid)[1] == 4)
    row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(4):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('segunda versión', timeout=15000)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible()
    check('T15_REGISTERED_NOT_AUTO', page.locator('#rm_doctor_signature_status').inner_text() == 'Sin firma')
    box = page.locator('#rm_signer_signature_canvas').bounding_box()
    page.mouse.click(box['x'] + 30, box['y'] + 30)
    page.wait_for_timeout(200)
    check('T13_BLANK_CLICK_NOT_BOUND', page.locator('#rm_signer_signature_status').inner_text() == 'Sin firma')
    draw(page, '#rm_signer_signature_canvas')
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    check('T12_SIGNER_BIND_UI', True)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_final_doctor_status')).to_contain_text('Falta la firma del médico', timeout=15000)
    check('IMP01C_SIGNER_ONLY_FINAL_CTA_BLOCKED', page.locator('#rm_emit').is_disabled())
    page.locator('#rm_prev').click()
    with page.expect_response(is_document_save_response) as signer_response:
        page.locator('#rm_save').click()
    check('T09_SIGNER_ONLY_SAVE', signer_response.value.status in (200, 201))
    _, version, payload = saved(doc_uuid)
    check('T12_SIGNER_BOUND_SERVER', payload['signature_binding_status'] == {'signer': 'valid_bound_signature', 'doctor': 'absent'}
          and payload['signatures']['signer']['binding']['version'] == 2
          and payload['signatures']['signer']['binding']['document_uuid'] == doc_uuid)
    signer_issue = issue_attempt(api, signer_response.value.request.post_data_json, doc_uuid, version)
    check('IMP01C_SIGNER_ONLY_BLOCKED', signer_issue.status == 400
          and signer_issue.json()['message'] == 'RESPONSIVA_PHYSICIAN_SIGNATURE_REQUIRED'
          and saved(doc_uuid)[0] == 'draft')
    row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(5):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    check('T20_REHYDRATED_SIGNER', True)
    draw(page, '#rm_doctor_signature_canvas')
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    check('T14_DOCTOR_BIND_UI', True)
    with page.expect_response(is_document_save_response) as both_response:
        page.locator('#rm_save').click()
    check('T11_BOTH_SAVE', both_response.value.status in (200, 201))
    _, version, payload = saved(doc_uuid)
    check('T14_DOCTOR_BOUND_SERVER', payload['signature_binding_status'] == {'signer': 'valid_bound_signature', 'doctor': 'valid_bound_signature'})
    check('U07_U08_U10_LOCAL_UUID_BOUND', all(
        payload['signatures'][role]['binding']['version'] == 2
        and payload['signatures'][role]['binding']['document_type'] == 'responsiva_medica'
        and payload['signatures'][role]['binding']['document_uuid'] == doc_uuid
        for role in ('signer', 'doctor')))
    check('IMP01C_BOTH_SIGNATURES_SAME_FINGERPRINT',
          payload['signatures']['signer']['binding']['content_fingerprint']
          == payload['signatures']['doctor']['binding']['content_fingerprint'])
    both_body = both_response.value.request.post_data_json
    wrong_client_document = copy.deepcopy(both_body)
    wrong_client_document['draft_ref'] = str(uuid.uuid4())
    check('U12_CLIENT_WRONG_UUID_REJECTED', page.evaluate('''body =>
        window.mxmedResponsivaSignatureBinding.classify(body, 'signer', '1')''', wrong_client_document)
          == 'stale_or_unverified_signature')
    for check_name, mutate in [
        ('IMP01C_SIGNER_DIGEST_REJECTED', lambda b: b['payload']['signatures']['signer']['binding'].update(artifact_digest='0' * 64)),
        ('IMP01C_DOCTOR_DIGEST_REJECTED', lambda b: b['payload']['signatures']['doctor']['binding'].update(artifact_digest='0' * 64)),
        ('IMP01C_SIGNER_ROLE_CHANGE_REJECTED', lambda b: b['payload']['signer'].update(role='tutor', relationship='Madre')),
        ('IMP01C_SIGNER_IDENTITY_MISMATCH_REJECTED', lambda b: b['payload']['signatures']['signer'].update(signer_name='Otra persona')),
        ('IMP01C_DOCTOR_AUTHORITY_REJECTED', lambda b: b['payload']['signatures']['doctor']['binding'].update(authority='otro-medico')),
        ('IMP01C_HEADER_CHANGE_REJECTED', lambda b: b['payload']['presentation'].update(professional_header='hidden')),
        ('IMP01C_LEGACY_SIGNER_REJECTED', lambda b: b['payload']['signatures']['signer'].pop('binding')),
        ('IMP01C_LEGACY_DOCTOR_REJECTED', lambda b: b['payload']['signatures']['doctor'].pop('binding')),
        ('U12_SIGNER_WRONG_UUID_REJECTED', lambda b: b['payload']['signatures']['signer']['binding'].update(document_uuid=str(uuid.uuid4()))),
        ('U12_DOCTOR_WRONG_UUID_REJECTED', lambda b: b['payload']['signatures']['doctor']['binding'].update(document_uuid=str(uuid.uuid4()))),
        ('U13_STALE_FINGERPRINT_REJECTED', lambda b: b['payload']['signatures']['signer']['binding'].update(content_fingerprint='0' * 64)),
        ('U14_MISSING_UUID_REJECTED', lambda b: b['payload']['signatures']['signer']['binding'].pop('document_uuid')),
        ('U14_MALFORMED_UUID_REJECTED', lambda b: b['payload']['signatures']['doctor']['binding'].update(document_uuid='not-a-uuid')),
        ('U14_OLD_V1_LOCAL_REJECTED', lambda b: b['payload']['signatures']['signer']['binding'].update(version=1)),
    ]:
        attempt_body = copy.deepcopy(both_body)
        mutate(attempt_body)
        attempt = issue_attempt(api, attempt_body, doc_uuid, version)
        check(check_name, attempt.status == 400 and saved(doc_uuid)[0] == 'draft')
    row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    page.locator('#rm_clinical_situation').fill('Contenido alterado después de las firmas')
    check('T17_CONTENT_STALES_SIGNER', 'revisar' in page.locator('#rm_signer_signature_status').inner_text())
    check('T18_CONTENT_STALES_DOCTOR', 'revisar' in page.locator('#rm_doctor_signature_status').inner_text())
    with page.expect_response(is_document_save_response) as stale_signature_response:
        page.locator('#rm_save').click()
    check('T17_STALE_SAVE_ALLOWED', stale_signature_response.value.status in (200, 201))
    _, version, payload = saved(doc_uuid)
    check('T18_STALE_SERVER', payload['signature_binding_status'] == {'signer': 'stale_or_unverified_signature', 'doctor': 'stale_or_unverified_signature'})
    row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(5):
        page.locator('#rm_next').click()
    check('T21_STALE_REHYDRATED', 'revisar' in page.locator('#rm_signer_signature_status').inner_text())
    check('T20_DATE_FROZEN', saved(doc_uuid)[2]['report']['emission_date'] == date)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_final_signer_status')).to_contain_text('versión anterior', timeout=15000)
    expect(page.locator('#rm_final_doctor_status')).to_contain_text('aplicarse nuevamente')
    check('IMP01C_STALE_FINAL_CTA_BLOCKED', page.locator('#rm_emit').is_disabled())
    page.locator('#rm_prev').click()
    page.locator('#rm_signer_signature_clear').click()
    page.locator('#rm_doctor_signature_clear').click()
    draw(page, '#rm_signer_signature_canvas')
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    draw(page, '#rm_doctor_signature_canvas')
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_final_review')).to_contain_text('Contenido alterado después de las firmas', timeout=15000)
    final_html = page.locator('#rm_final_review').inner_html()
    if os.environ.get('RESP_QA_SCREENSHOT'):
        page.locator('#rm_final_signature_summary').scroll_into_view_if_needed()
        page.screenshot(path=os.environ['RESP_QA_SCREENSHOT'], full_page=False)
    check('T25_FINAL_REVIEW_COMPLETE', 'Declaración de cierre' in final_html
          and 'Firma del paciente o responsable' in final_html and 'Firma del médico tratante' in final_html
          and 'doc-base-footer-block' in final_html and final_html.count('data:image/png;base64,') == 2)
    check('IMP01C_FINAL_STATES_VISIBLE', 'vinculada a esta versión' in page.locator('#rm_final_signer_status').inner_text()
          and 'vinculada a esta versión' in page.locator('#rm_final_doctor_status').inner_text()
          and page.locator('#rm_emit').is_enabled())
    check('IMP01C_NO_STALE_REPLACEMENT_WARNING', page.locator('#rm_notice').is_hidden())
    with page.expect_response(is_document_save_response) as emit_response:
        page.locator('#rm_emit').click()
    check('T26_EMIT_HTTP', emit_response.value.status in (200, 201))
    check('T26_SAME_UUID_FINAL', emit_response.value.json()['data']['document_id'] == doc_uuid)
    status, version, payload = saved(doc_uuid)
    check('T26_DRAFT_TO_GENERATED', status == 'generated' and payload['status'] == 'issued')
    check('IMP01C_NO_DUPLICATE_FINAL',
          sql(f"SELECT COUNT(*) FROM clinical_documents WHERE document_uuid='{doc_uuid}'") == '1')
    final_fingerprint = emit_response.value.request.post_data_json['payload']['signatures']['signer']['binding']['content_fingerprint']
    check('IMP01C_FINAL_FINGERPRINTS_MATCH', final_fingerprint
          == payload['signatures']['signer']['binding']['content_fingerprint']
          == payload['signatures']['doctor']['binding']['content_fingerprint'])
    check('U25_U26_FINAL_UUID_MATCH', all(payload['signatures'][role]['binding']['document_uuid'] == doc_uuid
          for role in ('signer', 'doctor')))
    check('T24_SHARED_COMPOSITION', payload['responsiva_snapshot']['html'] == final_html)
    expect(row.get_by_role('button', name='Continuar borrador')).to_have_count(0, timeout=15000)
    if os.environ.get('RESP_QR_AVAILABLE') == '1':
        check('T29_RESPONSIVA_QR_ENABLED', page.locator('#rm_signer_signature_qr').is_enabled()
              and page.locator('#rm_doctor_signature_qr').is_enabled())
    else:
        check('T29_CONSENT_QR_DISABLED', page.locator('#rm_signer_signature_qr').is_disabled()
              and page.locator('#rm_doctor_signature_qr').is_disabled())
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    page.locator('#modalDocumentPostEmission.show').wait_for(state='visible')
    with page.expect_popup() as immediate_view:
        page.locator('#modalDocumentPostEmission [data-post-emission="view"]').click()
    check('IMP01C_IMMEDIATE_VIEW_UUID', f'uuid={doc_uuid}' in immediate_view.value.url)
    expect(immediate_view.value.locator('.responsiva-doc-sheet')).to_be_visible(timeout=15000)
    check('IMP01C_EMITTED_VIEW_BOTH_SIGNATURES',
          immediate_view.value.locator('.responsiva-doc-sheet img.informe-doc-sign-image').count() == 2)
    immediate_view.value.close()
    with page.expect_popup() as immediate_print:
        page.locator('#modalDocumentPostEmission [data-post-emission="print"]').click()
    check('IMP01C_IMMEDIATE_PRINT_UUID', f'uuid={doc_uuid}' in immediate_print.value.url
          and 'autoprint=1' in immediate_print.value.url)
    expect(immediate_print.value.locator('.responsiva-doc-sheet')).to_be_visible(timeout=15000)
    check('IMP01C_PRINT_BOTH_SIGNATURES',
          immediate_print.value.locator('.responsiva-doc-sheet img.informe-doc-sign-image').count() == 2)
    immediate_print.value.close()
    page.locator('#modalDocumentPostEmission [data-post-emission="close"]').click()
    page.locator('#modalDocumentPostEmission').wait_for(state='hidden')
    generated_row = page.locator(f'#ci_list [data-doc-uuid="{doc_uuid}"]')
    expect(generated_row).to_have_count(1, timeout=15000)
    with page.expect_popup() as list_view:
        generated_row.locator('[data-doc-action="view"]').evaluate('(button)=>button.click()')
    check('IMP01C_LIST_VIEW_UUID', f'uuid={doc_uuid}' in list_view.value.url)
    list_view.value.close()
    with page.expect_popup() as list_print:
        generated_row.locator('[data-doc-action="print"]').evaluate('(button)=>button.click()')
    check('IMP01C_LIST_PRINT_UUID', f'uuid={doc_uuid}' in list_print.value.url
          and 'autoprint=1' in list_print.value.url)
    list_print.value.close()
    page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-responsiva"]').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(4):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Responsiva médica', timeout=15000)
    page.locator('#rm_next').click()
    draw(page, '#rm_doctor_signature_canvas')
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_final_signer_status')).to_contain_text('Falta la firma del paciente', timeout=15000)
    check('IMP01C_DOCTOR_ONLY_FINAL_CTA_BLOCKED', page.locator('#rm_emit').is_disabled())
    page.locator('#rm_prev').click()
    with page.expect_response(is_document_save_response) as doctor_only_response:
        page.locator('#rm_save').click()
    check('T10_DOCTOR_ONLY_HTTP', doctor_only_response.value.status in (200, 201))
    second_uuid = doctor_only_response.value.json()['data']['document_id']
    check('T10_DOCTOR_ONLY', saved(second_uuid)[2]['signature_binding_status'] == {'signer': 'absent', 'doctor': 'valid_bound_signature'})
    doctor_only_status, doctor_only_version, _ = saved(second_uuid)
    doctor_issue = issue_attempt(api, doctor_only_response.value.request.post_data_json, second_uuid, doctor_only_version)
    check('IMP01C_DOCTOR_ONLY_BLOCKED', doctor_only_status == 'draft' and doctor_issue.status == 400
          and doctor_issue.json()['message'] == 'RESPONSIVA_SIGNER_SIGNATURE_REQUIRED')
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    signature = page.evaluate("""() => { const c=document.createElement('canvas');c.width=220;c.height=90;
      const x=c.getContext('2d');x.fillStyle='white';x.fillRect(0,0,220,90);x.strokeStyle='#0f172a';
      x.lineWidth=3;x.beginPath();x.moveTo(20,25);x.lineTo(170,65);x.stroke();
      window.__respQaRegisteredSignature=c.toDataURL('image/png');
      window.mxmedPhysicianSignature={read:()=>window.__respQaRegisteredSignature,refresh:async()=>{}};
      return window.__respQaRegisteredSignature; }""")
    digest = hashlib.sha256(base64.b64decode(signature.split(',')[1])).hexdigest()
    sql('CREATE TABLE IF NOT EXISTS physician_signatures (doctor_id VARCHAR(64) PRIMARY KEY, checksum_sha256 CHAR(64) NOT NULL)')
    sql(f"INSERT INTO physician_signatures VALUES ('1','{digest}')")
    draft_row = page.locator('#t-consent .vis06-row').filter(has_text='Borrador').first
    draft_row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(5):
        page.locator('#rm_next').click()
    check('T15_REGISTERED_STILL_NOT_AUTO', page.locator('#rm_doctor_signature_source_registered').is_checked() is False)
    page.locator('#rm_doctor_signature_source_registered').check()
    expect(page.locator('#rm_doctor_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    draw(page, '#rm_signer_signature_canvas')
    expect(page.locator('#rm_signer_signature_status')).to_contain_text('Firma vinculada a esta versión', timeout=15000)
    with page.expect_response(is_document_save_response) as registered_response:
        page.locator('#rm_save').click()
    check('T16_REGISTERED_SAVE_HTTP', registered_response.value.status in (200, 201))
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    _, _, registered_payload = saved(second_uuid)
    check('T16_REGISTERED_BOUND', registered_payload['signature_binding_status'] == {'signer': 'valid_bound_signature', 'doctor': 'valid_bound_signature'}
          and registered_payload['signatures']['doctor']['source'] == 'registered_profile'
          and registered_payload['signatures']['doctor']['binding']['artifact_digest'] == digest
          and registered_payload['signatures']['doctor']['binding']['version'] == 2
          and registered_payload['signatures']['doctor']['binding']['document_uuid'] == second_uuid)
    wrong_registered = copy.deepcopy(registered_response.value.request.post_data_json)
    wrong_registered['payload']['signatures']['doctor']['binding']['document_uuid'] = str(uuid.uuid4())
    wrong_registered_result = issue_attempt(api, wrong_registered, second_uuid, saved(second_uuid)[1])
    check('U12_REGISTERED_WRONG_UUID_REJECTED', wrong_registered_result.status == 400
          and wrong_registered_result.json()['message'] == 'RESPONSIVA_PHYSICIAN_SIGNATURE_INVALID_CURRENT_VERSION')
    draft_row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(6):
        page.locator('#rm_next').click()
    expect(page.locator('#rm_final_signer_status')).to_contain_text('vinculada a esta versión', timeout=15000)
    expect(page.locator('#rm_final_doctor_status')).to_contain_text('vinculada a esta versión', timeout=15000)
    expect(page.locator('#rm_emit')).to_be_enabled(timeout=15000)
    check('IMP01C_REGISTERED_DOCTOR_FRONTEND_ACCEPTED', True)
    for _ in range(4):
        page.locator('#rm_prev').click()
    page.locator('#rm_signer_role').select_option('tutor')
    page.locator('#rm_signer_name').fill('Otro firmante QA')
    page.locator('#rm_signer_relationship').fill('Madre')
    check('T19_IDENTITY_STALES_SIGNER', 'revisar' in page.locator('#rm_signer_signature_status').inner_text())
    with page.expect_response(is_document_save_response) as identity_response:
        page.locator('#rm_save').click()
    check('T19_IDENTITY_SAVE_HTTP', identity_response.value.status in (200, 201))
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
    check('T19_IDENTITY_STALE_SERVER', saved(second_uuid)[2]['signature_binding_status']['signer'] == 'stale_or_unverified_signature')
    sql("UPDATE clinical_documents SET payload_json=JSON_REMOVE(payload_json,'$.signatures.signer.binding') "
        f"WHERE document_uuid='{second_uuid}'")
    draft_row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    expect(page.locator('#rm_signer_name')).to_have_value('Otro firmante QA', timeout=15000)
    for _ in range(5):
        page.locator('#rm_next').click()
    check('T22_LEGACY_UNVERIFIED', 'revisar' in page.locator('#rm_signer_signature_status').inner_text())
    with page.expect_response(is_document_save_response) as legacy_binding_response:
        page.locator('#rm_save').click()
    check('T22_LEGACY_SAVE_HTTP', legacy_binding_response.value.status in (200, 201))
    check('T22_LEGACY_CLASSIFIED', saved(second_uuid)[2]['signature_binding_status']['signer'] == 'legacy_unverified_binding')
    remote = json.loads(json.dumps(body))
    remote['payload']['signatures'] = {'signer': {'source': 'remote_qr', 'image_data': 'invalid'}, 'doctor': None}
    remote_result = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                             data=remote, headers={'Idempotency-Key': 'resp-remote-' + str(uuid.uuid4())})
    check('T29_REMOTE_CONSENT_REUSE_REJECTED', remote_result.status == 400)
    legacy_uuid = str(uuid.uuid4())
    historical = {'status': 'draft', 'report': {'emission_date': '2026-10-01'},
                  'responsiva': {'type_label': 'Alta voluntaria'},
                  'patient_snapshot': {'full_name': 'Paciente histórico QA'},
                  'content': {'clinical_situation': 'Situación histórica QA',
                              'declaration_text': 'Declaración histórica QA'},
                  'signer': {'name': 'Paciente histórico QA', 'role': 'paciente'},
                  'signatures': {'signer': {'source': 'local_canvas', 'image_data': 'legacy-artifact'}}}
    historical_hex = json.dumps(historical, ensure_ascii=False, separators=(',', ':')).encode('utf-8').hex()
    sql("INSERT INTO clinical_documents (document_uuid,document_type,title,version,status,patient_id,care_setting,payload_json,summary,event_datetime,widget_group,printable,created_at,generated_at,created_by_user_id) VALUES "
        f"('{legacy_uuid}','responsiva_medica','Responsiva histórica QA',1,'generated','{PATIENT}','consulta',CAST(0x{historical_hex} AS CHAR CHARACTER SET utf8mb4),"
        "'Histórica','2026-10-01 12:00:00','documentos_clinicos',1,UTC_TIMESTAMP(),UTC_TIMESTAMP(),'review-user')")
    before_historical = sql(f"SELECT CONCAT_WS('|',status,version,SHA2(CAST(payload_json AS CHAR),256)) FROM clinical_documents WHERE document_uuid='{legacy_uuid}'")
    old = api.get(BASE + f'/api/clinical/index.php/doctors/1/documents/{legacy_uuid}')
    check('T27_HISTORICAL_READABLE', old.status == 200 and old.json()['data']['document']['status'] == 'generated'
          and old.json()['data']['document']['content']['payload']['status'] == 'draft')
    historical_view = api.get(BASE + f'/modules/clinical/ui/viewer.php?uuid={legacy_uuid}&embed=1&doctor_id=1')
    historical_print = api.get(BASE + f'/modules/clinical/ui/viewer.php?uuid={legacy_uuid}&embed=1&doctor_id=1&autoprint=1')
    check('IMP01C_HISTORICAL_VIEW_PRINT', historical_view.status == 200
          and historical_print.status == 200 and 'responsiva-doc-sheet' in historical_view.text())
    third = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                     data=body, headers={'Idempotency-Key': 'resp-third-' + str(uuid.uuid4())})
    check('T28_NEW_WRITE_HTTP', third.status in (200, 201))
    after_historical = sql(f"SELECT CONCAT_WS('|',status,version,SHA2(CAST(payload_json AS CHAR),256)) FROM clinical_documents WHERE document_uuid='{legacy_uuid}'")
    check('T28_HISTORICAL_NOT_REWRITTEN', before_historical == after_historical)
    page.locator('#t-consent .vis06-controls').get_by_role('button', name='Actualizar').click()
    historical_row = page.locator('#t-consent .vis06-row').filter(has_text='Responsiva histórica QA')
    expect(historical_row).to_be_visible(timeout=15000)
    check('T27_HISTORICAL_NOT_RESUMABLE', historical_row.get_by_role('button', name='Continuar borrador').count() == 0)
    duplicate_content = copy.deepcopy(both_body)
    duplicate_content.pop('draft_ref', None)
    duplicate_content.pop('expected_version', None)
    duplicate_content['payload']['status'] = 'draft'
    duplicate_content['payload']['signatures'] = {'signer': None, 'doctor': None}
    duplicate_response = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                                  data=duplicate_content,
                                  headers={'Idempotency-Key': 'resp-cross-doc-' + str(uuid.uuid4())})
    check('U29_SECOND_IDENTICAL_DRAFT_CREATED', duplicate_response.status in (200, 201))
    duplicate_uuid = duplicate_response.json()['data']['document_id']
    duplicate_version = saved(duplicate_uuid)[1]
    duplicate_content['payload'] = saved(duplicate_uuid)[2]
    duplicate_fingerprint = page.evaluate('body=>window.mxmedResponsivaSignatureBinding.hash(body)', duplicate_content)
    original_fingerprint = both_body['payload']['signatures']['signer']['binding']['content_fingerprint']
    check('U29_DISTINCT_UUID_IDENTICAL_FINGERPRINT', duplicate_uuid != doc_uuid
          and duplicate_fingerprint == original_fingerprint)
    duplicate_content['payload']['signatures'] = copy.deepcopy(both_body['payload']['signatures'])
    reused = issue_attempt(api, duplicate_content, duplicate_uuid, duplicate_version)
    check('U29_CROSS_DOCUMENT_SIGNATURE_REJECTED', reused.status == 400
          and reused.json()['message'] == 'RESPONSIVA_SIGNER_SIGNATURE_INVALID_CURRENT_VERSION'
          and saved(duplicate_uuid)[0] == 'draft')
    print('RESPONSIVA_A_UUID=' + doc_uuid, flush=True)
    print('RESPONSIVA_B_UUID=' + duplicate_uuid, flush=True)
    while page.locator('#t-consent .docvis-back').is_visible():
        page.locator('#t-consent .docvis-back').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-responsiva"]').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    page.locator('#rm_clinical_situation').fill('Prueba de persistencia fallida QA')
    page.locator('#rm_next').click()
    page.locator('#rm_declaration_text').fill('Declaración de prueba QA')
    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Prueba de persistencia fallida QA', timeout=15000)
    count_before_failure = sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'")
    page.route(f'**/patients/{PATIENT}/documents', lambda route: route.abort(), times=1)
    page.locator('#rm_next').click()
    expect(page.locator('#rm_notice')).to_contain_text('No se pudo guardar el borrador de responsiva', timeout=15000)
    check('U12_PERSISTENCE_FAILURE_BLOCKS_SIGNATURE_PHASE', page.locator('#rm_step_5').is_visible()
          and not page.locator('#rm_step_6').is_visible()
          and sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == count_before_failure)
    with page.expect_response(is_document_save_response) as recovered_response:
        page.locator('#rm_next').click()
    expect(page.locator('#rm_step_6')).to_be_visible(timeout=15000)
    recovered_uuid = recovered_response.value.json()['data']['document_id']
    check('U12_RECOVERY_MATERIALIZES_DRAFT', saved(recovered_uuid)[0] == 'draft')
    page.locator('#modalResponsivaMedica .btn-close').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden()
    check('U13_CANCEL_PRESERVES_DRAFT', saved(recovered_uuid)[0] == 'draft')
    active_encounter = api.get(BASE + f'/api/clinical/index.php/patients/{PATIENT}/encounters/active')
    check('T31_M6_ACTIVE_READ', active_encounter.status == 200 and active_encounter.json().get('ok') is True)
    check('T31_M6_ENCOUNTER_UNCHANGED', baseline_encounter == '1'
          and sql('SELECT COUNT(*) FROM clinical_encounters WHERE encounter_id=1016 AND status="open"') == '1')
    check('T31_RESPONSIVA_PATIENT_LEVEL', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica' AND encounter_ref_id IS NOT NULL") == '0')
    check('NO_PAGE_ERRORS', not errors)
    browser.close()
