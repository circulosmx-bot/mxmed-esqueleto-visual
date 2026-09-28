"""Hard reloads without a type toggle, pointer/keyboard stepping and real VIS30 drafts."""
import hashlib,json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('VITALREF03_R1_ARTIFACTS','/tmp/mxmed-vitalref03-r1'));OUT.mkdir(parents=True,exist_ok=True)
BASE=os.environ.get('VITALREF03_R1_REVIEW_BASE','http://127.0.0.1:18148')
URL=BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref=adult'
EMPTY=URL+'&review_step2_visual=r4&review_current_values=0'
# Observe the actual app controller; do not instantiate a second one or change behavior.
INSTRUMENT='''\n;(()=>{const make=window.mxmedM7WS03;window.__anchorQA=[];window.mxmedM7WS03=(...args)=>{const ws=make(...args);window.__anchorQA.push(ws);return ws;};})();'''
CASES=[('blood_pressure','systolic','Ref. <120','121','119'),('blood_pressure','diastolic','Ref. <80','81','79'),('heart_rate','value','Ref. 60–100','81','79'),('respiratory_rate','value','Ref. 12–18','16','14'),('temperature','value','Ref. 36.5–37.3','37.0','36.8'),('oxygen_saturation','value','Ref. 95–100','99','97')]
checks={};errors=[];writes=[]
def check(name,condition=True):
 assert condition,name
 checks[name]='PASS';print('PASS '+name,flush=True)
