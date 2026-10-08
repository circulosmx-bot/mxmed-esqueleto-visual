"""CONS-SIGN02B-R2: real consent QR modal and Step-6 visual reference, disposable DB."""
import os
from pathlib import Path
from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
OUTPUT = Path(os.environ.get('MXMED_HANDOFF_QA_OUTPUT', '/tmp/mxmed-cons-sign02b-r2-qa'))
OUTPUT.mkdir(parents=True, exist_ok=True)

def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)

def enter_consent(page):
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    page.locator('#ci_next').click()
    page.locator('#ci_title').fill('Consentimiento visual sintético')
    page.locator('#ci_procedimiento').fill('Procedimiento de prueba para revisión visual')
    page.locator('#ci_preview').click()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()

def capture_reference(page, width, height):
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.evaluate('''() => {
      const dialog=document.querySelector('#docux-capture');
      dialog.classList.add('plan02b-modal');
      dialog.querySelector('[data-docux-capture-selection]').hidden=true;
      dialog.querySelector('[data-docux-capture-selected]').hidden=false;
      dialog.querySelector('[data-docux-capture-label]').textContent='Documento clínico';
      dialog.querySelector('[data-docux-capture-guide]').hidden=false;
      dialog.querySelector('[data-docux-qr-content]').hidden=false;
      dialog.querySelector('[data-docux-capture-generate]').hidden=true;
      dialog.querySelector('[data-m7-capture-cancel]').hidden=false;
      dialog.querySelector('[data-docux-capture-expiry]').textContent='Código de un solo uso · página 1';
      dialog.querySelector('[data-m7-capture-state]').textContent='Esperando la primera página…';
      dialog.querySelector('[data-m7-capture-link]').href=location.origin+'/public/note-capture.html';
      const qr=dialog.querySelector('[data-docux-qr]');qr.replaceChildren();
      new QRCode(qr,{text:location.origin+'/public/note-capture.html',width:216,height:216,
        colorDark:'#000000',colorLight:'#ffffff',correctLevel:QRCode.CorrectLevel.M});
      dialog.showModal();
    }''')
    expect(page.locator('#docux-capture')).to_be_visible()
    page.screenshot(path=str(OUTPUT / f'step6-reference-{width}x{height}.png'))

with sync_playwright() as playwright:
    browser = playwright.webkit.launch(headless=True)
    errors = []
    for width, height in ((1440, 900), (1366, 768)):
        context = browser.new_context(viewport={'width':width,'height':height},
            extra_http_headers={'Cookie':'PHPSESSID=step3-head-neck-qa'})
        page = context.new_page()
        page.on('pageerror', lambda error: errors.append(str(error)))
        enter_consent(page)
        page.locator('[data-action="ci-signature-open-qr"]').click()
        modal = page.locator('#modalConsentSignatureQr')
        expect(modal.locator('[data-role="ci-signature-qr-link"]')).to_have_attribute('href', __import__('re').compile('token='))
        expect(modal.locator('[data-role="ci-signature-qr-title"]')).to_have_text('Firmar con celular')
        check(f'STEPS_{width}', modal.locator('.docux-capture-steps li').count() == 3)
        check(f'QR_RENDERED_{width}', modal.locator('.docux-qr img, .docux-qr canvas').count() >= 1)
        check(f'CONTEXT_{width}', 'Paciente' in modal.locator('[data-role="ci-signature-qr-context"]').inner_text())
        check(f'STATUS_{width}', 'Esperando firma' in modal.locator('[role="status"]').inner_text())
        check(f'ACCESSIBILITY_{width}', modal.get_attribute('aria-labelledby') == 'ci-signature-qr-title'
            and modal.locator('[data-role="ci-signature-qr-link"]').get_attribute('href')
            and modal.locator('[data-action="ci-signature-qr-copy-link"]').get_attribute('aria-label') == 'Copiar enlace'
            and modal.locator('[role="status"]').get_attribute('aria-live') == 'polite')
        modal.locator('[data-modal-close]').focus()
        check(f'KEYBOARD_FOCUS_{width}', page.evaluate('document.activeElement?.closest("#modalConsentSignatureQr") !== null'))
        geometry = modal.evaluate('''el=>{const r=el.querySelector('.modal-content').getBoundingClientRect();
          const qr=el.querySelector('.docux-qr').getBoundingClientRect();
          const steps=[...el.querySelectorAll('.docux-capture-steps li')].map(x=>x.getBoundingClientRect());
          const footer=el.querySelector('.modal-footer').getBoundingClientRect();
          return {width:r.width,height:r.height,right:r.right,bottom:r.bottom,
            qrWidth:qr.width,qrHeight:qr.height,footerBottom:footer.bottom,
            stepsReadable:steps.every(x=>x.width>100&&x.height>=35),scrollWidth:el.querySelector('.modal-content').scrollWidth};}''')
        print(f'GEOMETRY_{width}={geometry}', flush=True)
        check(f'LAYOUT_{width}', geometry['right']<=width+1 and geometry['bottom']<=height+1
            and geometry['qrWidth']>=200 and geometry['qrHeight']>=200
            and geometry['stepsReadable'] and geometry['scrollWidth']<=geometry['width']+1
            and geometry['footerBottom']<=height+1)
        page.screenshot(path=str(OUTPUT / f'consent-signature-{width}x{height}.png'))
        modal.locator('[data-modal-close]').press('Enter')
        expect(modal).to_be_hidden()
        check(f'PARENT_FOCUS_RESTORED_{width}', page.evaluate('document.activeElement?.closest("#modalConsentimientoInformado") !== null'))
        page.locator('[data-action="ci-doctor-signature-open-qr"]').click()
        expect(modal.locator('[data-role="ci-signature-qr-link"]')).to_have_attribute('href', __import__('re').compile('token='))
        check(f'DOCTOR_CONTEXT_{width}', 'Médico responsable' in modal.locator('[data-role="ci-signature-qr-context"]').inner_text())
        check(f'DOCTOR_QR_{width}', modal.locator('.docux-qr img, .docux-qr canvas').count() >= 1)
        page.screenshot(path=str(OUTPUT / f'consent-signature-doctor-{width}x{height}.png'))
        modal.locator('.modal-footer [data-bs-dismiss="modal"]').click()
        expect(modal).to_be_hidden()
        capture_reference(page, width, height)
        context.close()
    check('NO_BROWSER_ERRORS', not errors)
    browser.close()
    print('SCREENSHOTS=' + str(OUTPUT), flush=True)
