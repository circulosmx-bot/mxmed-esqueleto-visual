"""Served-asset study-search QA against the actual PHP scorer and disposable SQLite catalog."""
import json
import pathlib
import subprocess
from urllib.parse import parse_qs, urlsplit

from playwright.sync_api import expect, sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[3]
BASE = 'http://127.0.0.1:18148'
AUTHORITY = json.loads((ROOT / 'modules/clinical/catalog/study_search_authority_v1.json').read_text())
KEYS = {study['study_key']: index + 1 for index, study in enumerate(AUTHORITY['studies'])}
HTML = '''<!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="stylesheet" href="/assets/css/expediente-paciente-visual-normalization.css?v=study-search02">
<link rel="stylesheet" href="/assets/css/clinical/order-composition-v1.css?v=study-search02">
<style>body{margin:0;background:#f2fafc}#qa-shell{display:grid;grid-template-columns:var(--qa-side,76px) minmax(0,1fr);min-height:100vh}
#qa-side{background:#00bcca}#qa-main{min-width:0;padding:20px}.ordcomp{max-width:1480px;margin:auto}
@media(max-width:767px){#qa-shell{grid-template-columns:minmax(0,1fr)}#qa-side{display:none}#qa-main{padding:8px}}</style></head>
<body><div id="qa-shell"><div id="qa-side"></div><main id="qa-main"><div class="ordcomp"><div class="ordcomp-workspace">
<section class="ordcomp-catalog"><h2 id="qa-heading">Estudios de diagnóstico</h2><div id="qa-composer"></div></section>
<aside class="ordcomp-summary"><h3>ÓRDENES EN PREPARACIÓN</h3><div id="qa-selected"></div></aside></div></div><div id="qa-modal"></div></main></div>
<script src="/assets/js/clinical/dental-location-v1.js"></script>
<script src="/assets/js/clinical/lab-cat02a-navigation-v1.js"></script>
<script src="/assets/js/clinical/study-navigation-hierarchy-v2.js"></script>
<script src="/assets/js/clinical/tax03c-study-composer.js?v=study-search02"></script>
<script>window.qaReady=Promise.all([
 fetch('/modules/clinical/catalog/study_order_routing_v1.json').then(r=>r.json()),
 fetch('/modules/clinical/catalog/study_featured_navigation_v1.json').then(r=>r.json()),
 fetch('/modules/clinical/catalog/study_specimen_requirements_v1.json').then(r=>r.json())
]).then(([routing,featured,specimen])=>{
 window.qaConfig={routing,featured,specimen};window.qaSelected=[];
 window.qaMount=leafId=>{
   window.qaComposer?.destroy();const leaf=featured.leaves[leafId];
   document.getElementById('qa-heading').textContent=leaf.heading;
   window.qaComposer=window.mxmedStudyComposer.mount(document.getElementById('qa-composer'),{
    doctorId:'qa',presentation:'embedded',routing,featuredNavigation:featured,leafId,
    navigationGroup:{label:leaf.heading,parts:[{category:leafId==='dental-cbct'?'IMAGEN':'LABORATORIO',keys:leaf.study_keys}]},
    specimenConfig:specimen,selectionHost:document.getElementById('qa-selected'),selected:window.qaSelected,
    onChange:items=>{window.qaSelected=items;}
   });
 };
 window.qaMountModal=()=>{window.qaModal?.destroy();window.qaModal=window.mxmedStudyComposer.mount(document.getElementById('qa-modal'),{doctorId:'qa',routing,specimenConfig:specimen});};
 window.qaMount('endocrine');
});</script></body></html>'''

def response(query):
    result = subprocess.check_output(['php', str(ROOT / 'modules/clinical/qa/study_search02_fixture.php'), '--response', json.dumps(query)], text=True)
    return result

