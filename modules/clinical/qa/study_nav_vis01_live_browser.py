"""Read-only visual and keyboard regression for diagnostic navigation cards."""
import os
from playwright.sync_api import expect, sync_playwright

BASE = os.environ.get('STUDY_NAV_VIS01_BASE', 'http://127.0.0.1:18148')
SESSION = os.environ['STUDY_NAV_VIS01_SESSION']
FAMILIES = ('laboratory', 'imaging', 'pathology', 'functional', 'procedures')
SIZES = ((1440, 900, 'compact'), (1366, 768, 'compact'), (1366, 768, 'expanded'), (390, 844, 'mobile'))

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, sidebar in SIZES:
        context = browser.new_context(viewport={'width': width, 'height': height})
        context.add_cookies([{'name': 'PHPSESSID', 'value': SESSION, 'url': BASE}])
        page = context.new_page()
        errors, blocked = [], []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.route('**/api/**', lambda route: route.continue_() if route.request.method in ('GET', 'HEAD', 'OPTIONS') else (blocked.append(route.request.url), route.abort()))

        def open_navigation(dental=False):
            page.goto(BASE+'/index.html?review_patient=plan02ux', wait_until='domcontentloaded')
            if dental:
                page.wait_for_function('() => !!window.mxmedReviewClassification')
                page.evaluate('mxmedReviewClassification.set(mxmedReviewClassification.options().find(x=>x.label==="Dentista").value)')
            page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
            page.locator('.vis06-intent-card').first.click()
            if sidebar == 'expanded':
                page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')

        def check_cards(label, selector, expected_min_height, expected_icon_size):
            cards = page.locator(selector)
            expect(cards.first).to_be_visible(timeout=20000)
            metrics = cards.evaluate_all('''cards => cards.map(card => {
              const css=getComputedStyle(card), icon=card.querySelector('.material-symbols-rounded'), iconCss=getComputedStyle(icon);
              const title=card.querySelector('.vis06-category-label'), arrow=card.querySelector('.vis06-category-chevron');
              const a=arrow.getBoundingClientRect(), box=card.getBoundingClientRect();
              return {height:box.height,icon:icon.getBoundingClientRect().width,glyph:parseFloat(iconCss.fontSize),
                title:parseFloat(getComputedStyle(title).fontSize),columns:css.gridTemplateColumns,
                arrowCentered:Math.abs((a.top+a.bottom)/2-(box.top+box.bottom)/2)<6,
                titleVisible:title.getBoundingClientRect().width>0};
            })''')
            assert all(m['height'] >= expected_min_height and m['icon'] >= expected_icon_size and m['arrowCentered'] and m['titleVisible'] for m in metrics), (label, metrics)
            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'), (label, width, sidebar)
            page.keyboard.press('Tab')
            cards.first.focus()
            assert cards.first.evaluate("card => {const s=getComputedStyle(card);return s.outlineStyle!=='none'&&parseFloat(s.outlineWidth)>=2}"), label
            cards.first.hover()
            assert cards.first.evaluate("card => getComputedStyle(card).cursor==='pointer'"), label
            if width == 1440 or width == 390:
                page.evaluate("document.getElementById('mxmed_dev_role_switcher')?.style.setProperty('display','none','important')")
                page.screenshot(path=f'/tmp/study-nav-vis01-{label}-{width}x{height}-{sidebar}.png', full_page=True)
            print(f'QA_{label}_{width}x{height}_{sidebar}=PASS', flush=True)

        open_navigation()
        expect(page.locator('.vis06-category-screen')).to_have_attribute('data-hier-level', 'root', timeout=20000)
        expect(page.locator('.vis06-category-screen[data-hier-level="root"] .vis06-category-primary')).to_have_count(5)
        check_cards('root', '.vis06-category-screen[data-hier-level="root"] .vis06-category-primary', 94, 56)
        for family in FAMILIES:
            page.locator(f'[data-hier-node="{family}"]').click()
            expect(page.locator('.vis06-category-screen')).to_have_attribute('data-hier-level', 'family')
            selector = '.vis06-category-screen[data-hier-level="family"] .vis06-category-primary'
            check_cards(family, selector, 78, 48)
            if family == 'laboratory':
                expect(page.locator('.vis06-secondary-categories .vis06-category-secondary')).to_have_count(4)
                check_cards('lab_secondary', '.vis06-secondary-categories .vis06-category-secondary', 68, 44)
            page.get_by_role('button', name='Volver a tipos de estudio').click()
            expect(page.locator('.vis06-category-screen')).to_have_attribute('data-hier-level', 'root')
        open_navigation(dental=True)
        expect(page.locator('.vis06-category-screen')).to_have_attribute('data-hier-level', 'dental', timeout=20000)
        expect(page.locator('.vis06-primary-categories button')).to_have_count(6)
        check_cards('dental', '.vis06-category-screen[data-hier-level="dental"] .vis06-category-primary', 78, 48)
        assert not errors and not blocked, (errors, blocked)
        context.close()
    browser.close()
