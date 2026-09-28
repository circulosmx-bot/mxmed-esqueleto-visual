"""WebKit semantic gates. Canonical Plan writes use only the disposable QA DB.

Run with STEP3_QA_BROWSER pointing here through step3_head_neck_disposable_gate.sh.
Director encounter records are hashed before/after and never written.
"""
import hashlib, json, os, subprocess
from pathlib import Path
from urllib.parse import urlsplit
from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[3]
BASE, DB = os.environ['STEP3_QA_BASE'], os.environ['STEP3_QA_DB']
assert DB.startswith('head_neck_qa_') and len(DB) == 25
OUT = Path(os.environ.get('PLACEHOLDER_R2_ARTIFACTS', '/tmp/mxmed-placeholder-r2'))
OUT.mkdir(parents=True, exist_ok=True)
REVIEW = 'http://127.0.0.1:18148/index.html?review_patient=plan02ux&review_encounter=open&qa_tools=hide&review_step=plan&review_placeholders=clean'
SAMPLE = 'Solicitar estudios de control y revisar los resultados en la próxima consulta.'
REAL = 'Solicitar resonancia de rodilla.'
ASSESSMENT = 'Describe tu impresión diagnóstica, evolución y diagnósticos diferenciales relevantes.'
STYLE = "n=>Object.fromEntries(['fontFamily','fontSize','fontWeight','lineHeight','color','opacity'].map(k=>[k,getComputedStyle(n,'::placeholder')[k]]))"
DRAFT = 'mxmed.m7.ws02.draft:enc:1016:plan'
def sql(q): return subprocess.check_output(['mysql', '-N', DB, '-e', q], text=True).strip()
def snapshot(): return hashlib.sha256(subprocess.check_output(['mysql','-N','mxmed_director_review_lon07c','-e','SELECT * FROM clinical_encounter_sections WHERE encounter_id=1016 ORDER BY section_type; SELECT * FROM clinical_observations WHERE encounter_id=1016 ORDER BY observation_id'])).hexdigest()
before = snapshot(); checks = {}; writes = []; errors = []; unexpected = []
def check(name, condition=True):
    assert condition, name
    checks[name] = 'PASS'; print('PASS ' + name, flush=True)

