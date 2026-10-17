#!/usr/bin/env python3
"""Original URLs/components, canonical HTTP/services, owned SQL/Valkey/TLS only."""
import os, pathlib, json, socket, subprocess, ssl, threading, signal, tempfile, importlib.util
import time, base64, hashlib, hmac, struct, zlib, datetime, traceback
from playwright.sync_api import sync_playwright, expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
CONN=json.loads(pathlib.Path(os.environ['GOV03B_QA_CONNECTION']).read_text())
assert CONN['db'].startswith('mxmed_gate4d_preview_c4ui_')
FIXTURE=json.loads((OUT/'ui-fixture.json').read_text())
RESULTS=[]
STAGE='initialization'
FORM=lambda action: '[data-onboarding-form="'+action+'"]'

def passed(label):
    RESULTS.append(label);print('PASS '+label,flush=True)

def sql(text):
    env={**os.environ,'MYSQL_PWD':CONN['password']}
    r=subprocess.run(['mysql','-h127.0.0.1','-P3306','-u'+CONN['user'],'-N','-B','-r',CONN['db']],input=text,text=True,capture_output=True,env=env)
    if r.returncode: raise RuntimeError('owned SQL probe failed')
    return r.stdout.strip()

def port():
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));return sock.getsockname()[1]

