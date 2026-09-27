"""WebKit density and accessibility checks; Director fixtures never persist writes."""
import json
import os
import subprocess
from pathlib import Path

from playwright.sync_api import expect, sync_playwright

ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('STEP2_VITALS_ARTIFACTS','/tmp/mxmed-step2-vitals-r2'))
OUT.mkdir(parents=True,exist_ok=True)
START='14495871120d5b35ec890cd317728aeb1d08089c'
BASE='http://127.0.0.1:18143/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide'
URL=BASE+'&review_step2_visual=r2&review_current_values='
DESKTOP=((1440,900),(1440,880),(1366,768))
report={}
errors=[]

METRICS='''()=>{
 const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return [r.x,r.y,r.width,r.height]};
 const chips=[...document.querySelectorAll('.vis-step2-chip')],capture=document.querySelector('.vis04-capture');
 const fields=[...document.querySelectorAll('.vis29-register input,.vis29-register select,.vis29-register [data-m7-measurement-save]')].filter(n=>n.getBoundingClientRect().height>0);
 return {header:rect('#p-expediente > .head > .exp-hdr'),consultation:rect('.m7-workspace-head'),appointment:rect('.vis23-appointment-meta'),origin:rect('[data-m7-origin-cluster]'),stepper:rect('.m7-workspace-sections'),footer:rect('.vis04-progression'),
 columns:getComputedStyle(document.querySelector('[data-m7-measurements-list]')).gridTemplateColumns.split(' ').length,
 chipRects:chips.map(n=>{const r=n.getBoundingClientRect();return [r.x,r.y,r.width,r.height]}),
 chipOverflow:chips.some(n=>n.scrollWidth>n.clientWidth+1),
 chipFooterOverlap:chips.some(n=>n.getBoundingClientRect().bottom>document.querySelector('.vis04-progression').getBoundingClientRect().top),
 pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,
 captureScroll:capture.scrollHeight>capture.clientHeight+1,
 fieldBottoms:fields.map(n=>n.getBoundingClientRect().bottom),
 priorHeight:document.querySelector('.vis29-prior').getBoundingClientRect().height,
 timestampVisible:[...document.querySelectorAll('[data-vis-step2-tooltip]')].some(n=>!n.hidden)};
}'''

