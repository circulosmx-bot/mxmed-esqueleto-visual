"""MULTI-CONS03 API, desktop and mobile review on a disposable QA database."""
import copy
import os
import re
import uuid
from pathlib import Path

from playwright.sync_api import sync_playwright, expect

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
OUT = Path('/tmp/mxmed-multi-cons03-qa')
OUT.mkdir(exist_ok=True)
PATIENT = 'p_plan02ux_review'
DOC = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/documents'
PREVIEW = BASE + f'/api/clinical/index.php/doctors/1/patients/{PATIENT}/multimedia-preview'
QR = BASE + '/api/clinical/index.php/multimedia-qr-sessions'


def check(name, condition):
    assert condition, name
    print(f'{name}=PASS', flush=True)


def body():
    return {'document_type': 'consentimiento_multimedia', 'type': 'consentimiento_multimedia',
        'title': 'Consentimiento multimedia', 'context': {'patient_id': PATIENT},
        'actor': {'user_id': 'review-user'}, 'payload': {'contract_version': 1, 'status': 'draft',
        'consent': {'model_version': 2, 'media_types': ['photo', 'video'], 'capture_allowed': True,
            'use_purposes': ['clinical_record', 'clinical_followup'],
            'sensitive_purposes_confirmed': False, 'other_specific_use_description': '',
            'identification_scope': 'identifiable', 'authorization_scope': 'current_condition_followup',
            'validity': {'mode': 'until_revoked', 'valid_from': '2026-09-01', 'valid_to': '2026-09-30'},
            'context': {'clinical_purpose_note': 'MULTI-CONS03 CASO SINTÉTICO QA'}},
        'signer': {'mode': 'patient'},
        'patient_snapshot': {'id': PATIENT, 'full_name': 'Paciente sintético QA', 'age': '36', 'sex': 'Femenino'},
        'actor_snapshot': {'user_id': 'review-user', 'full_name': 'Dra. QA'},
        'presentation': {'version': 1, 'professional_header': 'shown'},
        'document_date': '2026-10-11', 'signatures': {'signer': None, 'doctor': None}}}


