"""Step 3 states, canonical autosave and historical reads in an isolated database.

Run through step3_head_neck_disposable_gate.sh. The Director shell provides only
presentation; encounter reads and physical_exam writes use the disposable API.
"""
import hashlib,json,os,subprocess
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright,expect

ROOT=Path(__file__).resolve().parents[3]
BASE=os.environ['STEP3_QA_BASE'];DB=os.environ['STEP3_QA_DB']
assert DB.startswith('head_neck_qa_') and len(DB)==25
OUT=Path(os.environ.get('STEP3_QA_ARTIFACTS','/tmp/mxmed-step3-head-neck'));OUT.mkdir(parents=True,exist_ok=True)
REVIEW=os.environ.get('STEP3_QA_REVIEW_URL','http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref=adult')
def sql(query):return subprocess.check_output(['mysql','-N',DB,'-e',query],text=True).strip()
def payload():return json.loads(sql("SELECT payload_json FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'"))
def review_hash():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 ORDER BY section_type'])).hexdigest()
before_review=review_hash();checks={};errors=[];unexpected_writes=[];writes=[]
def check(name,condition=True):assert condition,name;checks[name]='PASS';print('PASS '+name,flush=True)
with sync_playwright() as pw:
 browser=pw.webkit.launch();api=pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
 def proxy(route):
  request=route.request;path=urlsplit(request.url).path
  if request.method not in ['GET','HEAD']:
   assert request.method=='PUT' and path.endswith('/sections/physical_exam'),path
   writes.append(request.post_data_json)
  result=api.fetch(BASE+path,method=request.method,data=request.post_data,headers={'Accept':'application/json','Content-Type':'application/json'})
  route.fulfill(response=result)
 def guard(route):
  r=route.request
  if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):
   unexpected_writes.append(r.url);route.abort();return
  route.continue_()
 def ready(viewport=None):
  context=browser.new_context(viewport=viewport or {'width':1440,'height':900},timezone_id='America/Mexico_City')
  page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
  page.route('**/api/clinical/**',guard);page.route('**/api/clinical/index.php/encounters/**',proxy)
  page.goto(REVIEW,wait_until='commit');expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
  page.locator('[data-m7-section="exam"]').click();expect(page.locator('[data-m7-section="exam"]')).to_have_attribute('aria-current','true')
  expect(page.locator('[data-m7-exam-system]')).to_have_count(8)
  return context,page
 def reload_exam(page):
  page.reload(wait_until='commit');expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
  page.locator('[data-m7-section="exam"]').click();expect(page.locator('[data-m7-section="exam"]')).to_have_attribute('aria-current','true')
 def row(page,key):return page.locator(f'[data-m7-exam-system="{key}"]')
 def finding_semantics(page,key):
  return row(page,key).locator('input').evaluate('(n)=>({disabled:n.disabled,required:n.required,value:n.value,placeholder:n.placeholder})')

 context,page=ready();hn=row(page,'head_neck');cv=row(page,'cardiovascular')
 expect(hn.locator('select')).to_have_value('NOT_REVIEWED');expect(hn.locator('input')).to_have_value('');expect(page.locator('[data-m7-exam-save]')).to_be_disabled()
 check('new field has no prepopulated finding and no false dirty state')
 check('accessible state and finding labels',page.get_by_role('combobox',name='Cabeza y cuello: estado',exact=True).count()==1 and page.get_by_role('textbox',name='Cabeza y cuello: hallazgo anormal',exact=True).count()==1)
 check('exact existing three options',hn.locator('select option').evaluate_all('(ns)=>ns.map(n=>[n.value,n.textContent])')==cv.locator('select option').evaluate_all('(ns)=>ns.map(n=>[n.value,n.textContent])'))
 for state in ['ABNORMAL','NORMAL','NOT_REVIEWED']:
  hn.locator('select').select_option(state);cv.locator('select').select_option(state)
  check(state+' finding semantics match Cardiovascular',finding_semantics(page,'head_neck')==finding_semantics(page,'cardiovascular'))
  if state=='ABNORMAL':
   expect(hn.locator('input')).to_be_enabled();assert hn.locator('input').evaluate('(n)=>n.required')
   hn.locator('input').fill('Texto temporal');cv.locator('input').fill('Texto temporal')
  else:
   expect(hn.locator('input')).to_be_disabled();expect(hn.locator('input')).to_have_value('');assert not hn.locator('input').evaluate('(n)=>n.required')
 expect(page.locator('[data-m7-exam-save]')).to_be_disabled();check('return to unreviewed matches baseline without a write',not writes)
 cv.locator('select').select_option('NORMAL');hn.locator('select').select_option('ABNORMAL')
 page.locator('[data-m7-section="assessment"]').click();expect(page.locator('[data-m7-section="exam"]')).to_have_attribute('aria-current','true')
 expect(page.locator('[data-m7-exam-state]')).to_contain_text('Describe cada hallazgo anormal');check('empty required finding blocks navigation and write',not writes)
 text='Hallazgo sintético de cabeza y cuello.';hn.locator('input').fill(text);expect(page.locator('[data-m7-exam-save]')).to_be_enabled()
 page.locator('[data-m7-section="assessment"]').click();expect(page.locator('[data-m7-section="assessment"]')).to_have_attribute('aria-current','true',timeout=15000)
 check('canonical autosave uses the existing physical_exam endpoint and schema',len(writes)==1 and writes[0]['payload_schema_version']==1 and writes[0]['payload']['systems']=={'cardiovascular':{'state':'NORMAL'},'head_neck':{'state':'ABNORMAL','finding':text}})
 check('new field and established system persist in canonical storage',payload()['systems']==writes[0]['payload']['systems'])
 reload_exam(page);expect(hn.locator('select')).to_have_value('ABNORMAL');expect(hn.locator('input')).to_have_value(text);expect(cv.locator('select')).to_have_value('NORMAL')
 check('state and finding survive actual save and reload')
 changed='Hallazgo sintético editado.';hn.locator('input').fill(changed);expect(page.locator('[data-m7-exam-save]')).to_be_enabled()
 previous=sql("SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'")
 def fail(route):route.fulfill(status=503,content_type='application/json',body=json.dumps({'ok':False,'error':{'code':'QA_WRITE_FAILURE'}}))
 page.route('**/sections/physical_exam',fail);page.locator('[data-m7-section="assessment"]').click()
 expect(page.locator('[data-m7-exam-state]')).to_contain_text('No se guardó');expect(page.locator('[data-m7-section="exam"]')).to_have_attribute('aria-current','true');expect(hn.locator('input')).to_have_value(changed)
 check('save failure blocks navigation and retains dirty finding',sql("SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'")==previous)
 page.unroute('**/sections/physical_exam',fail);page.locator('[data-m7-section="assessment"]').click();expect(page.locator('[data-m7-section="assessment"]')).to_have_attribute('aria-current','true')
 reload_exam(page);expect(hn.locator('input')).to_have_value(changed);check('finding-only change saves on retry and reload')
 hn.locator('select').select_option('NORMAL');expect(hn.locator('input')).to_have_value('');page.locator('[data-m7-exam-save]').click();expect(page.locator('[data-m7-exam-state]')).to_have_text('Guardado')
 check('Normal saves without unnecessary free text',payload()['systems']['head_neck']=={'state':'NORMAL'})
 reload_exam(page);expect(hn.locator('select')).to_have_value('NORMAL');hn.locator('select').select_option('NOT_REVIEWED');page.locator('[data-m7-exam-save]').click();expect(page.locator('[data-m7-exam-state]')).to_have_text('Guardado')
 check('unreviewed uses existing omitted-system storage semantics','head_neck' not in payload()['systems'] and payload()['systems']['cardiovascular']=={'state':'NORMAL'})
 reload_exam(page);expect(hn.locator('select')).to_have_value('NOT_REVIEWED');check('unreviewed survives reload without becoming Normal');context.close()

 legacy={'general':{'state':'NORMAL'},'cardiovascular':{'state':'ABNORMAL','finding':'Hallazgo cardiovascular histórico'},'respiratory':{'state':'NORMAL'},'abdomen':{'state':'ABNORMAL','finding':'Hallazgo abdominal histórico'},'neurological':{'state':'NORMAL'},'musculoskeletal':{'state':'NORMAL'},'skin':{'state':'ABNORMAL','finding':'Hallazgo cutáneo histórico'}}
 raw=json.dumps({'systems':legacy},ensure_ascii=False).replace("'","''")
 sql("DELETE FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'")
 sql("INSERT INTO clinical_encounter_sections (encounter_id,section_type,payload_schema_version,payload_json,narrative_text,row_version,created_by_user_id,updated_by_user_id,created_at,updated_at) VALUES (1016,'physical_exam',1,'"+raw+"','Exploración histórica sintética',7,'review-user','review-user','2024-01-02 03:04:05','2024-01-02 03:04:05')")
 historical_before=sql("SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'");count=len(writes)
 context,page=ready();hn=row(page,'head_neck')
 expect(hn.locator('select')).to_have_value('NOT_REVIEWED');expect(hn.locator('input')).to_have_value('');expect(hn.locator('input')).to_be_disabled();expect(page.locator('[data-m7-exam-save]')).to_be_disabled()
 for key,item in legacy.items():
  expect(row(page,key).locator('select')).to_have_value(item['state']);expect(row(page,key).locator('input')).to_have_value(item.get('finding',''))
 check('all seven historical systems remain readable and unchanged')
 page.locator('[data-m7-section="assessment"]').click();expect(page.locator('[data-m7-section="assessment"]')).to_have_attribute('aria-current','true');reload_exam(page)
 expect(hn.locator('select')).to_have_value('NOT_REVIEWED');expect(page.locator('[data-m7-exam-save]')).to_be_disabled()
 check('missing historical head_neck defaults to NOT_REVIEWED without mutation',len(writes)==count and sql("SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 AND section_type='physical_exam'")==historical_before)
 page.screenshot(path=str(OUT/'historical-eight-systems-webkit.png'));context.close()

 context,page=ready({'width':390,'height':844});hn=row(page,'head_neck')
 hn.locator('select').select_option('ABNORMAL');hn.locator('input').fill('Hallazgo sintético en pantalla estrecha.')
 check('narrow stacked field is reachable and editable',hn.locator('input').is_visible())
 page.locator('[data-m7-exam-save]').click();expect(page.locator('[data-m7-exam-state]')).to_have_text('Guardado')
 reload_exam(page);expect(hn.locator('select')).to_have_value('ABNORMAL');expect(hn.locator('input')).to_have_value('Hallazgo sintético en pantalla estrecha.')
 check('narrow stacked field saves and reloads through the same writer')
 context.close();api.dispose();browser.close()
check('Director clinical records unchanged',review_hash()==before_review)
check('no unexpected clinical writes',not unexpected_writes);check('no browser JavaScript errors',not errors)
(OUT/'canonical-browser-report.json').write_text(json.dumps({'qa':'PASS','EXPLORATION_WRITER_REUSED':True,'BACKEND_CONTRACT_CHANGED':False,'OLD_RECORDS_MUTATED':False,'checks':checks,'canonical_physical_exam_put_count':len(writes),'javascript_errors':errors,'unexpected_writes':unexpected_writes},indent=2,ensure_ascii=False)+'\n')
print('STEP3_HEAD_NECK_CANONICAL_BROWSER_QA=PASS',flush=True)