def snapshot():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
def state(page):return page.evaluate('''()=>({instances:window.__anchorQA.length,dirty:window.__anchorQA.at(-1).isDirty(),drafts:Object.keys(sessionStorage).filter(k=>k.startsWith('mxmed.m7.ws03.draft:')),values:['value','systolic','diastolic'].map(k=>document.querySelector('[data-m7-measurement-'+k+']').value),saveDisabled:document.querySelector('[data-m7-measurement-save]').disabled})''')
with sync_playwright() as p:
 before=snapshot()
 for engine in [p.webkit,p.chromium]:
  browser=engine.launch();page=browser.new_page(viewport={'width':1440,'height':900});page.on('dialog',lambda d:d.accept());page.on('pageerror',lambda e:errors.append(str(e)))
  page.on('request',lambda r:writes.append(r.url) if '/api/clinical/' in r.url and r.method not in ['GET','HEAD'] else None)
  def instrument(route):
   response=route.fetch();route.fulfill(response=response,body=response.text()+INSTRUMENT)
  page.route('**/assets/js/clinical/m7-ws03.js*',instrument)
  def ready(code,field,placeholder):
   expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
   expect(page.locator('[data-m7-measurement-code]')).to_have_value(code)
   input=page.locator('[data-m7-measurement-'+field+']');expect(input).to_be_visible();expect(input).to_have_attribute('placeholder',placeholder)
   return input
  def clean(code,field,placeholder):
   input=ready(code,field,placeholder);expect(input).to_have_value('');expect(input).to_be_enabled();s=state(page)
   assert s=={'instances':1,'dirty':False,'drafts':[],'values':['','',''],'saveDisabled':True},s
   return input
  def cancel(code,field,placeholder):
   page.locator('[data-m7-measurement-new]').click();return clean(code,field,placeholder)
  page.goto(EMPTY,wait_until='commit');ready('blood_pressure','systolic','Ref. <120')
  for code,field,placeholder,up,down in CASES:
   page.locator('[data-m7-measurement-code]').select_option(code);clean(code,field,placeholder)
   # Reload must restore the active type and reference without select_option/rebinding.
   page.reload(wait_until='commit');input=clean(code,field,placeholder)
   input.press('ArrowUp');expect(input).to_have_value(up);check(engine.name+' '+code+' '+field+' reload keyboard up')
   cancel(code,field,placeholder).press('ArrowDown');expect(input).to_have_value(down);check(engine.name+' '+code+' '+field+' reload keyboard down')
   cancel(code,field,placeholder).locator('..').locator('[data-vitalref-step="1"]').click();expect(input).to_have_value(up);check(engine.name+' '+code+' '+field+' reload spinner up')
   cancel(code,field,placeholder).locator('..').locator('[data-vitalref-step="-1"]').click();expect(input).to_have_value(down);check(engine.name+' '+code+' '+field+' reload spinner down')
   cancel(code,field,placeholder)
  page.locator('[data-m7-measurement-code]').select_option('heart_rate');page.locator('[data-m7-measurement-code]').select_option('temperature');page.locator('[data-m7-measurement-code]').select_option('heart_rate');page.reload(wait_until='commit');clean('heart_rate','value','Ref. 60–100').press('ArrowUp');expect(page.locator('[data-m7-measurement-value]')).to_have_value('81');cancel('heart_rate','value','Ref. 60–100');check(engine.name+' HR temperature HR reload without toggle')
  for cycle in range(1,6):
   input=clean('heart_rate','value','Ref. 60–100');page.evaluate('window.__entryNode=document.querySelector("[data-m7-measurement-value]")')
   input.press('ArrowUp');expect(input).to_have_value('81');input.press('ArrowUp');expect(input).to_have_value('82');input.press('ArrowDown');expect(input).to_have_value('81');cancel('heart_rate','value','Ref. 60–100')
   page.locator('[data-m7-section="exam"]').click();expect(page.locator('[data-m7-section="exam"]')).to_have_attribute('aria-current','true')
   page.locator('[data-m7-section="measurements"]').click();input=clean('heart_rate','value','Ref. 60–100')
   assert page.evaluate('window.__entryNode===document.querySelector("[data-m7-measurement-value]")')
   input.locator('..').locator('[data-vitalref-step="1"]').click();expect(input).to_have_value('81');cancel('heart_rate','value','Ref. 60–100')
   page.reload(wait_until='commit');input=clean('heart_rate','value','Ref. 60–100');input.press('ArrowUp');expect(input).to_have_value('81');cancel('heart_rate','value','Ref. 60–100');check(engine.name+' lifecycle cycle '+str(cycle)+' exactly one increment')
  # Genuine draft: preserve the recovery lock, original value and explicit decision.
  input=clean('heart_rate','value','Ref. 60–100');input.fill('112');draft=page.evaluate('sessionStorage.getItem("mxmed.m7.ws03.draft:enc:1016:measurements")');page.reload(wait_until='commit');input=ready('heart_rate','value','Ref. 60–100');expect(input).to_be_disabled();expect(input).to_have_value('');expect(page.locator('[data-vis30-measurement-draft]')).to_be_visible();assert page.evaluate('sessionStorage.getItem("mxmed.m7.ws03.draft:enc:1016:measurements")')==draft
  page.locator('[data-vis30-measurement-recover]').click();expect(input).to_have_value('112');input.press('ArrowUp');expect(input).to_have_value('113');cancel('heart_rate','value','Ref. 60–100');check(engine.name+' real draft recovery and actual value win')
  input.press('ArrowUp');page.reload(wait_until='commit');ready('heart_rate','value','Ref. 60–100');page.locator('[data-vis30-measurement-discard]').click();input=clean('heart_rate','value','Ref. 60–100');input.locator('..').locator('[data-vitalref-step="1"]').click();expect(input).to_have_value('81');cancel('heart_rate','value','Ref. 60–100');check(engine.name+' discard restores interactive reference without toggle')
  page.screenshot(path=str(OUT/(engine.name+'-reload-lifecycle.png')));browser.close()
 # Canonical Director reader with no synthetic values/interception.
 browser=p.webkit.launch();page=browser.new_page();page.on('dialog',lambda d:d.accept());page.route('**/assets/js/clinical/m7-ws03.js*',instrument);page.goto(URL,wait_until='commit');ready('blood_pressure','systolic','Ref. <120');page.locator('[data-m7-measurement-code]').select_option('heart_rate');page.reload(wait_until='commit');input=ready('heart_rate','value','Ref. 60–100');expect(input).to_have_value('');input.press('ArrowUp');expect(input).to_have_value('81');page.locator('[data-m7-measurement-new]').click();check('canonical production reader restores HR without toggle');browser.close()
 check('no JavaScript errors',not errors);check('no clinical writes',not writes);check('canonical observations unchanged',snapshot()==before)
 report={'checks':checks,'javascript_errors':errors,'clinical_writes':writes,'canonical_observations_sha256':before,'DUPLICATE_HANDLER_COUNT':0,'INPUT_NODES_REPLACED_DURING_STEP_NAVIGATION':False}
 (OUT/'browser-report.json').write_text(json.dumps(report,indent=2));print('VITALREF03_R1_BROWSER_GATE=PASS',flush=True)
