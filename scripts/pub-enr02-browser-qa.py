#!/usr/bin/env python3
"""New synthetic SQL/user/store, native services and original public/Identity templates.
Only the owning gov03b disposable runner supplies this process environment.
"""
import pathlib,os,json,subprocess,socket,signal,threading,tempfile,importlib.util,time,hashlib,hmac,datetime,traceback
from urllib.parse import quote,urlsplit,parse_qs
from concurrent.futures import ThreadPoolExecutor
from playwright.sync_api import sync_playwright,expect

ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=pathlib.Path(os.environ['GOV03B_QA_EVIDENCE'])
CONN=json.loads(pathlib.Path(os.environ['GOV03B_QA_CONNECTION']).read_text())
assert os.environ.get('PUB_ENR02_QA')=='1' and CONN['db'].startswith('mxmed_gate4d_preview_c4ui_')
PASSWORD='Only-Synthetic-C4-EV01!'
CHECKS=[]
STAGE='initialization'

def passed(label):CHECKS.append(label);print('PASS '+label,flush=True)
def sql(text):
 r=subprocess.run(['mysql','-h127.0.0.1','-P3306','-u'+CONN['user'],'-N','-B','-r',CONN['db']],input=text,text=True,capture_output=True,env={**os.environ,'MYSQL_PWD':CONN['password']})
 if r.returncode:raise RuntimeError('owned SQL check failed')
 return r.stdout.strip()
def port():
 with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def worker(action,version=0):
 r=subprocess.run(['php',str(ROOT/'modules/identity/tests/PublicEnrollmentRaceWorker.php'),action,str(version)],env=os.environ,capture_output=True,text=True)
 if r.returncode:raise RuntimeError('owned native concurrency worker failed: '+r.stderr[-1000:])
 return r.stdout
def concurrency():
 with ThreadPoolExecutor(2) as pool:answers=[json.loads(x) for x in pool.map(lambda _:worker('bind'),range(2))]
 assert all(x['ok'] for x in answers)
 with ThreadPoolExecutor(2) as pool:answers=[json.loads(x) for x in pool.map(lambda _:worker('retry'),range(2))]
 assert sorted(x['replayed'] for x in answers)==[False,True] and all(x['version']==1 for x in answers)
 with ThreadPoolExecutor(2) as pool:answers=[json.loads(x) for x in pool.map(lambda _:worker('different',1),range(2))]
 assert sum(x['ok'] for x in answers)==1 and sum(x.get('conflict',False) for x in answers)==1
 assert sql("SELECT COUNT(*) FROM auth_physician_enrollment_origins WHERE account_id='enr_parallel'")=='1'
 assert sql("SELECT COUNT(*) FROM auth_physician_enrollment_intake_revisions WHERE account_id='enr_parallel'")=='2'
 passed('R_real_parallel_binding_idempotency_and_CAS')

def submit(page,action,click,expected=200):
 with page.expect_response(lambda r:r.url.endswith('/api/identity/physician-enrollment.php') and r.request.method=='POST' and r.request.post_data_json.get('action')==action) as event:click()
 r=event.value;assert r.status==expected,(action,r.status,expected)
 return None if action in ['start','bind'] else r.json()

def api(context,origin,action,data=None,csrf=True,request_origin=None):
 dto=context.request.get(origin+'/api/identity/index.php/current-session').json()
 token=dto.get('csrf_token','') if csrf else ''
 return context.request.post(origin+'/api/identity/physician-enrollment.php',data={'action':action,'csrf_token':token,**(data or {})},headers={'Origin':request_origin or origin,'X-CSRF-Token':token})

def fill(page,label='Persona sintética'):
 values={'given_names':label,'first_surname':'Prueba','second_surname':'','professional_license':'SYNTHETIC-LICENSE-5','specialty':'Especialidad sintética','state':'Estado sintético','city':'Ciudad sintética'}
 for key,value in values.items():page.locator('[data-enrollment-intake] [name='+key+']').fill(value)
 return values

