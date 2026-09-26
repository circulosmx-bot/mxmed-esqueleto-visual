"""DOCUX01-R1 real browser/document QA, synthetic Director only.
Set DOCUX01R1_ARTIFACTS and DOCUX01R1_QR_DECODER (stdin PNG -> stdout QR value).
No bearer values in reports; QR screenshots are saved only after terminal state.
"""
import base64, json, os, subprocess, time, uuid
from pathlib import Path
from playwright.sync_api import sync_playwright, expect
import pymysql

OUT=Path(os.environ['DOCUX01R1_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
BASE='http://127.0.0.1:18143'; API=BASE+'/api/clinical/index.php/'
DB='mxmed_director_review_lon07c';PATIENT='p_plan02_review'
runtime=subprocess.check_output(['ps','eww','-p','4024'],text=True)
assert 'MXMED_DB_NAME='+DB in runtime and 'MXMED_DB_HOST=localhost' in runtime
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=True)
REPORT={};TOKENS=[];GENERAL=None;PAGE=None

def sql(q,args=()):
 with conn.cursor() as c:c.execute(q,args);return c.fetchall()
def docs():return sql('SELECT COUNT(*) FROM clinical_documents WHERE patient_id=%s',(PATIENT,))[0][0]
def token_state(token):return sql('SELECT status FROM clinical_note_capture_tokens WHERE token=%s',(token,))[0][0]
def check(name,ok=True):
 assert ok,name
 REPORT[name]='PASS';print('PASS '+name,flush=True)
def ready(page,eid='1015'):
 page.wait_for_function('(id)=>document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId===id',arg=eid,timeout=55000)
 page.locator('[data-m7-section="documents"]').click();expect(page.locator('[data-docux-attach]')).to_be_visible()
def badge(page,n):
 if n:expect(page.locator('[data-plan02b-count]')).to_have_text(str(n))
 else:expect(page.locator('[data-plan02b-count]')).not_to_be_visible()
def select(page,file,drop=False):
 if drop:
  dt=page.evaluate_handle('f=>{const d=new DataTransfer();d.items.add(new File([Uint8Array.from(atob(f.data),c=>c.charCodeAt(0))],f.name,{type:f.mimeType}));return d;}',{'name':file['name'],'mimeType':file['mimeType'],'data':base64.b64encode(file['buffer']).decode()})
  page.locator('[data-docux-dropzone]').dispatch_event('dragover',{'dataTransfer':dt});expect(page.locator('[data-docux-file-state]')).to_have_text('Suelta el archivo para adjuntarlo')
  page.locator('[data-docux-dropzone]').dispatch_event('drop',{'dataTransfer':dt});dt.dispose()
 else:
  with page.expect_file_chooser() as chooser:page.locator('[data-docux-pick]').click()
  chooser.value.set_files(file)
def start_capture(page):
 with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as res:page.locator('[data-m7-capture-start]').click()
 r=res.value;assert r.status==201,'authorized UI issuance'
 d=r.json()['data'];t=d['token'];TOKENS.append(t)
 expect(page.locator('[data-docux-qr-content]')).to_be_visible()
 return t,BASE+d['mobile_url']
def mobile_upload(client,t,file):
 return client.post(API+'note-capture-tokens/'+t+'/upload',multipart={'file':file})
def terminal_close(page):page.locator('[data-docux-capture-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible()
def keyboard(page,dialog):
 for _ in range(12):
  page.keyboard.press('Tab');assert page.evaluate('sel=>!!document.activeElement.closest(sel)',dialog)

def run(browser,p):
 global GENERAL,PAGE
 ctx=browser.new_context(viewport={'width':1440,'height':900},timezone_id='America/Mexico_City');page=ctx.new_page();PAGE=page;errors=[]
 page.on('pageerror',lambda e:errors.append(type(e).__name__))
 polls=[]
 def observe(r):
  if r.request.method=='GET' and '/note-capture-tokens/' in r.url:polls.append(time.monotonic())
 page.on('response',observe)
 before_tokens=sql('SELECT COUNT(*) FROM clinical_note_capture_tokens')[0][0]
 page.goto(BASE+'/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide',wait_until='commit');ready(page)
 check('render Step 6 does not issue',sql('SELECT COUNT(*) FROM clinical_note_capture_tokens')[0][0]==before_tokens)
 imgs=page.evaluate('''()=>{const c=document.createElement('canvas');c.width=128;c.height=96;const g=c.getContext('2d');g.fillStyle='#d7eef4';g.fillRect(0,0,128,96);g.fillStyle='#173e50';g.fillRect(16,16,32,32);g.font='12px sans-serif';g.fillText('DOCUX01 QA',12,76);return Object.fromEntries(['jpeg','png','webp'].map(t=>[t,c.toDataURL('image/'+t).split(',')[1]]));}''')
 files={ext:{'name':'DOCUX01 synthetic.'+ext,'mimeType':'image/'+ext,'buffer':base64.b64decode(data)} for ext,data in imgs.items()}
 files['pdf']={'name':'DOCUX01 synthetic.pdf','mimeType':'application/pdf','buffer':b'%PDF-1.4\n% synthetic document\n%%EOF\n'}
 # Genuine Plan preparations. Fail two requests before persistence, then retry unchanged.
 page.locator('[data-m7-section="plan"]').click()
 for i in range(4):
  page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('DOCUX01 Plan order '+str(i));page.locator('[data-modal-add]').click()
 page.locator('[data-m7-section="documents"]').click();badge(page,4)
 failed=[]
 def fail_two(route):
  if route.request.method=='POST' and route.request.headers.get('content-type','').startswith('application/json'):
   body=route.request.post_data_json
   if body.get('title') in ['DOCUX01 Plan order 2','DOCUX01 Plan order 3']:
    failed.append(body['title']);route.abort('failed');return
  route.continue_()
 page.route('**/encounters/*/documents',fail_two)
 page.locator('[data-ns="confirm"]').click();page.wait_for_function('!window.mxmedPlanNextSteps.isBusy()');badge(page,2);expect(page.locator('[data-prepared]')).to_have_count(2)
 page.unroute('**/encounters/*/documents',fail_two);check('Plan 4 to 2 successful handoff',len(failed)==2)
 # Every picker/drop action is memory-only until explicit save.
 n=docs();page.locator('[data-docux-attach]').click();select(page,files['jpeg']);page.locator('[data-m7-doc-title]').fill('Discarded synthetic');assert docs()==n
 page.locator('[data-docux-upload] [data-modal-cancel]').click();check('picker then cancel zero writes',docs()==n)
 assert page.locator('[data-docux-attach]').evaluate('(e)=>document.activeElement===e')
 for ext,drop in [('jpeg',False),('pdf',True),('png',True),('webp',True)]:
  n=docs();page.locator('[data-docux-attach]').click();select(page,files[ext],drop);assert docs()==n
  title='DOCUX01 '+ext+' '+uuid.uuid4().hex[:6];page.locator('[data-m7-doc-title]').fill(title)
  with page.expect_response(lambda r:r.request.method=='POST' and '/encounters/' in r.url and r.url.endswith('/documents')) as res:
   page.locator('[data-m7-doc-upload]').evaluate('(b)=>{b.click();b.click();}')
  assert res.value.status in [200,201], 'canonical desktop upload'
  expect(page.locator('[data-docux-upload]')).not_to_be_visible();expect(page.locator('[data-m7-encounter-documents]')).to_contain_text(title)
  check(ext+' upload once',docs()==n+1 and page.locator('[data-m7-encounter-documents] .m7-doc-card').filter(has=page.get_by_text(title,exact=True)).count()==1)
  badge(page,2)
 # Unsupported and server failure never pretend success.
 n=docs();page.locator('[data-docux-attach]').click();select(page,{'name':'invalid.txt','mimeType':'text/plain','buffer':b'synthetic'},True)
 expect(page.locator('[data-docux-upload-state]')).to_contain_text('Formato no compatible');assert docs()==n
 page.locator('[data-m7-doc-title]').fill('Failure proof');select(page,files['png'])
 def fail_upload(route):route.fulfill(status=500,content_type='application/json',body='{"ok":false,"message":"RAW BACKEND MUST NOT DISPLAY"}')
 page.route('**/encounters/*/documents',fail_upload);page.locator('[data-m7-doc-upload]').click();expect(page.locator('[data-docux-upload-state]')).to_have_text('No se pudo guardar el documento.')
 check('unsupported and failure safe',docs()==n and 'RAW BACKEND' not in page.locator('[data-docux-upload]').inner_text())
 page.unroute('**/encounters/*/documents',fail_upload);page.locator('[data-docux-upload] [data-modal-cancel]').click()
 # QR decode compares bytes in memory. Independent mobile context has no physician cookie.
 t,url=start_capture(page);qr=page.locator('[data-docux-qr]').screenshot()
 decoded=subprocess.run([os.environ['DOCUX01R1_QR_DECODER']],input=qr,capture_output=True)
 check('QR decoded exact canonical URL',decoded.returncode==0 and decoded.stdout.decode()==url and page.locator('[data-m7-capture-link]').get_attribute('href')==url)
 before_polls=len(polls);page.wait_for_timeout(5600);check('poll interval 2500ms',len(polls)-before_polls>=2 and all(b-a>=2.3 for a,b in zip(polls[before_polls:],polls[before_polls+1:])))
 n=docs();mobile=browser.new_context(viewport={'width':390,'height':844});phone=mobile.new_page();phone.goto(url,wait_until='networkidle');assert not mobile.cookies()
 phone.locator('#captureFile').set_input_files(files['png'])
 with phone.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/upload')) as response:phone.locator('#captureSubmit').click()
 assert response.value.status==201
 expect(page.locator('[data-m7-capture-state]')).to_have_text('✓ Documento recibido',timeout=15000)
 doc=sql('SELECT d.id,d.patient_id,d.encounter_ref_id FROM clinical_documents d JOIN clinical_note_capture_tokens t ON t.document_id=d.id WHERE t.token=%s',(t,))[0]
 check('QR mobile canonical one document',docs()==n+1 and doc[1:]==(PATIENT,1015))
 data=ctx.request.get(API+'doctors/1/patients/'+PATIENT+'/documents?limit=200').json()['data']['items'];assert sum(int(d['id'])==doc[0] for d in data)==1
 assert mobile_upload(mobile.request,t,files['png']).status==409
 assert ctx.request.post(API+'note-capture-tokens/'+t+'/cancel',data={}).status==409
 check('QR second upload and late cancel rejected',docs()==n+1)
 (OUT/'qr-terminal-only.png').write_bytes(qr) # token is already uploaded, never active in stored evidence
 pcount=len(polls);page.wait_for_timeout(2900);check('uploaded stops polling',len(polls)==pcount);badge(page,2);terminal_close(page);mobile.close()
 # Closing while issuance is in flight waits for its real response and cancels it.
 held=[]
 def hold_issuance(route):
  response=route.fetch();held.append((route,response));TOKENS.append(response.json()['data']['token'])
 page.route('**/note-capture-tokens',hold_issuance);page.locator('[data-m7-capture-start]').click()
 for _ in range(100):
  if held:break
  page.wait_for_timeout(50)
 assert held,'issuance response captured'
 page.locator('[data-docux-capture] [data-modal-close]').click();held[0][0].fulfill(response=held[0][1])
 expect(page.locator('[data-docux-capture]')).not_to_be_visible();check('close during issuance cancels late token',token_state(TOKENS[-1])=='cancelled');page.unroute('**/note-capture-tokens',hold_issuance)
 # Cancel and expiration use the same canonical token, no replacement issuance.
 for kind in ['cancel','expire','close','escape']:
  n=docs();t,_=start_capture(page)
  if kind=='expire':
   sql('UPDATE clinical_note_capture_tokens SET expires_at=DATE_SUB(UTC_TIMESTAMP(),INTERVAL 1 SECOND) WHERE token=%s',(t,));expect(page.locator('[data-m7-capture-state]')).to_have_text('El enlace de captura venció.',timeout=15000);terminal_close(page)
  elif kind=='cancel':
   page.locator('[data-m7-capture-cancel]').click();expect(page.locator('[data-m7-capture-state]')).to_have_text('Captura cancelada');terminal_close(page)
  elif kind=='escape':page.keyboard.press('Escape');expect(page.locator('[data-docux-capture]')).not_to_be_visible()
  else:page.locator('[data-docux-capture] [data-modal-close]').click();expect(page.locator('[data-docux-capture]')).not_to_be_visible()
  check(kind+' terminates session',token_state(t)==('expired' if kind=='expire' else 'cancelled'))
  anon=p.request.new_context();assert mobile_upload(anon,t,files['png']).status==(410 if kind=='expire' else 409);anon.dispose();assert docs()==n
  pc=len(polls);page.wait_for_timeout(2800);check(kind+' stops polling',len(polls)==pc)
 # Context loss must close/cancel, not render an old capture into a new encounter.
 t,_=start_capture(page);page.evaluate('document.querySelector("#m7-workspace [data-m7-body]").dataset.encounterKey="enc:1016"')
 expect(page.locator('[data-docux-capture]')).not_to_be_visible();page.wait_for_timeout(800);check('context change cancels old session',token_state(t)=='cancelled')
 page.evaluate('document.querySelector("#m7-workspace [data-m7-body]").dataset.encounterKey="enc:1015"')
 # General documents: real fixture row read through canonical patient document reader.
 GENERAL=str(uuid.uuid4());sql("INSERT INTO clinical_documents (document_uuid,document_type,title,patient_id,payload_json,status,event_datetime,created_at,created_by_user_id) VALUES (%s,'pdf','DOCUX01 General synthetic',%s,'{}','signed',UTC_TIMESTAMP(),UTC_TIMESTAMP(),'docux01-qa')",(GENERAL,PATIENT))
 page.locator('[data-m7-doc-refresh]').click();expect(page.locator('[data-docux-general]')).to_be_visible();expect(page.locator('[data-docux-general] h5')).to_have_text('Documentos generales del paciente')
 check('general canonical section separate',page.locator('[data-m7-patient-documents]').get_by_text('DOCUX01 General synthetic',exact=True).count()==1 and page.locator('[data-m7-encounter-documents]').get_by_text('DOCUX01 General synthetic',exact=True).count()==0)
 sql('DELETE FROM clinical_documents WHERE document_uuid=%s',(GENERAL,));GENERAL=None
 page.locator('[data-m7-doc-refresh]').click();expect(page.locator('[data-docux-general]')).not_to_be_visible();check('empty general section hidden')
 page.locator('[data-ns="confirm"]').click();page.wait_for_function('!window.mxmedPlanNextSteps.isBusy()');badge(page,0);expect(page.locator('[data-prepared]')).to_have_count(0);check('Plan 2 to 0 without duplicate success handoff')
 t,_=start_capture(page)
 page.evaluate("void window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false})")
 expect(page.locator('[data-docux-capture]')).not_to_be_visible()
 for _ in range(100):
  if token_state(t)!='pending':break
  page.wait_for_timeout(50)
 check('actual patient change cancels capture',token_state(t)=='cancelled')
 check('no page errors',not errors);ctx.close()
 # Clean UX context remains empty, used for representative responsive evidence only.
 ctx=browser.new_context(viewport={'width':1440,'height':900});page=ctx.new_page();PAGE=page
 page.goto(BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide',wait_until='commit');ready(page,'1016')
 expect(page.locator('[data-m7-encounter-documents]')).to_have_text('Aún no hay documentos registrados en esta consulta.')
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  page.set_viewport_size({'width':w,'height':h});page.locator('[data-docux-attach]').scroll_into_view_if_needed();page.screenshot(path=str(OUT/f'step6-{w}.png'))
  page.locator('[data-docux-attach]').click();keyboard(page,'[data-docux-upload]')
  assert page.locator('[data-docux-upload]').evaluate('(d)=>d.scrollWidth<=d.clientWidth && d.getBoundingClientRect().width<=innerWidth')
  page.screenshot(path=str(OUT/f'upload-{w}.png'));page.keyboard.press('Escape');expect(page.locator('[data-docux-upload]')).not_to_be_visible()
  t,_=start_capture(page);keyboard(page,'[data-docux-capture]');assert page.locator('[data-docux-capture]').evaluate('(d)=>d.scrollWidth<=d.clientWidth && d.getBoundingClientRect().width<=innerWidth')
  shot=page.screenshot();page.locator('[data-m7-capture-cancel]').click();expect(page.locator('[data-m7-capture-state]')).to_have_text('Captura cancelada');assert token_state(t)=='cancelled';(OUT/f'qr-cancelled-token-{w}.png').write_bytes(shot);terminal_close(page)
  check('responsive '+str(w))
 check('clean UX no documents',sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_plan02ux_review'")[0][0]==0)
 assert sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')[0][0]=='open';ctx.close()

try:
 with sync_playwright() as p:
  b=p.chromium.launch(headless=True)
  try:run(b,p)
  finally:
   # Pending bearer cleanup before evidence, even on a test failure.
   client=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=director-lon07c-review'})
   for t in TOKENS:client.post(API+'note-capture-tokens/'+t+'/cancel',data={})
   client.dispose();b.close()
finally:
 if GENERAL:sql('DELETE FROM clinical_documents WHERE document_uuid=%s',(GENERAL,))
 log=Path('/Users/circulodigital/.codex/artifacts/director-expediente-review/launch-error.log');text=log.read_text()
 for t in TOKENS:text=text.replace(t,'[DOCUX01_REDACTED]'.ljust(len(t),'_'))
 log.write_text(text);conn.close();(OUT/'browser-results.json').write_text(json.dumps(REPORT,indent=2)+'\n')
