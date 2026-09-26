"""VIS31 focused browser QA. Only the existing synthetic Director runtime.
Creates two clearly marked review tasks through the canonical API, cancels both.
No capture tokens or clinical document writes in this gate.
"""
import json, os, subprocess, uuid
from pathlib import Path
import pymysql
from playwright.sync_api import sync_playwright, expect
BASE='http://127.0.0.1:18143';PATIENT='p_plan02ux_review';DB='mxmed_director_review_lon07c'
OUT=Path(os.environ['VIS31_ARTIFACTS']);OUT.mkdir(parents=True,exist_ok=True)
runtime=subprocess.check_output(['ps','eww','-p','4024'],text=True)
assert 'MXMED_DB_NAME='+DB in runtime and 'MXMED_DB_HOST=localhost' in runtime
conn=pymysql.connect(unix_socket='/tmp/mysql.sock',user='root',database=DB,autocommit=True)
def sql(q):
 with conn.cursor() as c:c.execute(q);return c.fetchall()
def snapshot():
 return {'encounters':sql('SELECT encounter_id,status FROM clinical_encounters ORDER BY encounter_id'),'appointments':sql('SELECT appointment_id,status FROM agenda_appointments ORDER BY appointment_id')}
REPORT={};CREATED=[]
def check(k,ok=True):
 assert ok,k
 REPORT[k]='PASS';print('PASS '+k,flush=True)
with sync_playwright() as p:
 b=p.chromium.launch(headless=True);ctx=b.new_context(viewport={'width':1440,'height':900});page=ctx.new_page();errors=[]
 page.on('pageerror',lambda e:errors.append(str(e)))
 client=p.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=director-lon07c-review'})
 endpoint=BASE+'/api/clinical/index.php/patients/'+PATIENT+'/longitudinal/tasks'
 def api(suffix='',data=None):
  r=client.get(endpoint+suffix) if data is None else client.post(endpoint+suffix,data=data,headers={'Idempotency-Key':str(uuid.uuid4())})
  assert r.ok,'canonical task operation failed '+str(r.status)
  return r.json()['data']
 def refresh():page.evaluate("window.dispatchEvent(new Event('lon06b:changed'))")
 def ready():
  page.goto(BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide',wait_until='commit')
  page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1016"',timeout=55000)
  page.wait_for_function('document.body.classList.contains("exp-consultation-focus-active")')
 try:
  before=snapshot();ready();assert not [x for x in api()['items'] if x['state']=='OPEN']
  indicator=page.locator('[data-vis31-pending]');button=indicator.locator('button')
  expect(indicator).not_to_be_visible();check('zero pending hidden')
  page.locator('.vis02-context').scroll_into_view_if_needed();page.screenshot(path=str(OUT/'header-zero.png'))
  for n in [1,2]:
   item=api(data={'task_type':'CLINICAL_ACTION','title':'VIS31 revisión sintética '+str(n)})['item'];CREATED.append(item['task_id']);refresh()
   expect(button).to_have_text('1 pendiente clínico' if n==1 else '2 pendientes clínicos');expect(indicator).to_be_visible()
  check('one and multiple canonical OPEN counts')
  expect(button).to_have_attribute('aria-label','2 pendientes clínicos de este paciente. Abrir seguimientos.')
  button.focus();assert button.evaluate('(e)=>document.activeElement===e');page.keyboard.press('Enter')
  expect(page.locator('#t-tareas-longitudinal')).to_be_visible();expect(page.locator('#lon06b-tasks')).to_contain_text('VIS31 revisión sintética 1')
  check('keyboard navigation existing patient task surface')
  page.locator('[data-vis02-action="consulta"]').click()
  expect(page.locator('#m7-workspace')).to_be_visible();expect(page.locator('[data-m7-body]')).to_have_attribute('data-encounter-id','1016')
  check('return same OPEN encounter',snapshot()==before)
  # Full width in each step, with no reserved grid track at any target viewport.
  for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
   page.set_viewport_size({'width':w,'height':h})
   for step in ['reason','measurements','exam','assessment','plan','documents','finalize']:
    page.locator('[data-m7-section="'+step+'"]').click();page.wait_for_timeout(120)
    assert page.locator('.vis04-context').count()==0
    dims=page.locator('.vis04-layout').evaluate('(e)=>({grid:e.clientWidth,main:e.querySelector(".vis04-capture").getBoundingClientRect().width,overflow:document.documentElement.scrollWidth>innerWidth})')
    assert abs(dims['grid']-dims['main'])<=1 and not dims['overflow'],str((w,step,dims))
    if step in ['measurements','plan','documents']:
     page.locator('#m7-workspace').scroll_into_view_if_needed();page.screenshot(path=str(OUT/f'{step}-{w}.png'))
   for name in ['allergies','medications','problems']:
    value=page.locator('[data-vis02-value="'+name+'"]');expect(value).to_be_visible()
    assert value.inner_text().strip()
    style=value.evaluate('(e)=>{const s=getComputedStyle(e.closest(".vis02-card"));return [s.opacity,s.filter]}');assert style==['1','none'],style
   page.locator('.vis02-context').scroll_into_view_if_needed();page.screenshot(path=str(OUT/f'header-pending-{w}.png'))
   check('all seven full width and critical summaries legible '+str(w))
  # Failure hides the projection; it never asserts a false zero or prints raw errors.
  def fail(route):route.fulfill(status=500,content_type='application/json',body='{"ok":false,"error":"RAW VIS31"}')
  page.route('**/patients/'+PATIENT+'/longitudinal/tasks',fail);refresh();page.wait_for_timeout(900)
  expect(indicator).not_to_be_visible();assert 'RAW VIS31' not in page.locator('.vis02-context').inner_text()
  check('failed task read isolated');page.unroute('**/patients/'+PATIENT+'/longitudinal/tasks',fail);refresh();expect(button).to_have_text('2 pendientes clínicos')
  # Delay the old patient's canonical response while the actual patient gate moves.
  held=[]
  def hold(route):held.append((route,route.fetch()))
  page.route('**/patients/'+PATIENT+'/longitudinal/tasks',hold);refresh()
  for _ in range(100):
   if held:break
   page.wait_for_timeout(50)
  assert held
  page.evaluate("void window.setActivePatientId('p_plan02_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false})")
  page.wait_for_function("document.querySelector('#p-expediente').dataset.patientId==='p_plan02_review'")
  for route,response in held:route.fulfill(response=response)
  page.unroute('**/patients/'+PATIENT+'/longitudinal/tasks',hold)
  page.wait_for_timeout(1200)
  expected=client.get(BASE+'/api/clinical/index.php/patients/p_plan02_review/longitudinal/tasks').json()['data']['items'];n=sum(x['state']=='OPEN' for x in expected)
  if n:expect(button).to_have_text(f'{n} pendiente clínico' if n==1 else f'{n} pendientes clínicos')
  else:expect(indicator).not_to_be_visible()
  check('late prior patient read discarded')
  ready();check('navigation no encounter or appointment mutation',snapshot()==before)
  (OUT/'page-errors.json').write_text(json.dumps(errors,indent=2));check('no browser errors',not errors)
 finally:
  for task in CREATED:
   row=api('/'+str(task))['item']
   if row['state']=='OPEN':api('/'+str(task)+'/cancel',{'expected_version':row['row_version'],'reason':'Fin de QA sintética VIS31'})
  client.dispose();b.close();conn.close();(OUT/'browser-results.json').write_text(json.dumps(REPORT,indent=2)+'\n')
