"""Actual Director registry and canonical observations, WebKit evidence, no writes."""
import hashlib,json,os,re,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('VITALREF_ARTIFACTS','/tmp/mxmed-vitalref01'));OUT.mkdir(parents=True,exist_ok=True)
BASE=os.environ.get('VITALREF_REVIEW_BASE','http://127.0.0.1:18148')
START='4a2f11688084799563f66cb9c6ae446cec49ba8b'
URL=BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref='
errors=[];writes=[];report={}
METRICS='''()=>{
 const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return[r.x,r.y+scrollY,r.width,r.height]};
 const hint=document.querySelector('[data-vitalref-hint]'),r=hint.getBoundingClientRect(),writer=document.querySelector('.vis29-register').getBoundingClientRect();
 return {header:rect('#p-expediente>.head>.exp-hdr'),consultation:rect('.m7-workspace-head'),stepper:rect('.m7-workspace-sections'),footer:rect('.vis04-progression'),writer:rect('.vis29-register'),hint:rect('[data-vitalref-hint]'),hintHidden:hint.hidden,hintOverflow:hint.scrollWidth>hint.clientWidth+1,hintInWriter:hint.hidden||(r.left>=writer.left&&r.right<=writer.right&&r.top>=writer.top&&r.bottom<=writer.bottom),pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,inputs:[...document.querySelectorAll('[data-m7-measurement-value],[data-m7-measurement-systolic],[data-m7-measurement-diastolic]')].map(n=>n.value)};
}'''
names={'blood_pressure':'Presión arterial','heart_rate':'Frecuencia cardíaca','respiratory_rate':'Frecuencia respiratoria','temperature':'Temperatura','oxygen_saturation':'Saturación de oxígeno','pain':'Dolor','weight':'Peso','height':'Estatura','waist':'Cintura'}
with sync_playwright() as pw:
 browser=pw.webkit.launch()
 def ready(context='adult',w=1440,h=900,baseline=False):
  page=browser.new_page(viewport={'width':w,'height':h},has_touch=w==390,timezone_id='America/Mexico_City');page.on('pageerror',lambda e:errors.append(str(e)))
  page.on('request',lambda r:writes.append(r.url) if '/api/clinical/' in r.url and r.method not in ['GET','HEAD'] else None)
  if baseline:
   for relative in ['assets/js/clinical/m7-ws03.js','assets/css/expediente-paciente-visual-normalization.css']:
    content=subprocess.check_output(['git','show',START+':'+relative],cwd=ROOT);mime='text/javascript' if relative.endswith('.js') else 'text/css'
    page.route('**/'+relative+'*',lambda route,request,content=content,mime=mime:route.fulfill(status=200,body=content,content_type=mime))
   original=subprocess.check_output(['git','show',START+':index.html'],cwd=ROOT).decode()
   def html(route):
    runtime=route.fetch().text();boot=re.search(r"<script>\nwindow.addEventListener\('load', \(\) => \{.*?</script>",runtime,re.S).group()
    injection='<style>#mxmed_dev_role_switcher{display:none!important}</style><script src="/__director_vitalref01_review.js?v=1"></script>'
    route.fulfill(status=200,content_type='text/html',body=original.replace('</head>',injection+'</head>').replace('</body>',boot+'</body>'))
   page.route('**/index.html?*',html)
  page.goto(URL+context,wait_until='commit');expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
  if not baseline:expect(page.locator('[data-vitalref-hint]')).to_be_visible()
  page.evaluate('async()=>{await document.fonts.ready;scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
  page.mouse.move(0,0);return page
 def choose(page,code):
  cancel=page.locator('[data-m7-measurement-new]')
  if cancel.is_visible():cancel.click()
  selector=page.locator('[data-m7-measurement-code]')
  if selector.locator('option[value="'+code+'"]').count():selector.select_option(code)
  else:page.get_by_role('button',name='Editar: '+names[code]+' ·',exact=False).click()
  page.evaluate('async()=>{document.activeElement?.blur();scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  old=ready(w=w,h=h,baseline=True)
  # Baseline has no hint node; compare only accepted fixed shell anchors.
  anchors=old.evaluate('''()=>Object.fromEntries(['#p-expediente>.head>.exp-hdr','.m7-workspace-head','.m7-workspace-sections','.vis04-progression'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return[s,[r.x,r.y+scrollY,r.width,r.height]]}))''')
  page=ready(w=w,h=h);m=page.evaluate(METRICS);report[f'{w}x{h}']=m
  assert not m['horizontalOverflow'] and not m['hintOverflow'] and m['hintInWriter'],m
  assert m['header']==anchors['#p-expediente>.head>.exp-hdr'] and m['consultation']==anchors['.m7-workspace-head'] and m['stepper']==anchors['.m7-workspace-sections'],m
  assert m['footer']==anchors['.vis04-progression'],('footer displaced',m,anchors)
  if w>=1200:assert not m['pageScroll'] and m['footer'][1]+m['footer'][3]<=h,m
  page.screenshot(path=str(OUT/f'G-step2-{w}x{h}-webkit.png'),full_page=w<1200)
  if w==390:
   page.locator('[data-vitalref-hint]').tap();expect(page.locator('[data-vitalref-tooltip]')).to_be_visible()
   page.locator('[data-vitalref-hint]').press('Escape');expect(page.locator('[data-vitalref-tooltip]')).not_to_be_visible()
  if w>=1200:
   for code in names:
    choose(old,code);choose(page,code);a=page.evaluate(METRICS)
    accepted_writer=old.locator('.vis29-register').evaluate('(n)=>{const r=n.getBoundingClientRect();return[r.x,r.y+scrollY,r.width,r.height]}')
    assert a['footer']==m['footer'] and a['stepper']==m['stepper'] and a['writer']==accepted_writer and not a['horizontalOverflow'] and a['hintInWriter'] and not a['hintOverflow'],(code,a,accepted_writer)
    if code in ['weight','height','waist']:assert a['hintHidden']
   report[f'all_types_{w}x{h}']='PASS'
  else:
   for code in names:
    choose(old,code);choose(page,code);a=page.evaluate(METRICS)
    accepted=old.evaluate('''()=>Object.fromEntries(['.vis29-register','.vis04-progression'].map(s=>{const r=document.querySelector(s).getBoundingClientRect();return[s,[r.x,r.y+scrollY,r.width,r.height]]}))''')
    assert a['writer']==accepted['.vis29-register'] and a['footer']==accepted['.vis04-progression'] and a['hintInWriter'] and not a['hintOverflow'] and not a['horizontalOverflow'],(code,a,accepted)
   report[f'all_types_{w}x{h}']='PASS'
  old.close();page.close();print(f'PASS reference responsive {w}x{h}',flush=True)
 page=ready();response=page.request.get(BASE+'/api/clinical/index.php/encounters/enc%3A1016');before=response.json();assert response.status==200
 before_hash=hashlib.sha256(json.dumps(before['data']['observations'],sort_keys=True).encode()).hexdigest()
 for letter,code in [('A','heart_rate'),('B','blood_pressure'),('C','oxygen_saturation'),('D','pain'),('E','weight')]:
  choose(page,code);hint=page.locator('[data-vitalref-hint]')
  if code=='weight':expect(hint).not_to_be_visible()
  else:expect(hint).to_be_visible()
  if code in ['heart_rate','blood_pressure','oxygen_saturation','pain']:assert page.locator('[data-m7-measurement-value]').input_value()=='' and page.locator('[data-m7-measurement-systolic]').input_value()=='' and page.locator('[data-m7-measurement-source]').input_value()==''
  page.screenshot(path=str(OUT/f'{letter}-adult-{code}-webkit.png'))
 choose(page,'heart_rate');hint=page.locator('[data-vitalref-hint]');hint.hover();tip=page.locator('[data-vitalref-tooltip]');expect(tip).to_be_visible();expect(tip).to_contain_text('MedlinePlus — Vital signs');expect(tip).to_contain_text('review-2025-01-01');expect(tip).to_contain_text('Contexto:');page.screenshot(path=str(OUT/'A-source-tooltip-hover-webkit.png'))
 hint.press('Escape');expect(tip).not_to_be_visible();page.locator('[data-m7-measurement-source]').focus();hint.focus();expect(tip).to_be_visible();hint.press('Escape');expect(tip).not_to_be_visible()
 after=page.request.get(BASE+'/api/clinical/index.php/encounters/enc%3A1016').json();assert hashlib.sha256(json.dumps(after['data']['observations'],sort_keys=True).encode()).hexdigest()==before_hash
 report['canonical_observations_unchanged']=True;report['source_tooltip_hover_focus_escape']='PASS';page.close()
 for context in ['child','adolescent','infant','missing']:
  page=ready(context);hint=page.locator('[data-vitalref-hint]')
  text=hint.inner_text()
  if context=='child':assert text=='Referencia pediátrica: requiere edad, sexo y talla.';page.screenshot(path=str(OUT/'F-child-bp-incomplete-webkit.png'))
  if context=='adolescent':assert text=='Referencia adolescente: normal <120/<80 mmHg';hint.focus();expect(page.locator('[data-vitalref-tooltip]')).to_contain_text('AAP');hint.press('Escape')
  if context in ['infant','missing']:assert '120' not in text
  m=page.evaluate(METRICS);assert not m['pageScroll'] and not m['horizontalOverflow'] and not m['hintOverflow'] and m['hintInWriter'],m
  report[context]=text;page.close()
 assert not errors and not writes,(errors,writes)
 report['javascript_errors']=errors;report['clinical_writes']=writes;report['result']='PASS'
 (OUT/'browser-report.json').write_text(json.dumps(report,indent=2));browser.close()
 print('VITALREF01_BROWSER_GATE=PASS',flush=True)
