"""WebKit shell geometry and browser-only Director void, against the accepted source."""
import hashlib
import json
import os
import re
import subprocess
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[3]
OUT = Path(os.environ.get('SHELL_R4_ARTIFACTS', '/tmp/mxmed-consultation-shell-r4'))
OUT.mkdir(parents=True, exist_ok=True)
START = '51a9c74413a1ebd2a67a46f03299259270c12b9f'
BASE = os.environ.get('SHELL_R4_REVIEW_BASE', 'http://127.0.0.1:18148')
URL = BASE + '/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step2_visual=r4&review_current_values=8'
STEPS = ['reason', 'measurements', 'exam', 'assessment', 'plan', 'documents', 'finalize']
SIZES = [(1440, 900), (1440, 880), (1440, 768), (1366, 768)]
METRICS = r'''() => {
 const rect = s => {const r=document.querySelector(s).getBoundingClientRect(); return [r.x,r.y+scrollY,r.width,r.height]};
 const css = s => getComputedStyle(document.querySelector(s));
 const circle = document.querySelector('.vis04-step-number').getBoundingClientRect();
 const capture=document.querySelector('.vis04-capture');
 return {patient:rect('#p-expediente>.head>.exp-hdr'),header:rect('.m7-workspace-head'),badge:rect('#m7-workspace-title'),appointment:rect('.vis23-appointment-meta'),origin:rect('[data-m7-origin-cluster]'),exit:rect('[data-m7-exit]'),stepper:rect('.m7-workspace-sections'),centerline:circle.y+scrollY+circle.height/2,title:rect('[data-m7-step-title]'),intro:rect('.m7-step-intro'),capture:rect('.vis04-capture'),footer:rect('.vis04-progression'),footerBaseline:document.querySelector('.vis04-progression').getBoundingClientRect().bottom+scrollY,pageScroll:document.documentElement.scrollHeight>innerHeight+1,horizontalOverflow:document.documentElement.scrollWidth>innerWidth+1,bodyGap:css('.m7-workspace-body').marginTop,stepperGap:css('.m7-workspace-sections').marginBottom,titleFont:css('[data-m7-step-title]').fontSize,circle:[circle.width,circle.height,css('.vis04-step-number').fontSize,css('.vis04-step-number').borderTopWidth],connector:[css('.m7-workspace-sections').width,getComputedStyle(document.querySelector('.m7-workspace-sections'),'::before').height],captureScroll:capture.scrollHeight>capture.clientHeight+1,
 chipsFit:[...document.querySelectorAll('.vis-step2-chip')].every(n=>n.scrollWidth<=n.clientWidth+1),
 fieldsAligned:(()=>{const nodes=[...document.querySelectorAll('.vis29-register input,.vis29-register select,.vis29-register [data-m7-measurement-save]')].filter(n=>n.getBoundingClientRect().height>0);const bottoms=nodes.map(n=>n.getBoundingClientRect().bottom);return Math.max(...bottoms)-Math.min(...bottoms)<=1})()};
}'''
report = {'START_SOURCE_HEAD': START, 'checks': {}, 'positions': {}, 'deltas': {}}
errors = []
def check(name, value=True):
    assert value, name
    report['checks'][name] = 'PASS'
    print('PASS ' + name, flush=True)
def settle(page):
    page.evaluate('async()=>{await document.fonts.ready;document.activeElement?.blur();scrollTo(0,0);await new Promise(r=>requestAnimationFrame(()=>requestAnimationFrame(r)));}')
def near(a,b): return abs(a-b)<=.125

