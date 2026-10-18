#!/usr/bin/env python3
"""Synthetic UI-3 browser checks. Never connects to Identity, SQL or the original app.

Requires the already installed Python Playwright browsers. Evidence is synthetic.
WebKit is engine QA, not physical Safari acceptance. Mobile is interim smoke only.
"""
import argparse
import datetime as dt
import hashlib
import json
from pathlib import Path
import subprocess
import sys
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright


ROOT = Path(__file__).resolve().parents[2]
VIEWPORTS = [(1440, 900), (1366, 768), (820, 1180), (390, 844)]
ROUTES = {'/', '/style.css', '/catalog.js', '/app.js', '/assets/css/media-review-inbox.css'}
FUTURE = {'profile_create', 'profile_curate', 'profile_publication_manage', 'profile_support_assist'}
RESTRICTED = {'public_profile_correction_review', 'public_profile_correction_apply'}
DELEGATED = {'media_review_read', 'media_review_approve', 'physician_accreditation_review'}


def git(*args):
    return subprocess.check_output(['git', *args], cwd=ROOT, text=True).strip()


def run_case(browser, engine, viewport, base, evidence):
    width, height = viewport
    context = browser.new_context(viewport={'width': width, 'height': height}, locale='es-MX', reduced_motion='reduce')
    page = context.new_page()
    checks, screenshots, requests, errors, forbidden = [], [], [], [], []
    capture_diagnostics, capture_messages = [], []
    capture_active = False
    webkit_capture_warning = "Refused to apply a stylesheet because its hash, its nonce, or 'unsafe-inline' does not appear in the style-src directive of the Content Security Policy."
    origin = urlsplit(base)

    def route(request_route):
        request = request_route.request
        parsed = urlsplit(request.url)
        entry = {'method': request.method, 'path': parsed.path, 'origin': f'{parsed.scheme}://{parsed.netloc}'}
        requests.append(entry)
        if (parsed.scheme, parsed.netloc) != (origin.scheme, origin.netloc) or parsed.path not in ROUTES or request.method != 'GET':
            forbidden.append(entry)
            request_route.abort()
        else:
            request_route.continue_()

    context.route('**/*', route)
    page.on('pageerror', lambda error: errors.append(str(error)))
    def console(message):
        if message.type != 'error':
            return
        if capture_active and engine == 'webkit' and message.text == webkit_capture_warning:
            capture_messages.append(message.text)
        else:
            errors.append(message.text)

    page.on('console', console)
    context.add_init_script("""(() => {
      window.__storageAttempts = [];
      window.__injectedStyles = [];
      new MutationObserver(records => records.forEach(record => record.addedNodes.forEach(node => {
        if (node.nodeName === 'STYLE') window.__injectedStyles.push(node.textContent);
      }))).observe(document, {childList:true,subtree:true});
      window.__dialogCloses = 0;
      window.addEventListener('DOMContentLoaded', () => document.querySelector('#confirmation').addEventListener('close', () => window.__dialogCloses++));
      for (const method of ['setItem','removeItem','clear']) {
        const original = Storage.prototype[method];
        Storage.prototype[method] = function(...args) { window.__storageAttempts.push('Storage.'+method); return original.apply(this,args); };
      }
      if (window.indexedDB) { const original = indexedDB.open.bind(indexedDB); indexedDB.open = (...args) => { window.__storageAttempts.push('indexedDB.open'); return original(...args); }; }
      if (navigator.serviceWorker) { const original = navigator.serviceWorker.register.bind(navigator.serviceWorker); navigator.serviceWorker.register = (...args) => { window.__storageAttempts.push('serviceWorker.register'); return original(...args); }; }
    })();""")

    def check(name, condition, detail=None):
        checks.append({'name': name, 'pass': bool(condition), **({'detail': detail} if detail is not None else {})})
        if not condition:
            raise AssertionError(f'{engine} {width}x{height}: {name}; {detail}')

    def expect_text(selector, fragment, name):
        text = page.locator(selector).inner_text()
        check(name, fragment in text, text)

    def shot(name):
        nonlocal capture_active
        path = evidence / f'{engine}-{width}x{height}-{name}.png'
        before_styles = page.evaluate('window.__injectedStyles.length')
        before_messages = len(capture_messages)
        capture_active = True
        try:
            page.screenshot(path=str(path), full_page=True)
            new_styles = page.evaluate('(start) => window.__injectedStyles.slice(start)', before_styles)
            new_messages = capture_messages[before_messages:]
            # Playwright's WebKit screenshot synchronization injects and removes
            # exactly `body {}` (driver/lib/server/screenshotter.js). The strict
            # CSP correctly refuses it. Preserve that tool diagnostic separately;
            # never ignore arbitrary inline-style violations or runtime errors.
            if engine == 'webkit' and new_styles == ['body {}'] and new_messages == [webkit_capture_warning]:
                capture_diagnostics.append({'screenshot': name, 'source': 'Playwright WebKit screenshot syncAnimations', 'timing': 'Only during page.screenshot; observed DOM mutation and matching console event.', 'injected_style': 'body {}', 'csp_rejected': True, 'message': webkit_capture_warning})
            elif new_messages:
                errors.extend(new_messages)
        finally:
            capture_active = False
        screenshots.append(str(path.relative_to(ROOT)) if path.is_relative_to(ROOT) else str(path))

    def select(advisor):
        card = page.locator(f'[data-advisor="{advisor}"]')
        card.focus()
        page.keyboard.press('Enter')

    def save_with_keyboard():
        page.locator('#save-permissions').focus()
        page.keyboard.press('Enter')

    def lifecycle_with_keyboard():
        page.locator('#lifecycle').focus()
        page.keyboard.press('Enter')

    def focus_check(selector, name):
        try:
            page.wait_for_function('(selector) => document.querySelector(selector) === document.activeElement', arg=selector, timeout=2000)
            focused = True
        except Exception:
            focused = False
        check(name, focused, page.evaluate('({tag:document.activeElement.tagName,id:document.activeElement.id})'))

    def close_dialog(action):
        # Native dialog close is dispatched asynchronously after focus restoration.
        # Await its real event; do not race another user action into that interval.
        previous = page.evaluate('window.__dialogCloses')
        action()
        page.wait_for_function('(previous) => window.__dialogCloses > previous', arg=previous, timeout=2000)

    def reveal_review():
        page.locator('.review-controls').evaluate('(element) => element.open = true')

    def choose_mode(value):
        reveal_review()
        page.locator('#authority-view').select_option(value)

    def choose_state(value):
        reveal_review()
        page.locator('#directory-state').select_option(value)

    def cap(key):
        return page.locator(f'[data-capability="{key}"]')

    def checked_keys():
        return set(page.locator('[data-capability]:checked').evaluate_all('(nodes) => nodes.map(n => n.dataset.capability)'))

    try:
        response = page.goto(base, wait_until='networkidle')
        check('Static prototype loads with HTTP 200', response.status == 200)
        check('CSP prohibits backend connections and form submission', "connect-src 'none'" in response.headers.get('content-security-policy', '') and "form-action 'none'" in response.headers.get('content-security-policy', ''))
        check('UI is explicitly non-operational', 'PROTOTIPO — NO OPERATIVO' in page.locator('body').inner_text())
        check('Spanish page and exact heading', page.locator('html').get_attribute('lang') == 'es' and page.locator('h1').inner_text() == 'ASESORES Y PERMISOS')
        check('Page source anchor identifies isolated branch', 'prototype/ase-vis01-advisor-capability-matrix' in page.locator('.preview-footer').text_content())
        catalog = page.evaluate('window.ASE_VIS01_CATALOG')
        operational = {key for key, value in catalog.items() if value['kind'] == 'operational' and value['delegable']}
        reserved = set(catalog) - operational
        present = set(page.locator('[data-capability]').evaluate_all('(nodes) => nodes.map(n => n.dataset.capability)'))
        check('Catalog exactly represented: 18 definitions, 16 independent operational checkboxes', len(catalog) == 18 and len(operational) == 16 and present == operational)
        check('Two governance authorities have no operational checkboxes', len(reserved) == 2 and not (present & reserved))
        check('Operational groups cover all rendered capabilities exactly once', page.locator('[data-capability]').count() == len(present))
        check('A: one-capability fictional advisor', page.locator('#detail-title').inner_text() == 'Aurora Naranjo' and checked_keys() == {'media_review_read'})
        check('Selected advisor remains visibly and semantically selected', page.locator('[data-advisor="aurora"]').get_attribute('aria-pressed') == 'true')
        check('Four future functions remain read-only', all(cap(key).is_disabled() for key in FUTURE))
        check('N: Future workflow gap explained', page.locator('.availability').filter(has_text='Sin flujo conectado').count() == 4)
        check('Two class-restricted consumers remain read-only', all(cap(key).is_disabled() for key in RESTRICTED))
        check('Director can edit only ten currently represented eligible functions', page.locator('[data-capability]:enabled').count() == 10)
        check('Native labels and descriptions are associated with all checkboxes', page.locator('[data-capability]').evaluate_all("""nodes => nodes.every(n => n.labels.length === 1 && n.labels[0].textContent.trim() && n.getAttribute('aria-describedby') && document.getElementById(n.getAttribute('aria-describedby')))"""))
        check('Individual capability label click targets are at least 44px high', page.locator('.capability-row').evaluate_all('nodes => nodes.every(n => n.getBoundingClientRect().height >= 44)'))
        check('Checkbox technical identifiers are absent from visible product text', all(key not in page.locator('body').inner_text() for key in catalog))
        overflow = page.evaluate('({width:innerWidth, document:document.documentElement.scrollWidth, body:document.body.scrollWidth})')
        check('Viewport has no horizontal document overflow', max(overflow['document'], overflow['body']) <= width, overflow)
        positions = page.locator('.workspace-columns').evaluate("e => ({columns:getComputedStyle(e).gridTemplateColumns,children:Array.from(e.children).map(c=>({x:c.getBoundingClientRect().x,y:c.getBoundingClientRect().y,width:c.getBoundingClientRect().width}))})")
        check('Responsive composition retains desktop/tablet columns or mobile stack', positions['children'][1]['x'] > positions['children'][0]['x'] if width > 640 else positions['children'][1]['y'] > positions['children'][0]['y'], positions)
        shot('directory-matrix')

        page.keyboard.press('Tab')
        if engine == 'webkit':
            # WebKit on macOS inherits the platform option to skip links with Tab.
            # Test its real focus/activation; do not equate headless engine policy
            # with the Director's Safari Full Keyboard Access preference.
            page.locator('.skip-link').focus()
            check('Skip link accepts focus (WebKit platform Tab policy documented)', page.locator('.skip-link').evaluate('(e) => e === document.activeElement'))
        else:
            check('Keyboard reaches the skip link first', page.locator('.skip-link').evaluate('(e) => e === document.activeElement'))
        outline = page.locator('.skip-link').evaluate('e => ({style:getComputedStyle(e).outlineStyle,width:getComputedStyle(e).outlineWidth})')
        check('Keyboard focus has a visible outline', outline['style'] != 'none' and float(outline['width'].replace('px', '')) >= 2, outline)
        page.keyboard.press('Enter')
        check('Skip link focuses advisor detail', page.locator('#advisor-detail').evaluate('(e) => e === document.activeElement'))
        check('Status regions are announced politely', page.locator('#feedback').get_attribute('aria-live') == 'polite' and page.locator('#unsaved').get_attribute('role') == 'status')

        initial_url = page.url
        select('bruno')
        check('B: three independent permissions and same-page selection', len(checked_keys()) == 3 and page.url == initial_url and page.locator('#detail-title').inner_text() == 'Bruno Lago')
        check('G: selection moves focus to the new advisor heading', page.locator('#detail-title').evaluate('(e) => e === document.activeElement'))
        select('cora')
        check('C: all sixteen operational permissions can be represented without arbitrary maximum', checked_keys() == operational)
        select('diego')
        check('D: active advisor with zero grants', not checked_keys() and 'Activo' in page.locator('.detail-header').inner_text())
        select('elisa')
        check('E: suspended advisor preserves two grants and blocks editing', len(checked_keys()) == 2 and page.locator('[data-capability]:enabled').count() == 0 and page.locator('#lifecycle').inner_text() == 'REACTIVAR ASESOR')
        choose_mode('master')
        select('aurora')
        enabled = set(page.locator('[data-capability]:enabled').evaluate_all('(nodes) => nodes.map(n => n.dataset.capability)'))
        check('Master administration is limited to three exact delegated capabilities', enabled == DELEGATED, sorted(enabled))
        choose_mode('missing')
        check('F: Master lacking management authority cannot modify any grant or lifecycle', page.locator('[data-capability]:enabled').count() == 0 and page.locator('#lifecycle').is_disabled())
        expect_text('.authority-note', 'no tiene autorización', 'Missing management authority explained')
        choose_mode('self')
        check('Master cannot administer self or change own lifecycle', page.locator('#detail-title').inner_text() == 'Mar Solano' and page.locator('[data-capability]:enabled').count() == 0 and page.locator('#lifecycle').is_disabled())
        choose_mode('director')
        select('aurora')

        cap('media_review_read').uncheck()
        cap('media_review_approve').check()
        expect_text('#unsaved', '2 cambios sin guardar', 'H: grant and revocation remain explicitly unsaved')
        check('Dirty proposal blocks lifecycle until resolved', page.locator('#lifecycle').is_disabled())
        check('Unsaved grant does not change directory saved count', '1 permiso' in page.locator('[data-advisor="aurora"]').inner_text())
        save_with_keyboard()
        check('I: explicit save opens labelled native confirmation dialog', page.locator('#confirmation').evaluate('e => e.open') and page.locator('#confirmation').get_attribute('aria-labelledby') == 'confirmation-title' and page.locator('#confirmation').get_attribute('aria-describedby') == 'confirmation-description')
        expect_text('#confirmation-body', 'Conceder (1)', 'Confirmation separates exact grants')
        expect_text('#confirmation-body', 'Revocar (1)', 'Confirmation separates exact revocations')
        expect_text('#confirmation-description', 'sólo actualiza la simulación', 'Confirmation describes mock scope')
        check('Dialog focuses non-destructive cancel first', page.locator('#cancel-confirmation').evaluate('e => e === document.activeElement'))
        for key in ['Tab', 'Tab', 'Shift+Tab', 'Shift+Tab']:
            page.keyboard.press(key)
            check(f'Modal focus remains trapped after {key}', page.locator('#confirmation').evaluate('e => e.contains(document.activeElement)'))
        shot('save-confirmation')
        close_dialog(lambda: page.keyboard.press('Escape'))
        check('Native Escape cancels dialog', not page.locator('#confirmation').evaluate('e => e.open'))
        focus_check('#save-permissions', 'Escape restores keyboard-triggering save focus')
        expect_text('#unsaved', '2 cambios sin guardar', 'Escape preserves draft')
        select('bruno')
        expect_text('#confirmation-title', 'Descartar', 'Changing selection protects unsaved proposal')
        close_dialog(lambda: page.locator('#cancel-confirmation').click())
        check('Cancelling selection change preserves selected advisor and draft', page.locator('#detail-title').inner_text() == 'Aurora Naranjo' and checked_keys() == {'media_review_approve'})
        focus_check('[data-advisor="bruno"]', 'Cancel restores keyboard-triggering directory focus')
        page.locator('#revert').click()
        check('Revert restores saved grant without backend activity', checked_keys() == {'media_review_read'} and page.locator('#save-permissions').is_disabled())
        focus_check('#detail-title', 'Revert retains useful focus after replacing detail controls')
        cap('media_review_read').uncheck()
        cap('media_review_approve').check()
        save_with_keyboard()
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        expect_text('#feedback', 'Simulación guardada. Concedidos: 1. Revocados: 1.', 'J: grant and revoke feedback is explicit simulation')
        expect_text('#feedback', 'No hubo cambios reales', 'No canonical server success is claimed')
        check('In-memory save updates saved state and clears dirty flag', checked_keys() == {'media_review_approve'} and page.locator('#save-permissions').is_disabled())
        focus_check('#detail-title', 'Confirmed save moves focus to the replaced detail heading')
        page.locator('.history').evaluate('(e) => e.open = true')
        history = page.locator('#history-list').inner_text()
        check('O: five lifecycle history examples plus simulated grant/revoke entries remain discoverable', page.locator('#history-list li').count() == 7 and all(fragment in history for fragment in ['registrado', 'concedido', 'revocado', 'suspendido', 'reactivado']))
        shot('simulation-history')

        cap('media_review_read').check()
        reveal_review()
        page.locator('#simulate-conflict').check()
        save_with_keyboard()
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        check('K: stale version rejects save, preserves pending proposal and disables save', page.locator('#conflict-banner').is_visible() and len(checked_keys()) == 2 and page.locator('#save-permissions').is_disabled() and page.locator('#revert').is_disabled())
        expect_text('#feedback', 'no se aplicó ningún cambio', 'Conflict does not claim a successful update')
        focus_check('#conflict-banner', 'Conflict focuses its recovery alert after replacing the form')
        check('Conflict leaves saved directory count unchanged', '1 permiso' in page.locator('[data-advisor="aurora"]').inner_text())
        shot('conflict')
        page.locator('#refresh-conflict').click()
        check('Explicit conflict refresh discards proposal and restores current saved state', not page.locator('#conflict-banner').is_visible() and checked_keys() == {'media_review_approve'} and not page.locator('#lifecycle').is_disabled())

        lifecycle_with_keyboard()
        expect_text('#confirmation-description', 'conserva todos sus permisos e historial', 'Suspension confirmation explains preservation')
        close_dialog(lambda: page.locator('#cancel-confirmation').click())
        check('Cancelling suspension retains active status', page.locator('#lifecycle').inner_text() == 'SUSPENDER ASESOR')
        focus_check('#lifecycle', 'Cancelling lifecycle confirmation restores its keyboard trigger')
        lifecycle_with_keyboard()
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        check('Suspension changes only fictional status and preserves grants/history', checked_keys() == {'media_review_approve'} and page.locator('#lifecycle').inner_text() == 'REACTIVAR ASESOR' and page.locator('[data-capability]:enabled').count() == 0 and page.locator('#history-list li').count() == 8)
        focus_check('#detail-title', 'Confirmed suspension restores useful focus in replaced detail')
        expect_text('#feedback', 'Suspensión simulada', 'Suspension is explicitly simulated')
        lifecycle_with_keyboard()
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        check('Reactivation preserves saved grants/history and enables eligible editing', checked_keys() == {'media_review_approve'} and page.locator('#lifecycle').inner_text() == 'SUSPENDER ASESOR' and page.locator('[data-capability]:enabled').count() == 10 and page.locator('#history-list li').count() == 9)
        focus_check('#detail-title', 'Confirmed reactivation restores useful focus in replaced detail')

        cap('media_review_read').check()
        select('bruno')
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        check('Explicit discard switches selected advisor without applying draft', page.locator('#detail-title').inner_text() == 'Bruno Lago' and len(checked_keys()) == 3 and '1 permiso' in page.locator('[data-advisor="aurora"]').inner_text())
        page.locator('#advisor-search').fill('AURORA')
        check('Name search is case-insensitive', page.locator('[data-advisor]').count() == 1 and page.locator('[data-advisor="aurora"]').count() == 1)
        page.locator('#advisor-search').fill('Ninguna persona ficticia')
        expect_text('#directory-content', 'Sin resultados', 'M: no-results state is explicit')
        page.locator('#advisor-search').fill('')
        choose_state('empty')
        expect_text('#directory-content', 'Aún no hay asesores', 'L: empty directory state')
        expect_text('#advisor-detail', 'Selecciona un asesor', 'Empty directory has coherent empty detail')
        choose_state('loading')
        check('Loading state is busy and announced', page.locator('#directory-content').get_attribute('aria-busy') == 'true' and page.locator('#directory-content [role="status"]').count() == 1)
        choose_state('error')
        check('Directory error is announced and retry is available', page.locator('#directory-content [role="alert"]').count() == 1 and page.locator('#retry-directory').is_enabled())
        shot('directory-error')
        page.locator('#retry-directory').click()
        check('Retry restores fictional directory without re-registration', page.locator('[data-advisor]').count() == 6 and page.locator('#directory-state').input_value() == 'ready')
        page.locator('#add-advisor').click()
        expect_text('#confirmation-description', 'Identity ya existente', 'Add advisor describes existing-account association')
        check('Add advisor has no account-creation or real submit control', page.locator('#accept-confirmation').is_hidden() and page.locator('#confirmation input').count() == 0)
        close_dialog(lambda: page.keyboard.press('Escape'))
        check('Placeholder closes without creating fictional extra advisor', page.locator('[data-advisor]').count() == 6)
        metrics = page.evaluate("""() => ({
          overflow:document.documentElement.scrollWidth > innerWidth,
          undersized:Array.from(document.querySelectorAll('button,select,input[type=search],summary')).filter(e=>e.getClientRects().length && !e.closest('dialog:not([open])') && e.getBoundingClientRect().height < 43.9).map(e=>({id:e.id,text:e.textContent.trim(),height:e.getBoundingClientRect().height})),
          storageAttempts:window.__storageAttempts,
          local:localStorage.length, session:sessionStorage.length, cookies:document.cookie,
          forms:Array.from(document.forms).map(f=>({action:f.getAttribute('action'),method:f.getAttribute('method')}))
        })""")
        check('All displayed controls meet 44px minimum height', not metrics['undersized'], metrics['undersized'])
        check('All states preserve viewport width', not metrics['overflow'])
        check('No browser persistence attempted; storage and cookies stay empty', not metrics['storageAttempts'] and metrics['local'] == 0 and metrics['session'] == 0 and not metrics['cookies'], metrics)
        check('No session cookies or origins persisted in context', context.storage_state() == {'cookies': [], 'origins': []})
        check('Only allowlisted local GET resources were requested', not forbidden and requests and all(request['method'] == 'GET' for request in requests), requests)
        check('No application script/console errors; exact verified capture-tool diagnostic separated', not errors, errors)
        # Reload must discard all previously simulated changes, demonstrating page memory scope.
        page.reload(wait_until='networkidle')
        check('Reload discards all mock administration changes', checked_keys() == {'media_review_read'} and page.locator('#history-list li').count() == 5 and page.locator('#lifecycle').inner_text() == 'SUSPENDER ASESOR')
        # Regression for a close event queued by a previous confirmation: a rapid
        # reopen must retain its new action, not let the old close handler clear it.
        before_rapid_close = page.evaluate('window.__dialogCloses')
        page.evaluate("""() => {
          document.querySelector('[data-capability="media_review_approve"]').click();
          document.querySelector('#save-permissions').click();
          document.querySelector('#cancel-confirmation').click();
          document.querySelector('#save-permissions').click();
        }""")
        page.wait_for_function('(previous) => window.__dialogCloses > previous', arg=before_rapid_close, timeout=2000)
        check('Rapid reopening survives the previous queued dialog close event', page.locator('#confirmation').evaluate('e => e.open') and 'Confirmar propuesta' in page.locator('#confirmation-title').inner_text())
        close_dialog(lambda: page.locator('#accept-confirmation').click())
        check('Rapidly reopened confirmation retains and executes its own mock action', checked_keys() == {'media_review_read', 'media_review_approve'} and page.locator('#save-permissions').is_disabled() and 'Concedidos: 1.' in page.locator('#feedback').inner_text())
        check('Rapid-reopen regression causes no application script errors or forbidden requests', not errors and not forbidden, errors)
        return {'engine': engine, 'viewport': f'{width}x{height}', 'result': 'PASS', 'checks': checks, 'screenshots': screenshots, 'requests': requests, 'page_errors': errors, 'capture_tool_diagnostics': capture_diagnostics, 'mobile_classification': 'INTERIM_SMOKE_ONLY' if width == 390 else None}
    except Exception as error:
        try:
            shot('failure')
        except Exception:
            pass
        return {'engine': engine, 'viewport': f'{width}x{height}', 'result': 'FAIL', 'error': str(error), 'checks': checks, 'screenshots': screenshots, 'requests': requests, 'page_errors': errors, 'capture_tool_diagnostics': capture_diagnostics, 'forbidden_requests': forbidden}
    finally:
        context.close()


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--url', default='http://127.0.0.1:8140/')
    parser.add_argument('--evidence', type=Path, required=True)
    args = parser.parse_args()
    parsed = urlsplit(args.url)
    if parsed.scheme != 'http' or parsed.hostname != '127.0.0.1' or not parsed.port or parsed.port < 8140 or parsed.path not in {'', '/'} or parsed.query or parsed.fragment or parsed.username or parsed.password:
        parser.error('Only a loopback HTTP prototype root at port >= 8140 is permitted.')
    if git('branch', '--show-current') != 'prototype/ase-vis01-advisor-capability-matrix':
        parser.error('Run only inside the isolated ASE-VIS01 prototype worktree.')
    evidence = args.evidence.resolve()
    evidence.mkdir(parents=True, exist_ok=True)
    source_paths = ['prototypes/ase-vis01/' + name for name in ['index.html', 'style.css', 'app.js', 'catalog.js', 'serve.py', 'qa.py', 'export-catalog.php']]
    source_paths += ['assets/css/media-review-inbox.css', 'modules/identity/services/InternalCapabilityCatalog.php']
    hashes = {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in source_paths}
    driver_path = Path(__import__('playwright').__file__).resolve().parent / 'driver/package/lib/server/screenshotter.js'
    driver_source = driver_path.read_bytes()
    driver_lines = driver_source.decode().splitlines()
    capture_tool = {'driver_path': str(driver_path), 'sha256': hashlib.sha256(driver_source).hexdigest(), 'function': 'inPagePrepareForScreenshots', 'body_style_line': next(i for i, line in enumerate(driver_lines, 1) if 'style.textContent = "body {}"' in line), 'qualification': 'WebKit-only screenshot synchronization attempts an inline body {} style. Strict CSP rejects it. A capture diagnostic is accepted only when exact text, DOM style mutation and screenshot timing match; all other errors remain fatal. No CSP bypass or driver modification.'}
    report = {'task': 'ASE-VIS01', 'started_utc': dt.datetime.now(dt.timezone.utc).isoformat(), 'branch': git('branch', '--show-current'), 'commit_at_test': git('rev-parse', 'HEAD'), 'commit_binding': 'Candidate tested before commit; verify these file digests against the final commit tree.', 'source_sha256': hashes, 'url': args.url, 'scope': 'Synthetic static UI only; no original runtime, API or database access.', 'physical_safari': 'NOT_RUN', 'final_visual_approval': False, 'mobile': 'INTERIM_SMOKE_ONLY', 'accessibility_limits': 'DOM/keyboard/focus/label/status smoke only, not a screen-reader or full accessibility audit. macOS WebKit may skip links on Tab according to platform keyboard preferences; skip link focus and activation are tested explicitly.', 'cases': []}
    report['capture_tool_qualification'] = capture_tool
    with sync_playwright() as playwright:
        for engine in ['chromium', 'webkit']:
            browser = getattr(playwright, engine).launch(headless=True)
            try:
                for viewport in VIEWPORTS:
                    case = run_case(browser, engine, viewport, args.url, evidence)
                    report['cases'].append(case)
                    print(f"{engine} {case['viewport']}: {case['result']} ({len(case['checks'])} checks)", flush=True)
                    if case['result'] == 'FAIL':
                        print(case['error'], flush=True)
            finally:
                browser.close()
    report['finished_utc'] = dt.datetime.now(dt.timezone.utc).isoformat()
    report['result'] = 'PASS' if all(case['result'] == 'PASS' for case in report['cases']) and len(report['cases']) == 8 else 'FAIL'
    report['check_count'] = sum(len(case['checks']) for case in report['cases'])
    (evidence / 'summary.json').write_text(json.dumps(report, ensure_ascii=False, indent=2) + '\n')
    print(json.dumps({'result': report['result'], 'cases': len(report['cases']), 'checks': report['check_count'], 'summary': str(evidence / 'summary.json')}), flush=True)
    return 0 if report['result'] == 'PASS' else 1


if __name__ == '__main__':
    sys.exit(main())
