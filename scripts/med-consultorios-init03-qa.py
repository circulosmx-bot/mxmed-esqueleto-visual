#!/usr/bin/env python3
"""Read-only synthetic smoke; fault injection stays in owned browser contexts."""
import json,pathlib,hashlib,pymysql,sys,os,time
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
D=pathlib.Path(sys.argv[1]).resolve();S=json.loads((D/'state.json').read_text());assert D.name.startswith('mxmed-init02-') and D.stat().st_uid==os.getuid() and S['source']==str(ROOT) and S['expires']>time.time()
E=json.loads((D/'environment.json').read_text());C=json.loads((D/'credentials.json').read_text());O=json.loads((D/'state.json').read_text())['origin']
assert E['MXMED_DB_HOST']=='127.0.0.1' and int(E['MXMED_DB_PORT'])!=3306 and E['MXMED_DB_NAME']=='mxmed_gate4d_preview_adminqa_init02'
engine=sys.argv[2];assert engine in ['chromium','webkit']
db=pymysql.connect(host=E['MXMED_DB_HOST'],port=int(E['MXMED_DB_PORT']),user=E['MXMED_DB_USER'],password=E['MXMED_DB_PASS'],database=E['MXMED_DB_NAME'],autocommit=True)
def snap():
 with db.cursor() as q:
  q.execute('SELECT * FROM consultorios ORDER BY doctor_id,consultorio_id');h=hashlib.sha256(repr(q.fetchall()).encode()).hexdigest();q.execute('SELECT COUNT(*) FROM init02_write_log');return [h,q.fetchone()[0]]
before=snap();result={}
def enter(p):
 p.wait_for_load_state('load');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'")
 # Existing first-use help can open with Perfil. Let its Bootstrap transition finish.
 def dismiss_help():
  p.wait_for_timeout(400)
  if p.locator('#mx-visibility-help-modal').is_visible():
   p.locator('#mx-visibility-help-modal .modal-footer button').click()
   expect(p.locator('#mx-visibility-help-modal')).to_be_hidden()
 dismiss_help()
 sub=p.locator('button[data-panel="p-consultorio"]')
 if not sub.is_visible():p.locator('button.menu-main[data-group=perfil]').click()
 dismiss_help()
 if not sub.is_visible():p.locator('button.menu-main[data-group=perfil]').click()
 sub.click();expect(p.locator('#p-consultorio')).to_be_visible()
def state(p,value):expect(p.locator('#p-consultorio')).to_have_attribute('data-cons-load-state',value,timeout=18000)
def rows(p):return p.locator('#p-consultorio [data-consultorio-tab-item]:not([hidden]) .nav-link').evaluate_all("bs=>bs.map(b=>b.getAttribute('data-bs-target'))")
with sync_playwright() as pw:
 b=getattr(pw,engine).launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});p=c.new_page()
 profile_reads=[]
 def plain_profile(route):
  response=route.fetch();data=response.json()
  if data.get('data',{}).get('identity_public'):
   data['data']['identity_public']['display_name']='Aurora Prueba Segura';profile_reads.append(True)
  route.fulfill(response=response,json=data)
 p.route('**/api/profiles/private/doctor/qa05-doctor',plain_profile)
 p.goto(O+'/public/identity/acceso.php');p.locator('[name=email]').fill(C['owner']['email']);p.locator('[name=password]').fill(C['owner']['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php');enter(p);state(p,'READY')
 assert profile_reads, 'Canonical profile hydration was not exercised'
 assert p.evaluate("window.mxmedPrivateSession.context.professional.presentation_name")== 'Dra. Aurora Prueba Segura';expect(p.locator('.mx-gh-identity-name-text')).to_have_text('Dra. Aurora Prueba Segura');result['canonical_prefix_and_no_duplicate']='PASS'
 assert rows(p)==['#sede1','#sede2','#sede3'];assert [p.locator('#cons-titulo'+s).input_value() for s in ['', '2','3']]==['Consultorio Centro QA','Consultorio Norte QA','Consultorio Sur QA'];result['synthetic_login_first_three']='PASS'
 writes=[];p.on('request',lambda r:writes.append(r.method) if r.url.startswith(O+'/') and r.method in ['POST','PUT','PATCH','DELETE'] else None)
 auth=c.storage_state();c.close();c=b.new_context(ignore_https_errors=True,storage_state=auth,viewport={'width':1366,'height':900});p=c.new_page();p.on('request',lambda r:writes.append(r.method) if r.url.startswith(O+'/') and r.method in ['POST','PUT','PATCH','DELETE'] else None);p.route('**/api/agenda/index.php/consultorios?*',lambda r:r.fulfill(status=503,json={'ok':False}));p.goto(O+'/private-app.php',wait_until='domcontentloaded');enter(p);state(p,'ERROR');expect(p.locator('[data-cons-load-retry]')).to_be_visible();p.unroute('**/api/agenda/index.php/consultorios?*');p.locator('[data-cons-load-retry]').click();state(p,'READY');assert len(rows(p))==3;result['error_retry']='PASS'
 r=c.request.get(O+'/api/agenda/index.php/consultorios?doctor_id=1');assert r.status==403 and not r.json().get('data');result['real_physician_denied']='PASS'
 u=b.new_context(ignore_https_errors=True);r=u.request.get(O+'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor');assert r.status in [401,403] and not r.json().get('data');result['unauthenticated_denied']='PASS';u.close()
 p.wait_for_timeout(1000);assert not writes;assert before==snap();result['no_hydration_writes']='PASS';assert any(x['name']=='__Host-mxmed_init02_session' for x in c.cookies());result['isolated_cookie']='PASS';b.close()
print(json.dumps(result));(ROOT/'docs/evidence/med-consultorios-init03'/(engine+'-results.json')).write_text(json.dumps(result,indent=2))
