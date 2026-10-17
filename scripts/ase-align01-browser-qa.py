#!/usr/bin/env python3
"""ASE-ALIGN01 V2: original workspace, independently granted synthetic advisors.

Only the owning gov03b disposable runner supplies this transport, SQL principal,
fixtures and local SMS/notification adapters. No existing server is restarted.
"""
import importlib.util
import json
import os
from pathlib import Path
import re
import signal
import socket
import subprocess
import tempfile
import threading
import time
import traceback
from urllib.parse import parse_qs, quote, urlsplit

from playwright.sync_api import expect, sync_playwright

ROOT = Path(__file__).resolve().parents[1]
os.umask(0o077)
assert os.environ.get('ASE_ALIGN01_QA') == '1'
assert os.environ.get('ACT03_QA') == '1'
assert os.environ.get('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT') == '0'
CONN = json.loads(Path(os.environ['GOV03B_QA_CONNECTION']).read_text())
match = re.fullmatch(r'mxmed_gate4d_preview_c4ui_([a-f0-9]{10})', CONN['db'])
assert match and CONN['user'] == 'gov03b_' + match.group(1)
assert os.environ['MXMED_DB_NAME'] == CONN['db']
assert os.environ['MXMED_DB_USER'] == CONN['user']
assert os.environ['MXMED_DB_HOST'] == '127.0.0.1'
assert os.environ['MXMED_DB_PORT'] == '3306'
assert os.environ['MXMED_DB_PASS'] == CONN['password']
OUT = Path(os.environ['GOV03B_QA_EVIDENCE'])
assert OUT.is_dir() and not OUT.is_symlink() and OUT.stat().st_mode & 0o077 == 0

# Existing helpers do not run the historical suite or its fixture writers.
spec = importlib.util.spec_from_file_location('ase_existing_browser', ROOT / 'scripts/pub-enr03-browser-qa.py')
existing = importlib.util.module_from_spec(spec)
spec.loader.exec_module(existing)
c4, enr, FIXTURE = existing.c4, existing.enr, existing.FIXTURE
CHECKS = []
STAGE = 'initialization'
DESTINATION = '/internal/media-review/index.php?section=acreditacion'


def passed(label):
    CHECKS.append(label)
    print('PASS ' + label, flush=True)


def shape(page):
    existing.shape(page)
    assert page.locator('nav a[href="/internal/media-review/index.php"]').count() == 1


def source_ceremony(page, actor, action):
    expect(page.locator('[data-source-form=begin] fieldset')).to_be_enabled()
    page.locator('#source-action').select_option(action)
    page.locator('#source-email').fill(actor + '@example.invalid')
    page.locator('#source-password').fill(FIXTURE['password'])
    c4.response(page, 'source_begin', lambda: page.locator('[data-source-form=begin] button[type=submit]').click())
    expect(page.locator('[data-source-form=confirm] fieldset')).to_be_enabled()
    expect(page.locator('[data-source-feedback]')).to_have_text('')
    page.locator('#source-mfa').fill(existing.fresh_mfa(actor))
    c4.response(page, 'source_decide', lambda: page.locator('[data-source-form=confirm] button[type=submit]').click())
    expect(page.locator('[data-source-form=confirm]')).to_be_hidden()
    expect(page.locator('[data-source-form=begin] fieldset')).to_be_enabled()
    expect(page.locator('[data-source-feedback]')).to_have_text('')


