#!/usr/bin/env python3
"""Additional focused assertions: dismissal in flight and existing-invitation reconciliation.
Requires the exact owned PANEL05 preview; never connects to an original database.
"""
import pathlib,json,sys,os,time,hashlib
import pymysql
from playwright.sync_api import sync_playwright,expect
root=pathlib.Path(__file__).resolve().parents[1];owned=pathlib.Path(sys.argv[1]).resolve()
assert owned.parent==pathlib.Path('/private/tmp') and owned.name.startswith('mxmed-ase-adm02imp01-')
assert owned.stat().st_uid==os.getuid() and owned.stat().st_mode&0o777==0o700
a=json.loads((owned/'workflow-approval.json').read_text());assert a['root']==str(root) and a['expires']>time.time()
assert all(hashlib.sha256((root/f).read_bytes()).hexdigest()==h for f,h in a['source_hashes'].items())
env=json.loads((owned/'review-env.json').read_text());assert env['MXMED_DB_HOST']=='127.0.0.1' and env['MXMED_DB_NAME']==a['database']
db=pymysql.connect(host='127.0.0.1',port=int(env['MXMED_DB_PORT']),user=env['MXMED_DB_USER'],password=env['MXMED_DB_PASS'],database=env['MXMED_DB_NAME'],autocommit=True)
def sql(q,args=()):
 with db.cursor() as c:c.execute(q,args);return c.fetchall()
assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
tokens=json.loads((owned/'sessions.json').read_text());origin=env['MXMED_PREVIEW_ORIGIN'];results=[]
def check(label,ok):assert ok,label;results.append({'name':label,'pass':True});print('PASS '+label)
with sync_playwright() as pw:
 b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True,viewport=dict(width=1440,height=900));c.add_cookies([dict(name='__Host-mxmed_session',value=tokens['director'],url=origin,secure=True,httpOnly=True,sameSite='Strict')]);p=c.new_page();p.goto(origin+'/internal/media-review/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();p.locator('#new-given_names').fill('Persistencia PANEL05');p.locator('#new-first_surname').fill('Demostración');p.locator('#new-email').fill('persistencia@example.test')
 for w,h in [(1440,900),(1366,768),(390,844)]:
  p.set_viewport_size(dict(width=w,height=h));p.screenshot(path=str(root/f'docs/evidence/ase-adm02-panel05/screens/chromium-{w}-wizard.png'),full_page=True);check('Wizard geometry '+str(w),p.locator('#onboarding').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'))
 p.set_viewport_size(dict(width=1440,height=900))
 for n in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(n))
 held=[]
 def hold(route):
  if route.request.post_data_json['action']=='issue':held.append(route)
  else:route.continue_()
 p.route('**/api/internal/staff-admission/workflow.php',hold);p.locator('#next-onboarding').click();expect(p.locator('#next-onboarding')).to_be_disabled();p.wait_for_timeout(200);assert len(held)==1
 operation=held[0].request.post_data_json['input'];p.keyboard.press('Escape');expect(p.locator('#onboarding')).to_be_hidden();expect(p.locator('#add-member')).to_be_focused()
 # APIRequestContext lacks Fetch Metadata; retain the actual same-origin browser transport context.
 response=held[0].fetch(headers={**held[0].request.all_headers(),'Sec-Fetch-Site':'same-origin'});assert response.status==200
 result=response.json();held[0].fulfill(response=response);p.unroute('**/api/internal/staff-admission/workflow.php',hold);p.wait_for_timeout(300)
 check('In-flight dismissal commits exactly one invitation under the same operation',sql('SELECT COUNT(*) FROM auth_staff_admission_invitation_operations WHERE operation_id=%s',(operation['operation_id'],))[0][0]==1 and sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(result['case_id'],))[0][0]==1)
 p.locator('#status-trigger').click();p.locator('[data-value="PENDING"]').click();p.locator('#advisor-search').fill('Persistencia PANEL05');expect(p.locator('[data-case]')).to_have_count(1);p.locator('[data-case]').click();expect(p.locator('#staff-case-content')).to_contain_text('Invitación: registrada');p.locator('#resume-preparation').click();p.locator('#new-email').fill('persistencia@example.test')
 for n in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(n))
 p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada en el entorno sintético')
 check('Existing invitation reconciled without duplicate or changed case version',sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(result['case_id'],))[0][0]==1 and sql('SELECT version FROM auth_staff_admission_cases WHERE case_id=%s',(result['case_id'],))[0][0]==1)
 b.close()
db.close();(root/'docs/evidence/ase-adm02-panel05/smoke.json').write_text(json.dumps({'result':'PASS','checks':results,'check_count':len(results)},indent=2)+'\n')
