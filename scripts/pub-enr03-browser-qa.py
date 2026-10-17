#!/usr/bin/env python3
"""Reviewed resolution and C4 through the original pages, new owned synthetic target only."""
import os, pathlib, json, socket, subprocess, threading, signal, tempfile, importlib.util, time, hashlib, traceback
from urllib.parse import quote, urlsplit, parse_qs
from playwright.sync_api import sync_playwright, expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
FIXTURE=json.loads((OUT/'resolution-ui-fixture.json').read_text())
assert os.environ.get('PUB_ENR03_QA')=='1'
# Reuse existing browser helpers, without running either old suite or its fixture writer.
spec=importlib.util.spec_from_file_location('enrollment',ROOT/'scripts/pub-enr02-browser-qa.py');enr=importlib.util.module_from_spec(spec);spec.loader.exec_module(enr)
(OUT/'ui-fixture.json').write_text(json.dumps({'password':FIXTURE['password']}))
spec=importlib.util.spec_from_file_location('c4',ROOT/'scripts/c4-ui01-browser-qa.py');c4=importlib.util.module_from_spec(spec);spec.loader.exec_module(c4)
CHECKS=[];EXTERNAL_ERRORS=[];STAGE='initialization'
FOCUSED=os.environ.get('PUB_ENR03_BROWSER_SMOKE')=='1'
ACT03=os.environ.get('ACT03_QA')=='1'
SHARD=json.loads(os.environ['ACT03_BROWSER_SHARD']) if 'ACT03_BROWSER_SHARD' in os.environ else None
def passed(label):CHECKS.append(label);print('PASS '+label,flush=True)
def fresh_mfa(account):
    # Respect the real replay guard; wait for the next code, never reset a factor.
    while int(c4.sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='"+account+"' AND status='ACTIVE'"))>=int(time.time())//30:time.sleep(.2)
    return c4.totp()
def login(page,origin,email,destination):
    page.goto(origin+'/public/identity/acceso.php?next='+quote(destination,safe=''))
    page.locator('#login-email').fill(email);page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click();page.wait_for_url(origin+destination)
def shape(page):
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth+1')
    assert page.locator('[name=doctor_id],[name=profile_reference],[name=verified_identity_id]').count()==0
    text=page.locator('body').inner_text()
    for raw in ['c4ev_resolution_','res_applicant_','verified_identity_id','C4-EV01','ADM02']:assert raw not in text
def public_phone_hidden(context,origin,profile,label):
    for path in ['/profiles/doctor.php?doctor_id='+profile,'/api/profiles/index.php/public/doctor/'+profile]:
        result=context.request.get(origin+path);assert result.status==200,(path,result.status)
        assert '15557779999' not in result.text(),label+' public phone leak'
    passed(label+'_FREE_public_HTML_JSONLD_metadata_API_no_phone')
def private_contact_ui(browser,origin,width,height,label,index):
    # Explicit gap evidence. Administrative platform contacts are NOT a substitute
    # for the hidden global consultation contact editor requested by the Director.
    suffix='' if index==0 else '_'+str(index);profile='act03_contact_free'+suffix
    context=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=context.new_page()
    try:
        login(page,origin,'act03_contact_owner'+suffix+'@example.invalid','/private-app.php')
        endpoint=origin+'/api/profiles/index.php/private/doctor/'+profile+'/contact-points'
        result=context.request.get(endpoint);assert result.status==200 and result.json()['ok'] is True
        rows=result.json()['data']['items'];contact=next(x for x in rows if x['scope']=='public_profile' and x['type']=='phone')
        flags={key:contact[key] for key in ['scope','is_public','use_for_public_profile','visibility_plan_min','use_for_security']}
        target=endpoint+'/'+str(contact['contact_point_id'])
        for value in ['+15557779998',contact['value']]:
            saved=context.request.patch(target,data={'value':value},headers={'Origin':origin})
            assert saved.status==200 and saved.json()['ok'] is True
            item=saved.json()['data']['contact_point'];assert item['value']==value and all(item[k]==v for k,v in flags.items())
        public_phone_hidden(context,origin,profile,label)
        assert page.locator('#mx-dg-public-contact-list,#mx-dg-contact-create-form').count()==0
        assert page.locator('#mx-admin-phone').count()==1
        page.screenshot(path=str(OUT/(label+'-private-free-contact-gap.png')),full_page=True)
        passed(label+'_FREE_authorized_private_contact_API_read_write_flags_preserved')
        print('GAP '+label+'_global_consultation_editor_absent_administrative_contact_is_separate',flush=True)
    finally:context.close()

def run(origin):
    global STAGE
    with sync_playwright() as pw:
      for engine in (['chromium'] if FOCUSED else ['chromium','webkit']):
        if SHARD and engine!=SHARD[0]:continue
        browser=getattr(pw,engine).launch()
        try:
          for size_index,(width,height) in enumerate([(820,1180)] if FOCUSED else [(1440,900),(1366,768),(820,1180)]):
            if SHARD and width!=SHARD[1]:continue
            for entry in (['claim'] if FOCUSED else ['claim','join']):
              index=(0 if engine=='chromium' else 6)+size_index*2+(0 if entry=='claim' else 1)
              STAGE=f'{engine}_{width}_{entry}';profile=FIXTURE['profiles'][index][0];license=('ACT03-SYNTHETIC-browser-'+str(index)) if ACT03 else 'C4EV-SYNTHETIC-resolution_'+str(index+10)
              doctor=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});staff=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height})
              page=doctor.new_page();review=staff.new_page();errors=[];events=[];
              def response_trace(response):
                  if response.url.endswith('/api/identity/physician-onboarding.php') and response.request.method=='POST':
                      events.append({'action':response.request.post_data_json.get('action'),'status':response.status,'time':time.time()})
                      (OUT/(STAGE+'-responses.json')).write_text(json.dumps(events,indent=2))
              review.on('response',response_trace);page.on('response',response_trace);
              page.on('pageerror',lambda e:errors.append(str(e)+' STACK '+str(e.stack)));review.on('pageerror',lambda e:errors.append(str(e)+' STACK '+str(e.stack)))
              try:
                c4.sql('DELETE FROM auth_rate_limit_buckets')
                path='/public/identity/perfil-medico.php?entry=join' if entry=='join' else '/profiles/doctor.php?doctor_id='+profile
                page.goto(origin+path)
                if ACT03:public_phone_hidden(doctor,origin,profile,STAGE+'_before_source')
                if entry=='claim':page.locator('a[href*="entry=claim"]').click()
                enr.submit(page,'start',lambda:page.locator('[data-enrollment-start]').click());page.wait_for_url('**/public/identity/perfil-medico.php?application=*')
                app=parse_qs(urlsplit(page.url).query)['application'][0];app_path='/public/identity/perfil-medico.php?application='+app
                page.locator('[data-enrollment-register]').click();page.wait_for_load_state('load');email='res_browser_'+str(index)+'@example.invalid'
                page.locator('#create-email').fill(email);page.locator('#create-password').fill(FIXTURE['password']);page.locator('#create-confirm').fill(FIXTURE['password']);page.locator('#terms').check();page.locator('#privacy').check();page.locator('[data-submit]').click()
                expect(page.locator('[data-state-title]')).to_have_text('Solicitud de cuenta enviada')
                message=next(m for m in reversed(json.loads(pathlib.Path(os.environ['MXMED_PREVIEW_NOTIFICATION_FILE']).read_text())) if m['recipient']==email)
                assert message['continuation_path']==app_path
                page.goto(origin+'/public/identity/verificar-correo.php?token='+quote(message['token'])+'&next='+quote(app_path,safe=''));page.locator('[data-submit]').click();expect(page.locator('[data-state-title]')).to_have_text('Correo verificado')
                assert doctor.request.get(origin+'/api/identity/index.php/current-session').status==401
                login(page,origin,email,app_path);enr.submit(page,'bind',lambda:page.locator('[data-enrollment-bind]').click())
                page.wait_for_load_state('networkidle')
                data={'given_names':'Synthetic','first_surname':'Physician','second_surname':'','professional_license':license,'specialty':'Synthetic specialty','state':'Estado sintético','city':'Ciudad sintética'}
                for key,value in data.items():page.locator('[data-enrollment-intake] [name='+key+']').fill(value)
                enr.submit(page,'submit',lambda:page.locator('[data-enrollment-intake] button[value=submit]').click());page.reload();expect(page.locator('[data-enrollment-review-state]')).to_contain_text('Pendiente de revisión')
                shape(page);passed(STAGE+'_public_origin_registration_email_submitted_pending')
                reviewer='res_ui_reviewer_'+str(index);login(review,origin,reviewer+'@example.invalid','/internal/media-review/index.php?section=acreditacion')
                # Selection is an opaque application only; the target is recomputed in SQL.
                result=c4.api(staff,origin,'snapshot',{'view':'staff','application_id':app});assert result.status==200
                if ACT03:assert result.json()['data']['enrollment_review']['application']['source_readiness']['provisioning_available'] is True
                review.reload()
                # Select exact application from the original queue (same public names are deliberately repeated).
                c4.response(review,'snapshot',lambda:review.locator('[data-enrollment-application-id="'+app+'"]').click())
                if ACT03:
                    expect(review.locator('[data-source-panel]')).to_be_visible()
                    expect(review.locator('[data-source-status]')).to_contain_text('Verificación pendiente')
                    def source_begin(action):
                        review.locator('#source-action').select_option(action)
                        review.locator('#source-email').fill(reviewer+'@example.invalid')
                        review.locator('#source-password').fill(FIXTURE['password'])
                        c4.response(review,'source_begin',lambda:review.locator('[data-source-form=begin] button[type=submit]').click())
                        review.locator('#source-mfa').fill(fresh_mfa(reviewer))
                        c4.response(review,'source_decide',lambda:review.locator('[data-source-form=confirm] button[type=submit]').click())
                    source_begin('COLLECT')
                    page.reload();expect(page.locator('[data-source-form=upload]')).to_be_visible()
                    for kind in ['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL']:
                        page.locator('#source-kind').select_option(kind)
                        page.locator('#source-file').set_input_files({'name':'synthetic-source.png','mimeType':'image/png','buffer':c4.png()})
                        page.locator('#source-upload-email').fill(email);page.locator('#source-upload-password').fill(FIXTURE['password'])
                        c4.response(page,'source_upload',lambda:page.locator('[data-source-form=upload] button[type=submit]').click())
                        expect(page.locator('[data-source-files] p')).to_have_count(1 if kind=='OFFICIAL_ID' else 2)
                    c4.response(review,'source_status',lambda:review.locator('[data-source-refresh]').click())
                    expect(review.locator('[data-source-files] button')).to_have_count(2)
                    for button in review.locator('[data-source-files] button').all():
                        c4.response(review,'source_document',lambda:button.click())
                        expect(review.locator('[data-source-dialog]')).to_be_visible();review.locator('[data-source-close]').click()
                    review.locator('#source-action').select_option('AUTHORIZE')
                    facts={'given_names':'Synthetic','first_surname':'Physician','second_surname':'','license_number':license,'professional_area_label':'Synthetic area','institution_name':'Synthetic institution','identity_source':'ACT03 explicitly synthetic identity source','identity_reference':'synthetic-human-reference-identity','credential_source':'ACT03 explicitly synthetic credential source','credential_reference':'synthetic-human-reference-credential','verified_at':time.strftime('%Y-%m-%d %H:%M:%S',time.gmtime())}
                    for field,value in facts.items():review.locator('#source-'+field).fill(value)
                    review.locator('[data-source-facts] [name=relationship_confirmed]').check()
                    source_begin('AUTHORIZE')
                    c4.response(review,'source_execute',lambda:review.locator('[data-source-execute]').click())
                    expect(review.locator('[data-source-status]')).to_contain_text('Verificación realizada')
                    for p,label in [(page,'source-applicant'),(review,'source-reviewer')]:shape(p);p.screenshot(path=str(OUT/(STAGE+'-'+label+'.png')),full_page=True)
                    assert c4.sql("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id=(SELECT account_id FROM auth_physician_enrollment_origins WHERE origin_id='"+app+"')")=='0'
                    public_phone_hidden(doctor,origin,profile,STAGE+'_after_source')
                    passed(STAGE+'_ACT03_original_source_intake_human_verification_writers_no_owner')
                expect(review.locator('[data-enrollment-review-summary]')).to_contain_text('fuentes profesionales verificadas')
                review.locator('#enrollment-review-decision').select_option('APPROVED');review.locator('#enrollment-review-email').fill(reviewer+'@example.invalid');review.locator('#enrollment-review-password').fill(FIXTURE['password']);c4.submit(review,'enrollment_review_begin')
                page.reload();expect(page.locator('[data-enrollment-review-state]')).to_contain_text('En revisión')
                review.locator('#enrollment-review-mfa').fill(fresh_mfa(reviewer));review.locator('[data-onboarding-form=enrollment_review_decide] [name=relationship_confirmed]').check();c4.submit(review,'enrollment_review_decide')
                page.reload();expect(page.locator('[data-enrollment-review-state]')).to_contain_text('aprobada');shape(review)
                row=json.loads(c4.sql("SELECT JSON_OBJECT('origin',initial_origin,'profile',original_profile_doctor_id) FROM auth_physician_enrollment_origins WHERE origin_id='"+app+"'"))
                assert row['origin']==('CLAIM_EXISTING_PROFILE' if entry=='claim' else 'REQUEST_NEW_PROFILE') and row['profile']==(profile if entry=='claim' else None)
                page.locator('[data-enrollment-review-state] a').click();expect(page.locator('[data-enrollment-continuation]')).to_be_visible()
                # No documents or admission from resolution alone; canonical phone still required.
                assert c4.api(doctor,origin,'enrollment_handoff',{'application_id':app}).status==403
                page.locator('#onboarding-phone').fill('+1555'+str(3000000+index));page.locator('#phone-email').fill(email);page.locator('#phone-password').fill(FIXTURE['password']);ans=c4.submit(page,'phone_issue')
                code=json.loads(pathlib.Path(os.environ['C4_UI_QA_SMS']).read_text())[ans['data']['challenge_id']];page.locator('#phone-code').fill(code);c4.submit(page,'phone_verify')
                r=c4.submit(page,'enrollment_handoff')['data'];rid=r['request_id'];assert c4.api(doctor,origin,'enrollment_handoff',{'application_id':app}).json()['data']['request_id']==rid
                if ACT03:
                    assert c4.sql("SELECT COUNT(*) FROM auth_physician_evidence_files WHERE request_id='"+rid+"' AND professional_source_file_id IS NOT NULL")=='2'
                    assert c4.sql("SELECT COUNT(*) FROM auth_physician_evidence_files e JOIN auth_professional_source_files s ON s.file_id=e.professional_source_file_id WHERE e.request_id='"+rid+"' AND e.storage_key=s.storage_key AND e.ciphertext_hash=s.ciphertext_hash")=='2'
                    passed(STAGE+'_original_C4_reuses_two_source_documents_by_reference')
                else:
                    for kind in ['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL']:
                        page.locator('#evidence-kind').select_option(kind);page.locator('#evidence-file').set_input_files({'name':'synthetic.png','mimeType':'image/png','buffer':c4.png()});c4.submit(page,'document_add')
                c4.submit(page,'request_submit');c4.response(review,'snapshot',lambda:review.locator('[data-onboarding-refresh]').click())
                selection=c4.response(review,'snapshot',lambda:review.locator('[data-request-id="'+rid+'"]').click())
                assert selection['data']['request']['request_id']==rid
                expect(review.locator('[data-onboarding-form=review_begin]')).to_be_visible()
                review.locator('#review-decision').select_option('APPROVED');review.locator('#review-email').fill(reviewer+'@example.invalid');review.locator('#review-password').fill(FIXTURE['password']);c4.submit(review,'review_begin')
                for button in review.locator('[data-document-list] button').all():
                    c4.response(review,'document',lambda:button.click());expect(review.locator('[data-evidence-dialog]')).to_be_visible();review.locator('[data-evidence-close]').click()
                review.locator('[data-onboarding-form=review_decide] [name=relationship_confirmed]').check();review.locator('#review-mfa').fill(fresh_mfa(reviewer));c4.submit(review,'review_decide')
                c4.response(page,'snapshot',lambda:page.locator('[data-onboarding-refresh]').click());expect(page.locator('[data-confirmation-section]')).to_be_visible()
                page.locator('#confirmation-email').fill(email);page.locator('#confirmation-password').fill(FIXTURE['password']);nonce=c4.submit(page,'confirmation_issue')['data']['confirmation'];assert nonce not in page.content()
                page.locator('[data-onboarding-form=confirmation_accept] [name=accepted]').check();c4.submit(page,'confirmation_accept');c4.submit(page,'complete');expect(page.locator('[data-owner-pending]')).to_be_visible()
                if ACT03:
                    public_phone_hidden(doctor,origin,profile,STAGE+'_after_accreditation')
                    before=c4.sql('SELECT COUNT(*) FROM platform_audit_events')
                    replay=c4.api(staff,origin,'source_execute',{'view':'staff','application_id':app})
                    assert replay.status==200 and replay.json()['data']['state']=='COMPLETE'
                    assert c4.sql('SELECT COUNT(*) FROM platform_audit_events')==before
                    passed(STAGE+'_source_result_replay_after_C4_no_writes_or_second_authority')
                expect(page.locator('[data-enrollment-continuation-status]')).to_contain_text('acreditación está completada')
                c4.response(review,'snapshot',lambda:review.locator('[data-onboarding-refresh]').click())
                expect(review.locator('[data-enrollment-review-summary]')).to_contain_text('correspondencia del perfil ya fue revisada')
                assert c4.sql("SELECT COUNT(*) FROM auth_account_memberships WHERE account_id=(SELECT account_id FROM auth_physician_enrollment_origins WHERE origin_id='"+app+"')")=='0'
                assert c4.api(doctor,origin,'confirmation_accept',{'request_id':rid,'version':6,'confirmation':nonce,'accepted':True}).status in [403,409]
                assert c4.api(doctor,origin,'snapshot',{'view':'staff'}).status==403
                assert c4.api(doctor,origin,'enrollment_handoff',{'application_id':app,'profile_doctor_id':profile}).status==400
                assert c4.api(staff,origin,'snapshot',{'view':'staff'},csrf=False).status==403
                assert doctor.request.post(origin+'/api/clinical/index.php/prescriptions/synthetic/sign',data={},headers={'Origin':origin}).status in [401,403,404]
                assert doctor.request.get(origin+'/private-app.php').status==403
                for p,label in [(page,'applicant'),(review,'reviewer')]:shape(p);p.screenshot(path=str(OUT/(STAGE+'-'+label+'.png')),full_page=True)
                page.goto(origin+app_path);expect(page.locator('[data-enrollment-review-state]')).to_contain_text('Acreditación completada');shape(page)
                if ACT03 and entry=='claim':private_contact_ui(browser,origin,width,height,STAGE,index)
                if index==0:
                    destination='/public/identity/perfil-medico.php?continue_enrollment='+app;page.goto(origin+destination)
                    assert parse_qs(urlsplit(page.locator('[data-onboarding-login]').get_attribute('href')).query)['next']==[destination]
                    current=doctor.request.get(origin+'/api/identity/index.php/current-session').json();token=current['csrf_token']
                    logout=doctor.request.post(origin+'/api/identity/index.php/logout',data={'csrf_token':token},headers={'Origin':origin,'X-CSRF-Token':token});assert logout.status==200 and logout.json()['state']=='LOGOUT_SUCCESS'
                    page.goto(origin+destination);assert parse_qs(urlsplit(page.url).query)['next']==[destination]
                    page.locator('#login-email').fill(email);page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click();page.wait_for_url(origin+destination)
                    expect(page.locator('[data-request-summary]')).to_contain_text('Acreditación completada')
                    passed('canonical_logout_and_login_preserves_exact_C4_application')
                if ACT03:
                    # Preserve failures outside this bounded source flow. Never
                    # label the overall acceptance PASS when Maps or contact UI fail.
                    google_stack=any('maps.googleapis.com/maps-api-v3/' in error for error in errors)
                    external=[error for error in errors if 'maps.googleapis.com/maps-api-v3/' in error or (google_stack and error.startswith(('Error: Could not load "util".','Error: Could not load "search_impl".')))]
                    assert not [error for error in errors if error not in external],errors
                    if external:
                        EXTERNAL_ERRORS.append({'journey':STAGE,'errors':external})
                        (OUT/'external-script-failures.json').write_text(json.dumps(EXTERNAL_ERRORS,indent=2))
                        print('FAIL_EXTERNAL '+STAGE+'_Google_Maps_module_loading_original_public_profile',flush=True)
                else:assert not errors,errors
                passed(STAGE+'_human_resolution_C4_same_reviewer_documents_confirmation_issuer_no_owner')
              except Exception:
                if ACT03 and 'app' in locals():
                    token=next((c['value'] for c in staff.cookies() if c['name']=='__Host-mxmed_session'),None)
                    if token:
                        diagnostic=subprocess.run(['php',str(ROOT/'modules/identity/tests/support/Act03FailureDiagnostic.php')],input=json.dumps({'session':token,'application':app}),env=os.environ,cwd=ROOT,capture_output=True,text=True)
                        (OUT/(STAGE+'-private-service-diagnostic.json')).write_text(diagnostic.stdout)
                (OUT/(STAGE+'-browser-diagnostic.json')).write_text(json.dumps({'errors':errors,'events':events,'staff_body':review.locator('body').inner_text(),'invalid_staff_controls':review.locator(':invalid').evaluate_all('es=>es.map(e=>({tag:e.tagName,id:e.id,name:e.name,disabled:e.disabled}))'),'body':page.locator('body').inner_text(),'buttons':page.locator('button').evaluate_all('es=>es.map(e=>({text:e.textContent,disabled:e.disabled}))')},indent=2))
                page.screenshot(path=str(OUT/(STAGE+'-failure.png')),full_page=True)
                raise
              finally:doctor.close();staff.close()
        finally:browser.close()

