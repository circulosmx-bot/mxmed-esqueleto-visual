#!/usr/bin/env python3
"""Targeted CSRF-age, popup and permission-contract recovery probes, owner verified."""
import pathlib,json,sys,os,time,hashlib,subprocess,ssl,http.client
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert owned.parent==pathlib.Path('/private/tmp') and owned.name.startswith('mxmed-ase-adm02imp01-') and owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text());assert approval['root']==str(root) and approval['expires']>time.time()
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text());origin=env['MXMED_PREVIEW_ORIGIN'];records=json.loads((owned/'uxfunc02-records.json').read_text());checks=[]
def check(name,ok):assert ok,name;checks.append({'name':name,'pass':True});print('PASS '+name,flush=True)
php="""require 'scripts/ase-adm02-workflow-runtime.php';[$i]=staffWorkflowOwnedServices();$a=(new Identity\\Http\\CanonicalHttpSessionResolver($i->sessions()))->resolve(['__Host-mxmed_session'=>trim(stream_get_contents(STDIN))],true);$clock=new class implements Identity\\Contracts\\Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('-901 seconds');}};$csrf=new Identity\\Http\\CsrfTokenService(getenv('MXMED_PREVIEW_PEPPER'),900,$clock,getenv('MXMED_PREVIEW_ORIGIN'));echo $csrf->issueAuthenticated($a->session()->tokenDigest());"""
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport={'width':390,'height':844});p=ctx.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
  p.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo');p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill((owned/'fixture-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled()
  token=next(x['value'] for x in ctx.cookies() if x['name']=='__Host-mxmed_session');expired=subprocess.check_output(['php','-r',php],cwd=root,env=env,input=token,text=True);bootstraps=[];mutations=[]
  def route(route):
   action=route.request.post_data_json['action']
   if action=='bootstrap':
    response=route.fetch(headers={**route.request.all_headers(),'Sec-Fetch-Site':'same-origin'});body=response.json();bootstraps.append(1)
    if len(bootstraps)==1:body['csrf_token']=expired
    route.fulfill(response=response,json=body)
   else:
    if action=='update':mutations.append(1)
    route.continue_()
  p.route('**/api/internal/staff-admission/workflow.php',route);p.reload();expect(p.locator('#add-member')).to_be_enabled();p.locator('[data-member="'+records['bruno']['account']+'"]').click();p.locator('#personal-data summary').click();p.locator('#member-edit').click();expect(p.locator('#personal-given_names')).to_be_enabled();check(engine+' expired initial CSRF refreshed before opening authorized profile',len(bootstraps)>1);p.keyboard.press('Escape')
  p.locator('#show-directory').click();p.locator('[data-member="'+records['alma']['account']+'"]').click();p.locator('#identity-evidence summary').click();p.locator('#member-evidence').click();expect(p.locator('[data-review-action=READ]')).to_be_enabled();check(engine+' identity consultation works after initial CSRF expiry without mutation replay',not mutations);p.keyboard.press('Escape');p.unroute('**/api/internal/staff-admission/workflow.php',route)
  # Bring the popup trigger to the bottom of the visible dialog, then verify flipping and scrolling.
  p.locator('#personal-data summary').click();p.locator('#member-edit').click();expect(p.locator('#personal-gender-trigger')).to_be_enabled();p.set_viewport_size({'width':390,'height':400});trigger=p.locator('#personal-gender-trigger');trigger.evaluate('(e)=>e.scrollIntoView({block:"end"})');trigger.click();pop=p.locator('.team-select-options:popover-open');expect(pop).to_be_visible();check(engine+' popup flips upward at modal bottom',pop.get_attribute('data-direction')=='above');p.keyboard.press('End');check(engine+' expanded last option scrolls into view',pop.locator('button').last.evaluate('(e)=>{const a=e.getBoundingClientRect(),b=e.parentElement.getBoundingClientRect();return a.top>=b.top&&a.bottom<=b.bottom+1}'));p.keyboard.press('Escape');expect(p.locator('#staff-profile')).to_be_visible()
  trigger.click();p.mouse.click(2,2);expect(p.locator('.team-select-options:popover-open')).to_have_count(0);check(engine+' outside click closes popup without double dismissal',p.locator('#staff-profile').is_visible());p.keyboard.press('Escape')
  p.set_viewport_size({'width':1366,'height':768});p.locator('[data-member="'+records['bruno']['account']+'"]').click();p.locator('#personal-data summary').click();p.locator('#member-edit').click();expect(p.locator('#choose-portrait')).to_be_enabled();p.locator('#portrait-file').set_input_files(str(owned/'synthetic.png'));expect(p.locator('#staff-profile-status')).to_contain_text('Fotografía privada guardada');p.locator('#personal-operation_state').fill('Estado Ficticio '+engine+' '+str(time.time_ns()));p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');p.keyboard.press('Escape');expect(p.locator('#personal-image img')).to_be_visible();check(engine+' private portrait upload and presentation survive profile refresh',True)
  def media_fault(route):
   if route.request.post_data_json['action']=='media_status':route.fulfill(status=503,content_type='application/json',body='{"error":"unavailable_reconcile_required"}')
   else:route.continue_()
  p.route('**/api/internal/staff-admission/workflow.php',media_fault);p.locator('#member-edit').click();expect(p.locator('#personal-given_names')).to_be_enabled();expect(p.locator('#choose-portrait')).to_be_disabled();city='Ciudad Ficticia '+engine+' '+str(time.time_ns());p.locator('#personal-operation_city').fill(city);p.locator('#personal-second_surname').fill('Segundo Ficticio');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');p.keyboard.press('Escape');p.unroute('**/api/internal/staff-admission/workflow.php',media_fault);p.reload();p.locator('[data-member="'+records['bruno']['account']+'"]').click();p.locator('#personal-data summary').click();expect(p.locator('#personal-data')).to_contain_text(city);check(engine+' unavailable portrait reader does not block authorized changed fields or persistence',True)
  check(engine+' no unhandled errors in recovery',not errors);b.close()
