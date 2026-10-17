#!/usr/bin/env python3
"""Original staff/public components, real owned SQL/writer, reauth/MFA, recovery.
Only the owning application suite can supply this newly disposable environment.
"""
import os,pathlib,json,subprocess,socket,signal,threading,tempfile,importlib.util,time,traceback,uuid
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];OUT=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
spec=importlib.util.spec_from_file_location('cor01',ROOT/'scripts/pub-cor01-browser-qa.py');cor=importlib.util.module_from_spec(spec);spec.loader.exec_module(cor)
assert os.environ.get('PUB_COR02_QA')=='1' and cor.CONN['db'].startswith('mxmed_gate4d_preview_cor02_')
FIXTURE=json.loads((OUT/'application-fixture.json').read_text());CHECKS=[];STAGE='setup'
PRIVATE=cor.PRIVATE;WORKSPACE=cor.WORKSPACE
def passed(label):CHECKS.append(label);print('PASS '+label,flush=True)
def persisted(f):
    return cor.sql("SELECT normalized_value FROM doctor_contact_points WHERE contact_point_id="+f['contact'])
def public_value(context,origin,f):
    return context.request.get(origin+'/api/profiles/public/doctor/'+f['profile']).json()['data']['contact']['phone']
def result_count(f):return cor.sql("SELECT COUNT(*) FROM profiles_correction_application_results WHERE report_id='"+f['report_id']+"'")
def run(origin):
    global STAGE
    with sync_playwright() as pw:
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch()
        try:
          for i,(w,h) in enumerate([(1440,900),(1366,768),(820,1180)]):
            n=i+(0 if engine=='chromium' else 3);STAGE=f'{engine}_{w}_{h}';f=FIXTURE['reports'][n];user=FIXTURE['reviewers'][n]
            public=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});staff=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h})
            for ctx in [public,staff]:ctx.route('**/*',lambda route:route.continue_() if route.request.url.startswith(origin+'/') else route.fulfill(status=200,body='',content_type='text/html' if route.request.resource_type=='document' else 'text/plain'))
            page=public.new_page();review=staff.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));review.on('pageerror',lambda e:errors.append(str(e)))
            try:
                page.goto(origin+'/profiles/doctor.php?doctor_id='+f['profile']);assert public_value(public,origin,f)==f['old']
                expect(page.get_by_text('Teléfono: '+f['old'],exact=True)).to_be_visible()
                assert page.locator('[data-correction-report]').count()==0  # paid projection fixture, no gate expansion
                cor.login(review,origin,user);cor.response(review,PRIVATE,lambda:review.locator('[data-report-id="'+f['report_id']+'"]').click())
                app=review.locator('[data-correction-application]');expect(app).to_be_visible();expect(app.locator('[data-application-state]')).to_have_text('Aceptado, pendiente de aplicar')
                app.locator('[data-application-start]').click();target=app.locator('#application-target')
                assert target.input_value()=='' and app.locator('#application-proposed').input_value()==''
                target.select_option(f['contact']);expect(app.locator('[data-application-current]')).to_have_text(f['old'])
                proposed=f['old'] if n==3 else f['new']
                app.locator('#application-proposed').fill(proposed);app.locator('#application-references').fill('synthetic-browser-verification-'+str(n));app.locator('#application-reason').fill('Teléfono público comprobado mediante fuente sintética independiente.')
                app.locator('[name=application_facts_confirmed]').check();app.locator('#application-email').fill(user+'@example.invalid');app.locator('#application-password').fill(FIXTURE['password'])
                app.locator('#application-proposed').focus();review.keyboard.press('Tab');assert app.locator('#application-references').evaluate('e=>e===document.activeElement')
                with review.expect_response(lambda r:r.url.endswith(PRIVATE) and r.request.method=='POST' and r.request.post_data_json.get('action')=='application_begin') as event:app.locator('[data-application-begin] button').click()
                reply=event.value;assert reply.status==200;begin=reply.json()['data'];command=reply.request.post_data_json['command'];cid=begin['command_id']
                assert begin['state']=='REQUESTED' and persisted(f)==f['old'] and result_count(f)=='0'
                assert app.locator('#application-password').input_value()==''
                expect(app.locator('[data-application-mfa]')).to_be_visible();expect(app.locator('[data-application-comparison]')).to_contain_text(proposed)
                cor.shape(review);review.screenshot(path=str(OUT/(STAGE+'-proposed.png')),full_page=True)
                if n==0:
                    assert cor.staff_api(staff,origin,'application_begin',{'command':command,'email':user+'@example.invalid','password':FIXTURE['password']}).json()['data']['replayed']
                    changed={**command,'proposed_value':'+15550000009'}
                    assert cor.staff_api(staff,origin,'application_begin',{'command':changed,'email':user+'@example.invalid','password':FIXTURE['password']}).status==409
                    assert cor.staff_api(staff,origin,'application_execute',{'command_id':cid},csrf=False).status==403
                    assert cor.staff_api(staff,origin,'application_execute',{'command_id':cid,'patch':{'profile_status':'hidden'}}).status==400
                    assert cor.staff_api(staff,origin,'application_execute',{'command_id':cid}).status==403
                    bad={**command,'command_id':str(uuid.uuid4()),'profile_id':'app_profile_21'}
                    assert cor.staff_api(staff,origin,'application_begin',{'command':bad,'email':user+'@example.invalid','password':FIXTURE['password']}).status==403
                    passed('HTTP_exact_target_capability_CSRF_replay_no_generic_PATCH_no_premature_execute')
                app.locator('#application-code').fill(cor.totp());cor.response(review,PRIVATE,lambda:app.locator('[data-application-mfa] button').click())
                expect(app.locator('[data-application-confirm]')).to_be_visible()
                assert persisted(f)==f['old'] and public_value(public,origin,f)==f['old'] and result_count(f)=='0'
                expect(app.locator('[data-application-state]')).to_have_text('Cambio autorizado, pendiente de aplicar')
                passed(STAGE+'_comparison_reauth_MFA_keeps_original_public_value_before_commit')
                if n in [0,1]:
                    fault=4 if n==0 else 5;cor.sql('UPDATE gov03b_qa_faults SET enabled='+str(fault))
                    cor.response(review,PRIVATE,lambda:app.locator('[data-application-execute]').click(),503 if fault==4 else 422)
                    expect(app.locator('[data-application-message]')).to_contain_text('No se confirmó')
                    assert persisted(f)==f['old'] and public_value(public,origin,f)==f['old'] and result_count(f)=='0'
                    cor.sql('UPDATE gov03b_qa_faults SET enabled=0')
                    cor.response(review,PRIVATE,lambda:app.locator('[data-application-status]').click());expect(app.locator('[data-application-confirm]')).to_be_visible()
                    passed(STAGE+'_writer_or_audit_failure_rolls_back_and_requires_status_recovery')
                if n==2:
                    # Lose only the delivery of a genuine committed response. The UI
                    # must consult the authoritative journal, never assume success.
                    def lose_response(route):
                        if route.request.method=='POST' and route.request.post_data_json.get('action')=='application_execute':
                            route.fetch();route.fulfill(status=503,content_type='application/json',body='{"ok":false}')
                        else:route.fallback()
                    staff.route('**/api/profiles/internal/correction-review.php',lose_response)
                    cor.response(review,PRIVATE,lambda:app.locator('[data-application-execute]').click(),503)
                    assert persisted(f)==proposed and result_count(f)=='1'
                    expect(app.locator('[data-application-message]')).to_contain_text('No se confirmó')
                    staff.unroute('**/api/profiles/internal/correction-review.php',lose_response)
                    cor.response(review,PRIVATE,lambda:app.locator('[data-application-status]').click())
                    passed('committed_response_loss_recovers_from_journal_without_second_mutation')
                else:
                    if n==3:cor.sql('UPDATE gov03b_qa_faults SET enabled=5')  # NO_CHANGE must never call writer
                    result=cor.response(review,PRIVATE,lambda:app.locator('[data-application-execute]').click())['data']
                    if n==3:cor.sql('UPDATE gov03b_qa_faults SET enabled=0')
                    assert result['state']==('NO_CHANGE' if n==3 else 'APPLIED') and result['profile_changed']==(n!=3)
                expected='El dato ya coincide; no se realizó una nueva modificación' if n==3 else 'Corrección aplicada'
                expect(app.locator('[data-application-state]')).to_have_text(expected)
                assert persisted(f)==proposed and public_value(public,origin,f)==proposed
                page.reload();expect(page.get_by_text('Teléfono: '+proposed,exact=True)).to_be_visible()
                replay=cor.staff_api(staff,origin,'application_execute',{'command_id':cid});assert replay.status==200 and replay.json()['data']['replayed']
                assert result_count(f)=='1'
                review.reload();cor.response(review,PRIVATE,lambda:review.locator('[data-report-id="'+f['report_id']+'"]').click());expect(app.locator('[data-application-state]')).to_have_text(expected)
                assert app.locator('[data-application-start]').is_hidden()
                for p,label in [(page,'public-committed'),(review,'staff-result')]:cor.shape(p);p.screenshot(path=str(OUT/(STAGE+'-'+label+'.png')),full_page=True)
                if n==0:
                    denied=browser.new_context(ignore_https_errors=True);p=denied.new_page();cor.login(p,origin,'app_reviewonly')
                    cor.response(p,PRIVATE,lambda:p.locator('[data-report-id="'+f['report_id']+'"]').click())
                    assert p.locator('[data-application-start]').is_hidden()
                    assert cor.staff_api(denied,origin,'application_execute',{'command_id':cid}).status==403
                    denied.close();passed('review_grant_never_inherits_apply_authority_in_original_UI_or_HTTP')
                assert not errors,errors
                passed(STAGE+'_canonical_commit_or_NO_CHANGE_original_profile_and_immutable_result_replay')
            finally:public.close();staff.close()
        finally:browser.close()