def main():
    os.umask(0o077)
    spec=importlib.util.spec_from_file_location('proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
    with tempfile.TemporaryDirectory(prefix='mxmed-enr03-',dir='/tmp') as d,(OUT/'browser-private-server.log').open('wb') as log:
        folder=pathlib.Path(d);folder.chmod(0o700)
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(folder/'key.pem'),'-out',str(folder/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
        backend=enr.port();tls=proxy.make_server(str(folder/'cert.pem'),str(folder/'key.pem'),port=0,backend_port=backend);tls.expected_host='localhost:'+str(tls.server_port);origin='https://'+tls.expected_host;key=os.urandom(32).hex();tls.governance_transport_key=key.encode()
        env={**os.environ,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_GOVERNANCE_PROXY_KEY':key,'PUB_ENR02_QA_PROOF_KEY':os.urandom(32).hex(),'MXMED_PROFILES_API_BASE':'','MXMED_API_BASE':''}
        php=subprocess.Popen(['php','-S','127.0.0.1:'+str(backend),'-t',str(ROOT),str(ROOT/'modules/identity/tests/support/PublicEnrollmentQaRouter.php')],cwd=ROOT,env=env,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(50):
                if php.poll() is not None:raise RuntimeError('owned PHP failed')
                try:
                    with socket.create_connection(('127.0.0.1',backend),timeout=.1):break
                except OSError:time.sleep(.1)
            else:raise RuntimeError('owned PHP unavailable')
            threading.Thread(target=tls.serve_forever,daemon=True).start();run(origin)
            (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS_SOURCE_FLOW_WITH_CONTACT_UI_GAP' if ACT03 else 'PASS','checks':CHECKS,'journeys':1 if FOCUSED else (2 if SHARD else 12),'engines':[SHARD[0]] if SHARD else (['chromium'] if FOCUSED else ['chromium','webkit']),'viewports':[[SHARD[1],SHARD[2]]] if SHARD else ([[820,1180]] if FOCUSED else [[1440,900],[1366,768],[820,1180]]),'scope':'SHARD' if SHARD else ('FOCUSED_RETURN' if FOCUSED else 'FULL'),'original_templates':True,'normal_runtime_active':False,'overall_acceptance':'PARTIAL' if ACT03 else 'PASS','source_flow_result':'PASS','external_script_failures':EXTERNAL_ERRORS,'required_contact_ui_ready':False,'contact_ui_gap':'GLOBAL_CONSULTATION_EDITOR_ABSENT_ADMINISTRATIVE_CONTACT_SEPARATE'},indent=2))
        finally:
            tls.shutdown();tls.server_close()
            if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
            (OUT/'enrollment-race.json').unlink(missing_ok=True)
    print('ACT03_SOURCE_FLOW_BROWSER=PASS; OVERALL_ACCEPTANCE=PARTIAL' if ACT03 else 'PUB_ENR03_ORIGINAL_BROWSER=PASS',flush=True)

if __name__=='__main__':
    try:main()
    except Exception:
        (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('PUB_ENR03_ORIGINAL_BROWSER=FAIL STAGE='+STAGE,flush=True);raise SystemExit(1)
