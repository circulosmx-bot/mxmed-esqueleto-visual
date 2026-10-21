#!/usr/bin/env python3
"""Targeted STAFF-CONTROL06 selection/authentication/keyboard regression; owned only."""
import pathlib,sys,json,importlib.util,uuid,time,os
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(path,native_only=False):
 os.umask(0o077)
 review=module('review06',ROOT/'scripts/staff-control06-review.py');owned,approval=review.owned_configuration(path);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  with db.cursor() as c:c.execute(q,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 helper=module('helper06',ROOT/'scripts/ase-adm02-oper09-browser.py');wf=helper.wf
 native=module('native06',ROOT/'scripts/ase-advisor-workspace-review.py')
 out=ROOT/'docs/evidence/staff-control06';shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 results=json.loads((out/'supplement.json').read_text()) if native_only else {'checks':[],'page_errors':[]}
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});(out/'supplement.json').write_text(json.dumps(results,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
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
 def geography(p,engine,size):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator('#add-member').click();expect(p.locator('#new-operation_state')).to_be_enabled();expect(p.locator('#new-operation_state option')).to_have_count(33)
  trigger=p.locator('#new-operation_state-trigger');trigger.focus();p.keyboard.type('aguas',delay=70);expect(p.get_by_role('option',name='Aguascalientes',exact=True)).to_be_focused();p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value('Aguascalientes')
  city=p.locator('#new-operation_city-trigger');city.focus();p.keyboard.type('calv',delay=70);expect(p.get_by_role('option',name='Calvillo',exact=True)).to_be_focused();p.keyboard.press('Enter');expect(p.locator('#new-operation_city')).to_have_value('Calvillo')
  check(engine+' '+size+' closed prefix selects canonical Aguascalientes/Calvillo',True)
  trigger.click();p.keyboard.type('micho',delay=70);expect(p.get_by_role('option',name='Michoacán de Ocampo',exact=True)).to_be_focused();p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value('Michoacán de Ocampo');expect(p.locator('#new-operation_city')).to_have_value('')
  trigger.focus();p.keyboard.type('mex',delay=70);expect(p.get_by_role('option',name='México',exact=True)).to_be_focused();p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value('México')
  check(engine+' '+size+' accent-insensitive open/closed search preserves accented value and clears city',True)
  city.click();p.keyboard.type('san',delay=70);first=p.locator(':focus').inner_text();p.keyboard.press('ArrowDown');second=p.locator(':focus').inner_text();p.keyboard.press('Enter');expect(p.locator('#new-operation_city')).to_have_value(second)
  check(engine+' '+size+' similarly prefixed municipalities remain arrow-selectable',first!=second and first.startswith('San') and second.startswith('San'))
  trigger.focus();p.keyboard.type('zzzz',delay=70);expect(p.locator('.team-select-search-status').filter(has_text='Sin coincidencias')).to_be_visible();before=p.locator('#new-operation_state').input_value();p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value(before)
  box=p.locator('.team-select-options:popover-open').bounding_box();check(engine+' '+size+' no-match state and popup constrained to viewport',box['x']>=0 and box['y']>=0 and box['x']+box['width']<=p.evaluate('innerWidth')+1 and box['y']+box['height']<=p.evaluate('innerHeight')+1)
  p.keyboard.press('Escape');expect(trigger).to_be_focused();expect(p.locator('#onboarding')).to_be_visible();p.keyboard.press('Escape');expect(p.locator('#onboarding')).not_to_be_visible()
 with sync_playwright() as pw:
  b,ctx,p=native.native_browser(pw);p.on('pageerror',lambda e:results['page_errors'].append(str(e)));p.on('dialog',lambda dialog:dialog.accept())
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  if native_only:
   martha=json.loads((owned/'staff06-martha.json').read_text());choose_case(p,martha,'Martha');p.screenshot(path=str(shots/'native-director.png'))
   check('Native viewport and selected pending detail remain coherent',p.evaluate('document.documentElement.scrollWidth<=innerWidth') and p.locator('[data-case="'+martha['case']+'"]').get_attribute('aria-pressed')=='true')
   check('No JavaScript errors in targeted browser flows',not results['page_errors']);b.close();db.close();results['result']='PASS';(out/'supplement.json').write_text(json.dumps(results,indent=2));return
  martha=prepare(p,'martha','Martha','Figueroa');candela=prepare(p,'candela','Candela Revisión','Ficticia');lucia=prepare(p,'lucia','Lucía Pendiente','Ficticia')
  p.reload();expect(p.locator('[data-case="'+martha['case']+'"]').first).to_be_visible()
  for row,label in [(None,'Irene'),(martha,'Martha'),(candela,'Candela'),(None,'Irene')]:
   if row:choose_case(p,row,label)
   else:select_active(p)
   check('Exact right panel selection '+label,p.locator('#detail-title').inner_text().startswith(label) and p.locator('#directory-content [aria-pressed=true]').count()==1 and p.locator('dialog[open]').count()==0)
  choose_case(p,candela,'Candela');check('Pending summary retains canonical invitation/binding/evidence/prepared and effective counts',all(x in p.locator('#staff-case-content').inner_text() for x in ['Categoría prevista','Invitación registrada','Sin vincular','Confirmación personal','Evidencia de identidad','Resultado de revisión','Facultades previstas','Permisos activos']))
  before=sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(lucia['case'],))[0][0]
  # Owned fixture observes a future clock after expiry; immutable expiry and history stay intact.
  sql('UPDATE auth_staff_admission_invitations SET last_observed_at=DATE_ADD(expires_at, INTERVAL 1 SECOND) WHERE invitation_id=%s',(lucia['invitation'],))
  choose_case(p,lucia,'Lucía');expect(p.locator('#invitation-state')).to_contain_text('vencida');select_active(p);choose_case(p,lucia,'Lucía')
  check('Expired invitation navigation never reissues it',before==sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(lucia['case'],))[0][0])
  choose_case(p,martha,'Martha')
  if good(p,'simulation_status',{'case_id':martha['case']})['state']=='NOT_REQUESTED':p.locator('#simulate-onboarding').click()
  expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000);expect(p.locator('#admission-next-state')).to_have_text('Simulación completada — Lista para revisión del Director')
  job=json.loads((owned/('simulation-'+martha['case']+'.json')).read_text())
  check('Six simulation stages do not grant staff or capabilities',p.locator('[data-stage][data-complete=true]').count()==6 and not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(job['account_id'],)) and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(job['account_id'],)))
  p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-stepup-title')).to_have_text('Confirmar identidad del Director');expect(p.locator('#staff-stepup-account')).to_have_text('Cuenta: qa_director@example.test');expect(p.locator('#staff-stepup-target')).to_contain_text('Consultar evidencia de Martha Figueroa Rosado');expect(p.locator('#staff-password-label')).to_have_text('Contraseña del Director')
  p.screenshot(path=str(shots/'director-authentication.png'));check('Reauthentication clearly names server-authenticated Director and exact candidate',True)
  p.locator('#staff-password').fill('incorrect-synthetic-password');p.locator('#staff-challenge').click();expect(p.locator('#staff-stepup-status')).not_to_have_text('Confirma esta acción con tu cuenta actual.');check('Invalid Director password cannot advance to MFA or expose documents',not p.locator('#staff-code-stage').is_visible() and p.locator('#protected-case-pages img').count()==0)
  p.locator('#cancel-staff-stepup').click();helper.review_action(p,'READ',owned,sql);expect(p.locator('#staff-evidence img')).not_to_have_count(0)
  check('Canonical password plus MFA reveals protected exact-case documents without approval',good(p,'view',{'case_id':martha['case']})['review'] is None and not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(job['account_id'],)))
  p.locator('#staff-evidence .team-dialog-body').evaluate('(e)=>e.scrollTop=e.scrollHeight');expect(p.locator('#close-staff-evidence')).to_be_visible();p.screenshot(path=str(shots/'protected-evidence-native.png'));p.keyboard.press('Escape');expect(p.locator('#staff-evidence')).not_to_be_visible();check('Evidence dismissal clears document bytes and retains selected candidate',p.locator('#protected-case-pages img').count()==0 and 'Martha' in p.locator('#detail-title').inner_text())
  # Independent Advisor session cannot use Director readers or protected documents.
  a=b.new_page(ignore_https_errors=True);native.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR)
  check('Advisor denied exact-case protected review reader',wf(a,'view',{'case_id':martha['case']})['status']==403);a.close()
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine).launch()
   for width,height in [(1440,900),(1366,768),(390,844)]:
    c=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=c.new_page();page.on('pageerror',lambda e:results['page_errors'].append(str(e)));page.on('dialog',lambda d:d.accept())
    native.login(page,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR);choose_case(page,martha,'Martha');check(engine+' '+str(width)+' pending right-panel responsive layout',page.locator('dialog[open]').count()==0 and page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
    page.locator('[data-review-action=READ]').click();page.locator('#staff-stepup .team-dialog-body').evaluate('(e)=>e.scrollTop=e.scrollHeight');expect(page.locator('#cancel-staff-stepup')).to_be_visible();page.screenshot(path=str(shots/(engine+'-'+str(width)+'-authentication.png')));page.keyboard.press('Escape');expect(page.locator('#staff-stepup')).not_to_be_visible()
    geography(page,engine,str(width));choose_case(page,candela,'Candela');select_active(page);c.close()
   browser.close()
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR);choose_case(p,martha,'Martha');p.screenshot(path=str(shots/'native-director.png'));check('Native viewport and selected pending detail remain coherent',p.evaluate('document.documentElement.scrollWidth<=innerWidth') and p.locator('[data-case="'+martha['case']+'"]').get_attribute('aria-pressed')=='true')
  check('No JavaScript errors in targeted browser flows',not results['page_errors']);b.close()
 db.close();results['result']='PASS';(out/'supplement.json').write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1],'--native-final' in sys.argv)
