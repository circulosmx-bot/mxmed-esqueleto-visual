#!/usr/bin/env python3
"""Original public profile and internal workspace, real owned SQL + canonical Identity/MFA.
Only gov03b-disposable-qa --suite correction may supply this environment.
"""
import os,pathlib,json,subprocess,socket,signal,threading,tempfile,importlib.util,time,traceback,hmac,hashlib,struct
from concurrent.futures import ThreadPoolExecutor
from playwright.sync_api import sync_playwright,expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
CONN=json.loads(pathlib.Path(os.environ['GOV03B_QA_CONNECTION']).read_text())
assert os.environ.get('PUB_COR01_QA')=='1' and CONN['db'].startswith('mxmed_gate4d_preview_cor01_')
FIXTURE=json.loads((OUT/'cor-fixture.json').read_text());CHECKS=[];STAGE='setup'
PUBLIC='/api/profiles/public/correction-reports.php';PRIVATE='/api/profiles/internal/correction-review.php'
WORKSPACE='/internal/media-review/index.php?section=correcciones'
def passed(label):CHECKS.append(label);print('PASS '+label,flush=True)
def sql(text):
    r=subprocess.run(['mysql','-h127.0.0.1','-P3306','-u'+CONN['user'],'-N','-B','-r',CONN['db']],input=text,text=True,capture_output=True,env={**os.environ,'MYSQL_PWD':CONN['password']})
    if r.returncode:raise RuntimeError('owned SQL assertion failed: '+r.stderr[-600:])
    return r.stdout.strip()
def port():
    with socket.socket() as sock:sock.bind(('127.0.0.1',0));return sock.getsockname()[1]
def totp():
    step=int(time.time())//30;raw=hmac.new(b'e'*20,struct.pack('>Q',step),hashlib.sha1).digest();offset=raw[-1]&15
    return str((int.from_bytes(raw[offset:offset+4],'big')&0x7fffffff)%1000000).zfill(6)
def shape(page):
    assert page.evaluate('document.documentElement.scrollWidth <= innerWidth + 1')
    assert 'example.invalid' not in page.url and 'intent=' not in page.url and 'challenge=' not in page.url
    assert page.locator('input[type=file]').count()==0
def response(page,path,click,status=200):
    with page.expect_response(lambda r:r.url.endswith(path) and r.request.method=='POST') as event:click()
    r=event.value;assert r.status==status,(r.status,r.text()[:100]);return r.json()
def public_data(page):
    return page.locator('[data-correction-report]').evaluate("f => ({intent:f.elements.intent.value,csrf_token:f.elements.csrf_token.value,category:'CHANGED_PHONE',explanation:'Explicación sintética suficientemente larga para verificar recepción privada.',relation:'',contact:'private-browser@example.invalid',privacy_ack:true})")
def public_api(context,origin,page,data,**kwargs):
    headers={'Origin':origin,'Referer':page.url,**kwargs.pop('headers',{})}
    return context.request.post(origin+PUBLIC,data=data,headers=headers,**kwargs)
def staff_api(context,origin,action,data=None,csrf=True):
    dto=context.request.get(origin+'/api/identity/index.php/current-session').json();token=dto.get('csrf_token','') if csrf else ''
    return context.request.post(origin+PRIVATE,data={'action':action,'csrf_token':token,**(data or {})},headers={'Origin':origin})
def login(page,origin,user):
    page.goto(origin+'/public/identity/acceso.php?next='+WORKSPACE)
    page.locator('#login-email').fill(user+'@example.invalid');page.locator('#login-password').fill(FIXTURE['password']);page.locator('[data-submit]').click()
    page.wait_for_url(origin+WORKSPACE);expect(page.locator('[data-refresh]')).to_be_visible()