def db_snapshot():
    data = subprocess.check_output(['mysql', '-N', 'mxmed_director_review_lon07c', '-e', 'SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])
    return hashlib.sha256(data).hexdigest()

with sync_playwright() as pw:
    browser = pw.webkit.launch()
    def ready(w=1440,h=900,baseline=False):
        page = browser.new_page(viewport={'width':w,'height':h},has_touch=w==390)
        page.on('pageerror',lambda e: errors.append(str(e)))
        if baseline:
            old_css = subprocess.check_output(['git','show',START+':assets/css/expediente-paciente-visual-normalization.css'],cwd=ROOT)
            old_html = subprocess.check_output(['git','show',START+':index.html'],cwd=ROOT,text=True)
            page.route('**/assets/css/expediente-paciente-visual-normalization.css*',lambda r:r.fulfill(body=old_css,content_type='text/css'))
            def old_index(route):
                runtime = route.fetch().text()
                boot = re.search(r"<script>\nwindow.addEventListener\('load', \(\) => \{.*?</script>",runtime,re.S).group()
                inject = '<style>#mxmed_dev_role_switcher{display:none!important}</style><script src="/__director_review_step2_r4.js"></script>'
                route.fulfill(body=old_html.replace('</head>',inject+'</head>').replace('</body>',boot+'</body>'),content_type='text/html')
            page.route('**/index.html?*',old_index)
        page.goto(URL,wait_until='commit')
        expect(page.locator('.vis-step2-chip')).to_have_count(8,timeout=55000)
        settle(page)
        return page

    # Saved pre-edit measurements are preferred; otherwise reproduce the exact accepted source.
    baseline_path = OUT/'baseline.json'
    baseline = json.loads(baseline_path.read_text()) if baseline_path.exists() else {}
    for w,h in SIZES:
        size = f'{w}x{h}'
        if size not in baseline:
            old = ready(w,h,baseline=True)
            baseline[size] = {}
            for step in STEPS:
                old.locator(f'[data-m7-section="{step}"]').click()
                settle(old)
                baseline[size][step] = old.evaluate(METRICS)
            old.close()
        page = ready(w,h)
        report['positions'][size] = {}
        report['deltas'][size] = {}
        ref = baseline[size]['reason']
        for i,step in enumerate(STEPS):
            page.locator(f'[data-m7-section="{step}"]').click()
            expect(page.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
            settle(page)
            m = page.evaluate(METRICS)
            report['positions'][size][step] = m
            old = baseline[size][step]
            for key in ['patient','badge','appointment','origin','exit','header','footer']:
                check(f'{size} {step} canonical {key} anchor',m[key]==ref[key])
            check(f'{size} {step} physical stepper and title size',m['circle']==old['circle'] and m['connector']==old['connector'] and m['titleFont']==old['titleFont'] and m['stepper'][2:]==old['stepper'][2:])
            delta = -10 if h<=800 else -12
            check(f'{size} {step} shared vertical compaction',near(m['centerline']-ref['centerline'],delta) and near(m['title'][1]-ref['title'][1],2*delta))
            report['deltas'][size][step] = {'stepper':m['centerline']-ref['centerline'],'title':m['title'][1]-ref['title'][1],'footer':m['footerBaseline']-ref['footerBaseline']}
            check(f'{size} {step} no page/horizontal overflow',not m['pageScroll'] and not m['horizontalOverflow'])
            if step=='measurements':
                check(f'{size} Step 2 full-width capture, chips and reference fit',m['chipsFit'] and m['fieldsAligned'] and not m['captureScroll'])
                expect(page.locator('[data-vitalref-hint]')).not_to_be_visible()  # Cintura has no generic numeric reference.
            if (w,h)==(1440,900):page.screenshot(path=str(OUT/f'after-step-{i+1}-webkit.png'))
        page.close()
    for size,items in report['positions'].items():
        drift = lambda values:max(values)-min(values)
        report.setdefault('drifts',{})[size] = {
            'upperHeader':drift([m['header'][1] for m in items.values()]),
            'stepper':drift([m['centerline'] for m in items.values()]),
            'title':drift([m['title'][1] for m in items.values()]),
            'footer':drift([m['footerBaseline'] for m in items.values()])}
        check(size+' all seven shared anchors within 2px',all(n<=2 for n in report['drifts'][size].values()))

    before_db = db_snapshot()
    page = ready()
    writes = []
    page.on('request',lambda r:writes.append({'url':r.url,'method':r.method}) if '/api/clinical/' in r.url and r.method not in ['GET','HEAD'] else None)
    page.get_by_role('button',name='Editar: Frecuencia cardíaca',exact=False).click()
    hint = page.locator('[data-vitalref-hint]')
    expect(hint).to_be_visible()
    hint.focus()
    expect(page.locator('[data-vitalref-tooltip]')).to_be_visible()
    expect(page.locator('[data-vitalref-tooltip]')).to_contain_text('MedlinePlus')
    hint.press('Escape')
    check('VITALREF01 fits within the writer without changing input',page.locator('[data-m7-measurement-value]').input_value()=='72' and hint.evaluate('(n)=>{const r=n.getBoundingClientRect(),p=n.closest(".vis29-register").getBoundingClientRect();return r.left>=p.left&&r.right<=p.right&&r.top>=p.top&&r.bottom<=p.bottom}') )
    page.screenshot(path=str(OUT/'reference-hint-webkit.png'))
    page.locator('[data-m7-measurement-new]').click()
    settle(page)
    before = page.evaluate(METRICS)
    request_url = '/api/clinical/index.php/encounters/enc%3A1016/observations/99000/void'
    # Fixture enforces scope/version without sending invented IDs to the server.
    statuses = page.evaluate('''async url=>{let out=[];for(const body of [{patient_id:'p_other',row_version:1},{patient_id:'p_plan02ux_review',row_version:2}])out.push((await fetch(url,{method:'POST',body:JSON.stringify(body)})).status);out.push((await fetch(url.replace('99000','777777'),{method:'POST',body:JSON.stringify({patient_id:'p_plan02ux_review',row_version:1})})).status);return out;}''',request_url)
    check('synthetic scope/version/unknown ID are rejected',statuses==[409,409,404])
    chip = page.locator('.vis-step2-chip').filter(has_text='Presión arterial')
    dialog = page.locator('[data-meas01-confirm]')
    chip.get_by_role('button',name='Eliminar:',exact=False).click()
    expect(dialog).to_be_visible()
    dialog.get_by_role('button',name='Cancelar',exact=True).click()
    expect(page.locator('.vis-step2-chip')).to_have_count(8)
    check('cancel keeps review chip')
    page.evaluate('''()=>{const base=window.fetch;let fail=true;window.fetch=async(input,init)=>{if(String(input).endsWith('/99000/void')&&fail){fail=false;return new Response(JSON.stringify({ok:false,error:{code:'M6_WRITE_WINDOW_BLOCKED'}}),{status:503});}return base(input,init);};}''')
    chip.get_by_role('button',name='Eliminar:',exact=False).click()
    dialog.get_by_role('button',name='Eliminar',exact=True).click()
    expect(page.locator('[data-m7-measurements-state]')).to_contain_text('No se confirmó la eliminación.')
    expect(chip).to_be_visible()
    check('failed command keeps chip and selected-type filtering',page.locator('[data-m7-measurement-code] option[value=blood_pressure]').count()==0)
    chip.get_by_role('button',name='Eliminar:',exact=False).click()
    dialog.get_by_role('button',name='Eliminar',exact=True).click()
    expect(dialog).not_to_be_visible()
    expect(page.locator('.vis-step2-chip')).to_have_count(7)
    expect(page.locator('[data-m7-measurement-code] option[value=blood_pressure]')).to_have_count(1)
    expect(page.locator('[data-m7-measurements-state]')).to_have_text('Medición eliminada')
    detail = page.evaluate("async()=> (await (await fetch('/api/clinical/index.php/encounters/enc%3A1016')).json()).data")
    voided = next(r for r in detail['invalidated_observations'] if r['observation_id']==99000)
    check('synthetic audit/readback preserves other current rows',bool(voided['invalidated_at']) and voided['row_version']==2 and sum(not r['invalidated_at'] for r in detail['observations'])==7)
    settle(page)
    after = page.evaluate(METRICS)
    check('remove preserves shared shell geometry',all(before[k]==after[k] for k in ['patient','header','stepper','title','footer']))
    page.screenshot(path=str(OUT/'synthetic-void-success-webkit.png'))
    page.reload(wait_until='commit')
    expect(page.locator('.vis-step2-chip')).to_have_count(7,timeout=55000)
    expect(page.locator('[data-m7-measurement-code] option[value=blood_pressure]')).to_have_count(1)
    check('browser-session readback keeps void after reload')
    page.locator('[data-vis29-prior-open]:visible').click()
    prior = page.locator('[data-vis29-prior-dialog] .vis-step2-prior-chip').filter(has_text='Presión arterial')
    expect(prior.get_by_role('button')).to_be_enabled()
    prior.get_by_role('button').click()
    expect(page.locator('.vis-step2-chip')).to_have_count(8)
    expect(prior.get_by_role('button')).to_be_disabled()
    page.locator('[data-vis29-prior-close]').last.click()
    settle(page)
    reused = page.evaluate(METRICS)
    check('voided type reusable without shell movement',all(after[k]==reused[k] for k in ['header','stepper','title','footer']))
    # Also exercise void of a browser-reused row, not only the original example.
    chip.get_by_role('button',name='Eliminar:',exact=False).click()
    dialog.get_by_role('button',name='Eliminar',exact=True).click()
    expect(page.locator('.vis-step2-chip')).to_have_count(7)
    page.reload(wait_until='commit')
    expect(page.locator('.vis-step2-chip')).to_have_count(7,timeout=55000)
    check('reused synthetic row can be voided and read back')
    check('no invented clinical write reaches network',not writes)
    check('Director canonical observation data unchanged',before_db==db_snapshot())
    report['DIRECTOR_SYNTHETIC_VOID_QA'] = 'PASS'
    report['DIRECTOR_SYNTHETIC_VOID_PERSISTED'] = False
    report['canonical_observations_sha256'] = before_db
    page.close()
    # Width responsiveness keeps the accepted Step 2 capture and modal functional.
    for w,h in [(820,1180),(390,844)]:
        page = ready(w,h)
        m = page.evaluate(METRICS)
        check(f'{w}x{h} Step 2 chips have no horizontal overflow',not m['horizontalOverflow'] and m['chipsFit'])
        page.locator('[data-vis29-prior-open]:visible').click()
        modal=page.locator('[data-vis29-prior-dialog]')
        expect(modal).to_be_visible()
        check(f'{w}x{h} previous-values dialog fits',modal.bounding_box()['height']<=h-30 and modal.evaluate('(n)=>n.scrollWidth<=n.clientWidth'))
        page.screenshot(path=str(OUT/f'prior-modal-{w}x{h}-webkit.png'))
        page.close()
    check('no browser JavaScript errors',not errors)
    browser.close()
report['javascript_errors'] = errors
(OUT/'shell-browser-report.json').write_text(json.dumps(report,indent=2,ensure_ascii=False)+'\n')
print('CONSULTATION_SHELL_R4_BROWSER_GATE=PASS',flush=True)
