"""CONS-SIGN02B disposable browser and HTTP proof. Run through consultation_flow_r1_disposable_gate.sh."""
import hashlib, json, os, re, subprocess, sys
from urllib.parse import urlparse, parse_qs
from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
assert re.fullmatch(r'http://127\.0\.0\.1:\d+', BASE)

def check(name, value):
    if not value: raise AssertionError(name)
    print(f'{name}=PASS', flush=True)

def sql(query):
    return subprocess.check_output(['mysql','--raw','-N','-B',DB,'-e',query], text=True).strip()

def status(request, token):
    response = request.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+token)
    return response.status, response.json()

def init_desktop(page):
    page.goto(BASE+'/index.html?qa_tools=hide',wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()

def new_consent(page):
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    page.locator('#ci_next').click()
    page.locator('#ci_title').fill('CONS-SIGN02B prueba')
    page.locator('#ci_procedimiento').fill('Procedimiento de prueba versión A')
    page.locator('#ci_preview').click()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()

def open_draft(page):
    page.locator('[data-action="documents-open-consent"]').click()
    expect(page.locator('#modalConsentDraftPrompt')).to_be_visible()
    page.locator('#modalConsentDraftPrompt [data-draft-ref]').first.click()
    expect(page.locator('#modalConsentimientoInformado')).to_be_visible()
    page.locator('#ci_preview').click()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()

def save_draft(page):
    if not page.locator('#ci_signatures_continue').is_visible():
        if page.locator('#ci_preview').is_visible(): page.locator('#ci_preview').click()
        if page.locator('#ci_review_continue').is_visible(): page.locator('#ci_review_continue').click()
    page.locator('#ci_signatures_continue').click()
    with page.expect_response(lambda r:r.request.method=='POST' and '/patients/p_plan02ux_review/documents' in r.url) as response:
        page.locator('#ci_save').click()
    check('DRAFT_SAVE_HTTP',response.value.status in (200,201))
    expect(page.locator('#modalConsentimientoInformado')).to_be_hidden()

def current_payload():
    return json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' ORDER BY id DESC LIMIT 1"))

def to_signatures(page):
    if page.locator('#ci_next').is_visible(): page.locator('#ci_next').click()
    if page.locator('#ci_preview').is_visible(): page.locator('#ci_preview').click()
    if page.locator('#ci_review_continue').is_visible(): page.locator('#ci_review_continue').click()
    if not page.locator('#ci_signatures_panel').is_visible() and page.locator('#ci_review_edit_button').is_visible():
        page.locator('#ci_review_edit_button').click()
        editor=page.locator('#ci_review_edit_procedimiento')
        editor.fill(editor.input_value()+' (revisado)')
        page.locator('#ci_review_apply').click()
        page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    if not page.locator('#ci_review_confirm_informed').is_checked():
        page.locator('#ci_review_confirm_informed').check()

def change_field(page,selector,value):
    page.locator(selector).evaluate('''(el,value)=>{el.value=value;el.dispatchEvent(new Event('input',{bubbles:true}));el.dispatchEvent(new Event('change',{bubbles:true}))}''',value)

def wait_cancelled(page,token):
    page.wait_for_function('''token=>fetch('/api/clinical/index.php/note-capture-tokens/'+token)
      .then(r=>r.json()).then(j=>j.data?.status==='cancelled')''',arg=token,timeout=15000)

def qr(page, role='patient'):
    action='ci-doctor-signature-open-qr' if role=='doctor' else 'ci-signature-open-qr'
    page.locator(f'[data-action="{action}"]').click()
    link=page.locator('#modalConsentSignatureQr [data-role="ci-signature-qr-link"]')
    try: expect(link).to_have_attribute('href',re.compile('token='),timeout=12000)
    except Exception:
        print('QR_ERROR',page.locator('#modalConsentSignatureQr [data-role="ci-signature-qr-state"]').inner_text(),
              page.locator('#ci_signature_remote_status').inner_text(),flush=True)
        raise
    href=link.get_attribute('href')
    token=parse_qs(urlparse(href).query)['token'][0]
    check('TOKEN_OPAQUE',bool(re.fullmatch(r'[0-9a-f]{32}',token)))
    return token,href

def close_qr(page):
    page.locator('#modalConsentSignatureQr .modal-footer [data-bs-dismiss="modal"]').click()
    expect(page.locator('#modalConsentSignatureQr')).to_be_hidden()

def mobile(context,href):
    page=context.new_page()
    page.set_viewport_size({'width':390,'height':844})
    page.goto(href,wait_until='domcontentloaded')
    expect(page.locator('#consentReview')).to_be_visible(timeout=10000)
    return page

def draw(page):
    box=page.locator('#signatureCanvas').bounding_box()
    page.mouse.move(box['x']+25,box['y']+25)
    page.mouse.down()
    page.mouse.move(box['x']+190,box['y']+85,steps=12)
    page.mouse.up()

with sync_playwright() as pw:
    browser=pw.webkit.launch(headless=True)
    context=browser.new_context(viewport={'width':1440,'height':900},extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    mobile_context=browser.new_context()
    desktop=context.new_page()
    errors=[];desktop.on('pageerror',lambda error:errors.append(str(error)))
    token_responses=[]
    desktop.on('response',lambda response:token_responses.append(response)
        if response.request.method=='POST' and response.url.endswith('/note-capture-tokens') else None)
    init_desktop(desktop);new_consent(desktop)
    try: token,href=qr(desktop)
    except Exception:
        print('TOKEN_HTTP',[(r.status,r.text()) for r in token_responses],flush=True)
        for r in token_responses:
            body=json.loads(r.request.post_data)
            print('TOKEN_BODY_SHAPE', {k:body.get(k) for k in ['consent_uuid','draft_ref','draft_version','content_fingerprint']},
                  {k:body.get('consent',{}).get(k) for k in ['document_type','actor_user_id','actor','context']},
                  {k:body.get('consent',{}).get('payload',{}).get(k) for k in ['qr_consent_uuid','signature_document_date']},flush=True)
        raise
    check('T01_CREATE_PATIENT_QR_SESSION',sql(f"SELECT COUNT(*) FROM clinical_consent_qr_sessions q JOIN clinical_note_capture_tokens t ON t.id=q.token_id WHERE t.token='{token}' AND q.role='patient'")=='1')
    check('QR_TTL_900_MAX',int(sql(f"SELECT TIMESTAMPDIFF(SECOND,created_at,expires_at) FROM clinical_note_capture_tokens WHERE token='{token}'"))<=900)
    check('NO_SESSION_DESKTOP_STATUS_DENIED',mobile_context.request.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+token).status in (401,403))
    phone=mobile(mobile_context,href)
    reviewed_html=phone.locator('#consentReviewFrame').get_attribute('srcdoc')
    expected_hash=sql(f"SELECT q.review_html_sha256 FROM clinical_consent_qr_sessions q JOIN clinical_note_capture_tokens t ON t.id=q.token_id WHERE t.token='{token}'")
    check('T02_MOBILE_DISPLAYS_EXACT_CONSENT', 'Procedimiento de prueba versión A' in reviewed_html and hashlib.sha256(reviewed_html.encode()).hexdigest()==expected_hash)
    check('DOC_PRESENT02_MOBILE_SHOWN', 'doc-base-medical-header' in reviewed_html)
    check('MOBILE_390X844_NO_HORIZONTAL_OVERFLOW',phone.evaluate('document.documentElement.scrollWidth<=window.innerWidth'))
    check('T03_MOBILE_ROLE_PATIENT_CORRECT','Firma del paciente' in phone.locator('#consentSigner').inner_text())
    check('T04_MOBILE_REVIEW_REQUIRED_BEFORE_SIGNATURE',not phone.locator('#signatureForm').is_visible())
    phone.locator('#consentContinue').click()
    expect(phone.locator('#signatureForm')).to_be_visible()
    blank=phone.locator('#signatureCanvas').evaluate('(canvas)=>canvas.toDataURL("image/png")')
    check('T08_BLANK_REMOTE_SIGNATURE_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/signature',data={'signature_data':blank}).status==422)
    tiny=phone.locator('#signatureCanvas').evaluate('''(canvas)=>{const c=canvas.getContext('2d');c.fillStyle='#000';c.fillRect(5,5,1,1);const image=canvas.toDataURL('image/png');c.fillStyle='#fff';c.fillRect(5,5,1,1);return image}''')
    check('T09_TINY_REMOTE_SIGNATURE_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/signature',data={'signature_data':tiny}).status==422)
    draw(phone)
    phone.locator('#signatureSubmit').click()
    expect(phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente',timeout=15000)
    check('T05_VALID_PATIENT_QR_SIGNATURE',status(context.request,token)[1]['data']['status']=='uploaded')
    check('T07_REMOTE_ARTIFACT_DIGEST_VALIDATED',bool(re.fullmatch(r'[a-f0-9]{64}',sql(f"SELECT q.artifact_digest FROM clinical_consent_qr_sessions q JOIN clinical_note_capture_tokens t ON t.id=q.token_id WHERE t.token='{token}'"))))
    check('TOKEN_REPLAY_AFTER_UPLOAD_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/signature',data={'signature_data':'x'}).status==409)
    desktop.locator('#modalConsentSignatureQr [data-action="ci-signature-qr-verify-now"]').click()
    desktop.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')",timeout=15000)
    check('PATIENT_DESKTOP_BOUND',desktop.locator('#ci_signature_status').inner_text()=='Firma vinculada a esta versión')
    close_qr(desktop)
    save_draft(desktop)
    payload=current_payload()
    binding=payload['signatures']['patient']['binding']
    check('T06_REMOTE_SIGNATURE_BINDING_METADATA_PERSISTED',payload['signature_binding_status']['patient']=='valid_bound_signature' and binding['version']==2 and binding['source']=='remote_qr')
    check('T11_CONSUMED_TOKEN_REPLAY_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/signature',data={'signature_data':'x'}).status==409)
    consumed_status=status(context.request,token)[1]['data']
    check('CONSUMED_TOKEN_HISTORICAL_BINDING_VISIBLE',consumed_status['status']=='consumed'
        and consumed_status['signature']['binding']['version']==2 and not consumed_status.get('qr_stale'))

    open_draft(desktop)
    check('T20_DRAFT_REOPEN_VALID_REMOTE_SIGNATURE_RESTORED',desktop.locator('#ci_signature_status').inner_text()=='Firma vinculada a esta versión')
    if not desktop.locator('#ci_review_confirm_informed').is_checked():
        desktop.locator('#ci_review_confirm_informed').check()
    doctor_token,doctor_href=qr(desktop,'doctor')
    check('T15_CREATE_PHYSICIAN_QR_SESSION',sql(f"SELECT role FROM clinical_consent_qr_sessions q JOIN clinical_note_capture_tokens t ON t.id=q.token_id WHERE t.token='{doctor_token}'")=='doctor')
    check('QR_TOKEN_BOUND_TO_DOCTOR_AUTHORITY',sql(f"SELECT CONCAT(q.doctor_id,'|',q.actor_user_id,'|',q.signer_authority) FROM clinical_consent_qr_sessions q JOIN clinical_note_capture_tokens t ON t.id=q.token_id WHERE t.token='{doctor_token}'")=='1|review-user|1|review-user')
    doctor_phone=mobile(mobile_context,doctor_href)
    check('T16_MOBILE_ROLE_DOCTOR_CORRECT','Firma del médico' in doctor_phone.locator('#consentSigner').inner_text())
    doctor_phone.locator('#consentContinue').click()
    expect(doctor_phone.locator('#signatureForm')).to_be_visible()
    draw(doctor_phone)
    doctor_phone.locator('#signatureSubmit').click()
    expect(doctor_phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente',timeout=15000)
    desktop.locator('#modalConsentSignatureQr [data-action="ci-signature-qr-verify-now"]').click()
    desktop.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')",timeout=15000)
    check('T17_VALID_PHYSICIAN_QR_SIGNATURE',desktop.locator('#ci_doctor_signature_status').inner_text()=='Firma vinculada a esta versión')
    close_qr(desktop)
    save_draft(desktop)
    check('DOCTOR_SERVER_BOUND',current_payload()['signature_binding_status']['doctor']=='valid_bound_signature')
    emission_mode=os.environ.get('CONSENT_QR_EMISSION_MODE','')
    if emission_mode in ('both_qr','patient_local_doctor_qr','patient_qr_doctor_registered'):
        open_draft(desktop)
        if not desktop.locator('#ci_review_confirm_informed').is_checked():
            desktop.locator('#ci_review_confirm_informed').check()
        if emission_mode=='patient_local_doctor_qr':
            desktop.locator('#ci_signature_clear').click()
            box=desktop.locator('#ci_signature_canvas').bounding_box()
            desktop.mouse.move(box['x']+25,box['y']+25)
            desktop.mouse.down();desktop.mouse.move(box['x']+150,box['y']+65,steps=9);desktop.mouse.up()
            desktop.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')")
        elif emission_mode=='patient_qr_doctor_registered':
            desktop.locator('#ci_doctor_signature_clear').click()
            image=current_payload()['signatures']['patient']['image_data']
            digest=hashlib.sha256(__import__('base64').b64decode(image.split(',',1)[1])).hexdigest()
            subprocess.run(['mysql',DB,'-e',"CREATE TABLE IF NOT EXISTS physician_signatures (doctor_id VARCHAR(64) PRIMARY KEY, checksum_sha256 CHAR(64) NOT NULL)"],check=True)
            subprocess.run(['mysql',DB,'-e',f"REPLACE INTO physician_signatures VALUES ('1','{digest}')"],check=True)
            desktop.evaluate("image=>{window.mxmedPhysicianSignature={read:()=>image,refresh:async()=>{}};document.dispatchEvent(new Event('mxmed:signature-changed'))}",image)
            desktop.locator('#ci_doctor_signature_apply_registered').click()
            desktop.wait_for_function("document.querySelector('#ci_doctor_signature_status').textContent.includes('vinculada')")
        desktop.locator('#ci_signatures_continue').click()
        expect(desktop.locator('#ci_review_signature_status')).to_contain_text('Paciente: Firma válida para esta versión')
        expect(desktop.locator('#ci_review_signature_status')).to_contain_text('Médico: Firma válida para esta versión')
        with desktop.expect_response(lambda r:r.request.method=='POST' and '/patients/p_plan02ux_review/documents' in r.url) as emission:
            desktop.locator('#ci_emit').click()
        check('CONSENT_QR_EMISSION_HTTP',emission.value.status in (200,201))
        result=current_payload()
        check('CONSENT_QR_EMISSION_VERIFIED',result['consent']['status']=='granted'
            and result['signature_binding_status']=={'patient':'valid_bound_signature','doctor':'valid_bound_signature'}
            and result['signatures']['patient']['binding']['content_fingerprint']==result['signatures']['doctor']['binding']['content_fingerprint'])
        check('CONSENT_QR_EMISSION_MODE_'+emission_mode.upper(),True)
        browser.close()
        sys.exit(0)
    subprocess.run(['php','modules/clinical/qa/consent_qr_binding_contract.php',DB],check=True)
    doctor_phone.close()

    # Authenticated desktop scope is the issuing doctor, even if another doctor has a patient link.
    sql("INSERT INTO patients_doctor_links (doctor_id,patient_id,status) VALUES ('2','p_plan02ux_review','active')")
    sessions=os.path.join(os.environ['FLOW_R1_QA_ROOT'],'sessions')
    subprocess.run(['php','-d',f'session.save_path={sessions}','-r',
        'session_id("consent-qr-other-doctor");session_start();$_SESSION["doctor_id"]="2";$_SESSION["user_id"]="other-user";session_write_close();'],check=True)
    other=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=consent-qr-other-doctor'})
    check('T18_WRONG_DOCTOR_TOKEN_REJECTED',other.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+doctor_token).status==403)
    other.dispose()
    create_body=json.loads(token_responses[0].request.post_data)
    sql("INSERT INTO patients_patients VALUES ('p_foreign','1990-01-01')")
    create_body['patient_id']='p_foreign'
    wrong_patient=context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens',data=create_body)
    check('T19_WRONG_PATIENT_SCOPE_REJECTED',wrong_patient.status==404
        and wrong_patient.json()['error']=='not_found')

    # The previously accepted remote signature becomes stale when the saved draft changes.
    sql("UPDATE clinical_documents SET payload_json=JSON_SET(payload_json,'$.form_snapshot.procedimiento','Procedimiento de prueba versión B'),version=version+1 WHERE document_type='consentimiento_informado'")
    init_desktop(desktop);open_draft(desktop)
    desktop.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('requiere confirmación')",timeout=15000)
    check('T21_CHANGED_DRAFT_REMOTE_SIGNATURE_STALE',desktop.locator('#ci_signature_status').inner_text()=='Firma requiere confirmación nuevamente')
    if os.environ.get('CONSENT_QR_STALE_EMISSION_ONLY')=='1':
        if not desktop.locator('#ci_review_confirm_informed').is_checked():
            desktop.locator('#ci_review_confirm_informed').check()
        desktop.locator('#ci_signatures_continue').click()
        expect(desktop.locator('#ci_review_heading')).to_have_text('Vista previa · Revisión final')
        desktop.locator('#ci_emit').click()
        expect(desktop.locator('#ci_review_warning')).to_contain_text('versión anterior')
        check('STALE_REOPENED_DRAFT_EMISSION_BLOCKED',True)
        browser.close()
        sys.exit(0)

    # An uploaded but unsaved QR must stay revoked even if the editor later
    # returns every visible field to the same fingerprint.
    to_signatures(desktop)
    revert_token,revert_href=qr(desktop)
    revert_phone=mobile(mobile_context,revert_href)
    revert_phone.locator('#consentContinue').click()
    expect(revert_phone.locator('#signatureForm')).to_be_visible()
    draw(revert_phone)
    revert_phone.locator('#signatureSubmit').click()
    expect(revert_phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente',timeout=15000)
    desktop.locator('#modalConsentSignatureQr [data-action="ci-signature-qr-verify-now"]').click()
    desktop.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('vinculada')",timeout=15000)
    close_qr(desktop)
    change_field(desktop,'#ci_procedimiento','Procedimiento de prueba versión C')
    change_field(desktop,'#ci_procedimiento','Procedimiento de prueba versión B')
    desktop.wait_for_function('''token=>fetch('/api/clinical/index.php/note-capture-tokens/'+token)
      .then(r=>r.json()).then(j=>j.data?.qr_stale===true)''',arg=revert_token,timeout=15000)
    reverted=status(context.request,revert_token)[1]['data']
    check('QR_UPLOAD_EDIT_REVERT_STAYS_REVOKED',reverted['status']=='uploaded'
        and reverted.get('qr_stale') is True and 'binding' not in reverted['signature']
        and desktop.locator('#ci_signature_status').inner_text()=='Firma requiere confirmación nuevamente')
    revert_phone.close()

    # Every pending token is revoked when its live consent changes.
    to_signatures(desktop)
    stale_token,stale_href=qr(desktop)
    stale_phone=mobile(mobile_context,stale_href)
    stale_phone.locator('#consentContinue').click()
    expect(stale_phone.locator('#signatureForm')).to_be_visible()
    change_field(desktop,'#ci_procedimiento','Procedimiento de prueba versión C')
    wait_cancelled(desktop,stale_token)
    valid_image=sql(f"SELECT signature_image_data FROM clinical_note_capture_tokens WHERE token='{token}'")
    check('T12_CONTENT_CHANGE_AFTER_TOKEN_REJECTS_OLD_SIGNATURE',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+stale_token+'/signature',data={'signature_data':valid_image}).status==409)
    close_qr(desktop)
    stale_phone.close()

    # A pending V2 QR is bound to the visible professional-header choice.
    to_signatures(desktop)
    header_token,header_href=qr(desktop)
    header_phone=mobile(mobile_context,header_href)
    check('DOC_PRESENT02_OLD_MOBILE_SHOWN',
        'doc-base-medical-header' in header_phone.locator('#consentReviewFrame').get_attribute('srcdoc'))
    header_phone.locator('#consentContinue').click()
    expect(header_phone.locator('#signatureForm')).to_be_visible()
    desktop.locator('#ci_professional_header_hidden').evaluate(
        "el=>{el.checked=true;el.dispatchEvent(new Event('change',{bubbles:true}))}")
    wait_cancelled(desktop,header_token)
    check('DOC_PRESENT02_HEADER_CHANGE_REJECTS_OLD_QR',
        context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+header_token+'/signature',
            data={'signature_data':valid_image}).status==409)
    close_qr(desktop)
    header_phone.close()

    to_signatures(desktop)
    attachment_token,_=qr(desktop)
    desktop.locator('#ci_identity_files').set_input_files({'name':'identidad.png','mimeType':'image/png','buffer':__import__('base64').b64decode(valid_image.split(',',1)[1])})
    wait_cancelled(desktop,attachment_token)
    check('T13_ATTACHMENT_CHANGE_AFTER_TOKEN_REJECTS_OLD_SIGNATURE',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+attachment_token+'/signature',data={'signature_data':valid_image}).status==409)
    close_qr(desktop)
    desktop.locator('#ci_identity_files').set_input_files([])

    to_signatures(desktop)
    signer_token,_=qr(desktop)
    change_field(desktop,'#ci_firmante_nombre','Representante de prueba')
    wait_cancelled(desktop,signer_token)
    check('T14_SIGNER_CHANGE_AFTER_TOKEN_REJECTS_OLD_SIGNATURE',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+signer_token+'/signature',data={'signature_data':valid_image}).status==409)
    close_qr(desktop)

    to_signatures(desktop)
    expired_token,_=qr(desktop)
    sql(f"UPDATE clinical_note_capture_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE token='{expired_token}'")
    check('T10_EXPIRED_TOKEN_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+expired_token+'/signature',data={'signature_data':valid_image}).status==410)
    check('EXPIRED_MOBILE_REVIEW_UNAVAILABLE',mobile_context.request.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+expired_token+'/mobile-context').status==410)
    close_qr(desktop)

    to_signatures(desktop)
    version_token,_=qr(desktop)
    sql("UPDATE clinical_documents SET version=version+1 WHERE document_type='consentimiento_informado'")
    check('SERVER_DRAFT_VERSION_STALE_REJECTED',context.request.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+version_token+'/signature',data={'signature_data':valid_image}).status==409)
    close_qr(desktop)

    # A QR artifact with no binding remains visibly unbound after resume.
    sql("UPDATE clinical_documents SET payload_json=JSON_REMOVE(payload_json,'$.signatures.patient.binding') WHERE document_type='consentimiento_informado'")
    init_desktop(desktop);open_draft(desktop)
    desktop.wait_for_function("document.querySelector('#ci_signature_status').textContent.includes('sin vinculación V1')",timeout=15000)
    check('T22_LEGACY_QR_REMAINS_UNBOUND',desktop.locator('#ci_signature_status').inner_text()=='Firma remota sin vinculación V1')
    check('NO_BROWSER_ERRORS',not errors)
    phone.close();mobile_context.close();browser.close()