# Legitimate canonical fixture logins; inspect actual correction consumers with stored grants.
for actor in ['director','bruno']:
 result=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=env,input=json.dumps({'actor':actor,'email':('qa_director' if actor=='director' else 'bruno')+'@example.test','existing':actor=='director'}),text=True,capture_output=True);assert result.returncode==0 and json.loads(result.stdout).get('ok')
tokens=json.loads((owned/'sessions.json').read_text())
def request(path,body=None,csrf=None):
 c=http.client.HTTPSConnection('127.0.0.1',int(origin.rsplit(':',1)[1]),context=ssl.create_default_context(cafile=str(owned/'cert.pem')));h={'Origin':origin,'Sec-Fetch-Site':'same-origin','Cookie':'__Host-mxmed_session='+tokens['director'],'Content-Type':'application/json'}
 if csrf:h['X-CSRF-Token']=csrf
 try:c.request('GET' if body is None else 'POST',path,body=None if body is None else json.dumps(body),headers=h);r=c.getresponse();return r.status,json.loads(r.read())
 finally:c.close()
csrf=request('/api/internal/team/csrf.php')[1]['csrf_token'];caps=['public_profile_correction_review','public_profile_correction_apply'];target=records['bruno']['account'];given=[]
try:
 for cap in caps:
  current=request('/api/internal/team/read.php?target='+target)[1]['items'][0]['grants']
  if cap not in current:assert request('/api/internal/team/capability.php',{'operation':'grant','target':target,'capability':cap},csrf)[0]==200;given.append(cap)
 result=subprocess.run(['php','scripts/ase-adm02-panel07-security.php'],cwd=root,env=env,text=True,capture_output=True);assert result.returncode==0,result.stderr
 answer=json.loads(result.stdout);check('Existing correction consumers and MFA contract deny Advisor even with canonical stored grants',answer=={'review':'DENIED_403','apply':'DENIED_403','routine_review':False,'routine_apply':False})
finally:
 for cap in given:assert request('/api/internal/team/capability.php',{'operation':'revoke','target':target,'capability':cap},csrf)[0]==200
check('Synthetic negative-test grants revoked canonically',not set(caps)&set(request('/api/internal/team/read.php?target='+target)[1]['items'][0]['grants']))
(root/'docs/evidence/ase-adm02-panel07/recovery.json').write_text(json.dumps({'result':'PASS','checks':checks,'check_count':len(checks)},indent=2)+'\n')