def run(origin):
    global STAGE
    with sync_playwright() as pw:
        for engine_index, engine in enumerate(['chromium', 'webkit']):
            browser = getattr(pw, engine).launch()
            try:
                for size_index, (width, height) in enumerate([(1440, 900), (1366, 768), (820, 1180)]):
                    index = engine_index * 3 + size_index
                    STAGE = f'{engine}_{width}'
                    source_actor = 'res_ui_reviewer_' + str(index)
                    review_actor = 'res_ui_reviewer_' + str(index + 6)
                    profile = FIXTURE['profiles'][index][0]
                    license_number = 'ACT03-SYNTHETIC-browser-' + str(index)
                    # A fresh independent browser session for each capability.
                    contexts = [browser.new_context(ignore_https_errors=True, viewport={'width': width, 'height': height}) for _ in range(3)]
                    doctor, source_staff, review_staff = contexts
                    page, source_page, review_page = [context.new_page() for context in contexts]
                    errors = []
                    network_failures, pending_requests = [], {}
                    def network_started(request):
                        pending_requests[id(request)] = {'method': request.method, 'path': urlsplit(request.url).path}
                    def network_finished(request):
                        pending_requests.pop(id(request), None)
                    def network_failed(request):
                        failure = re.match(r'net::[A-Z_0-9]+', request.failure or '')
                        network_failures.append({'method': request.method, 'path': urlsplit(request.url).path, 'failure': failure.group(0) if failure else 'request_failed'})
                        network_finished(request)
                    for context in contexts:
                        # Keep all traffic inside the owned transport. No Google,
                        # SMS, email or other remote provider is exercised.
                        context.route('**/*', lambda route: route.continue_() if route.request.url.startswith(origin + '/') else route.abort())
                    for p in [page, source_page, review_page]:
                        p.on('pageerror', lambda error: errors.append(str(error)))
                        p.on('request', network_started)
                        p.on('requestfinished', network_finished)
                        p.on('requestfailed', network_failed)
                    try:
                        # Existing rate-limit fixture is reset only within this
                        # owned synthetic database, between independent cases.
                        c4.sql('DELETE FROM auth_rate_limit_buckets')
                        page.goto(origin + '/public/identity/perfil-medico.php?entry=claim&profile=' + quote(profile))
                        enr.submit(page, 'start', lambda: page.locator('[data-enrollment-start]').click())
                        page.wait_for_url('**/public/identity/perfil-medico.php?application=*')
                        application = parse_qs(urlsplit(page.url).query)['application'][0]
                        app_path = '/public/identity/perfil-medico.php?application=' + application
                        page.locator('[data-enrollment-register]').click()
                        page.wait_for_load_state('load')
                        email = 'ase_browser_applicant_' + str(index) + '@example.invalid'
                        for selector, value in [('#create-email', email), ('#create-password', FIXTURE['password']), ('#create-confirm', FIXTURE['password'])]:
                            page.locator(selector).fill(value)
                        page.locator('#terms').check()
                        page.locator('#privacy').check()
                        page.locator('[data-submit]').click()
                        expect(page.locator('[data-state-title]')).to_have_text('Solicitud de cuenta enviada')
                        message = next(item for item in reversed(json.loads(Path(os.environ['MXMED_PREVIEW_NOTIFICATION_FILE']).read_text())) if item['recipient'] == email)
                        page.goto(origin + '/public/identity/verificar-correo.php?token=' + quote(message['token']) + '&next=' + quote(app_path, safe=''))
                        page.locator('[data-submit]').click()
                        expect(page.locator('[data-state-title]')).to_have_text('Correo verificado')
                        existing.login(page, origin, email, app_path)
                        enr.submit(page, 'bind', lambda: page.locator('[data-enrollment-bind]').click())
                        for key, value in {'given_names': 'Synthetic', 'first_surname': 'Physician', 'second_surname': '', 'professional_license': license_number, 'specialty': 'Synthetic specialty', 'state': 'Estado sintético', 'city': 'Ciudad sintética'}.items():
                            page.locator('[data-enrollment-intake] [name=' + key + ']').fill(value)
                        enr.submit(page, 'submit', lambda: page.locator('[data-enrollment-intake] button[value=submit]').click())

                        existing.login(source_page, origin, source_actor + '@example.invalid', DESTINATION)
                        c4.response(source_page, 'snapshot', lambda: source_page.locator('[data-enrollment-application-id="' + application + '"]').click())
                        expect(source_page.locator('[data-source-panel]')).to_be_visible()
                        assert source_page.locator('[data-onboarding-form=enrollment_review_begin],[data-onboarding-form=review_begin]').count() == 0
                        assert c4.api(source_staff, origin, 'enrollment_review_begin', {'application_id': application, 'intake_version': 1, 'review_version': 0, 'decision': 'APPROVED', 'email': source_actor + '@example.invalid', 'password': FIXTURE['password']}).status == 403
                        assert c4.api(source_staff, origin, 'snapshot', {'view': 'staff', 'request_id': 'not-authorized-evidence'}).status == 403
                        # A rejected crafted request does not alter the DOM's
                        # independent legitimate workflow or grant.
                        source_page.reload()
                        c4.response(source_page, 'snapshot', lambda: source_page.locator('[data-enrollment-application-id="' + application + '"]').click())
                        source_ceremony(source_page, source_actor, 'COLLECT')
                        expect(source_page.locator('[data-source-status]')).to_contain_text('Documentación por verificar')
                        page.reload()
                        expect(page.locator('[data-source-form=upload]')).to_be_visible()
                        for file_index, kind in enumerate(['OFFICIAL_ID', 'PROFESSIONAL_CREDENTIAL'], start=1):
                            expect(page.locator('[data-source-form=upload] fieldset')).to_be_enabled()
                            expect(page.locator('[data-source-feedback]')).to_have_text('')
                            page.locator('#source-kind').select_option(kind)
                            page.locator('#source-file').set_input_files({'name': 'synthetic-source.png', 'mimeType': 'image/png', 'buffer': c4.png()})
                            page.locator('#source-upload-email').fill(email)
                            page.locator('#source-upload-password').fill(FIXTURE['password'])
                            c4.response(page, 'source_upload', lambda: page.locator('[data-source-form=upload] button[type=submit]').click())
                            # The POST commit precedes the original module's
                            # status refresh and form reset. Wait for its actual
                            # server-rendered result before filling the next file.
                            expect(page.locator('[data-source-files] p')).to_have_count(file_index)
                            expect(page.locator('[data-source-form=upload] fieldset')).to_be_enabled()
                            expect(page.locator('[data-source-feedback]')).to_have_text('')
                        c4.response(source_page, 'source_status', lambda: source_page.locator('[data-source-refresh]').click())
                        expect(source_page.locator('[data-source-files] button')).to_have_count(2)
                        expect(source_page.locator('[data-source-form=begin] fieldset')).to_be_enabled()
                        for button in source_page.locator('[data-source-files] button').all():
                            expect(button).to_be_enabled()
                            c4.response(source_page, 'source_document', lambda: button.click())
                            expect(source_page.locator('[data-source-dialog]')).to_be_visible()
                            source_page.locator('[data-source-close]').click()
                            expect(source_page.locator('[data-source-form=begin] fieldset')).to_be_enabled()
                            expect(source_page.locator('[data-source-feedback]')).to_have_text('')
                        source_page.locator('#source-action').select_option('AUTHORIZE')
                        for field, value in {'given_names': 'Synthetic', 'first_surname': 'Physician', 'second_surname': '', 'license_number': license_number, 'professional_area_label': 'Synthetic area', 'institution_name': 'Synthetic institution', 'identity_source': 'ASE explicitly synthetic human identity source', 'identity_reference': 'synthetic-human-reference-identity', 'credential_source': 'ASE explicitly synthetic human credential source', 'credential_reference': 'synthetic-human-reference-credential', 'verified_at': time.strftime('%Y-%m-%d %H:%M:%S', time.gmtime())}.items():
                            source_page.locator('#source-' + field).fill(value)
                        source_page.locator('[data-source-facts] [name=relationship_confirmed]').check()
                        source_ceremony(source_page, source_actor, 'AUTHORIZE')
                        # Success is asserted only after the server commit.
                        expect(source_page.locator('[data-source-status]')).to_contain_text('Falta guardar')
                        c4.response(source_page, 'source_execute', lambda: source_page.locator('[data-source-execute]').click())
                        expect(source_page.locator('[data-source-status]')).to_contain_text('Verificación realizada')
                        expect(source_page.locator('[data-onboarding]')).not_to_have_attribute('aria-busy', 'true')
                        expect(source_page.locator('[data-enrollment-review-summary]')).not_to_contain_text('pendiente de fuentes profesionales verificadas')
                        source_page.screenshot(path=str(OUT / (STAGE + '-source-only.png')), full_page=True)
                        shape(source_page)
                        passed(STAGE + '_source_only_original_workspace_MFA_sources_no_review_authority')

                        existing.login(review_page, origin, review_actor + '@example.invalid', DESTINATION)
                        c4.response(review_page, 'snapshot', lambda: review_page.locator('[data-enrollment-application-id="' + application + '"]').click())
                        expect(review_page.locator('[data-source-panel]')).to_be_hidden()
                        assert c4.api(review_staff, origin, 'source_status', {'view': 'staff', 'application_id': application}).status == 403
                        review_page.locator('#enrollment-review-decision').select_option('APPROVED')
                        review_page.locator('#enrollment-review-email').fill(review_actor + '@example.invalid')
                        review_page.locator('#enrollment-review-password').fill(FIXTURE['password'])
                        c4.submit(review_page, 'enrollment_review_begin')
                        review_page.locator('#enrollment-review-mfa').fill(existing.fresh_mfa(review_actor))
                        review_page.locator('[data-onboarding-form=enrollment_review_decide] [name=relationship_confirmed]').check()
                        c4.submit(review_page, 'enrollment_review_decide')
                        page.reload()
                        page.locator('[data-enrollment-review-state] a').click()
                        page.locator('#onboarding-phone').fill('+1555' + str(6000000 + index))
                        page.locator('#phone-email').fill(email)
                        page.locator('#phone-password').fill(FIXTURE['password'])
                        answer = c4.submit(page, 'phone_issue')
                        code = json.loads(Path(os.environ['C4_UI_QA_SMS']).read_text())[answer['data']['challenge_id']]
                        page.locator('#phone-code').fill(code)
                        c4.submit(page, 'phone_verify')
                        request_id = c4.submit(page, 'enrollment_handoff')['data']['request_id']
                        c4.submit(page, 'request_submit')
                        assert c4.api(source_staff, origin, 'snapshot', {'view': 'staff', 'request_id': request_id}).status == 403
                        c4.response(review_page, 'snapshot', lambda: review_page.locator('[data-onboarding-refresh]').click())
                        c4.response(review_page, 'snapshot', lambda: review_page.locator('[data-request-id="' + request_id + '"]').click())
                        review_page.locator('#review-decision').select_option('APPROVED')
                        review_page.locator('#review-email').fill(review_actor + '@example.invalid')
                        review_page.locator('#review-password').fill(FIXTURE['password'])
                        c4.submit(review_page, 'review_begin')
                        for button in review_page.locator('[data-document-list] button').all():
                            c4.response(review_page, 'document', lambda: button.click())
                            expect(review_page.locator('[data-evidence-dialog]')).to_be_visible()
                            review_page.locator('[data-evidence-close]').click()
                        review_page.locator('[data-onboarding-form=review_decide] [name=relationship_confirmed]').check()
                        review_page.locator('#review-mfa').fill(existing.fresh_mfa(review_actor))
                        c4.submit(review_page, 'review_decide')
                        c4.response(page, 'snapshot', lambda: page.locator('[data-onboarding-refresh]').click())
                        page.locator('#confirmation-email').fill(email)
                        page.locator('#confirmation-password').fill(FIXTURE['password'])
                        c4.submit(page, 'confirmation_issue')
                        page.locator('[data-onboarding-form=confirmation_accept] [name=accepted]').check()
                        c4.submit(page, 'confirmation_accept')
                        c4.submit(page, 'complete')
                        expect(page.locator('[data-owner-pending]')).to_be_visible()
                        assert c4.sql("SELECT COUNT(*) FROM auth_account_memberships WHERE profile_doctor_id='" + profile + "'") == '0'
                        assert c4.sql("SELECT reviewer_account_id FROM auth_physician_evidence_requests WHERE request_id='" + request_id + "'") == review_actor
                        assert c4.sql("SELECT reviewer_account_id FROM auth_physician_enrollment_resolutions WHERE origin_id='" + application + "'") == review_actor
                        assert c4.sql("SELECT reviewer_account_id FROM auth_professional_source_operations WHERE origin_id='" + application + "'") == source_actor
                        shape(review_page)
                        review_page.screenshot(path=str(OUT / (STAGE + '-review-only.png')), full_page=True)
                        passed(STAGE + '_review_only_enrollment_C4_same_reviewer_MFA_no_source_no_owner')
                        assert not errors, errors
                    except Exception:
                        (OUT / (STAGE + '-network-failure.json')).write_text(json.dumps({'failed_requests': network_failures, 'pending_requests': list(pending_requests.values())}, indent=2))
                        for p, label in [(page, 'applicant'), (source_page, 'source'), (review_page, 'review')]:
                            p.screenshot(path=str(OUT / (STAGE + '-' + label + '-failure.png')), full_page=True)
                        raise
                    finally:
                        for context in contexts:
                            context.close()
            finally:
                browser.close()


