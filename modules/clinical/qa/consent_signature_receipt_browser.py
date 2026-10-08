"""CONS-SIGN02B-R3 disposable desktop receipt regression proof."""
import json
import os
import re
import subprocess
import time
from pathlib import Path
from urllib.parse import parse_qs, urlparse

from playwright.sync_api import expect, sync_playwright

BASE = os.environ['FLOW_R1_QA_BASE']
DB = os.environ['FLOW_R1_QA_DB']
assert re.fullmatch(r'flow_r1_qa_[0-9a-f]{12}', DB)
OUT = Path(os.environ.get('MXMED_R3_QA_OUTPUT', '/tmp/mxmed-cons-sign02b-r3-qa'))
OUT.mkdir(parents=True, exist_ok=True)


def check(name, condition):
    if not condition:
        raise AssertionError(name)
    print(f'{name}=PASS', flush=True)


def sql(query):
    return subprocess.check_output(['mysql', '--raw', '-N', '-B', DB, '-e', query], text=True).strip()


def enter_consent(page, title):
    page.goto(BASE + '/index.html?qa_tools=hide', wait_until='domcontentloaded')
    page.wait_for_function('typeof window.setActivePatientId === "function"')
    page.evaluate("window.__MXMED_USER_ID='review-user';window.mxmedStore.user_id='review-user'")
    page.evaluate("async()=>{await window.setActivePatientId('p_plan02ux_review',{emitEvent:true,skipM7DirtyGuard:true,skipActiveEncounterConfirm:true,skipUnsavedNewPatientConfirm:true,applyEntryRule:false});document.querySelector('#p-expediente').classList.remove('d-none');window.dispatchEvent(new Event('patient:selected'))}")
    page.locator('#p-expediente [data-exp-tabs] [data-bs-target="#t-consent"]').click()
    page.locator('#t-consent .docvis-intents').first.locator('button').first.click()
    page.locator('#t-consent .docvis-intents').nth(1).locator('button').first.click()
    page.locator('[data-action="documents-open-consent"]').click()
    page.wait_for_function('''() => ['modalConsentDraftPrompt','modalConsentTemplateFlow']
      .some(id=>document.getElementById(id)?.classList.contains('show'))''')
    if page.locator('#modalConsentDraftPrompt').is_visible():
        page.locator('#ci_draft_discard_btn').click()
    expect(page.locator('#modalConsentTemplateFlow')).to_be_visible()
    page.locator('#modalConsentTemplateFlow [data-tpl-action="blank"]').click()
    page.locator('#ci_next').click()
    page.locator('#ci_title').fill(title)
    page.locator('#ci_procedimiento').fill('Procedimiento sintético QA versión A')
    page.locator('#ci_preview').click()
    page.locator('#ci_review_continue').click()
    expect(page.locator('#ci_signatures_panel')).to_be_visible()
    page.locator('#ci_review_confirm_informed').check()


def qr(page):
    page.locator('[data-action="ci-signature-open-qr"]').click()
    modal = page.locator('#modalConsentSignatureQr')
    expect(modal).to_be_visible()
    link = modal.locator('[data-role="ci-signature-qr-link"]')
    expect(link).to_have_attribute('href', re.compile('token='), timeout=15000)
    href = link.get_attribute('href')
    token = parse_qs(urlparse(href).query)['token'][0]
    check('OPAQUE_TOKEN', bool(re.fullmatch('[a-f0-9]{32}', token)))
    return token, href


def sign_phone(context, href):
    phone = context.new_page()
    phone.goto(href, wait_until='domcontentloaded')
    expect(phone.locator('#consentReview')).to_be_visible()
    phone.locator('#consentContinue').click()
    expect(phone.locator('#signatureForm')).to_be_visible()
    box = phone.locator('#signatureCanvas').bounding_box()
    phone.mouse.move(box['x'] + 25, box['y'] + 25)
    phone.mouse.down()
    phone.mouse.move(box['x'] + 190, box['y'] + 85, steps=12)
    phone.mouse.up()
    phone.locator('#signatureSubmit').click()
    expect(phone.locator('#captureMsg')).to_contain_text('Firma recibida correctamente', timeout=15000)
    phone.close()


def close_qr(page):
    page.locator('#modalConsentSignatureQr .modal-footer [data-bs-dismiss="modal"]').click()
    expect(page.locator('#modalConsentSignatureQr')).to_be_hidden()


def capture(page, name):
    target = OUT / name
    page.screenshot(path=str(target))
    target.chmod(0o600)
    print(f'SCREENSHOT_{name}={target}', flush=True)


