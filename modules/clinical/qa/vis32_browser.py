"""VIS32 navigation QA. Existing synthetic Director only."""
import json,os,subprocess,uuid
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
import pymysql
BASE='http://127.0.0.1:18143';OUT=Path(os.environ['VIS32_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
DB='mxmed_director_review_lon07c';runtime=subprocess.check_output(['ps','eww','-p','4024'],text=True)
assert 'MXMED_DB_NAME='+DB in runtime and 'MXMED_DB_HOST=localhost' in runtime
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=True)
def sql(q):
 with conn.cursor() as c:c.execute(q);return c.fetchall()
REPORT={};STARTED=None
TARGETS=['#t-resumen-longitudinal','#t-historial-atencion','#t-estudios','#t-consent','#t-tratamiento','#t-datos']
with sync_playwright() as p:
 b=p.chromium.launch();ctx=b.new_context(viewport={'width':1440,'height':900});page=ctx.new_page();errors=[];writes=[];dialogs=[]
 page.on('pageerror',lambda e:errors.append(e.stack));page.on('request',lambda r:writes.append((r.method,r.url)) if r.method in ['POST','PUT','PATCH','DELETE'] and '/api/' in r.url else None)
 page.on('dialog',lambda d:(dialogs.append(d.message),d.dismiss()))
 client=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=director-lon07c-review'})
 def check(name,ok=True):
  assert ok,name
  REPORT[name]='PASS';print('PASS '+name,flush=True)
 def ready(patient='plan02ux',eid='1016',inside=True):
  page.goto(BASE+'/index.html?review_patient='+patient+('&review_encounter=open' if inside else '')+'&qa_tools=hide',wait_until='commit')
  if inside:
   expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id',eid,timeout=55000)
   page.wait_for_function("document.body.classList.contains('mx-consultation-mode')")
  else:expect(page.locator('[data-vis02-action="consulta"]')).to_be_enabled(timeout=55000)
 def inside():return page.evaluate("document.body.classList.contains('mx-consultation-mode')")
 def leave():
  page.locator('[data-m7-exit]').click();page.wait_for_function("!document.body.classList.contains('mx-consultation-mode')")
 def resume(eid):
  page.locator('[data-vis02-action="consulta"]').click();expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id',eid)
  page.wait_for_function("document.body.classList.contains('mx-consultation-mode')")
 def six():
  assert page.locator('[data-exp-tabs] .nav-link:visible .tab-lbl').all_text_contents()==['Resumen','Historial','Órdenes y resultados','Documentos','Recetas','Administrativo']
 def step(s):
  page.locator('[data-m7-section="'+s+'"]').click();expect(page.locator('[data-m7-section="'+s+'"]')).to_have_attribute('aria-current','true')
 try:
  ready('no-open',inside=False);expect(page.locator('[data-vis02-action="consulta"]')).to_have_text('INICIAR CONSULTA');six()
  assert sql("SELECT COUNT(*) FROM clinical_encounters WHERE patient_id='review-no-open' AND status='open'")[0][0]==0
  n=len(writes)
  for target in TARGETS:
   page.locator('[data-exp-tabs] [data-bs-target="'+target+'"]').click();expect(page.locator(target)).to_be_visible();check('direct access '+target)
  check('six modules without START',len(writes)==n)
  page.locator('[data-vis02-action="consulta"]').click();page.wait_for_function("document.querySelector('[data-m7-body]')?.dataset.encounterState==='open'",timeout=20000)
  page.wait_for_function("!!document.querySelector('[data-m7-body]')?.dataset.encounterId")
  STARTED=page.locator('[data-m7-body]').get_attribute('data-encounter-id')
  check('explicit START exactly one',sql("SELECT COUNT(*) FROM clinical_encounters WHERE patient_id='review-no-open' AND status='open'")[0][0]==1)
  for _ in range(3):leave();six();expect(page.locator('[data-vis02-action="consulta"]')).to_have_text('VOLVER A CONSULTA');resume(STARTED)
  check('repeat resume zero extra START',len(writes)==n+1)
  ready('plan02','1015');n=len(writes);leave();check('clean exit immediate no write or dialog',len(writes)==n and not dialogs)
  page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();resume('1015');leave();expect(page.locator('#t-estudios')).to_be_visible();check('return remembers valid general tab')
  resume('1015')
  for name in ['reason','plan']:
   step(name);editor=page.locator('[data-m7-editor-text]');expect(editor).to_be_editable();original=editor.input_value();editor.fill(original+'\nVIS32 synthetic exit save');n=len(writes);leave();check('dirty '+name+' uses existing safe writer',len(writes)==n+1)
   resume('1015');step(name);expect(editor).to_be_editable();expect(editor).to_have_value(original+'\nVIS32 synthetic exit save');editor.fill(original);leave();resume('1015')
  step('reason');editor=page.locator('[data-m7-editor-text]');expect(editor).to_be_editable();original=editor.input_value();draft=original+'\nVIS32 blocked save';editor.fill(draft)
  def fail(route):route.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"M6_WRITE_WINDOW_BLOCKED"}')
  page.route('**/sections/reason_evolution',fail);page.locator('[data-m7-exit]').click();expect(page.locator('[data-m7-editor-state]')).to_contain_text('Guardado temporalmente pausado')
  check('write-window failure blocks exit preserves content',inside() and editor.input_value()==draft)
  page.unroute('**/sections/reason_evolution',fail);editor.fill(original)
  step('plan');page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('VIS32 prepared only');page.locator('[data-modal-add]').click()
  expect(page.locator('[data-plan02b-count]')).to_have_text('1');n=len(writes);leave();resume('1015');step('documents')
  expect(page.locator('[data-prepared]')).to_have_count(1);expect(page.locator('[data-plan02b-count]')).to_have_text('1');expect(page.locator('[data-prepared]')).to_contain_text('VIS32 prepared only')
  check('Plan preparation badge preserved with zero exit writes',len(writes)==n and not dialogs)
  page.locator('[data-remove]').click();expect(page.locator('[data-prepared]')).to_have_count(0)
  step('plan');page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('VIS32 blocked in flight');page.locator('[data-modal-add]').click();step('documents');held=[]
  page.route('**/encounters/*/documents',lambda r:held.append(r));page.locator('[data-ns="confirm"]').click()
  for _ in range(100):
   if held:break
   page.wait_for_timeout(30)
  assert held;page.locator('[data-m7-exit]').click();assert inside()
  held[0].fulfill(status=400,content_type='application/json',body='{"ok":false,"error":"QA_REJECTED"}');page.unroute('**/encounters/*/documents');page.wait_for_function('!window.mxmedPlanNextSteps.isBusy()')
  check('in progress Plan exit blocked');leave();resume('1015');step('documents');expect(page.locator('[data-prepared]')).to_have_count(1)
  ctx.close();ctx=b.new_context(viewport={'width':1440,'height':900});page=ctx.new_page();page.on('pageerror',lambda e:errors.append(e.stack))
  ready();leave();page.locator('[data-vis16-finalize]').click();expect(page.locator('[data-m7-section="finalize"]')).to_have_attribute('aria-current','true');check('finalize shortcut opens Step7 without terminal effect',sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')[0][0]=='open')
  step('reason');history_length=page.evaluate('history.length')
  for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
   page.set_viewport_size({'width':w,'height':h})
   for s in ['reason','plan','documents']:
    step(s);page.wait_for_timeout(400);page.evaluate('scrollTo(0,0)');page.screenshot(path=str(OUT/f'dedicated-{s}-{w}.png'))
    assert page.locator('#p-expediente [data-exp-tabs]').is_hidden() and page.locator('.mm-sidebar').is_hidden()
    assert not page.locator('.vis04-context').count()
    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
    assert page.locator('.mm-main').bounding_box()['x']<25
    for key in ['allergies','medications','problems']:expect(page.locator('[data-vis02-value="'+key+'"]').first).to_be_visible()
    assert page.locator('.exp-hdr button:visible, .exp-hdr a:visible').count()==0
    page.locator('[data-m7-exit]').focus();assert page.locator('[data-m7-exit]').evaluate('(e)=>e===document.activeElement')
   leave();six();page.wait_for_timeout(800);page.evaluate('scrollTo(0,0)');page.screenshot(path=str(OUT/f'expediente-{w}.png'));resume('1016');check('responsive isolated mode '+str(w))
  check('no history entries per step',page.evaluate('history.length')==history_length)
  check('clean UX encounter OPEN no documents',sql('SELECT status FROM clinical_encounters WHERE encounter_id=1016')[0][0]=='open' and sql("SELECT COUNT(*) FROM clinical_documents WHERE patient_id='p_plan02ux_review'")[0][0]==0)
  check('no browser errors',not errors)
 finally:
  if STARTED:
   r=client.post(BASE+'/api/clinical/index.php/encounters/enc%3A'+STARTED+'/void',data={'reason':'Fin de prueba sintética VIS32 START'},headers={'Idempotency-Key':str(uuid.uuid4())});assert r.ok,'QA START cleanup failed'
  client.dispose();b.close();conn.close();(OUT/'browser-results.json').write_text(json.dumps(REPORT,indent=2)+'\n');(OUT/'page-errors.json').write_text(json.dumps(errors,indent=2)+'\n')
