"""Read-only authenticated SEARCH02-R1 gate against the actual Director runtime."""
import json
import os
from playwright.sync_api import expect, sync_playwright

BASE = os.environ.get('STUDY_SEARCH02_R1_BASE', 'http://127.0.0.1:18148')
SESSION = os.environ['STUDY_SEARCH02_R1_SESSION']
QUERIES = ('hem', 'gli', 'glu', 'hba', 'a1c', 'ant', 'pro', 'psa', 'psa t', 'psa l', 'crp', 'pcr', 'tac', 'rmn', 'eco', 'hol', 'mapa')

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, sidebar in ((1440, 900, 'compact'), (1366, 768, 'compact'), (1366, 768, 'expanded'), (390, 844, 'mobile')):
        context = browser.new_context(viewport={'width': width, 'height': height})
        context.add_cookies([{'name': 'PHPSESSID', 'value': SESSION, 'url': BASE}])
        page = context.new_page()
        errors, writes, catalog_responses = [], [], []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.on('response', lambda response: catalog_responses.append(response.status) if '/study-types?' in response.url else None)
        page.route('**/api/**', lambda route: route.continue_() if route.request.method in ('GET', 'HEAD', 'OPTIONS') else (writes.append(route.request.url), route.abort()))
        page.goto(BASE + '/index.html?review_patient=plan02ux&qa_tools=hide', wait_until='domcontentloaded')
        page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
        page.locator('.vis06-intent-card').first.click()
        page.locator('[data-hier-node="laboratory"]').click()
        page.locator('[data-hier-node="chemistry"]').click()
        if sidebar == 'expanded':
            page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
        search = page.locator('.ordcomp [data-tax03c-search]')
        expect(search).to_be_visible()
        assert catalog_responses and all(status == 200 for status in catalog_responses), catalog_responses
        if width == 1440:
            coverage = page.evaluate('''async () => {
              const h=window.mxmedStudyNavigationHierarchyV2;
              const url=performance.getEntriesByType('resource').map(x=>x.name).find(x=>x.includes('/study-types?'));
              const base=url.split('?')[0];let rows=[];
              for(let offset=0;;){const response=await fetch(base+'?limit=100&offset='+offset,{credentials:'same-origin'});
                const data=(await response.json()).data;rows.push(...data.items);offset+=data.items.length;if(!data.has_more)break;}
              const featured=await (await fetch('/modules/clinical/catalog/study_featured_navigation_v1.json')).json();
              const unmapped=rows.filter(item=>!h.root.some(root=>h.parts(root).some(p=>p.category===item.category_key&&(!p.keys||p.keys.includes(item.study_type_key))))
                &&!Object.values(featured.leaves).some(leaf=>leaf.root_family==='dental'&&leaf.study_keys.includes(item.study_type_key)));
              return {count:rows.length,unmapped:unmapped.map(x=>x.study_type_key)};
            }''')
            print('REAL_CATALOG_COVERAGE=' + json.dumps(coverage))
            assert coverage['count'] == 252 and not coverage['unmapped'], coverage
        observed = {}
        for query in QUERIES:
            search.fill(query)
            expect(page.locator('.ordcomp .tax03c-family-results h5')).to_have_text('Resultados en Laboratorio')
            page.wait_for_function("() => { const s=document.querySelector('.ordcomp [data-tax03c-status]'); return s && !s.textContent.includes('Buscando') && s.textContent.includes('para esta búsqueda'); }")
            same = page.locator('.ordcomp .tax03c-family-results .tax03c-result-row strong').all_inner_texts()
            rescue = page.locator('.ordcomp .tax03c-rescue-row strong').all_inner_texts()
            observed[query] = {'family': same[:5], 'other': rescue[:5]}
            if query in ('hba', 'psa'):
                location = 'Endocrinología y hormonas' if query == 'hba' else 'Marcadores tumorales'
                assert location in page.locator('.ordcomp .tax03c-family-results').inner_text()
            if query in ('tac', 'rmn', 'eco', 'hol', 'mapa'):
                family = 'Imagenología' if query in ('tac', 'rmn', 'eco') else 'Estudios funcionales'
                assert family in page.locator('.ordcomp .tax03c-rescue-row').first.inner_text()
                assert page.locator('.ordcomp .tax03c-rescue-row [data-tax03c-id]').count() == 0
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (query, width, sidebar)
        assert 'Antígeno prostático específico total' in ' '.join(observed['psa']['family'])
        assert observed['tac']['other'] and observed['rmn']['other']
        assert observed['hol']['other'] and observed['mapa']['other']
        assert page.locator('.ordcomp [data-tax03c-global]').is_hidden()
        search.fill('psa')
        expect(page.locator('.ordcomp .tax03c-family-results .tax03c-result-row')).not_to_have_count(0)
        page.locator('.ordcomp .tax03c-family-results .tax03c-result-row').first.click()
        selected_before = page.locator('.ordcomp-prepared-order .tax03c-selected-row').count()
        search.fill('')
        page.locator('.ordcomp-full-catalog > summary').click()
        page.locator('.ordcomp [data-tax03c-custom-open]').click()
        page.locator('.ordcomp [data-tax03c-custom-name]').fill('Borrador de estudio propio')
        search.fill('tac')
        rescue_action = page.locator('.ordcomp .tax03c-rescue-action').first
        expect(rescue_action).to_be_visible()
        rescue_action.click()
        expect(page.locator('#t-estudios .vis06-head h3')).to_contain_text('TOMOGRAFÍA')
        expect(page.locator('.ordcomp [data-tax03c-search]')).to_have_value('tac')
        expect(page.locator('.ordcomp .tax03c-navigation-target')).to_contain_text('TAC Tórax')
        assert page.locator('.ordcomp-prepared-order .tax03c-selected-row').count() == selected_before
        expect(page.locator('.ordcomp [data-tax03c-custom-name]')).to_have_value('Borrador de estudio propio')
        page.locator('.ordcomp [data-tax03c-search]').fill('psa')
        expect(page.locator('.ordcomp .tax03c-rescue-action:disabled')).to_contain_text('✓ Agregado')
        assert page.locator('[role="dialog"]').filter(has_text='Descartar').count() == 0
        page.locator('.ordcomp [data-tax03c-search]').fill('')
        expect(page.locator('.ordcomp .tax03c-family-results')).to_have_count(0)
        assert not errors and not writes, (errors, writes)
        print(f'QA_LIVE_{width}x{height}_{sidebar}=PASS')
        if width == 1440:
            print('REAL_RUNTIME_QUERY_RESULTS=' + json.dumps(observed, ensure_ascii=False))
        context.close()
    browser.close()
