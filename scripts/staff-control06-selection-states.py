#!/usr/bin/env python3
"""Targeted STAFF-CONTROL06 selection/authentication/keyboard regression; owned only."""
import pathlib,sys,json,importlib.util,uuid,time,os
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(path):
 os.umask(0o077)
 review=module('review06',ROOT/'scripts/staff-control06-review.py');owned,approval=review.owned_configuration(path);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  with db.cursor() as c:c.execute(q,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 helper=module('helper06',ROOT/'scripts/ase-adm02-oper09-browser.py');wf=helper.wf
 native=module('native06',ROOT/'scripts/ase-advisor-workspace-review.py')
 out=ROOT/'docs/evidence/staff-control06';shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 results={'checks':[],'page_errors':[]}
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});(out/'selection-states.json').write_text(json.dumps(results,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
 def good(p,action,body):
  v=wf(p,action,body);assert v['status']==200,(action,v['status']);return v['body']
 def prepare(p,key,given,last):
  f=owned/('staff06-'+key+'.json')
  if f.exists():return json.loads(f.read_text())
  c=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':given,'first_surname':last,'second_surname':'Rosado' if key=='martha' else '','operation_state':'Aguascalientes','operation_city':'Calvillo'})
  email=key+'_staff06@example.test';inv=good(p,'issue',{'case_id':c['case_id'],'expected_version':c['version'],'operation_id':str(uuid.uuid4()),'recipient_email':email})
  row={'case':c['case_id'],'email':email,'invitation':inv['invitation_id']};f.write_text(json.dumps(row));return row
 def choose_case(p,row,label):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator('[data-case="'+row['case']+'"]').click();expect(p.locator('#detail-title')).to_contain_text(label);expect(p.locator('#admission-next-state')).to_be_visible();expect(p.locator('#staff-case-content')).to_have_js_property('inert',False)
 def select_active(p):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator('[data-member="'+record['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Irene')
 with sync_playwright() as pw:
  b=pw.chromium.launch();p=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});p.on('pageerror',lambda e:results['page_errors'].append(str(e)));p.on('dialog',lambda d:d.accept())
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  select_active(p)
  # Canonical suspension/reactivation, retaining original grants and history.
  for status,label in [('SUSPENDED','Suspendido'),('ACTIVE','Activo')]:
   p.locator('#lifecycle').click();p.locator('#accept-confirmation').click();expect(p.locator('#advisor-detail .detail-header .status')).to_have_text(label);expect(p.locator('#lifecycle')).to_be_enabled()
   p.locator('#status-trigger').click();p.get_by_role('option',name='Suspendidos' if status=='SUSPENDED' else 'Activos',exact=True).click();expect(p.locator('[data-member="'+record['account']+'"]').first).to_be_visible();select_active(p)
   check('Canonical '+status+' record selects exact right panel',p.locator('#advisor-detail .detail-header .status').inner_text()==label and p.locator('#directory-content [aria-pressed=true]').count()==1)
  p.locator('#filter-masters').click();expect(p.locator('[data-member]').first).to_be_visible();master=p.locator('[data-member]').first;reference=master.get_attribute('data-member');master.click();expect(p.locator('#advisor-detail .team-reference')).to_contain_text(reference)
  expect(master).to_have_attribute('aria-pressed','true');check('Master selection remains within existing authorized scope','Administrador maestro' in p.locator('#advisor-detail').text_content())
  p.locator('#filter-advisors').click();expect(p.locator('[data-member="'+record['account']+'"]').first).to_be_visible()
  historical=prepare(p,'historical','Samuel Histórico','Ficticio');p.reload()
  completed=sql('SELECT account_id FROM auth_staff_admission_completions WHERE case_id=%s',(historical['case'],))
  if completed:
   historical['account']=completed[0][0];p.locator('[data-member="'+historical['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Samuel');p.locator('#identity-evidence summary').click();p.locator('#member-evidence').click();expect(p.locator('#detail-title')).to_contain_text('Samuel')
  else:
   choose_case(p,historical,'Samuel')
   if good(p,'simulation_status',{'case_id':historical['case']})['state']=='NOT_REQUESTED':p.locator('#simulate-onboarding').click()
   expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000)
   job=json.loads((owned/('simulation-'+historical['case']+'.json')).read_text());historical['account']=job['account_id']
  if not sql('SELECT case_id FROM auth_staff_admission_completions WHERE case_id=%s',(historical['case'],)):
   for action in ['READ','ACCEPT','ADMIT']:
    helper.review_action(p,action,owned,sql)
    if action=='READ':p.locator('#close-staff-evidence').click()
    if action=='ACCEPT':check('Positive evidence decision still does not activate staff',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(historical['account'],)))
  expect(p.locator('#detail-title')).to_contain_text('Samuel');expect(p.locator('[data-member="'+historical['account']+'"][aria-pressed=true]')).to_be_visible();check('Admission refresh preserves exact candidate and highlights new active card','Samuel' in p.locator('#detail-title').inner_text())
  p.locator('[data-member="'+historical['account']+'"]').click();p.locator('#remove-member').click();p.locator('#accept-confirmation').click();expect(p.locator('#advisor-detail .detail-header .status')).to_have_text('Baja definitiva')
  p.locator('#status-trigger').click();p.get_by_role('option',name='Dados de baja',exact=True).click();expect(p.locator('[data-member="'+historical['account']+'"]').first).to_be_visible();p.locator('[data-member="'+historical['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Samuel')
  check('Removed historical Advisor selection preserves identity and disables mutations',p.locator('#remove-member').is_disabled() and p.locator('#lifecycle').is_disabled() and sql('SELECT status FROM auth_accounts WHERE account_id=%s',(historical['account'],))[0][0]=='active')
  p.locator('#status-trigger').click();p.get_by_role('option',name='Activos',exact=True).click()
  cancelled=prepare(p,'cancelled','Cancelación','Ficticia');p.reload();choose_case(p,cancelled,'Cancelación');p.get_by_text('Otras opciones',exact=True).click();p.locator('#revoke-preparation').click();expect(p.locator('#advisor-detail .detail-header .status')).to_have_text('Incorporación cancelada');check('Cancellation refresh keeps exact cancelled identity and no executable future controls','Cancelación' in p.locator('#detail-title').inner_text() and p.locator('#staff-case-content button').count()==0)
  martha=json.loads((owned/'staff06-martha.json').read_text());choose_case(p,martha,'Martha');expect(p.locator('#admission-next-state')).to_be_visible()
  check('Irene reactivated; Martha remains unadmitted for visual review',sql('SELECT status FROM internal_staff WHERE account_id=%s',(record['account'],))[0][0]=='ACTIVE' and not sql('SELECT case_id FROM auth_staff_admission_completions WHERE case_id=%s',(martha['case'],)))
  check('No JavaScript errors across lifecycle selections',not results['page_errors']);b.close()
 db.close();results['result']='PASS';(out/'selection-states.json').write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1])
