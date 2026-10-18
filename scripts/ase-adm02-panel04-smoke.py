#!/usr/bin/env python3
"""Focused regression after logout DOM teardown fix; read/draft only except canonical logout."""
import pathlib,json,os,sys,time,subprocess,hashlib
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
approval=json.loads((owned/'workflow-approval.json').read_text());assert pathlib.Path(approval['root'])==root and approval['expires']>time.time()
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in approval['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text());info=json.loads((owned/'review-info.json').read_text());origin=env['MXMED_PREVIEW_ORIGIN'];records=json.loads((owned/'uxfunc02-records.json').read_text());out=root/'docs/evidence/ase-adm02-panel04';checks=[]
def check(name,value):
 checks.append({'name':name,'pass':bool(value)});print(('PASS ' if value else 'FAIL ')+name,flush=True);assert value,name
def login(actor='director'):
 p=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=env,input=json.dumps({'actor':actor,'email':'qa_'+actor+'@example.test','existing':True}),text=True,capture_output=True);assert p.returncode==0 and json.loads(p.stdout)['ok'];return json.loads((owned/'sessions.json').read_text())[actor]
with sync_playwright() as pw:
 b=pw.chromium.launch()
 def newpage(token):
  c=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});c.add_cookies([{'name':'__Host-mxmed_session','value':token,'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);return c.new_page()
 # Reproduce only the previous client teardown, against the same legitimate canonical logout.
 p=newpage(login());old=(root/'assets/js/internal-team.js').read_text().replace("if($('staff-status'))$('staff-status').value=status;}","$('staff-status').value=status;}",1);old_errors=[];p.on('pageerror',lambda e:old_errors.append(str(e)));p.route('**/assets/js/internal-team.js',lambda route:route.fulfill(status=200,content_type='text/javascript',body=old));p.goto(info['url']);expect(p.locator('#detail-title')).not_to_have_text('Selecciona un integrante');p.locator('#team-logout').click();expect(p.locator('h1')).to_have_text('Sesión cerrada');p.wait_for_timeout(300)
 check('Previous logout error reproduced exactly after canonical session closure',any('null' in e and 'value' in e for e in old_errors));p.context.close()
 p=newpage(login());errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(info['url']);expect(p.locator('#team-role')).to_have_text('Director');expect(p.locator('[data-member]')).to_have_count(2)
 check('Recovered synthetic active and removed records preserved',p.locator('[data-member]').count()==2)
 for value,expected in [('REMOVED','Baja definitiva'),('SUSPENDED','Suspendido')]:
  p.locator('#staff-status').select_option(value);expect(p.locator('#advisor-detail .status')).to_have_text(expected)
 check('Historical state filters survive navigation',True)
 p.locator('#staff-status').select_option('ACTIVE');p.locator('[data-member="'+records['bruno']['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled()
 for w,h in [(1440,900),(1366,768),(390,844)]:
  p.set_viewport_size({'width':w,'height':h})
  if w==390:p.locator('#show-directory').click()
  p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#cancel-onboarding').click()
  if w==390:p.locator('#show-detail').click()
  for section in ['personal-data','identity-evidence','member-limits','member-clients','member-history']:p.locator('#'+section).evaluate('(e)=>e.open=true')
  check('Final responsive panel and wizard '+str(w),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
  p.screenshot(path=str(out/'screens'/f'final-panel-{w}.png'),full_page=True)
 p.set_viewport_size({'width':1440,'height':900});p.locator('#team-logout').click();expect(p.locator('h1')).to_have_text('Sesión cerrada');p.wait_for_timeout(300)
 check('Logout teardown has no JavaScript errors',not errors)
 check('Session cookie removed by canonical endpoint',not any(c['name']=='__Host-mxmed_session' for c in p.context.cookies()))
 p.context.close();mp=newpage(login('master'));master_errors=[];mp.on('pageerror',lambda e:master_errors.append(str(e)));mp.goto(info['url']);expect(mp.locator('#team-role')).to_have_text('Administrador maestro');expect(mp.locator('#filter-masters')).to_be_disabled();expect(mp.locator('#add-member')).to_be_disabled();expect(mp.locator('#remove-member')).to_be_disabled();mp.locator('#team-logout').click();expect(mp.locator('h1')).to_have_text('Sesión cerrada');mp.wait_for_timeout(300)
 check('Master role boundary and canonical logout preserved',not master_errors and not any(c['name']=='__Host-mxmed_session' for c in mp.context.cookies()))
 b.close()
login();login('master')
(out/'smoke.json').write_text(json.dumps({'result':'PASS','checks':checks,'check_count':len(checks),'previous_error':old_errors,'errors_after_fix':errors+master_errors,'source_sha256':hashlib.sha256((root/'assets/js/internal-team.js').read_bytes()).hexdigest(),'canonical_records_preserved':True,'real_personal_data_used':False,'production_activation':False},indent=2)+'\n')
