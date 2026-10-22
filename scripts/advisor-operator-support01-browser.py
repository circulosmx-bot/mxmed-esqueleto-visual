#!/usr/bin/env python3
"""Targeted support UI/security checks against an explicitly owned review."""
import pathlib,json,sys,importlib.util,pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def run(owned):
 o=pathlib.Path(owned).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
 approval=json.loads((o/'workflow-approval.json').read_text());assert approval['root']==str(ROOT)
 env=json.loads((o/'review-env.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];assert origin.startswith('https://127.0.0.1:')
 out=o/'support-evidence';out.mkdir(exist_ok=True);results=[]
 def check(n,v):
  results.append({'name':n,'pass':bool(v)});(out/'checks.json').write_text(json.dumps(results,indent=2));print(('PASS ' if v else 'FAIL ')+n,flush=True);assert v,n
 db=pymysql.connect(unix_socket=str(o/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  assert q.lstrip().upper().startswith('SELECT ')
  with db.cursor() as c:c.execute(q,args or None);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==(o/'mysql').resolve()
 spec=importlib.util.spec_from_file_location('native',ROOT/'scripts/ase-advisor-workspace-review.py');native=importlib.util.module_from_spec(spec);spec.loader.exec_module(native)
 password=(o/'fixture-password').read_text().strip();cap='physician_operator_support_read';profile='op01_support'
 def api(page,doctor=profile,extra=''):
  return page.evaluate('async path=>{const r=await fetch(path,{cache:"no-store"});return {status:r.status,body:await r.json()}}','/api/internal/workspace/requests.php?mode=operators&doctor_id='+doctor+extra)
 def grant(d,on):
  d.goto(origin+'/internal/media-review/index.php?section=equipo');expect(d.locator('#filter-advisors')).to_be_enabled();d.locator('#filter-advisors').click();expect(d.locator('#directory-content')).to_have_attribute('aria-busy','false');d.locator('[data-member="qa_target"]').click()
  box=d.locator('[data-capability="'+cap+'"]')
  for det in box.locator('xpath=ancestor::details').all():
   if not det.evaluate('(e)=>e.open'):det.locator('summary').first.click()
  if not box.is_visible():d.locator('[data-expand="use-operators"]').click()
  expect(box).to_be_enabled();box.set_checked(on)
  if d.locator('#save-permissions').is_enabled():d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
  check('Director UI '+('grant' if on else 'revoke'),bool(sql("SELECT 1 FROM internal_operator_grants WHERE account_id='qa_target' AND capability=%s AND status='ACTIVE' AND revoked_at IS NULL",(cap,)))==on)
 def select(a,doctor=profile):
  a.goto(origin+'/internal/staff-workspace/index.php');expect(a.locator('#staff-role')).to_have_text('Asesor');expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000);a.locator('[data-doctor-id="'+doctor+'"]').first.click();expect(a.locator('#physician-name')).to_be_visible();a.locator('#tab-operators').click();expect(a.locator('#operators-content')).to_contain_text('Titular del perfil',timeout=20000)
 fixture=json.loads((o/'support-fixture.json').read_text());check('Canonical owner-only writer denials',all(fixture['owner_only_denials'].values()));check('Canonical third collaborator position denied',fixture['third_position_denied'])
 with sync_playwright() as pw:
  browser=pw.chromium.launch();d=browser.new_page(ignore_https_errors=True,viewport={'width':1366,'height':850});a=browser.new_page(ignore_https_errors=True,viewport={'width':1366,'height':850});errors=[];a.on('pageerror',lambda e:errors.append(str(e)))
  native.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');check('Independent Advisor authenticated',a.url.endswith('/internal/staff-workspace/index.php'))
  baseline=sql("SELECT event_id,event_hash FROM platform_audit_events WHERE resource_reference LIKE 'op01_%' ORDER BY event_id")
  grant(d,False);check('Support denied without explicit capability',api(a)['status']==403)
  grant(d,True);select(a);d.screenshot(path=str(out/'director.png'));a.screenshot(path=str(out/'advisor-desktop.png'),full_page=True)
  data=api(a);check('Authorized support HTTP 200',data['status']==200);v=data['body']['data'];check('Exact selected physician binding',v['doctor_id']==profile);check('Canonical owner reference',v['owner']['account_id']=='owner_a' and v['owner_state']=='confirmed')
  statuses={i['display_status'] for i in v['invitations']};check('Pending accepted revoked invitations',statuses=={'pending','accepted','cancelled'})
  m=next(m for m in v['members'] if m['role_code']=='collaborator');check('Canonical alias and delegated functions',m['alias']=='Lucía' and m['delegated_functions']==['agenda.appointments.read'] and m['contact_verified'])
  check('Canonical two-position count',v['seats']=={'limit':2,'occupied':1,'reserved':1,'available':0,'consistent':True})
  overview=fixture['owner_overview'];check('Advisor matches canonical owner overview',overview['occupied']==v['seats']['occupied'] and overview['reserved']==v['seats']['reserved'] and overview['members'][0]['membership_id']==m['membership_id'] and overview['members'][0]['capabilities']==m['delegated_functions'])
  check('No support mutation actions',v['support_actions']==[]);check('Canonical operator event references',len(v['events'])>=6 and all(sql('SELECT 1 FROM platform_audit_events WHERE event_id=%s AND resource_reference=%s',(e['event_id'],profile)) for e in v['events']))
  raw=json.dumps(v);check('No secrets or clinical payload',all(k not in raw for k in ['token_hash','password','email_normalized','credential_version','patient_id','clinical_document','offered_capabilities']))
  check('Out-of-scope physician denied',api(a,'workspace_out_of_scope')['status']==403);check('Cross-context injected resource rejected',api(a,profile,'&request_id=foreign')['status']==400)
  empty=api(a,'workspace_bruno');check('Accurate empty relationships',empty['status']==200 and not empty['body']['data']['members'] and not empty['body']['data']['invitations'])
  states=api(a,'op01_support_states')['body']['data'];check('Suspended and revoked memberships',{'suspended','revoked'}.issubset({m['status'] for m in states['members']}) and states['seats']['occupied']==1)
  hist=api(a,'op01_support_history')['body']['data'];check('Accepted not relabeled expired; pending expiry derived',{'accepted','expired'}=={i['display_status'] for i in hist['invitations']})
  post=a.evaluate('async()=>{let r=await fetch("/api/internal/workspace/requests.php",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify({action:"invite",input:{}})});return r.status}');check('Owner mutation unavailable through support HTTP',post==403)
  grant(d,False);check('Live revocation direct request denied',api(a)['status']==403);a.locator('#refresh-operators').click();expect(a.locator('#operators-content')).to_be_empty();expect(a.locator('#operators-message')).to_contain_text('no está autorizada');check('Open view clears revoked data',True)
  media=a.evaluate('async()=>{const r=await fetch("/api/internal/workspace/read.php?doctor_id=op01_support");return r.status}');check('Existing Media read survives support revocation',media==200)
  grant(d,True);select(a);check('Prior canonical operator history immutable',baseline==sql("SELECT event_id,event_hash FROM platform_audit_events WHERE resource_reference LIKE 'op01_%' ORDER BY event_id"))
  check('Grant and revoke canonical audit present',len(sql("SELECT event_id FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND resource_reference='qa_target' AND metadata_json LIKE %s",('%'+cap+'%',)))>=3)
  for w,h in [(1366,850),(820,1180),(390,844)]:
   a.set_viewport_size({'width':w,'height':h});check('Chromium contained '+str(w),a.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));a.screenshot(path=str(out/('chromium-'+str(w)+'.png')),full_page=True)
  check('Chromium JavaScript errors absent',not errors)
  a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php*');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');select(a);check('Advisor logout/login support preserved',True)
  dev=d.request.get(origin+'/development/interfaces.php');check('Development index remains available',dev.status==200)
  browser.close()
  browser=pw.webkit.launch();a=browser.new_page(ignore_https_errors=True,viewport={'width':390,'height':844});native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');select(a);check('WebKit independent authorized read',api(a)['status']==200);check('WebKit scope denial',api(a,'workspace_out_of_scope')['status']==403);check('WebKit mobile containment',a.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));a.screenshot(path=str(out/'webkit-mobile.png'),full_page=True);browser.close()
 db.close();(out/'result.json').write_text(json.dumps({'checks':results,'result':'PASS','visual_acceptance':'PENDING','cross_surface':'canonical owner overview and exact records; physician/operator browsers not enabled'},indent=2));print('SUPPORT_QA_PASS',len(results),flush=True)
if __name__=='__main__':run(sys.argv[1])