def run(origin,backend):
    global STAGE
    with sync_playwright() as pw:
      for engine in ['chromium','webkit']:
        browser=getattr(pw,engine).launch()
        try:
          for i,(w,h) in enumerate([(1440,900),(1366,768),(820,1180)]):
            n=i+(0 if engine=='chromium' else 3);STAGE=f'{engine}_{w}_{h}';profile=FIXTURE['profiles'][n];reviewer=FIXTURE['reviewers'][n]
            anon=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});staff=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h})
            # Do not contact an external map/font service with synthetic QA. Keep
            # original first-party templates/scripts and all their errors observable.
            for context in [anon,staff]:
                context.route('**/*',lambda route:route.continue_() if route.request.url.startswith(origin+'/') else route.fulfill(status=200,content_type='text/html' if route.request.resource_type=='document' else 'text/plain',body=''))
            page=anon.new_page();review=staff.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)));review.on('pageerror',lambda e:errors.append(str(e)))
            try:
                page.goto(origin+'/profiles/doctor.php?doctor_id='+profile)
                page.locator('#mxpp-correction summary').click();form=page.locator('[data-correction-report]');expect(form).to_be_visible()
                assert page.locator('a[href*="entry=claim"]').count()>=1 and page.get_by_text('FORMAR PARTE',exact=True).count()>=1
                assert form.locator('select[name=category] option').count()==11 and form.locator('select[name=relation] option').count()==9
                assert form.locator('[name=doctor_id]').count()==0
                assert form.get_attribute('method')=='post' and form.get_attribute('action')==PUBLIC
                assert form.locator('input:not([type=hidden]):not([type=submit]),select,textarea').evaluate_all('els=>els.every(e=>e.labels.length>0)')
                form.locator('button').click();assert form.evaluate('f=>f.checkValidity()') is False
                form.locator('[name=category]').select_option('PHYSICIAN_DECEASED' if n%2 else 'PHYSICIAN_RETIRED')
                form.locator('[name=explanation]').fill('Aviso sintético de prueba. <script>window.correctionXss=1</script> No corresponde a una persona real.')
                form.locator('[name=contact]').fill('private-browser@example.invalid');form.locator('[name=privacy_ack]').check()
                form.locator('[name=category]').focus();page.keyboard.press('Tab');assert page.locator('[name=explanation]').evaluate('e=>e===document.activeElement')
                data=public_data(page)
                if n==0:
                    assert public_api(anon,origin,page,{**data,'doctor_id':'cor_profile_5'}).status==400
                    assert public_api(anon,origin,page,{**data,'csrf_token':''}).status==403
                    assert public_api(anon,origin,page,data,headers={'Origin':'https://untrusted.invalid'}).status==403
                    assert public_api(anon,origin,page,data,headers={'Referer':origin+'/profiles/doctor.php?doctor_id=cor_profile_5'}).status==403
                    assert public_api(anon,origin,page,{**data,'category':'OWNER'}).status==400
                    assert public_api(anon,origin,page,{**data,'relation':{'role':'owner'}}).status==400
                    assert public_api(anon,origin,page,{**data,'explanation':'x'*2001}).status==400
                    assert public_api(anon,origin,page,{**data,'explanation':'x'*17000}).status==413
                    assert public_api(anon,origin,page,data,headers={'Content-Type':'text/plain'}).status==415
                    # No TLS terminator proof on the cleartext backend, even with forged HTTPS.
                    assert anon.request.post('http://127.0.0.1:'+str(backend)+PUBLIC,data=data,headers={'Host':origin.split('//')[1],'Origin':origin,'Referer':page.url,'X-Forwarded-Proto':'https'}).status==503
                    assert anon.request.get(origin+PUBLIC+'?receipt=public-value').status==405
                    assert staff_api(anon,origin,'queue').status==401
                    passed('HTTP_strict_types_size_origin_CSRF_substitution_cleartext_and_receipt_denials')
                    sql('UPDATE gov03b_qa_faults SET enabled=3')
                    response(page,PUBLIC,lambda:form.locator('button').click(),503)
                    expect(form.locator('[role=status]')).to_contain_text('No pudimos confirmar')
                    sql('UPDATE gov03b_qa_faults SET enabled=0')
                    passed('HTTP_required_rate_storage_failure_no_success')
                    sql('UPDATE gov03b_qa_faults SET enabled=1')
                    response(page,PUBLIC,lambda:form.locator('button').click(),503)
                    expect(form.locator('[role=status]')).to_contain_text('No pudimos confirmar')
                    assert 'Recibimos tu informe' not in form.inner_text()
                    sql('UPDATE gov03b_qa_faults SET enabled=0')
                    passed('original_form_required_audit_failure_honest_retry')
                count=sql('SELECT COUNT(*) FROM auth_account_memberships')
                result=response(page,PUBLIC,lambda:form.locator('button').click());rid=result['data']['receipt_id']
                expect(form.locator('[role=status]')).to_have_text('Recibimos tu informe. Nuestro equipo revisará la información antes de realizar cualquier cambio.')
                assert form.locator('[name=explanation]').input_value()=='' and form.locator('button').is_disabled()
                assert sql('SELECT COUNT(*) FROM auth_account_memberships')==count
                row=json.loads(sql("SELECT JSON_OBJECT('profile',profile_doctor_id,'route',original_route,'cipher',HEX(payload_ciphertext)) FROM profiles_correction_reports WHERE report_id='"+rid+"'"))
                assert row['profile']==profile and row['route']=='/profiles/doctor.php?doctor_id='+profile and b'private-browser' not in bytes.fromhex(row['cipher'])
                assert anon.request.get(origin+'/api/identity/index.php/current-session').status==401
                assert not page.evaluate('window.correctionXss || false');shape(page)
                passed(STAGE+'_original_public_form_real_encrypted_persistence_no_membership')
                login(review,origin,reviewer)
                expect(review.locator('[data-report-id="'+rid+'"]')).to_be_visible()
                if n==0:
                    opened=[]
                    review.on('request',lambda r:opened.append(r.post_data_json) if r.url.endswith(PRIVATE) and r.method=='POST' and r.post_data_json.get('action')=='open' else None)
                    response(review,PRIVATE,lambda:review.evaluate("id=>{const buttons=[...document.querySelectorAll('[data-report-id]')];buttons.find(b=>b.dataset.reportId===id).click();buttons.find(b=>b.dataset.reportId!==id).click();}",rid))
                    assert len(opened)==1 and opened[0]['report_id']==rid
                    passed('rapid_open_actions_preserve_one_exact_report_before_decision')
                else:response(review,PRIVATE,lambda:review.locator('[data-report-id="'+rid+'"]').click())
                expect(review.locator('[data-original-profile]')).to_contain_text('Profesional sintético')
                expect(review.locator('[data-explanation]')).to_contain_text('<script>window.correctionXss=1</script>')
                assert not review.evaluate('window.correctionXss || false')
                expect(review.locator('[data-contact]')).to_contain_text('private-browser@example.invalid')
                status=['ACCEPTED_FOR_APPLICATION','REJECTED','NEEDS_CLARIFICATION'][i]
                review.locator('#correction-decision').select_option(status);review.locator('#correction-reason').select_option({'ACCEPTED_FOR_APPLICATION':'FACTS_SUPPORTED','REJECTED':'NOT_SUPPORTED','NEEDS_CLARIFICATION':'NEEDS_DETAILS'}[status])
                review.locator('#correction-refs').fill('synthetic-reference-'+str(n));review.locator('#correction-note').fill('Revisión humana sintética; no aplicar cambios al perfil.')
                review.locator('[name=facts_confirmed]').check();review.locator('#correction-review-email').fill(reviewer+'@example.invalid');review.locator('#correction-review-password').fill(FIXTURE['password'])
                begun=response(review,PRIVATE,lambda:review.locator('[data-review-begin] button').click());assert begun['data']['report_id']==rid
                expect(review.locator('[data-review-decide]')).to_be_visible();assert review.locator('#correction-review-password').input_value()==''
                review.locator('#correction-mfa').fill(totp());response(review,PRIVATE,lambda:review.locator('[data-review-decide] button[type=submit]').click())
                expect(review.locator('[data-decision-message]')).to_contain_text('pendiente de una aplicación autorizada' if i==0 else ('no ha sido modificado' if i==1 else 'No se ha enviado'))
                assert sql("SELECT status FROM profiles_correction_decisions WHERE report_id='"+rid+"'")==status
                expect(review.locator('[data-review-history] li')).to_have_count(1);shape(review)
                assert staff_api(staff,origin,'queue',csrf=False).status==403
                assert staff_api(staff,origin,'queue',{'csrf_token':[]}).status==403
                assert staff_api(staff,origin,'queue',{'page':None}).status==400
                assert staff_api(staff,origin,'open',{'report_id':rid,'profile_doctor_id':'cor_profile_5'}).status==400
                passed(STAGE+'_original_workspace_exact_grant_password_MFA_human_decision_no_application')
                if n==0:
                    # The current browser's failed audit attempt spent one slot; two
                    # successful commands including this one leave no fourth attempt.
                    page.goto(origin+'/profiles/doctor.php?doctor_id='+profile);page.locator('#mxpp-correction summary').click();retry=public_data(page)
                    assert public_api(anon,origin,page,retry).status==200
                    # Replay is free, private payload/ack only; no report read endpoint.
                    replay=public_api(anon,origin,page,retry);assert replay.status==200 and replay.json()['data']['replayed'] is True
                    page.reload();page.locator('#mxpp-correction summary').click();assert public_api(anon,origin,page,public_data(page)).status==429
                    passed('HTTP_rate_denial_and_exact_successful_replay_one_report')
                    unauthorized=browser.new_context(ignore_https_errors=True);p=unauthorized.new_page()
                    p.goto(origin+'/public/identity/acceso.php');p.locator('#login-email').fill('cor_c4_only@example.invalid');p.locator('#login-password').fill(FIXTURE['password']);p.locator('[data-submit]').click();p.wait_for_timeout(500)
                    assert staff_api(unauthorized,origin,'open',{'report_id':rid}).status==403
                    unauthorized.close();passed('HTTP_C4_permission_does_not_authorize_correction_payload')
                for p,label in [(page,'public'),(review,'staff')]:shape(p);p.screenshot(path=str(OUT/(STAGE+'-'+label+'.png')),full_page=True)
                assert not errors,errors
            finally:anon.close();staff.close()
        finally:browser.close()