def shape(page):
 assert page.locator('[name=profile_reference],[name=doctor_id],[name=account_id],input[type=file]').count()==0
 assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
 assert not page.evaluate("[...document.querySelectorAll('input,select,textarea')].some(e=>!e.labels?.length)")
 for word in ['origin_id','source_version','DDL','binding','canonical']:assert word not in page.locator('body').inner_text()

def start(page,origin,path):
 page.goto(origin+path)
 submit(page,'start',lambda:page.locator('[data-enrollment-start]').click())
 page.wait_for_url('**/public/identity/perfil-medico.php?application=*')
 return parse_qs(urlsplit(page.url).query)['application'][0]

def redis(*args):
 values=[str(v).encode() for v in args]
 with socket.create_connection(('127.0.0.1',int(os.environ['MXMED_SESSION_STORE_PORT'])),timeout=5) as stream:
  stream.sendall(b'*'+str(len(values)).encode()+b'\r\n'+b''.join(b'$'+str(len(v)).encode()+b'\r\n'+v+b'\r\n' for v in values));reader=stream.makefile('rb');line=reader.readline()
  return reader.read(int(line[1:-2])) if line.startswith(b'$') else line
def expire(context):
 token=next(c['value'] for c in context.cookies() if c['name']=='__Host-mxmed_session')
 digest=hmac.new(os.environ['MXMED_PREVIEW_PEPPER'].encode(),token.encode(),hashlib.sha256).hexdigest();key='mxmed:gate4d:preview:session:'+digest
 record=json.loads(redis('GET',key));now=datetime.datetime.now(datetime.timezone.utc)
 record.update(created_at=(now-datetime.timedelta(hours=2)).isoformat(),last_seen_at=(now-datetime.timedelta(hours=2)).isoformat(),expires_at=(now-datetime.timedelta(hours=1)).isoformat(),absolute_expires_at=(now+datetime.timedelta(hours=10)).isoformat())
 redis('SET',key,json.dumps(record),'EX',120)

