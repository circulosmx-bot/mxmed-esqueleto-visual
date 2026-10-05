"""Read-only end-to-end Dental navigation/search on the actual Director runtime."""
import os
from playwright.sync_api import sync_playwright,expect
BASE=os.environ.get('DENTALCAT03C_REVIEW_BASE','http://127.0.0.1:18148')
SESSION=os.environ['DENTALCAT03C_REVIEW_SESSION']
forbidden=[]
with sync_playwright() as playwright:
    browser=playwright.chromium.launch(headless=True)
    for width,height,sidebar in [(1440,900,'compact'),(1366,768,'compact'),(1366,768,'expanded'),(390,844,'mobile')]:
        context=browser.new_context(viewport={'width':width,'height':height})
        context.add_cookies([{'name':'PHPSESSID','value':SESSION,'url':BASE}])
        page=context.new_page();errors=[];catalog=[]
        page.on('pageerror',lambda error:errors.append(str(error)))
        page.on('response',lambda response:catalog.append(response.status) if '/study-types?' in response.url else None)
        page.route('**/api/**',lambda route:route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else (forbidden.append(route.request.url),route.abort()))
        page.goto(BASE+'/index.html?review_patient=plan02ux',wait_until='domcontentloaded')
        page.evaluate('mxmedReviewClassification.set(mxmedReviewClassification.options().find(x=>x.label==="Dentista").value)')
        page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
        page.locator('.vis06-intent-card').first.click()
        if sidebar=='expanded':page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
        expect(page.locator('.vis06-primary-categories button')).to_have_count(6,timeout=20000)
        assert 'Radiografía intraoral' in page.locator('.vis06-primary-categories').inner_text()
        page.locator('.vis06-primary-categories button').first.click()
        expect(page.locator('#t-estudios .vis06-head h3')).to_contain_text('RADIOGRAFÍA INTRAORAL')
        expect(page.locator('.ordcomp .tax03c-result-row')).to_have_count(4)
        assert page.locator('.ordcomp [data-tax03c-global]').is_hidden()
        for term,key in [('periapical','dental_periapical_xray'),('bitewing','dental_bitewing_xray'),('oclusal','dental_occlusal_xray'),('cbct','dental_cbct'),('atm','tmj_comparative_xray'),('panoramica','dental_panoramic_xray'),('cefalometria','dental_cephalometric_xray')]:
            page.locator('.ordcomp [data-tax03c-search]').fill(term)
            page.wait_for_function("() => {let x=document.querySelector('.ordcomp [data-tax03c-status]');return x && x.textContent.includes('para esta búsqueda')}")
            assert page.locator(f'.ordcomp [data-tax03c-key="{key}"]').count()>0,(term,key,page.locator('.ordcomp').inner_text()[:700])
        page.locator('.ordcomp [data-tax03c-search]').fill('')
        expect(page.locator('.ordcomp .tax03c-result-row')).to_have_count(4)
        page.locator('.ordcomp [data-tax03c-key="dental_periapical_xray"]').click()
        page.wait_for_selector('.dental-location-dialog[open] [data-fdi="16"]')
        page.locator('.dental-location-dialog[open] [data-fdi="16"]').click()
        assert page.locator('.dental-location-dialog[open] [data-fdi="16"]').get_attribute('aria-pressed')=='true'
        assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,sidebar,page.evaluate('document.documentElement.scrollWidth'))
        assert catalog and all(code==200 for code in catalog),catalog
        assert not errors and not forbidden,(errors,forbidden)
        if width==1440 or width==390:page.screenshot(path=f'/tmp/dental-cat03c-live-{width}x{height}.png',full_page=True)
        print(f'QA_LIVE_{width}x{height}_{sidebar}=PASS',flush=True)
        context.close()
    browser.close()
