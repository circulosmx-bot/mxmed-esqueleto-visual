"""Focused canonical evidence access and presentation checks; all fixtures artificial."""
import pathlib,json,uuid,importlib.util
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(r,check,cli):
 # Existing canonical admission creates the independent Advisor, without direct role writes.
 module('doc_admission',ROOT/'scripts/staff-control06-browser.py').run(r,check,cli)
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];out=r['OUT'];shots=out/'screens';shots.mkdir(exist_ok=True)
 helper=module('doc_helper',ROOT/'scripts/ase-adm02-oper09-browser.py');native=module('doc_native',ROOT/'scripts/ase-advisor-workspace-review.py');review=module('doc_review',ROOT/'scripts/staff-doc-vis01-review.py')
 errors=[];measures=[];request=[]
 def good(p,action,body):
  v=helper.wf(p,action,body);assert v['status']==200,(action,v['status']);return v['body']
 def login(p):native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
 def selected(p,case):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
  p.locator('[data-case="'+case+'"]').first.click();expect(p.locator('#detail-title')).to_contain_text('Martha');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def layout(p,label):
  expect(p.locator('#staff-evidence')).to_be_visible();expect(p.locator('#staff-evidence-title')).to_have_text('DOCUMENTACIÓN DE IDENTIDAD DEL INTEGRANTE');expect(p.locator('.evidence-applicant')).to_have_text('Martha Figueroa Rosado');expect(p.locator('.evidence-status')).to_have_text('Asesor · Pendiente de revisión')
  expect(p.locator('.evidence-document')).to_have_count(1);expect(p.locator('.evidence-document h3')).to_have_text('INE');expect(p.locator('.evidence-page-grid figcaption')).to_have_text(['Anverso','Reverso']);expect(p.locator('.evidence-synthetic')).to_be_visible();expect(p.locator('.evidence-version').first).to_contain_text('versión 1')
  body=p.locator('#staff-evidence .team-dialog-body');body.evaluate('(e)=>e.scrollTop=e.scrollHeight');last=p.locator('.evidence-page-grid figure').last;last.scroll_into_view_if_needed();expect(last).to_be_in_viewport();expect(p.locator('#close-staff-evidence')).to_be_in_viewport()
  boxes=p.locator('.evidence-page-grid figure').evaluate_all('(els)=>els.map(e=>{const r=e.getBoundingClientRect();return {x:r.x,y:r.y,width:r.width}})');width=p.evaluate('innerWidth');columns=abs(boxes[0]['y']-boxes[1]['y'])<2
  check(label+' exact applicant, INE, current document grouping and explicit sides',True)
  check(label+' responsive columns and reachable final image/footer',columns==(width>640) and p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
  check(label+' image aspect ratio preserved',p.locator('.evidence-preview img').evaluate_all('(es)=>es.every(e=>Math.abs(e.clientWidth/e.clientHeight-e.naturalWidth/e.naturalHeight)<.02)'))
  p.screenshot(path=str(shots/(label.replace(' ','-')+'.png')));measures.append({'label':label,'width':width,'figures':boxes,'dialog':p.locator('#staff-evidence').bounding_box(),'footer':p.locator('#close-staff-evidence').bounding_box()})
  p.locator('.evidence-preview').last.click();expect(p.locator('.evidence-enlarged')).to_be_visible();expect(p.locator('.evidence-enlarged img')).to_be_visible();expect(p.locator('#close-staff-evidence')).to_be_in_viewport();check(label+' enlarged image preserves aspect ratio',p.locator('.evidence-enlarged img').evaluate('(e)=>Math.abs(e.clientWidth/e.clientHeight-e.naturalWidth/e.naturalHeight)<.02'));p.locator('.evidence-enlarged button').click();expect(p.locator('.evidence-enlarged img')).not_to_have_attribute('src',__import__('re').compile('.+'))
 with sync_playwright() as pw:
  b,c,p=native.native_browser(pw);p.on('pageerror',lambda e:errors.append(str(e)));login(p)
  f=owned/'staff06-martha.json'
  if f.exists():martha=json.loads(f.read_text())
  else:
   case=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':'Martha','first_surname':'Figueroa','second_surname':'Rosado','operation_state':'Aguascalientes','operation_city':'Calvillo'})
   invitation=good(p,'issue',{'case_id':case['case_id'],'expected_version':case['version'],'operation_id':str(uuid.uuid4()),'recipient_email':'martha_doc@example.test'});martha={'case':case['case_id'],'email':'martha_doc@example.test','invitation':invitation['invitation_id']};f.write_text(json.dumps(martha))
  p.reload();selected(p,martha['case'])
  if good(p,'simulation_status',{'case_id':martha['case']})['state']=='NOT_REQUESTED':p.locator('#simulate-onboarding').click()
  expect(p.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000)
  expect(p.locator('#staff-case-content')).to_have_js_property('inert',False)
  p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-password')).to_be_visible(timeout=20000);p.locator('#staff-password').fill('incorrect-synthetic-password');p.locator('#staff-challenge').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se autorizó');check('Wrong password does not expose private images',p.locator('#protected-case-pages img').count()==0)
  p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();p.locator('#staff-code').fill('12345');p.locator('#staff-execute').click();expect(p.locator('#staff-stepup-status')).to_contain_text('No se validó');check('Invalid MFA cannot open evidence',not p.locator('#staff-evidence').is_visible());p.locator('#cancel-staff-stepup').click()
  def capture(req):
   if '/api/internal/staff-admission/workflow.php' in req.url and req.method=='POST':
    data=req.post_data_json
    if data.get('action')=='evidence':request.append(data['input'])
  p.on('request',capture);helper.review_action(p,'READ',owned,sql);layout(p,'native Chromium')
  check('READ does not decide identity or complete admission',good(p,'view',{'case_id':martha['case']})['review'] is None and not sql('SELECT case_id FROM auth_staff_admission_completions WHERE case_id=%s',(martha['case'],)))
  p.locator('#close-staff-evidence').click();expect(p.locator('#staff-evidence')).not_to_be_visible();expect(p.locator('#staff-evidence img')).to_have_count(0);check('Close clears all private sources and returns exact selected case',p.locator('#staff-evidence img').count()==0 and p.locator('#staff-evidence-context').inner_text()=='' and 'Martha' in p.locator('#detail-title').inner_text());expect(p.locator('[data-review-action=ACCEPT]')).to_be_visible();check('Next actions remain canonical and admission is separate',not p.locator('[data-review-action=ADMIT]').count())
  check('Consumed READ proof cannot be replayed',helper.wf(p,'evidence',request[-1])['status']!=200)
  record=json.loads((owned/'oper09-advisor.json').read_text());ac=b.new_context(ignore_https_errors=True);ap=ac.new_page();native.login(ap,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR)
  check('Independent Advisor cannot read other case metadata',helper.wf(ap,'view',{'case_id':martha['case']})['status']!=200)
  check('Independent Advisor cannot use Director evidence proof',helper.wf(ap,'evidence',request[-1])['status']!=200);ac.close()
  p.locator('[data-member="'+record['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Irene');p.locator('#identity-evidence summary').click();p.locator('#member-evidence').click();expect(p.locator('[data-review-action=READ]')).to_be_visible();helper.review_action(p,'READ',owned,sql);expect(p.locator('.evidence-applicant')).to_contain_text('Irene');expect(p.locator('.evidence-status')).to_contain_text('Evidencia aceptada');check('Applicant switch has only second canonical applicant and review state', 'Martha' not in p.locator('#staff-evidence').inner_text());p.keyboard.press('Escape');expect(p.locator('#staff-evidence')).not_to_be_visible();expect(p.locator('#staff-evidence img')).to_have_count(0);check('Escape clears private content',p.locator('#staff-evidence img').count()==0);b.close()
  for engine,sizes in [('chromium',[(1440,900),(1366,768),(390,844),(1366,420)]),('webkit',[(1366,768),(390,844)])]:
   browser=getattr(pw,engine).launch();ctx=browser.new_context(ignore_https_errors=True,viewport={'width':sizes[0][0],'height':sizes[0][1]});page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)));login(page);selected(page,martha['case']);helper.review_action(page,'READ',owned,sql)
   for width,height in sizes:page.set_viewport_size({'width':width,'height':height});layout(page,engine+' '+str(width)+'x'+str(height))
   if engine=='chromium':page.mouse.click(2,2)
   else:page.keyboard.press('Escape')
   expect(page.locator('#staff-evidence')).not_to_be_visible();expect(page.locator('#staff-evidence img')).to_have_count(0);check(engine+' dismissal clears all private images',page.locator('#staff-evidence img').count()==0);browser.close()
  # Presentation-only fixtures exercise absent metadata and numbered/unknown roles.
  # They never change canonical records or intercept authorization responses.
  browser=pw.chromium.launch();fp=browser.new_page();fp.set_content('<dialog id="staff-evidence"><div id="staff-evidence-context"></div><div id="protected-case-pages"></div><p id="staff-evidence-next"></p></dialog>');fp.add_script_tag(path=str(ROOT/'assets/js/staff-evidence-viewer.js'))
  result=fp.evaluate('''()=>{const context={name:'Fixture de presentación',category:'ADVISOR',set:{sequence_number:3,case_version:4,manifest:[{document_id:'fixture',document_version:2}]},reviewCurrent:false,actions:[]};const pages=[{document_id:'fixture',page_number:1},{document_id:'fixture',page_number:2},{document_id:'fixture'},{document_id:'fixture',side:'BIOGRAPHIC'}].map(p=>({...p,mime_type:'image/png',base64:''}));staffEvidenceViewer.render(context,pages);return {type:document.querySelector('.evidence-document h3').textContent,labels:[...document.querySelectorAll('figcaption')].map(e=>e.textContent),synthetic:document.querySelectorAll('.evidence-synthetic').length};}''')
  check('Missing kind uses neutral type without fabricating INE or Passport',result['type']=='Documento de identidad — Tipo no especificado');check('Recorded page numbers, unknown image role and biographic side use exact labels',result['labels']==['Página 1','Página 2','Imagen 3','Página biográfica']);check('Renderer never injects synthetic banner outside owned server context',result['synthetic']==0);browser.close()
 check('No JavaScript errors in canonical viewer flows',not errors)
 (out/'viewer.json').write_text(json.dumps({'result':'PASS','measurements':measures,'page_errors':errors,'metadata_fallback_test':'Presentation-only artificial fixture; no canonical upload changes','security':'Canonical password/MFA, exact receipts, replay and Advisor denials'},indent=2))