def main():
    os.umask(0o077)
    spec=importlib.util.spec_from_file_location('proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
    with tempfile.TemporaryDirectory(prefix='mxmed-cor02-',dir='/tmp') as d,(OUT/'browser-private-server.log').open('wb') as log:
        folder=pathlib.Path(d);folder.chmod(0o700)
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(folder/'key.pem'),'-out',str(folder/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
        backend=cor.port();tls=proxy.make_server(str(folder/'cert.pem'),str(folder/'key.pem'),port=0,backend_port=backend);tls.expected_host='localhost:'+str(tls.server_port);origin='https://'+tls.expected_host;key=os.urandom(32).hex();tls.governance_transport_key=key.encode()
        env={**os.environ,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_GOVERNANCE_PROXY_KEY':key,'MXMED_PROFILES_API_BASE':'http://127.0.0.1:'+str(backend),'MXMED_API_BASE':'','PHP_CLI_SERVER_WORKERS':'4'}
        php=subprocess.Popen(['php','-S','127.0.0.1:'+str(backend),'-t',str(ROOT),str(ROOT/'modules/profiles/tests/support/CorrectionQaRouter.php')],cwd=ROOT,env=env,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(50):
                if php.poll() is not None:raise RuntimeError('owned PHP failed')
                try:
                    with socket.create_connection(('127.0.0.1',backend),timeout=.1):break
                except OSError:time.sleep(.1)
            else:raise RuntimeError('owned PHP unavailable')
            threading.Thread(target=tls.serve_forever,daemon=True).start();run(origin)
            cor.run(origin,backend)  # The original PUB-COR01 browser suite, unchanged flows.
            (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS','checks':CHECKS,'pub_cor01_checks':cor.CHECKS,'viewports':[[1440,900],[1366,768],[820,1180]],'engines':['chromium','webkit'],'original_components':True,'normal_active':False},indent=2))
        finally:
            tls.shutdown();tls.server_close()
            if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
    print('PUB_COR02_ORIGINAL_BROWSER=PASS',flush=True)
if __name__=='__main__':
    try:main()
    except Exception:
        (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('PUB_COR02_ORIGINAL_BROWSER=FAIL STAGE='+STAGE,flush=True);raise SystemExit(1)
