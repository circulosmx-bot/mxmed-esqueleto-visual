"""Read-only WebKit shell/visual QA against the saved pre-change R1 baseline."""
import hashlib,json,os,subprocess,urllib.request
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
OUT=Path(os.environ.get('FLOW_R1_ARTIFACTS','/Users/circulodigital/.codex/artifacts/step6-7-clinical-flow-r1'));OUT.mkdir(parents=True,exist_ok=True)
BASE='http://127.0.0.1:18148'
QUERY='review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=reason&review_placeholders=clean'
BASELINE=json.loads((OUT/'visual-baseline.json').read_text())
STEPS=['reason','measurements','exam','assessment','plan','documents','finalize']
HEAD=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
FINAL=os.environ.get('FLOW_R1_FINAL')=='1'
METRICS="()=>{\n const rect=n=>{const r=n.getBoundingClientRect();return [r.x,r.y+scrollY,r.width,r.height]};\n const css=n=>{const s=getComputedStyle(n);return Object.fromEntries(['backgroundColor','color','borderTopColor','borderTopWidth','borderRadius','fontSize','lineHeight','padding','margin','opacity','outlineColor','outlineStyle','outlineWidth','boxShadow'].map(k=>[k,s[k]]))};\n const areas={};for(const s of ['#p-expediente>.head>.exp-hdr','.m7-workspace-head','#m7-workspace-title','.vis23-appointment-meta','.m7-workspace-sections','.m7-step-intro','[data-m7-step-title]','[data-m7-step-subtitle]','.vis04-layout','.vis04-capture','.vis04-progression','.vis04-helper','[data-vis04-prev]','[data-vis04-next]','.vis29-register','.vis29-current']){const n=document.querySelector(s);areas[s]={rect:rect(n),css:css(n)}}\n const visible=n=>n.getBoundingClientRect().height>0&&getComputedStyle(n).visibility!=='hidden';\n const controls=[...document.querySelectorAll('#m7-workspace input,#m7-workspace select,#m7-workspace textarea,#m7-workspace button,.vis-step2-chip')].filter(visible).map(n=>({tag:n.tagName,attrs:[...n.attributes].filter(a=>a.name.startsWith('data-')).map(a=>a.name).sort(),disabled:n.disabled??null,rect:rect(n),css:css(n)}));\n const modalStyles=[...document.querySelectorAll('dialog,.modal-content')].map(n=>({id:n.id,cls:n.className,css:css(n)}));\n const surf=document.querySelector('.consultation-workspace-surface');\n return {areas,controls,modalStyles,pageHeight:document.documentElement.scrollHeight,pageWidth:document.documentElement.scrollWidth,pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,captureScroll:document.querySelector('.vis04-capture').scrollHeight>document.querySelector('.vis04-capture').clientHeight+1,surface:surf?{rect:rect(surf),css:css(surf)}:null};\n}"
ANCHORS=['#p-expediente>.head>.exp-hdr','.m7-workspace-head','#m7-workspace-title','.vis23-appointment-meta','.m7-workspace-sections','[data-m7-step-title]']
DESKTOP=['.m7-step-intro','[data-m7-step-subtitle]','.vis04-capture','.vis04-progression','.vis04-helper','[data-vis04-prev]','[data-vis04-next]']
errors=[];writes=[];report={'source_head':HEAD,'sizes':{},'fixture':{}}
def records():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounters ORDER BY encounter_id;SELECT * FROM clinical_documents ORDER BY id;SELECT * FROM clinical_encounter_sections ORDER BY encounter_id,section_type;SELECT * FROM clinical_observations ORDER BY observation_id'])).hexdigest()
before=records()
def settle(page):
 page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();document.querySelector(".vis04-capture").scrollTop=0;scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)))}')
 page.mouse.move(0,0)
