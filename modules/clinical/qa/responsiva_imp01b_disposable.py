"""RESP-IMP01B disposable browser/HTTP proof; run with consultation_flow_r1_disposable_gate.sh."""
import base64
import hashlib
import json
import os
import re
import subprocess
import uuid
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
ROOT = os.path.abspath(os.path.join(os.path.dirname(__file__), '../../..'))
PATIENT = 'p_plan02ux_review'
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)


def check(name, value):
    if not value:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def row(doc_uuid):
    value = sql(f"SELECT CONCAT_WS('\t',status,version,payload_json) FROM clinical_documents WHERE document_uuid='{doc_uuid}'")
    status, version, payload = value.split('\t', 2)
    return status, int(version), json.loads(payload)


def qrrow(token):
    return json.loads(sql(f"SELECT JSON_OBJECT('uuid',document_uuid,'version',document_version,'role',role,"
                          f"'authority',signer_authority,'fingerprint',content_fingerprint,'status',status,"
                          f"'review_hash',review_html_sha256,'artifact',artifact_digest) "
                          f"FROM clinical_responsiva_qr_sessions WHERE token='{token}'"))


def draw(page, selector='#signatureCanvas'):
    box = page.locator(selector).bounding_box()
    page.mouse.move(box['x'] + 25, box['y'] + 25)
    page.mouse.down()
    page.mouse.move(box['x'] + 170, box['y'] + 80, steps=12)
    page.mouse.up()


def post_document(api, body, doc_uuid, version):
    data = json.loads(json.dumps(body))
    data['payload']['status'] = 'draft'
    data['draft_ref'] = doc_uuid
    data['expected_version'] = version
    response = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                        data=data, headers={'Idempotency-Key': 'resp-b-' + str(uuid.uuid4())})
    return response


def create_qr(api, doc_uuid, version, role='signer'):
    response = api.post(BASE + '/api/clinical/index.php/responsiva-qr-sessions',
                        data={'document_uuid': doc_uuid, 'document_version': version, 'role': role})
    check('QR_CREATE_HTTP', response.status == 201)
    return response.json()['data']['token']


