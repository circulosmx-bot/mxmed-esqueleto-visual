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
  for engine in ['chromium','webkit']:
   br=getattr(pw,engine).launch();ctx=br.new_context(ignore_https_errors=True);q=ctx.new_page();q.on('pageerror',log_error);login(q)
   for width,height in [(1440,900),(1366,768),(390,844)]:
    q.set_viewport_size({'width':width,'height':height});select(q,cid)
    expect(q.locator('#case-documentation')).to_be_visible(timeout=20000)
    check(f'{engine} {width} canonical brand loaded',q.locator('.team-brand img').evaluate('(e)=>e.complete&&e.naturalWidth>0&&e.src.endsWith("/assets/mexico-medico.svg")'))
    expect(q.locator('.team-brand h1')).to_have_text('EQUIPO INTERNO Y PERMISOS');expect(q.locator('.team-brand .eyebrow')).to_have_count(0)
    expect(q.locator('#filter-advisors')).to_have_text('ASESORES');expect(q.locator('#filter-masters')).to_have_text('ADMINISTRADORES MAESTROS')
    check(f'{engine} {width} title and uppercase categories',True)
    check(f'{engine} {width} non-interactive circular identity',q.locator('.team-identity').evaluate('(e)=>!e.closest("button,a")&&!e.hasAttribute("tabindex")&&getComputedStyle(e.querySelector("svg")).borderRadius==="50%"'))
    logout=q.locator('#team-logout');check(f'{engine} {width} quiet accessible logout',logout.evaluate('(e)=>getComputedStyle(e).backgroundColor==="rgba(0, 0, 0, 0)"&&e.getBoundingClientRect().height>=44'))
    q.keyboard.press('Tab');logout.focus();check(f'{engine} {width} visible keyboard focus',logout.evaluate('(e)=>e===document.activeElement&&parseFloat(getComputedStyle(e).outlineWidth)>=2'))
    q.locator('.team-brand h1').scroll_into_view_if_needed();q.screenshot(path=str(shots/f'{engine}-{width}-header.png'))
    if width<640:q.locator('#show-directory').click()
    anchor=q.locator('.category-filter').bounding_box()
    for unused in range(2):
     for category in ['masters','advisors']:
      q.locator('#filter-'+category).click();expect(q.locator('#directory-content')).to_have_attribute('aria-busy','false')
      box=q.locator('.category-filter').bounding_box();assert abs(box['y']-anchor['y'])<1 and abs(box['height']-anchor['height'])<1
    check(f'{engine} {width} stable category control geometry',True)
    q.locator('#filter-masters').click();expect(q.locator('#directory-content')).to_have_attribute('aria-busy','false');expect(q.locator('#filter-masters')).to_have_attribute('aria-pressed','true')
    boxes=[q.locator('#filter-'+a).bounding_box() for a in ['advisors','masters']];check(f'{engine} {width} Advisors left, Masters right and switching',boxes[0]['x']<boxes[1]['x'])
    q.locator('#filter-advisors').click();expect(q.locator('#directory-content')).to_have_attribute('aria-busy','false');q.locator('[data-case="'+cid+'"]').click();expect(q.locator('#case-documentation')).to_be_visible(timeout=20000)
    hist=q.locator('#case-documentation .document-history');expect(hist).not_to_have_attribute('open','');expect(hist.locator('[data-document-set]')).not_to_be_visible();expect(q.locator('#case-documentation > [data-document-set]')).to_have_count(1)
    v=good(q,'view',{'case_id':cid});check(f'{engine} {width} only exact current documents visible',q.locator('#case-documentation > [data-document-set]').get_attribute('data-document-set')==v['current_set_id'])
    q.locator('#invitation-details').scroll_into_view_if_needed();expect(q.locator('#invitation-details .invitation-state')).to_have_text('Invitación aceptada');expect(q.locator('#invitation-details small')).not_to_be_visible();q.locator('#invitation-details .secondary-details summary').click();expect(q.locator('#invitation-details small')).to_be_visible();q.locator('#invitation-details .secondary-details summary').click()
    check(f'{engine} {width} accepted invitation concise with collapsed reference',True)
    hist.locator('summary').click();expect(hist.locator('[data-document-set]')).to_be_visible();old=hist.locator('[data-consult-set]').first.get_attribute('data-consult-set');read(q,old);expect(q.locator('#staff-evidence-context')).to_contain_text('Conjunto histórico');expect(q.locator('#ack-staff-evidence')).not_to_be_visible();close(q)
    read(q,v['current_set_id']);expect(q.locator('#staff-evidence-context')).to_contain_text('Martha Figueroa Rosado');close(q)
    check(f'{engine} {width} current and historical authorized viewers without credentials',q.locator('#staff-password,#staff-code').count()==0)
    expect(q.locator('#case-documentation .document-history')).not_to_have_attribute('open','')
    actions=q.locator('#advisor-detail [data-review-action]');check(f'{engine} {width} one documentation review area',actions.count()>0 and actions.evaluate_all('(xs)=>xs.every(x=>!!x.closest("#case-documentation"))'))
    if width<640:check(f'{engine} mobile documentation buttons readable',q.locator('.documentation-actions button').evaluate_all('(xs)=>xs.every(x=>x.getBoundingClientRect().width>=180)'))
    q.locator('#staff-documentation').scroll_into_view_if_needed();q.screenshot(path=str(shots/f'{engine}-{width}.png'))
    check(f'{engine} {width} responsive containment',q.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   select(q,pending['case_id']);expect(q.locator('#invitation-details .invitation-state')).to_contain_text('Pendiente de aceptación');expect(q.locator('#invitation-details small')).not_to_be_visible();check(engine+' pending invitation summary',True)
   # Exact HTTP read audits and no implicit examination/admission.
   check(engine+' consultation does not acknowledge or admit',not good(q,'view',{'case_id':cid})['document_ack_current'] and not good(q,'view',{'case_id':cid})['admitted'])
   ctx.close();br.close()
  check('No JavaScript errors',not errors)
  check('Canonical audit records protected document consultation',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='STAFF_MEDIA_READ' AND real_actor_reference='account:qa_director' AND resource_reference=%s",(cid,))[0][0]>0)
  files=[str(p.relative_to(ROOT)) for p in (ROOT/'modules/identity').rglob('*.php')]
  check('Identity authorization and business services unchanged',all(subprocess.check_output(['git','show','38354c74d96b009cdff078bea558697932ef0c91:'+f],cwd=ROOT)==(ROOT/f).read_bytes() for f in files))
 (out/'workflow.json').write_text(json.dumps({'result':'PASS','martha_case':cid,'javascript_errors':errors,'source_baseline':'38354c74d96b009cdff078bea558697932ef0c91'},indent=2))
