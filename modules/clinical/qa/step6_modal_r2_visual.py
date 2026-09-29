"""Read-only WebKit R2 shell/visual QA against the captured clean R1 HEAD."""
import ast,hashlib,json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
OUT=Path(os.environ.get('STEP6_R2_ARTIFACTS','/Users/circulodigital/.codex/artifacts/step6-modal-actions-r2'))
BASE='http://127.0.0.1:18148'
QUERY='review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=documents&review_placeholders=clean'
source=ast.parse(Path('modules/clinical/qa/consultation_flow_r1_visual.py').read_text())
METRICS=next(ast.literal_eval(n.value) for n in source.body if isinstance(n,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='METRICS' for t in n.targets))
baseline=json.loads((OUT/'visual-baseline.json').read_text())
HEAD=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
errors=[];writes=[];report={'source_head':HEAD,'sizes':{}}
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
  if os.environ.get('STEP6_R2_FINAL')=='1':assert response.status==200 and response.headers['x-mxmed-director-source-head']==HEAD
  expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true',timeout=55000);report['sizes'][size]={}
  for step in ['reason','measurements','exam','assessment','plan','documents','finalize']:
   page.locator(f'.m7-workspace-sections [data-m7-section="{step}"]').click();expect(page.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
   if step=='documents':expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved')
   page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();scrollTo(0,0);document.querySelector(".vis04-capture").scrollTop=0;await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))}');page.mouse.move(0,0)
   m=page.evaluate(METRICS);old=baseline['sizes'][size][step];report['sizes'][size][step]=m
   # Hidden elements have no box; METRICS adds scrollY to their zero rect.
   for value in [m,old]:
    for area in value['areas'].values():
     if area['rect'][2:]==[0,0]:area['rect']=[0,0,0,0]
   for anchor in ['#p-expediente>.head>.exp-hdr','.m7-workspace-head','#m7-workspace-title','.vis23-appointment-meta','.m7-workspace-sections','[data-m7-step-title]']:
    assert m['areas'][anchor]['rect']==old['areas'][anchor]['rect'],(size,step,anchor)
   if w>=1200 or step!='documents':
    for anchor in ['.m7-step-intro','[data-m7-step-subtitle]','.vis04-capture','.vis04-progression','.vis04-helper','[data-vis04-prev]','[data-vis04-next]']:
     # Subtitle copy changes its intrinsic width; its origin/height stay locked.
     if step=='documents' and anchor=='[data-m7-step-subtitle]':assert m['areas'][anchor]['rect'][:2]==old['areas'][anchor]['rect'][:2] and m['areas'][anchor]['rect'][3]==old['areas'][anchor]['rect'][3]
     else:assert m['areas'][anchor]==old['areas'][anchor],(size,step,anchor,m['areas'][anchor],old['areas'][anchor])
   if step!='documents':
    # Generated document buttons arrive asynchronously from canonical readback.
    # Compare the frozen named controls; generated anonymous reader launches are
    # separately verified by the canonical gate.
    controls=lambda value:[n for n in value['controls'] if step!='finalize' or n['attrs']]
    assert controls(m)==controls(old) and m['surface']==old['surface'],(size,step,'frozen step drift')
   assert not m['horizontalOverflow'],(size,step,'horizontal overflow')
   if w>=1200:assert not m['pageScroll'],(size,step,'page scroll')
   if step=='documents':
    assert page.locator('[data-doc-tool]').count()==3 and page.locator('[data-m7-documents] form:visible').count()==0
    icons=page.locator('[data-doc-tool]>.material-symbols-rounded:first-child').evaluate_all('ns=>ns.map(n=>parseFloat(getComputedStyle(n).fontSize))')
    assert all(a==1.5*float(b[:-2]) for a,b in zip(icons,old['icon_sizes'])),(size,'icon scale',icons)
    if w>=1200:assert not m['captureScroll'],(size,'main-panel content scroll')
    page.screenshot(path=str(OUT/f'main-step6-{size}-webkit.png'),full_page=w<1200)
    for launch,modal,close,name in [('[data-docux-attach]','[data-docux-upload]','[data-docux-upload] [data-modal-cancel]','attach'),('[data-doc-tool="result"]','[data-docux-result]','[data-docux-result-cancel]','result')]:
     page.locator(launch).click();expect(page.locator(modal)).to_be_visible();assert page.locator(modal).evaluate('n=>!!n.getAttribute("aria-labelledby")&&!!n.getAttribute("aria-describedby")')
     for _ in range(9):page.keyboard.press('Tab');assert page.locator(modal).evaluate('n=>n.contains(document.activeElement)')
     assert page.locator(modal).evaluate('n=>n.scrollWidth<=n.clientWidth+1') and page.evaluate('document.documentElement.scrollWidth<=innerWidth+1')
     page.mouse.move(0,0);page.screenshot(path=str(OUT/f'{name}-step6-{size}-webkit.png'));page.locator(close).click();assert page.locator(launch).evaluate('n=>document.activeElement===n')
  page.close()
 browser.close()
assert not errors and not writes and records()==before,(errors,writes)
report.update(qa='PASS',javascript_errors=errors,clinical_writes=writes,director_records_unchanged=True)
(OUT/'visual-final.json').write_text(json.dumps(report,indent=2));print('STEP6_MODAL_R2_VISUAL_QA=PASS',flush=True)
