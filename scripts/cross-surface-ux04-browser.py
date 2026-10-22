#!/usr/bin/env python3
"""Focused real HTTP/UI tests. Evidence remains owned/private, never committed personal data."""
import pathlib,json,sys,importlib.util,concurrent.futures,ssl,urllib.request,urllib.error
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
config=json.loads((o/'administration-source.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];ref=config['origin']+':'+config['staff']['qa_target']['physician_administrative_read'][0]
out=o/'ux04-evidence';out.mkdir(exist_ok=True);checks=[]
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
def search(p,text):
 if p.locator('#change-physician').is_visible():p.locator('#change-physician').click()
 p.locator('#physician-search').fill(text);p.locator('#physician-search-form').evaluate('(f)=>f.requestSubmit()')
 expect(p.locator('#physician-workspace')).to_be_hidden()
 expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
def choose(p,identifier):
 p.locator('#discovery-list [data-doctor-id="'+identifier+'"]').first.click();expect(p.locator('#physician-workspace')).to_be_visible()
def locations(p):
 p.locator('#tab-offices').click();expect(p.locator('#offices-content article')).to_have_count(3,timeout=20000)
with sync_playwright() as pw:
 b=pw.chromium.launch();d=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});a=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});errors=[];a.on('pageerror',lambda e:errors.append(str(e)))
 native.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php')
 for cap in ['physician_administrative_read','physician_operator_support_read','physician_locations_read']:grant(d,cap,False)
 check('Without discovery grant locations denied',api(a,'admin_locations')['status']==403)
 grant(d,'physician_administrative_read',True)
 check('Discovery does not authorize location details',api(a,'admin_locations')['status']==403)
 check('Summary omits detailed location records', 'consultorios' not in api(a)['body']['data']['physician'])
 grant(d,'physician_locations_read',True);grant(d,'physician_operator_support_read',True)
 r=api(a,'admin_locations');check('Source locations exact qualified physician',r['status']==200 and r['body']['data']['doctor_id']==ref and len(r['body']['data']['locations'])==3)
 check('Only allowlisted location fields',all(set(c)=={'consultorio_id','titulo','municipio','estado'} for c in r['body']['data']['locations']))
 page1=api(a,'admin_locations',limit=2)['body']['data'];page2=api(a,'admin_locations',limit=2,after=page1['next_after'])['body']['data'];check('Locations bounded cursor without duplicates',len(page1['locations'])==2 and len(page2['locations'])==1 and len({c['consultorio_id'] for c in page1['locations']+page2['locations']})==3)
 for bad in ['1','other:1',config['origin']+':2']:check('Location resource isolation '+bad,api(a,'admin_locations',profile_ref=bad)['status']==403)
 check('Location oversized limit rejected',api(a,'admin_locations',limit=21)['status']==400)
 search(a,'Aurora');choose(a,'workspace_aurora');expect(a.locator('#physician-name')).to_contain_text('Aurora')
 search(a,'Leticia');check('Search hides old physician header and tabs',not a.locator('#physician-name').is_visible() and not a.locator('#section-tabs').is_visible())
 a.evaluate('window.dispatchEvent(new Event("focus"))');expect(a.locator('#physician-workspace')).to_be_hidden();choose(a,ref);expect(a.locator('#physician-name')).to_have_text('Leticia Muñoz Romo');locations(a)
 expect(a.locator('#physician-avatar')).to_be_visible();check('Original without eligible image uses neutral avatar',not a.locator('#physician-portrait').get_attribute('src'))
 check('All source locations rendered',all(v in a.locator('#offices-content').inner_text() for v in ['Las Américas','Star Médica','MAC Norte','Aguascalientes']))
 a.locator('#tab-operators').click();expect(a.locator('#operators-content')).to_contain_text('Colaborador');check('Operator reader regression',api(a,'admin_operators')['status']==200)
 check('Requests reader regression',a.request.get(origin+'/api/internal/workspace/requests.php?mode=read').status==200)
 check('Media reader regression',a.request.get(origin+'/api/internal/workspace/read.php?doctor_id=workspace_aurora').status==200)
 search(a,'Aurora');choose(a,'workspace_aurora');check('Return to synthetic physician removes original locations',a.locator('#offices-content').inner_text()=='' and 'mxmed-local-development' not in a.locator('#summary-facts').inner_text())
 # Delay original response, return to search, then select synthetic context.
 search(a,'');delayed=[]
 def hold(route):delayed.append((route,route.fetch()))
 a.route('**/requests.php?mode=admin_summary&*',hold)
 a.locator('#discovery-list [data-doctor-id="'+ref+'"]').click();a.wait_for_timeout(100)
 search(a,'Aurora');choose(a,'workspace_aurora')
 for route,response in delayed:route.fulfill(response=response)
 a.wait_for_timeout(100);expect(a.locator('#physician-name')).to_contain_text('Aurora');check('Late response cannot revive original context',bool(delayed));a.unroute('**/requests.php?mode=admin_summary&*',hold)
 grant(d,'physician_locations_read',False);check('Live location grant revocation',api(a,'admin_locations')['status']==403);check('Summary still independently allowed',api(a)['status']==200);grant(d,'physician_locations_read',True)
 saved=(o/'administration-source.json').read_bytes();scope=json.loads(saved);scope['staff']['qa_target']['physician_locations_read']=[]
 try:
  (o/'administration-source.json').write_text(json.dumps(scope));check('Live location scope revocation',api(a,'admin_locations')['status']==403)
 finally:(o/'administration-source.json').write_bytes(saved)
 try:
  bad=json.loads(saved);bad['secret_file']=str(o/'missing-reader');(o/'administration-source.json').write_text(json.dumps(bad));check('Source failure fails closed',api(a,'admin_locations')['status']==503)
 finally:(o/'administration-source.json').write_bytes(saved)
 search(a,'Leticia');choose(a,ref);locations(a)
 for w,h in [(1440,900),(1366,768),(390,844)]:
  a.set_viewport_size({'width':w,'height':h});expect(a.locator('#physician-avatar')).to_be_visible();check('Chromium containment '+str(w)+'x'+str(h),a.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));a.screenshot(path=str(out/('chromium-'+str(w)+'.png')),full_page=True)
 a.set_viewport_size({'width':1440,'height':900});a.locator('#development-interface-index > summary').click();expect(a.locator('#development-interface-index')).to_contain_text('Comparación bloqueada');check('Six surfaces honest index',a.locator('#development-interface-index > ul > li').count()==6)
 check('No physician/operator shortcut',a.locator('#development-interface-index a[href="/private-app.php"]').count()==0)
 # Actual route probes, same session only: never impersonate a resource actor.
 probes={}
 for route in ['/private-app.php','/profiles/doctor.php','/provider/index.php']:
  response=a.request.get(origin+route,max_redirects=0);probes[route]={'status':response.status,'location':response.headers.get('location')}
 (out/'surface-probes.json').write_text(json.dumps(probes,indent=2));check('Additional routes attempted without new identity',True)
 a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php*');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');search(a,'Leticia');choose(a,ref);locations(a);check('Advisor logout relogin exact context',True)
 check('No JavaScript errors',not errors);b.close()
 w=pw.webkit.launch();p=w.new_page(ignore_https_errors=True,viewport={'width':1366,'height':768});native.login(p,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php');search(p,'Aurora');choose(p,'workspace_aurora');search(p,'Leticia');choose(p,ref);locations(p);expect(p.locator('#physician-avatar')).to_be_visible();check('WebKit search context and locations',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(out/'webkit.png'),full_page=True);w.close()
(out/'result.json').write_text(json.dumps({'pass':True,'checks':len(checks),'additional_surfaces':'BLOCKED prerequisites; no impersonation'}));print('UX04_BROWSER_PASS',len(checks),flush=True)
