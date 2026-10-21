"""Targeted canonical document lifecycle, real Chromium UI and WebKit verification."""
import pathlib,json,uuid,importlib.util,time,hashlib
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(r,check,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];out=r['OUT'];shots=out/'screens';shots.mkdir(exist_ok=True)
 helper=module('lc_helper',ROOT/'scripts/ase-adm02-oper09-browser.py');native=module('lc_native',ROOT/'scripts/ase-advisor-workspace-review.py')
 review=module('lc_review',ROOT/'scripts/staff-doc-lifecycle03-review.py');errors=[];requests=[]
 def good(p,action,body={}):
  result=helper.wf(p,action,body);assert result['status']==200,(action,result['status']);return result['body']
 def login(p,email='qa_director@example.test',applicant=False):native.login(p,origin,email,(owned/('applicant-password' if applicant else 'fixture-password')).read_text().strip(),'/staff-admission.php' if applicant else review.DIRECTOR)
 def select(p,cid):
  p.reload();expect(p.locator('#add-member')).to_be_enabled();p.locator('[data-case="'+cid+'"],[data-admission-case="'+cid+'"]').first.click();expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def finish(p):
  expect(p.locator('#staff-password')).to_be_visible();p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
  last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
  while last is not None and int(time.time())//30<=last:p.wait_for_timeout(300)
  p.locator('#staff-code').fill(helper.review.totp(owned));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000)
 def action(p,a):
  button=p.locator('#advisor-detail [data-review-action='+a+']').first
  expect(button).to_be_attached(timeout=20000)
  for d in button.locator('xpath=ancestor::details').all():
   if not d.evaluate('(e)=>e.open'):d.locator('summary').first.click()
  button.click();finish(p)
 def read(p,setid=None):
  button=p.locator('#advisor-detail [data-consult-set'+('="'+setid+'"' if setid else '')+']').first
  expect(button).to_be_attached(timeout=20000)
  for d in button.locator('xpath=ancestor::details').all():
   if not d.evaluate('(e)=>e.open'):d.locator('summary').first.click()
  button.click();finish(p);expect(p.locator('#staff-evidence')).to_be_visible();expect(p.locator('.evidence-page-grid img')).to_have_count(2)
  assert p.locator('.evidence-page-grid img').evaluate_all('(xs)=>xs.every(x=>x.complete&&x.naturalWidth>0)')
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
  assert state['state']=='REVIEW_REQUIRED'
  record={'case':case['case_id'],'email':email,'account':sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(case['case_id'],))[0][0]};path.write_text(json.dumps(record));return record
 with sync_playwright() as pw:
  checkpoint=owned/'lifecycle-core-observed.json'
  if checkpoint.exists():
   saved=json.loads(checkpoint.read_text())
   assert all(hashlib.sha256((ROOT/n).read_bytes()).hexdigest()==h for n,h in saved['source_hashes'].items())
   for item in saved['checks']:check(item['name'],item['pass'])
   cid=json.loads((owned/'staff06-martha.json').read_text())['case']
   sets=sql('SELECT set_id FROM auth_staff_document_sets WHERE case_id=%s ORDER BY sequence_number DESC',(cid,));new={'set_id':sets[0][0]};set_before={'set_id':sets[1][0]}
  else:
   browser,context,p=native.native_browser(pw);p.on('pageerror',lambda e:errors.append(str(e)));login(p)
   p.on('request',lambda req:requests.append(req.post_data_json) if '/api/internal/staff-admission/workflow.php' in req.url and req.method=='POST' else None)
   irene=prepare(p,'oper09-advisor.json','oper09_advisor@example.test','Irene Ficticia','Oper09');select(p,irene['case'])
   if not good(p,'view',{'case_id':irene['case']})['admitted']:
    read(p);close(p);action(p,'ACCEPT');action(p,'ADMIT')
   martha=prepare(p,'staff06-martha.json','martha_doc@example.test','Martha','Figueroa','Rosado');cid=martha['case'];select(p,cid)
   v=good(p,'view',{'case_id':cid});initial=v['sets'][0];before=sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]
   read(p);close(p);read(p);check('A repeated protected consultation renders images after close without acknowledgement',True);close(p)
   check('Closing documents never manufactures acknowledgement',sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]==before)
   read_request=[x['input'] for x in requests if x['action']=='evidence'][-1]
   check('Consumed READ proof remains one-use',helper.wf(p,'evidence',read_request)['status']!=200)
   read(p);p.locator('#ack-staff-evidence').click();finish(p);read(p);check('B consultation after explicit acknowledgement',True);close(p)
   action(p,'ACCEPT');read(p);check('C consultation after evidence acceptance',True);close(p)
   if not good(p,'view',{'case_id':cid})['admitted']:action(p,'ADMIT')
   select(p,cid);expect(p.locator('#detail-title')).to_have_text('Martha Figueroa Rosado');expect(p.locator('#advisor-detail .status')).to_contain_text('Activo')
   check('Admitted member shows ordinary staff controls, not another onboarding',p.locator('#lifecycle').count()==1 and p.get_by_text('Revisar incorporación',exact=True).count()==0)
   read(p);check('D post-admission protected current images rendered',True);close(p)
   staff_before=sql('SELECT * FROM internal_staff WHERE account_id=%s',(martha['account'],));grants_before=sql('SELECT * FROM internal_operator_grants WHERE account_id=%s',(martha['account'],));assets_before=sql('SELECT asset_id,ciphertext_hash,storage_key FROM auth_staff_media_assets WHERE case_id=%s ORDER BY asset_version,purpose',(cid,));set_before=good(p,'view',{'case_id':cid})['sets'][0]
   ac=browser.new_context(ignore_https_errors=True);ap=ac.new_page();login(ap,martha['email'],True);ap.locator('#wf-cases').select_option(cid);expect(ap.locator('#wf-review-state')).to_contain_text('Admisión registrada')
   ap.locator('#wf-update-mode').select_option('CORRECT');ap.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-justification').fill('Nueva imagen geométrica sintética para corregir legibilidad.');ap.locator('#wf-add-document').click();expect(ap.locator('#wf-documents')).to_contain_text('INE: 2 cara(s) añadidas');ap.locator('#wf-consent').check();ap.locator('#wf-submit').click();expect(ap.locator('#wf-review-state')).to_contain_text('Nueva entrega pendiente de revisión')
   v=good(p,'view',{'case_id':cid});new=v['sets'][0];old_doc=json.loads(set_before['manifest'])[0];new_doc=json.loads(new['manifest'])[0]
   check('G immutable replacement retains document identity and advances version',new['set_id']!=set_before['set_id'] and new_doc['document_id']==old_doc['document_id'] and new_doc['document_version']==old_doc['document_version']+1)
   check('H earlier metadata and ciphertext hashes remain unchanged',assets_before==sql('SELECT asset_id,ciphertext_hash,storage_key FROM auth_staff_media_assets WHERE case_id=%s ORDER BY asset_version,purpose LIMIT %s',(cid,len(assets_before))))
   check('I replacement does not inherit old review or acknowledgement',not v['review_current'] and not v['document_ack_current'])
   check('J active staff and grants remain byte-equivalent',staff_before==sql('SELECT * FROM internal_staff WHERE account_id=%s',(martha['account'],)) and grants_before==sql('SELECT * FROM internal_operator_grants WHERE account_id=%s',(martha['account'],)))
   check('L single admission and stable account binding',sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(cid,))[0][0]==1 and good(p,'case',{'case_id':cid})['account_bound'])
   select(p,cid);read(p,new['set_id']);expect(p.locator('.evidence-version').first).to_contain_text('versión '+str(new['sequence_number']));check('E current set exact version shown in UI',True);close(p)
   read(p,set_before['set_id']);expect(p.locator('#staff-evidence-context')).to_contain_text('Conjunto histórico');expect(p.locator('#ack-staff-evidence')).not_to_be_visible();check('F archived previous set remains protected readable and distinct',True)
   p.get_by_role('button',name='Ampliar Anverso · INE',exact=True).click();p.keyboard.press('Escape');expect(p.locator('#ack-staff-evidence')).not_to_be_visible();p.screenshot(path=str(shots/'historical-documents.png'));close(p)
   check('Historical consultation does not reset current review',not good(p,'view',{'case_id':cid})['document_ack_current'])
   for email,applicant in [(irene['email'],True),('qa_master@example.test',False)]:
    ctx=browser.new_context(ignore_https_errors=True);q=ctx.new_page();login(q,email,applicant)
    check('K '+email.split('@')[0]+' cross-case metadata and evidence denied',helper.wf(q,'view',{'case_id':cid})['status']!=200 and helper.wf(q,'evidence',read_request)['status']!=200);ctx.close()
   check('Cross-case set binding denied despite Director authority',helper.wf(p,'challenge',{'case_id':irene['case'],'set_id':new['set_id'],'action':'READ','reason':'Consulta sintética cruzada','password':(owned/'fixture-password').read_text().strip()})['status']!=200)
   check('M historical reads attributed to current actor and canonical audit',bool(sql("SELECT i.intent_id FROM auth_staff_review_intents i WHERE i.case_id=%s AND i.set_id=%s AND i.action='READ' AND i.actor_account_id='qa_director' AND i.consumed_at IS NOT NULL",(cid,set_before['set_id']))))
   ac.close();browser.close()
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':768});q=ctx.new_page();q.on('pageerror',lambda e:errors.append(str(e)));login(q);select(q,cid);read(q,new['set_id'] if engine=='chromium' else set_before['set_id'])
   for width,height in [(1366,768),(820,900),(390,844),(1366,420)]:
    q.set_viewport_size({'width':width,'height':height});q.get_by_role('button',name='Ampliar Anverso · INE',exact=True).click();expect(q.locator('.evidence-enlarged')).to_be_visible();expect(q.locator('#evidence-back')).to_be_in_viewport();q.keyboard.press('Escape');expect(q.locator('.evidence-enlarged')).not_to_be_visible();expect(q.locator('#staff-evidence')).to_be_visible();expect(q.locator('#close-staff-evidence')).to_be_visible();expect(q.locator('.evidence-page-grid img')).to_have_count(2)
    check('N '+engine+' viewport '+str(width)+'x'+str(height),q.evaluate('document.documentElement.scrollWidth<=innerWidth') and q.locator('#staff-evidence').evaluate('(e)=>{const r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1}'))
    q.screenshot(path=str(shots/(engine+'-'+str(width)+'x'+str(height)+'.png')))
   close(q);b.close()
 check('No JavaScript errors',not errors)
 (out/'workflow.json').write_text(json.dumps({'result':'PASS','page_errors':errors,'data':'Synthetic example.test accounts and geometric images only','admission_case':cid,'original_set':set_before['set_id'],'current_set':new['set_id']},indent=2))
