"""Safe transition-save UX in WebKit, using the disposable canonical Plan API.

STEP3_QA_BROWSER points here through step3_head_neck_disposable_gate.sh.
Director data supplies presentation only; no Director clinical write is allowed.
"""
import hashlib, json, os, subprocess
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, expect

BASE, DB = os.environ['STEP3_QA_BASE'], os.environ['STEP3_QA_DB']
assert DB.startswith('head_neck_qa_') and len(DB) == 25
OUT = Path(os.environ.get('SAVE_UX_R6_ARTIFACTS', '/tmp/mxmed-save-ux-r6'))
OUT.mkdir(parents=True, exist_ok=True)
REVIEW = 'http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=plan&review_placeholders=clean'
SAMPLE = 'Solicitar estudios de control y revisar los resultados en la próxima consulta.'
def sql(q): return subprocess.check_output(['mysql','-N',DB,'-e',q], text=True).strip()
def narrative(): return sql("SELECT narrative_text FROM clinical_encounter_sections WHERE section_type='plan'")
def snapshot(): return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 ORDER BY section_type; SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
before = snapshot(); checks = {}; writes = []; unexpected = []; errors = []; dialogs = []; audit = {}
pending = []; hold = False; fail = False
def check(name, condition=True):
    assert condition, name
    checks[name] = 'PASS'; print('PASS ' + name, flush=True)