with sync_playwright() as pw:
    api = pw.request.new_context(extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    case = body()
    response = api.post(PREVIEW, data=case)
    check('V2_PREVIEW', response.status == 200)
    html = response.json()['data']['html']
    check('UNTIL_REVOKED_COPY', 'solicite su revocación para usos futuros' in html)
    check('RECOGNITION_QUESTION', '¿La persona puede ser reconocida en el material?' in html)
    check('INACTIVE_DATES_EXCLUDED', '2026-09-30' not in html)

    for purpose in ('teaching', 'clinical_research', 'clinical_trial', 'scientific_publication',
                    'institutional_communication', 'public_dissemination', 'other_specific_use'):
        variant = copy.deepcopy(case)
        variant['payload']['consent']['use_purposes'].append(purpose)
        if purpose == 'other_specific_use':
            variant['payload']['consent']['other_specific_use_description'] = 'Sesión académica definida'
        preview = api.post(PREVIEW, data=variant)
        check('PURPOSE_PREVIEW_' + purpose.upper(), preview.status == 200)
        variant['payload']['status'] = 'generated'
        blocked = api.post(DOC, data=variant, headers={'Idempotency-Key': 'mmc03-' + str(uuid.uuid4())})
        check('SENSITIVE_EMISSION_BLOCKED_' + purpose.upper(), blocked.status in (400, 422)
              and 'MULTIMEDIA_SENSITIVE_PURPOSE_CONFIRMATION_REQUIRED' in blocked.text())

    created = api.post(DOC, data=case, headers={'Idempotency-Key': 'mmc03-' + str(uuid.uuid4())})
    check('V2_DRAFT_CREATED', created.status == 201)
    ref = created.json()['data']['document_id']
    version = int(created.json()['data']['document']['version'])
    qr = api.post(QR, data={'document_uuid': ref, 'document_version': version, 'role': 'signer'})
    check('V2_QR_CREATED', qr.status == 201)
    token = qr.json()['data']['token']
    browser = pw.chromium.launch(headless=True)
    for width, height in ((320, 740), (360, 800), (390, 844), (414, 896)):
        page = browser.new_page(viewport={'width': width, 'height': height})
        errors = []
        page.on('pageerror', lambda error: errors.append(str(error)))
        page.goto(BASE + qr.json()['data']['mobile_url'], wait_until='domcontentloaded')
        expect(page.locator('#consentReview')).to_be_visible(timeout=15000)
        frame = page.frame_locator('#consentReviewFrame')
        expect(frame.locator('.doc-base-title')).to_contain_text('CONSENTIMIENTO MULTIMEDIA')
        frame_overflow = frame.locator('html').evaluate('(el)=>el.scrollWidth>el.clientWidth+1')
        top_overflow = page.evaluate('document.documentElement.scrollWidth>innerWidth+1')
        check(f'MOBILE_{width}_READABLE', not frame_overflow and not top_overflow and not errors)
        page.screenshot(path=str(OUT / f'mobile-{width}.png'))
        page.close()
    long_case = body()
    long_case['payload']['consent']['use_purposes'] = ['scientific_publication', 'institutional_communication',
        'public_dissemination', 'other_specific_use']
    long_case['payload']['consent']['sensitive_purposes_confirmed'] = True
    long_case['payload']['consent']['other_specific_use_description'] = 'Presentación clínica interna del caso sintético con alcance específico y previamente autorizado'
    long_created = api.post(DOC, data=long_case, headers={'Idempotency-Key': 'mmc03-' + str(uuid.uuid4())})
    check('LONG_LABEL_DRAFT', long_created.status == 201)
    long_qr = api.post(QR, data={'document_uuid': long_created.json()['data']['document_id'],
        'document_version': 1, 'role': 'signer'})
    check('LONG_LABEL_QR', long_qr.status == 201)
    for width, height in ((320, 740), (360, 800), (390, 844), (414, 896)):
        page = browser.new_page(viewport={'width': width, 'height': height})
        page.goto(BASE + long_qr.json()['data']['mobile_url'], wait_until='domcontentloaded')
        expect(page.locator('#consentReview')).to_be_visible(timeout=15000)
        frame = page.frame_locator('#consentReviewFrame')
        expect(frame.locator('body')).to_contain_text('Presentaciones o publicaciones científicas')
        check(f'MOBILE_{width}_LONG_LABELS_NO_OVERFLOW',
            not page.evaluate('document.documentElement.scrollWidth>innerWidth+1')
            and not frame.locator('html').evaluate('(el)=>el.scrollWidth>el.clientWidth+1'))
        page.screenshot(path=str(OUT / f'mobile-long-{width}.png'))
        page.close()

    desktop = browser.new_page(viewport={'width': 1440, 'height': 900},
        extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    errors = []
    desktop.on('pageerror', lambda error: errors.append(str(error)))
    desktop.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    desktop.wait_for_function('typeof window.setActivePatientId === "function"')
    desktop.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    desktop.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    desktop.locator('[data-doc-code="consentimiento_multimedia"]').evaluate('(el)=>el.click()')
    expect(desktop.locator('#modalConsentimientoMultimedia')).to_be_visible(timeout=15000)
    desktop.locator('[data-mmc-new]').click()
    desktop.locator('[data-mmc-help="purposes"]').click()
    check('PURPOSE_HELP_VISIBLE', 'captura no autoriza usos' in desktop.locator('.mxeh-overlay:not([hidden])').inner_text())
    check('HELP_OPEN_READ_ONLY', desktop.locator('input[name="mmc_purpose"]:checked').count() == 0)
    desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-close').click()
    for field in ('mmc_identification', 'mmc_scope', 'mmc_validity'):
        desktop.locator(f'[data-example-for="{field}"]').click()
        check('SELECTOR_HELP_GUIDANCE_' + field.upper(),
              desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').count() == 0)
        desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-close').click()
    desktop.locator('[data-mmc-capture] details').evaluate('(node)=>node.open=true')
    desktop.locator('[data-example-for="mmc_note"]').click()
    desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    check('CONTEXT_HELP_INSERTS', 'Autorización para obtener fotografías' in desktop.locator('#mmc_note').input_value())
    desktop.locator('#mmc_note').fill('')
    check('FULL_EXAMPLE_ACTION', desktop.locator('[data-mmc-full-example]').is_visible())
    desktop.locator('[data-mmc-full-example]').click()
    desktop.locator('[data-mmc-use-example]').click()
    check('EMPTY_EXAMPLE_APPLIED', desktop.locator('#mmc_validity').input_value() == 'until_revoked')
    check('EXAMPLE_SELECTED_ONLY_CLINICAL', desktop.locator('input[name="mmc_purpose"]:checked').count() == 2)
    check('EXAMPLE_WARNING', desktop.locator('[data-mmc-example-warning]').is_visible())
    desktop.locator('[data-mmc-capture] details').evaluate('(node)=>node.open=true')
    desktop.locator('#mmc_note').fill('Texto propio')
    desktop.locator('[data-mmc-use-example]').click()
    check('POPULATED_REPLACE_CONFIRM', desktop.locator('[data-mmc-replace-confirm]').is_visible())
    desktop.locator('[data-mmc-replace-cancel]').click()
    check('CANCEL_PRESERVES', desktop.locator('#mmc_note').input_value() == 'Texto propio')
    desktop.locator('[data-mmc-use-example]').click()
    desktop.locator('[data-mmc-replace-apply]').click()
    check('REPLACE_APPLIES', 'Autorización para obtener fotografías' in desktop.locator('#mmc_note').input_value())
    desktop.locator('input[name="mmc_purpose"][value="teaching"]').check()
    check('SENSITIVE_CONFIRM_VISIBLE', desktop.locator('[data-mmc-sensitive-wrap]').is_visible())
    desktop.locator('[data-mmc-sensitive-confirm]').check()
    desktop.locator('input[name="mmc_purpose"][value="clinical_research"]').check()
    check('NEW_SENSITIVE_PURPOSE_REQUIRES_RECONFIRM', not desktop.locator('[data-mmc-sensitive-confirm]').is_checked())
    desktop.locator('input[name="mmc_purpose"][value="clinical_research"]').uncheck()
    desktop.locator('input[name="mmc_purpose"][value="teaching"]').uncheck()
    desktop.locator('input[name="mmc_purpose"][value="other_specific_use"]').check()
    desktop.locator('[data-example-for="mmc_other_use"]').click()
    desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    check('OTHER_USE_HELP_INSERTS', 'sesión clínica interna' in desktop.locator('#mmc_other_use').input_value())
    desktop.locator('input[name="mmc_purpose"][value="other_specific_use"]').uncheck()
    desktop.locator('#mmc_scope').select_option('custom_limited_scope')
    desktop.locator('[data-example-for="mmc_custom_scope"]').click()
    desktop.locator('.mxeh-overlay:not([hidden]) .mxeh-use:visible').click()
    check('CUSTOM_SCOPE_HELP_INSERTS', 'La autorización se limita al material obtenido' in
          desktop.locator('#mmc_custom_scope').input_value())
    desktop.locator('#mmc_scope').select_option('current_condition_followup')
    check('EXAMPLE_ACTION_KEYBOARD_ACCESSIBLE',
          desktop.locator('[data-mmc-full-example]').evaluate('(el)=>el.tagName === "BUTTON" && el.tabIndex >= 0'))
    desktop.locator('[data-mmc-preview]').click()
    expect(desktop.locator('[data-mmc-review]')).to_be_visible(timeout=15000)
    check('EXAMPLE_CANONICAL_PREVIEW', 'hasta que el paciente' in desktop.locator('[data-mmc-review-html]').inner_text())
    desktop.screenshot(path=str(OUT / 'desktop-example-1440.png'))
    check('DESKTOP_NO_PAGE_ERRORS', not errors)
    browser.close()
    check('V2_QR_UNCHANGED_PROTOCOL', api.get(f'{QR}/{token}/mobile-context').status == 200)

print('MULTI_CONS03_DISPOSABLE=PASS')