def main():
    os.umask(0o077)
    spec = importlib.util.spec_from_file_location('ase_proxy', ROOT / 'scripts/normal-local-https-proxy.py')
    proxy = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(proxy)
    with tempfile.TemporaryDirectory(prefix='mxmed-ase-browser-', dir='/tmp') as directory, (OUT / 'ase-browser-private-server.log').open('wb') as log:
        folder = Path(directory)
        folder.chmod(0o700)
        subprocess.run(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-keyout', str(folder / 'key.pem'), '-out', str(folder / 'cert.pem'), '-days', '1', '-subj', '/CN=localhost', '-addext', 'subjectAltName=DNS:localhost'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
        backend = enr.port()
        tls = proxy.make_server(str(folder / 'cert.pem'), str(folder / 'key.pem'), port=0, backend_port=backend)
        tls.expected_host = 'localhost:' + str(tls.server_port)
        origin = 'https://' + tls.expected_host
        key = os.urandom(32).hex()
        tls.governance_transport_key = key.encode()
        env = {**os.environ, 'MXMED_PREVIEW_ORIGIN': origin, 'MXMED_GOVERNANCE_PROXY_KEY': key, 'PUB_ENR02_QA_PROOF_KEY': os.urandom(32).hex(), 'MXMED_PROFILES_API_BASE': '', 'MXMED_API_BASE': ''}
        php = subprocess.Popen(['php', '-S', '127.0.0.1:' + str(backend), '-t', str(ROOT), str(ROOT / 'modules/identity/tests/support/PublicEnrollmentQaRouter.php')], cwd=ROOT, env=env, stdin=subprocess.DEVNULL, stdout=log, stderr=log, start_new_session=True)
        try:
            for _ in range(50):
                if php.poll() is not None:
                    raise RuntimeError('owned PHP failed')
                try:
                    with socket.create_connection(('127.0.0.1', backend), timeout=.1):
                        break
                except OSError:
                    time.sleep(.1)
            else:
                raise RuntimeError('owned PHP unavailable')
            threading.Thread(target=tls.serve_forever, daemon=True).start()
            run(origin)
            (OUT / 'ase-browser-result.json').write_text(json.dumps({'result': 'PASS', 'checks': CHECKS, 'journeys': 6, 'engines': ['chromium', 'webkit'], 'viewports': [[1440, 900], [1366, 768], [820, 1180]], 'original_templates': True, 'source_only_and_review_only': True, 'normal_runtime_active': False, 'external_delivery': False}, indent=2))
        finally:
            tls.shutdown()
            tls.server_close()
            if php.poll() is None:
                os.killpg(php.pid, signal.SIGTERM)
                php.wait(timeout=10)
    print('ASE_ALIGN01_ORIGINAL_BROWSER=PASS', flush=True)


if __name__ == '__main__':
    try:
        main()
    except Exception:
        (OUT / 'ase-browser-private-failure.log').write_text(traceback.format_exc())
        print('ASE_ALIGN01_ORIGINAL_BROWSER=FAIL; stage=' + STAGE + '; inspect private evidence', flush=True)
        raise SystemExit(1)
