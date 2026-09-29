"""WebKit clinical flow through disposable canonical APIs; Director is presentation only."""
import base64, hashlib, json, os, re, subprocess
from email.parser import BytesParser
from email.policy import default
from datetime import datetime,timedelta
from pathlib import Path
from urllib.parse import urlsplit,urljoin
from playwright.sync_api import sync_playwright,expect
BASE,DB=os.environ['FLOW_R1_QA_BASE'],os.environ['FLOW_R1_QA_DB']
assert DB.startswith('flow_r1_qa_') and len(DB)==23
OUT=Path(os.environ.get('FLOW_R1_ARTIFACTS','/Users/circulodigital/.codex/artifacts/step6-7-clinical-flow-r1'));OUT.mkdir(parents=True,exist_ok=True)
REVIEW='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=plan&review_placeholders=clean'
KEY='mxmed-plan02b:1:p_plan02ux_review:1016'
def sql(q):return subprocess.check_output(['mysql','-N',DB,'-e',q],text=True).strip()
def count(table):return int(sql('SELECT COUNT(*) FROM '+table))
def director():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounters ORDER BY encounter_id;SELECT * FROM clinical_documents ORDER BY id;SELECT * FROM clinical_encounter_sections ORDER BY encounter_id,section_type;SELECT * FROM clinical_observations ORDER BY observation_id'])).hexdigest()
checks={};writes=[];errors=[];unexpected=[];dialog_text=[];lost_order=False;failed_rx=False;shutting_down=False;discard_allowed=True;lost_result=False;active_routes=0
before=director()
def check(name,value=True):assert value,name;checks[name]='PASS';print('PASS '+name,flush=True)
with sync_playwright() as pw:
 browser=pw.webkit.launch();api=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
 def proxy(route):
  global active_routes
  active_routes+=1
  try:forward(route)
  finally:active_routes-=1
 def forward(route):
  global lost_order,failed_rx,lost_result
  if shutting_down:route.abort();return
  r=route.request;parsed=urlsplit(r.url);path=parsed.path+('?' + parsed.query if parsed.query else '')
  content=r.headers.get('content-type','');body=r.post_data_json if r.post_data and content.startswith('application/json') else None
  if r.method not in ['GET','HEAD']:
   writes.append({'path':re.sub(r'(/note-capture-tokens/)[^/]+',r'\1[redacted]',parsed.path),'method':r.method,'body':body,'key':r.headers.get('idempotency-key'),'multipart':content.startswith('multipart/')})
   if failed_rx and body and body.get('document_type')=='prescription':
    route.fulfill(status=400,json={'ok':False,'error':'INVALID_BODY'});return
  headers={'Accept':'application/json',**({'Idempotency-Key':r.headers['idempotency-key']} if 'idempotency-key' in r.headers else {})}
  if content.startswith('multipart/'):
   # WebKit protocol postData does not preserve uploaded file bytes; retain the
   # actual browser-selected File and original serialized metadata in this proxy.
   message=BytesParser(policy=default).parsebytes(('Content-Type: '+content+'\r\n\r\n').encode()+r.post_data_buffer)
   fields={part.get_param('name',header='content-disposition'):part.get_payload(decode=True).decode() for part in message.iter_parts() if not part.get_filename()}
   file=page.evaluate('async()=>{const n=document.querySelector("[data-docux-upload][open] [data-m7-doc-file]")||document.querySelector("[data-m7-result-file]");const f=n.files[0],bytes=new Uint8Array(await f.arrayBuffer());let raw="";for(const b of bytes)raw+=String.fromCharCode(b);return {name:f.name,mimeType:f.type,data:btoa(raw)}}')
   fields['file']={'name':file['name'],'mimeType':file['mimeType'],'buffer':base64.b64decode(file['data'])}
   result=api.post(BASE+path,multipart=fields,headers=headers)
  else:result=api.fetch(BASE+path,method=r.method,data=r.post_data_buffer,headers={**headers,**({'Content-Type':content} if content else {})})
  if not result.ok and content.startswith('multipart/'):
   print('MULTIPART_FAILURE='+str(result.status)+' '+str(result.json().get('error')),flush=True)
  if lost_order and body and body.get('document_type')=='order' and result.ok:
   lost_order=False;route.abort('failed');return
  if lost_result and content.startswith('multipart/') and result.ok:
   lost_result=False;route.abort('failed');return
  route.fulfill(response=result)
 def guard(route):
  r=route.request
  if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):unexpected.append(r.url);route.abort()
  else:route.continue_()
 def setup(w=1440,h=900):
  ctx=browser.new_context(viewport={'width':w,'height':h},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(22000)
  page.on('pageerror',lambda e:errors.append(str(e)));page.on('dialog',lambda d:(dialog_text.append(d.message),d.dismiss() if 'Descartar los cambios de este modal' in d.message and not discard_allowed else d.accept()))
  page.route('**/api/clinical/**',guard)
  for pattern in ['**/api/clinical/index.php/encounters/**','**/api/clinical/index.php/patients/*/encounters*','**/api/clinical/index.php/patients/*/encounters/**','**/api/clinical/index.php/doctors/*/patients/*/documents*','**/api/clinical/index.php/patients/*/longitudinal/tasks*','**/api/clinical/index.php/patients/*/longitudinal/tasks/**','**/api/clinical/index.php/documents/**','**/api/clinical/index.php/note-capture-tokens*','**/api/clinical/index.php/note-capture-tokens/**','**/api/agenda/index.php/**']:page.route(pattern,proxy)
  page.goto(REVIEW,wait_until='commit');expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true',timeout=55000)
  return ctx,page
 def step(page,name):
  page.locator(f'.m7-workspace-sections [data-m7-section="{name}"]').click();expect(page.locator(f'[data-m7-section="{name}"]')).to_have_attribute('aria-current','true')
  if name=='documents':expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
 def state(page):return page.evaluate('k=>JSON.parse(sessionStorage.getItem(k))',KEY)
 def open_kind(page,kind):
  try:page.locator(f'[data-plan02b] [data-ns="{kind}"]').click();expect(page.locator('.plan02b-modal')).to_be_visible()
  except Exception:
   page.screenshot(path=str(OUT/'failed-plan-launch-webkit.png'));(OUT/'failed-plan-launch.json').write_text(json.dumps({'errors':errors,'buttons':page.locator('[data-plan02b] button').evaluate_all('ns=>ns.map(n=>({text:n.textContent,rect:n.getBoundingClientRect().toJSON(),disabled:n.disabled}))')},indent=2));raise
 def order(page,title,mode='none'):
  open_kind(page,'orders');page.locator('[data-order-field="title"]').fill(title);page.locator('[data-order-review="mode"]').select_option(mode)
  if mode=='days':page.locator('[data-order-review="days"]').fill('10')
  if mode=='date':page.locator('[data-order-review="date"]').fill((datetime.now()+timedelta(days=11)).strftime('%Y-%m-%d'))
  page.locator('[data-modal-add]').click();expect(page.locator('.plan02b-modal')).to_have_count(0)
 def confirm(page):page.locator('[data-ns="confirm"]').click();page.wait_for_function('!window.mxmedPlanNextSteps.isBusy()');expect(page.locator('[data-ns-message]')).not_to_contain_text('Verificando registros')
 def capture(page):
  page.locator('[data-m7-capture-start]').click();page.locator('input[name="capture-classification"][value="clinical_image"]').check();page.locator('[data-docux-capture-generate]').click()
 ctx,page=setup()
 order(page,'Orden sin revisión');check('default order prepares no follow-up',len(state(page)['orderReviews'])==0 and len(state(page)['orders'])==1)
 # Appointment unavailable until an eligible appointment is selected/prepared.
 open_kind(page,'orders');check('next-appointment option safely disabled without next appointment',page.locator('[data-order-review="mode"] option[value="appointment"]').evaluate('n=>n.disabled'));page.locator('.plan02b-modal[open] [data-modal-cancel]').click()
 step(page,'finalize');page.locator('[data-remove]').click();step(page,'plan')
 order(page,'Estudio inicial','days');step(page,'finalize');s=state(page);check('10-day order prepares derived task only in orchestration',len(s['orderReviews'])==1 and s['orderReviews'][0]['derivedFromOrder']==s['orders'][0]['id'] and count('clinical_patient_tasks')==0)
 page.locator('[data-prepared]').filter(has=page.get_by_text('Orden de estudio',exact=True)).locator('[data-review]').click();page.locator('[data-order-field="title"]').fill('Estudio actualizado');page.locator('[data-modal-add]').click()
 check('order edit keeps derived title coherent',state(page)['orderReviews'][0]['title']=='Revisar resultado de Estudio actualizado')
 page.locator('[data-prepared]').filter(has=page.get_by_text('Orden de estudio',exact=True)).locator('[data-remove]').click();check('removing order removes its unconfirmed companion',not state(page)['orders'] and not state(page)['orderReviews'])
 step(page,'plan');open_kind(page,'followup');page.locator('[data-ns-title]').fill('Seguimiento general independiente');page.locator('[data-modal-add]').click();order(page,'Orden con revisión','date');step(page,'finalize');page.locator('[data-prepared]').filter(has=page.get_by_text('Orden de estudio',exact=True)).locator('[data-remove]').click();check('general follow-up survives derived removal',state(page)['followup']['title']=='Seguimiento general independiente' and not state(page)['orderReviews']);page.locator('[data-remove]').click();step(page,'plan')
 # Main four-action preparation through the actual Plan tools.
 order(page,'Biometría hemática','days')
 open_kind(page,'prescription')
 page.locator('[data-rx-field="medicamento"]').fill('Paracetamol de prueba');page.locator('[data-rx-field="dosis"]').fill('500 mg');page.locator('[data-rx-add]').click();page.locator('[data-rx-field="medicamento"]').nth(1).fill('Segundo medicamento de prueba');page.locator('[data-modal-add]').click()
 open_kind(page,'appointment');page.locator('input[name="ns-mode"][value="new"]').check();page.locator('[data-ns-location]').select_option('1');page.locator('[data-ns="availability"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();page.locator('[data-ns-slot]').first.click();page.locator('[data-modal-add]').click()
 check('four preparations and zero canonical domain writes',len(state(page)['orders'])==1 and state(page)['prescription'] and state(page)['appointment'] and len(state(page)['orderReviews'])==1 and not writes and count('clinical_documents')==0 and count('agenda_appointments')==0 and count('clinical_patient_tasks')==0)
 # Dedicated-view exit/resume must preserve same encounter and actions without executing.
 prepared=state(page);page.locator('[data-m7-exit]').click();expect(page.locator('[data-bs-target="#t-consulta-actual"]')).not_to_have_attribute('aria-selected','true')
 page.evaluate("()=>window.mxmedM7OpenFromHeader('p_plan02ux_review','resume')");page.wait_for_function('document.querySelector("[data-m7-body]").dataset.encounterId==="1016"');step(page,'plan');check('exit and same-encounter resume preserve preparation',state(page)==prepared and not writes)
 step(page,'documents');check('Step 6 contains no Plan collector or confirmation',page.locator('[data-m7-documents] [data-plan02b-collector]').count()==0 and page.locator('[data-ns="confirm"]:visible').count()==0)
 check('three document tools and no unconfirmed result target',page.locator('[data-doc-tool]').count()==3 and page.locator('[data-m7-result-order] option').count()==1)
 page.locator('[data-doc-tool="result"]').click();check('result requires canonical order',page.locator('[data-docux-result-guide]').is_visible() and page.locator('[data-docux-result-save]').is_disabled());page.locator('[data-docux-result-cancel]').click()
 step(page,'finalize');expect(page.locator('[data-prepared]')).to_have_count(4);expect(page.locator('[data-m7-finalize]')).to_be_disabled();check('5 to 6 to 7 retains all four and zero writes',not writes and sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')=='open')
 page.locator('[data-review-plan]').click();expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true');step(page,'finalize');page.locator('[data-review-docs]').click();expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true');step(page,'finalize');check('Edit Plan and Edit Documents preserve encounter and preparation',state(page)==prepared and not writes)
 page.mouse.move(0,0);page.evaluate('document.activeElement?.blur()');page.screenshot(path=str(OUT/'canonical-step7-pending-1440x900-webkit.png'))
 # One independent failure and one lost successful response: recover exact key/body.
 lost_order=True;failed_rx=True;confirm(page)
 s=state(page);check('partial failure preserves successful appointment and blocks derived task',s['appointment']['state']=='SUCCESS' and s['orders'][0]['state']=='FAILED' and s['orders'][0]['uncertain'] and s['prescription']['state']=='FAILED' and s['orderReviews'][0]['state']=='BLOCKED_BY_DEPENDENCY' and count('clinical_documents')==1 and count('agenda_appointments')==1 and count('clinical_patient_tasks')==0)
 expect(page.locator('[data-m7-finalize]')).to_be_disabled();check('unresolved indications guard finalization',not any(x['path'].endswith('/finalize') for x in writes))
 page.locator('[data-prepared]').filter(has=page.get_by_text('Receta',exact=True)).locator('[data-review]').click();page.locator('[data-rx-field="dosis"]').first.fill('Dosis reparada de prueba');page.locator('[data-modal-add]').click();failed_rx=False
 initial_order=[x for x in writes if x['body'] and x['body'].get('document_type')=='order'][0];appointments_before=len([x for x in writes if x['path'].endswith('/appointments')]);confirm(page)
 s=state(page);check('retry produces exact canonical counts and no repeated success',all(a['state']=='SUCCESS' for a in [*s['orders'],s['prescription'],s['appointment'],*s['orderReviews']]) and count('clinical_documents')==2 and count('agenda_appointments')==1 and count('clinical_patient_tasks')==1 and len([x for x in writes if x['path'].endswith('/appointments')])==appointments_before)
 retried_order=[x for x in writes if x['body'] and x['body'].get('document_type')=='order'][1];check('lost response recovers original idempotency key and payload',initial_order['key']==retried_order['key'] and initial_order['body']==retried_order['body'])
 task=next(x['body'] for x in writes if x['path'].endswith('/longitudinal/tasks'));check('derived task uses FUP01 authority without invented direct order link',set(task)=={'task_type','title','due_at','source_encounter_id','appointment_id'} and task['title']=='Revisar resultado de Biometría hemática' and task['source_encounter_id']==1016 and task['due_at'] and task['appointment_id'] is None)
 expect(page.locator('[data-ns="confirm"]')).to_have_count(0);expect(page.locator('[data-prepared]')).to_have_count(4);expect(page.locator('[data-action-state="SUCCESS"] .plan02b-state[data-state="SUCCESS"]')).to_have_count(4);expect(page.locator('[data-m7-finalize]')).to_be_enabled();check('confirmed summary uses canonical readback and encounter remains OPEN',sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')=='open')
 expect(page.locator('[data-review-documents]')).to_be_visible();expect(page.locator('[data-review-document-list] button')).to_have_count(2)
 check('generated canonical records shown without inventing PDFs',not re.search('PDF',page.locator('[data-review-documents]').inner_text(),re.I))
 page.locator('[data-review-document-list] button').first.click();expect(page.locator('[data-doc-reader]')).to_be_visible();check('generated metadata uses existing canonical document reader',page.locator('[data-doc-reader] .m7-doc-card').count()==2);page.locator('[data-doc-reader-close]').click();step(page,'finalize')
 page.mouse.move(0,0);page.evaluate('document.activeElement?.blur()');page.screenshot(path=str(OUT/'canonical-step7-confirmed-1440x900-webkit.png'))
 # Upload picker/drop cancellation are memory-only; explicit save uses accepted multipart.
 step(page,'documents');page.locator('[data-docux-attach]').click();image=base64.b64decode(page.evaluate('()=>{const c=document.createElement("canvas");c.width=128;c.height=96;const g=c.getContext("2d");g.fillStyle="#dbfcff";g.fillRect(0,0,128,96);g.fillStyle="#173f54";g.fillText("QA documental",8,50);return c.toDataURL("image/png").split(",")[1]}'));file={'name':'resultado-prueba.png','mimeType':'image/png','buffer':image}
 page.screenshot(path=str(OUT/'attach-document-modal-1440x900-webkit.png'));page.locator('[data-m7-doc-file]').set_input_files(file);check('upload file selection alone never writes',count('clinical_documents')==2);page.locator('[data-m7-doc-title]').fill('Documento clínico de prueba')
 discard_allowed=False;page.keyboard.press('Escape');expect(page.locator('[data-docux-upload]')).to_be_visible();check('attach dirty Escape rejection preserves local fields',page.locator('[data-m7-doc-title]').input_value()=='Documento clínico de prueba' and count('clinical_documents')==2);discard_allowed=True
 page.locator('[data-docux-upload] [data-modal-cancel]').click();check('upload cancel preserves canonical counts and no dirty state',count('clinical_documents')==2 and page.locator('[data-m7-doc-title]').input_value()=='' and page.locator('[data-m7-doc-file]').input_value()=='');check('attach returns focus',page.locator('[data-docux-attach]').evaluate('n=>document.activeElement===n'))
 page.locator('[data-docux-attach]').click();page.locator('[data-m7-doc-file]').set_input_files(file);page.locator('[data-m7-doc-title]').fill('Documento clínico de prueba');page.locator('[data-m7-doc-upload]').click();expect(page.locator('[data-docux-upload]')).not_to_be_visible();check('explicit document upload persisted once',count('clinical_documents')==3)
 page.locator('[data-doc-tool="result"]').click();expect(page.locator('[data-m7-result-form]')).to_be_visible();check('only canonical order offered',page.locator('[data-m7-result-order] option').count()==2)
 page.screenshot(path=str(OUT/'register-result-modal-1440x900-webkit.png'))
 page.locator('[data-m7-result-title]').fill('Resultado sin guardar');page.locator('[data-m7-result-file]').set_input_files(file);n=count('clinical_documents');discard_allowed=False;page.keyboard.press('Escape');check('result dirty guard preserves fields',page.locator('[data-m7-result-title]').input_value()=='Resultado sin guardar' and page.locator('[data-docux-result]').is_visible());discard_allowed=True;page.locator('[data-docux-result-cancel]').click();check('result cancel clears local dirty state without writes',count('clinical_documents')==n and page.locator('[data-m7-result-title]').input_value()=='');check('result returns focus',page.locator('[data-doc-tool="result"]').evaluate('n=>document.activeElement===n'));page.locator('[data-doc-tool="result"]').click()
 page.locator('[data-m7-result-order]').select_option(s['orders'][0]['result']['document_uuid']);page.locator('[data-m7-result-title]').fill('Resultado de biometría de prueba');page.locator('[data-m7-result-provenance]').fill('Laboratorio de prueba');page.locator('[data-m7-result-file]').set_input_files(file);n=count('clinical_documents');lost_result=True;page.locator('[data-m7-result-form] button[type="submit"]').click();expect(page.locator('[data-docux-result-state]')).to_have_attribute('data-state','failed');check('lost result response retains modal for safe retry',page.locator('[data-docux-result]').is_visible() and count('clinical_documents')==n+1)
 first_key=writes[-1]['key'];page.locator('[data-m7-result-form] button[type="submit"]').click();expect(page.locator('[data-docux-result]')).not_to_be_visible();check('result registration and retry preserve originating encounter and idempotency',count('clinical_documents')==n+1 and sql("SELECT encounter_ref_id FROM clinical_documents WHERE title='Resultado de biometría de prueba'")=='1016' and writes[-1]['key']==first_key)
 check('current document view compact with patient reader modal',page.locator('[data-m7-encounter-documents] .m7-doc-card').count()<=4 and page.locator('[data-m7-documents] [data-m7-patient-documents]').count()==0)
 page.locator('[data-doc-patient]').click();expect(page.locator('[data-doc-reader]')).to_be_visible();page.locator('[data-doc-reader-close]').click()
 # Secure capture issuance only after explicit action, cancellation reaches canonical terminal state.
 tokens_before=count('clinical_note_capture_tokens') if sql("SHOW TABLES LIKE 'clinical_note_capture_tokens'") else 0
 capture(page);expect(page.locator('[data-docux-qr-content]')).to_be_visible();check('mobile secure capture preserved',count('clinical_note_capture_tokens')==tokens_before+1)
 for _ in range(10):page.keyboard.press('Tab');assert page.locator('[data-docux-capture]').evaluate('n=>n.contains(document.activeElement)')
 for _ in range(8):page.keyboard.press('Shift+Tab');assert page.locator('[data-docux-capture]').evaluate('n=>n.contains(document.activeElement)')
 check('mobile capture modal keyboard focus stays trapped')
 page.locator('[data-m7-capture-cancel]').click();expect(page.locator('[data-m7-capture-state]')).to_have_text('Captura cancelada');check('capture cancel preserves secure token state',sql('SELECT status FROM clinical_note_capture_tokens ORDER BY created_at DESC LIMIT 1')=='cancelled');page.locator('[data-docux-capture-close]').click()
 # The actual mobile bearer upload still uses DOCSEC01's canonical single-use authority.
 with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as issued:capture(page)
 capture_data=issued.value.json()['data'];token=capture_data['token'];anon=pw.request.new_context();n=count('clinical_documents')
 check('capture status and issuance remain physician-authenticated',anon.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+token).status==401 and anon.post(BASE+'/api/clinical/index.php/note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','note_context':'nota_clinica_modal'}).status==401)
 page.evaluate('document.activeElement?.blur()');pending_capture=page.screenshot();capture_views={}
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  page.set_viewport_size({'width':w,'height':h});page.mouse.move(0,0)
  for _ in range(5):page.keyboard.press('Tab');assert page.locator('[data-docux-capture]').evaluate('n=>n.contains(document.activeElement)')
  check(f'mobile capture modal {w}x{h} no horizontal overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1') and page.locator('[data-docux-capture]').evaluate('n=>n.scrollWidth<=n.clientWidth+1'))
  page.evaluate('document.activeElement?.blur()');capture_views[f'{w}x{h}']=page.screenshot()
 page.set_viewport_size({'width':1440,'height':900})
 mobile=browser.new_context(viewport={'width':390,'height':844});phone=mobile.new_page();phone.goto(urljoin(BASE,capture_data['mobile_url']),wait_until='networkidle');check('actual phone page requires no physician login',not mobile.cookies());phone.locator('#captureFile').set_input_files(file)
 with phone.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/upload')) as uploaded:phone.locator('#captureSubmit').click()
 check('anonymous phone upload saves only token-authorized encounter',uploaded.value.status in [200,201] and count('clinical_documents')==n+1 and sql('SELECT encounter_ref_id FROM clinical_documents ORDER BY id DESC LIMIT 1')=='1016');mobile.close()
 reused=anon.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/upload',multipart={'file':file});check('capture token is single-use with no duplicate document',reused.status in [409,410] and count('clinical_documents')==n+1)
 expect(page.locator('[data-m7-capture-state]')).to_have_text('Documento recibido',timeout=15000);expect(page.locator('[data-docux-qr-content]')).to_be_hidden();expect(page.locator('[data-docux-capture-close]')).to_be_enabled();check('received capture uses canonical readback',page.locator('[data-docux-received]').is_visible() and 'Registrado en esta consulta.' in page.locator('[data-docux-received-detail]').inner_text());(OUT/'mobile-capture-modal-terminal-token-webkit.png').write_bytes(pending_capture)
 for size,data in capture_views.items():(OUT/f'capture-modal-{size}-terminal-token-webkit.png').write_bytes(data)
 expect(page.locator('[data-docux-received-preview]')).to_be_visible();check('capture preview uses authorized canonical binary without write',page.locator('[data-docux-received-preview]').evaluate('n=>n.naturalWidth===128&&n.naturalHeight===96&&n.getAttribute("src").includes("/binary/ORIGINAL")') and count('clinical_documents')==n+1)
 check('received-image preview retains physician authentication',anon.get(BASE+page.locator('[data-docux-received-preview]').get_attribute('src')).status==401)
 page.screenshot(path=str(OUT/'mobile-capture-received-webkit.png'));n=count('clinical_documents');page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible();check('Close received capture creates no second document and restores focus',count('clinical_documents')==n and page.locator('[data-m7-capture-start]').evaluate('n=>document.activeElement===n'));check('closed capture clears private preview',page.locator('[data-docux-received-preview]').get_attribute('src') is None);anon.dispose()
 # Expiry retains the existing terminal authority and anonymous rejection.
 with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as issued:capture(page)
 expiring=issued.value.json()['data']['token'];sql("UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='"+expiring+"'");expect(page.locator('[data-m7-capture-state]')).to_have_text('El código expiró.',timeout=15000)
 anon=pw.request.new_context();check('expired token rejects anonymous upload without document',anon.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+expiring+'/upload',multipart={'file':file}).status==410 and count('clinical_documents')==n);anon.dispose();page.locator('[data-docux-capture-close]').click()
 expect(page.locator('[data-doc-all]')).to_be_visible();page.locator('[data-doc-all]').click();expect(page.locator('[data-doc-reader] .m7-doc-card')).to_have_count(count('clinical_documents'));page.locator('[data-doc-reader-close]').click();check('many files stay bounded with full canonical reader modal')
 # Snapshot both operational surfaces at approved desktop and narrow sizes.
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  page.set_viewport_size({'width':w,'height':h})
  for name in ['documents','finalize']:
   step(page,name)
   page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();document.querySelector(".vis04-capture").scrollTop=0;scrollTo(0,0)}')
   check(f'{name} {w}x{h} no horizontal overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   if w>=1200:check(f'{name} {w}x{h} no page scroll',page.evaluate('document.documentElement.scrollHeight<=innerHeight+1'))
   if name=='documents':
    check(f'document actions {w}x{h} available without inline forms',page.locator('[data-doc-tool]:disabled').count()==0 and page.locator('[data-m7-documents] form:visible').count()==0)
    for launch,modal,close in [('[data-docux-attach]','[data-docux-upload]','[data-docux-upload] [data-modal-cancel]'),('[data-doc-tool="result"]','[data-docux-result]','[data-docux-result-cancel]')]:
     page.locator(launch).click();expect(page.locator(modal)).to_be_visible()
     for _ in range(9):page.keyboard.press('Tab');assert page.locator(modal).evaluate('n=>n.contains(document.activeElement)')
     check(f'modal focus trap and overflow {launch} {w}x{h}',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1') and page.locator(modal).evaluate('n=>n.scrollWidth<=n.clientWidth+1'))
     page.screenshot(path=str(OUT/f'{"attach" if "attach" in launch else "result"}-modal-{w}x{h}-webkit.png'));page.locator(close).click();check(f'modal return focus {launch} {w}x{h}',page.locator(launch).evaluate('n=>document.activeElement===n'))
   page.mouse.move(0,0);page.screenshot(path=str(OUT/f'canonical-{name}-{w}x{h}-webkit.png'),full_page=w<1200,animations='disabled')
 # A second order's review uses the resulting canonical appointment ID, not a guessed dependency.
 page.set_viewport_size({'width':1440,'height':900});step(page,'plan');order(page,'Estudio con revisión en próxima cita','appointment');step(page,'finalize');appointments_before=count('agenda_appointments');confirm(page)
 linked=[x['body'] for x in writes if x['path'].endswith('/longitudinal/tasks')][-1]
 check('next-appointment review uses actual appointment result without reexecution',linked['appointment_id']==state(page)['appointment']['result']['appointment_id'] and linked['due_at'] is None and count('agenda_appointments')==appointments_before and count('clinical_patient_tasks')==2)
 old_writes=len(writes);page.locator('[data-m7-finalize]').click();page.wait_for_function('document.querySelector("[data-m7-body]").dataset.encounterState==="closed"')
 check('explicit terminal confirmation closes canonical encounter without duplicate actions',sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')=='closed' and len(writes)==old_writes+1 and writes[-1]['path'].endswith('/finalize') and any(t.startswith('Finalizar cerrará') for t in dialog_text))
 check('void and amendment authorities retained; void is visually secondary',page.locator('[data-m7-void-form]').count()==1 and page.locator('details.flow-exception [data-m7-void-form]').count()==1 and page.locator('[data-m7-amendment-form]').is_visible())
 # Late results remain attached to their original closed encounter.
 step(page,'documents');page.locator('[data-doc-tool="result"]').click();page.locator('[data-m7-result-order]').select_option(s['orders'][0]['result']['document_uuid']);page.locator('[data-m7-result-title]').fill('Resultado tardío de prueba');page.locator('[data-m7-result-provenance]').fill('Laboratorio de prueba');page.locator('[data-m7-result-file]').set_input_files(file);page.locator('[data-m7-result-form] button[type="submit"]').click();expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
 check('late result preserves originating CLOSED encounter',sql("SELECT encounter_ref_id FROM clinical_documents WHERE title='Resultado tardío de prueba'")=='1016' and sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')=='closed')
 # A separate disposable fixture checks the demoted void form still reaches the
 # same explicit reason/confirmation authority. It never touches Director records.
 sql("INSERT INTO clinical_encounters(encounter_id,doctor_id,patient_id,encounter_dt,status,opened_by_user_id) VALUES(1017,'1','p_plan02ux_review',UTC_TIMESTAMP(),'open','review-user')")
 page.reload(wait_until='commit');expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true',timeout=55000);page.wait_for_function('document.querySelector("[data-m7-body]").dataset.encounterId==="1017"');step(page,'finalize')
 page.locator('details.flow-exception summary').click();page.locator('[data-m7-void-reason]').fill('Anulación explícita de prueba');page.locator('[data-m7-void-form] button[type="submit"]').click();page.wait_for_function('document.querySelector("[data-m7-body]").dataset.encounterState==="voided"')
 check('secondary void form preserves explicit canonical reason and confirmation',sql("SELECT CONCAT(status,'|',void_reason) FROM clinical_encounters WHERE encounter_id=1017")=='voided|Anulación explícita de prueba' and any(t.startswith('Anular conservará') for t in dialog_text))
 shutting_down=True
 for _ in range(200):
  page.wait_for_timeout(50)
  if active_routes==0:break
 check('all routed canonical requests completed before teardown',active_routes==0)
 ctx.close();browser.close();api.dispose()
check('no JavaScript errors or unexpected Director writes',not errors and not unexpected and director()==before)
(OUT/'canonical-browser-report.json').write_text(json.dumps({'qa':'PASS','checks':checks,'writes':writes,'javascript_errors':errors,'director_records_unchanged':True,'source_sha256':{f:hashlib.sha256(Path(f).read_bytes()).hexdigest() for f in ['index.html','assets/js/clinical/plan02b-next-steps.js','assets/js/clinical/m7-ws04.js','assets/js/clinical/m7-ws05.js','assets/js/clinical/vis04-consultation.js','assets/css/expediente-paciente-visual-normalization.css']}},indent=2,ensure_ascii=False))
print('CONSULTATION_FLOW_R1_CANONICAL_QA=PASS',flush=True)
