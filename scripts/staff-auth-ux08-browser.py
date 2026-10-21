"""Focused session-authorized Director HTTP, audit and real-browser regression."""
import pathlib,json,uuid,importlib.util,time,hashlib
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
  expect(p.locator('#staff-evidence')).to_be_visible(timeout=20000)
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
  browser,context,p=native.native_browser(pw);p.on('pageerror',log_error);login(p)
  p.on('request',lambda req:requests.append(req.post_data_json) if '/api/internal/staff-admission/workflow.php' in req.url and req.method=='POST' else None)
  check('Canonical Director login through Identity',good(p,'bootstrap')['account_id']=='qa_director')
  irene=prepare(p,'oper09-advisor.json','oper09_advisor@example.test','Irene Ficticia','Oper09');select(p,irene['case'])
  if not good(p,'view',{'case_id':irene['case']})['admitted']:
   read(p);close(p);action(p,'ACCEPT');action(p,'ADMIT')
  martha=prepare(p,'staff06-martha.json','martha_doc@example.test','Martha','Figueroa','Rosado');cid=martha['case'];select(p,cid)
  before=sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]
  read(p);close(p);read(p);close(p)
  check('Martha private consultation and reopening without password or MFA',True)
  check('Opening and closing do not acknowledge or admit',sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]==before and not good(p,'view',{'case_id':cid})['admitted'])
  read(p);p.locator('#ack-staff-evidence').click();expect(p.locator('#staff-stepup')).to_be_visible();p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000)
  check('Explicit acknowledgement records exact Director and version',good(p,'view',{'case_id':cid})['document_ack_current'] and sql('SELECT reviewer_account_id,reviewer_role FROM auth_staff_document_acknowledgements WHERE case_id=%s ORDER BY created_at DESC LIMIT 1',(cid,))[0]==('qa_director','DIRECTOR'))
  correction='Sustituye la imagen geométrica del reverso por una versión legible de prueba.'
  action(p,'CORRECTIONS',correction)
  check('Correction reason persisted canonically without admission',good(p,'view',{'case_id':cid})['review']['reason']==correction and not good(p,'view',{'case_id':cid})['admitted'])
  reject=prepare(p,'auth08-reject.json','auth08_reject@example.test','Rechazo Ficticio','Prueba');select(p,reject['case']);action(p,'REJECT','Documento geométrico incompleto para este ensayo sintético.')
  check('Separate eligible rejection case',good(p,'view',{'case_id':reject['case']})['review']['decision']=='REJECT')
  check('Separate acceptance and admission through explicit UI confirmations',good(p,'view',{'case_id':irene['case']})['admitted'] and sql('SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s',(irene['account'],))[0][0]==0)
  # A real applicant replaces the evidence using the existing own-account writer.
  ac=browser.new_context(ignore_https_errors=True);ap=ac.new_page();login(ap,martha['email'],True);ap.locator('#wf-cases').select_option(cid)
  expect(ap.locator('#wf-review-state')).to_contain_text('Correcciones')
  old=good(p,'view',{'case_id':cid})['sets'][0]
  ap.locator('#wf-update-mode').select_option('CORRECT');ap.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-justification').fill('Corrección sintética del reverso solicitada por Dirección.');ap.locator('#wf-add-document').click();expect(ap.locator('#wf-documents')).to_contain_text('2 cara(s)',timeout=20000);ap.locator('#wf-consent').check();ap.locator('#wf-submit').click();expect(ap.locator('#wf-review-state')).to_contain_text('Nueva entrega pendiente de revisión',timeout=20000)
  current=good(p,'view',{'case_id':cid});check('Applicant resubmission preserves history and invalidates previous acknowledgement',len(current['sets'])>=2 and not current['document_ack_current'] and not current['review_current'])
  select(p,cid);read(p,old['set_id']);expect(p.locator('#staff-evidence-context')).to_contain_text('Conjunto histórico');expect(p.locator('#ack-staff-evidence')).not_to_be_visible();close(p)
  check('Previous document version remains privately accessible',True)
  # Exact snapshot rejects stale case, different case/set, and racing decisions.
  b=request(p,cid,'READ');bad={**b,'expected_version':b['expected_version']-1};check('Stale case version denied',helper.wf(p,'authorize',bad)['status']==409)
  check('Cross-case evidence denied',helper.wf(p,'authorize',{**b,'set_id':good(p,'view',{'case_id':irene['case']})['sets'][0]['set_id']})['status']!=200)
  rb=request(p,cid,'REJECT');one=good(p,'authorize',rb);two=good(p,'authorize',rb);good(p,'decide',one)
  check('Concurrent decision snapshot rejects second commit',helper.wf(p,'decide',two)['status']==409)
  check('Historical acknowledgement denied',helper.wf(p,'authorize',request(p,cid,'ACK',old['set_id']))['status']!=200)
  # Different actor cannot create or consume a Director intent.
  fresh=good(p,'authorize',request(p,cid,'READ'))
  for email,applicant in [(irene['email'],True),('qa_master@example.test',False)]:
   ctx=browser.new_context(ignore_https_errors=True);q=ctx.new_page();login(q,email,applicant)
   check(email.split('@')[0]+' denied Director authorization and private bytes',helper.wf(q,'authorize',b)['status']!=200 and helper.wf(q,'evidence',fresh)['status']!=200);ctx.close()
  check('Applicant cannot authorize own identity',helper.wf(ap,'authorize',request(p,cid,'ACK'))['status']!=200)
  # Owned fixture authority changes are rolled back in finally; never real accounts.
  for field,value in [('status','SUSPENDED'),('governance_class','ADVISOR')]:
   prior=sql('SELECT '+field+' FROM internal_staff WHERE account_id=%s',('qa_director',))[0][0]
   try:
    sql('UPDATE internal_staff SET '+field+'=%s WHERE account_id=%s',(value,'qa_director'))
    check('Current Director '+field+' revocation denies consumption',helper.wf(p,'evidence',fresh)['status']!=200)
   finally:sql('UPDATE internal_staff SET '+field+'=%s WHERE account_id=%s',(prior,'qa_director'))
  check('No obsolete challenge or verification HTTP requests',all(x['action'] not in ['challenge','verify'] for x in requests))
  check('No generated MFA proofs for ordinary staff actions',sql("SELECT COUNT(*) FROM auth_staff_mfa_proofs WHERE operation='STAFF_ADMISSION_REVIEW'")[0][0]==0)
  # Missing CSRF and anonymous session, including after actual manual logout.
  status=p.evaluate("""async b => (await fetch('/api/internal/staff-admission/workflow.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'evidence',input:b})})).status""",fresh)
  check('CSRF remains mandatory',status==403)
  select(p,cid);p.get_by_role('button',name='Cerrar sesión',exact=True).click();p.wait_for_url('**/public/identity/acceso.php**',timeout=20000)
  check('Logged-out session denied',helper.wf(p,'evidence',fresh)['status']==401)
  login(p);check('Manual logout and legitimate relogin',good(p,'bootstrap')['account_id']=='qa_director')
  check('Intent cannot move to a new login session',helper.wf(p,'evidence',fresh)['status']!=200)
  # Exact canonical audit links, not browser success alone.
  rows=sql('SELECT reviewer_account_id,audit_event_id FROM auth_staff_identity_reviews WHERE case_id IN (%s,%s,%s)',(cid,irene['case'],reject['case']))
  check('Canonical decisions attributed only to actual Director',bool(rows) and all(x[0]=='qa_director' for x in rows))
  check('Every decision has a physical canonical audit event',all(sql('SELECT COUNT(*) FROM platform_audit_events WHERE event_id=SHA2(%s,256) AND real_actor_reference=\'account:qa_director\'',(x[1],))[0][0]==1 for x in rows))
  # Keep Martha pending with current documents for Director visual inspection.
  ac.close();context.close();browser.close()
  for engine in ['chromium','webkit']:
   br=getattr(pw,engine).launch(headless=True);ctx=br.new_context(ignore_https_errors=True);q=ctx.new_page();q.on('pageerror',log_error);login(q);select(q,cid)
   for width,height in [(1366,768),(390,844)]:
    q.set_viewport_size({'width':width,'height':height});read(q);q.get_by_role('button',name='Ampliar Anverso · INE',exact=True).click();q.keyboard.press('Escape');expect(q.locator('.evidence-page-grid')).to_be_visible();q.screenshot(path=str(shots/f'{engine}-{width}.png'));close(q)
    check(f'{engine} {width} direct viewer, keyboard return and containment',q.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   ctx.close();br.close()
  check('No JavaScript runtime errors',not errors)
 (out/'workflow.json').write_text(json.dumps({'result':'PASS','martha_case':cid,'javascript_errors':errors,'engines':['Chromium','WebKit'],'session_policy':'canonical Director session; no per-operation credentials'},indent=2))
