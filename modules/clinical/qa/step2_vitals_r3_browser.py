"""Focused R3 WebKit visual and interaction gate. Review rows never reach clinical storage."""
import json,os,re,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('STEP2_VITALS_ARTIFACTS','/tmp/mxmed-step2-vitals-r3a'));OUT.mkdir(parents=True,exist_ok=True)
START='402fb5f642fc95c9e280caa3f9d7de59afc88011'
BASE='http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide'
URL=BASE+'&review_step2_visual=r3&review_current_values='
STEPS=['reason','measurements','exam','assessment','plan','documents','finalize']
DESKTOP=[(1440,900),(1440,880),(1366,768)]
report={};errors=[]
METRICS='''()=>{
 const rect=n=>{if(typeof n==='string')n=document.querySelector(n);const r=n.getBoundingClientRect();return [r.x,r.y,r.width,r.height]};
 const capture=document.querySelector('.vis04-capture'),chips=[...document.querySelectorAll('.vis-step2-chip')];
 const fields=[...document.querySelectorAll('.vis29-register input,.vis29-register select,.vis29-register [data-m7-measurement-save]')].filter(n=>n.getBoundingClientRect().height>0);
 const badge=document.querySelector('#m7-workspace-title'),header=document.querySelector('.vis16-consultation-open [data-vis02-value="consulta"]');
 return {header:rect('#p-expediente>.head>.exp-hdr'),consultation:rect('.m7-workspace-head'),stepper:rect('.m7-workspace-sections'),title:rect('.m7-step-intro'),capture:rect(capture),footer:rect('.vis04-progression'),appointment:rect('.vis23-appointment-meta'),origin:rect('[data-m7-origin-cluster]'),prev:rect('[data-vis04-prev]'),next:rect('[data-vis04-next]'),form:rect('.vis29-register'),
 columns:getComputedStyle(document.querySelector('[data-m7-measurements-list]')).gridTemplateColumns.split(' ').length,
 pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,captureScroll:capture.scrollHeight>capture.clientHeight+1,
 fieldBottoms:fields.map(n=>n.getBoundingClientRect().bottom),fieldHeight:fields.map(n=>n.getBoundingClientRect().height),
 timestampVisible:[...document.querySelectorAll('[data-vis-step2-tooltip]')].some(n=>!n.hidden),
 animation:[getComputedStyle(badge).animation,getComputedStyle(header).animation],dotAnimation:[getComputedStyle(badge,'::before').animation,getComputedStyle(header,'::before').animation],badgeColor:getComputedStyle(badge).backgroundColor,dotColor:getComputedStyle(badge,'::before').backgroundColor,
 chips:chips.map(n=>({rect:rect(n),label:rect(n.querySelector('span')),value:rect(n.querySelector('strong')),edit:rect(n.querySelector('[aria-label^="Editar:"]')),close:rect(n.querySelector('[aria-label^="Eliminar:"]')),font:getComputedStyle(n.querySelector('button')).fontSize,overflow:n.scrollWidth>n.clientWidth+1}))};
}'''
def check(name,condition=True):
 assert condition,name
 report[name]='PASS';print('PASS '+name,flush=True)
