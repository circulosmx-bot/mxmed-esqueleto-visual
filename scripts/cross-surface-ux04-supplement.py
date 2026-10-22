#!/usr/bin/env python3
"""Focused real HTTP/UI tests. Evidence remains owned/private, never committed personal data."""
import pathlib,json,sys,importlib.util,concurrent.futures,ssl,urllib.request,urllib.error
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-')
config=json.loads((o/'administration-source.json').read_text());origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];ref=config['origin']+':'+config['staff']['qa_target']['physician_administrative_read'][0]
out=o/'ux04-evidence'/'supplement';out.mkdir(parents=True,exist_ok=True);checks=[]
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
 if not box.is_visible():d.locator('[data-expand="use-'+('media' if cap.startswith('media_') else 'operators' if cap=='physician_operator_support_read' else 'profiles')+'"]').click()
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
 b=pw.chromium.launch();d=b.new_page(ignore_https_errors=True);a=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900})
 native.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo');native.login(a,origin,'qa_target@example.test',password,'/internal/staff-workspace/index.php')
 grant(d,'media_review_request_replacement',True)
 search(a,'Aurora');choose(a,'workspace_aurora');a.locator('#tab-images').click();a.locator('#media-items button').first.click();a.locator('#request-replacement').click();a.locator('#replacement-reason').select_option('QUALITY_INSUFFICIENT');a.locator('#replacement-feedback').fill('Ensayo sintético de borrador; no enviar.')
 def dismiss(dialog):dialog.dismiss()
 a.once('dialog',dismiss);a.locator('#change-physician').click();expect(a.locator('#physician-workspace')).to_be_visible();expect(a.locator('#replacement-feedback')).to_have_value('Ensayo sintético de borrador; no enviar.');check('Declining discard preserves work and draft',True)
 a.once('dialog',lambda dialog:dialog.accept());a.locator('#change-physician').click();expect(a.locator('#physician-workspace')).to_be_hidden();expect(a.locator('#replacement-feedback')).to_have_value('');check('Accepting discard clears draft and hides work',True)
 posts=[];a.on('request',lambda req:posts.append(req.url) if req.method=='POST' else None)
 a.locator('#send-replacement').evaluate('(button)=>button.click()');a.wait_for_timeout(100);check('Hidden old action cannot post in search mode',not posts)
 search(a,'Aurora');choose(a,'workspace_aurora');a.locator('#tab-images').click();a.locator('#media-items button').first.click();a.locator('#request-replacement').click();a.locator('#replacement-reason').select_option('QUALITY_INSUFFICIENT');a.locator('#replacement-feedback').fill('Ensayo sintético de operación pendiente.')
 delayed=[]
 def hold(route):
  if route.request.method=='POST':delayed.append(route)
  else:route.continue_()
 a.route('**/media.php?*',hold);a.locator('#send-replacement').click();a.wait_for_timeout(200)
 a.locator('#change-physician').click();expect(a.locator('#physician-workspace')).to_be_visible();expect(a.locator('#physician-name')).to_contain_text('Aurora');check('Pending mutation prevents context change',bool(delayed))
 # Abort before sending to server: no synthetic business record consumed.
 for route in delayed:route.abort()
 a.unroute('**/media.php?*',hold);expect(a.locator('#send-replacement')).to_be_enabled(timeout=20000)
 a.once('dialog',lambda dialog:dialog.accept());a.locator('#change-physician').click();search(a,'Leticia');choose(a,ref);locations(a)
 grant(d,'media_review_request_replacement',False)
 grant(d,'physician_locations_read',False);a.evaluate('window.dispatchEvent(new Event("focus"))');expect(a.locator('#offices-panel')).to_be_hidden(timeout=20000);check('Revocation hides already open locations UI',True)
 grant(d,'physician_locations_read',True);a.evaluate('window.dispatchEvent(new Event("focus"))');expect(a.locator('#offices-content article')).to_have_count(3,timeout=20000)
 search(a,'Leticia');a.screenshot(path=str(out/'exclusive-search.png'),full_page=True);choose(a,ref);locations(a);b.close()
# Read-only audit/no-copy verification against the owned synthetic database only.
import pymysql
env=json.loads((o/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(o/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'])
with db.cursor() as c:
 c.execute('SELECT COUNT(*) FROM profiles_doctors WHERE doctor_id=%s',(ref.split(':',1)[1],));check('No original profile copied into synthetic schema',c.fetchone()[0]==0)
 c.execute("SELECT COUNT(*) FROM platform_audit_events WHERE outcome='SUCCESS' AND resource_reference=%s AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$._audit_v1.source_route'))='physician-administration/admin_locations'",(ref,));check('Canonical audit records exact location read',c.fetchone()[0]>0)
 c.execute("SELECT COUNT(*) FROM platform_audit_events WHERE outcome='DENIED' AND resource_reference='physician-administration'");check('Canonical denial audit present',c.fetchone()[0]>0)
db.rollback();db.close();print('UX04_SUPPLEMENT_PASS',len(checks),flush=True)