def keys(page, selector='#qa-composer [data-tax03c-id]'):
    return [int(value) for value in page.locator(selector).evaluate_all('(nodes)=>nodes.map(n=>n.dataset.tax03cId)')]

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, sidebar in [(1440, 900, 76), (1366, 768, 76), (1366, 768, 232), (390, 844, 0)]:
        page = browser.new_page(viewport={'width': width, 'height': height})
        page_errors, writes = [], []
        page.on('pageerror', lambda error: page_errors.append(str(error)))
        page.route(BASE + '/__study_search02__', lambda route: route.fulfill(content_type='text/html; charset=utf-8', body=HTML))
        cache = {}
        def api(route):
            url = urlsplit(route.request.url)
            if route.request.method != 'GET':
                writes.append(url.path)
                route.fulfill(status=403, body='{"ok":false}')
                return
            if url.path.endswith('/study-types'):
                query = {key: value[0] for key, value in parse_qs(url.query).items()}
                cache_key = json.dumps(query, sort_keys=True)
                if cache_key not in cache: cache[cache_key] = response(query)
                route.fulfill(content_type='application/json', body=cache[cache_key])
            else:
                route.fulfill(content_type='application/json', body='{"ok":true,"data":{"items":[]}}')
        page.route('**/api/clinical/index.php/**', api)
        page.goto(BASE + '/__study_search02__', wait_until='networkidle')
        page.evaluate('([width])=>document.documentElement.style.setProperty("--qa-side",width+"px")', [sidebar])
        page.evaluate('window.qaReady')
        search = page.locator('#qa-composer [data-tax03c-search]')
        expect(page.locator('[data-ordcomp-featured]')).to_be_visible()
        page.evaluate('''() => {const original=window.fetch.bind(window);window.fetch=(url,options)=>{
          const pending=original(url,options),request=new URL(url,location.href);
          return request.searchParams.get('search')==='psa' ? pending.then(response=>new Promise(resolve=>setTimeout(()=>resolve(response),650))) : pending;
        };}''')
        search.fill('psa')
        page.wait_for_timeout(320)
        search.fill('gli')
        expect(page.locator(f'#qa-composer [data-tax03c-id="{KEYS["hba1c"]}"]')).to_have_count(1)
        assert keys(page) == [KEYS['hba1c']]
        page.wait_for_timeout(700)
        assert keys(page) == [KEYS['hba1c']]  # delayed old response cannot repaint a new query
        assert page.locator('#qa-composer .tax03c-common-name').count() == 0  # canonical already contains it
        search.focus()
        page.keyboard.press('Tab')
        expect(page.locator(f'#qa-composer [data-tax03c-id="{KEYS["hba1c"]}"]')).to_be_focused()
        assert 'nombre común' not in page.locator(f'#qa-composer [data-tax03c-id="{KEYS["hba1c"]}"]').get_attribute('aria-label')
        page.keyboard.press('Enter')
        expect(search).to_be_focused()
        expect(page.locator('#qa-selected .tax03c-selected-row')).to_have_count(1)
        expect(page.locator(f'#qa-composer [data-tax03c-id="{KEYS["hba1c"]}"]')).to_have_attribute('aria-pressed', 'true')
        assert len(page.evaluate('window.qaSelected')) == 1
        search.fill('')
        expect(page.locator('[data-ordcomp-featured]')).to_be_visible()
        assert len(page.evaluate('window.qaSelected')) == 1
        page.evaluate("window.qaMount('tumor')")
        search = page.locator('#qa-composer [data-tax03c-search]')
        search.fill('ant pro')
        expect(page.locator('#qa-composer [data-tax03c-id]')).to_have_count(2)
        assert keys(page) == [KEYS['psa_free'], KEYS['psa_total']]
        assert page.locator('#qa-composer .tax03c-common-name').all_inner_texts() == ['PSA libre', 'PSA total']
        search.fill('psa t')
        expect(page.locator('#qa-composer [data-tax03c-id]')).to_have_count(1)
        assert keys(page) == [KEYS['psa_total']]
        search.fill('psa l')
        expect(page.locator('#qa-composer [data-tax03c-id]')).to_have_count(1)
        assert keys(page) == [KEYS['psa_free']]
        search.fill('PCR')
        expect(page.locator('#qa-composer [data-tax03c-id]')).to_have_count(3)
        assert keys(page) == [KEYS['crp_hs'], KEYS['urine_protein_creatinine_panel'], KEYS['crp_standard']]
        page.evaluate("window.qaMount('chemistry')")
        search = page.locator('#qa-composer [data-tax03c-search]')
        search.fill('glu')
        expect(page.locator('#qa-composer [data-tax03c-id]')).to_have_count(6)
        assert KEYS['hba1c'] in keys(page)
        assert KEYS['glucose'] in keys(page)
        assert page.locator('#qa-composer [data-tax03c-global]').is_hidden()
        page.evaluate('window.qaMountModal()')
        modal_search = page.locator('#qa-modal [data-tax03c-search]')
        modal_search.fill('ant pro')
        expect(page.locator('#qa-modal [data-tax03c-id]')).to_have_count(2)
        assert keys(page, '#qa-modal [data-tax03c-id]') == [KEYS['psa_free'], KEYS['psa_total']]
        assert page.locator('#qa-modal .tax03c-common-name').all_inner_texts() == ['PSA libre', 'PSA total']
        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
        assert page.locator('#qa-composer .tax03c-result-row').evaluate_all('(rows)=>rows.every(row=>row.getBoundingClientRect().height>=44)')
        assert page.locator('#qa-composer [data-tax03c-id]').first.get_attribute('aria-label').startswith('Agregar ')
        assert not page_errors and not writes, (page_errors, writes)
        label = f'{width}x{height}_' + ('EXPANDED' if sidebar == 232 else 'COMPACT' if width == 1366 else 'DEFAULT')
        page.screenshot(path=f'/tmp/study_search02_{label}.png', full_page=True)
        print(f'QA_STUDY_SEARCH02_{label}=PASS', flush=True)
        page.close()
    browser.close()