# WebKit rounds transformed edges to fractional CSS pixels.
def near(a,b):return abs(a-b)<.125
with sync_playwright() as pw:
 browser=pw.webkit.launch(headless=True)
 def ready(w=1440,h=900,count=8,baseline=False,origin=False):
  page=browser.new_page(viewport={'width':w,'height':h},has_touch=w==390,timezone_id='America/Mexico_City');page.on('pageerror',lambda e:errors.append(str(e)))
  if baseline:
   for path in ['assets/js/clinical/m7-ws03.js','assets/css/expediente-paciente-visual-normalization.css']:
    content=subprocess.check_output(['git','show',START+':'+path],cwd=ROOT);mime='text/javascript' if path.endswith('.js') else 'text/css'
    page.route('**/'+path+'*',lambda route,request,content=content,mime=mime:route.fulfill(status=200,body=content,content_type=mime))
   original=subprocess.check_output(['git','show',START+':index.html'],cwd=ROOT).decode()
   def baseline_html(route):
    runtime=route.fetch().text();boot=re.search(r"<script>\nwindow.addEventListener\('load', \(\) => \{.*?</script>",runtime,re.S).group()
    injected='<style>#mxmed_dev_role_switcher{display:none!important}</style><script src="/__director_review_step2_r3.js"></script>'
    if origin:injected='<script src="/__director_review_origin_visual.js?v=1"></script>'+injected
    route.fulfill(status=200,content_type='text/html',body=original.replace('</head>',injected+'</head>').replace('</body>',boot+'</body>'))
   page.route('**/index.html?*',baseline_html)
  page.goto(URL+str(count)+('&review_origin_visual=linked-fallback' if origin else ''),wait_until='commit')
  expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
  expect(page.locator('.vis-step2-chip')).to_have_count(count);expect(page.locator('[data-vis29-prior] .vis29-reading')).to_have_count(4)
  page.evaluate('async()=>{await document.fonts.ready;scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}');page.mouse.move(0,0);return page
 baselines={}
 for w,h in ([] if os.environ.get('STEP2_QA_SHARED_ONLY') else DESKTOP):
  page=ready(w,h,baseline=True);baselines[(w,h)]=page.evaluate(METRICS);page.close()
 for w,h in ([] if os.environ.get('STEP2_QA_SHARED_ONLY') else DESKTOP+[(820,1180),(390,844)]):
  for count in ([0,1,4,8,9] if (w,h)==(1440,900) else [8]):
   page=ready(w,h,count);m=page.evaluate(METRICS)
   assert not m['horizontalOverflow'] and not any(c['overflow'] for c in m['chips']),m
   assert m['columns']==min(count or 1,8 if w>=1200 else 4 if w>=576 else 2),m
   assert not m['timestampVisible'],m
   assert page.locator('[data-vis29-prior-open]:visible').count()==1
   assert page.locator('.vis29-prior').count()==0
   if w>=1200:
    old=baselines[(w,h)]
    for field in ['header','consultation','stepper','title','capture','footer']:assert m[field]==old[field],(field,m[field],old[field])
    assert not m['pageScroll'],m
    if count<=8:assert not m['captureScroll'],m
    assert near(m['form'][2],m['capture'][2]),m
    assert max(m['fieldBottoms'])-min(m['fieldBottoms'])<=1,m
    assert min(m['fieldHeight'])>=36,m
    assert m['appointment'][:2]==old['appointment'][:2] and near(m['appointment'][2],old['appointment'][2]*.8) and near(m['appointment'][3],old['appointment'][3]*.8),m
   for c in m['chips']:
    x,y,width,height=c['rect'];cl=c['close'];ed=c['edit']
    assert near(cl[0]+cl[2],x+width-3) and near(cl[1],y+3),(c,'close')
    assert near(ed[0]+ed[2],x+width-3) and near(ed[1]+ed[3],y+height-3),(c,'edit')
    assert c['font']=='17.25px' and cl[2]>=28 and ed[3]>=28,c
    assert c['label'][0]+c['label'][2]<=cl[0] and c['value'][0]+c['value'][2]<=ed[0],c
   assert m['animation'][0]==m['animation'][1] and m['dotAnimation'][0]==m['dotAnimation'][1],m
   assert m['badgeColor']=='rgb(38, 200, 210)' and m['dotColor']=='rgb(21, 90, 112)',m
   report[f'{w}x{h}-{count}']=m
   if count==8 or (w,h)==(1440,900):page.screenshot(path=str(OUT/f'step2-{count}-values-{w}x{h}-webkit.png'),full_page=w<1200)
   check(f'responsive {w}x{h} count={count}')
   if count==8:
    page.locator('[data-vis29-prior-open]:visible').click();dialog=page.locator('[data-vis29-prior-dialog]');expect(dialog).to_be_visible()
    assert dialog.evaluate('(n)=>n.scrollWidth<=n.clientWidth') and dialog.bounding_box()['height']<=h-30
    page.screenshot(path=str(OUT/f'prior-modal-{w}x{h}-webkit.png'));page.locator('[data-vis29-prior-close]').click()
   page.close()
 # Compare both scaling and shell anchors for every accepted step at normal desktop size.
 old=ready(baseline=True);page=ready()
 for step in STEPS:
  for target in [old,page]:target.locator(f'[data-m7-section="{step}"]').click();expect(target.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
  for target in [old,page]:target.evaluate('async()=>{scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
  a=old.evaluate(METRICS);b=page.evaluate(METRICS)
  for field in ['header','consultation','stepper','title','footer']:assert a[field]==b[field],(step,field,a[field],b[field])
  for button in ['prev','next']:
   if a[button][2]==0:continue
   assert near(a[button][1],b[button][1]) and near(a[button][2]*.8,b[button][2]) and near(a[button][3]*.8,b[button][3]),(step,button,a[button],b[button])
   assert near(a[button][0],b[button][0]) if button=='prev' else near(a[button][0]+a[button][2],b[button][0]+b[button][2]),(step,button,a[button],b[button])
  check('shared anchors and 80% navigation '+step)
 old.close();page.close()
 # Origin fixture semantics remain untouched; scaling retains the accepted alignment.
 page=ready(origin=True);a=page.locator('.vis32-appointment-label').bounding_box();dot=page.locator('[data-m7-origin-dot]').bounding_box();label=page.locator('[data-m7-origin-label]').bounding_box()
 assert abs(dot['y']+dot['height']/2-(label['y']+label['height']/2))<2
 expect(page.locator('[data-m7-origin-label]')).to_have_text('Médico tratante');page.screenshot(path=str(OUT/'origin-alignment-webkit.png'));check('appointment origin alignment and semantics');page.close()
 page=ready();page.emulate_media(reduced_motion='reduce');m=page.evaluate(METRICS);assert all('none' in x for x in m['animation']+m['dotAnimation']);check('reduced motion shared effect');page.close()
 # Failure, two successes in the same modal, filtering and reload through canonical readback.
 page=ready(count=0);page.locator('[data-vis29-prior-open]:visible').click();dialog=page.locator('[data-vis29-prior-dialog]')
 temperature=dialog.locator('.vis-step2-prior-chip').filter(has_text='Temperatura');weight=dialog.locator('.vis-step2-prior-chip').filter(has_text='Peso')
 page.evaluate('''()=>{const base=window.fetch;let fail=true;window.fetch=async(input,init)=>{if(String(input).endsWith('/observations/reuse')&&fail){fail=false;return new Response(JSON.stringify({ok:false,error:{code:'M6_WRITE_WINDOW_BLOCKED'}}),{status:503});}return base(input,init);};}''')
 temperature.get_by_role('button',name='Usar en esta consulta:',exact=False).click();expect(page.locator('[data-vis29-prior-state]')).to_contain_text('guardado está pausado');expect(page.locator('.vis-step2-chip')).to_have_count(0);expect(temperature.get_by_role('button')).to_be_enabled();expect(dialog).to_be_visible();check('reuse failure keeps modal state and no phantom chip')
 temperature.get_by_role('button').click();expect(page.locator('.vis-step2-chip')).to_have_count(1);expect(temperature.get_by_role('button')).to_have_text('Ya registrado');expect(temperature.get_by_role('button')).to_be_disabled();expect(dialog).to_be_visible()
 weight.get_by_role('button').click();expect(page.locator('.vis-step2-chip')).to_have_count(2);expect(weight.get_by_role('button')).to_be_disabled();expect(dialog).to_be_visible();check('two canonical reuses in same modal with duplicate prevention')
 page.screenshot(path=str(OUT/'prior-two-reuses-same-modal-webkit.png'));page.locator('[data-vis29-prior-close]').click();assert page.locator('[data-m7-measurement-code] option[value=temperature]').count()==0 and page.locator('[data-m7-measurement-code] option[value=weight]').count()==0
 chip=page.locator('.vis-step2-chip').filter(has_text='Temperatura');chip.hover();tip=chip.locator('[role=tooltip]');expect(tip).to_be_visible();assert all(label in tip.inner_text() for label in ['Medido:','Incorporado a esta consulta:','Origen: Medición directa']);page.screenshot(path=str(OUT/'reused-two-times-tooltip-webkit.png'));chip.press('Escape');expect(tip).not_to_be_visible();page.locator('[data-vis29-prior-open]:visible').focus();chip.focus();expect(tip).to_be_visible();check('reused tooltip distinguishes measured and incorporated time')
 page.reload(wait_until='commit');expect(page.locator('.vis-step2-chip')).to_have_count(2,timeout=55000);assert page.locator('[data-m7-measurement-code] option[value=temperature]').count()==0;check('reload canonical current reader retains reused rows and filter');page.close()
 # Persisted response lost before UI sees it; retry uses the retained logical key.
 page=ready(count=0);page.locator('[data-vis29-prior-open]:visible').click();dialog=page.locator('[data-vis29-prior-dialog]');temperature=dialog.locator('.vis-step2-prior-chip').filter(has_text='Temperatura')
 page.evaluate('''()=>{const base=window.fetch;let lost=true;window.reuseQAKeys=[];window.fetch=async(input,init)=>{if(String(input).endsWith('/observations/reuse')){window.reuseQAKeys.push(new Headers(init.headers).get('Idempotency-Key'));const response=await base(input,init);if(lost){lost=false;throw new TypeError('QA response lost after persistence');}return response;}return base(input,init);};}''')
 temperature.get_by_role('button').click();expect(page.locator('[data-vis29-prior-state]')).to_contain_text('No se confirmó');expect(page.locator('.vis-step2-chip')).to_have_count(0);temperature.get_by_role('button').click();expect(page.locator('.vis-step2-chip')).to_have_count(1);keys=page.evaluate('window.reuseQAKeys');assert len(keys)==2 and keys[0]==keys[1];check('lost response retry keeps same command key and one chip');page.close()
 page=ready(390,844,count=1);chip=page.locator('.vis-step2-chip').first;chip.locator('span:not([data-vis-step2-tooltip])').first.tap();expect(chip.locator('[role=tooltip]')).to_be_visible();check('phone current metadata available on tap');page.close()
 # Reuse must preserve an unrelated manual draft, including the mandatory original Origen choice.
 page=ready(count=0);page.locator('[data-m7-measurement-code]').select_option('heart_rate');page.locator('[data-m7-measurement-value]').fill('88');page.locator('[data-vis29-prior-open]:visible').click()
 page.locator('[data-vis29-prior-dialog] .vis-step2-prior-chip').filter(has_text='Peso').get_by_role('button').click();expect(page.locator('.vis-step2-chip')).to_have_count(1);page.locator('[data-vis29-prior-close]').click()
 assert page.locator('[data-m7-measurement-value]').input_value()=='88' and page.locator('[data-m7-measurement-source]').input_value()==''
 page.locator('[data-vis04-next]').click();expect(page.locator('[data-m7-section="measurements"]')).to_have_attribute('aria-current','true');expect(page.locator('[data-m7-measurements-state]')).to_contain_text('origen');check('unrelated manual draft retained; manual Origen still blocks incomplete capture');page.close()
 # Safe navigation / VIS32: clean round-trip and failed save retains current consultation and draft.
 page=ready(count=0);page.locator('[data-vis04-prev]').click();expect(page.locator('[data-m7-section="reason"]')).to_have_attribute('aria-current','true');page.locator('[data-vis04-next]').click();expect(page.locator('[data-m7-section="measurements"]')).to_have_attribute('aria-current','true')
 page.locator('[data-m7-exit]').click();page.wait_for_function("!document.body.classList.contains('mx-consultation-mode')");page.locator('[data-vis02-action="consulta"]').click();page.wait_for_function("document.body.classList.contains('mx-consultation-mode')");check('VIS32 clean exit and resume; previous next navigation')
 page.locator('[data-m7-section="reason"]').click();editor=page.locator('[data-m7-editor-text]');expect(editor).to_be_editable();editor.fill(editor.input_value()+'\nR3 local QA draft');draft=editor.input_value();page.locator('[data-m7-exit]').click();expect(page.locator('[data-m7-editor-state]')).to_contain_text('No se guardó');assert page.evaluate("document.body.classList.contains('mx-consultation-mode')") and editor.input_value()==draft;check('VIS32 failed writer prevents exit and retains draft');page.close()
 # PLANRX02 preparation is local; all clinical confirmation writes remain blocked in visual review.
 page=ready(count=0);page.locator('[data-m7-section="plan"]').click();page.locator('[data-ns="prescription"]').click();rx=page.locator('.plan02b-modal');expect(rx).to_be_visible();expect(rx.locator('h4')).to_have_text('Preparar receta');page.locator('[data-rx-field="medicamento"]').fill('Medicamento sintético de QA');page.locator('[data-rx-add]').click();expect(page.locator('[data-rx-row]')).to_have_count(2);page.locator('[data-rx-field="medicamento"]').nth(1).fill('Segundo medicamento de QA');page.locator('[data-modal-add]').click();expect(rx).to_have_count(0);page.locator('[data-m7-section="documents"]').click();expect(page.locator('[data-plan02b-collector]')).to_contain_text('Receta');page.locator('[data-m7-section="finalize"]').click();expect(page.locator('[data-m7-section="finalize"]')).to_have_attribute('aria-current','true');check('PLANRX02 two-item preparation and Step 6/7 safe navigation');page.close()
 check('no browser JS errors',not errors);browser.close()
(OUT/'browser-results.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
print('STEP2_VITALS_R3_BROWSER_GATE=PASS',flush=True)
