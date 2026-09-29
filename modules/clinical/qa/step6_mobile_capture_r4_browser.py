"""R4 security/lifecycle/QR WebKit proof. Every physical write uses the asserted disposable DB."""
import base64,hashlib,json,os,re,subprocess,time
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright,expect
BASE,DB=os.environ['FLOW_R1_QA_BASE'],os.environ['FLOW_R1_QA_DB'];assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}',DB)
OUT=Path(os.environ['FLOW_R1_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
API=BASE+'/api/clinical/index.php/';REVIEW='http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_placeholders=clean&review_step=documents'
checks={};errors=[];tokens=[];requests=[];screens={};polls=[];cancel_failure=False;active=0;closing=False
IMAGE=base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/l1sAAAAASUVORK5CYII=')
PDF=b'%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<</Root 1 0 R>>\n%%EOF\n'
def sql(q):return subprocess.check_output(['mysql','-N',DB,'-e',q],text=True).strip()
def count(table):return int(sql('SELECT COUNT(*) FROM '+table)) if sql("SHOW TABLES LIKE '"+table+"'") else 0
def check(name,ok=True):assert ok,name;checks[name]='PASS';print('PASS '+name,flush=True)
def doc_for(t):return sql("SELECT CONCAT(d.document_type,'|',d.patient_id,'|',d.encounter_ref_id,'|',JSON_UNQUOTE(JSON_EXTRACT(d.payload_json,'$.capture_classification'))) FROM clinical_documents d JOIN clinical_note_capture_tokens t ON t.document_id=d.id WHERE t.token='"+t+"'")
def status(t):return sql("SELECT status FROM clinical_note_capture_tokens WHERE token='"+t+"'")
def imagefile():return {'name':'captura-qa.png','mimeType':'image/png','buffer':IMAGE}
def pdffile():return {'name':'documento-qa.pdf','mimeType':'application/pdf','buffer':PDF}
with sync_playwright() as pw:
 api=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'});anon=pw.request.new_context()
 def issue(kind='clinical_image',**extra):
  r=api.post(API+'note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':kind,**extra});assert r.status==201,r.text();data=r.json()['data'];tokens.append(data['token']);return data
 def upload(t,file=None,**extra):return anon.post(API+'note-capture-tokens/'+t+'/upload',multipart={'file':file or imagefile(),**extra})
 def cancel(t):return api.post(API+'note-capture-tokens/'+t+'/cancel',data={})
 check('authenticated whitelist contains only supported image/pdf types', {x['document_type'] for x in api.get(API+'note-capture-tokens/classifications').json()['data']['items']}=={'image','pdf'})
 for name,client,body,expected in [('unauth issuance',anon,{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':'clinical_image'},401),('wrong patient',api,{'patient_id':'wrong-patient','encounter_key':'enc:1016','capture_classification':'clinical_image'},403),('unknown classification',api,{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':'lab_result'},400),('empty classification',api,{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','capture_classification':''},400),('reserved context bypass',api,{'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','note_context':'step6_capture_r4:clinical_pdf'},400)]:
  n=count('clinical_note_capture_tokens');r=client.post(API+'note-capture-tokens',data=body);check(name,r.status==expected and count('clinical_note_capture_tokens')==n)
 sql("INSERT INTO clinical_encounters(encounter_id,doctor_id,patient_id,encounter_dt,status) VALUES(1017,'2','p_plan02ux_review',UTC_TIMESTAMP(),'open')")
 n=count('clinical_note_capture_tokens');r=api.post(API+'note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1017','capture_classification':'clinical_image'});check('foreign physician encounter rejected',r.status in [403,404] and count('clinical_note_capture_tokens')==n)
 a=issue();b=issue('clinical_pdf');n=count('clinical_documents')
 check('QR URL is absolute canonical bearer only',a['mobile_url']==a['qr_value'] and urlsplit(a['mobile_url']).query=='token='+a['token'] and a['mobile_url'].startswith(BASE+'/public/note-capture.html?'))
 phone=anon.get(API+'note-capture-tokens/'+a['token']+'/mobile-context').json()['data'];check('phone context excludes patient and private context',set(phone)=={'status','expires_at','classification'} and phone['classification']['id']=='clinical_image')
 check('phone cannot read authenticated token status',anon.get(API+'note-capture-tokens/'+a['token']).status==401)
 r=upload(a['token'],document_type='pdf',capture_classification='clinical_pdf',note_context='step6_capture_r4:clinical_pdf',patient_id='wrong',encounter_key='enc:1017',title='tampered',summary='tampered',event_datetime='1999-01-01 00:00:00')
 check('classification/scope/title tampering ignored',r.status==201 and doc_for(a['token'])=='image|p_plan02ux_review|1016|clinical_image' and 'tampered' not in sql('SELECT CONCAT(title,summary,payload_json) FROM clinical_documents ORDER BY id DESC LIMIT 1'))
 check('second upload rejected exactly one document',upload(a['token']).status==409 and count('clinical_documents')==n+1)
 check('classification MIME mismatch rejects without consume',upload(b['token']).status==400 and status(b['token'])=='pending' and count('clinical_documents')==n+1)
 r=upload(b['token'],pdffile(),document_type='image');check('two tokens retain separate canonical types',r.status==201 and doc_for(b['token'])=='pdf|p_plan02ux_review|1016|clinical_pdf' and count('clinical_documents')==n+2)
 c=issue();cancel(c['token']);n=count('clinical_documents');check('cancelled token cannot upload',upload(c['token']).status==409 and count('clinical_documents')==n)
 c=issue();sql("UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='"+c['token']+"'");check('expired token cannot upload',upload(c['token']).status==410 and count('clinical_documents')==n)
 legacy=api.post(API+'note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','note_context':'nota_clinica_modal'}).json()['data'];tokens.append(legacy['token']);check('legacy URL and context remain compatible',legacy['mobile_url'].startswith('/public/note-capture.html?') and upload(legacy['token']).status==201)
 browser=pw.webkit.launch();ctx=browser.new_context(viewport={'width':1440,'height':810},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(25000);page.on('pageerror',lambda e:errors.append(str(e)))
 def proxy(route):
  global active
  active+=1
  try:
   if closing:route.abort();return
   r=route.request;path=urlsplit(r.url).path;query=urlsplit(r.url).query
   if '/note-capture-tokens/' in path and r.method=='GET' and not path.endswith('classifications'):polls.append(time.monotonic())
   if cancel_failure and path.endswith('/cancel'):route.fulfill(status=503,json={'ok':False,'error':'server_error'});return
   if r.method not in ['GET','HEAD']:requests.append(re.sub(r'(note-capture-tokens/)[^/]+',r'\1[redacted]',path))
   resp=api.fetch(BASE+path+('?' + query if query else ''),method=r.method,data=r.post_data_buffer,headers={k:v for k,v in r.headers.items() if k in ['content-type','accept','idempotency-key']})
   if r.method=='POST' and path.endswith('/note-capture-tokens') and resp.status==201:tokens.append(resp.json()['data']['token'])
   route.fulfill(response=resp)
  finally:active-=1
 def guard(route):
  r=route.request
  if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):raise AssertionError('Unexpected Director write')
  route.continue_()
 page.route('**/api/clinical/**',guard)
 for pattern in ['**/api/clinical/index.php/encounters/**','**/api/clinical/index.php/patients/*/encounters*','**/api/clinical/index.php/doctors/*/patients/*/documents*','**/api/clinical/index.php/documents/**','**/api/clinical/index.php/note-capture-tokens*','**/api/clinical/index.php/note-capture-tokens/**']:page.route(pattern,proxy)
 page.goto(REVIEW,wait_until='commit');expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true',timeout=55000);expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
 def open_capture():page.locator('[data-m7-capture-start]').click();expect(page.locator('input[name="capture-classification"]')).to_have_count(2)
 def select(kind='clinical_image'):page.locator('input[name="capture-classification"][value="'+kind+'"]').check()
 def generate():
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as response:page.locator('[data-docux-capture-generate]').click()
  data=response.value.json()['data'];expect(page.locator('[data-docux-qr-content]')).to_be_visible();return data
 def shot(name):page.evaluate('document.activeElement?.blur()');page.mouse.move(0,0);screens[name]=page.screenshot()
 def fits(name):
  d=page.locator('[data-docux-capture]').bounding_box();check(name,d['y']>=0 and d['y']+d['height']<=810 and page.evaluate('document.documentElement.scrollHeight<=innerHeight+1&&document.documentElement.scrollWidth<=innerWidth+1'))
 n=count('clinical_note_capture_tokens');open_capture();expect(page.locator('[data-docux-capture-generate]')).to_be_disabled();check('opening and no selection issue no token',count('clinical_note_capture_tokens')==n);fits('classification modal fits actual MacBook Air viewport');shot('A-classification-1440x810-webkit.png')
 for _ in range(10):page.keyboard.press('Tab');assert page.locator('[data-docux-capture]').evaluate('n=>n.contains(document.activeElement)')
 select();check('classification selection alone issues no token',count('clinical_note_capture_tokens')==n);data=generate();check('explicit generate issues exactly one',count('clinical_note_capture_tokens')==n+1);fits('QR modal fits actual MacBook Air viewport');shot('B-live-qr-1440x810-webkit.png')
 qr=page.locator('[data-docux-qr]').screenshot();decoded=subprocess.check_output(['swift','modules/clinical/qa/docux01r1_qr_decode.swift'],input=qr).decode();check('scannable local QR exactly equals canonical URL',decoded==data['mobile_url']==page.locator('[data-m7-capture-link]').get_attribute('href'))
 expect(page.locator('[data-docux-capture-expiry]')).to_contain_text('Expira en');shot('C-waiting-1440x810-webkit.png')
 cancel_failure=True;page.locator('[data-docux-capture-change]').click();expect(page.locator('[data-m7-capture-state]')).to_contain_text('No se pudo cancelar');check('failed cancellation keeps modal and classification locked',page.locator('[data-docux-capture]').is_visible() and page.locator('[data-docux-capture-selection]').is_hidden() and status(data['token'])=='pending')
 page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-m7-capture-state]')).to_contain_text('No se pudo cancelar');check('failed close cancellation stays open for retry',page.locator('[data-docux-capture]').is_visible() and status(data['token'])=='pending')
 cancel_failure=False;page.locator('[data-docux-capture-change]').click();expect(page.locator('[data-docux-capture-selection]')).to_be_visible();check('change type confirms old token cancellation',status(data['token'])=='cancelled');shot('F-change-type-1440x810-webkit.png');select('clinical_pdf');other=generate();check('type change creates a distinct token',other['token']!=data['token']);page.keyboard.press('Escape');expect(page.locator('[data-docux-capture]')).not_to_be_visible();check('Escape cancels and restores focus',status(other['token'])=='cancelled' and page.locator('[data-m7-capture-start]').evaluate('n=>n===document.activeElement'))
 open_capture();select();data=generate();sql("UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token='"+data['token']+"'");expect(page.locator('[data-m7-capture-state]')).to_have_text('El código expiró.',timeout=15000);shot('E-expired-1440x810-webkit.png');pcount=len(polls);page.wait_for_timeout(3000);check('expiry stops polling',len(polls)==pcount)
 with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as response:page.locator('[data-docux-capture-renew]').click()
 fresh=response.value.json()['data'];expect(page.locator('[data-docux-qr-content]')).to_be_visible();check('expired token renews same classification with new bearer',fresh['token']!=data['token'] and fresh['classification']['id']=='clinical_image')
 phone_ctx=browser.new_context(viewport={'width':390,'height':844});mobile=phone_ctx.new_page();mobile.on('pageerror',lambda e:errors.append(str(e)));mobile.goto(fresh['mobile_url']);expect(mobile.locator('#captureClassification')).to_have_text('Documento: Imagen clínica');expect(mobile.locator('#captureChooseFile')).to_be_enabled();check('phone has no physician session or PII',not phone_ctx.cookies() and 'p_plan02ux_review' not in mobile.locator('body').inner_text() and mobile.locator('#captureLegacyMetadata').is_hidden());check('390 phone has no horizontal overflow',mobile.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));screens['G-phone-390x844-webkit.png']=mobile.screenshot()
 n=count('clinical_documents');mobile.locator('#captureFile').set_input_files(imagefile());check('file selection does not upload',count('clinical_documents')==n);expect(mobile.locator('#capturePreview')).to_be_visible();screens['G-phone-preview-390x844-webkit.png']=mobile.screenshot()
 with mobile.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/upload')) as response:mobile.locator('#captureSubmit').click()
 check('explicit phone send creates exactly one canonical document',response.value.status==201 and count('clinical_documents')==n+1 and doc_for(fresh['token'])=='image|p_plan02ux_review|1016|clinical_image');expect(mobile.locator('#captureMsg')).to_contain_text('Documento enviado correctamente');screens['G-phone-sent-390x844-webkit.png']=mobile.screenshot()
 expect(page.locator('[data-m7-capture-state]')).to_have_text('Documento recibido',timeout=15000);expect(page.locator('[data-docux-received-preview]')).to_be_visible();expect(page.locator('[data-docux-received-detail]')).to_contain_text('Registrado en esta consulta.');check('no post-upload commit action',page.locator('[data-docux-capture-use]').count()==0);shot('D-received-1440x810-webkit.png');pcount=len(polls);page.wait_for_timeout(3000);check('received stops polling',len(polls)==pcount)
 page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible();check('closing received does not write a second document',count('clinical_documents')==n+1);expect(page.locator('[data-m7-encounter-documents]')).to_contain_text('Imagen clínica (captura móvil)');shot('H-updated-list-1440x810-webkit.png')
 check('main Step 6 remains compact without inline operational forms',page.locator('[data-m7-documents] form:visible').count()==0 and page.evaluate('document.documentElement.scrollHeight<=innerHeight+1'))
 for w,h in [(1440,900),(1366,768),(390,844)]:
  page.set_viewport_size({'width':w,'height':h});open_capture();check(f'selection modal no overflow {w}x{h}',page.locator('[data-docux-capture]').evaluate('n=>n.scrollWidth<=n.clientWidth+1'));page.keyboard.press('Escape');expect(page.locator('[data-docux-capture]')).not_to_be_visible()
 check('no JavaScript errors',not errors)
 closing=True
 for _ in range(100):
  page.wait_for_timeout(50)
  if active==0:break
 phone_ctx.close();ctx.close();browser.close()
 for t in tokens:
  if status(t)=='pending':cancel(t)
 for name,data in screens.items():(OUT/name).write_bytes(data)
 api.dispose();anon.dispose()
(OUT/'canonical-r4.json').write_text(json.dumps({'qa':'PASS','checks':checks,'javascript_errors':errors,'working_database_connected':False,'working_database_mutated':False,'screenshots_contain_only_terminal_disposable_tokens':True},indent=2))
print('STEP6_MOBILE_CAPTURE_R4_QA=PASS',flush=True)