with sync_playwright() as pw:
 browser=pw.webkit.launch(headless=True)
 def ready(width,height,count,baseline=False,touch=False):
  page=browser.new_page(viewport={'width':width,'height':height},has_touch=touch,timezone_id='America/Mexico_City')
  page.on('pageerror',lambda error:errors.append(str(error)))
  if baseline:
   for path in ('assets/js/clinical/m7-ws03.js','assets/css/expediente-paciente-visual-normalization.css'):
    content=subprocess.check_output(['git','show',START+':'+path],cwd=ROOT)
    mime='text/javascript' if path.endswith('.js') else 'text/css'
    page.route('**/'+path+'*',lambda route,request,content=content,mime=mime:route.fulfill(status=200,body=content,content_type=mime))
  page.goto(URL+str(count),wait_until='commit')
  expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
  expect(page.locator('.vis-step2-chip')).to_have_count(count)
  expect(page.locator('[data-vis29-prior] .vis29-reading')).to_have_count(4)
  page.mouse.move(0,0)
  return page

 baselines={}
 for width,height in DESKTOP:
  page=ready(width,height,8,baseline=True)
  baselines[f'{width}x{height}']=page.evaluate(METRICS)
  page.close()
 for width,height in DESKTOP+((820,1180),(390,844)):
  for count in (0,1,4,8,9):
   page=ready(width,height,count,touch=width==390)
   metric=page.evaluate(METRICS)
   assert not metric['horizontalOverflow'] and not metric['chipOverflow'],metric
   assert not metric['timestampVisible'],metric
   expected=min(count or 1,8 if width>=1200 else 4 if width>=576 else 2)
   assert metric['columns']==expected,metric
   if width>=1200:
    assert not metric['pageScroll'],metric
    for field in ('header','consultation','appointment','origin','stepper','footer'):
     assert metric[field]==baselines[f'{width}x{height}'][field],(field,metric[field],baselines[f'{width}x{height}'][field])
    if count<=8:
     assert not metric['captureScroll'] and not metric['chipFooterOverlap'],metric
    assert max(metric['fieldBottoms'])-min(metric['fieldBottoms'])<=1,metric
    if count==1:assert metric['chipRects'][0][2]<=240
    if count==8:assert metric['chipRects'][0][3]<baselines[f'{width}x{height}']['chipRects'][0][3],metric
   assert page.locator('[data-m7-measurement-time]').count()==0
   expect(page.locator('#vis29-prior-title')).to_have_text('historyValores registrados anteriormente')
   if count:
    assert page.locator('[data-m7-measurement-code] option[value=blood_pressure]').count()==0
   if (width,height)==(1440,900) or count==8:
    page.screenshot(path=str(OUT/f'step2-{count}-values-{width}x{height}-webkit.png'),full_page=width<1200)
   report[f'{width}x{height}-{count}']=metric
   if (width,height,count)==(1440,900,0):
    page.locator('[data-m7-measurement-code]').select_option('blood_pressure')
    pressure=page.evaluate(METRICS)
    assert max(pressure['fieldBottoms'])-min(pressure['fieldBottoms'])<=1,pressure
    page.screenshot(path=str(OUT/'step2-pressure-entry-1440-webkit.png'))
   print(f'PASS {width}x{height} count={count}',flush=True)
   page.close()

 page=ready(1440,900,4)
 chip=page.locator('.vis-step2-chip').first
 tip=chip.locator('[role=tooltip]')
 chip.hover();expect(tip).to_be_visible()
 expected=page.evaluate("()=>{const d=new Date('2026-09-27T20:22:00Z');return [d.toLocaleDateString('es-MX',{day:'numeric',month:'short',year:'numeric'}),d.toLocaleTimeString('es-MX',{hour:'numeric',minute:'2-digit'})]}")
 assert all(text in tip.inner_text() for text in expected),tip.inner_text()
 page.screenshot(path=str(OUT/'step2-timestamp-hover-webkit.png'))
 page.mouse.move(0,0);expect(tip).not_to_be_visible()
 chip.focus();expect(tip).to_be_visible()
 page.screenshot(path=str(OUT/'step2-timestamp-focus-webkit.png'))
 chip.press('Escape');expect(tip).not_to_be_visible()
 prior=page.locator('.vis-step2-prior-chip').first
 prior.focus();expect(prior.locator('[role=tooltip]')).to_be_visible()
 expected_prior=page.evaluate("()=>new Date('2026-09-20T18:00:00Z').toLocaleDateString('es-MX',{day:'numeric',month:'short',year:'numeric'})")
 assert expected_prior in prior.locator('[role=tooltip]').inner_text()
 page.screenshot(path=str(OUT/'step2-previous-timestamp-focus-webkit.png'))
 page.close()
 page=ready(390,844,1,touch=True)
 chip=page.locator('.vis-step2-chip').first
 chip.locator('span:not([data-vis-step2-tooltip])').first.tap();expect(chip.locator('[role=tooltip]')).to_be_visible()
 page.screenshot(path=str(OUT/'step2-timestamp-touch-390-webkit.png'))
 page.close()

 # Accepted prior reuse and pending navigation remain intact in the read-only review.
 page=ready(1440,900,0)
 prior=page.locator('.vis-step2-prior-chip').filter(has_text='Peso').first
 prior.get_by_role('button',name='Usar valor:',exact=False).click()
 assert page.locator('[data-m7-measurement-value]').input_value()=='78'
 assert page.locator('[data-m7-measurement-source]').input_value()==''
 page.locator('[data-vis04-next]').click()
 dialog=page.locator('[data-vis29-pending-dialog]');expect(dialog).to_be_visible()
 assert page.locator('[data-vis29-pending-register]').is_disabled()
 dialog.get_by_role('button',name='Seguir aquí').click()
 expect(page.locator('[data-vis04-progress]')).to_have_text('Paso 2 de 7')
 page.locator('[data-vis04-next]').click()
 dialog.get_by_role('button',name='Descartar y continuar').click()
 expect(page.locator('[data-vis04-progress]')).to_have_text('Paso 3 de 7')
 page.locator('[data-m7-section=measurements]').click()
 assert page.evaluate('window.mxmedDirectorStep2VisualFixture.readOnly')
 blocked=page.evaluate('''async()=>{const r=await fetch('/api/clinical/index.php/encounters/enc%3A1016/observations',{method:'POST',headers:{'Content-Type':'application/json'},body:'{}'});return [r.status,(await r.json()).error.code]}''')
 assert blocked==[403,'DIRECTOR_VISUAL_REVIEW_READ_ONLY'],blocked
 page.close()
 assert not errors,errors
 report['baseline']=baselines
 report['tooltip_hover_focus_touch']='PASS'
 report['prior_reuse_safe_navigation']='PASS'
 report['fixture_writes_blocked']='PASS'
 report['javascript_errors']=errors
 (OUT/'results.json').write_text(json.dumps(report,indent=2),encoding='utf-8')
 print('STEP2_VITALS_R2_WEBKIT_GATE=PASS',flush=True)
 browser.close()