def totp():
    digest=hmac.new(b'e'*20,struct.pack('>Q',int(time.time())//30),hashlib.sha1).digest();offset=digest[-1]&15
    return str((struct.unpack('>I',digest[offset:offset+4])[0]&0x7fffffff)%1000000).zfill(6)

def png():
    def chunk(kind,content):return struct.pack('>I',len(content))+kind+content+struct.pack('>I',zlib.crc32(kind+content)&0xffffffff)
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',1,1,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b'\x00\x00\xad\xc1'))+chunk(b'IEND',b'')

def redis(*args):
    values=[str(v).encode() for v in args]
    with socket.create_connection(('127.0.0.1',int(os.environ['MXMED_SESSION_STORE_PORT'])),timeout=5) as stream:
        stream.sendall(b'*'+str(len(values)).encode()+b'\r\n'+b''.join(b'$'+str(len(v)).encode()+b'\r\n'+v+b'\r\n' for v in values));reader=stream.makefile('rb');line=reader.readline()
        if line.startswith(b'$'):return reader.read(int(line[1:-2]))
        return line

def expire(context):
    token=next(c['value'] for c in context.cookies() if c['name']=='__Host-mxmed_session')
    digest=hmac.new(os.environ['MXMED_PREVIEW_PEPPER'].encode(),token.encode(),hashlib.sha256).hexdigest();key='mxmed:gate4d:preview:session:'+digest
    record=json.loads(redis('GET',key));now=datetime.datetime.now(datetime.timezone.utc)
    date=lambda d:d.isoformat(timespec='seconds')
    record.update(created_at=date(now-datetime.timedelta(hours=2)),last_seen_at=date(now-datetime.timedelta(hours=2)),expires_at=date(now-datetime.timedelta(hours=1)),absolute_expires_at=date(now+datetime.timedelta(hours=10)))
    redis('SET',key,json.dumps(record),'EX',120)

def response(page,action,fn,expected=200):
    with page.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.method=='POST' and r.request.post_data_json.get('action')==action) as event:fn()
    actual=event.value
    assert actual.status==expected,(action,actual.status,expected)
    return actual.json() if actual.headers.get('content-type','').startswith('application/json') else actual

def submit(page,action,expected=200):return response(page,action,lambda:page.locator(FORM(action)).locator('button[type=submit]').click(),expected)

def login(page,origin,email,staff=False):
    dest='/internal/media-review/index.php?section=acreditacion' if staff else '/public/identity/perfil-medico.php'
    from urllib.parse import quote
    page.goto(origin+'/public/identity/acceso.php?next='+quote(dest,safe=''))
    page.locator('#login-email').fill(email);page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click()
    page.wait_for_url(origin+dest);expect(page.locator('[data-onboarding]')).to_be_visible()

def register(page,origin,index):
    email='c4ui_physician_'+str(index)+'@example.invalid'
    page.goto(origin+'/public/identity/crear-cuenta.php');page.locator('#create-email').fill(email);page.locator('#create-password').fill(FIXTURE['password']);page.locator('#create-confirm').fill(FIXTURE['password']);page.locator('#terms').check();page.locator('#privacy').check();page.locator('[data-submit]').click()
    expect(page.locator('[data-state-title]')).to_have_text('Solicitud de cuenta enviada');expect(page.locator('[data-onboarding-next]')).to_be_visible()
    messages=json.loads(pathlib.Path(os.environ['MXMED_PREVIEW_NOTIFICATION_FILE']).read_text());token=next(r['token'] for r in reversed(messages) if r['recipient']==email)
    page.goto(origin+'/public/identity/verificar-correo.php?token='+token);page.locator('[data-submit]').click();expect(page.locator('[data-state-title]')).to_have_text('Correo verificado');page.locator('[data-onboarding-next] a').click()
    page.locator('#login-email').fill(email);page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click();page.wait_for_url(origin+'/public/identity/perfil-medico.php')
    return email

def prepare(page,origin,index):
    # Independent browser cases share only an owned synthetic DB. Reset their
    # rate-limit fixture between cases, never within the OTP/security assertions.
    # No productive policy, normal-local bucket or persistent environment changes.
    sql('DELETE FROM auth_rate_limit_buckets')
    email=register(page,origin,index);expect(page.locator('[data-phone-status]')).to_have_text('Verifica tu teléfono para continuar.')
    page.locator('#onboarding-phone').fill('+1555'+str(1000000+index));page.locator('#phone-email').fill(email);page.locator('#phone-password').fill(FIXTURE['password']);answer=submit(page,'phone_issue')
    code=json.loads(pathlib.Path(os.environ['C4_UI_QA_SMS']).read_text())[answer['data']['challenge_id']]
    wrong=('1' if code[0]!='1' else '2')+code[1:];page.locator('#phone-code').fill(wrong);submit(page,'phone_verify',422);assert 'Teléfono verificado.' not in page.locator('[data-phone-status]').inner_text()
    page.locator('#phone-code').fill(code);submit(page,'phone_verify');expect(page.locator('[data-phone-status]')).to_have_text('Teléfono verificado.')
    # UX-ALIGN01 removes internal-ID entry. New public origin persistence remains
    # unavailable: create ONLY the owned synthetic draft through the native service,
    # after genuine browser phone verification, then test original UI resume.
    assert page.locator('#profile-reference,[name=profile_reference]').count()==0
    setup=subprocess.run(['php',str(ROOT/'modules/identity/tests/PhysicianOnboardingResumeFixture.php'),str(index)],capture_output=True,text=True,env=os.environ)
    if setup.returncode:raise RuntimeError('owned canonical resume fixture failed: '+setup.stderr[-1500:])
    created=json.loads(setup.stdout);assert created['fixture_setup_only'] is True;request_id=created['request_id']
    response(page,'snapshot',lambda:page.locator('[data-onboarding-refresh]').click())
    expect(page.locator('[data-request-panel]')).to_be_visible()
    if index==0:
        page.locator('#evidence-file').set_input_files({'name':'not-an-id.txt','mimeType':'text/plain','buffer':b'SYNTHETIC invalid evidence'});submit(page,'document_add',422)
        expect(page.locator('[data-document-list]>div')).to_have_count(0)
    for kind in ['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL']:
        page.locator('#evidence-kind').select_option(kind);page.locator('#evidence-file').set_input_files({'name':'synthetic-evidence.png','mimeType':'image/png','buffer':png()});submit(page,'document_add')
        expect(page.locator('#evidence-file')).to_have_value('')
    expect(page.locator('[data-document-list]>div')).to_have_count(2);submit(page,'request_submit');expect(page.locator('[data-request-summary]')).to_contain_text('pendiente de revisión')
    return email,request_id

def staff_review(page,origin,index,request_id,reject=False):
    login(page,origin,'ui_reviewer_'+str(index)+'@example.invalid',True)
    response(page,'snapshot',lambda:page.locator('[data-request-id="'+request_id+'"]').click())
    expect(page.locator('[data-request-summary]')).to_contain_text(FIXTURE['profiles'][index])
    if reject:page.locator('#review-decision').select_option('REJECTED')
    page.locator('#review-email').fill('ui_reviewer_'+str(index)+'@example.invalid');page.locator('#review-password').fill(FIXTURE['password']);submit(page,'review_begin')
    for button in page.locator('[data-document-list] button').all():
        with page.expect_response(lambda r:r.url.endswith('/api/identity/physician-onboarding.php') and r.request.post_data_json.get('action')=='document') as event:button.click()
        result=event.value;assert result.status==200,('document_status',result.status);assert 'no-store' in result.headers.get('cache-control',''),('document_cache',result.headers.get('cache-control'));assert result.headers.get('x-content-type-options')=='nosniff','document_nosniff'
        direct=page.context.request.post(origin+'/api/identity/physician-onboarding.php',data=result.request.post_data_json,headers={'Origin':origin,'X-CSRF-Token':result.request.post_data_json['csrf_token']})
        assert direct.status==200 and direct.body()==png(),('protected_document_bytes',direct.status,len(direct.body()),direct.headers.get('content-type'))
        expect(page.locator('[data-evidence-dialog]')).to_be_visible();expect(page.locator('[data-evidence-content] img')).to_be_visible();assert page.locator('[data-evidence-content] img').evaluate('(e)=>e.naturalWidth')==1
        page.locator('[data-evidence-close]').click();assert page.locator('[data-evidence-content] img').count()==0
    if not reject:page.locator(FORM('review_decide')+' [name=relationship_confirmed]').check()
    page.locator('#review-mfa').fill(totp());submit(page,'review_decide')
    expect(page.locator('[data-request-summary]')).to_contain_text('rechazada' if reject else 'Falta la confirmación del médico')

def api(context,origin,action,data=None,csrf=True,request_origin=None):
    current=context.request.get(origin+'/api/identity/index.php/current-session').json();token=current.get('csrf_token','') if csrf else ''
    return context.request.post(origin+'/api/identity/physician-onboarding.php',data={'action':action,'csrf_token':token,**(data or {})},headers={'Origin':request_origin or origin,'X-CSRF-Token':token})

def run_suite(origin,mode):
    global STAGE
    with sync_playwright() as playwright:
        for engine in ['chromium','webkit']:
            browser=getattr(playwright,engine).launch(headless=True)
            try:
                for size_index,(width,height) in enumerate([(1440,900),(1366,768),(820,1180)]):
                    index=(0 if engine=='chromium' else 3)+size_index;STAGE=engine+'_'+str(width)+'_claim'
                    doctor=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});staff=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height})
                    page=doctor.new_page();review=staff.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));review.on('pageerror',lambda e:errors.append(str(e)))
                    try:
                        email,request_id=prepare(page,origin,index);passed(STAGE+'_registration_email_phone_fixture_draft_UI_resume_documents_submit')
                        assert api(doctor,origin,'snapshot',csrf=False).status==403
                        assert api(doctor,origin,'snapshot',request_origin='https://other.invalid').status==403
                        assert api(doctor,origin,'document',{'view':'staff','request_id':request_id,'version':4,'file_id':'none'}).status==403
                        clinical=doctor.request.post(origin+'/api/clinical/index.php/prescriptions/synthetic/sign',data={},headers={'Origin':origin})
                        assert clinical.status in [401,403,404],('clinical_sign_denied',clinical.status)
                        operators=doctor.request.get(origin+'/api/profile-operators/index.php/overview')
                        assert operators.status==503 and operators.json().get('ok') is False and 'pendiente de habilitación' in operators.json().get('message','')
                        passed(STAGE+'_csrf_origin_staff_only_document_clinical_operator_denials')
                        STAGE=engine+'_'+str(width)+'_review';staff_review(review,origin,index,request_id);passed(STAGE+'_one_staff_scoped_document_MFA_decision')
                        response(page,'snapshot',lambda:page.locator('[data-onboarding-refresh]').click());expect(page.locator('[data-confirmation-section]')).to_be_visible()
                        page.locator('#confirmation-email').fill(email);page.locator('#confirmation-password').fill(FIXTURE['password']);nonce=submit(page,'confirmation_issue')['data']['confirmation']
                        assert nonce not in page.content() and nonce not in page.url
                        if index==0:
                            sql("UPDATE auth_physician_evidence_requests SET confirmation_expires_at=UTC_TIMESTAMP()-INTERVAL 1 SECOND WHERE request_id='"+request_id+"'")
                            page.locator(FORM('confirmation_accept')+' [name=accepted]').check();submit(page,'confirmation_accept',409);expect(page.locator('[data-onboarding-feedback]')).to_contain_text('venció');assert page.locator('[data-owner-pending]').is_hidden()
                            page.locator('#confirmation-email').fill(email);page.locator('#confirmation-password').fill(FIXTURE['password']);nonce=submit(page,'confirmation_issue')['data']['confirmation'];passed('expired_confirmation_requires_new_authorization_no_false_success')
                        page.locator(FORM('confirmation_accept')+' [name=accepted]').check();submit(page,'confirmation_accept');expect(page.locator(FORM('complete'))).to_be_visible()
                        if index==0:
                            sql('UPDATE gov03b_qa_faults SET enabled=2');submit(page,'complete',503);expect(page.locator('[data-owner-pending]')).to_be_hidden();assert sql('SELECT COUNT(*) FROM profiles_physician_admissions')=='0'
                            sql('UPDATE gov03b_qa_faults SET enabled=0');passed('native_audit_failure_no_success_and_safe_retry')
                        submit(page,'complete');expect(page.locator('[data-owner-pending]')).to_be_visible();assert sql("SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner'")=='0';passed(engine+'_'+str(width)+'_independent_confirmation_admission_no_automatic_owner')
                        for p in [page,review]:
                            assert p.evaluate('document.documentElement.scrollWidth <= window.innerWidth'),engine+' responsive overflow'
                            p.screenshot(path=str(OUT/(engine+'-'+str(width)+('-staff' if p==review else '-physician')+'.png')),full_page=True)
                        assert not errors,errors
                        # HTTP replay cannot reuse the consumed confirmation or change account/profile.
                        version=int(sql("SELECT version FROM auth_physician_evidence_requests WHERE request_id='"+request_id+"'"))
                        assert api(doctor,origin,'confirmation_accept',{'request_id':request_id,'version':version,'confirmation':nonce,'accepted':True}).status in [403,409]
                        assert api(doctor,origin,'complete',{'request_id':request_id,'version':version,'account_id':'another-account'}).status==400
                        # Historical evidence remains protected after approval and logout/expiry.
                        doc_button=review.locator('[data-document-list] button').first;response(review,'document',lambda:doc_button.click(),409)
                        expect(review.locator('[data-onboarding-feedback]')).to_contain_text('La solicitud cambió')
                        response(review,'snapshot',lambda:review.locator('[data-onboarding-refresh]').click())
                        response(review,'document',lambda:review.locator('[data-document-list] button').first.click());expect(review.locator('[data-evidence-dialog]')).to_be_visible()
                        if size_index==0:
                            expire(staff);response(review,'document',lambda:review.locator('[data-evidence-close]').click() or review.locator('[data-document-list] button').first.click(),401)
                            expect(review.locator('[data-request-panel]')).to_be_hidden();assert review.locator('[data-evidence-content] img').count()==0;passed(engine+'_expired_session_removes_preview_and_denies_read')
                        else:
                            review.locator('[data-evidence-close]').click();review.locator('[data-onboarding-logout]').click();expect(review.locator('[data-onboarding-login]')).to_be_visible();expect(review.locator('[data-request-panel]')).to_be_hidden();assert review.locator('[data-evidence-content] img').count()==0;passed(engine+'_'+str(width)+'_logout_cleans_protected_UI')
                        mode.write_text('pending');page.reload();expect(page.locator('[data-onboarding-status]')).to_contain_text('pendiente de habilitación')
                        assert page.locator('input[type=file]:enabled').count()==0 and page.locator('fieldset:enabled').count()==0
                        page.screenshot(path=str(OUT/(engine+'-'+str(width)+'-pending.png')),full_page=True);assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                        passed(engine+'_'+str(width)+'_same_original_UI_pending_disabled');mode.write_text('available')
                    finally:mode.write_text('available');doctor.close();staff.close()
                # Unauthenticated original route redirects to original login; no shadow page.
                index=6 if engine=='chromium' else 7;STAGE=engine+'_rejection_and_denials'
                extra=browser.new_context(ignore_https_errors=True,viewport={'width':820,'height':1180});extra_staff=browser.new_context(ignore_https_errors=True,viewport={'width':820,'height':1180})
                try:
                    applicant=extra.new_page();reviewer=extra_staff.new_page();_,rid=prepare(applicant,origin,index)
                    staff_review(reviewer,origin,index,rid,reject=True)
                    response(applicant,'snapshot',lambda:applicant.locator('[data-onboarding-refresh]').click())
                    expect(applicant.locator('[data-request-summary]')).to_contain_text('rechazada');expect(applicant.locator('[data-confirmation-section]')).to_be_hidden();assert applicant.locator('[data-owner-pending]').is_hidden()
                    victim=sql("SELECT request_id FROM auth_physician_evidence_requests WHERE profile_doctor_id='"+FIXTURE['profiles'][0]+"'")
                    assert api(extra,origin,'snapshot',{'request_id':victim}).status==403
                    assert api(extra,origin,'confirmation_issue',{'request_id':rid,'version':1,'email':'x@example.invalid','password':'wrong'}).status==409
                    foreign_file=sql("SELECT file_id FROM auth_physician_evidence_files WHERE request_id='"+victim+"' LIMIT 1")
                    current_version=int(sql("SELECT version FROM auth_physician_evidence_requests WHERE request_id='"+rid+"'"))
                    denied=api(extra_staff,origin,'document',{'view':'staff','request_id':rid,'version':current_version,'file_id':foreign_file})
                    assert denied.status==403 and denied.headers.get('content-type','').startswith('application/json')
                    passed(engine+'_rejected_application_wrong_account_stale_version_denials')
                finally:extra.close();extra_staff.close()
                anonymous=browser.new_context(ignore_https_errors=True);p=anonymous.new_page();p.goto(origin+'/public/identity/perfil-medico.php');assert '/public/identity/acceso.php?next=' in p.url
                assert anonymous.request.get(origin+'/api/identity/physician-onboarding.php').status==405
                assert anonymous.request.post(origin+'/api/identity/physician-onboarding.php',data={'action':'snapshot'},headers={'Origin':origin}).status==401
                passed(engine+'_anonymous_and_public_document_URL_denied');anonymous.close()
                unprivileged=browser.new_context(ignore_https_errors=True);p=unprivileged.new_page()
                from urllib.parse import quote
                destination='/internal/media-review/index.php?section=acreditacion';p.goto(origin+'/public/identity/acceso.php?next='+quote(destination,safe=''))
                p.locator('#login-email').fill('ui_no_grant@example.invalid');p.locator('#login-password').fill(FIXTURE['password']);p.locator('[data-submit]').click();p.wait_for_url(origin+destination)
                expect(p.get_by_role('heading',name='Acceso restringido')).to_be_visible();assert p.locator('[data-request-list]').count()==0
                assert api(unprivileged,origin,'snapshot',{'view':'staff'}).status==403;passed(engine+'_existing_internal_workspace_requires_explicit_grant');unprivileged.close()
            finally:browser.close()

