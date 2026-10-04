"""Read-only FEATURED02 visual gate on the existing Director runtime."""
import os
from playwright.sync_api import expect, sync_playwright

BASE = 'http://127.0.0.1:18148'
SESSION = os.environ['FEATURED02_REVIEW_SESSION']

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height in [(1440, 900), (1366, 768), (390, 844)]:
        context = browser.new_context(viewport={'width':width, 'height':height})
        context.add_cookies([{'name':'PHPSESSID', 'value':SESSION, 'url':BASE}])
        page = context.new_page()
        errors, writes = [], []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.route('**/api/**', lambda route: route.continue_() if route.request.method in ('GET', 'HEAD', 'OPTIONS') else (writes.append(route.request.url), route.abort()))
        page.goto(BASE + '/index.html?review_patient=plan02ux&qa_tools=hide', wait_until='domcontentloaded')
        page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
        page.locator('.vis06-intent-card').first.click()
        page.locator('[data-hier-node="laboratory"]').click()
        expect(page.locator('.vis06-hier-breadcrumb')).to_be_hidden()
        expect(page.locator('#t-estudios .vis06-head h3')).to_have_text('LABORATORIO')
        if width == 1440:
            page.screenshot(path='/tmp/study_nav_featured02_live_family_1440.png', full_page=True)
        page.locator('[data-hier-node="chemistry"]').click()
        expect(page.locator('#t-estudios .vis06-head h3')).to_have_text('LABORATORIO / QUÍMICA CLÍNICA')
        expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
        expect(page.locator('.ordcomp-featured-section h5')).to_have_text('COMUNES')
        expect(page.locator('.tax03c-status')).to_be_hidden()
        assert page.locator('.ordcomp-catalog-role,.ordcomp-breadcrumb').count() == 0
        states = ('compact', 'expanded') if width > 768 else ('mobile',)
        for state in states:
            if state == 'expanded':
                page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
            page.wait_for_timeout(350)
            expect(page.locator('.ordcomp-full-catalog > summary')).to_be_visible()
            assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
            page.screenshot(path=f'/tmp/study_nav_featured02_live_{width}_{state}.png', full_page=True)
            if state == 'compact' and width > 768:
                page.locator('.ordcomp-full-catalog > summary').click()
                expect(page.locator('.ordcomp-featured-section')).to_be_hidden()
                expect(page.locator('.ordcomp-custom-link')).to_be_visible()
                assert page.locator('[data-catalog-group]').count() == 6
                page.locator('.ordcomp-full-catalog > summary').click()
                expect(page.locator('.ordcomp-featured-section')).to_be_visible()
        assert not errors and not writes, (errors, writes)
        print(f'QA_FEATURED02_LIVE_{width}x{height}_{"_".join(states)}=PASS', flush=True)
        context.close()
    browser.close()
print('QA_BROWSER_LAUNCHER=Playwright bundled Chromium headless', flush=True)