with sync_playwright() as pw:
    browser = pw.webkit.launch()
    api = pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    def send(route):
        r = route.request
        result = api.fetch(BASE + urlsplit(r.url).path, method=r.method, data=r.post_data,
                           headers={'Accept':'application/json','Content-Type':'application/json'})
        route.fulfill(response=result)
    def proxy(route):
        r = route.request; path = urlsplit(r.url).path
        if r.method not in ['GET','HEAD']:
            assert r.method == 'PUT' and path.endswith('/sections/plan'), path
            writes.append(r.post_data_json)
            if hold:
                pending.append(route); return
            if fail:
                route.fulfill(status=503, json={'ok':False,'error':{'code':'M6_WRITE_WINDOW_BLOCKED'}}); return
        send(route)
    def guard(route):
        r = route.request
        if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):
            unexpected.append(r.url); route.abort()
        else: route.continue_()
    def ready(width=1440,height=900):
        ctx = browser.new_context(viewport={'width':width,'height':height}, timezone_id='America/Mexico_City')
        page = ctx.new_page(); page.on('pageerror', lambda e: errors.append(str(e)))
        page.on('dialog',lambda d:(dialogs.append(d.message),d.dismiss()))
        page.route('**/api/clinical/**',guard)
        page.route('**/api/clinical/index.php/encounters/**',proxy)
        page.goto(REVIEW,wait_until='commit')
        expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true',timeout=55000)
        return ctx,page,page.locator('[data-m7-editor-text]')
    def selected(page,step): expect(page.locator(f'[data-m7-section="{step}"]')).to_have_attribute('aria-current','true')
    def routine_ui_removed(page):
        expect(page.get_by_role('button',name='Guardar ahora',exact=True)).to_have_count(0)
        expect(page.locator('#m7-workspace').get_by_text('Cambios sin guardar',exact=True)).to_have_count(0)

    ctx,page,editor = ready(); expect(editor).to_have_value(''); expect(editor).to_have_attribute('placeholder',SAMPLE)
    check('R2 placeholder stays guidance with empty actual value',editor.evaluate('n=>n.matches(":placeholder-shown")'))
    # All three clean navigation routes must skip the writer entirely.
    for selector,target in [('[data-vis04-next]','documents'),('[data-vis04-prev]','assessment'),('[data-m7-section="reason"]','reason')]:
        page.locator('[data-m7-section="plan"]').click(); selected(page,'plan')
        page.locator(selector).click(); selected(page,target)
    clean_count = len(writes)
    check('clean Next Previous and stepper navigation have zero writes',clean_count==0)
    page.locator('[data-m7-section="plan"]').click(); editor.focus()
    routine_ui_removed(page)
    check('clean placeholder/focus creates no narrative draft',page.evaluate('sessionStorage.getItem("mxmed.m7.ws02.draft:enc:1016:plan")') is None)

    # Hold each real API command to prove the destination cannot appear early.
    for label,selector,target in [('Next','[data-vis04-next]','documents'),('Previous','[data-vis04-prev]','assessment'),('Stepper','[data-m7-section="reason"]','reason')]:
        page.locator('[data-m7-section="plan"]').click(); selected(page,'plan')
        text='Plan clínico sintético R6 — '+label+'.'; old=narrative(); count=len(writes)
        editor.fill(text); routine_ui_removed(page); expect(page.locator('[data-m7-editor-state]')).to_be_hidden()
        hold=True; page.locator(selector).click()
        expect(editor).to_have_attribute('readonly','')
        expect(page.locator('[data-m7-editor-state]')).to_have_text('Guardando…')
        check(label+' invokes exactly one existing writer while still on Plan',len(writes)==count+1 and len(pending)==1 and page.locator('[data-m7-section="plan"]').get_attribute('aria-current')=='true' and narrative()==old)
        page.locator(selector).evaluate('n=>n.click()')
        check(label+' cannot duplicate a pending save or navigate early',len(writes)==count+1 and len(pending)==1)
        held=pending.pop(); hold=False; send(held); selected(page,target)
        check(label+' navigates only after canonical success',narrative()==text and writes[-1]['narrative_text']==text and writes[-1]['payload_schema_version']==1 and writes[-1]['payload']=={})
        page.reload(wait_until='commit'); selected(page,'plan'); expect(editor).to_have_value(text)
        check(label+' saved text survives reload',not editor.evaluate('n=>n.matches(":placeholder-shown")'))

    # Existing failure feedback remains available; retry is another transition.
    text='Plan clínico sintético R6 pendiente de reintento.'; old=narrative(); editor.fill(text); fail=True
    count=len(writes); page.locator('[data-vis04-next]').click()
    expect(page.locator('[data-m7-editor-state]')).to_have_text('Guardado temporalmente pausado; tu borrador se conserva.')
    selected(page,'plan'); expect(editor).to_have_value(text); expect(page.locator('[data-m7-editor-state]')).to_be_visible(); routine_ui_removed(page)
    check('failed save blocks navigation and preserves text/canonical baseline',len(writes)==count+1 and narrative()==old)
    check('failed save preserves recoverable draft',page.evaluate('sessionStorage.getItem("mxmed.m7.ws02.draft:enc:1016:plan")')==text)
    for selector in ['[data-vis04-prev]','[data-m7-section="reason"]']:
        count=len(writes); page.locator(selector).click(); selected(page,'plan')
        expect(editor).to_have_value(text); routine_ui_removed(page)
        check('failed '+selector+' also blocks navigation',len(writes)==count+1 and narrative()==old)
    fail=False; count=len(writes); page.locator('[data-vis04-next]').click(); selected(page,'documents')
    check('retry through transition uses existing writer and succeeds',len(writes)==count+1 and narrative()==text)

    # A prepared domain action must survive narrative autosave without being executed.
    page.locator('[data-m7-section="plan"]').click()
    for kind in ['orders','prescription','appointment','followup']:
        page.locator(f'[data-plan02b] [data-ns="{kind}"]').click(); expect(page.locator('.plan02b-modal')).to_be_visible()
        page.locator('.plan02b-modal [data-modal-cancel]').click()
    check('all four Plan preparation flows remain available without domain writes',not unexpected)
    page.locator('[data-plan02b] [data-ns="orders"]').click()
    page.locator('[data-order-field="title"]').fill('Orden sintética R6 preparada, sin confirmar')
    page.locator('[data-order-field="summary"]').fill('Indicación sintética')
    page.locator('[data-modal-add]').click()
    text='Plan R6 con una orden preparada.'; editor.fill(text); count=len(writes)
    page.locator('[data-vis04-next]').click(); selected(page,'documents')
    expect(page.locator('[data-plan02b-collector] [data-ns="confirm"]')).to_be_visible()
    expect(page.locator('[data-plan02b-collector]')).to_contain_text('Orden sintética R6 preparada, sin confirmar')
    check('narrative transition preserves prepared action and Step 6 Confirmar acciones',len(writes)==count+1 and not unexpected and sql('SELECT COUNT(*) FROM clinical_documents')=='0')
    # Clear only the unconfirmed browser preparation before entering other steps.
    page.locator('[data-plan02b-collector] [data-remove]').click()
    page.locator('[data-m7-section="finalize"]').click(); selected(page,'finalize')
    expect(page.locator('[data-m7-finalize]' )).to_be_visible()
    check('Step 7 Finalizar consulta and terminal controls remain',page.locator('[data-m7-void-form]').count()==1 and page.locator('[data-m7-amendment-form]').count()==1)
    ctx.close()

    # Responsive routine UI audit, including dirty narratives and examination.
    for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
        ctx,page,editor=ready(w,h); audit[f'{w}x{h}']={}
        for step in ['reason','measurements','exam','assessment','plan','documents','finalize']:
            page.locator(f'[data-m7-section="{step}"]').click(); selected(page,step); routine_ui_removed(page)
            labels=page.locator('#m7-workspace button:visible').all_text_contents()
            audit[f'{w}x{h}'][step]=[label.strip() for label in labels if 'Guardar' in label or 'Agregar a esta consulta' in label or 'Finalizar consulta' in label or 'Confirmar acciones' in label]
            if step in ['reason','assessment','plan']:
                original=editor.input_value(); editor.fill('Texto clínico sintético para auditoría R6.'); routine_ui_removed(page)
                expect(page.locator('[data-m7-editor-state]')).to_be_hidden()
                check(f'{w}x{h} {step} dirty state stays internal and generic row collapses',page.locator('.vis28-save-utility').evaluate('n=>getComputedStyle(n).display')=='none')
                editor.fill(original)
            elif step=='exam':
                row=page.locator('[data-m7-exam-system="head_neck"]'); row.locator('select').select_option('ABNORMAL'); row.locator('input').fill('Hallazgo sintético sin persistir.')
                routine_ui_removed(page); expect(page.locator('[data-m7-exam-state]')).to_have_text('')
                row.locator('select').select_option('NOT_REVIEWED')
                expect(page.get_by_role('button',name='Guardar exploración',exact=True)).to_have_count(0)
            elif step=='measurements':
                expect(page.locator('[data-m7-measurement-save]')).to_have_text('Agregar a esta consulta')
        check(f'{w}x{h} no horizontal overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
        ctx.close()
    api.dispose(); browser.close()

check('no extra confirmation or browser JavaScript errors',not dialogs and not errors)
check('no unexpected domain writes and unchanged Director canonical records',not unexpected and snapshot()==before)
(OUT/'canonical-browser-report.json').write_text(json.dumps({'qa':'PASS','checks':checks,'clean_plan_write_count':clean_count,'plan_put_count':len(writes),'generic_save_ui_audit':audit,'director_records_unchanged':True,'dialogs':dialogs,'javascript_errors':errors,'unexpected_writes':unexpected},indent=2,ensure_ascii=False)+'\n')
print('CONSULTATION_SAVE_UX_R6_CANONICAL_BROWSER_QA=PASS',flush=True)
