"""RESP-IMP01A browser and canonical draft QA. Run through consultation_flow_r1_disposable_gate.sh."""
import base64
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


def draw(page, selector):
    page.locator(selector).scroll_into_view_if_needed()
    box = page.locator(selector).bounding_box()
    page.mouse.move(box['x'] + 30, box['y'] + 30)
    page.mouse.down()
    page.mouse.move(box['x'] + 150, box['y'] + 75, steps=10)
    page.mouse.up()


with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    page = browser.new_page(viewport={'width': 1440, 'height': 900},
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
    page.locator('#rm_next').click()
    page.locator('#rm_clinical_situation').fill('Situación clínica QA')
    page.locator('#rm_indicated_conduct').fill('Conducta QA')
    page.locator('#rm_relevant_risk').fill('Riesgo QA')
    page.locator('#rm_next').click()
    page.locator('#rm_declaration_text').fill('Declaración QA')
    page.locator('#rm_additional_manifestation').fill('Manifestación QA')
    page.locator('#rm_next').click()
    page.locator('#rm_signer_name').fill('Paciente QA')
    page.locator('#rm_next').click()
    expect(page.locator('#rm_content_review')).to_contain_text('Situación clínica QA', timeout=15000)
    check('T23_PREVIEW_ZERO_ROWS_AFTER', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica'") == '0')
    check('T25_CONTENT_REVIEW', page.locator('#rm_content_review').get_by_text('Declaración de cierre').count() > 0)
    with page.expect_response(is_document_save_response) as first_response:
        page.locator('#rm_save').click()
    first = first_response.value
    check('T01_CREATE_HTTP', first.status in (200, 201))
    body = first.request.post_data_json
    first_json = first.json()
    doc_uuid = first_json['data']['document_id']
    status, version, payload = saved(doc_uuid)
    check('T01_CANONICAL_DRAFT', status == payload['status'] == 'draft' and version == 1)
    check('T08_NO_SIGNATURES', payload['signature_binding_status'] == {'signer': 'absent', 'doctor': 'absent'})
    check('T05_CONTENT_STORED', payload['content']['clinical_situation'] == 'Situación clínica QA')
    date = payload['report']['emission_date']
    print('FIRST_UUID=' + doc_uuid, flush=True)
    page.locator('#t-consent .docvis-back').click()
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
          and page.locator('#rm_signer_name').input_value() == 'Paciente QA'
          and page.locator('#rm_date').input_value() == date)
    page.locator('#rm_next').click()
    page.locator('#rm_clinical_situation').fill('Situación clínica QA segunda versión')
    with page.expect_response(is_document_save_response) as second_response:
        page.locator('#rm_save').click()
    second = second_response.value
    check('T02_SECOND_SAVE_HTTP', second.status in (200, 201))
    check('T02_SAME_UUID', second.json()['data']['document_id'] == doc_uuid)
    status, version, payload = saved(doc_uuid)
    check('T03_VERSION_ADVANCED', version == 2 and payload['content']['clinical_situation'] == 'Situación clínica QA segunda versión')
    stale = dict(body)
    stale['draft_ref'] = doc_uuid
    stale['expected_version'] = 1
    stale['payload']['content']['clinical_situation'] = 'Attempt stale write'
    conflict = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                        data=stale, headers={'Idempotency-Key': 'resp-stale-' + str(uuid.uuid4())})
    check('T04_STALE_VERSION_REJECTED', conflict.status == 409 and saved(doc_uuid)[1] == 2)
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
    with page.expect_response(is_document_save_response) as signer_response:
        page.locator('#rm_save').click()
    check('T09_SIGNER_ONLY_SAVE', signer_response.value.status in (200, 201))
    _, version, payload = saved(doc_uuid)
    check('T12_SIGNER_BOUND_SERVER', payload['signature_binding_status'] == {'signer': 'valid_bound_signature', 'doctor': 'absent'}
          and payload['signatures']['signer']['binding']['version'] == 1)
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
    row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    page.locator('#rm_next').click()
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
        page.screenshot(path=os.environ['RESP_QA_SCREENSHOT'], full_page=True)
    check('T25_FINAL_REVIEW_COMPLETE', 'Declaración de cierre' in final_html
          and 'Firma del paciente o responsable' in final_html and 'Firma del médico tratante' in final_html
          and 'doc-base-footer-block' in final_html and final_html.count('data:image/png;base64,') == 2)
    with page.expect_response(is_document_save_response) as emit_response:
        page.locator('#rm_emit').click()
    check('T26_EMIT_HTTP', emit_response.value.status in (200, 201))
    check('T26_SAME_UUID_FINAL', emit_response.value.json()['data']['document_id'] == doc_uuid)
    status, version, payload = saved(doc_uuid)
    check('T26_DRAFT_TO_GENERATED', status == 'generated' and payload['status'] == 'issued')
    check('T24_SHARED_COMPOSITION', payload['responsiva_snapshot']['html'] == final_html)
    expect(row.get_by_role('button', name='Continuar borrador')).to_have_count(0, timeout=15000)
    if os.environ.get('RESP_QR_AVAILABLE') == '1':
        check('T29_RESPONSIVA_QR_ENABLED', page.locator('#rm_signer_signature_qr').is_enabled()
              and page.locator('#rm_doctor_signature_qr').is_enabled())
    else:
        check('T29_CONSENT_QR_DISABLED', page.locator('#rm_signer_signature_qr').is_disabled()
              and page.locator('#rm_doctor_signature_qr').is_disabled())
    expect(page.locator('#modalResponsivaMedica')).to_be_hidden(timeout=15000)
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
    with page.expect_response(is_document_save_response) as doctor_only_response:
        page.locator('#rm_save').click()
    check('T10_DOCTOR_ONLY_HTTP', doctor_only_response.value.status in (200, 201))
    second_uuid = doctor_only_response.value.json()['data']['document_id']
    check('T10_DOCTOR_ONLY', saved(second_uuid)[2]['signature_binding_status'] == {'signer': 'absent', 'doctor': 'valid_bound_signature'})
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
    check('T16_REGISTERED_BOUND', registered_payload['signature_binding_status']['doctor'] == 'valid_bound_signature'
          and registered_payload['signatures']['doctor']['source'] == 'registered_profile'
          and registered_payload['signatures']['doctor']['binding']['artifact_digest'] == digest)
    draft_row.get_by_role('button', name='Continuar borrador').click()
    expect(page.locator('#modalResponsivaMedica')).to_be_visible()
    for _ in range(3):
        page.locator('#rm_next').click()
    page.locator('#rm_signer_name').fill('Otro firmante QA')
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
    third = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                     data=body, headers={'Idempotency-Key': 'resp-third-' + str(uuid.uuid4())})
    check('T28_NEW_WRITE_HTTP', third.status in (200, 201))
    after_historical = sql(f"SELECT CONCAT_WS('|',status,version,SHA2(CAST(payload_json AS CHAR),256)) FROM clinical_documents WHERE document_uuid='{legacy_uuid}'")
    check('T28_HISTORICAL_NOT_REWRITTEN', before_historical == after_historical)
    page.locator('#t-consent .vis06-controls').get_by_role('button', name='Actualizar').click()
    historical_row = page.locator('#t-consent .vis06-row').filter(has_text='Responsiva histórica QA')
    expect(historical_row).to_be_visible(timeout=15000)
    check('T27_HISTORICAL_NOT_RESUMABLE', historical_row.get_by_role('button', name='Continuar borrador').count() == 0)
    active_encounter = api.get(BASE + f'/api/clinical/index.php/patients/{PATIENT}/encounters/active')
    check('T31_M6_ACTIVE_READ', active_encounter.status == 200 and active_encounter.json().get('ok') is True)
    check('T31_M6_ENCOUNTER_UNCHANGED', baseline_encounter == '1'
          and sql('SELECT COUNT(*) FROM clinical_encounters WHERE encounter_id=1016 AND status="open"') == '1')
    check('T31_RESPONSIVA_PATIENT_LEVEL', sql("SELECT COUNT(*) FROM clinical_documents WHERE document_type='responsiva_medica' AND encounter_ref_id IS NOT NULL") == '0')
    check('NO_PAGE_ERRORS', not errors)
    browser.close()
