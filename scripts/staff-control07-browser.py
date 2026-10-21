"""Focused layout, selection, legitimate admission and MFA regression."""
import pathlib,json,uuid,importlib.util
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
def run(r,check,cli):
 module('r2_admission',ROOT/'scripts/staff-control06-browser.py').run(r,check,cli)
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']]
 helper=module('r2_helper',ROOT/'scripts/ase-adm02-oper09-browser.py')
 native=module('r2_native',ROOT/'scripts/ase-advisor-workspace-review.py')
 review=module('r2_review',ROOT/'scripts/staff-control07-review.py')
 shots=r['OUT']/'screens';shots.mkdir(parents=True,exist_ok=True)
 errors=[];measurements=[]
 def good(p,action,body):
  v=helper.wf(p,action,body);assert v['status']==200,(action,v['status']);return v['body']
 def select(p,selector,name):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator(selector).click();expect(p.locator('#detail-title')).to_contain_text(name)
  check('Selected '+name+' has one highlighted card and matching panel',p.locator('#directory-content [aria-pressed=true]').count()==1)
 def layout(p,label):
  expect(p.locator('#add-member')).to_be_visible()
  expect(p.locator('.directory h2')).to_have_text('STAFF')
  header=p.locator('.workspace-header').bounding_box();button=p.locator('#add-member').bounding_box();state=p.locator('#status-trigger').bounding_box()
  measurements.append({'viewport':label,'header':header,'add':button,'state':state})
  check(label+' compact essential header',header['height']<115 and p.locator('.workspace-header h1').inner_text()=='EQUIPO INTERNO Y PERMISOS' and p.locator('.team-introduction').count()==0)
  check(label+' neutral mixed directory heading',p.locator('#directory-content h3').filter(has_text='Incorporaciones pendientes').count()==0 and 'Sin acceso activo; preparación e invitaciones.' not in p.locator('body').inner_text())
  check(label+' add action shares top row with state and fits viewport',abs(button['y']+button['height']-state['y']-state['height'])<3 and button['x']>state['x'] and button['y']+button['height']<p.evaluate('innerHeight'))
  check(label+' no horizontal overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
 def geography(p,label):
  p.locator('#add-member').click();expect(p.locator('#new-operation_state')).to_be_enabled(timeout=20000)
  p.locator('#new-operation_state-trigger').focus();p.keyboard.type('aguas',delay=70);p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value('Aguascalientes')
  p.locator('#new-operation_city-trigger').focus();p.keyboard.type('calv',delay=70);p.keyboard.press('Enter');expect(p.locator('#new-operation_city')).to_have_value('Calvillo')
  p.locator('#new-operation_state-trigger').click();p.keyboard.type('micho',delay=70);p.keyboard.press('Enter');expect(p.locator('#new-operation_state')).to_have_value('Michoacán de Ocampo');expect(p.locator('#new-operation_city')).to_have_value('')
  check(label+' add opens wizard and geographic keyboard search clears dependent city',True)
  p.locator('#cancel-onboarding').click()
 with sync_playwright() as pw:
  b,ctx,p=native.native_browser(pw);p.on('pageerror',lambda e:errors.append(str(e)));p.on('dialog',lambda d:d.accept())
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  record=json.loads((owned/'oper09-advisor.json').read_text());f=owned/'staff06-martha.json'
  if f.exists():martha=json.loads(f.read_text())
  else:
   case=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':'Martha','first_surname':'Figueroa','second_surname':'Rosado','operation_state':'Aguascalientes','operation_city':'Calvillo'})
   invitation=good(p,'issue',{'case_id':case['case_id'],'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'martha_r2@example.test'})
   martha={'case':case['case_id'],'email':'martha_r2@example.test','invitation':invitation['invitation_id']};f.write_text(json.dumps(martha))
  p.reload();expect(p.locator('[data-case="'+martha['case']+'"]').first).to_be_visible()
  layout(p,'native Chromium');geography(p,'native Chromium')
  active='[data-member="'+record['account']+'"]';pending='[data-case="'+martha['case']+'"]'
  for selector,name in [(active,'Irene'),(pending,'Martha'),(active,'Irene'),(pending,'Martha')]:select(p,selector,name)
  if good(p,'simulation_status',{'case_id':martha['case']})['state']=='NOT_REQUESTED':p.locator('#simulate-onboarding').click()
  expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000)
  expect(p.locator('#admission-next-action')).to_contain_text('contraseña actual de tu cuenta de Director y un código MFA vigente')
  expect(p.locator('#admission-next-action .synthetic-review-help')).to_be_visible()
  text=p.locator('#admission-next-action .synthetic-review-help').text_content()
  check('Visible synthetic guide identifies current owned MFA and credential commands',str(owned) in text and 'staff-control07-review.py' in text and 'mfa-director' in text and 'credentials-director' in text and (owned/'fixture-password').read_text().strip() not in text)
  p.screenshot(path=str(shots/'native-director.png'))
  p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-stepup .synthetic-review-help')).to_be_visible(timeout=20000)
  p.screenshot(path=str(shots/'native-authentication.png'))
  p.locator('#staff-password').fill('incorrect-synthetic-password');p.locator('#staff-challenge').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se autorizó la verificación')
  check('Wrong password cannot advance to MFA or expose documents',not p.locator('#staff-code-stage').is_visible() and p.locator('#protected-case-pages img').count()==0)
  p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();expect(p.locator('#staff-stepup-expiry')).to_be_visible();expect(p.locator('#staff-stepup-expiry')).to_contain_text('Esta confirmación vence')
  # Invalid-format code is always rejected, independent of the current TOTP value.
  rejected=good(p,'view',{'case_id':martha['case']})
  p.locator('#staff-code').fill('12345');p.locator('#staff-execute').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se validó el código MFA')
  check('Invalid MFA cannot reveal protected evidence or approve admission',p.locator('#staff-stepup').is_visible() and p.locator('#protected-case-pages img').count()==0 and rejected['review'] is None)
  p.locator('#cancel-staff-stepup').click();helper.review_action(p,'READ',owned,sql);expect(p.locator('#staff-evidence img')).not_to_have_count(0)
  check('Canonical password and valid MFA permit READ without review or admission',good(p,'view',{'case_id':martha['case']})['review'] is None and not sql('SELECT case_id FROM auth_staff_admission_completions WHERE case_id=%s',(martha['case'],)))
  p.locator('#close-staff-evidence').click();expect(p.locator('#protected-case-pages img')).to_have_count(0);check('Evidence close clears private images and retains selection',p.locator('#protected-case-pages img').count()==0 and 'Martha' in p.locator('#detail-title').inner_text())
  for width,height in [(1366,768),(390,844)]:
   c=b.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=c.new_page();page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:d.accept())
   native.login(page,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
   label='Chromium '+str(width);layout(page,label);page.screenshot(path=str(shots/(str(width)+'-directory.png')));geography(page,label);select(page,pending,'Martha')
   expect(page.locator('#admission-next-action .synthetic-review-help')).to_be_visible();page.locator('[data-review-action=READ]').click();expect(page.locator('#staff-stepup .synthetic-review-help')).to_be_visible();page.screenshot(path=str(shots/(str(width)+'-authentication.png')));page.keyboard.press('Escape');expect(page.locator('#staff-stepup')).not_to_be_visible();c.close()
  check('No JavaScript errors in targeted STAFF-CONTROL07 flows',not errors);b.close()
 (r['OUT']/'layout.json').write_text(json.dumps({'measurements':measurements,'page_errors':errors},indent=2))

 module('staff07_navigation',ROOT/'scripts/staff-control07-navigation.py').run(r,check,cli)
