"""Disposable LAB-CAT05C browser gate against the authenticated physician workspace."""
import os
from playwright.sync_api import sync_playwright, expect

BASE = os.environ['LAB05C_BASE']
SESSION = os.environ.get('LAB05C_SESSION', 'labcat05c-ui')
PATIENT = os.environ.get('LAB05C_PATIENT', 'p_labcat05c_order')
READ_ONLY = os.environ.get('LAB05C_READ_ONLY') == '1'
SIZES = [(1440, 900, 'QA_1440x900'), (1366, 768, 'QA_1366x768_COMPACT'),
         (1366, 768, 'QA_1366x768_EXPANDED'), (390, 844, 'QA_390x844')]

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    for width, height, label in SIZES:
        context = browser.new_context(viewport={'width': width, 'height': height})
        context.add_cookies([{'name': 'PHPSESSID', 'value': SESSION, 'url': BASE}])
        page = context.new_page()
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(BASE + ('/index.html?review_patient=plan02ux&qa_tools=hide' if READ_ONLY else '/index.html?qa_tools=hide'), wait_until='domcontentloaded')
        if not READ_ONLY:
            page.evaluate("async patient=>{await window.setActivePatientId(patient,{emitEvent:true,skipActiveEncounterConfirm:true});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}", PATIENT)
        page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-estudios"]').click()
        page.locator('.vis06-intent-card').first.click()
        page.locator('[data-hier-node="laboratory"]').click()
        page.locator('[data-hier-node="chemistry"]').click()
        expect(page.locator('[data-tax03c-preset]')).to_have_count(3)
        expect(page.locator('[data-tax03c-preset="preset_qs3_renal_v1"]')).to_contain_text('Agregar 3 estudios')
        if not READ_ONLY:
            page.locator('[data-tax03c-preset="preset_qs3_renal_v1"]').click()
            page.locator('[data-tax03c-preset="preset_qs3_lipids_v1"]').click()
            expect(page.locator('.ordcomp-prepared-order .tax03c-selected-row')).to_have_count(5)
        else:
            page.locator('[data-tax03c-search]').fill('qs6')
            expect(page.locator('[data-tax03c-key="panel_quimica_6"]')).to_contain_text('Panel de laboratorio · 1 estudio')
            page.locator('[data-tax03c-search]').fill('gsa')
            expect(page.locator('[data-tax03c-key="arterial_blood_gas"]')).to_be_visible()
        if label == 'QA_1440x900' and not READ_ONLY:
            search = page.locator('[data-tax03c-search]')
            search.fill('qs6')
            expect(page.locator('[data-tax03c-key="panel_quimica_6"]')).to_contain_text('Panel de laboratorio · 1 estudio')
            page.locator('[data-tax03c-key="panel_quimica_6"]').click()
            expect(page.locator('.ordcomp-prepared-order .tax03c-selected-row')).to_have_count(6)
            search.fill('gsa')
            expect(page.locator('[data-tax03c-key="arterial_blood_gas"]')).to_be_visible()
            page.locator('[data-tax03c-key="arterial_blood_gas"]').click()
            oxygen = page.locator('select[aria-label="Contexto de oxígeno"]')
            expect(oxygen).to_have_value('UNKNOWN')
            oxygen.select_option('SUPPLEMENTAL_OXYGEN')
            page.locator('.tax03c-oxygen-editor input[type="number"]').fill('35')
            expect(page.locator('.tax03c-oxygen-editor input[type="number"]')).to_have_value('35')
            print('QA_CANONICAL_PANEL_GAS_OXYGEN_UI=PASS', flush=True)
        if label == 'QA_1366x768_EXPANDED':
            page.locator('.mx-gh-toggle').click()
            expect(page.locator('body')).to_have_class(__import__('re').compile(r'.*mx-sidebar-expanded.*'))
        metrics = page.evaluate("""() => ({doc:document.documentElement.scrollWidth, width:innerWidth,
          selected:[...document.querySelectorAll('.ordcomp-prepared-order .tax03c-selected-row strong')].map(n=>n.textContent),
          presets:[...document.querySelectorAll('[data-tax03c-preset]')].length})""")
        assert metrics['doc'] <= width + 1, (label, metrics)
        assert len(metrics['selected']) == (0 if READ_ONLY else 7 if label == 'QA_1440x900' else 5) and not errors, (label, metrics, errors)
        if os.environ.get('LAB05C_SCREENSHOT_DIR'):
            from pathlib import Path
            target = Path(os.environ['LAB05C_SCREENSHOT_DIR'])
            target.mkdir(parents=True, exist_ok=True)
            page.screenshot(path=str(target / (("review-" if READ_ONLY else "disposable-") + label + '.png')))
        print(label + '=PASS', flush=True)
        context.close()
    browser.close()
