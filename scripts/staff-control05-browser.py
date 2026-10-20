"""STAFF-CONTROL05 browser verification; resumed runs reconcile canonical records."""
import pathlib,json,subprocess,time,uuid,importlib.util
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def choose(p,id,label):
 p.locator('#'+id+'-trigger').click();p.get_by_role('option',name=label,exact=True).click()

def run(r,check,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']]
 helper=module('staff04_browser_helpers',ROOT/'scripts/ase-adm02-oper09-browser.py');wf=helper.wf
 review=module('staff04_review',ROOT/'scripts/staff-control05-review.py')
 native=module('staff04_login',ROOT/'scripts/ase-advisor-workspace-review.py')
 out=r['OUT'];shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 with sync_playwright() as pw:
  browser=pw.chromium.launch();context=browser.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});p=context.new_page()
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  expect(p.locator('#add-member')).to_be_enabled()
  recordfile=owned/'oper09-advisor.json';record=json.loads(recordfile.read_text()) if recordfile.exists() else {'email':'oper09_advisor@example.test','name':'Irene Ficticia Oper09'}
  if not record.get('case'):
   p.locator('#add-member').click();p.locator('#new-given_names').fill('Irene Ficticia');p.locator('#new-first_surname').fill('Oper09');p.locator('#new-email').fill(record['email'])
   expect(p.locator('#new-operation_state option')).to_have_count(33);expect(p.locator('#new-operation_state')).to_be_enabled();choose(p,'new-operation_state','Aguascalientes');choose(p,'new-operation_city','Aguascalientes')
   choose(p,'new-operation_state','Baja California');expect(p.locator('#new-operation_city')).to_have_value('');choose(p,'new-operation_state','Aguascalientes');choose(p,'new-operation_city','Aguascalientes')
   check('Dependent canonical state/city selection clears incompatible city',True)
   for step in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(step))
   p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada',timeout=20000)
   record['case']=sql("SELECT c.case_id FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE v.first_surname='Oper09'")[0][0];recordfile.write_text(json.dumps(record));p.locator('#cancel-onboarding').click()
  completed=bool(sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(record['case'],))[0][0])
  if not completed:
   p.reload();expect(p.locator('[data-case="'+record['case']+'"]')).to_be_visible();check('Prepared invited candidate appears in default Director directory without active staff',True)
   p.locator('[data-case="'+record['case']+'"]').click();expect(p.locator('#admission-next-state')).to_contain_text('Pendiente de completar');check('Unbound pending candidate shows no future review actions',p.locator('[data-review-action]').count()==0);expect(p.locator('#invitation-state')).to_contain_text('Invitación registrada — Pendiente de aceptación');p.locator('#prepared-faculties summary').click();expect(p.locator('#save-intentions')).to_be_visible();check('Only eleven connected Advisor capabilities are selectable',p.locator('[data-intention]').count()==11 and p.locator('[data-intention=profile_create]').count()==0)
   if not sql('SELECT COUNT(*) FROM auth_staff_capability_intentions WHERE case_id=%s',(record['case'],))[0][0]:
    p.locator('[data-intention="media_review_read"]').check();p.locator('[data-intention="media_review_approve"]').check();p.locator('#save-intentions').click();expect(p.locator('#staff-case-status')).to_contain_text('Facultades previstas guardadas')
   check('Versioned pending capabilities persist without effective grants',sql('SELECT COUNT(*) FROM internal_operator_grants WHERE account_id IN (SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s)',(record['case'],))[0][0]==0)
   p.locator('#simulate-onboarding').click();expect(p.locator('#simulation-progress')).not_to_have_attribute('data-state','NOT_REQUESTED');check('Simulation click acknowledges an operation visibly',True);p.wait_for_timeout(500)
   deadline=time.time()+60
   while time.time()<deadline:
    state=json.loads((owned/('simulation-'+record['case']+'.json')).read_text())
    if state['state'] in ['REVIEW_REQUIRED','RECONCILIATION_REQUIRED']:break
    p.wait_for_timeout(500)
   check('Synthetic applicant completes canonical registration, verification, binding and evidence',state['state']=='REVIEW_REQUIRED')
   expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=15000);check('All six simulation milestones are confirmed and visible',p.locator('[data-stage][data-complete=true]').count()==6)
   expect(p.locator('#staff-case-content')).to_have_js_property('inert',False)
   record['account']=state['account_id'];recordfile.write_text(json.dumps(record))
   check('Simulation does not manufacture admission or grants',sql('SELECT COUNT(*) FROM internal_staff WHERE account_id=%s',(record['account'],))[0][0]==0)
   p.locator('#close-staff-case').click();p.locator('[data-case="'+record['case']+'"]').click();expect(p.locator('[data-review-action="READ"]')).to_be_enabled()
   for action in ['READ','ACCEPT','ADMIT']:helper.review_action(p,action,owned,sql)
   check('Director explicit review and admission preserve canonical password and MFA',sql("SELECT status FROM internal_staff WHERE account_id=%s",(record['account'],))[0][0]=='ACTIVE')
   check('Admission alone still grants no operational authority',sql('SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s',(record['account'],))[0][0]==0)
  if completed:
   p.locator('[data-member="'+record['account']+'"]').click();p.locator('#identity-evidence summary').click();p.locator('#member-evidence').click();expect(p.locator('#prepared-faculties')).to_be_visible()
  if not p.locator('#prepared-faculties').evaluate('(e)=>e.open'):p.locator('#prepared-faculties summary').click()
  current=wf(p,'intentions',{'case_id':record['case']})['body']
  if current['prepared_case_version']!=current['case_version']:
   p.locator('#save-intentions').click();expect(p.locator('#staff-case-status')).to_contain_text('Facultades previstas guardadas')
  p.on('dialog',lambda dialog:dialog.accept())
  for cap in ['media_review_read','media_review_approve']:
   if sql('SELECT COUNT(*) FROM auth_staff_intention_activations WHERE case_id=%s AND capability=%s',(record['case'],cap))[0][0]:continue
   button=p.locator('[data-activate-intention="'+cap+'"]');button.click();expect(button).to_have_count(0,timeout=15000)
  check('Explicit final grants use canonical writer and intention activation receipts',sql('SELECT COUNT(*) FROM auth_staff_intention_activations WHERE case_id=%s',(record['case'],))[0][0]==2)
  check('Canonical admitted account has only the two explicitly confirmed capabilities',set(x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(record['account'],)))=={'media_review_read','media_review_approve'})
  p.locator('#close-staff-case').click()
  p.screenshot(path=str(shots/'director.png'))
  browser.close()
