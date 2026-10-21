"""Focused real-browser canonical workflow checks in a fresh owned composition."""
import pathlib,json,uuid,importlib.util,time
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(r,check,cli):
 # Fixture preparation uses existing Identity/admission/grant writers, not direct authority writes.
 module('vis02_admission',ROOT/'scripts/staff-control06-browser.py').run(r,check,cli)
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];out=r['OUT'];shots=out/'screens';shots.mkdir(exist_ok=True)
 helper=module('vis02_helper',ROOT/'scripts/ase-adm02-oper09-browser.py');native=module('vis02_native',ROOT/'scripts/ase-advisor-workspace-review.py');review=module('vis02_review',ROOT/'scripts/staff-doc-vis02-review.py')
 errors=[];requests=[];measures=[]
 def good(p,action,body={}):
  v=helper.wf(p,action,body);assert v['status']==200,(action,v['status']);return v['body']
 def login(p):native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
 def selected(p,cid):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator('[data-case="'+cid+'"]').first.click();expect(p.locator('#detail-title')).to_contain_text('Martha');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def finish(p,reason=None):
  expect(p.locator('#staff-password')).to_be_visible(timeout=20000)
  if reason:p.locator('#staff-reason').fill(reason)
  p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
  last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
  while last is not None and int(time.time())//30<=last:p.wait_for_timeout(300)
  p.locator('#staff-code').fill(helper.review.totp(owned));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000);expect(p.locator('#staff-case-status')).to_contain_text('operación confirmada')
 def expand(p,action):
  for details in p.locator('[data-review-action='+action+']').locator('xpath=ancestor::details').all():
   if not details.evaluate('(e)=>e.open'):details.locator('summary').first.click()
 def read(p):
  expand(p,'READ');helper.review_action(p,'READ',owned,sql)
 def ack(p):p.locator('#ack-staff-evidence').click();finish(p);expect(p.locator('#document-ack-status')).to_have_text('Documentos revisados')
 def nav(p,label):
  expect(p.locator('#staff-evidence')).to_be_visible();expect(p.locator('.evidence-applicant')).to_have_text('Martha Figueroa Rosado');expect(p.locator('.evidence-page-grid figcaption')).to_have_text(['Anverso','Reverso'])
  for side in ['Anverso','Reverso']:
   p.get_by_role('button',name='Ampliar '+side+' · INE',exact=True).click();expect(p.locator('.evidence-enlarged')).to_be_visible();expect(p.locator('#close-staff-evidence')).not_to_be_visible();expect(p.locator('#ack-staff-evidence')).not_to_be_visible();expect(p.locator('#evidence-back')).to_be_in_viewport()
   if side=='Anverso':p.locator('#evidence-back').click()
   else:p.keyboard.press('Escape')
   expect(p.locator('.evidence-enlarged')).not_to_be_visible();expect(p.locator('#staff-evidence')).to_be_visible();expect(p.locator('.evidence-preview')).to_have_count(2);expect(p.locator('#close-staff-evidence')).to_be_in_viewport();expect(p.locator('#ack-staff-evidence')).to_be_in_viewport()
  check(label+' both sides return to complete overview, Escape and reachable distinct footers',True)
  check(label+' viewport containment',p.evaluate('document.documentElement.scrollWidth<=innerWidth') and p.locator('#staff-evidence').evaluate('(e)=>{let r=e.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1}'))
  p.screenshot(path=str(shots/(label.replace(' ','-')+'.png')));measures.append({'label':label,'dialog':p.locator('#staff-evidence').bounding_box(),'ack':p.locator('#ack-staff-evidence').bounding_box()})
 with sync_playwright() as pw:
  b,c,p=native.native_browser(pw);p.on('pageerror',lambda e:errors.append(str(e)));login(p)
  def capture(req):
   if '/api/internal/staff-admission/workflow.php' in req.url and req.method=='POST':
    data=req.post_data_json
    if data.get('action') in ['evidence','acknowledge']:requests.append(data)
  p.on('request',capture)
  f=owned/'staff06-martha.json'
  if f.exists():martha=json.loads(f.read_text())
  else:
   case=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':'Martha','first_surname':'Figueroa','second_surname':'Rosado','operation_state':'Aguascalientes','operation_city':'Calvillo'})
   invitation=good(p,'issue',{'case_id':case['case_id'],'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'martha_doc@example.test'});martha={'case':case['case_id'],'email':'martha_doc@example.test','invitation':invitation['invitation_id']};f.write_text(json.dumps(martha))
  cid=martha['case'];p.reload();selected(p,cid)
  if good(p,'simulation_status',{'case_id':cid})['state']=='NOT_REQUESTED':p.locator('#simulate-onboarding').click()
  expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000);expect(p.locator('#staff-case-content')).to_have_js_property('inert',False)
  invitation=good(p,'invitation_status',{'case_id':cid})['invitation'];source=sql('SELECT issued_at,delivered_at,consumed_at,consumed_account_id,expires_at FROM auth_staff_admission_invitations WHERE invitation_id=%s',(martha['invitation'],))[0]
  check('Invitation projects authoritative issuance, local transport acknowledgement, authenticated acceptance and expiry',invitation['state']=='CONSUMED' and all(invitation[k] is not None for k in ['issued_at','delivered_at','consumed_at','consumed_account_id']) and invitation['consumed_account_id']==source[3] and not invitation['expired'])
  check('Invitation response never exposes token or digest',not any('token' in k or 'digest' in k for k in invitation))
  p.locator('#invitation-details summary').click();expect(p.locator('#invitation-details')).to_contain_text('ACEPTADA');expect(p.locator('#invitation-details')).to_contain_text('Ciudad de México');expect(p.locator('#invitation-details')).to_contain_text('No equivale a envío a la persona.');check('Human invitation dates, accepted state and no fictional external delivery visible',True)
  # Test presentation against a future browser clock; canonical accepted status must remain accepted.
  p.evaluate('window.__realNow=Date.now;Date.now=()=>4102444800000');p.locator('#refresh-team').click();selected(p,cid);p.locator('#invitation-details summary').click();expect(p.locator('#invitation-details')).to_contain_text('ACEPTADA');p.evaluate('Date.now=window.__realNow');check('Accepted invitation stays accepted past historical expiry in reviewer clock',True)
  expect(p.locator('#revoke-preparation')).to_have_text('Cancelar incorporación');expect(p.locator('#staff-case-content')).to_contain_text('Se cancelará esta incorporación sin eliminar los datos y antecedentes registrados de la persona.');check('Cancellation copy preserves existing confirmation writer',True)
  # No READ or acknowledgement is manufactured by opening/closing a UI.
  before=sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]
  expand(p,'READ');p.locator('[data-review-action=READ]').click();p.locator('#staff-password').fill('incorrect-synthetic-password');p.locator('#staff-challenge').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se autorizó');check('Incorrect password cannot reveal evidence',p.locator('#protected-case-pages img').count()==0)
  p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();p.locator('#staff-code').fill('12345');p.locator('#staff-execute').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se validó');p.locator('#cancel-staff-stepup').click();check('Invalid MFA cannot reveal evidence',not p.locator('#staff-evidence').is_visible())
  read(p);nav(p,'native Chromium');p.locator('#close-staff-evidence').click();expect(p.locator('#staff-evidence img')).to_have_count(0)
  check('Close without acknowledgement neither writes examination nor accepts/admit',sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]==before and not good(p,'view',{'case_id':cid})['admitted'])
  readRequest=[x for x in requests if x['action']=='evidence'][-1];check('Consumed READ proof cannot be replayed',helper.wf(p,'evidence',readRequest['input'])['status']!=200)
  read(p);ack(p);v=good(p,'view',{'case_id':cid});a=v['document_ack'];set1=v['sets'][0]
  row=sql('SELECT case_id,case_version,set_id,reviewer_account_id,reviewer_role,audit_event_id,created_at FROM auth_staff_document_acknowledgements WHERE acknowledgement_id=%s',(a['acknowledgement_id'],))[0]
  check('Explicit acknowledgement persists exact case/version/set, actual Director, server timestamp and canonical audit',v['document_ack_current'] and row[:5]==(cid,int(set1['case_version']),set1['set_id'],'qa_director','DIRECTOR') and row[5]==a['audit_event_id'] and row[6] is not None and bool(sql('SELECT event_id FROM platform_audit_events WHERE event_id=SHA2(%s,256)',(a['audit_event_id'],))))
  check('Acknowledgement does not accept identity, admit staff or grant permissions',not v['admitted'] and (not v['review'] or v['review']['decision']!='ACCEPT'))
  ackRequest=[x for x in requests if x['action']=='acknowledge'][-1]
  count=sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0];good(p,'acknowledge',ackRequest['input']);check('Exact ACK retry returns receipt without another write',sql('SELECT COUNT(*) FROM auth_staff_document_acknowledgements WHERE case_id=%s',(cid,))[0][0]==count)
  check('ACK proof cannot be used for identity decision',helper.wf(p,'decide',ackRequest['input'])['status']!=200)
  # Independent Advisor and Master identities do not inherit Director authority.
  advisor=json.loads((owned/'oper09-advisor.json').read_text())
  for email,password in [(advisor['email'],(owned/'applicant-password').read_text().strip()),('qa_master@example.test',(owned/'fixture-password').read_text().strip())]:
   ctx=b.new_context(ignore_https_errors=True);ap=ctx.new_page();native.login(ap,origin,email,password,review.ADVISOR)
   check(email.split('@')[0]+' cannot read or acknowledge Director applicant evidence',helper.wf(ap,'view',{'case_id':cid})['status']!=200 and helper.wf(ap,'acknowledge',ackRequest['input'])['status']!=200);ctx.close()
  # Real correction request, persisted by the existing decision writer.
  expand(p,'CORRECTIONS');p.locator('[data-review-action=CORRECTIONS]').click();expect(p.locator('#staff-stepup-title')).to_have_text('Solicitar corrección de documentos');expect(p.locator('#staff-reason-caption')).to_have_text('¿Qué necesita corregir?');expect(p.locator('#staff-corrections-channel')).to_contain_text('No se envía correo');reason='Prueba sintética: vuelve a presentar ambas caras de la INE con la imagen geométrica actualizada.';finish(p,reason)
  v=good(p,'view',{'case_id':cid});rv=v['review'];check('Corrections persist exact actor/case/set/reason and audit without changing confirmed personal state',rv['decision']=='CORRECTIONS' and rv['reason']==reason and rv['reviewer_account_id']=='qa_director' and rv['set_id']==set1['set_id'] and bool(sql('SELECT review_id FROM auth_staff_identity_reviews WHERE case_id=%s AND review_id=%s AND audit_event_id=%s',(cid,rv['review_id'],rv['audit_event_id']))) and good(p,'case',{'case_id':cid})['state']=='PERSON_CONFIRMED')
  expect(p.locator('#admission-next-state')).to_contain_text('Correcciones solicitadas');p.screenshot(path=str(shots/'correction-director.png'))
  ac=b.new_context(ignore_https_errors=True);ap=ac.new_page();native.login(ap,origin,martha['email'],(owned/'applicant-password').read_text().strip(),'/staff-admission.php');ap.locator('#wf-cases').select_option(cid);expect(ap.locator('#wf-review-state')).to_contain_text(reason);check('Exact authenticated applicant sees canonical correction instructions',True);ap.screenshot(path=str(shots/'correction-applicant.png'))
  ap.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));ap.locator('#wf-justification').fill('Respuesta sintética a las correcciones: conjunto completo actualizado.');ap.locator('#wf-add-document').click();expect(ap.locator('#wf-documents')).to_contain_text('INE: 2 cara(s) añadidas')
  check('Replacement upload immediately invalidates prior acknowledgement',not good(p,'view',{'case_id':cid})['document_ack_current'])
  ap.locator('#wf-consent').check();ap.locator('#wf-submit').click();expect(ap.locator('#wf-review-state')).to_contain_text('Nueva entrega pendiente de revisión');v=good(p,'view',{'case_id':cid});set2=v['sets'][0];d1=json.loads(set1['manifest'])[0];d2=json.loads(set2['manifest'])[0]
  check('Applicant resubmission versions same logical document and invalidates old acknowledgement and decision',set2['set_id']!=set1['set_id'] and d2['document_id']==d1['document_id'] and d2['document_version']==d1['document_version']+1 and not v['review_current'] and not v['document_ack_current'] and v['document_ack']['set_id']==set1['set_id'])
  p.locator('#refresh-team').click();selected(p,cid);expect(p.locator('#correction-history')).to_contain_text(reason);expect(p.locator('#correction-history')).to_contain_text('nueva entrega');check('Director sees corrected current set and preserved correction attribution',True)
  check('Old consumed ACK receipt cannot mark replacement reviewed',good(p,'acknowledge',ackRequest['input'])['set_id']==set1['set_id'] and not good(p,'view',{'case_id':cid})['document_ack_current'])
  # Current ACK requires a new READ followed by its own fresh password/MFA.
  read(p);ack(p);check('Replacement requires and obtains its own explicit acknowledgement',good(p,'view',{'case_id':cid})['document_ack']['set_id']==set2['set_id']);ac.close();b.close()
  for engine,sizes in [('chromium',[(1366,768),(390,844),(1366,420)]),('webkit',[(1366,768),(390,844)])]:
   browser=getattr(pw,engine).launch();ctx=browser.new_context(ignore_https_errors=True,viewport={'width':sizes[0][0],'height':sizes[0][1]});page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));login(page);selected(page,cid);read(page)
   for width,height in sizes:page.set_viewport_size({'width':width,'height':height});nav(page,engine+' '+str(width)+'x'+str(height))
   page.locator('#close-staff-evidence').click();expect(page.locator('#staff-evidence img')).to_have_count(0);browser.close()
 check('No JavaScript errors in focused flows',not errors)
 (out/'workflow.json').write_text(json.dumps({'result':'PASS','measurements':measures,'page_errors':errors,'corrections_channel':'Authenticated applicant case; no external notification','ack_source':'auth_staff_document_acknowledgements; fresh intent/password/MFA; independent of identity decision','data':'Artificial geometric images and example.test identities only'},indent=2))