def main():
    global STAGE
    source_files=[*ROOT.glob('modules/identity/Accreditation/*.php'),
        *[ROOT/p for p in ['api/identity/physician-onboarding.php','public/identity/acceso.php','public/identity/crear-cuenta.php',
            'public/identity/verificar-correo.php','public/identity/perfil-medico.php','public/identity/identity-access.js',
            'public/identity/identity-access.css','private-app.php','internal/media-review/index.php',
            'partials/identity/physician-onboarding.php','assets/css/physician-onboarding.css','assets/js/identity/physician-onboarding.js',
            'scripts/normal-local-https-proxy.py','scripts/c4-ui01-browser-qa.py','scripts/gov03b-disposable-qa.py',
            'modules/identity/tests/support/C4UiQaRouter.php','modules/identity/tests/PhysicianOnboardingUiFixture.php',
            'modules/identity/tests/PhysicianOnboardingResumeFixture.php']]]
    source_manifest={str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in source_files}
    spec=importlib.util.spec_from_file_location('c4_ui_proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
    with tempfile.TemporaryDirectory(prefix='mxmed-c4-ui-',dir='/tmp') as d,(OUT/'ui-private-server.log').open('wb') as log:
        folder=pathlib.Path(d);folder.chmod(0o700);mode=folder/'mode';mode.write_text('available')
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(folder/'key.pem'),'-out',str(folder/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
        backend=port();tls=proxy.make_server(str(folder/'cert.pem'),str(folder/'key.pem'),port=0,backend_port=backend);tls.expected_host='localhost:'+str(tls.server_port);origin='https://'+tls.expected_host
        key='c4-ui-owned-transport-'+os.urandom(32).hex();tls.governance_transport_key=key.encode()
        config={**os.environ,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_GOVERNANCE_PROXY_KEY':key,'C4_UI_QA_MODE_FILE':str(mode)}
        php=subprocess.Popen(['php','-S','127.0.0.1:'+str(backend),'-t',str(ROOT),str(ROOT/'modules/identity/tests/support/C4UiQaRouter.php')],cwd=ROOT,env=config,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(50):
                if php.poll() is not None:raise RuntimeError('own PHP failed')
                try:
                    with socket.create_connection(('127.0.0.1',backend),timeout=.1):break
                except OSError:time.sleep(.1)
            else:raise RuntimeError('own PHP unavailable')
            threading.Thread(target=tls.serve_forever,daemon=True).start();run_suite(origin,mode)
            assert sql("SELECT COUNT(*) FROM auth_account_memberships WHERE role_code='owner'")=='0'
            assert sql('SELECT COUNT(*) FROM profiles_physician_admissions')=='6'
            passed('six_original_UI_flows_native_admissions_and_bindings_without_membership_writes')
            assert source_manifest=={str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in source_files},'source changed during accepted browser run'
            (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS','checks':RESULTS,'engines':['chromium','webkit'],'viewports':[[1440,900],[1366,768],[820,1180]],'original_components':True,'real_activation':False,'source_manifest':source_manifest},indent=2))
        finally:
            tls.shutdown();tls.server_close()
            if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
            (OUT/'ui-fixture.json').unlink(missing_ok=True)
    print('C4_UI01_ORIGINAL_BROWSER_QA=PASS',flush=True)

if __name__=='__main__':
    try:main()
    except Exception:
        (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('C4_UI01_ORIGINAL_BROWSER_QA=FAIL; stage='+STAGE+'; inspect private evidence',flush=True);raise SystemExit(1)