def select(page,step):
 page.locator(f'.m7-workspace-sections [data-m7-section="{step}"]').click();expect(page.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
 if step=='documents':expect(page.locator('[data-m7-doc-state]')).to_have_attribute('data-state','saved',timeout=15000)
 settle(page)
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
  if FINAL:assert response.status==200 and response.headers['x-mxmed-director-source-head']==HEAD
  expect(page.locator('[data-m7-section="reason"]')).to_have_attribute('aria-current','true',timeout=55000);report['sizes'][size]={}
  for step in STEPS:
   select(page,step);m=page.evaluate(METRICS);previous=BASELINE['sizes'][size][step];report['sizes'][size][step]=m
   for k in ANCHORS:
    actual=m['areas'][k]['rect'];expected=previous['areas'][k]['rect']
    assert (actual[:2] if k=='[data-m7-step-title]' else actual)==(expected[:2] if k=='[data-m7-step-title]' else expected),(size,step,k,actual,expected)
   if w>=1200 or step not in ['documents','finalize']:
    for k in DESKTOP:assert m['areas'][k]==previous['areas'][k],(size,step,k,m['areas'][k],previous['areas'][k])
   if step not in ['documents','finalize']:
    assert m['controls']==previous['controls'],(size,step,'control geometry/style drift')
    assert m['surface']==previous['surface'],(size,step,'surface drift')
   assert not m['horizontalOverflow'],(size,step,'horizontal overflow')
   if w>=1200:assert m['pageHeight']<=h+1,(size,step,'page scroll')
   if step in ['measurements','plan','documents','finalize']:page.screenshot(path=str(OUT/f'final-{step}-{size}-webkit.png'),full_page=w<1200)
  # Four pending preparations must not change any shared anchor, including the footer.
  page.goto(BASE+'/index.html?'+QUERY.replace('review_step=reason','review_step=finalize')+'&review_flow=r1',wait_until='commit');expect(page.locator('[data-m7-section="finalize"]')).to_have_attribute('aria-current','true',timeout=55000)
  expect(page.locator('[data-prepared]')).to_have_count(4);expect(page.locator('[data-plan02b-count]')).to_have_text('4');expect(page.locator('[data-m7-finalize]')).to_be_disabled();report['fixture'][size]={}
  for step in ['documents','finalize']:
   select(page,step);m=page.evaluate(METRICS);plain=report['sizes'][size][step];report['fixture'][size][step]=m
   for k in ANCHORS+(DESKTOP if w>=1200 else []):assert m['areas'][k]==plain['areas'][k],(size,step,'pending badge moved shell',k)
   assert not m['horizontalOverflow'] and (w<1200 or m['pageHeight']<=h+1),(size,step,'fixture overflow')
   if step=='documents':assert page.locator('[data-ns="confirm"]:visible').count()==0 and page.locator('[data-doc-tool]').count()==3
   else:
    expect(page.locator('[data-ns="confirm"]')).to_be_visible()
    if w>=1200:
     confirm=page.locator('[data-ns="confirm"]').bounding_box();final=page.locator('[data-m7-finalize]').bounding_box();assert confirm['x']>=0 and abs(confirm['y']-final['y'])<1 and confirm['x']+confirm['width']<=final['x']-5,(size,'footer confirmation anchor')
    # Primary four cards and edit controls are visible without capture scrolling.
    if w>=1200:
     capture=page.locator('.vis04-capture').bounding_box();edit=page.locator('[data-review-docs]').bounding_box();assert edit['y']+edit['height']<=capture['y']+capture['height']+1,(size,'normal review clipped')
   page.screenshot(path=str(OUT/f'director-{step}-{size}-webkit.png'),full_page=w<1200)
  page.close()
 browser.close()
assert not errors and not writes and records()==before,(errors,writes)
report.update(qa='PASS',javascript_errors=errors,clinical_writes=writes,director_records_unchanged=True)
(OUT/'visual-final.json').write_text(json.dumps(report,indent=2));print('CONSULTATION_FLOW_R1_VISUAL_QA=PASS',flush=True)