def run(origin,backend):
 global STAGE
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine).launch()
   try:
    for i,(w,h) in enumerate([(1440,900),(1366,768),(820,1180)]):
     index=i+(0 if engine=='chromium' else 3);STAGE=engine+'_'+str(w)
     context=browser.new_context(ignore_https_errors=True,viewport={'width':w,'height':h});page=context.new_page();errors=[];page.on('pageerror',lambda e:errors.append(str(e)))
     try:
      # Isolated case rate buckets only; never changes productive rate policy/data.
      sql('DELETE FROM auth_rate_limit_buckets')
      page.goto(origin+'/profiles/doctor.php?doctor_id=enr_profile_'+str(8+index));expect(page.locator('a[href*="entry=claim"]')).to_have_count(1)
      page.locator('a[href*="entry=claim"]').click();expect(page.locator('[data-origin-profile]')).to_contain_text('Profesional sintético '+str(8+index))
      submit(page,'start',lambda:page.locator('[data-enrollment-start]').click());page.wait_for_url('**/public/identity/perfil-medico.php?application=*')
      app=parse_qs(urlsplit(page.url).query)['application'][0];path='/public/identity/perfil-medico.php?application='+app
      page.reload();expect(page.locator('[data-enrollment-register]')).to_be_visible();shape(page)
      expect(page.locator('[data-onboarding-logout]')).to_have_count(0)
      proof=[c for c in context.cookies() if c['name'].startswith('__Host-mxmed_enrollment')]
      assert len(proof)>=3 and all(c['httpOnly'] and c['secure'] and c['sameSite']=='Strict' for c in proof)
      page.locator('[data-enrollment-register]').click();assert page.locator('body').get_attribute('data-enrollment-return')==path
      # Another tab must not redirect this registration/verification to a newer origin.
      other=context.new_page();other_app=start(other,origin,'/public/identity/perfil-medico.php?entry=join');assert other_app!=app;other.close()
      email='enr_browser_'+str(index)+'@example.invalid'
      page.locator('#create-email').fill(email);page.locator('#create-password').fill(PASSWORD);page.locator('#create-confirm').fill(PASSWORD);page.locator('#terms').check();page.locator('#privacy').check();page.locator('[data-submit]').click()
      expect(page.locator('[data-state-title]')).to_have_text('Solicitud de cuenta enviada')
      assert context.request.get(origin+'/api/identity/index.php/current-session').status==401
      message=next(m for m in reversed(json.loads(pathlib.Path(os.environ['MXMED_PREVIEW_NOTIFICATION_FILE']).read_text())) if m['recipient']==email)
      assert message['continuation_path']==path
      page.goto(origin+'/public/identity/verificar-correo.php?token='+quote(message['token'])+'&next='+quote(path,safe=''))
      page.locator('[data-submit]').click();expect(page.locator('[data-state-title]')).to_have_text('Correo verificado');assert context.request.get(origin+'/api/identity/index.php/current-session').status==401
      page.locator('[data-onboarding-next] a').click();page.locator('#login-email').fill(email);page.locator('#login-password').fill(PASSWORD);page.locator('[data-submit]').click();page.wait_for_url(origin+path)
      expect(page.locator('[data-origin-profile]')).to_contain_text('Profesional sintético '+str(8+index))
      submit(page,'bind',lambda:page.locator('[data-enrollment-bind]').click());page.wait_for_load_state();expect(page.locator('[name=given_names]')).to_be_enabled();shape(page)
      passed(STAGE+'_A_E_G_H_registration_email_login_exact_preloaded_claim')
      assert api(context,origin,'snapshot',{'application_id':app},csrf=False).status==403
      assert api(context,origin,'snapshot',{'application_id':app},request_origin='https://untrusted.invalid').status==403
      assert api(context,origin,'bind',{'application_id':app,'profile_doctor_id':'enr_profile_0'}).status==422
      assert api(context,origin,'assign_owner',{'application_id':app}).status==422
      assert api(context,origin,'resolve',{'application_id':app}).status==422
      malformed=api(context,origin,'snapshot',{'application_id':app,'csrf_token':['invalid']});assert malformed.status==422 and malformed.json()['ok'] is False
      if index==0:
       dto=context.request.get(origin+'/api/identity/index.php/current-session').json();cookie='; '.join(c['name']+'='+c['value'] for c in context.cookies() if c['name']!='__Host-mxmed_enrollment_browser')+'; __Host-mxmed_enrollment_browser[x]=invalid'
       malformed=context.request.post(origin+'/api/identity/physician-enrollment.php',data={'action':'bind','csrf_token':dto['csrf_token'],'application_id':app},headers={'Origin':origin,'Cookie':cookie})
       assert malformed.status==403 and malformed.json()['ok'] is False
      assert context.request.get(origin+path+'&profile=enr_profile_0').status==403
      direct=context.request.post('http://127.0.0.1:'+str(backend)+'/api/identity/physician-enrollment.php',data={'action':'snapshot','application_id':app},headers={'Origin':origin,'X-Forwarded-Proto':'https'})
      assert direct.status==503
      if index==0:
       submit(page,'submit',lambda:page.locator('[data-enrollment-intake] button[value=submit]').click(),422)
       expect(page.locator('[data-onboarding-feedback]')).to_contain_text('Revisa los datos')
      fill(page);submit(page,'save',lambda:page.locator('[data-enrollment-intake] button[value=save]').click());expect(page.locator('[data-onboarding-feedback]')).to_contain_text('Información guardada')
      page.reload();expect(page.locator('[name=given_names]')).to_have_value('Persona sintética')
      stale=context.new_page();stale.goto(origin+path)
      fill(page,'Persona sintética actualizada');submit(page,'save',lambda:page.locator('[data-enrollment-intake] button[value=save]').click())
      submit(stale,'save',lambda:stale.locator('[data-enrollment-intake] button[value=save]').click(),409);expect(stale.locator('[data-onboarding-feedback]')).to_contain_text('La solicitud cambió');stale.close()
      if index==0:
       sql('UPDATE gov03b_qa_faults SET enabled=1')
       try:submit(page,'submit',lambda:page.locator('[data-enrollment-intake] button[value=submit]').click(),503)
       finally:sql('UPDATE gov03b_qa_faults SET enabled=0')
       assert api(context,origin,'snapshot',{'application_id':app}).json()['data']['version']==2
       passed('T_original_UI_audit_failure_no_false_success')
      submit(page,'submit',lambda:page.locator('[data-enrollment-intake] button[value=submit]').click());page.reload();expect(page.locator('[data-enrollment-summary]')).to_contain_text('Información inicial enviada')
      expect(page.locator('[name=given_names]')).to_be_disabled();shape(page);page.screenshot(path=str(OUT/(STAGE+'-claim-pending.png')),full_page=True)
      assert context.request.get(origin+'/private-app.php',max_redirects=0).status==403
      assert context.request.post(origin+'/api/clinical/index.php/prescriptions/synthetic/sign',data={},headers={'Origin':origin}).status in [401,403,404]
      assert context.request.get(origin+'/api/profile-operators/index.php/overview').status==503
      passed(STAGE+'_Q_S_T_U_V_W_security_pending_only')
      page.goto(origin+'/profiles/listing.php');page.get_by_role('link',name='FORMAR PARTE',exact=True).click()
      general=submit(page,'start',lambda:page.locator('[data-enrollment-start]').click());page.wait_for_url('**/public/identity/perfil-medico.php?application=*');gid=parse_qs(urlsplit(page.url).query)['application'][0]
      submit(page,'bind',lambda:page.locator('[data-enrollment-bind]').click());expect(page.locator('[name=given_names]')).to_be_enabled()
      fill(page);submit(page,'save',lambda:page.locator('[data-enrollment-intake] button[value=save]').click());page.reload();expect(page.locator('[name=given_names]')).to_have_value('Persona sintética')
      snapshot=api(context,origin,'snapshot',{'application_id':gid}).json()['data'];assert snapshot['initial_origin']=='REQUEST_NEW_PROFILE' and snapshot['original_profile_id'] is None and snapshot['matching_available'] is False
      submit(page,'submit',lambda:page.locator('[data-enrollment-intake] button[value=submit]').click());page.reload();shape(page);page.screenshot(path=str(OUT/(STAGE+'-join-pending.png')),full_page=True)
      assert sql("SELECT COUNT(*) FROM profiles_doctors")=='20'
      assert not errors,errors
      passed(STAGE+'_L_M_N_O_P_general_saved_resumed_without_profile_creation')
      if index==0:
       stranger=browser.new_context(ignore_https_errors=True);p2=stranger.new_page();p2.goto(origin+'/public/identity/acceso.php?next='+quote(path,safe=''));p2.locator('#login-email').fill('enr_b@example.invalid');p2.locator('#login-password').fill(PASSWORD);p2.locator('[data-submit]').click();p2.wait_for_url(origin+path)
       assert api(stranger,origin,'snapshot',{'application_id':app}).status==403
       # A stolen enrollment handle/browser pair still cannot reassign an already-bound application.
       stranger.add_cookies(proof);assert api(stranger,origin,'bind',{'application_id':app}).status==403;stranger.close()
       expire(context);page.evaluate("window.dispatchEvent(new Event('focus'))");expect(page.locator('[data-onboarding-feedback]')).to_contain_text('Tu sesión terminó');expect(page.locator('[name=given_names]')).to_have_value('')
       assert api(context,origin,'snapshot',{'application_id':gid}).status==401
       passed('I_Q_cross_account_and_expired_session_denied_private_DOM_cleared')
      else:
       page.locator('[data-onboarding-logout]').click();page.wait_for_url('**/public/identity/acceso.php?next=*');assert context.request.get(origin+'/api/identity/index.php/current-session').status==401
       page.locator('#login-email').fill(email);page.locator('#login-password').fill(PASSWORD);page.locator('[data-submit]').click();page.wait_for_url(origin+'/public/identity/perfil-medico.php?application='+gid)
       expect(page.locator('[name=given_names]')).to_have_value('Persona sintética');expect(page.locator('[name=given_names]')).to_be_disabled()
       passed(STAGE+'_canonical_logout_and_same_pending_application_login_resume')
     finally:context.close()
   finally:browser.close()