with sync_playwright() as playwright:
    browser = playwright.webkit.launch(headless=True)
    desktop_context = browser.new_context(viewport={'width': 1440, 'height': 900},
        extra_http_headers={'Cookie': 'PHPSESSID=step3-head-neck-qa'})
    phone_context = browser.new_context(viewport={'width': 390, 'height': 844})
    page = desktop_context.new_page()
    errors = []
    requests = []
    page.on('pageerror', lambda error: errors.append(str(error)))
    page.on('response', lambda response: requests.append((response.url, response.status))
        if response.request.method == 'GET' and '/note-capture-tokens/' in response.url
        and '/mobile-context' not in response.url else None)

    enter_consent(page, 'R3 recepción tardía')
    token, href = qr(page)
    status = page.locator('#modalConsentSignatureQr [data-role="ci-signature-qr-state"]')
    expect(status).to_have_text('Esperando firma…')
    capture(page, 'waiting.png')
    page.wait_for_timeout(6500)
    check('T01_POLLING_BEGINS', any(item[0].endswith('/note-capture-tokens/' + token) for item in requests))
    check('T02_WAITING_VISIBLE', status.inner_text() == 'Esperando firma…')
    # The production defect stopped at 90 seconds, despite a 15-minute token.
    long_wait_ms = int(os.environ.get('MXMED_R3_LONG_WAIT_MS', '97000'))
    page.wait_for_timeout(long_wait_ms)
    recent = [item for item in requests if item[0].endswith('/note-capture-tokens/' + token)]
    if long_wait_ms >= 95000:
        check('T01_POLLING_SURVIVES_90_SECONDS', len(recent) >= 24 and recent[-1][1] == 200)
    else:
        check('T01_FAST_RERUN_POLLING', len(recent) >= 2 and recent[-1][1] == 200)
    check('T02_STILL_WAITING', status.inner_text() == 'Esperando firma…')
    sign_phone(phone_context, href)
    expect(status).to_have_text('Firma recibida correctamente.', timeout=15000)
    capture(page, 'received.png')
    check('T03_REMOTE_UPLOAD_AUTO_DETECTED', sql(f"SELECT status FROM clinical_note_capture_tokens WHERE token='{token}'") == 'uploaded')
    check('T04_RECEIVED_WITHOUT_MANUAL_VERIFY', status.inner_text() == 'Firma recibida correctamente.')
    check('T05_BOUND_SIGNATURE_LOADED', page.locator('#ci_signature_status').inner_text() == 'Firma vinculada a esta versión')
    receipt_count = len([item for item in requests if item[0].endswith('/note-capture-tokens/' + token)])
    page.wait_for_timeout(5500)
    check('T06_POLLING_STOPS_AFTER_RECEIPT', len([item for item in requests if item[0].endswith('/note-capture-tokens/' + token)]) == receipt_count)
    close_qr(page)
    page.locator('#ci_signatures_continue').click()
    with page.expect_response(lambda response: response.request.method == 'POST'
        and '/patients/p_plan02ux_review/documents' in response.url) as saved:
        page.locator('#ci_save').click()
    check('T16_DRAFT_SAVE', saved.value.status in (200, 201))
    payload = json.loads(sql("SELECT payload_json FROM clinical_documents WHERE document_type='consentimiento_informado' ORDER BY id DESC LIMIT 1"))
    binding = payload['signatures']['patient']['binding']
    check('T17_REVIEW_AND_V1_BINDING_PRESERVED', binding['version'] == 1
        and binding['source'] == 'remote_qr' and payload['signatures']['patient']['token'] == token)
    for _ in range(20):
        if sql(f"SELECT status FROM clinical_note_capture_tokens WHERE token='{token}'") == 'consumed':
            break
        time.sleep(0.1)
    check('T17_TOKEN_CONSUMED_ON_DRAFT_SAVE', sql(f"SELECT status FROM clinical_note_capture_tokens WHERE token='{token}'") == 'consumed')

    enter_consent(page, 'R3 expiración')
    expired_token, _ = qr(page)
    sql(f"UPDATE clinical_note_capture_tokens SET expires_at=UTC_TIMESTAMP()-INTERVAL 60 SECOND WHERE token='{expired_token}'")
    expect(status).to_have_text('El código expiró. Genera uno nuevo.', timeout=12000)
    check('T07_EXPIRED_REPORTED', status.inner_text() == 'El código expiró. Genera uno nuevo.')
    expired_count = len([item for item in requests if item[0].endswith('/note-capture-tokens/' + expired_token)])
    page.wait_for_timeout(5500)
    check('T07_EXPIRED_STOPS_POLLING', len([item for item in requests if item[0].endswith('/note-capture-tokens/' + expired_token)]) == expired_count)
    close_qr(page)

    enter_consent(page, 'R3 versión obsoleta')
    stale_token, _ = qr(page)
    response = desktop_context.request.post(BASE + '/api/clinical/index.php/note-capture-tokens/' + stale_token + '/invalidate')
    check('T08_INVALIDATION_ACCEPTED', response.status == 200)
    expect(status).to_have_text('El consentimiento cambió. Genera un nuevo código para firmar la versión actual.', timeout=12000)
    check('T08_STALE_REPORTED', 'consentimiento cambió' in status.inner_text())
    stale_count = len([item for item in requests if item[0].endswith('/note-capture-tokens/' + stale_token)])
    page.wait_for_timeout(5500)
    check('T08_STALE_STOPS_POLLING', len([item for item in requests if item[0].endswith('/note-capture-tokens/' + stale_token)]) == stale_count)
    close_qr(page)

    enter_consent(page, 'R3 cierre')
    closed_token, _ = qr(page)
    page.wait_for_timeout(6500)
    check('T09_POLLING_BEGAN_BEFORE_CLOSE', any(item[0].endswith('/note-capture-tokens/' + closed_token) for item in requests))
    close_qr(page)
    closed_count = len([item for item in requests if item[0].endswith('/note-capture-tokens/' + closed_token)])
    page.wait_for_timeout(5500)
    check('T09_MODAL_CLOSE_STOPS_POLLING', len([item for item in requests if item[0].endswith('/note-capture-tokens/' + closed_token)]) == closed_count)

    enter_consent(page, 'R3 sustitución de sesión')
    old_token, _ = qr(page)
    page.evaluate('''token=>{const original=window.fetch.bind(window);window.__r3ReleaseOld=null;
      window.fetch=(url,options)=>String(url).endsWith('/note-capture-tokens/'+token)
        ? new Promise(resolve=>{window.__r3ReleaseOld=()=>resolve(new Response(JSON.stringify({ok:true,data:{status:'expired'}}),
          {status:200,headers:{'Content-Type':'application/json'}}))}) : original(url,options); }''', old_token)
    page.locator('#modalConsentSignatureQr [data-action="ci-signature-qr-verify-now"]').click()
    page.wait_for_function('window.__r3ReleaseOld !== null')
    page.evaluate("document.querySelector('[data-action=\"ci-signature-open-qr\"]').click()")
    page.wait_for_function('old=>{const href=document.querySelector("#modalConsentSignatureQr [data-role=ci-signature-qr-link]")?.href||"";return href.includes("token=")&&!href.includes(old)}', arg=old_token)
    new_href = page.locator('#modalConsentSignatureQr [data-role="ci-signature-qr-link"]').get_attribute('href')
    new_token = parse_qs(urlparse(new_href).query)['token'][0]
    check('T10_NEW_QR_REPLACES_OLD', new_token != old_token)
    page.evaluate('window.__r3ReleaseOld()')
    page.wait_for_timeout(200)
    check('T11_LATE_OLD_RESPONSE_IGNORED', status.inner_text() == 'Esperando firma…')
    page.evaluate('''token=>{const previous=window.fetch;window.__r3Transient=true;
      window.fetch=(url,options)=>{if(window.__r3Transient && String(url).endsWith('/note-capture-tokens/'+token)){
        window.__r3Transient=false;return Promise.reject(new TypeError('synthetic network interruption'));}
        return previous(url,options);};}''', new_token)
    page.wait_for_timeout(9000)
    check('T12_TRANSIENT_RETRIES_WITHOUT_CORRUPTION', status.inner_text() == 'Esperando firma…'
        and page.evaluate('window.__r3Transient') is False)
    close_qr(page)

    enter_consent(page, 'R3 vínculo inválido')
    mismatch_token, mismatch_href = qr(page)
    def mismatch_response(route):
        response = route.fetch()
        body = response.json()
        if body.get('data', {}).get('status') == 'uploaded':
            body['data']['signature']['binding']['content_fingerprint'] = '0' * 64
        route.fulfill(status=response.status, content_type='application/json', body=json.dumps(body))
    page.route('**/api/clinical/index.php/note-capture-tokens/' + mismatch_token, mismatch_response)
    sign_phone(phone_context, mismatch_href)
    expect(status).to_have_text('El consentimiento cambió. Genera un nuevo código para firmar la versión actual.', timeout=15000)
    check('T13_BINDING_MISMATCH_NOT_RECEIVED', page.locator('#ci_signature_status').inner_text() == 'Sin firma')
    close_qr(page)

    check('T18_NO_BROWSER_ERRORS', not errors)
    browser.close()
