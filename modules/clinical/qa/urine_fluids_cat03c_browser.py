"""CAT03C served-asset browser QA; clinical API writes are intercepted."""
import ast,json,pathlib,subprocess
from urllib.parse import parse_qs,urlsplit
from playwright.sync_api import expect,sync_playwright

ROOT=pathlib.Path(__file__).resolve().parents[3]
BASE='http://127.0.0.1:18148'
tree=ast.parse((ROOT/'modules/clinical/qa/lab_cat02a_browser.py').read_text())
HTML=next(ast.literal_eval(node.value) for node in tree.body if isinstance(node,ast.Assign) and any(isinstance(t,ast.Name) and t.id=='HTML' for t in node.targets))
HTML=HTML.replace('</head>','<link rel="stylesheet" href="/assets/css/clinical/study-navigation-hierarchy-v2.css"><link rel="stylesheet" href="/assets/css/clinical/order-composition-v1.css"></head>')
HTML=HTML.replace('<script src="/assets/js/clinical/vis06-modules.js','<script src="/assets/js/clinical/study-navigation-hierarchy-v2.js"></script><script src="/assets/js/clinical/patient-workspace-navigation-guard.js"></script><script src="/assets/js/clinical/order-composition-v1.js"></script><script src="/assets/js/clinical/vis06-modules.js')
raw=subprocess.check_output(['mysql','-N','-B','mxmed_director_review_lon07c','-e','SELECT study_type_id,study_type_key,display_name_es,category_key,aliases_json FROM clinical_study_types WHERE is_active=1'],text=True)
rows=[dict(study_type_id=int(i),study_type_key=k,display_name_es=n,category_key=c,aliases=json.loads(a)) for i,k,n,c,a in (line.split('\t') for line in raw.splitlines())]
assert len(rows)==232
counts={category:sum(row['category_key']==category for row in rows) for category in {row['category_key'] for row in rows}}
ids={row['study_type_key']:row['study_type_id'] for row in rows}

with sync_playwright() as playwright:
    browser=playwright.chromium.launch(headless=True)
    for width,height in [(1440,900),(1366,768),(390,844)]:
        page=browser.new_page(viewport={'width':width,'height':height});errors=[];writes=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.route(BASE+'/__cat03c__',lambda route:route.fulfill(content_type='text/html',body=HTML))
        page.route('**/api/profiles/index.php/private/doctor/**',lambda route:route.fulfill(content_type='application/json',body=json.dumps({'ok':True,'data':{'identity_public':{'specialty_primary':'Médico General'},'verified_credentials':{'professional':None,'specialties':[]}}})))
        def api(route):
            url=urlsplit(route.request.url);query=parse_qs(url.query)
            if route.request.method!='GET':
                writes.append(url.path);route.fulfill(status=403,content_type='application/json',body='{"ok":false}');return
            if url.path.endswith('/study-types'):
                filtered=[row for row in rows if not query.get('category') or row['category_key']==query['category'][0]]
                term=query.get('search',[''])[0].casefold()
                if term:filtered=[row for row in filtered if term in (row['display_name_es']+' '+row['study_type_key']).casefold()]
                offset=int(query.get('offset',['0'])[0]);limit=int(query.get('limit',['30'])[0])
                data={'items':filtered[offset:offset+limit],'has_more':offset+limit<len(filtered),'categories':[{'category_key':k,'label_es':k,'active_count':v} for k,v in counts.items()]}
            elif url.path.endswith('/encounters/active'):data={'doctor_id':'d_labcat02a'}
            else:data={'items':[]}
            route.fulfill(content_type='application/json',body=json.dumps({'ok':True,'data':data},ensure_ascii=False))
        page.route('**/api/clinical/index.php/**',api)
        page.goto(BASE+'/__cat03c__',wait_until='networkidle')
        page.locator('.vis06-intent-card').first.click()
        page.locator('[data-hier-node="laboratory"]').click()
        page.locator('[data-hier-node="urine"]').click()
        expect(page.locator('.ordcomp')).to_be_visible()
        expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
        page.locator('.ordcomp-full-catalog > summary').click()
        groups=[element.get_attribute('data-catalog-group') for element in page.locator('[data-catalog-group]').all()]
        assert groups==['Estudios generales y renales','Electrolitos y minerales urinarios','Microbiología urinaria','Semen','Líquido cefalorraquídeo (LCR)','Líquidos serosos','Líquido sinovial'],groups
        serous=page.locator('[data-catalog-group="Líquidos serosos"]')
        serous.locator('summary').click()
        assert serous.locator('[data-tax03c-id]').count()==8
        synovial=page.locator('[data-catalog-group="Líquido sinovial"]')
        synovial.locator('summary').click()
        assert synovial.locator('[data-tax03c-id]').count()==5
        serous.locator('summary').click()
        serous.locator(f'[data-tax03c-id="{ids["body_fluid_glucose"]}"]').click()
        if width<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
        expect(page.locator('[data-tax03c-specimen]')).to_have_count(1)
        expect(page.locator('.specimen-editor select')).to_have_count(1)
        selector=page.locator('.specimen-editor select')
        assert selector.evaluate('(element)=>element.required && element.closest("label")?.textContent?.includes("Muestra")')
        assert selector.locator('option').evaluate_all('(options)=>options.map(option=>option.value)')==['','PLEURAL_FLUID','ASCITIC_PERITONEAL_FLUID','SYNOVIAL_FLUID','PERICARDIAL_FLUID']
        page.locator('.ordcomp-review-one').click()
        expect(page.locator('dialog.ordcomp-review[open] .ordcomp-error').first).to_contain_text('Completa los datos de muestra')
        page.get_by_role('button',name='Cerrar revisión').click()
        selector.focus();assert selector.evaluate('(element)=>document.activeElement===element')
        selector.select_option('PLEURAL_FLUID')
        expect(page.locator('.ordcomp-summary .tax03c-selected-row')).to_contain_text('Líquido pleural')
        assert page.locator('.specimen-editor').evaluate('(element)=>element.getBoundingClientRect().right<=innerWidth')
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
        assert not errors and not writes,(errors,writes)
        page.screenshot(path=f'/tmp/cat03c_specimen_{width}x{height}.png',full_page=True)
        print(f'QA_CAT03C_BROWSER_SPECIMEN_ACCESSIBILITY_{width}x{height}=PASS',flush=True)
        if width==1440:
            for route_key,study_keys in [('microbiology',['sterile_body_fluid_bacterial_culture','csf_cryptococcal_antigen','csf_vdrl']),('molecular_infectious',['csf_meningitis_encephalitis_panel']),('fluid_cytology',['urine_cytology','csf_cytology','serous_fluid_cytology'])]:
                page.goto(BASE+'/__cat03c__',wait_until='networkidle')
                page.locator('.vis06-intent-card').first.click()
                if route_key=='fluid_cytology':page.locator('[data-hier-node="pathology"]').click()
                else:page.locator('[data-hier-node="laboratory"]').click()
                page.locator(f'[data-hier-node="{route_key}"]').click()
                expect(page.locator('.ordcomp')).to_be_visible()
                for key in study_keys:
                    expect(page.locator(f'.ordcomp [data-tax03c-id="{ids[key]}"]')).to_have_count(1)
            assert not errors and not writes,(errors,writes)
            print('QA_CAT03C_BROWSER_PRIMARY_FAMILY_ROUTES=PASS',flush=True)
        page.close()
    browser.close()
print('QA_BROWSER_LAUNCHER=Playwright bundled Chromium headless',flush=True)
