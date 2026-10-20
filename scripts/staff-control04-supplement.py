#!/usr/bin/env python3
"""Directed security, login, navigation and cross-surface smoke; owned target only."""
import pathlib,sys,json,subprocess,importlib.util,time,uuid
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(owned):
 review=module('staff04_review_supplement',ROOT/'scripts/staff-control04-review.py');owned,approval=review.owned_configuration(owned);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text());conn=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(query,args=()):
  with conn.cursor() as c:c.execute(query,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 helpers=module('staff04_supplement_helpers',ROOT/'scripts/ase-adm02-oper09-browser.py');wf=helpers.wf
 native=module('staff04_supplement_login',ROOT/'scripts/ase-advisor-workspace-review.py')
 out=ROOT/'docs/evidence/staff-control04';shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 results={'checks':[],'responsive':[],'page_errors':[]}
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});(out/'supplement.json').write_text(json.dumps(results,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
 def good(p,action,body):
  r=wf(p,action,body);assert r['status']==200,(action,r['status']);return r['body']
 with sync_playwright() as pw:
  b=pw.chromium.launch();dc=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});d=dc.new_page();d.on('pageerror',lambda e:results['page_errors'].append(str(e)))
  for attempt in range(2):
   # No next parameter: reproduces the actual former failure, not the bootstrap route.
   d.goto(origin+'/public/identity/acceso.php');subprocess.run([sys.executable,str(ROOT/'scripts/staff-control04-review.py'),str(owned),'credentials-director'],check=True,capture_output=True)
   password=subprocess.check_output(['pbpaste']).decode();assert password==(owned/'fixture-password').read_text().strip()
   d.locator('#login-email').fill('qa_director@example.test');d.locator('#login-password').fill(password);del password;d.locator('[data-submit]').click();d.wait_for_url(origin+review.DIRECTOR);expect(d.locator('#add-member')).to_be_enabled()
   check('Manual Director login without next using documented clipboard command '+str(attempt+1),True)
   if attempt==0:d.locator('#team-logout').click() if d.locator('#team-logout').count() else review.canonical_logout(d)
  check('Original tmp and private tmp identify the same owned preview',pathlib.Path('/tmp/'+owned.name).resolve()==owned)
  check('Requested real account was not imported',not sql('SELECT account_id FROM auth_accounts WHERE email_address=%s',('circulodigital@msn.com',)))
  # Pending synthetic candidate left for visual review. All mutations use canonical HTTP.
  casefile=owned/'staff04-pending.json'
  if not casefile.exists():
   d.locator('#add-member').click();d.locator('#new-given_names').fill('Lucía Ficticia');d.locator('#new-first_surname').fill('Pendiente');d.locator('#new-email').fill('lucia_staff04@example.test')
   expect(d.locator('#new-operation_state option')).to_have_count(33)
   for field,value in [('new-operation_state','Aguascalientes'),('new-operation_city','Aguascalientes')]:
    d.locator('#'+field+'-trigger').click();d.get_by_role('option',name=value,exact=True).click()
   d.locator('#save-onboarding').click();expect(d.locator('#onboarding-feedback')).to_contain_text('Borrador guardado')
   pending=sql("SELECT c.case_id FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE v.first_surname='Pendiente'")[0][0];casefile.write_text(json.dumps({'case':pending}));d.locator('#cancel-onboarding').click()
  pending=json.loads(casefile.read_text())['case'];d.reload();expect(d.locator('[data-case="'+pending+'"]').first).to_be_visible()
  case=good(d,'case',{'case_id':pending});check('Selected canonical location survives save and reload',case['profile']['operation_state']=='Aguascalientes' and case['profile']['operation_city']=='Aguascalientes')
  bad=wf(d,'update',{'case_id':pending,'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'changes':{'operation_city':'Municipio inexistente STAFF04'}})
  check('Server rejects invalid state municipality pair without new personal version',bad['status']==400 and good(d,'case',{'case_id':pending})['version']==case['version'])
  intentions=good(d,'intentions',{'case_id':pending});saved=good(d,'prepare_intentions',{'case_id':pending,'expected_case_version':case['version'],'expected_version':intentions['version'],'capabilities':['media_review_read'],'operation_id':str(uuid.uuid4())})
  denied=wf(d,'activate_intention',{'case_id':pending,'expected_case_version':case['version'],'expected_version':saved['version'],'capability':'media_review_read'})
  check('Pending case cannot activate prepared permissions',denied['status'] in [400,403,409] and not sql('SELECT account_id FROM internal_staff WHERE account_id=(SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s)',(pending,)))
  stale=wf(d,'prepare_intentions',{'case_id':pending,'expected_case_version':case['version'],'expected_version':intentions['version'],'capabilities':[],'operation_id':str(uuid.uuid4())})
  check('Concurrent stale intention version cannot overwrite latest configuration',stale['status']==409)
  ac=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});a=ac.new_page();a.on('pageerror',lambda e:results['page_errors'].append(str(e)))
  native.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR)
  expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  check('Advisor session remains independent of Director',good(d,'bootstrap',{})['director'] and not good(a,'bootstrap',{})['director'])
  check('Advisor cannot read or change pending intentions',wf(a,'intentions',{'case_id':pending})['status']==403 and wf(a,'prepare_intentions',{'case_id':pending,'expected_case_version':case['version'],'expected_version':saved['version'],'capabilities':[],'operation_id':str(uuid.uuid4())})['status']==403)
  a.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();expect(a.locator('#physician-name')).to_contain_text('Aurora');check('All fifteen support domains remain discoverable',a.locator('#section-tabs [role=tab]').count()==15 and a.locator('#more-menu button').count()==15)
  a.locator('#more-toggle').click();a.locator('#more-menu [data-section="operators"]').click();check('Unconnected operator support does not expose owner commands',a.locator('#restricted-panel').is_visible() and 'no está conectado' in a.locator('#restricted-message').inner_text() and a.locator('#restricted-panel input, #restricted-panel form').count()==0)
  # One actual authorized Media mutation, checked against canonical state and history.
  photo=sql("SELECT submission_id,review_status FROM media_review_submissions WHERE owner_id='workspace_aurora' AND purpose='DOCTOR_PROFILE_PHOTO'")[0]
  if photo[1]=='PENDING_REVIEW':
   a.locator('#more-toggle').click();a.locator('#more-menu [data-section="images"]').click();a.locator('[data-submission-id="'+photo[0]+'"]').last.click();expect(a.locator('#approve-image')).to_be_enabled();a.once('dialog',lambda dialog:dialog.accept());a.locator('#approve-image').click();expect(a.locator('#media-items [data-submission-id="'+photo[0]+'"]').last).to_have_count(0)
  check('Authorized Advisor Media decision reaches canonical APPROVED state',sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo[0],))[0][0]=='APPROVED')
  check('Media approval does not publish hidden physician profile',sql("SELECT profile_status FROM profiles_doctors WHERE doctor_id='workspace_aurora'")[0][0]=='hidden')
  a.locator('#more-toggle').click();a.locator('#more-menu [data-section="history"]').click();expect(a.locator('.history-entry')).not_to_have_count(0);check('Media operation appears in authorized canonical history',True)
  for page in [d,a]:
   page.locator('#development-interface-index summary').click();check('Six actual interfaces represented with session-scoped links',page.locator('#development-interface-index li').count()==6 and page.locator('#development-interface-index li a').count()==1);page.locator('#development-interface-index summary').click()
  d.screenshot(path=str(shots/'director-pending.png'));a.screenshot(path=str(shots/'advisor-history.png'))
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine).launch()
   for width,height in [(1440,900),(1366,768),(390,844)]:
    ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=ctx.new_page();page.on('pageerror',lambda e:results['page_errors'].append(str(e)))
    native.login(page,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR);expect(page.locator('#discovery-list')).to_have_attribute('aria-busy','false');page.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click()
    page.locator('#more-toggle').click();expect(page.locator('#more-menu')).to_be_visible();page.locator('#more-menu [data-section="account"]').click();expect(page.locator('#restricted-panel')).to_be_visible()
    page.locator('#development-interface-index summary').click();page.screenshot(path=str(shots/(engine+'-'+str(width)+'.png')))
    fits=page.evaluate('document.documentElement.scrollWidth<=innerWidth');check(engine+' responsive navigation and index '+str(width),fits and page.locator('#section-tabs [role=tab]').count()==15)
    results['responsive'].append({'engine':engine,'width':width,'height':height,'pass':fits});ctx.close()
   browser.close()
  check('No browser JavaScript exceptions',not results['page_errors'])
  b.close()
 conn.close();results['result']='PASS';(out/'supplement.json').write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1])
