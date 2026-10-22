#!/usr/bin/env python3
"""Focused real HTTP/UI tests. Evidence remains owned/private, never committed personal data."""
import pathlib,json,sys,importlib.util,concurrent.futures,ssl,urllib.request,urllib.error
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
config=json.loads((o/'administration-source.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];ref=config['origin']+':'+config['staff']['qa_target']['physician_administrative_read'][0]
out=o/'link03-evidence';out.mkdir(exist_ok=True);checks=[]
def check(n,v):
 checks.append({'name':n,'pass':bool(v)});(out/'checks.json').write_text(json.dumps(checks,indent=2));print(('PASS ' if v else 'FAIL ')+n,flush=True);assert v,n
spec=importlib.util.spec_from_file_location('native',ROOT/'scripts/ase-advisor-workspace-review.py');native=importlib.util.module_from_spec(spec);spec.loader.exec_module(native)
password=(o/'fixture-password').read_text().strip()
def api(page,mode='admin_summary',**values):
 from urllib.parse import urlencode
 if mode!='admin_search' and 'profile_ref' not in values:values['profile_ref']=ref
 return page.evaluate('async path=>{const r=await fetch(path,{cache:"no-store"});return {status:r.status,body:await r.json()}}','/api/internal/workspace/requests.php?'+urlencode({'mode':mode,**values}))
def grant(d,cap,on):
 d.goto(origin+'/internal/media-review/index.php?section=equipo');expect(d.locator('#filter-advisors')).to_be_enabled();d.locator('#filter-advisors').click();expect(d.locator('#directory-content')).to_have_attribute('aria-busy','false');d.locator('[data-member="qa_target"]').click();box=d.locator('[data-capability="'+cap+'"]')
 for det in box.locator('xpath=ancestor::details').all():
  if not det.evaluate('(e)=>e.open'):det.locator('summary').first.click()
 if not box.is_visible():d.locator('[data-expand="use-'+('operators' if cap=='physician_operator_support_read' else 'profiles')+'"]').click()
 expect(box).to_be_enabled();box.set_checked(on)
 if d.locator('#save-permissions').is_enabled():d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
 check('Director canonical '+('grant ' if on else 'revoke ')+cap,True)
def select(a):
 a.goto(origin+'/internal/staff-workspace/index.php');expect(a.locator('#staff-role')).to_have_text('Asesor');expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
 a.locator('#physician-search').fill('Leticia');a.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()');a.locator('[data-doctor-id="'+ref+'"]').click();expect(a.locator('#physician-name')).to_have_text('Leticia Muñoz Romo',timeout=10000)
with sync_playwright() as pw:
 b=pw.chromium.launch();d=b.new_page(ignore_https_errors=True,viewport={'width':1366,'height':900});a=b.new_page(ignore_https_errors=True,viewport={'width':1366,'height':900});errors=[];a.on('pageerror',lambda e:errors.append(str(e)))
 native.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php')
 grant(d,'physician_administrative_read',False);grant(d,'physician_operator_support_read',False)
 check('Unauthorized discovery denied',api(a,'admin_search')['status']==403)
 grant(d,'physician_administrative_read',True)
 r=api(a,'admin_search',query='Leticia');(out/'initial-response.json').write_text(json.dumps(r,ensure_ascii=False,indent=2));check('Authorized search returns original qualified reference',r['status']==200 and r['body']['data']['physicians'][0]['doctor_id']==ref)
 r=api(a);check('Source identity and specialty',r['status']==200 and r['body']['data']['physician']['name']=='Leticia Muñoz Romo' and r['body']['data']['physician']['specialty']=='Endocrinología')
 check('Locations source-backed and publication honest',len(r['body']['data']['physician']['consultorios'])==3 and r['body']['data']['physician']['publication_verified'] is False)
 check('Independent operator grant denied',api(a,'admin_operators')['status']==403)
 grant(d,'physician_operator_support_read',True);r=api(a,'admin_operators');check('Canonical collaborator summary',r['status']==200 and any(m['role_code']=='collaborator' and m['status']=='active' for m in r['body']['data']['members']))
 check('No inferred owner or unsupported invitations',r['body']['data']['owner_state']=='unavailable' and not r['body']['data']['invitations_available'])
 check('Wrong source denied',api(a,profile_ref='other:1')['status']==403);check('Unqualified identifier denied',api(a,profile_ref='1')['status']==403);check('Out-of-scope identifier denied',api(a,profile_ref=config['origin']+':2')['status']==403)
 check('Server rejects oversized page',api(a,'admin_search',limit=21)['status']==400)
 check('Search prefix bound on server',api(a,'admin_search',query='NotTheReference')['body']['data']['physicians']==[])
 check('Cursor excludes previous record',api(a,'admin_search',after=ref.split(':')[1])['body']['data']['physicians']==[])
 select(a);check('Chromium search and context',True);a.locator('#tab-operators').click();expect(a.locator('#operators-content')).to_contain_text('Colaborador');check('Chromium operator independent read',True)
 a.locator('#tab-summary').click();expect(a.locator('#summary-facts')).to_contain_text('Identificador canónico');check('15 navigation tabs retained',a.locator('#section-tabs [role="tab"]').count()==15)
 for w,h in [(1366,900),(820,1180),(390,844)]:
  a.set_viewport_size({'width':w,'height':h});check('Responsive containment '+str(w),a.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));a.screenshot(path=str(out/('advisor-'+str(w)+'.png')),full_page=True)
 a.set_viewport_size({'width':1366,'height':900})
 grant(d,'physician_operator_support_read',False);check('Live operator revocation',api(a,'admin_operators')['status']==403);check('Discovery survives operator revocation',api(a)['status']==200)
 grant(d,'physician_administrative_read',False);check('Live summary revocation',api(a)['status']==403);a.evaluate('window.dispatchEvent(new Event("focus"))');expect(a.locator('#physician-workspace')).to_be_hidden(timeout=20000);check('Revoked open context cleared',True)
 grant(d,'physician_administrative_read',True);grant(d,'physician_operator_support_read',True);select(a)
 # Configured source failure: no original service changes, no credential mutations.
 saved=(o/'administration-source.json').read_bytes();broken=dict(config);broken['secret_file']=str(o/'missing-source-config')
 try:
  (o/'administration-source.json').write_text(json.dumps(broken));check('Missing source configuration fails closed',api(a)['status']==503)
  media=a.request.get(origin+'/api/internal/workspace/read.php?doctor_id=workspace_aurora');check('Source failure leaves synthetic reader operational',media.status==200)
 finally:(o/'administration-source.json').write_bytes(saved)
 scope=json.loads(saved);scope['staff']['qa_target']['physician_administrative_read']=[]
 try:
  (o/'administration-source.json').write_text(json.dumps(scope));check('Live resource scope revocation',api(a)['status']==403)
 finally:(o/'administration-source.json').write_bytes(saved)
 # Independent browser sessions and concurrent current reads, each new HTTP request.
 cookie='; '.join(c['name']+'='+c['value'] for c in a.context.cookies());url=origin+'/api/internal/workspace/requests.php?mode=admin_summary&profile_ref='+ref
 def read(_):
  req=urllib.request.Request(url,headers={'Cookie':cookie});return urllib.request.urlopen(req,context=ssl._create_unverified_context(),timeout=15).status
 with concurrent.futures.ThreadPoolExecutor(max_workers=4) as pool:check('Four concurrent clients / twelve reads',list(pool.map(read,range(12)))==[200]*12)
 check('No JavaScript errors',not errors)
 check('Development six-surface index available',d.request.get(origin+'/development/interfaces.php').status==200)
 a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php*');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');select(a);check('Advisor manual logout and relogin',True)
 b.close()
(out/'result.json').write_text(json.dumps({'pass':True,'checks':len(checks),'production_load_test':False}));print('LINK03_BROWSER_PASS',len(checks))