def mobile_status(api, token, action='mobile-context'):
    return api.get(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/{action}')


migration = os.path.join(ROOT, 'modules/clinical/db/migrations/2026_10_08_31_responsiva_qr_sessions.sql')
for _ in range(2):
    subprocess.run(['mysql', DB], stdin=open(migration, 'rb'), check=True)
check('MIGRATION_APPLIED_TWICE', sql("SHOW TABLES LIKE 'clinical_responsiva_qr_sessions'") == 'clinical_responsiva_qr_sessions')

with sync_playwright() as pw:
    browser = pw.webkit.launch(headless=True)
    desktop_context = browser.new_context(viewport={'width': 1440, 'height': 900},
                                          extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    phone_context = browser.new_context(viewport={'width': 390, 'height': 844})
    desktop = desktop_context.new_page()
    errors = []
    document_requests = []
    desktop.on('pageerror', lambda error: errors.append(str(error)))
    desktop.on('request', lambda request: document_requests.append(request.post_data_json)
               if request.method == 'POST' and f'/patients/{PATIENT}/documents' in request.url else None)
    api = desktop_context.request
    phone_api = phone_context.request
    desktop.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    desktop.wait_for_function('typeof window.setActivePatientId === "function"')
    desktop.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    desktop.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    desktop.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    desktop.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    desktop.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    desktop.locator('[data-action="documents-open-responsiva"]').click()
    patient_signer_name = desktop.locator('#rm_signer_name').input_value()
    desktop.locator('#rm_clinical_situation').fill('Situación clínica versión A')
    desktop.locator('#rm_next').click()
    desktop.locator('#rm_additional_info summary').click()
    desktop.locator('#rm_indicated_conduct').fill('Conducta A')
    desktop.locator('#rm_declaration_text').fill('Declaración A')
    desktop.locator('#rm_next').click()
    desktop.locator('#rm_next').click()
    desktop.locator('#rm_next').click()
    expect(desktop.locator('#rm_content_review')).to_contain_text('Situación clínica versión A')
    desktop.locator('#rm_next').click()
    check('T26_LOCAL_BINDING_UNCHANGED', desktop.evaluate('typeof window.mxmedResponsivaSignatureBinding?.bind === "function"'))
    with desktop.expect_response(lambda r: r.request.method == 'POST' and r.url.endswith('/responsiva-qr-sessions')) as created:
        desktop.locator('#rm_signer_signature_qr').click()
    if created.value.status != 201:
        print('QR_CREATE_RESPONSE', created.value.status, created.value.text(), flush=True)
        print('QR_CREATE_BODY', created.value.request.post_data, flush=True)
        print('DRAFT_DB', sql("SELECT CONCAT_WS('|',document_uuid,version,status,patient_id,created_by_user_id,JSON_UNQUOTE(JSON_EXTRACT(payload_json,'$.actor_snapshot.user_id'))) FROM clinical_documents WHERE document_type='responsiva_medica'"), flush=True)
        print('QR_UI_STATUS', desktop.locator('#modalResponsivaSignatureQr [data-role="rm-qr-status"]').inner_text(), flush=True)
    check('T01_CREATE_SIGNER_SESSION', created.value.status == 201)
    data = created.value.json()['data']
    token = data['token']
    check('QR_OPAQUE_TOKEN', bool(re.fullmatch(r'[a-f0-9]{64}', token)))
    link = desktop.locator('#modalResponsivaSignatureQr [data-role="rm-qr-link"]')
    expect(link).to_have_attribute('href', re.compile('token='), timeout=15000)
    href = link.get_attribute('href')
    if os.environ.get('RESP_QR_DESKTOP_SCREENSHOT'):
        desktop.screenshot(path=os.environ['RESP_QR_DESKTOP_SCREENSHOT'], full_page=False)
    check('QR_DIRECT_URL', parse_qs(urlparse(href).query)['token'][0] == token)
    doc_uuid = sql("SELECT document_uuid FROM clinical_documents WHERE document_type='responsiva_medica' ORDER BY id DESC LIMIT 1")
    status, version, payload = row(doc_uuid)
    q = qrrow(token)
    check('T02_UUID_BOUND', q['uuid'] == doc_uuid)
    check('T03_VERSION_BOUND', q['version'] == version)
    fingerprint = desktop.evaluate('async()=>{const s=window.mxmedResponsivaSignatureBinding;return s?.hash ? "available" : "missing"}')
    check('T04_FINGERPRINT_BOUND', fingerprint == 'available' and bool(re.fullmatch(r'[a-f0-9]{64}', q['fingerprint'])))
    check('T05_SIGNER_AUTHORITY_BOUND', q['role'] == 'signer' and patient_signer_name in q['authority'])
    check('TTL_900', int(sql(f"SELECT TIMESTAMPDIFF(SECOND,created_at,expires_at) FROM clinical_responsiva_qr_sessions WHERE token='{token}'")) == 900)
    check('NO_SESSION_STATUS_DENIED', phone_api.get(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/status').status in (401, 403))
    phone = phone_context.new_page()
    phone.goto(href, wait_until='domcontentloaded')
    expect(phone.locator('#consentReview')).to_be_visible(timeout=15000)
    if os.environ.get('RESP_QR_MOBILE_SCREENSHOT'):
        phone.screenshot(path=os.environ['RESP_QR_MOBILE_SCREENSHOT'], full_page=True)
    srcdoc = phone.locator('#consentReviewFrame').get_attribute('srcdoc')
    saved_html = payload['responsiva_snapshot']['html']
    check('T06_EXACT_MOBILE_REVIEW', saved_html in srcdoc and hashlib.sha256(saved_html.encode()).hexdigest() == q['review_hash'])
    check('T07_MOBILE_SIGNER_ROLE', 'Paciente' in phone.locator('#consentSigner').inner_text())
    check('T08_REVIEW_FIRST', not phone.locator('#signatureForm').is_visible()
          and phone_api.post(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/signature', data={}).status == 409)
    phone.locator('#consentContinue').click()
    expect(phone.locator('#signatureForm')).to_be_visible()
    blank = phone.locator('#signatureCanvas').evaluate('(canvas)=>canvas.toDataURL("image/png")')
    check('T12_BLANK_REJECTED', phone_api.post(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/signature',
          data={'signature_data': blank}).status == 422)
    tiny = phone.locator('#signatureCanvas').evaluate('''(canvas)=>{const c=canvas.getContext('2d');c.fillStyle='#000';c.fillRect(5,5,1,1);const d=canvas.toDataURL('image/png');c.fillStyle='#fff';c.fillRect(5,5,1,1);return d}''')
    check('T13_TINY_REJECTED', phone_api.post(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/signature',
          data={'signature_data': tiny}).status == 422)
    draw(phone)
    phone.locator('#signatureSubmit').click()
    expect(phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente', timeout=15000)
    check('T09_REMOTE_SIGNER_ACCEPTED', qrrow(token)['status'] in ('uploaded', 'consumed'))
    check('T10_ARTIFACT_DIGEST', bool(re.fullmatch(r'[a-f0-9]{64}', qrrow(token)['artifact'])))
    check('T15_REPLAY_REJECTED', phone_api.post(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{token}/signature',
          data={'signature_data': blank}).status == 409)
    expect(desktop.locator('#modalResponsivaSignatureQr [data-role="rm-qr-received"]')).to_be_visible(timeout=20000)
    check('T11_BINDING_PERSISTED', row(doc_uuid)[2]['signature_binding_status']['signer'] == 'valid_bound_signature'
          and qrrow(token)['status'] == 'consumed')
    check('T23_DRAFT_REMOTE_SIGNER', row(doc_uuid)[0] == 'draft')
    check('DESKTOP_RECEIVED', 'Firma recibida correctamente' in desktop.locator('#modalResponsivaSignatureQr [data-role="rm-qr-status"]').inner_text())
    desktop.locator('#modalResponsivaSignatureQr [data-bs-dismiss="modal"]').last.click()
    expect(desktop.locator('#modalResponsivaSignatureQr')).to_be_hidden()
    desktop.locator('#rm_save').click()
    expect(desktop.locator('#modalResponsivaMedica')).to_be_hidden()
    desktop.locator('#t-consent .docvis-back').click()
    desktop.locator('#t-consent .docvis-back').click()
    desktop.locator('#t-consent .docvis-intents').first.locator('button').nth(1).click()
    desktop.locator('#t-consent .vis06-controls').get_by_role('button', name='Actualizar').click()
    draft = desktop.locator('#t-consent .vis06-row').filter(has_text='Responsiva médica').first
    draft.get_by_role('button', name='Continuar borrador').click()
    for _ in range(5):
        desktop.locator('#rm_next').click()
    expect(desktop.locator('#rm_step_6')).to_be_visible(timeout=15000)
    check('T24_REOPEN_REMOTE_VALID', 'vinculada' in desktop.locator('#rm_signer_signature_status').inner_text())
    check('T29_SHARED_PREVIEW', saved_html.startswith('<article') and '<article' in row(doc_uuid)[2]['responsiva_snapshot']['html'])
    # A newer canonical draft version invalidates a QR issued for the preceding version.
    token_stale = create_qr(api, doc_uuid, row(doc_uuid)[1])
    before = row(doc_uuid)[1]
    source_body = json.loads(json.dumps(document_requests[0]))
    source_body['payload'] = row(doc_uuid)[2]
    source_body['payload']['content']['clinical_situation'] = 'Situación clínica versión B'
    changed = post_document(api, source_body, doc_uuid, before)
    check('T16_NEW_VERSION_SAVED', changed.status in (200, 201))
    check('T16_CONTENT_CHANGE_STALE', mobile_status(phone_api, token_stale).status == 409)
    check('T03_VERSION_MOVED', row(doc_uuid)[1] == before + 1)
    check('T25_REMOTE_CLASSIFIES_STALE', row(doc_uuid)[2]['signature_binding_status']['signer'] == 'stale_or_unverified_signature')
    # Isolated variants prove the same server-side gate for signer and physician authority.
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.content.clinical_situation','Situación clínica versión A') WHERE document_uuid='{doc_uuid}'")
    current = row(doc_uuid)[1]
    signer_token = create_qr(api, doc_uuid, current)
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.signer.name','Otro firmante') WHERE document_uuid='{doc_uuid}'")
    check('T17_SIGNER_CHANGE_STALE', mobile_status(phone_api, signer_token).status == 409)
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.signer.name','Paciente QA') WHERE document_uuid='{doc_uuid}'")
    rel_token = create_qr(api, doc_uuid, current)
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.signer.relationship','Tutor') WHERE document_uuid='{doc_uuid}'")
    check('T18_RELATIONSHIP_CHANGE_STALE', mobile_status(phone_api, rel_token).status == 409)
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.signer.relationship','') WHERE document_uuid='{doc_uuid}'")
    expired_token = create_qr(api, doc_uuid, current)
    sql(f"UPDATE clinical_responsiva_qr_sessions SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='{expired_token}'")
    check('T14_EXPIRED_REJECTED', mobile_status(phone_api, expired_token).status == 409)
    doctor_token = create_qr(api, doc_uuid, current, 'doctor')
    check('T20_DOCTOR_SESSION', qrrow(doctor_token)['role'] == 'doctor')
    doctor_phone = phone_context.new_page()
    doctor_phone.goto(BASE + '/public/note-capture.html?mode=responsiva-signature&token=' + doctor_token)
    expect(doctor_phone.locator('#consentReview')).to_be_visible()
    check('T21_DOCTOR_ROLE', 'Médico' in doctor_phone.locator('#consentSigner').inner_text())
    doctor_phone.locator('#consentContinue').click()
    draw(doctor_phone)
    doctor_phone.locator('#signatureSubmit').click()
    expect(doctor_phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente')
    check('T22_DOCTOR_REMOTE_UPLOAD', qrrow(doctor_token)['status'] == 'uploaded')
    doctor_signature = api.get(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{doctor_token}/status').json()['data']['signature']
    doctor_body = json.loads(json.dumps(document_requests[0]))
    doctor_body['payload'] = row(doc_uuid)[2]
    doctor_body['payload']['signatures']['doctor'] = doctor_signature
    doctor_saved = post_document(api, doctor_body, doc_uuid, current)
    check('T22_DOCTOR_REMOTE_BOUND', doctor_saved.status in (200, 201)
          and row(doc_uuid)[2]['signature_binding_status']['doctor'] == 'valid_bound_signature'
          and qrrow(doctor_token)['status'] == 'consumed')
    # A material edit may preserve the exact prior QR artifact even when the browser
    # serializes JSON object keys in a different order from the stored document.
    current = row(doc_uuid)[1]
    edited_body = json.loads(json.dumps(document_requests[0]))
    edited_body['payload'] = row(doc_uuid)[2]
    prior_doctor = edited_body['payload']['signatures']['doctor']
    reordered_doctor = dict(reversed(list(prior_doctor.items())))
    reordered_doctor['binding'] = dict(reversed(list(prior_doctor['binding'].items())))
    edited_body['payload']['signatures']['doctor'] = reordered_doctor
    edited_body['payload'].setdefault('presentation', {})['professional_header'] = 'hidden'
    edited = post_document(api, edited_body, doc_uuid, current)
    check('T32_REORDERED_REMOTE_ARTIFACT_PRESERVED', edited.status in (200, 201)
          and row(doc_uuid)[1] == current + 1
          and row(doc_uuid)[2]['signature_binding_status']['doctor'] == 'stale_or_unverified_signature'
          and row(doc_uuid)[2]['signatures']['doctor']['image_data'] == prior_doctor['image_data'])
    tampered_body = json.loads(json.dumps(edited_body))
    tampered_body['payload'] = row(doc_uuid)[2]
    tampered_body['payload']['signatures']['doctor']['binding']['artifact_digest'] = '0' * 64
    tampered = post_document(api, tampered_body, doc_uuid, row(doc_uuid)[1])
    check('T33_TAMPERED_STALE_REMOTE_REJECTED', tampered.status == 400)
    doctor_stale_token = create_qr(api, doc_uuid, row(doc_uuid)[1], 'doctor')
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.actor_snapshot.user_id','other-user') WHERE document_uuid='{doc_uuid}'")
    check('T19_PHYSICIAN_CHANGE_STALE', mobile_status(phone_api, doctor_stale_token).status == 409)
    sql(f"UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.actor_snapshot.user_id','review-user') WHERE document_uuid='{doc_uuid}'")
    for role in ('signer', 'doctor'):
        current = row(doc_uuid)[1]
        fresh_token = create_qr(api, doc_uuid, current, role)
        fresh_phone = phone_context.new_page()
        fresh_phone.goto(BASE + '/public/note-capture.html?mode=responsiva-signature&token=' + fresh_token)
        expect(fresh_phone.locator('#consentReview')).to_be_visible()
        fresh_phone.locator('#consentContinue').click()
        draw(fresh_phone)
        fresh_phone.locator('#signatureSubmit').click()
        expect(fresh_phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente')
        fresh_signature = api.get(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{fresh_token}/status').json()['data']['signature']
        fresh_body = json.loads(json.dumps(document_requests[0]))
        fresh_body['payload'] = row(doc_uuid)[2]
        fresh_body['payload']['signatures'][role] = fresh_signature
        claimed = post_document(api, fresh_body, doc_uuid, current)
        check('IMP01C_FRESH_' + role.upper() + '_QR_CLAIMED', claimed.status in (200, 201)
              and qrrow(fresh_token)['status'] == 'consumed'
              and row(doc_uuid)[2]['signature_binding_status'][role] == 'valid_bound_signature')
        fresh_phone.close()
    current = row(doc_uuid)[1]
    final_body = json.loads(json.dumps(document_requests[0]))
    final_body['payload'] = row(doc_uuid)[2]
    final_body['payload']['status'] = 'issued'
    final_body['draft_ref'] = doc_uuid
    final_body['expected_version'] = current
    issued = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                      data=final_body, headers={'Idempotency-Key': 'resp-qr-final-' + str(uuid.uuid4())})
    check('IMP01C_QR_QR_EMIT', issued.status in (200, 201) and row(doc_uuid)[0] == 'generated'
          and issued.json()['data']['document_id'] == doc_uuid)
    emitted = row(doc_uuid)[2]
    check('IMP01C_QR_QR_FINGERPRINT_MATCH', emitted['signatures']['signer']['binding']['content_fingerprint']
          == emitted['signatures']['doctor']['binding']['content_fingerprint'])
    def create_extra_draft():
        body = json.loads(json.dumps(document_requests[0]))
        body['payload']['status'] = 'draft'
        body['payload']['signatures'] = {'signer': None, 'doctor': None}
        body.pop('draft_ref', None)
        body.pop('expected_version', None)
        response = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                            data=body, headers={'Idempotency-Key': 'resp-extra-' + str(uuid.uuid4())})
        check('IMP01C_EXTRA_DRAFT', response.status in (200, 201))
        return response.json()['data']['document_id'], body

    def bound_signature(body, role, source, document_uuid):
        body = json.loads(json.dumps(body))
        body['draft_ref'] = document_uuid
        return desktop.evaluate('''async ({body,role,source}) => {
          const canvas=document.createElement('canvas');canvas.width=220;canvas.height=90;
          const ctx=canvas.getContext('2d');ctx.fillStyle='#fff';ctx.fillRect(0,0,220,90);
          ctx.strokeStyle='#102a43';ctx.lineWidth=4;ctx.beginPath();ctx.moveTo(20,20);ctx.lineTo(180,65);ctx.stroke();
          return window.mxmedResponsivaSignatureBinding.bind(body,role,'1',{
            type:'drawn',role,source,image_data:canvas.toDataURL('image/png'),signed_at:'2026-10-08 12:00:00',
            signer_name:role==='doctor'?body.payload.actor_snapshot.full_name:body.payload.signer.name});
        }''', {'body': body, 'role': role, 'source': source})

    def sign_qr_and_claim(extra_uuid, role, source_body):
        version = row(extra_uuid)[1]
        extra_token = create_qr(api, extra_uuid, version, role)
        extra_phone = phone_context.new_page()
        extra_phone.goto(BASE + '/public/note-capture.html?mode=responsiva-signature&token=' + extra_token)
        expect(extra_phone.locator('#consentReview')).to_be_visible()
        extra_phone.locator('#consentContinue').click()
        draw(extra_phone)
        extra_phone.locator('#signatureSubmit').click()
        expect(extra_phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente')
        signature = api.get(BASE + f'/api/clinical/index.php/responsiva-qr-sessions/{extra_token}/status').json()['data']['signature']
        source_body['payload'] = row(extra_uuid)[2]
        source_body['payload']['signatures'][role] = signature
        claimed = post_document(api, source_body, extra_uuid, version)
        check('IMP01C_EXTRA_QR_CLAIMED', claimed.status in (200, 201)
              and row(extra_uuid)[2]['signature_binding_status'][role] == 'valid_bound_signature')
        extra_phone.close()

    def emit_extra(extra_uuid, source_body, name):
        source_body['payload'] = row(extra_uuid)[2]
        source_body['payload']['status'] = 'issued'
        source_body['draft_ref'] = extra_uuid
        source_body['expected_version'] = row(extra_uuid)[1]
        response = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                            data=source_body, headers={'Idempotency-Key': 'resp-extra-emit-' + str(uuid.uuid4())})
        check(name, response.status in (200, 201) and row(extra_uuid)[0] == 'generated'
              and response.json()['data']['document_id'] == extra_uuid)

    local_uuid, local_body = create_extra_draft()
    local_body['payload'] = row(local_uuid)[2]
    local_body['payload']['signatures']['signer'] = bound_signature(local_body, 'signer', 'local_canvas', local_uuid)
    check('IMP01C_LOCAL_SIGNER_DRAFT', post_document(api, local_body, local_uuid, row(local_uuid)[1]).status in (200, 201))
    sign_qr_and_claim(local_uuid, 'doctor', local_body)
    emit_extra(local_uuid, local_body, 'IMP01C_LOCAL_SIGNER_QR_DOCTOR_EMIT')

    registered_uuid, registered_body = create_extra_draft()
    sign_qr_and_claim(registered_uuid, 'signer', registered_body)
    registered_body['payload'] = row(registered_uuid)[2]
    registered = bound_signature(registered_body, 'doctor', 'registered_profile', registered_uuid)
    digest = hashlib.sha256(base64.b64decode(registered['image_data'].split(',')[1])).hexdigest()
    sql('CREATE TABLE IF NOT EXISTS physician_signatures (doctor_id VARCHAR(64) PRIMARY KEY, checksum_sha256 CHAR(64) NOT NULL)')
    sql(f"INSERT INTO physician_signatures (doctor_id,checksum_sha256) VALUES ('1','{digest}') ON DUPLICATE KEY UPDATE checksum_sha256=VALUES(checksum_sha256)")
    registered_body['payload']['signatures']['doctor'] = registered
    check('IMP01C_EXPLICIT_REGISTERED_DOCTOR_DRAFT',
          post_document(api, registered_body, registered_uuid, row(registered_uuid)[1]).status in (200, 201))
    emit_extra(registered_uuid, registered_body, 'IMP01C_QR_SIGNER_REGISTERED_DOCTOR_EMIT')

    representative_uuid, representative_body = create_extra_draft()
    representative_body['payload'] = row(representative_uuid)[2]
    representative_body['payload']['signer'].update({
        'role': 'tutor', 'name': 'Representante QA', 'character': 'Madre', 'relationship': 'Madre'})
    representative_body['payload']['signatures']['signer'] = bound_signature(representative_body, 'signer', 'local_canvas', representative_uuid)
    representative_body['payload']['signatures']['doctor'] = bound_signature(representative_body, 'doctor', 'local_canvas', representative_uuid)
    check('IMP01C_REPRESENTATIVE_BOTH_DRAFT',
          post_document(api, representative_body, representative_uuid, row(representative_uuid)[1]).status in (200, 201))
    changed_representative = json.loads(json.dumps(representative_body))
    changed_representative['payload'] = row(representative_uuid)[2]
    changed_representative['payload']['status'] = 'issued'
    changed_representative['payload']['signer']['relationship'] = 'Tutor legal'
    changed_representative['draft_ref'] = representative_uuid
    changed_representative['expected_version'] = row(representative_uuid)[1]
    changed_attempt = api.post(BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents',
                               data=changed_representative,
                               headers={'Idempotency-Key': 'resp-representative-changed-' + str(uuid.uuid4())})
    check('IMP01C_REPRESENTATIVE_RELATIONSHIP_CHANGE_BLOCKED',
          changed_attempt.status == 400 and row(representative_uuid)[0] == 'draft')
    emit_extra(representative_uuid, representative_body, 'IMP01C_REPRESENTATIVE_EMIT')
    check('T30_CONSENT_TABLE_UNTOUCHED', sql("SHOW TABLES LIKE 'clinical_consent_qr_sessions'") == '')
    check('T31_M6_ENCOUNTER_STILL_OPEN', sql("SELECT COUNT(*) FROM clinical_encounters WHERE encounter_id=1016 AND status='open'") == '1')
    check('NO_BROWSER_ERRORS', not errors)
    browser.close()