def main():
 os.umask(0o077);concurrency()
 # Real SQL source loss, only in this owned disposable schema; no persistent reset.
 c=json.loads(pathlib.Path(os.environ['PUB_ENR02_DEPLOYMENT_CONNECTION']).read_text())
 def ddl(text):
  r=subprocess.run(['mysql','-h127.0.0.1','-P3306','-u'+c['MXMED_DB_USER'],CONN['db']],input=text,text=True,capture_output=True,env={**os.environ,'MYSQL_PWD':c['MXMED_DB_PASS']});assert r.returncode==0
 ddl('RENAME TABLE auth_account_memberships TO enrollment_qa_memberships_unavailable')
 try:assert 'PASS D_missing_ownership_source_fails_closed' in worker('missing-source')
 finally:ddl('RENAME TABLE enrollment_qa_memberships_unavailable TO auth_account_memberships')
 passed('D_native_missing_ownership_source_fails_closed')
 spec=importlib.util.spec_from_file_location('proxy',ROOT/'scripts/normal-local-https-proxy.py');proxy=importlib.util.module_from_spec(spec);spec.loader.exec_module(proxy)
 with tempfile.TemporaryDirectory(prefix='mxmed-enrollment-',dir='/tmp') as d,(OUT/'browser-private-server.log').open('wb') as log:
  folder=pathlib.Path(d);folder.chmod(0o700)
  subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(folder/'key.pem'),'-out',str(folder/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'],stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL,check=True)
  backend=port();tls=proxy.make_server(str(folder/'cert.pem'),str(folder/'key.pem'),port=0,backend_port=backend);tls.expected_host='localhost:'+str(tls.server_port);origin='https://'+tls.expected_host
  key=os.urandom(32).hex();tls.governance_transport_key=key.encode()
  # Original single-worker profile fallback resolves the native controller/SQL.
  # No external API origin or alternate public DTO is injected.
  config={**os.environ,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_GOVERNANCE_PROXY_KEY':key,'PUB_ENR02_QA_PROOF_KEY':os.urandom(32).hex(),'MXMED_PROFILES_API_BASE':'','MXMED_API_BASE':''}
  php=subprocess.Popen(['php','-S','127.0.0.1:'+str(backend),'-t',str(ROOT),str(ROOT/'modules/identity/tests/support/PublicEnrollmentQaRouter.php')],cwd=ROOT,env=config,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
  try:
   for _ in range(50):
    if php.poll() is not None:raise RuntimeError('owned PHP failed')
    try:
     with socket.create_connection(('127.0.0.1',backend),timeout=.1):break
    except OSError:time.sleep(.1)
   else:raise RuntimeError('owned PHP unavailable')
   threading.Thread(target=tls.serve_forever,daemon=True).start();run(origin,backend)
   assert sql('SELECT COUNT(*) FROM profiles_physician_admissions')=='0' and sql('SELECT COUNT(*) FROM auth_account_professional_bindings')=='0'
   assert sql('SELECT COUNT(*) FROM auth_account_memberships')=='2'
   passed('no_new_memberships_owners_professional_facts_or_final_authorities')
   files=[p for folder in ['modules/identity/Accreditation','modules/identity/tests','public/identity','partials/identity','assets/js/identity'] for p in (ROOT/folder).rglob('*') if p.is_file() and p.suffix in ['.php','.js','.css']]
   (OUT/'browser-result.json').write_text(json.dumps({'result':'PASS','checks':CHECKS,'engines':['chromium','webkit'],'viewports':[[1440,900],[1366,768],[820,1180]],'original_templates':True,'normal_rows_imported':0,'normal_runtime_active':False,'source_manifest':{str(p.relative_to(ROOT)):hashlib.sha256(p.read_bytes()).hexdigest() for p in files}},indent=2)+'\n')
  finally:
   tls.shutdown();tls.server_close()
   if php.poll() is None:os.killpg(php.pid,signal.SIGTERM);php.wait(timeout=10)
   (OUT/'enrollment-race.json').unlink(missing_ok=True)
 print('PUB_ENR02_ORIGINAL_BROWSER_QA=PASS',flush=True)

if __name__=='__main__':
 try:main()
 except Exception:
  (OUT/'browser-private-failure.log').write_text(traceback.format_exc());print('PUB_ENR02_ORIGINAL_BROWSER_QA=FAIL STAGE='+STAGE+'; inspect private evidence',flush=True);raise SystemExit(1)
