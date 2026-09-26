"""VIS32 failure/in-flight and read-only header checks. Synthetic Director only."""
import json,os,subprocess,uuid
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
BASE='http://127.0.0.1:18143';OUT=Path(os.environ['VIS32_ARTIFACTS']);TOKENS=[];TASK=None;REPORT={}
assert 'MXMED_DB_NAME=mxmed_director_review_lon07c' in subprocess.check_output(['ps','eww','-p','4024'],text=True)
with sync_playwright() as p:
 b=p.chromium.launch();ctx=b.new_context(viewport={'width':1366,'height':768});page=ctx.new_page();errors=[];page.on('pageerror',lambda e:errors.append(e.stack))
 client=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=director-lon07c-review'})
 def mode():return page.evaluate("document.body.classList.contains('mx-consultation-mode')")
 def check(k,v=True):assert v,k;REPORT[k]='PASS';print('PASS '+k,flush=True)
 def ready(patient='plan02ux',eid='1016'):
  page.goto(BASE+'/index.html?review_patient='+patient+'&review_encounter=open&qa_tools=hide',wait_until='commit');expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id',eid,timeout=55000);page.wait_for_function("document.body.classList.contains('mx-consultation-mode')")
 def exit_programmatically():page.evaluate("document.querySelector('[data-m7-exit]').click()")
 def resume(eid='1016'):page.locator('[data-vis02-action="consulta"]').click();expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id',eid);page.wait_for_function("document.body.classList.contains('mx-consultation-mode')")
 endpoint=BASE+'/api/clinical/index.php/patients/p_plan02ux_review/longitudinal/tasks'
 try:
  ready();r=client.post(endpoint,data={'task_type':'CLINICAL_ACTION','title':'VIS32 pendiente sintético de revisión'},headers={'Idempotency-Key':str(uuid.uuid4())});assert r.ok;TASK=r.json()['data']['item']['task_id']
  page.evaluate("window.dispatchEvent(new Event('lon06b:changed'))");expect(page.locator('[data-vis32-pending-context]')).to_have_text('1 pendiente clínico');expect(page.locator('.vis31-pending-link')).not_to_be_visible()
  page.evaluate('scrollTo(0,0)');page.screenshot(path=str(OUT/'pending-readonly-dedicated.png'));check('pending canonical visible read-only in dedicated mode')
  page.locator('[data-m7-exit]').click();page.wait_for_function("!document.body.classList.contains('mx-consultation-mode')");expect(page.locator('.vis31-pending-link')).to_be_visible();page.locator('.vis31-pending-link').click();expect(page.locator('#t-tareas-longitudinal')).to_be_visible();check('pending original navigation restored outside');resume()
  page.locator('[data-m7-section="documents"]').click()
  with page.expect_response(lambda r:r.request.method=='POST' and r.url.endswith('/note-capture-tokens')) as res:page.locator('[data-m7-capture-start]').click()
  token=res.value.json()['data']['token'];TOKENS.append(token);expect(page.locator('[data-docux-qr-content]')).to_be_visible()
  exit_programmatically();page.wait_for_function("!document.body.classList.contains('mx-consultation-mode')")
  d=client.get(BASE+'/api/clinical/index.php/note-capture-tokens/'+token).json()['data'];check('dedicated exit cancels pending capture through canonical authority',d['status']=='cancelled');resume()
  page.locator('[data-m7-section="documents"]').click();page.locator('[data-docux-attach]').click();page.locator('[data-m7-doc-title]').fill('VIS32 blocked upload')
  page.locator('[data-m7-doc-file]').set_input_files({'name':'synthetic.pdf','mimeType':'application/pdf','buffer':b'%PDF-1.4\n%%EOF'})
  held=[];page.route('**/encounters/*/documents',lambda r:held.append(r));page.locator('[data-m7-doc-upload]').click()
  for _ in range(100):
   if held:break
   page.wait_for_timeout(30)
  assert held;exit_programmatically();assert mode();expect(page.locator('[data-docux-upload]')).to_be_visible()
  held[0].fulfill(status=400,content_type='application/json',body='{"ok":false,"error":"QA_REJECTED"}');page.unroute('**/encounters/*/documents');expect(page.locator('[data-docux-upload-state]')).to_have_text('No se pudo guardar el documento.');page.locator('[data-docux-upload] [data-modal-cancel]').click();check('in-flight upload prevents context loss')
  ready('plan02','1015');page.locator('[data-m7-section="reason"]').click();editor=page.locator('[data-m7-editor-text]');expect(editor).to_be_editable();draft=editor.input_value()+'\nVIS32 local conflict proof';editor.fill(draft)
  def conflict(route):route.fulfill(status=409,content_type='application/json',body='{"ok":false,"error":"VERSION_CONFLICT"}')
  page.route('**/sections/reason_evolution',conflict);page.locator('[data-m7-exit]').click();expect(page.locator('[data-m7-conflict]')).to_be_visible()
  check('version conflict blocks exit preserves draft',mode() and page.locator('[data-m7-conflict-draft]').input_value()==draft)
  anon=p.request.new_context();r=anon.post(BASE+'/api/clinical/index.php/note-capture-tokens',data={'patient_id':'p_plan02ux_review','encounter_key':'enc:1016','note_context':'nota_clinica_modal'});check('unauthenticated issuance remains 401',r.status==401);anon.dispose()
  check('no browser errors',not errors)
 finally:
  if TASK:
   d=client.get(endpoint+'/'+str(TASK)).json()['data']['item']
   assert client.post(endpoint+'/'+str(TASK)+'/cancel',data={'expected_version':d['row_version'],'reason':'Fin de prueba sintética VIS32'},headers={'Idempotency-Key':str(uuid.uuid4())}).ok
  for token in TOKENS:client.post(BASE+'/api/clinical/index.php/note-capture-tokens/'+token+'/cancel',data={})
  client.dispose();b.close()
  log=Path('/Users/circulodigital/.codex/artifacts/director-expediente-review/launch-error.log');text=log.read_text()
  for token in TOKENS:text=text.replace(token,'[VIS32_REDACTED]'.ljust(len(token),'_'))
  log.write_text(text);(OUT/'safety-results.json').write_text(json.dumps(REPORT,indent=2)+'\n')
