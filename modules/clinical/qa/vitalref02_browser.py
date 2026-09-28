"""Director WebKit placeholder, tooltip, geometry, no-write and reuse evidence."""
import hashlib,json,os,subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright,expect
ROOT=Path(__file__).resolve().parents[3]
OUT=Path(os.environ.get('VITALREF02_ARTIFACTS','/tmp/mxmed-vitalref02'));OUT.mkdir(parents=True,exist_ok=True)
BASE=os.environ.get('VITALREF02_REVIEW_BASE','http://127.0.0.1:18148')
URL=BASE+'/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_vitalref=adult'
START='ec76ffe67925d7751117c8b4380f0242dedc37a3'
NAMES={'blood_pressure':'Presión arterial','heart_rate':'Frecuencia cardíaca','respiratory_rate':'Frecuencia respiratoria','temperature':'Temperatura','oxygen_saturation':'Saturación de oxígeno','pain':'Dolor','weight':'Peso','height':'Estatura','waist':'Cintura'}
PLACEHOLDERS={'blood_pressure':['','Ref. <120','Ref. <80'],'heart_rate':['Ref. 60–100','',''],'respiratory_rate':['Ref. 12–18','',''],'temperature':['Ref. 36.5–37.3','',''],'oxygen_saturation':['Ref. 95–100','',''],'pain':['Escala 0–10','',''],'weight':['','',''],'height':['','',''],'waist':['','','']}
METRICS=r'''()=>{
 const rect=s=>{const r=document.querySelector(s).getBoundingClientRect();return [r.x,r.y+scrollY,r.width,r.height]};
 const hint=document.querySelector('[data-vitalref-hint]'),form=document.querySelector('.vis29-register'),inputs=['value','systolic','diastolic'].map(k=>document.querySelector('[data-m7-measurement-'+k+']'));
 const visibleFields=[...form.querySelectorAll('input,select,[data-m7-measurement-save]')].filter(n=>n.getBoundingClientRect().height>0);
 return {header:rect('#p-expediente>.head>.exp-hdr'),consultation:rect('.m7-workspace-head'),stepper:rect('.m7-workspace-sections'),footer:rect('.vis04-progression'),writer:rect('.vis29-register'),pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,
 placeholders:inputs.map(n=>n.placeholder),values:inputs.map(n=>n.value),placeholderStyle:getComputedStyle(inputs[0],'::placeholder').color,placeholderOpacity:getComputedStyle(inputs[0],'::placeholder').opacity,inputColor:getComputedStyle(inputs[0]).color,
 infoInLabel:hint.hidden||hint.parentElement.matches('[data-vitalref-measurement-label]'),infoSize:[hint.offsetWidth,hint.offsetHeight],fieldsFit:visibleFields.every(n=>{const r=n.getBoundingClientRect(),f=form.getBoundingClientRect();return r.left>=f.left&&r.right<=f.right+1}),fieldBottoms:visibleFields.map(n=>n.getBoundingClientRect().bottom)};
}'''
errors=[];writes=[];report={'START_SOURCE_HEAD':START,'checks':{},'positions':{},'writerHeightDeltas':{}}
def check(name,condition=True):
    assert condition,name
    report['checks'][name]='PASS';print('PASS '+name,flush=True)
