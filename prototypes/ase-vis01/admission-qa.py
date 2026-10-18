#!/usr/bin/env python3
"""ASE-ADM01: fictional static UI checks; no live admission or backend acceptance."""
import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path
import subprocess
import sys
import unicodedata
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright

ROOT = Path(__file__).resolve().parents[2]
VIEWPORTS = [(1440, 900), (1366, 768), (820, 1180), (390, 844)]
ROUTES = {'/', '/style.css', '/app.js', '/catalog.js', '/commercial.js',
          '/commercial.css', '/admission.js', '/admission.css',
          '/portrait-example.svg', '/assets/css/media-review-inbox.css'}
WARNING = "Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy."


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def normalized(value):
    return ''.join(c for c in unicodedata.normalize('NFD', value.lower())
                   if unicodedata.category(c) != 'Mn')


def run_case(browser, engine, viewport, base, out, catalog_baseline):
    width, height = viewport
    context = browser.new_context(viewport={'width': width, 'height': height},
                                  locale='es-MX', reduced_motion='reduce')
    page = context.new_page()
    checks, errors, requests, forbidden, shots, diagnostics, warnings = [], [], [], [], [], [], []
    capturing = False
    origin = urlsplit(base)

    def check(name, ok, detail=None):
        checks.append({'name': name, 'pass': bool(ok),
                       **({'detail': detail} if detail is not None else {})})
        if not ok:
            raise AssertionError(name + ': ' + str(detail))

    def route(r):
        p = urlsplit(r.request.url)
        entry = {'path': p.path, 'method': r.request.method,
                 'origin': f'{p.scheme}://{p.netloc}'}
        requests.append(entry)
        if ((p.scheme, p.netloc) != (origin.scheme, origin.netloc) or
                p.path not in ROUTES or p.query or p.fragment or r.request.method != 'GET'):
            forbidden.append(entry)
            r.abort()
        else:
            r.continue_()

    context.route('**/*', route)
    context.add_init_script("""(() => {
      window.__effects=[]; window.__styles=[];
      const deny=name=>(...args)=>{window.__effects.push(name);throw Error('Unexpected external/persistent action: '+name);};
      for(const key of ['setItem','removeItem','clear'])Storage.prototype[key]=deny('Storage.'+key);
      if(window.indexedDB)indexedDB.open=deny('indexedDB');
      if(navigator.serviceWorker)navigator.serviceWorker.register=deny('serviceWorker');
      window.fetch=deny('fetch'); XMLHttpRequest.prototype.open=deny('XMLHttpRequest');
      if(navigator.sendBeacon)navigator.sendBeacon=deny('sendBeacon');
      if(window.WebSocket)window.WebSocket=deny('WebSocket');
      if(window.EventSource)window.EventSource=deny('EventSource');
      if(navigator.mediaDevices)navigator.mediaDevices.getUserMedia=deny('getUserMedia');
      const cookie=Object.getOwnPropertyDescriptor(Document.prototype,'cookie');
      if(cookie&&cookie.configurable)Object.defineProperty(Document.prototype,'cookie',{...cookie,set:deny('cookie')});
      new MutationObserver(rs=>rs.forEach(r=>r.addedNodes.forEach(n=>{
        if(n.nodeName==='STYLE')window.__styles.push(n.textContent);
      }))).observe(document,{childList:true,subtree:true});
    })();""")
    page.on('pageerror', lambda e: errors.append(str(e)))

    def console(message):
        if message.type != 'error':
            return
        if capturing and engine == 'webkit' and message.text == WARNING:
            warnings.append(message.text)
        else:
            errors.append(message.text)

    page.on('console', console)

    def shot(name, modal=False):
        nonlocal capturing
        start = page.evaluate('window.__styles.length')
        count = len(warnings)
        path = out / f'{engine}-{width}x{height}-{name}.png'
        capturing = True
        try:
            page.screenshot(path=str(path), full_page=not modal)
        finally:
            capturing = False
        styles = page.evaluate('(n)=>window.__styles.slice(n)', start)
        found = warnings[count:]
        if found:
            check('Only exact WebKit screenshot body-style injection qualified',
                  engine == 'webkit' and styles == ['body {}'] and found == [WARNING],
                  {'styles': styles, 'messages': found})
            diagnostics.append({'screenshot': name, 'style': 'body {}',
                                'csp_rejected': True, 'warning': WARNING,
                                'scope': 'Exact DOM mutation and screenshot timing only'})
        elif styles:
            check('No unqualified style injection during screenshot', False, styles)
        shots.append(str(path.relative_to(ROOT)))

    def layout(name):
        metrics = page.evaluate("""()=>({
          overflow:Math.max(document.body.scrollWidth,document.documentElement.scrollWidth)>innerWidth,
          modalOverflow:[...document.querySelectorAll('dialog[open]')].filter(d=>d.scrollWidth>d.clientWidth+1).map(d=>d.id),
          small:[...document.querySelectorAll('button,select,input:not([type=checkbox]),summary,label:has(input[type=checkbox])')]
            .filter(e=>e.getClientRects().length&&!e.closest('dialog:not([open])')&&e.getBoundingClientRect().height<43.9)
            .map(e=>({id:e.id,text:e.textContent.slice(0,80),height:e.getBoundingClientRect().height})),
          unlabeled:[...document.querySelectorAll('input,select,textarea')].filter(e=>e.getClientRects().length&&!e.closest('dialog:not([open])')&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.id)
        })""")
        check(name + ': no horizontal overflow', not metrics['overflow'] and not metrics['modalOverflow'], metrics)
        check(name + ': controls and checkbox labels >=44px', not metrics['small'], metrics['small'])
        check(name + ': visible inputs have accessible labels', not metrics['unlabeled'], metrics['unlabeled'])

    def mode(value):
        page.locator('.review-controls').evaluate('e=>e.open=true')
        page.locator('#authority-view').select_option(value)

    def detail():
        if width < 640:
            page.locator('#show-detail').click()

    def member(ident):
        if width < 640:
            page.locator('#show-directory').click()
        page.locator(f'[data-member="{ident}"]').click()

    def queue():
        page.locator('#admission-requests').evaluate('e=>e.open=true')

    def open_case(ident, applicant=False):
        queue()
        selector = f'[data-{"applicant" if applicant else "admission"}-case="{ident}"]'
        page.locator(selector).click()
        target = '#applicant-preview-title' if applicant else '#admission-review-title'
        page.wait_for_function('(s)=>document.querySelector(s)===document.activeElement', arg=target)
        check('Application/review opens at its named heading', True)

    def close_dialog(ident):
        page.keyboard.press('Escape')
        page.wait_for_function('(id)=>!document.getElementById(id).open', arg=ident)

    def trap(ident):
        dialog = page.locator('#' + ident)
        controls = dialog.locator('button,input,select,textarea,[tabindex="0"]')
        enabled = [c for c in controls.all() if c.is_visible() and c.is_enabled()]
        check(ident + ': modal has keyboard controls', bool(enabled))
        enabled[-1].focus()
        page.keyboard.press('Tab')
        check(ident + ': Tab wraps to first control', enabled[0].evaluate('e=>e===document.activeElement'))
        page.keyboard.press('Shift+Tab')
        check(ident + ': Shift+Tab wraps to last control', enabled[-1].evaluate('e=>e===document.activeElement'))

    def status(name):
        return page.locator(f'#admission-review [data-status-{name}]').inner_text()

    def not_active():
        access = normalized(status('access'))
        check('Submission/review never creates ACTIVE operational access',
              any(term in access for term in ['pendiente', 'sin acceso', 'inactivo', 'no habilitado', 'no activo', 'bloqueado']), access)

    def applicant_submit(document='readable', photo=True):
        page.locator('#applicant-given').fill('Nora')
        page.locator('#applicant-first').fill('Cedro')
        page.locator('#applicant-second').fill('Ejemplo')
        if page.locator('#applicant-mobile').is_editable():
            page.locator('#applicant-mobile').fill('TEL-EJEMPLO')
        photo_control = page.locator('#applicant-photo')
        if photo and photo_control.get_attribute('type') == 'checkbox':
            photo_control.check()
        elif photo and page.locator('#applicant-portrait img').count() == 0:
            photo_control.click()
        page.locator('#applicant-document').select_option(document)
        page.locator('#applicant-confirm').check()
        page.locator('#applicant-ack').check()
        page.locator('#applicant-submit').click()

    result, error = 'FAIL', None
    try:
        response = page.goto(base, wait_until='networkidle')
        csp = response.headers.get('content-security-policy', '')
        check('Loopback static 200 with no connection/form/object capability', response.status == 200 and
              all(v in csp for v in ["connect-src 'none'", "form-action 'none'", "object-src 'none'", "frame-ancestors 'none'"]))
        check('Explicit non-operational prototype scope', 'PROTOTIPO — NO OPERATIVO' in page.locator('body').inner_text())
        check('No real file selection, camera capture or delegated password field',
              page.locator('input[type=file],input[type=password],input[capture]').count() == 0)
        catalog = page.evaluate('window.ASE_VIS01_CATALOG')
        check('Exact R2 approved canonical catalog snapshot preserved', catalog == catalog_baseline)
        expected = {k for k, v in catalog.items() if v['kind'] == 'operational' and v['delegable']}
        check('Canonical catalog remains18 with no admission/commercial capability invented', len(catalog) == 18 and len(expected) == 16 and
              not any('admission' in key or 'commission' in key or 'sales' in key for key in catalog))
        detail()
        check('Five R2 operational families and exact16 keys preserved',
              page.locator('#operational-matrix [data-family]').count() == 5 and
              set(page.locator('#operational-matrix [data-capability]').evaluate_all('ns=>ns.map(n=>n.dataset.capability)')) == expected)
        check('One permission matrix and one commercial section, no duplicated editors',
              page.locator('#capabilities-form,#operational-matrix,#commercial-conditions').count() == 3)
        check('Portrait shown in directory and selected header from internal illustration only',
              page.locator('[data-member="aurora"] .avatar img[src="/portrait-example.svg"]').count() == 1 and
              page.locator('#advisor-detail .admission-portrait img[src="/portrait-example.svg"]').count() == 1)
        check('Other members retain neutral portrait fallback',
              page.locator('[data-member="bruno"] .avatar img').count() == 0 and
              page.locator('[data-member="bruno"] .avatar [aria-label]').count() == 1)
        check('Identity review and access are distinct selected-header indicators',
              page.locator('#advisor-detail .admission-indicators span').count() == 2)
        check('Required personal/evidence/access/commercial/history collapsibles retained',
              all(page.locator(selector).count() == 1 for selector in
                  ['#admission-personal', '#admission-identity', '#admission-access', '#commercial-conditions', '#advisor-detail .history']))
        check('Commercial proposed four-activity group preserved separately',
              page.locator('#commercial-proposal').count() == 1 and
              page.locator('#commercial-proposal input:disabled').count() == 4 and
              page.locator('#commercial-proposal [data-capability]').count() == 0)
        page.locator('#commercial-conditions').evaluate('e=>e.open=true')
        terms = page.locator('#commercial-conditions').inner_text()
        check('Commercial unresolved rate/basis and15% customer distinction retained',
              terms.count('PENDIENTE DE DEFINICIÓN') == 2 and '15% de descuento al cliente' in terms)
        check('Recurring paid monthly/annual renewals remain in commercial scope',
              'mensuales y anuales' in terms and 'No requiere reingresar el código' in terms)
        page.locator('#commercial-conditions').evaluate('e=>e.open=false')
        layout('Directory and selected profile')
        shot('directory-profile')

        # Both invitation entry paths keep the original four-step administrative journey.
        for path, scenario, given in [('assisted', 'existing', 'Sara'), ('remote', 'new', 'Teo')]:
            if width < 640:
                page.locator('#show-directory').click()
            page.locator('#add-member').click()
            check('Original onboarding remains four steps', page.locator('#onboarding .wizard-steps li').count() == 4)
            page.locator('#admission-path').select_option(path)
            page.locator('#member-name').fill(given)
            page.locator('#admission-first-surname').fill('Sauce')
            page.locator('#admission-second-surname').fill('Ejemplo')
            page.locator('#member-email').fill(given.lower() + '.sauce@example.test')
            check(path + ': mobile reference never claims SMS verification',
                  'Sin verificar' in page.locator('#admission-phone-hint').inner_text())
            page.locator('#admission-city').fill('Ciudad de ejemplo')
            page.locator('#identity-case').select_option(scenario)
            check(path + ': no password or file selection in assisted preparation',
                  page.locator('#onboarding input[type=password],#onboarding input[type=file]').count() == 0)
            page.locator('#next-onboarding').click()
            page.locator('#member-category').select_option('ADVISOR')
            page.locator('#next-onboarding').click()
            check(path + ': intended functions remain individual requests',
                  page.locator('#onboarding-use [data-capability]').count() == 16)
            page.locator('#next-onboarding').click()
            summary = normalized(page.locator('#onboarding-content').inner_text())
            check(path + ': review never asserts delivered invitation or active authority',
                  'no activa' in summary or 'pendiente' in summary)
            if path == 'remote':
                layout('Remote invitation proposal')
                shot('remote-proposal', True)
            page.locator('#next-onboarding').click()
            check(path + ': closes after fictional request without active grant creation',
                  not page.locator('#onboarding').evaluate('e=>e.open') and
                  page.locator('#operational-matrix [data-capability]:checked').count() == 0)
            check(path + ': account/invitation effects remain unexecuted',
                  not page.evaluate('window.__effects'))
            page.locator('#admission-personal').evaluate('e=>e.open=true')
            prepared = normalized(page.locator('#admission-personal').inner_text())
            check(path + ': administrator provenance never fabricates applicant confirmation',
                  'preparados por administrador' in prepared and 'confirmacion personal pendiente' in prepared)
            page.locator('#admission-personal').evaluate('e=>e.open=false')

        open_case('remote-demo', True)
        applicant = page.locator('#applicant-preview')
        check('Applicant restricted to own case, no category/inviter/grants editor',
              applicant.locator('#member-category,[data-capability],input[type=password],input[type=file]').count() == 0)
        check('Self-completion has core personal data and separate confirmations',
              all(applicant.locator(s).count() == 1 for s in ['#applicant-given', '#applicant-first', '#applicant-second',
                  '#applicant-mobile', '#applicant-photo', '#applicant-document', '#applicant-confirm', '#applicant-ack']))
        applicant_submit('missing')
        check('Missing evidence cannot silently submit as reviewed/active',
              'no se ha presentado una version' in normalized(page.locator('#applicant-feedback').inner_text()) and
              'sin presentar' in normalized(applicant.locator('[data-status-review]').inner_text()))
        layout('Applicant own completion')
        trap('applicant-preview')
        page.locator('#applicant-preview-title').focus()
        shot('applicant-completion', True)
        applicant_submit('readable')
        check('Applicant submit remains only fictional own-case submission',
              not page.evaluate('window.__effects'))
        close_dialog('applicant-preview')

        open_case('remote-demo')
        review = page.locator('#admission-review')
        check('Seven independent account/contact/review/admission/access/MFA states',
              all(review.locator('[data-status-' + name + ']').count() == 1 for name in
                  ['account', 'email', 'phone', 'review', 'admission', 'access', 'mfa']))
        check('New account/email remain pending despite document submission',
              'pendiente' in normalized(status('account') + ' ' + status('email')))
        check('Telephone never falsely marked verified without provider',
              any(s in normalized(status('phone')) for s in ['sin verificar', 'no verificado', 'no verificada', 'pendiente']))
        not_active()
        page.locator('#review-factual').check()
        check('Factual acknowledgement alone cannot replace opening exact evidence version',
              page.locator('#review-complete').is_disabled())
        page.locator('#review-factual').uncheck()
        page.locator('#review-document').click()
        placeholder = page.locator('#evidence-placeholder')
        check('Protected evidence is plainly fictional document, never portrait image',
              'DOCUMENTO DE EJEMPLO' in placeholder.inner_text() and
              placeholder.locator('img').count() == 0 and placeholder.get_attribute('data-evidence-version') is not None)
        check('No real document URL, download or encoded identity payload',
              page.locator('#admission-document a[download],#admission-document iframe,#admission-document object,#admission-document canvas').count() == 0)
        layout('Synthetic document preview')
        shot('document-placeholder', True)
        close_dialog('admission-document')
        page.wait_for_function('()=>document.activeElement===document.getElementById("review-document")')
        check('Escape from evidence returns focus to exact review entry', True)
        page.locator('#review-factual').check()
        page.locator('#review-complete').click()
        check('Review transition preserves keyboard focus inside modal',
              review.evaluate('e=>e.contains(document.activeElement)'))
        check('Routine factual review is separate from admission approval',
              'complet' in normalized(status('review')) and 'aprob' not in normalized(status('admission')))
        page.locator('#review-approve').click()
        check('Approval transition preserves keyboard focus inside modal',
              review.evaluate('e=>e.contains(document.activeElement)'))
        check('Approval preserves pending personal setup',
              'aprob' in normalized(status('admission')) and
              any(s in normalized(review.inner_text()) for s in ['configuracion personal', 'setup', 'configuracion pendiente']))
        not_active()
        check('Review cannot activate financial conditions or all grants',
              '15%' not in status('access') and not page.evaluate('window.__effects'))
        reviewed_v1 = review.locator('[data-reviewed-version="1"]').inner_text()
        check('Reviewed version1 binds the submitted name and separate portrait',
              'Nora Cedro Ejemplo' in reviewed_v1 and 'retrato separado incluido' in reviewed_v1)
        layout('Review approved with setup pending')
        trap('admission-review')
        page.locator('#admission-review-title').focus()
        shot('review-setup-pending', True)
        close_dialog('admission-review')

        open_case('remote-demo', True)
        page.locator('#applicant-material-change').click()
        public_history = normalized(applicant.locator('.admission-history').inner_text())
        check('Applicant sees retained version without internal reviewer notes',
              applicant.locator('[data-reviewed-version="1"]').count() == 1 and
              'direccion ficticia' not in public_history and 'metodo:' not in public_history and
              'fecha ficticia de revision' not in public_history)
        page.locator('#applicant-given').fill('Nora Segunda')
        page.locator('#applicant-document').select_option('unreadable')
        if page.locator('#applicant-portrait img').count():
            page.locator('#applicant-photo').click()
        page.locator('#applicant-confirm').check()
        page.locator('#applicant-ack').check()
        page.locator('#applicant-submit').click()
        close_dialog('applicant-preview')
        open_case('remote-demo')
        check('Material change retains reviewed version while new version needs review',
              review.locator('[data-reviewed-version="1"]').count() > 0 and
              'complet' not in normalized(status('review')))
        check('Reviewed-v1 name/photo/document metadata never overwritten by new material version',
              review.locator('[data-reviewed-version="1"]').inner_text() == reviewed_v1 and
              'Nora Cedro Ejemplo' in review.locator('[data-submitted-version="1"]').inner_text() and
              'documento legible en el ejemplo; retrato incluido' in review.locator('[data-submitted-version="1"]').inner_text() and
              'Nora Segunda Cedro Ejemplo' in review.locator('[data-submitted-version="2"]').inner_text() and
              'documento ilegible; retrato sin indicar' in review.locator('[data-submitted-version="2"]').inner_text())
        not_active()
        review.locator('[data-reviewed-version="1"]').scroll_into_view_if_needed()
        shot('version-history', True)
        close_dialog('admission-review')

        open_case('clarification-demo')
        check('Existing legitimate account differs from new pending account',
              'pendiente' not in normalized(status('account')))
        check('Unreadable evidence cannot complete or approve review',
              page.locator('#review-complete').is_disabled() and page.locator('#review-approve').is_disabled())
        page.locator('#review-clarification').click()
        check('Uncertain evidence requests clarification instead of verified badge',
              'aclar' in normalized(status('review')) and page.locator('#review-approve').is_disabled())
        not_active()
        layout('Clarification requested')
        shot('clarification', True)
        close_dialog('admission-review')
        open_case('clarification-demo', True)
        check('Applicant sees own clarification without staff-only document custody',
              'aclar' in normalized(applicant.inner_text()) and applicant.locator('#review-document,#review-approve,#review-reject').count() == 0)
        applicant_submit('readable')
        close_dialog('applicant-preview')
        open_case('clarification-demo')
        check('Clarification response is a new evidence version',
              review.locator('[data-evidence-version="2"]').count() > 0)
        check('Clarification-v1 unreadable metadata retained beside legible response-v2',
              'Lina Sauce' in review.locator('[data-submitted-version="1"]').inner_text() and
              'documento ilegible' in review.locator('[data-submitted-version="1"]').inner_text() and
              'Nora Cedro Ejemplo' in review.locator('[data-submitted-version="2"]').inner_text() and
              'documento legible en el ejemplo' in review.locator('[data-submitted-version="2"]').inner_text())
        page.locator('#review-reject').click()
        check('Rejection blocks operational access without deleting prior history',
              'rechaz' in normalized(status('admission')))
        not_active()
        close_dialog('admission-review')
        open_case('clarification-demo', True)
        check('Applicant rejection stays bounded, no reviewer-only evidence controls',
              'no fue aprobada' in normalized(applicant.inner_text()) and
              applicant.locator('#review-document,#review-complete,#review-approve').count() == 0)
        close_dialog('applicant-preview')

        # A presentation role switch is not backend authority. Proposed review is distinct.
        mode('master')
        queue()
        check('Existing Master management alone does not grant evidence review',
              page.locator('[data-admission-case="remote-demo"]').is_disabled())
        page.locator('#admission-master-review').check()
        check('Explicit proposed Master review scope reaches Advisors only',
              page.locator('[data-admission-case="remote-demo"]').is_enabled() and
              page.locator('[data-admission-case="master-demo"]').is_disabled())
        check('Master cannot approve own application',
              page.locator('[data-admission-case="self-demo"]').is_disabled())
        if width < 640:
            page.locator('#show-directory').click()
        page.locator('#add-member').click()
        page.locator('#next-onboarding').click()
        check('Only Director may propose another Master',
              page.locator('#member-category option[value="MASTER_ADMIN"]').evaluate('e=>e.disabled'))
        close_dialog('onboarding')
        check('Master cannot reach the Master directory or self-management',
              page.locator('#filter-masters').is_disabled())
        page.locator('#admission-peer-view').check()
        queue()
        check('Ordinary peer view has no protected-evidence/review actions',
              all(c.is_disabled() for c in page.locator('[data-admission-case]').all()))
        check('Peer cannot impersonate another applicant through preview controls',
              all(c.is_disabled() for c in page.locator('[data-applicant-case]').all()))
        page.locator('#admission-peer-view').uncheck()
        page.locator('#admission-master-review').uncheck()
        mode('missing')
        queue()
        check('Master without management cannot prepare admission or inspect evidence',
              page.locator('#add-member').is_disabled() and
              all(c.is_disabled() for c in page.locator('[data-admission-case]').all()))
        mode('director')
        queue()
        check('Director can inspect proposed Master case explicitly',
              page.locator('[data-admission-case="master-demo"]').is_enabled())

        # Existing individual grants and own-only commercial examples survive new admission UI.
        member('aurora')
        page.locator('#operational-matrix [data-family="media"] [data-expand]').click()
        read = page.locator('#operational-matrix [data-capability="media_review_read"]')
        approve = page.locator('#operational-matrix [data-capability="media_review_approve"]')
        check('R2 existing individual use state remains distinct', read.is_checked() and not approve.is_checked())
        approve.check()
        page.locator('#save-permissions').click()
        check('Permission change retains explicit individual confirmation',
              page.locator('#confirmation').evaluate('e=>e.open') and 'Aprobar' in page.locator('#confirmation-body').inner_text())
        page.locator('#cancel-confirmation').click()
        page.locator('#revert').click()
        page.locator('#commercial-conditions').evaluate('e=>e.open=true')
        page.locator('#preview-own-activity').click()
        commercial = page.locator('#commercial-activity')
        check('Commercial own-only fictitious customers remain isolated',
              commercial.locator('[data-own-customer="aurora"]').count() == 2 and
              commercial.locator('[data-own-advisor="aurora"]').count() == 5 and
              commercial.locator('select').count() == 0 and 'Bruno Lago' not in commercial.inner_text())
        page.locator('#commercial-replay').click()
        check('Conceptual repeat still produces no extra commission result',
              commercial.locator('[data-conceptual-result]').count() == 5 and '$' not in commercial.inner_text())
        close_dialog('commercial-activity')
        check('No document scans/camera/password accepted anywhere',
              page.locator('input[type=file],input[type=password],input[capture]').count() == 0)
        check('No persistence, APIs, delivery, scanner, camera or provider action',
              not page.evaluate('window.__effects') and context.storage_state() == {'cookies': [], 'origins': []})
        check('Only allowlisted static GETs and no unqualified errors',
              bool(requests) and not forbidden and not errors, {'forbidden': forbidden, 'errors': errors})
        result = 'PASS'
    except Exception as exc:
        error = str(exc)
        try:
            shot('failure', bool(page.locator('dialog[open]').count()))
        except Exception as capture_error:
            errors.append('Failure screenshot: ' + str(capture_error))
    finally:
        context.close()
    return {'engine': engine, 'viewport': f'{width}x{height}', 'result': result,
            'error': error, 'checks': checks, 'requests': requests,
            'forbidden_requests': forbidden, 'application_errors': errors,
            'capture_tool_diagnostics': diagnostics, 'screenshots': shots}


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--url', default='http://127.0.0.1:8140/')
    parser.add_argument('--evidence', type=Path, default=ROOT / 'prototypes/ase-vis01/evidence/adm01/browser')
    args = parser.parse_args()
    origin = urlsplit(args.url)
    if (origin.scheme != 'http' or origin.hostname != '127.0.0.1' or not origin.port or origin.port < 8140 or
            origin.path not in ['', '/'] or origin.query or origin.fragment or origin.username or origin.password):
        parser.error('Only loopback static preview root at port>=8140')
    if git('branch', '--show-current') != 'prototype/ase-vis01-advisor-capability-matrix':
        parser.error('The existing isolated prototype branch is required')
    out = args.evidence.resolve()
    out.mkdir(parents=True, exist_ok=True)
    catalog_source = git('show', 'bee02e5106d443abeeecaa2a88ec8caf2c1db6d1:prototypes/ase-vis01/catalog.js')
    catalog_baseline = json.loads(catalog_source.split('=', 1)[1].strip().removesuffix(';'))
    driver = Path(__import__('playwright').__file__).resolve().parent / 'driver/package/lib/server/screenshotter.js'
    driver_bytes = driver.read_bytes()
    paths = ['prototypes/ase-vis01/' + name for name in [
        'index.html', 'app.js', 'style.css', 'catalog.js', 'commercial.js', 'commercial.css',
        'admission.js', 'admission.css', 'portrait-example.svg', 'serve.py', 'admission-qa.py', 'admission-regression.py', 'qa.py', 'commercial-qa.py']]
    paths += ['modules/identity/services/InternalCapabilityCatalog.php', 'assets/css/media-review-inbox.css']
    report = {'task': 'ASE-ADM01', 'scope': 'Static fictional admission/profile prototype only; no backend security or live accreditation acceptance',
              'started_utc': dt.datetime.now(dt.timezone.utc).isoformat(),
              'branch': git('branch', '--show-current'), 'commit_at_test': git('rev-parse', 'HEAD'),
              'commit_binding': 'Candidate source SHA256 must match final commit tree',
              'source_sha256': {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in paths if (ROOT / path).exists()},
              'physical_safari': 'NOT_RUN', 'mobile': 'INTERIM_MOBILE_SMOKE_ONLY',
              'r2_approval_preserved': True, 'commercial_visual_approval_pending': True,
              'new_visual_approval_pending': True, 'real_documents_used': False,
              'real_account_or_staff_changes': False,
              'capture_tool_qualification': {'driver_path': str(driver),
                  'sha256': hashlib.sha256(driver_bytes).hexdigest(),
                  'body_style_line': next(i for i, line in enumerate(driver_bytes.decode().splitlines(), 1) if 'style.textContent = "body {}"' in line),
                  'rule': 'Only exact WebKit body {} mutation plus matching CSP denial during screenshot; no driver/CSP bypass; other errors fatal.'},
              'accessibility_limits': 'DOM/keyboard/focus/modal/44px/overflow smoke only; no physical Safari or screen-reader acceptance.',
              'cases': []}
    with sync_playwright() as playwright:
        for engine in ['chromium', 'webkit']:
            browser = getattr(playwright, engine).launch()
            for viewport in VIEWPORTS:
                case = run_case(browser, engine, viewport, args.url, out, catalog_baseline)
                report['cases'].append(case)
                print(engine, case['viewport'], case['result'], len(case['checks']), case['error'], flush=True)
            browser.close()
    report['result'] = 'PASS' if len(report['cases']) == 8 and all(case['result'] == 'PASS' for case in report['cases']) else 'FAIL'
    report['check_count'] = sum(len(case['checks']) for case in report['cases'])
    report['finished_utc'] = dt.datetime.now(dt.timezone.utc).isoformat()
    (out / 'summary.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(report['result'], report['check_count'], str(out / 'summary.json'), flush=True)
    return 0 if report['result'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
