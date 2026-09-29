"""Read-only WebKit R3 visual fidelity and frozen-shell comparison."""
import ast,hashlib,json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
OUT=Path(os.environ.get('STEP6_R3_ARTIFACTS','/Users/circulodigital/.codex/artifacts/step6-visual-fidelity-r3'))
BASE='http://127.0.0.1:18148'
QUERY='review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=documents&review_placeholders=clean'
source=ast.parse(Path('modules/clinical/qa/consultation_flow_r1_visual.py').read_text())
METRICS=next(ast.literal_eval(n.value) for n in source.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='METRICS' for t in n.targets))
baseline=json.loads((OUT/'visual-baseline.json').read_text())
EXTRA=json.loads((OUT/'metrics-script.json').read_text())
HEAD=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
errors=[];writes=[];report={'source_head':HEAD,'sizes':{},'extra':{},'modals':{}}
def records():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounters ORDER BY encounter_id;SELECT * FROM clinical_documents ORDER BY id;SELECT * FROM clinical_encounter_sections ORDER BY encounter_id,section_type;SELECT * FROM clinical_observations ORDER BY observation_id'])).hexdigest()
before=records()
with sync_playwright() as p:
 browser=p.webkit.launch()
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  size=f'{w}x{h}';print('VISUAL '+size,flush=True);page=browser.new_page(viewport={'width':w,'height':h},timezone_id='America/Mexico_City')
  page.on('pageerror',lambda e:errors.append(str(e)))
  def guard(route):
   r=route.request
   if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):writes.append(r.url);route.abort()
   else:route.continue_()
  page.route('**/api/clinical/**',guard);page.route('**/api/agenda/**',guard)
  response=page.goto(BASE+'/index.html?'+QUERY,wait_until='commit')
  if os.environ.get('STEP6_R3_FINAL')=='1':assert response.status==200 and response.headers['x-mxmed-director-source-head']==HEAD
  expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true',timeout=55000);report['sizes'][size]={}
  for step in ['reason','measurements','exam','assessment','plan','documents','finalize']:
   page.locator(f'.m7-workspace-sections [data-m7-section="{step}"]').click();expect(page.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
   if step=='documents':expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
   if step=='finalize':expect(page.locator('[data-review-document-list] button')).to_have_count(4)
   page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();scrollTo(0,0);document.querySelector(".vis04-capture").scrollTop=0;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))}');page.mouse.move(0,0)
   m=page.evaluate(METRICS);old=baseline['sizes'][size][step];report['sizes'][size][step]=m
   # Hidden elements have no box; METRICS adds scrollY to their zero rect.
   for value in [m,old]:
    for area in value['areas'].values():
     if area['rect'][2:]==[0,0]:area['rect']=[0,0,0,0]
   for anchor in ['#p-expediente>.head>.exp-hdr','.m7-workspace-head','#m7-workspace-title','.vis23-appointment-meta','.m7-workspace-sections','[data-m7-step-title]']:
    assert m['areas'][anchor]['rect']==old['areas'][anchor]['rect'],(size,step,anchor)
   for anchor in ['.m7-step-intro','[data-m7-step-subtitle]','.vis04-capture','.vis04-progression','.vis04-helper','[data-vis04-prev]','[data-vis04-next]']:
    assert m['areas'][anchor]==old['areas'][anchor],(size,step,anchor,m['areas'][anchor],old['areas'][anchor])
   if step!='documents':assert m['controls']==old['controls'] and m['surface']==old['surface'],(size,step,'frozen step drift')
   assert not m['horizontalOverflow'],(size,step,'horizontal overflow')
   if w>=1200:assert not m['pageScroll'],(size,step,'page scroll')
   if step=='documents':
    assert page.locator('[data-doc-tool]').count()==3 and page.locator('[data-m7-documents] form:visible').count()==0
    icons=page.locator('[data-doc-tool]>.material-symbols-rounded:first-child').evaluate_all('ns=>ns.map(n=>parseFloat(getComputedStyle(n).fontSize))')
    assert all(a>=(60 if w>=1200 else 52) for a in icons),(size,'reference icon scale',icons)
    report['extra'][size]=page.evaluate(EXTRA)
    if w>=1200:
     assert not m['captureScroll'],(size,'main-panel content scroll')
     capture=page.locator('.vis04-capture').bounding_box();footer=page.locator('.vis04-progression').bounding_box()
     for selector in ['.flow-action-card','.flow-doc-preview','.flow-patient-documents']:
      for box in page.locator(selector).all():
       rect=box.bounding_box();assert rect['y']+rect['height']<=capture['y']+capture['height']+1 and rect['y']+rect['height']<footer['y'],(size,selector,'cropped by footer')
     assert page.locator('.flow-doc-preview').evaluate_all('ns=>ns.every(n=>getComputedStyle(n).backgroundColor==="rgb(255, 255, 255)")')
    page.screenshot(path=str(OUT/f'main-step6-{size}-webkit.png'),full_page=w<1200)
    for launch,modal,close,name in [('[data-docux-attach]','[data-docux-upload]','[data-docux-upload] [data-modal-cancel]','attach'),('[data-doc-tool="result"]','[data-docux-result]','[data-docux-result-cancel]','result')]:
     page.locator(launch).click();expect(page.locator(modal)).to_be_visible();assert page.locator(modal).evaluate('n=>!!n.getAttribute("aria-labelledby")&&!!n.getAttribute("aria-describedby")')
     for _ in range(9):page.keyboard.press('Tab');assert page.locator(modal).evaluate('n=>n.contains(document.activeElement)')
     assert page.locator(modal).evaluate('n=>n.scrollWidth<=n.clientWidth+1') and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
     page.evaluate('document.activeElement?.blur()');page.mouse.move(0,0);report['modals'][size+'-'+name]=page.evaluate(EXTRA);page.screenshot(path=str(OUT/f'{name}-step6-{size}-webkit.png'));page.locator(close).click();assert page.locator(launch).evaluate('n=>document.activeElement===n')
   if step=='finalize':page.screenshot(path=str(OUT/f'step7-regression-{size}-webkit.png'),full_page=w<1200)
  page.close()
 browser.close()
assert not errors and not writes and records()==before,(errors,writes)
report.update(qa='PASS',javascript_errors=errors,clinical_writes=writes,director_records_unchanged=True)
(OUT/'visual-final.json').write_text(json.dumps(report,indent=2));print('STEP6_VISUAL_R3_QA=PASS',flush=True)
