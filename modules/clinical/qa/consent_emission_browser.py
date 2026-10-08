"""CONS-SIGN02C disposable browser and canonical-writer emission proof."""
import json
import os
import re
import subprocess
import uuid
from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
assert re.fullmatch(r'http://127\.0\.0\.1:\d+', BASE)

def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(name + '=PASS', flush=True)

def latest():
    raw = subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e',
        "SELECT status,payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' ORDER BY id DESC LIMIT 1"], text=True)
    status, payload = raw.rstrip('\n').split('\t', 1)
    return status, json.loads(payload)

def draw(page, selector):
    page.locator(selector).scroll_into_view_if_needed()
    box = page.locator(selector).bounding_box()
    page.mouse.move(box['x'] + 25, box['y'] + 25)
    page.mouse.down()
    page.mouse.move(box['x'] + 145, box['y'] + 65, steps=9)
    page.mouse.up()

def setup(page):
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
    page.locator('#ci_title').fill('CONS-SIGN02C emisión')
    page.locator('#ci_procedimiento').fill('Procedimiento con dos firmas verificadas')
    page.locator('#ci_preview').click()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()

def review(page):
    page.locator('#ci_signatures_continue').click()
    expect(page.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
    expect(page.locator('#ci_review_signature_status')).to_be_visible()

def back(page):
    page.locator('#ci_review_back_signatures').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()

def blocked(page, expected):
    posts = []
    page.on('request', lambda request: posts.append(request.url)
        if request.method == 'POST' and '/patients/p_plan02ux_review/documents' in request.url else None)
    page.locator('#ci_emit').click()
    expect(page.locator('#ci_review_warning')).to_contain_text(expected)
    check('FRONTEND_NO_EMISSION_REQUEST', len(posts) == 0)

with sync_playwright() as playwright:
    browser = playwright.webkit.launch(headless=True)
    for width, height in [(1366, 768), (1440, 900)]:
        page = browser.new_page(viewport={'width': width, 'height': height},
            extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        setup(page)
        review(page)
        check('ZERO_SIGNATURES_STATUS', 'Paciente: Firma pendiente' in page.locator('#ci_review_signature_status').inner_text()
            and 'Médico: Firma pendiente' in page.locator('#ci_review_signature_status').inner_text())
        blocked(page, 'Falta una firma válida del paciente')
        back(page)
        draw(page, '#ci_doctor_signature_canvas')
        page.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
        review(page)
        blocked(page, 'Falta una firma válida del paciente')
        back(page)
        page.locator('#ci_doctor_signature_clear').click()
        draw(page, '#ci_signature_canvas')
        page.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')")
        review(page)
        blocked(page, 'Falta una firma válida del médico')
        back(page)
        draw(page, '#ci_doctor_signature_canvas')
        page.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
        review(page)
        check('BOTH_VALID_STATUS', page.locator('#ci_review_signature_status').inner_text().count('Firma válida para esta versión') == 2)
        check('SIGNATURE_STATUS_NO_CLIPPING', page.locator('#ci_review_signature_status').evaluate(
            '(el)=>el.scrollWidth<=el.clientWidth'))
        with page.expect_response(lambda response: response.request.method == 'POST'
            and '/patients/p_plan02ux_review/documents' in response.url) as response:
            page.locator('#ci_emit').click()
        if response.value.status not in (200, 201):
            print('EMIT_REJECTION', response.value.status, response.value.text(), flush=True)
            body = json.loads(response.value.request.post_data)
            payload = body['payload']
            print('EMIT_CONTEXT', {'actor_user_id': body.get('actor_user_id'),
                'form_signer': {key: payload['form_snapshot'].get(key) for key in
                    ('firmante_tipo', 'firmante_nombre', 'firmante_parentesco')},
                'signer': payload.get('firmante'),
                'patient_name': payload.get('patient_snapshot', {}).get('full_name')}, flush=True)
        check('EMIT_HTTP', response.value.status in (200, 201))
        status, payload = latest()
        check('GENERATED', status == 'generated' and payload['consent']['status'] == 'granted')
        check('SERVER_VERIFIED_BOTH', payload['signature_binding_status'] == {
            'patient': 'valid_bound_signature', 'doctor': 'valid_bound_signature'})
        patient_fp = payload['signatures']['patient']['binding']['content_fingerprint']
        doctor_fp = payload['signatures']['doctor']['binding']['content_fingerprint']
        check('FINAL_FINGERPRINTS_MATCH', patient_fp == doctor_fp and len(patient_fp) == 64)
        live_fp = page.evaluate('body=>window.mxmedConsentSignatureBinding.hash(body)',
            json.loads(response.value.request.post_data))
        check('FINAL_PROJECTION_FINGERPRINT_MATCH', patient_fp == live_fp)
        if width == 1440:
            accepted = json.loads(response.value.request.post_data)
            endpoint = response.value.url
            emitted_data = response.value.json()['data']
            emitted_uuid = emitted_data['document'].get('document_uuid') or emitted_data['document_id']
            historical_before = subprocess.check_output(['mysql', '-N', '-B', DB, '-e',
                f"SELECT CONCAT(status,':',version,':',SHA2(payload_json,256)) FROM clinical_documents WHERE document_uuid='{emitted_uuid}'"], text=True).strip()
            document_count = subprocess.check_output(['mysql', '-N', '-B', DB, '-e',
                "SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado'"], text=True).strip()
            def reject(name, mutate, expected):
                candidate = json.loads(json.dumps(accepted))
                mutate(candidate)
                result = page.request.post(endpoint, data=candidate,
                    headers={'Idempotency-Key': 'consent-qa-' + uuid.uuid4().hex})
                if result.status != 422 or result.json().get('error', {}).get('code') != expected:
                    print('REJECT_MISMATCH', name, result.status, result.text(), flush=True)
                check(name, result.status == 422 and result.json().get('error', {}).get('code') == expected)
            reject('SERVER_ZERO_SIGNATURES_REJECTED', lambda body: body['payload']['signatures'].update(
                {'patient': None, 'doctor': None}), 'CONSENT_PATIENT_SIGNATURE_REQUIRED')
            reject('SERVER_PATIENT_ONLY_REJECTED', lambda body: body['payload']['signatures'].update(
                {'doctor': None}), 'CONSENT_PHYSICIAN_SIGNATURE_REQUIRED')
            reject('SERVER_DOCTOR_ONLY_REJECTED', lambda body: body['payload']['signatures'].update(
                {'patient': None}), 'CONSENT_PATIENT_SIGNATURE_REQUIRED')
            reject('SERVER_CONFIRMATION_REQUIRED', lambda body: body['payload']['form_snapshot'].update(
                {'confirm_informed': False}), 'CONSENT_CONFIRMATION_REQUIRED')
            reject('SERVER_BLANK_PATIENT_REJECTED', lambda body: body['payload']['signatures']['patient'].update(
                {'image_data': ''}), 'CONSENT_PATIENT_SIGNATURE_REQUIRED')
            reject('SERVER_BLANK_DOCTOR_REJECTED', lambda body: body['payload']['signatures']['doctor'].update(
                {'image_data': ''}), 'CONSENT_PHYSICIAN_SIGNATURE_REQUIRED')
            reject('SERVER_LEGACY_PATIENT_REJECTED', lambda body: body['payload']['signatures']['patient'].pop(
                'binding'), 'CONSENT_PATIENT_SIGNATURE_REQUIRED')
            reject('SERVER_LEGACY_DOCTOR_REJECTED', lambda body: body['payload']['signatures']['doctor'].pop(
                'binding'), 'CONSENT_PHYSICIAN_SIGNATURE_REQUIRED')
            reject('SERVER_STALE_PATIENT_REJECTED', lambda body: body['payload']['signatures']['patient']['binding'].update(
                {'content_fingerprint': '0' * 64}), 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject('SERVER_STALE_DOCTOR_REJECTED', lambda body: body['payload']['signatures']['doctor']['binding'].update(
                {'content_fingerprint': '0' * 64}), 'CONSENT_DOCTOR_SIGNATURE_STALE')
            reject('SERVER_WRONG_SIGNER_ROLE_REJECTED', lambda body: body['payload']['signatures']['patient'].update(
                {'role': 'doctor'}), 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject('SERVER_ARTIFACT_DIGEST_REJECTED', lambda body: body['payload']['signatures']['doctor']['binding'].update(
                {'artifact_digest': '0' * 64}), 'CONSENT_DOCTOR_SIGNATURE_STALE')
            reject('SERVER_WRONG_DOCTOR_BINDING_REJECTED', lambda body: body['payload']['signatures']['doctor']['binding'].update(
                {'authority': '2|another-doctor'}), 'CONSENT_DOCTOR_SIGNATURE_STALE')
            reject('SERVER_CONTENT_CHANGE_REJECTED', lambda body: body['payload']['form_snapshot'].update(
                {'procedimiento': 'Contenido cambiado'}), 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject('SERVER_WITNESS_CHANGE_REJECTED', lambda body: body['payload'].update(
                {'testigos': [{'nombre': 'Nuevo testigo'}]}), 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject('SERVER_REPRESENTATIVE_MISSING_REJECTED', lambda body: (
                body['payload']['form_snapshot'].update({'firmante_tipo': 'tutor',
                    'firmante_nombre': 'Tutor QA', 'firmante_parentesco': 'madre'}),
                body['payload']['firmante'].update({'tipo': 'tutor', 'nombre': 'Tutor QA',
                    'relacion': 'madre', 'parentesco': 'madre'}),
                body['payload']['signatures'].update({'patient': None})),
                'CONSENT_REPRESENTATIVE_SIGNATURE_REQUIRED')
            reject('SERVER_REPRESENTATIVE_OLD_PATIENT_REJECTED', lambda body: (
                body['payload']['form_snapshot'].update({'firmante_tipo': 'tutor',
                    'firmante_nombre': 'Tutor QA', 'firmante_parentesco': 'madre'}),
                body['payload']['firmante'].update({'tipo': 'tutor', 'nombre': 'Tutor QA',
                    'relacion': 'madre', 'parentesco': 'madre'})), 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject('SERVER_PHYSICIAN_CONTEXT_REJECTED', lambda body: body['actor'].update(
                {'user_id': 'another-doctor'}), 'CONSENT_SIGNATURE_CONTEXT_MISMATCH')
            unchanged_count = subprocess.check_output(['mysql', '-N', '-B', DB, '-e',
                "SELECT COUNT(*) FROM clinical_documents WHERE document_type='consentimiento_informado'"], text=True).strip()
            check('REJECTED_ATTEMPTS_DID_NOT_WRITE', unchanged_count == document_count)
            historical_after = subprocess.check_output(['mysql', '-N', '-B', DB, '-e',
                f"SELECT CONCAT(status,':',version,':',SHA2(payload_json,256)) FROM clinical_documents WHERE document_uuid='{emitted_uuid}'"], text=True).strip()
            check('HISTORICAL_GENERATED_UNCHANGED', historical_before == historical_after)
            historical_read = page.request.get(BASE + '/api/clinical/index.php/doctors/1/documents/' + emitted_uuid)
            check('HISTORICAL_GENERATED_READABLE', historical_read.status == 200
                and historical_read.json().get('ok') is True)
            page.locator('[data-action="documents-open-consent"]').click()
            page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
            page.locator('#ci_next').click()
            page.locator('#ci_title').fill('CONS-SIGN02C representante')
            page.locator('#ci_procedimiento').fill('Procedimiento con firma de representante')
            page.locator('#ci_preview').click()
            page.locator('#ci_review_continue').click()
            page.locator('#ci_signer_choice_other').check()
            page.locator('#ci_review_firmante_tipo').select_option('tutor')
            page.locator('#ci_review_firmante_nombre').fill('Tutora QA')
            page.locator('#ci_review_firmante_parentesco').select_option('madre')
            page.locator('#ci_review_confirm_informed').check()
            draw(page, '#ci_signature_canvas')
            page.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')")
            draw(page, '#ci_doctor_signature_canvas')
            page.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
            page.locator('#ci_signatures_back').click()
            page.locator('#ci_review_edit_button').click()
            page.locator('#ci_review_edit_procedimiento').fill('Procedimiento con firma de representante, versión B')
            page.locator('#ci_review_apply').click()
            page.locator('#ci_review_continue').click()
            expect(page.locator('#ci_signatures_panel')).to_be_visible()
            check('CONTENT_EDIT_STALES_BOTH', 'requiere confirmación' in page.locator('#ci_signature_status').inner_text()
                and 'requiere confirmación' in page.locator('#ci_doctor_signature_status').inner_text())
            page.locator('#ci_review_confirm_informed').check()
            review(page)
            blocked(page, 'corresponde a una versión anterior')
            back(page)
            draw(page, '#ci_signature_canvas')
            page.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')")
            draw(page, '#ci_doctor_signature_canvas')
            page.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
            review(page)
            check('REPRESENTATIVE_VALID_STATUS', 'Representante: Firma válida para esta versión'
                in page.locator('#ci_review_signature_status').inner_text())
            with page.expect_response(lambda response: response.request.method == 'POST'
                and '/patients/p_plan02ux_review/documents' in response.url) as representative_response:
                page.locator('#ci_emit').click()
            if representative_response.value.status not in (200, 201):
                print('REPRESENTATIVE_REJECTION', representative_response.value.status,
                    representative_response.value.text(), flush=True)
            check('REPRESENTATIVE_EMIT_HTTP', representative_response.value.status in (200, 201))
            representative_status, representative_payload = latest()
            check('REPRESENTATIVE_VERIFIED', representative_status == 'generated'
                and representative_payload['firmante']['tipo'] == 'tutor'
                and representative_payload['signature_binding_status'] == {
                    'patient': 'valid_bound_signature', 'doctor': 'valid_bound_signature'})
            representative_body = json.loads(representative_response.value.request.post_data)
            def reject_representative(name, field, value):
                candidate = json.loads(json.dumps(representative_body))
                candidate['payload']['form_snapshot'][field] = value
                signer_field = {'firmante_nombre': 'nombre', 'firmante_parentesco': 'relacion'}[field]
                candidate['payload']['firmante'][signer_field] = value
                if field == 'firmante_parentesco': candidate['payload']['firmante']['parentesco'] = value
                result = page.request.post(representative_response.value.url, data=candidate,
                    headers={'Idempotency-Key': 'consent-qa-' + uuid.uuid4().hex})
                check(name, result.status == 422
                    and result.json().get('error', {}).get('code') == 'CONSENT_PATIENT_SIGNATURE_STALE')
            reject_representative('REPRESENTATIVE_NAME_CHANGE_REJECTED', 'firmante_nombre', 'Otra tutora')
            reject_representative('REPRESENTATIVE_RELATIONSHIP_CHANGE_REJECTED', 'firmante_parentesco', 'padre')
            page.locator('[data-action="documents-open-consent"]').click()
            page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
            page.locator('#ci_next').click()
            page.locator('#ci_title').fill('CONS-SIGN02C borrador solo médico')
            page.locator('#ci_procedimiento').fill('Borrador permitido con firma médica')
            page.locator('#ci_preview').click()
            page.locator('#ci_review_continue').click()
            page.locator('#ci_review_confirm_informed').check()
            draw(page, '#ci_doctor_signature_canvas')
            page.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
            review(page)
            with page.expect_response(lambda response: response.request.method == 'POST'
                and '/patients/p_plan02ux_review/documents' in response.url) as draft_response:
                page.locator('#ci_save').click()
            check('DOCTOR_ONLY_DRAFT_HTTP', draft_response.value.status in (200, 201))
            draft_status, draft_payload = latest()
            check('DOCTOR_ONLY_DRAFT_ALLOWED', draft_status == 'draft'
                and draft_payload['signature_binding_status'] == {
                    'patient': 'absent', 'doctor': 'valid_bound_signature'})
        check('NO_PAGE_ERRORS', not errors)
        check(f'BROWSER_{width}X{height}', True)
        page.close()
    browser.close()
