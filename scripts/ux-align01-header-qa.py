#!/usr/bin/env python3
"""Native resolver + ORIGINAL header/session/operator components, synthetic browser interception.

No listener, runtime configuration, real cookie, MySQL/Valkey or persistent fixture.
The HTML under test is the original private-app header rendered by PHP; it is not
a second product page. This deliberately does not claim full private-app E2E.
"""
import argparse
import copy
import hashlib
import json
import mimetypes
import os
from pathlib import Path
import re
import subprocess
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright, expect

ROOT = Path(__file__).resolve().parents[1]
ORIGIN = 'https://ux-align01-header.example.invalid'
SIZES = ((1440, 900), (1366, 768), (820, 1180))
SOURCES = ('modules/identity/http/PrivateApplicationAccessResolver.php',
           'modules/identity/http/CanonicalClinicalIdentityResolver.php',
           'modules/identity/operators/OperatorHttp.php', 'private-app.php',
           'assets/js/core/header-account.js', 'assets/js/core/private-session.js',
           'assets/js/agenda/canonical-operators.js', 'assets/css/style.css')


def manifest():
    return {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in SOURCES}


def render_header(source, operator=False):
    header = re.search(r'<header class="mx-global-header">[\s\S]*?</header>', source)
    assert header, 'Original private-app header not found'
    return subprocess.check_output(['php'], cwd=ROOT, text=True,
        input='<?php $operatorUi03=' + ('true' if operator else 'false') + '; ?>' + header.group(0))


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--evidence', type=Path,
        default=Path.home() / 'Library/Application Support/MXMED/ux-align01/header')
    args = parser.parse_args()
    os.umask(0o077)
    args.evidence.mkdir(parents=True, exist_ok=True)
    pinned = manifest()
    native = json.loads(subprocess.check_output(['php', str(ROOT /
        'modules/identity/tests/UxAlign01HeaderProjectionTest.php'), '--json'], cwd=ROOT, text=True))
    assert native['result'] == 'PASS'
    contexts = native['contexts']
    source = (ROOT / 'private-app.php').read_text()
    bootstrap_css = re.search(r'<link href="https://cdn\.jsdelivr\.net/npm/bootstrap@5\.3\.3/dist/css/bootstrap\.min\.css"[^>]*>', source)
    assert bootstrap_css, 'Original Bootstrap stylesheet not found'
    font_links = ''.join(re.findall(r'<link[^>]*href="https://fonts\.googleapis\.com/[^>]+>', source))
    assert 'Material+Symbols+Rounded' in font_links and 'family=Asap' in font_links
    header = render_header(source)
    operator_header = render_header(source, True)
    # The real nonmedical branch never loads the physician renderer/clinical bundles.
    operator_scripts = source.split('<?php if ($operatorUi03): ?>\n<script src="/assets/js/core/navigation.js', 1)[1].split('<?php else: ?>', 1)[0]
    assert 'header-account.js' not in operator_scripts and '/assets/js/clinical/' not in operator_scripts
    assert 'canonical-operators.js' in operator_scripts and 'agenda-workspace.js' in operator_scripts
    results = []
    with sync_playwright() as p:
        for engine in ('chromium', 'webkit'):
            browser = getattr(p, engine).launch()
            for width, height in SIZES:
                context = browser.new_context(viewport={'width': width, 'height': height})
                page = context.new_page()
                errors, requests = [], []
                page.on('pageerror', lambda error: errors.append(str(error)))
                current = {'context': contexts['endocrinology'], 'operator': False}

                def html():
                    boot = {**current['context'], 'csrf_token': 'synthetic-only-header-csrf'}
                    encoded = json.dumps(boot, ensure_ascii=False).replace('<', '\\u003c')
                    bounded = current['operator']
                    # Original PHP header, original CSS and JS. Only the isolated test
                    # scaffolding/API responses below are synthetic.
                    return ('<!doctype html><html lang="es" class="mxmed-auth-checking"><head>'
                        '<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
                        + bootstrap_css.group(0) + font_links + '<link rel="stylesheet" href="/assets/css/style.css">'
                        '<link rel="stylesheet" href="/assets/css/mxmed-admin-visual-primitives.css">'
                        '<style>html.mxmed-auth-checking body{visibility:hidden}.dropdown-menu{display:none}</style>'
                        '<script id="mxmed-private-bootstrap" type="application/json">' + encoded + '</script>'
                        '<script src="/assets/js/core/private-session.js"></script></head>'
                        '<body class="mx-sidebar-collapsed" data-shell-color-mode="turquoise-shell-blue-patient"' +
                        (' data-operator-composition="canonical"' if bounded else '') + '>' +
                        (operator_header if bounded else header) +
                        '<span id="fs-esp" hidden>Especialidad de navegador que no es autoridad</span>' +
                        '<main id="viewport"><section id="p-ag-admin"></section></main>' +
                        ('<div id="operator-inbox-list" hidden></div><div id="operator-offer-modal" hidden>'
                         '<button id="operator-offer-accept"></button><button id="operator-offer-reject"></button></div>'
                         '<script>window.mxmedInitializeOperatorsPanel=()=>null;</script>'
                         '<script src="/assets/js/agenda/canonical-operators.js"></script>' if bounded else '') +
                        # Inject physician renderer additionally in the operator fixture to
                        # verify its defensive scope guard; real operator branch omits it.
                        '<script src="/assets/js/core/header-account.js"></script></body></html>')

                def route_request(route):
                    request = route.request
                    parsed = urlsplit(request.url)
                    requests.append({'path': parsed.path, 'method': request.method})
                    if request.url == 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css':
                        route.continue_(); return
                    if request.method == 'GET' and parsed.netloc in ('fonts.googleapis.com', 'fonts.gstatic.com'):
                        route.continue_(); return
                    if parsed.netloc != urlsplit(ORIGIN).netloc:
                        route.abort(); return
                    if parsed.path == '/private-app.php':
                        route.fulfill(status=200, body=html(), content_type='text/html'); return
                    if parsed.path == '/api/identity/index.php/current-session' and request.method == 'GET':
                        route.fulfill(status=200, json={'authenticated': True, 'state': 'AUTHENTICATED',
                            'account_status': 'active', 'application_access': {'allowed': True, 'context': current['context']},
                            'csrf_token': 'synthetic-only-header-csrf'}); return
                    if parsed.path == '/api/profile-operators/index.php/bootstrap' and request.method == 'GET' and current['operator']:
                        route.fulfill(status=200, json={'ok': True, 'data': current['context']['operator']}); return
                    if parsed.path.startswith('/assets/'):
                        asset = (ROOT / parsed.path.lstrip('/')).resolve()
                        if asset.is_relative_to(ROOT / 'assets') and asset.is_file():
                            route.fulfill(status=200, body=asset.read_bytes(), content_type=mimetypes.guess_type(asset)[0] or 'application/octet-stream'); return
                    # Unknown API or mutation is never forwarded to a live service.
                    route.fulfill(status=403, json={'ok': False, 'error': 'isolated_fixture_denied'})

                context.route('**/*', route_request)
                for name, projected in contexts.items():
                    if name == 'operator':
                        continue
                    current['context'], current['operator'] = projected, False
                    page.goto(ORIGIN + '/private-app.php', wait_until='load')
                    page.evaluate('document.fonts.ready')
                    page.wait_for_function("mxmedPrivateSession.state==='authenticated'")
                    expected = projected['professional']['professional_designation'] or 'Perfil profesional'
                    expect(page.locator('.mx-gh-identity-name-text')).to_have_text(projected['professional']['display_name'])
                    expect(page.locator('.mx-gh-identity-role')).to_have_text(expected)
                    assert page.evaluate("mxmedPrivateSession.context.professional.role==='collaborator' && mxmedDoctor.role==='collaborator' && mxmedStore.activeProfessionalContext.role==='collaborator'")
                    assert not re.search(r'collaborator|role_code|\bowner\b', page.locator('.mx-global-header').inner_text())
                    # Browser summaries and local storage are never denomination sources.
                    page.evaluate("document.getElementById('fs-esp').textContent='collaborator';localStorage.setItem('mxmed.dev.rbac.role','owner')")
                    page.wait_for_timeout(30)
                    expect(page.locator('.mx-gh-identity-role')).to_have_text(expected)
                    assert page.evaluate("mxmedPrivateSession.context.professional.role==='collaborator'")
                    assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+2'), (engine, width, name)
                    assert page.locator('.mx-global-header').count() == 1
                    if name == 'endocrinology':
                        page.screenshot(path=str(args.evidence / f'{engine}-{width}x{height}-physician.png'))
                    results.append({'engine': engine, 'viewport': [width, height], 'case': name,
                        'original_header': 'PASS', 'canonical_designation': 'PASS', 'authority_unchanged': 'PASS',
                        'browser_summary_ignored': 'PASS', 'horizontal_overflow': False})

                # Real private-session manager's validation applies a new native DTO,
                # then emits the existing canonical session-authenticated event.
                current['context'] = contexts['dentistry']
                assert page.evaluate("mxmedPrivateSession.validate('header-projection-regression')")
                expect(page.locator('.mx-gh-identity-role')).to_have_text('Cirujano dentista')
                expect(page.locator('.mx-gh-identity-name-text')).to_have_text(contexts['dentistry']['professional']['display_name'])
                assert page.evaluate("mxmedDoctor.role==='collaborator'")
                results.append({'engine': engine, 'viewport': [width, height], 'case': 'canonical_session_refresh', 'result': 'PASS'})

                current['context'] = copy.deepcopy(contexts['operator'])
                current['operator'] = True
                page.goto(ORIGIN + '/private-app.php', wait_until='load')
                page.evaluate('document.fonts.ready')
                page.wait_for_function("mxmedPrivateSession.state==='authenticated' && !!mxmedCanonicalOperators")
                page.evaluate('mxmedCanonicalOperators.whenReady')
                expect(page.locator('.mx-gh-identity-name-text')).to_have_text('Mary')
                expect(page.locator('.mx-gh-identity-role')).to_have_text('Operador · Otro perfil independiente')
                assert page.evaluate("mxmedPrivateSession.context.workspace==='agenda_operators' && mxmedPrivateSession.context.professional.role==='operator' && Object.keys(mxmedPrivateSession.context.capabilities).length===0")
                assert not page.locator('#p-expediente,#p-pac-recetas,[data-account-panel=p-suscripcion]').count()
                # Canonical manager refresh must not replace personal alias with the physician.
                assert page.evaluate("mxmedPrivateSession.validate('operator-header-refresh')")
                expect(page.locator('.mx-gh-identity-name-text')).to_have_text('Mary')
                expect(page.locator('.mx-gh-identity-role')).to_have_text('Operador · Otro perfil independiente')
                assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+2')
                page.screenshot(path=str(args.evidence / f'{engine}-{width}x{height}-operator.png'))
                results.append({'engine': engine, 'viewport': [width, height], 'case': 'bounded_operator_personal_identity', 'result': 'PASS'})
                assert not errors, errors
                assert all(r['method'] == 'GET' for r in requests), 'Unexpected write request from presentation'
                context.close()
                print(f'UX_ALIGN01_HEADER_{engine}_{width}x{height}=PASS', flush=True)
            browser.close()
    assert manifest() == pinned, 'Source changed during acceptance; rerun on stable source'
    output = {'result': 'PASS', 'scope': 'NATIVE_SQLITE_MEMORY_RESOLVER_AND_ORIGINAL_COMPONENT_BROWSER_NOT_FULL_APP_E2E',
        'native_checks': native['checks'], 'native_check_count': len(native['checks']), 'browser_cases': results,
        'source_manifest': pinned, 'normal_local_accessed': False, 'persistent_fixture_mutations': False}
    (args.evidence / 'result.json').write_text(json.dumps(output, indent=2, ensure_ascii=False) + '\n')
    print('UX_ALIGN01_HEADER_NATIVE_CHECKS=' + str(len(native['checks'])))
    print('UX_ALIGN01_HEADER_BROWSER_CASES=' + str(len(results)))
    print('UX_ALIGN01_HEADER=PASS_NATIVE_AND_ORIGINAL_COMPONENTS_ONLY')


if __name__ == '__main__':
    main()
