"""Read-only six-family selector smoke test against Director runtime 18148."""
import os
from playwright.sync_api import expect, sync_playwright

BASE = os.environ.get('STUDY_COVERAGE02_BASE', 'http://127.0.0.1:18148')
SESSION = os.environ['STUDY_COVERAGE02_SESSION']
CASES = [
    ('laboratory', 'chemistry', 'glucose'),
    ('imaging', 'tomography', 'ct_chest'),
    ('pathology', 'histopathology', 'histopath_biopsy'),
    ('functional', 'gi_motility', 'esophageal_manometry'),
    ('procedures', 'gynecology', 'colposcopy_diagnostic'),
]

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, sidebar in ((1440, 900, 'compact'), (1366, 768, 'compact'), (1366, 768, 'expanded'), (390, 844, 'mobile')):
        context = browser.new_context(viewport={'width': width, 'height': height})
        context.add_cookies([{'name': 'PHPSESSID', 'value': SESSION, 'url': BASE}])
        blocked = []; errors = []
        for family, leaf, key in CASES + [('dental', 'dental', 'dental_periapical_xray')]:
            page = context.new_page()
            page.on('pageerror', lambda error: errors.append(str(error)))
            page.route('**/api/**', lambda route: route.continue_() if route.request.method in ('GET', 'HEAD', 'OPTIONS') else (blocked.append(route.request.url), route.abort()))
            page.goto(BASE + '/index.html?review_patient=plan02ux', wait_until='domcontentloaded')
            if family == 'dental':
                page.wait_for_function('() => !!window.mxmedReviewClassification')
                page.evaluate('mxmedReviewClassification.set(mxmedReviewClassification.options().find(x=>x.label==="Dentista").value)')
            page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
            page.locator('.vis06-intent-card').first.click()
            if sidebar == 'expanded':
                page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
            if family == 'dental':
                expect(page.locator('.vis06-primary-categories button')).to_have_count(6, timeout=20000)
                page.locator('.vis06-primary-categories button').first.click()
            else:
                page.locator(f'[data-hier-node="{family}"]').click()
                page.locator(f'[data-hier-node="{leaf}"]').click()
            expect(page.locator(f'.ordcomp [data-tax03c-key="{key}"]:visible').first).to_be_visible(timeout=20000)
            expect(page.locator('.ordcomp [data-tax03c-search]')).to_be_visible()
            if width >= 600:
                expect(page.locator('.ordcomp-summary')).to_be_visible()
            else:
                expect(page.locator('.ordcomp-summary')).to_have_count(1)
            assert page.locator('.ordcomp [data-tax03c-global]').is_hidden(), family
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (family, width, sidebar)
            if family == 'laboratory' or (family == 'dental' and width == 390):
                page.screenshot(path=f'/tmp/study-coverage02-{family}-{width}x{height}-{sidebar}.png', full_page=True)
            page.close()
        assert not blocked and not errors, (blocked, errors)
        print(f'QA_{width}x{height}_{sidebar}_SIX_FAMILIES=PASS', flush=True)
        context.close()
    browser.close()
