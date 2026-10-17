#!/usr/bin/env python3
"""Read-only synthetic QA of the original public correction disclosure; no DB/session."""
import argparse
import functools
import hashlib
import http.server
import json
import os
import pathlib
import subprocess
import threading
from urllib.parse import quote, urlsplit

from playwright.sync_api import expect, sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[1]
FIXTURE = ROOT / 'modules/profiles/tests/PublicCorrectionTemplateFixture.php'


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument('--evidence', required=True)
    args = parser.parse_args()
    os.umask(0o077)
    out = pathlib.Path(args.evidence)
    out.mkdir(mode=0o700, parents=True, exist_ok=True)
    tested = ['profiles/doctor.php', 'assets/css/public-profile.css',
              'modules/profiles/controllers/PublicProfileController.php',
              'modules/profiles/services/PublicProfilePlanCapabilities.php',
              'modules/profiles/tests/PublicCorrectionTemplateFixture.php',
              'modules/profiles/tests/PublicCorrectionReportGateTest.php',
              'scripts/ux-align01-correction-browser-qa.py']
    manifest = {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in tested}
    results = []

    class Handler(http.server.SimpleHTTPRequestHandler):
        def log_message(self, *args):
            pass

        def do_GET(self):
            uri = urlsplit(self.path)
            path = uri.path
            if path.startswith('/api/profiles/public/doctor/'):
                path, query = '/fixture-dto', 'profile=' + quote(path.rsplit('/', 1)[1])
            elif path == '/profiles/doctor.php':
                query = uri.query
            elif path.startswith('/assets/'):
                return super().do_GET()
            else:
                self.send_error(404)
                return
            environment = {**os.environ, 'MXMED_PUBLIC_CORRECTION_SYNTHETIC_QA': '1',
                           'MXMED_PROFILES_API_BASE': self.server.origin}
            answer = subprocess.run(['php', str(FIXTURE), path, query], cwd=ROOT,
                                    env=environment, capture_output=True, text=True, timeout=20)
            if answer.returncode or answer.stderr:
                raise RuntimeError('original template fixture failed: ' + answer.stderr[-1500:])
            payload = answer.stdout.encode()
            self.send_response(200)
            self.send_header('Content-Type', 'application/json' if path == '/fixture-dto' else 'text/html; charset=utf-8')
            self.send_header('Content-Length', str(len(payload)))
            self.end_headers()
            self.wfile.write(payload)

    server = http.server.ThreadingHTTPServer(('127.0.0.1', 0), functools.partial(Handler, directory=str(ROOT)))
    server.origin = 'http://127.0.0.1:' + str(server.server_port)
    threading.Thread(target=server.serve_forever, daemon=True).start()
    try:
        with sync_playwright() as playwright:
            for engine in ['chromium', 'webkit']:
                browser = getattr(playwright, engine).launch(headless=True)
                try:
                    for width, height in [(1440, 900), (1366, 768), (820, 1180)]:
                        context = browser.new_context(viewport={'width': width, 'height': height})
                        page = context.new_page()
                        errors, writes = [], []
                        page.on('pageerror', lambda error: errors.append(str(error)))

                        def readonly(route):
                            if route.request.method not in ['GET', 'HEAD']:
                                writes.append(route.request.method)
                                route.abort()
                            else:
                                route.continue_()

                        context.route('**/*', readonly)
                        page.goto(server.origin + '/profiles/doctor.php?doctor_id=synthetic-free')
                        disclosure = page.locator('#mxpp-correction')
                        summary = disclosure.locator('summary')
                        expect(summary).to_have_text('Sugerir corrección')
                        assert summary.bounding_box()['height'] >= 44
                        assert disclosure.get_attribute('open') is None
                        initial_storage = page.evaluate('JSON.stringify([Object.entries(localStorage),Object.entries(sessionStorage)])')
                        summary.focus()
                        page.keyboard.press('Enter')
                        expect(disclosure).to_have_attribute('open', '')
                        expect(disclosure.locator('.mxpp-correction__body')).to_contain_text('Profesional Sintética <QA>')
                        assert disclosure.locator('qa,script').count() == 0, 'profile context was not escaped'
                        expect(disclosure.locator('#mxpp-correction-status')).to_contain_text('sin escribir ni enviar datos')
                        assert disclosure.locator('form,[name],input[type=file],a').count() == 0
                        controls = disclosure.locator('input,select,textarea,button')
                        assert controls.count() == 5
                        for control in controls.all():
                            expect(control).to_be_disabled()
                            if control.evaluate('element => element.tagName') != 'BUTTON':
                                assert control.evaluate('element => element.labels.length > 0')
                        categories = disclosure.locator('#mxpp-correction-category option').all_text_contents()
                        relations = disclosure.locator('#mxpp-correction-relation option').all_text_contents()
                        assert all(value in categories for value in ['Médico retirado', 'Médico fallecido', 'Perfil duplicado'])
                        assert all(value in relations for value in ['Paciente', 'Familiar', 'Colega', 'Asistente', 'Institución', 'Otra'])
                        assert 'por sí solo' in disclosure.locator('.mxpp-correction__note').inner_text()
                        claim = page.locator('.mxpp-action-claim')
                        expect(claim).to_be_visible()
                        assert not claim.evaluate("element => !!element.closest('#mxpp-correction')")
                        assert page.locator('footer a', has_text='FORMAR PARTE').count() == 1
                        assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                        page.screenshot(path=str(out / f'{engine}-{width}-report-open.png'), full_page=True)
                        assert page.evaluate('JSON.stringify([Object.entries(localStorage),Object.entries(sessionStorage)])') == initial_storage
                        summary.focus()
                        page.keyboard.press('Enter')
                        assert disclosure.get_attribute('open') is None
                        for scenario in ['synthetic-administered', 'synthetic-paid', 'synthetic-unknown-source']:
                            page.goto(server.origin + '/profiles/doctor.php?doctor_id=' + scenario)
                            assert page.locator('#mxpp-correction').count() == 0, scenario
                            assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
                        assert not errors, errors
                        assert not writes, writes
                        results.append({'engine': engine, 'width': width, 'height': height, 'result': 'PASS',
                                        'scenarios': 4, 'mutations': 0})
                        context.close()
                finally:
                    browser.close()
        assert manifest == {path: hashlib.sha256((ROOT / path).read_bytes()).hexdigest() for path in tested}, 'tested source changed'
        (out / 'result.json').write_text(json.dumps({'result': 'PASS', 'scope': 'original_public_template_synthetic_readonly',
                                                   'report_persistence': 'NOT_IMPLEMENTED', 'checks': results,
                                                   'source_manifest': manifest}, indent=2) + '\n')
        print('UX_ALIGN01_PUBLIC_CORRECTION=PASS_2_ENGINES_3_SIZES_24_SCENARIOS_NO_MUTATIONS', flush=True)
    finally:
        server.shutdown()
        server.server_close()


if __name__ == '__main__':
    main()
