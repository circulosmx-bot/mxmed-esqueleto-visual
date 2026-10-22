"""Focused session-authorized Director HTTP, audit and real-browser regression."""
import pathlib,json,uuid,importlib.util,time,hashlib,subprocess
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(r,check,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];out=r['OUT'];shots=out/'screens';shots.mkdir(exist_ok=True)
 helper=module('auth_helper',ROOT/'scripts/ase-adm02-oper09-browser.py');native=module('auth_native',ROOT/'scripts/ase-advisor-workspace-review.py')
 errors=[];requests=[]
 def log_error(e):
  errors.append(str(e));(owned/'auth08-js-errors.json').write_text(json.dumps([{'message':str(e),'stack':e.stack}],indent=2));print('BROWSER_ERROR '+str(e),flush=True)
 def good(p,action,body={}):
  result=helper.wf(p,action,body);assert result['status']==200,(action,result['status'],result['body']);return result['body']
 def login(p,email='qa_director@example.test',applicant=False):native.login(p,origin,email,(owned/('applicant-password' if applicant else 'fixture-password')).read_text().strip(),'/staff-admission.php' if applicant else '/internal/media-review/index.php?section=equipo')
 def select(p,cid):
  p.goto(origin+'/internal/media-review/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled(timeout=20000);p.locator('[data-case="'+cid+'"],[data-admission-case="'+cid+'"]').first.click();expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def click_visible(button):
  expect(button).to_be_attached(timeout=20000)
  for d in button.locator('xpath=ancestor::details').all():
   if not d.evaluate('(e)=>e.open'):d.locator('summary').first.click()
  button.click()
 def action(p,a,reason=None):
  click_visible(p.locator('#advisor-detail [data-review-action='+a+']').first)
  expect(p.locator('#staff-stepup')).to_be_visible();assert p.locator('#staff-stepup input[type=password],#staff-code').count()==0
  if reason:p.locator('#staff-reason').fill(reason)
  p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000)
 def read(p,setid=None):
  click_visible(p.locator('#advisor-detail [data-consult-set'+('="'+setid+'"' if setid else '')+']').first)
  try:expect(p.locator('#staff-evidence')).to_be_visible(timeout=20000)
  except Exception:
   print('READ_FAILURE '+p.locator('body').inner_text(),flush=True);raise
  assert not p.locator('#staff-stepup').is_visible() and p.locator('#staff-password,#staff-code').count()==0
  assert p.locator('.evidence-page-grid img').evaluate_all('(xs)=>xs.length>0&&xs.every(x=>x.complete&&x.naturalWidth>0)')
 def close(p):p.locator('#close-staff-evidence').click();expect(p.locator('#staff-evidence img')).to_have_count(0)
 def prepare(p,key,email,given,surname,second=''):
  path=owned/key
  if path.exists():return json.loads(path.read_text())
  case=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':given,'first_surname':surname,'second_surname':second,'operation_state':'Aguascalientes','operation_city':'Calvillo'})
  good(p,'issue',{'case_id':case['case_id'],'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'recipient_email':email});good(p,'simulate',{'case_id':case['case_id']})
  deadline=time.time()+90
  while time.time()<deadline:
   state=good(p,'simulation_status',{'case_id':case['case_id']})
   if state['state']=='REVIEW_REQUIRED':break
   p.wait_for_timeout(500)
  assert state['state']=='REVIEW_REQUIRED',state
  record={'case':case['case_id'],'email':email,'account':sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(case['case_id'],))[0][0]};path.write_text(json.dumps(record));return record
 def request(p,cid,a,setid=None,reason='Operación sintética explícita'):
  c=good(p,'case',{'case_id':cid});v=good(p,'view',{'case_id':cid})
  return {'case_id':cid,'set_id':setid or v['current_set_id'],'action':a,'reason':reason,'expected_version':c['version'],'expected_review_id':v['review']['review_id'] if v['review'] else None}
 def execute(p,b):
  intent=good(p,'authorize',b);a=b['action'];body={'intent_id':intent['intent_id']}
  if a=='ADMIT':body['case_id']=b['case_id']
  return good(p,{'READ':'evidence','ACK':'acknowledge','ADMIT':'admit'}.get(a,'decide'),body)
 with sync_playwright() as pw:
  br=pw.chromium.launch();ctx=br.new_context(ignore_https_errors=True);p=ctx.new_page();login(p)
  irene=prepare(p,'oper09-advisor.json','oper09_advisor@example.test','Irene Ficticia','Oper09')
  if not good(p,'view',{'case_id':irene['case']})['admitted']:
   execute(p,request(p,irene['case'],'READ'));execute(p,request(p,irene['case'],'ACCEPT'));execute(p,request(p,irene['case'],'ADMIT'))
  martha=prepare(p,'staff06-martha.json','martha_doc@example.test','Martha','Figueroa','Rosado');cid=martha['case']
  if len(good(p,'view',{'case_id':cid})['sets'])<2:
   ac=br.new_context(ignore_https_errors=True);ap=ac.new_page();login(ap,martha['email'],True);ap.locator('#wf-cases').select_option(cid);expect(ap.locator('#wf-given_names')).to_have_value('Martha');ap.locator('#wf-update-mode').select_option('CORRECT');ap.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-justification').fill('Actualización geométrica sintética para comprobar el historial.');ap.locator('#wf-add-document').click();expect(ap.locator('#wf-documents')).to_contain_text('2 cara(s)',timeout=20000);ap.locator('#wf-consent').check();ap.locator('#wf-submit').click();expect(ap.locator('#wf-status')).to_have_text('Solicitud consultada.',timeout=20000);expect(ap.locator('#wf-documents')).to_be_empty(timeout=20000);ac.close()
  pendingfile=owned/'panel09-pending.json'
  if pendingfile.exists():pending=json.loads(pendingfile.read_text())
  else:
   pending=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':'Invitación Ficticia','first_surname':'Pendiente','second_surname':'','operation_state':'Aguascalientes','operation_city':'Calvillo'})
   good(p,'issue',{'case_id':pending['case_id'],'expected_version':pending['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'panel09_pending@example.test'});pendingfile.write_text(json.dumps(pending))
  check('Canonical synthetic scenarios: active Advisor, Martha with versions, pending invitation',len(good(p,'view',{'case_id':cid})['sets'])==2)
  ctx.close();br.close()
  grants_before=sql("SELECT account_id,capability,status FROM internal_operator_grants ORDER BY account_id,capability,grant_id")
  for engine in ['chromium','webkit']:
   br=getattr(pw,engine).launch();dc=br.new_context(ignore_https_errors=True);ac=br.new_context(ignore_https_errors=True);d=dc.new_page();a=ac.new_page();d.on('pageerror',log_error);a.on('pageerror',log_error)
   login(d);native.login(a,origin,irene['email'],(owned/'applicant-password').read_text().strip(),'/internal/staff-workspace/index.php')
   expect(a.locator('#staff-name')).to_have_text('Irene Ficticia Oper09',timeout=20000);expect(a.locator('#staff-role')).to_have_text('Asesor')
   check(engine+' authorized Advisor name and role with neutral local avatar',a.locator('.team-avatar path').count()==1)
   def metrics(page):return page.locator('.staff-platform-header').evaluate('(e)=>{const h=e.querySelector("h1"),a=e.querySelector(".team-avatar"),l=e.querySelector("button"),s=getComputedStyle(h),b=getComputedStyle(l);return {font:s.fontSize,weight:s.fontWeight,color:s.color,line:s.lineHeight,logo:e.querySelector(".team-brand img").getBoundingClientRect().width,avatar:a.getBoundingClientRect().width,radius:getComputedStyle(a).borderRadius,logout:[b.backgroundColor,b.borderColor,b.fontSize],height:e.getBoundingClientRect().height}}')
   for width,height in [(1440,900),(1366,768),(390,844)]:
    for page in [d,a]:page.set_viewport_size({'width':width,'height':height})
    select(d,cid);expect(d.locator('#detail-title')).to_contain_text('Martha')
    for page,title,role in [(d,'EQUIPO INTERNO Y PERMISOS','director'),(a,'GESTIÓN DE PERFILES MÉDICOS','advisor')]:
     expect(page.locator('.team-brand h1')).to_have_text(title);expect(page.locator('.staff-platform-header .eyebrow')).to_have_count(0)
     check(f'{engine} {width} {role} official logo',page.locator('.team-brand img').evaluate('(e)=>e.complete&&e.naturalWidth>0&&e.src.endsWith("/assets/mexico-medico.svg")'))
     check(f'{engine} {width} {role} header containment',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
     page.screenshot(path=str(shots/f'{engine}-{width}-{role}.png'))
    dm,am=metrics(d),metrics(a);check(f'{engine} {width} shared header typography avatar logout parity',all(dm[k]==am[k] for k in ['font','weight','color','line','logo','avatar','radius','logout']) and abs(dm['height']-am['height'])<12)
   # Same authenticated Advisor retains its existing zero-grant restrictions.
   denied=a.evaluate("async()=>{const r=await fetch('/api/internal/team/read.php');return r.status}")
   check(engine+' Advisor cannot acquire Director authority',denied==403)
   expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');check(engine+' ungranted Advisor retains empty scoped inbox',a.locator('#discovery-list [data-doctor-id]').count()==0)
   a.locator('#physician-search').fill('Aurora');a.locator('#physician-search-form button').click();expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');check(engine+' Advisor search respects existing scope',a.locator('#discovery-list [data-doctor-id]').count()==0)
   # Positive navigation with the existing base QA target fixture, seeded read-only at creation.
   wc=br.new_context(ignore_https_errors=True);w=wc.new_page();native.login(w,origin,'qa_target@example.test',(owned/'fixture-password').read_text().strip(),'/internal/staff-workspace/index.php');expect(w.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
   w.locator('[data-doctor-id="workspace_aurora"]').first.click();expect(w.locator('#physician-name')).to_contain_text('Aurora');expect(w.locator('#section-tabs [role=tab]')).to_have_count(15)
   for tabid in ['images','requests','history','seo','summary']:
    w.locator('#tab-'+tabid).click();expect(w.locator('#work-panel')).to_have_attribute('data-doctor-id','workspace_aurora');expect(w.locator('#physician-name')).to_contain_text('Aurora')
   check(engine+' authorized physician selection and fifteen tabs preserve context',True)
   w.locator('#search-toggle').click();w.locator('#physician-search').fill('Bruno');w.locator('#physician-search-form button').click();expect(w.locator('#discovery-list [data-doctor-id="workspace_bruno"]')).to_be_visible();check(engine+' authorized physician search works',True)
   w.locator('#pending-toggle').click();expect(w.locator('#discovery-list')).to_have_attribute('aria-busy','false');check(engine+' grouped inbox keeps one entry per physician',w.locator('#discovery-list [data-doctor-id="workspace_aurora"]').count()==1)
   wc.close()
   a.locator('#staff-logout').click();a.wait_for_url('**/public/identity/acceso.php*');check(engine+' Advisor canonical logout',True)
   # Independent Director session remains authorized after Advisor logout.
   select(d,cid);expect(d.locator('#detail-title')).to_contain_text('Martha');check(engine+' independent sessions preserve Director selection',True)
   dc.close();ac.close();br.close()
  check('Existing roles and grants unchanged',sql("SELECT account_id,capability,status FROM internal_operator_grants ORDER BY account_id,capability,grant_id")==grants_before)
  check('No JavaScript errors',not errors)
 (out/'workflow.json').write_text(json.dumps({'result':'PASS','martha_case':cid,'javascript_errors':errors,'source_baseline':'f69af160699afe7af1aac24eb0166e9f238c3c8e','advisor_permissions':'Preserved zero grants; positive workspace navigation tested with original read-only QA target fixture, no grant added.'},indent=2))