with sync_playwright() as pw:
    browser = pw.webkit.launch()
    api = pw.request.new_context(extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
    def guard(route):
        r = route.request
        if r.method not in ['GET','HEAD'] and not r.url.endswith('/patient-id/resolve'):
            unexpected.append(r.url); route.abort()
        else: route.continue_()
    def proxy(route):
        r = route.request; path = urlsplit(r.url).path
        if r.method not in ['GET','HEAD']:
            assert r.method == 'PUT' and path.endswith('/sections/plan'), path
            writes.append(r.post_data_json)
        result = api.fetch(BASE + path, method=r.method, data=r.post_data,
                           headers={'Accept':'application/json','Content-Type':'application/json'})
        route.fulfill(response=result)
    def ready(width=1440, height=900, draft=None):
        ctx = browser.new_context(viewport={'width':width,'height':height}, timezone_id='America/Mexico_City')
        if draft is not None:
            ctx.add_init_script('sessionStorage.setItem(' + json.dumps(DRAFT) + ',' + json.dumps(draft) + ')')
        page = ctx.new_page(); page.on('pageerror', lambda e: errors.append(str(e)))
        page.route('**/api/clinical/**', guard)
        page.route('**/api/clinical/index.php/encounters/**', proxy)
        page.goto(REVIEW, wait_until='commit')
        expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true', timeout=55000)
        return ctx, page, page.locator('[data-m7-editor-text]')

    for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
        ctx,page,editor = ready(w,h)
        expect(editor).to_have_value(''); expect(editor).to_have_attribute('placeholder',SAMPLE)
        expect(page.locator('[data-m7-editor-state]')).to_have_text('')
        check(f'{w}x{h} clean Plan is true guidance with empty value', editor.evaluate('n=>n.matches(":placeholder-shown")'))
        check(f'{w}x{h} no draft created', page.evaluate('key=>sessionStorage.getItem(key)', DRAFT) is None)
        plan_style = editor.evaluate(STYLE)
        page.locator('[data-m7-section="reason"]').click(); expect(editor).to_have_value('')
        check(f'{w}x{h} Plan matches measured Step 1', editor.evaluate(STYLE) == plan_style)
        page.locator('[data-m7-section="assessment"]').click()
        expect(editor).to_have_value(''); expect(editor).to_have_attribute('placeholder',ASSESSMENT)
        check(f'{w}x{h} assessment matches Step 1', editor.evaluate(STYLE) == plan_style)
        page.locator('[data-m7-section="plan"]').click(); editor.focus()
        check(f'{w}x{h} empty focus leaves placeholder and no dirty state', editor.evaluate('n=>n.matches(":placeholder-shown")') and page.locator('[data-m7-editor-state]').inner_text() == '')
        editor.fill(REAL); expect(page.locator('[data-m7-editor-state]')).to_be_hidden()
        check(f'{w}x{h} typed text hides native placeholder', not editor.evaluate('n=>n.matches(":placeholder-shown")'))
        check(f'{w}x{h} actual text retains darker style', editor.evaluate('n=>getComputedStyle(n).color') != plan_style['color'])
        editor.fill(''); expect(page.locator('[data-m7-editor-state]')).to_have_text('')
        check(f'{w}x{h} clearing restores native placeholder', editor.evaluate('n=>n.matches(":placeholder-shown")'))
        page.locator('[data-vis04-next]').click(); expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true')
        check(f'{w}x{h} clean navigation never writes guidance', not writes)
        page.locator('[data-m7-section="measurements"]').click()
        code = page.locator('[data-m7-measurement-code]')
        for name in code.locator('option:not([disabled])').evaluate_all('ns=>ns.map(n=>n.value)'):
            code.select_option(name)
            for field in page.locator('.vis29-register input[placeholder]:visible').all():
                expect(field).to_have_value('')
                fits = field.evaluate('''n=>{
                  const s=getComputedStyle(n),p=getComputedStyle(n,'::placeholder');
                  const c=document.createElement('canvas').getContext('2d');
                  c.font=p.fontSize+' '+p.fontFamily;
                  const width=n.clientWidth-parseFloat(s.paddingLeft)-parseFloat(s.paddingRight);
                  return c.measureText(n.placeholder).width<=width+1 && parseFloat(p.fontSize)<=n.clientHeight;
                }''')
                assert fits, (w,h,name,field.evaluate('n=>({copy:n.placeholder,width:n.clientWidth,padding:getComputedStyle(n).padding,font:getComputedStyle(n,"::placeholder").fontSize})'))
        check(f'{w}x{h} all VITALREF copies fit without becoming values or writes', not writes)
        check(f'{w}x{h} no horizontal overflow', page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
        ctx.close()
    check('placeholder never creates a canonical section', sql('SELECT COUNT(*) FROM clinical_encounter_sections') == '0')

    ctx,page,editor = ready(draft='Borrador clínico legítimo.')
    expect(page.locator('[data-vis30-section-draft]')).to_be_visible(); expect(editor).to_have_value('')
    page.locator('[data-vis30-section-recover]').click(); expect(editor).to_have_value('Borrador clínico legítimo.')
    check('existing physician draft remains recoverable without writing', not writes and not editor.evaluate('n=>n.matches(":placeholder-shown")'))
    ctx.close()

    ctx,page,editor = ready(); editor.fill(REAL); page.locator('[data-vis04-next]').click()
    expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true',timeout=15000)
    check('real Plan uses canonical writer unchanged', len(writes)==1 and writes[0]['narrative_text']==REAL and writes[0]['payload_schema_version']==1 and writes[0]['payload']=={})
    check('real Plan is stored in disposable canonical DB', sql("SELECT narrative_text FROM clinical_encounter_sections WHERE section_type='plan'")==REAL)
    page.reload(wait_until='commit'); expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true',timeout=55000)
    expect(editor).to_have_value(REAL)
    check('real Plan survives save/reload and never paints guidance over data', not editor.evaluate('n=>n.matches(":placeholder-shown")'))
    # A clinician may genuinely save this wording. It must never be stripped by text alone.
    editor.fill(SAMPLE); page.locator('[data-vis04-next]').click()
    expect(page.locator('[data-m7-section="documents"]')).to_have_attribute('aria-current','true')
    page.reload(wait_until='commit'); expect(page.locator('[data-m7-section="plan"]')).to_have_attribute('aria-current','true',timeout=55000)
    expect(editor).to_have_value(SAMPLE)
    check('genuine saved same-wording narrative remains actual data', sql("SELECT narrative_text FROM clinical_encounter_sections WHERE section_type='plan'")==SAMPLE and not editor.evaluate('n=>n.matches(":placeholder-shown")'))
    ctx.close()

    # Exercise the preview guard with the identified seed and several genuine/unrelated reads.
    seed = {'ok':True,'data':{'patient_id':'p_plan02ux_review','encounter_id':1016,'status':'open','sections':{'plan':{'section_type':'plan','row_version':1,'payload':[], 'narrative_text':SAMPLE,'created_at':'2026-09-25 22:48:46','updated_at':'2026-09-25 22:48:46'}}}}
    ctx = browser.new_context(); page = ctx.new_page()
    page.route('**/__placeholder_guard.html**',lambda r:r.fulfill(body='<html></html>',content_type='text/html'))
    page.goto(REVIEW.replace('index.html','__placeholder_guard.html'))
    page.evaluate('window.__response=null;window.fetch=async()=>new Response(JSON.stringify(window.__response),{status:200,headers:{"Content-Type":"application/json"}})')
    page.add_script_tag(content=(ROOT/'modules/clinical/qa/consultation_placeholder_r2_review.js').read_text())
    def projected(payload, method='GET', path='/api/clinical/index.php/encounters/enc%3A1016'):
        page.evaluate('v=>window.__response=v',payload)
        return page.evaluate('async v=>(await fetch(v.path,{method:v.method})).json()',{'path':path,'method':method})
    result=projected(seed); expected=json.loads(json.dumps(seed)); expected['data']['sections']['plan']['narrative_text']=''
    check('clean preview changes only seed narrative, retaining version metadata',result==expected)
    for key,value in [('row_version',2),('updated_at','2026-09-28 12:00:00'),('narrative_text',REAL)]:
        genuine=json.loads(json.dumps(seed)); genuine['data']['sections']['plan'][key]=value
        check('preview preserves real row with '+key, projected(genuine)==genuine)
    foreign=json.loads(json.dumps(seed));foreign['data']['patient_id']='other-patient'
    check('preview preserves other patient and all commands',projected(foreign)==foreign and projected(seed,'PUT')==seed and projected(seed,path='/api/clinical/index.php/encounters/enc%3A1017')==seed)
    ctx.close(); api.dispose(); browser.close()

check('Director canonical data unchanged', snapshot()==before)
check('no unexpected clinical writes or JavaScript errors', not unexpected and not errors)
(OUT/'semantic-browser-report.json').write_text(json.dumps({'qa':'PASS','checks':checks,'plan_put_count':len(writes),'canonical_writes_target':'disposable DB only','director_records_unchanged':True,'javascript_errors':errors,'unexpected_writes':unexpected},indent=2,ensure_ascii=False)+'\n')
print('CONSULTATION_PLACEHOLDER_R2_SEMANTIC_QA=PASS',flush=True)
