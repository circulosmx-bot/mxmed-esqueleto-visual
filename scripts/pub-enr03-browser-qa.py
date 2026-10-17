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
CHECKS=[];STAGE='initialization'
FOCUSED=os.environ.get('PUB_ENR03_BROWSER_SMOKE')=='1'
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
def run(origin):
    global STAGE
    with sync_playwright() as pw:
      for engine in (['chromium'] if FOCUSED else ['chromium','webkit']):
        browser=getattr(pw,engine).launch()
        try:
          for size_index,(width,height) in enumerate([(820,1180)] if FOCUSED else [(1440,900),(1366,768),(820,1180)]):
            for entry in (['claim'] if FOCUSED else ['claim','join']):
              index=(0 if engine=='chromium' else 6)+size_index*2+(0 if entry=='claim' else 1)
              STAGE=f'{engine}_{width}_{entry}';profile=FIXTURE['profiles'][index][0];license='C4EV-SYNTHETIC-resolution_'+str(index+10)
              doctor=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});staff=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height})
              page=doctor.new_page();review=staff.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));review.on('pageerror',lambda e:errors.append(str(e)))
              try:
                c4.sql('DELETE FROM auth_rate_limit_buckets')
                path='/public/identity/perfil-medico.php?entry=join' if entry=='join' else '/profiles/doctor.php?doctor_id='+profile
                page.goto(origin+path)
                if entry=='claim':page.locator('a[href*="entry=claim"]').click()
                enr.submit(page,'start',lambda:page.locator('[data-enrollment-start]').click());page.wait_for_url('**/public/identity/perfil-medico.php?application=*')
                app=parse_qs(urlsplit(page.url).query)['application'][0];app_path='/public/identity/perfil-medico.php?application='+app
                page.locator('[data-enrollment-register]').click();email='res_browser_'+str(index)+'@example.invalid'
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
                review.reload()
                # Select exact application from the original queue (same public names are deliberately repeated).
                c4.response(review,'snapshot',lambda:review.locator('[data-enrollment-application-id="'+app+'"]').click())
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
                for kind in ['OFFICIAL_ID','PROFESSIONAL_CREDENTIAL']:
                    page.locator('#evidence-kind').select_option(kind);page.locator('#evidence-file').set_input_files({'name':'synthetic.png','mimeType':'image/png','buffer':c4.png()});c4.submit(page,'document_add')
                c4.submit(page,'request_submit');c4.response(review,'snapshot',lambda:review.locator('[data-onboarding-refresh]').click())
                c4.response(review,'snapshot',lambda:review.locator('[data-request-id="'+rid+'"]').click())
                review.locator('#review-decision').select_option('APPROVED');review.locator('#review-email').fill(reviewer+'@example.invalid');review.locator('#review-password').fill(FIXTURE['password']);c4.submit(review,'review_begin')
                for button in review.locator('[data-document-list] button').all():
                    c4.response(review,'document',lambda:button.click());expect(review.locator('[data-evidence-dialog]')).to_be_visible();review.locator('[data-evidence-close]').click()
                review.locator('[data-onboarding-form=review_decide] [name=relationship_confirmed]').check();review.locator('#review-mfa').fill(fresh_mfa(reviewer));c4.submit(review,'review_decide')
                c4.response(page,'snapshot',lambda:page.locator('[data-onboarding-refresh]').click());expect(page.locator('[data-confirmation-section]')).to_be_visible()
                page.locator('#confirmation-email').fill(email);page.locator('#confirmation-password').fill(FIXTURE['password']);nonce=c4.submit(page,'confirmation_issue')['data']['confirmation'];assert nonce not in page.content()
                page.locator('[data-onboarding-form=confirmation_accept] [name=accepted]').check();c4.submit(page,'confirmation_accept');c4.submit(page,'complete');expect(page.locator('[data-owner-pending]')).to_be_visible()
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
                if index==0:
                    destination='/public/identity/perfil-medico.php?continue_enrollment='+app;page.goto(origin+destination)
                    assert parse_qs(urlsplit(page.locator('[data-onboarding-login]').get_attribute('href')).query)['next']==[destination]
                    current=doctor.request.get(origin+'/api/identity/index.php/current-session').json();token=current['csrf_token']
                    logout=doctor.request.post(origin+'/api/identity/index.php/logout',data={'csrf_token':token},headers={'Origin':origin,'X-CSRF-Token':token});assert logout.status==200 and logout.json()['state']=='LOGOUT_SUCCESS'
                    page.goto(origin+destination);assert parse_qs(urlsplit(page.url).query)['next']==[destination]
                    page.locator('#login-email').fill(email);page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click();page.wait_for_url(origin+destination)
                    expect(page.locator('[data-request-summary]')).to_contain_text('Acreditación completada')
                    passed('canonical_logout_and_login_preserves_exact_C4_application')
                assert not errors,errors
                passed(STAGE+'_human_resolution_C4_same_reviewer_documents_confirmation_issuer_no_owner')
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
            (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS','checks':CHECKS,'journeys':1 if FOCUSED else 12,'engines':['chromium'] if FOCUSED else ['chromium','webkit'],'viewports':[[820,1180]] if FOCUSED else [[1440,900],[1366,768],[820,1180]],'scope':'FOCUSED_RETURN' if FOCUSED else 'FULL','original_templates':True,'normal_runtime_active':False},indent=2))
        finally:
            tls.shutdown();tls.server_close()
            if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
            (OUT/'enrollment-race.json').unlink(missing_ok=True)
    print('PUB_ENR03_ORIGINAL_BROWSER=PASS',flush=True)

if __name__=='__main__':
    try:main()
    except Exception:
        (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('PUB_ENR03_ORIGINAL_BROWSER=FAIL STAGE='+STAGE,flush=True);raise SystemExit(1)