def snapshot():return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
def settle(page):page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
with sync_playwright() as pw:
    browser=pw.webkit.launch()
    def ready(context='adult',w=1440,h=900,baseline=False,synthetic=False):
        page=browser.new_page(viewport={'width':w,'height':h},has_touch=w==390)
        page.on('pageerror',lambda e:errors.append(str(e)))
        page.on('request',lambda r:writes.append(r.url) if '/api/clinical/' in r.url and r.method not in ['GET','HEAD'] else None)
        if baseline:
            for path in ['assets/js/clinical/m7-ws03.js','assets/css/expediente-paciente-visual-normalization.css']:
                content=subprocess.check_output(['git','show',START+':'+path],cwd=ROOT)
                page.route('**/'+path+'*',lambda route,request,content=content,mime='text/javascript' if path.endswith('.js') else 'text/css':route.fulfill(body=content,content_type=mime))
            old_html=subprocess.check_output(['git','show',START+':index.html'],cwd=ROOT,text=True)
            def html(route):
                import re
                runtime=route.fetch().text();boot=re.search(r"<script>\nwindow.addEventListener\('load', \(\) => \{.*?</script>",runtime,re.S).group()
                inject='<style>#mxmed_dev_role_switcher{display:none!important}</style><script src="/__director_vitalref01_review.js?v=1"></script>'
                route.fulfill(body=old_html.replace('</head>',inject+'</head>').replace('</body>',boot+'</body>'),content_type='text/html')
            page.route('**/index.html?*',html)
        page.goto(URL.replace('review_vitalref=adult','review_vitalref='+context)+('&review_step2_visual=r4&review_current_values=0' if synthetic else ''),wait_until='commit')
        expect(page.locator('[data-m7-step-title]')).to_have_text('Signos vitales y otros valores clínicos',timeout=55000)
        expect(page.locator('[data-vitalref-hint]')).to_be_visible()
        settle(page);return page
    def choose(page,code):
        cancel=page.locator('[data-m7-measurement-new]')
        if cancel.is_visible():cancel.click()
        selector=page.locator('[data-m7-measurement-code]')
        if selector.locator(f'option[value={code}]').count():selector.select_option(code)
        else:page.get_by_role('button',name='Editar: '+NAMES[code],exact=False).click()
        settle(page)
    before_db=snapshot()
    baseline_path=OUT/'baseline.json';baseline=json.loads(baseline_path.read_text()) if baseline_path.exists() else {}
    for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
        size=f'{w}x{h}'
        if size not in baseline:
            old=ready(w=w,h=h,baseline=True);baseline[size]={}
            for code in NAMES:
                choose(old,code);baseline[size][code]=old.evaluate(METRICS)
            old.close()
        page=ready(w=w,h=h);report['positions'][size]={};report['writerHeightDeltas'][size]={}
        for code in NAMES:
            choose(page,code);m=page.evaluate(METRICS);old=baseline[size][code]
            check(size+' '+code+' exact placeholders',m['placeholders']==PLACEHOLDERS[code])
            check(size+' '+code+' frozen shell anchors',all(m[k]==old[k] for k in ['header','consultation','stepper','footer']))
            check(size+' '+code+' inputs and info fit',not m['horizontalOverflow'] and m['fieldsFit'] and m['infoInLabel'] and m['infoSize'][1]<=14)
            delta=-1 if w>=1200 else -2
            check(size+' '+code+' compact strip without footer displacement',abs(m['writer'][3]-old['writer'][3]-delta)<.125)
            if w>=1200:
                check(size+' '+code+' no page scroll',not m['pageScroll'])
                if code not in ['temperature','weight','height','waist']:check(size+' '+code+' desktop single-line capture',max(m['fieldBottoms'])-min(m['fieldBottoms'])<=1)
            check(size+' '+code+' subdued placeholder style',m['placeholderStyle']!=m['inputColor'] and m['placeholderOpacity']=='1')
            report['positions'][size][code]=m;report['writerHeightDeltas'][size][code]=m['writer'][3]-old['writer'][3]
            if code in ['blood_pressure','heart_rate','temperature','pain','weight']:
                page.screenshot(path=str(OUT/f'{code}-{size}-webkit.png'),full_page=w<1200)
            if code=='blood_pressure':
                hint=page.locator('[data-vitalref-hint]');hint.focus();tip=page.locator('[data-vitalref-tooltip]');expect(tip).to_be_visible();expect(tip).to_contain_text('2025 AHA/ACC');expect(tip).to_contain_text('10.1161/CIR.0000000000001356')
                r=tip.bounding_box();check(size+' tooltip fits',r['x']>=0 and r['x']+r['width']<=w+1 and r['height']<=h-16)
                hint.press('Escape');expect(tip).not_to_be_visible()
        page.close()
    # Real adult empty-field requestSubmit cannot send a reference as an observation.
    page=ready(synthetic=True);expect(page.locator('.vis-step2-chip')).to_have_count(0)
    for code in NAMES:
        choose(page,code)
        check(code+' initially empty in adult capture',page.evaluate("()=>['value','systolic','diastolic'].every(k=>document.querySelector('[data-m7-measurement-'+k+']').value==='')"))
        page.locator('[data-m7-measurements-form]').evaluate('(n)=>n.requestSubmit()')
        expect(page.locator('.vis-step2-chip')).to_have_count(0)
        check(code+' placeholder creates no draft',page.evaluate("()=>!Object.keys(sessionStorage).some(k=>k.startsWith('mxmed.m7.ws03.draft:'))"))
    page.close()
    # Genuine registry pediatric/incomplete contexts stay in source metadata.
    for context in ['child','adolescent','infant','missing']:
        page=ready(context);m=page.evaluate(METRICS)
        wanted=['','Ref. <120','Ref. <80'] if context=='adolescent' else ['','','']
        check(context+' BP placeholder context',m['placeholders']==wanted)
        page.locator('[data-vitalref-hint]').focus();tip=page.locator('[data-vitalref-tooltip]');expect(tip).to_be_visible()
        if context=='child':expect(tip).to_contain_text('requiere edad, sexo y talla')
        if context=='adolescent':expect(tip).to_contain_text('AAP')
        check(context+' metadata never becomes value',all(v=='' for v in m['values']))
        page.screenshot(path=str(OUT/f'{context}-bp-webkit.png'));page.close()
    check('no clinical write reaches network',not writes)
    check('Director observations unchanged',snapshot()==before_db)
    check('no JavaScript errors',not errors)
    report['canonical_observations_sha256']=before_db;report['javascript_errors']=errors;report['clinical_writes']=writes
    (OUT/'browser-report.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));browser.close()
    print('VITALREF02_BROWSER_GATE=PASS',flush=True)