def main():
    os.umask(0o077)
    spec=importlib.util.spec_from_file_location('proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
    with tempfile.TemporaryDirectory(prefix='mxmed-cor01-',dir='/tmp') as d,(OUT/'browser-private-server.log').open('wb') as log:
        folder=pathlib.Path(d);folder.chmod(0o700)
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(folder/'key.pem'),'-out',str(folder/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
        backend=port();tls=proxy.make_server(str(folder/'cert.pem'),str(folder/'key.pem'),port=0,backend_port=backend);tls.expected_host='localhost:'+str(tls.server_port);origin='https://'+tls.expected_host;key=os.urandom(32).hex();tls.governance_transport_key=key.encode()
        env={**os.environ,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_GOVERNANCE_PROXY_KEY':key,'MXMED_PROFILES_API_BASE':'http://127.0.0.1:'+str(backend),'MXMED_API_BASE':'','PHP_CLI_SERVER_WORKERS':'4'}
        php=subprocess.Popen(['php','-S','127.0.0.1:'+str(backend),'-t',str(ROOT),str(ROOT/'modules/profiles/tests/support/CorrectionQaRouter.php')],cwd=ROOT,env=env,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
        try:
            for _ in range(50):
                if php.poll() is not None:raise RuntimeError('owned PHP failed')
                try:
                    with socket.create_connection(('127.0.0.1',backend),timeout=.1):break
                except OSError:time.sleep(.1)
            else:raise RuntimeError('owned PHP unavailable')
            threading.Thread(target=tls.serve_forever,daemon=True).start();run(origin,backend)
            (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS','checks':CHECKS,'engines':['chromium','webkit'],'viewports':[[1440,900],[1366,768],[820,1180]],'original_templates':True,'normal_activation':False},indent=2))
        finally:
            tls.shutdown();tls.server_close()
            if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
    print('PUB_COR01_ORIGINAL_BROWSER=PASS',flush=True)
if __name__=='__main__':
    try:main()
    except Exception:
        (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('PUB_COR01_ORIGINAL_BROWSER=FAIL STAGE='+STAGE,flush=True);raise SystemExit(1)
