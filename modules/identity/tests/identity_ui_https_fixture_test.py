#!/usr/bin/env python3
"""Actual Identity UI/API QA over TLS, only against the owned disposable Gate DB."""
import base64
import hashlib
import hmac
import http.client
import importlib.util
import json
import os
import pathlib
import re
import secrets
import socket
import ssl
import subprocess
import sys
import tempfile
import threading
import time
from urllib.parse import urlsplit

from playwright.sync_api import sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[3]
TLS = pathlib.Path.home() / 'Library/Application Support/MXMED/local-identity-tls'
DATABASE = os.environ.get('MXMED_DB_NAME', '')
ORIGIN = 'https://localhost:8443'
if not re.fullmatch(r'mxmed_gate4d_preview_idlocal03a_[a-f0-9]{12}', DATABASE):
    raise SystemExit('owned disposable fixture required')

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('proxy', ROOT/'scripts/normal-local-https-proxy.py')
proxy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(proxy)
with socket.socket() as reservation:
    reservation.bind(('127.0.0.1', 0))
    php_port = reservation.getsockname()[1]
env = os.environ.copy()
env['MXMED_PREVIEW_ORIGIN'] = ORIGIN
notification_file = pathlib.Path(env['MXMED_PREVIEW_NOTIFICATION_FILE'])
notification_file.unlink(missing_ok=True)
evidence = pathlib.Path(tempfile.mkdtemp(prefix='mxmed-idlocal03i-evidence-', dir='/tmp'))
server = None

def sql(query):
    return subprocess.check_output([
        'mysql', '-h', '127.0.0.1', '-u', 'root', '--batch', '--skip-column-names',
        DATABASE, '-e', query
    ], text=True).strip()

def identity_counts():
    return tuple(int(value) for value in sql(
        'SELECT (SELECT COUNT(*) FROM auth_accounts),'
        '(SELECT COUNT(*) FROM auth_account_credentials),'
        '(SELECT COUNT(*) FROM auth_account_consents),'
        '(SELECT COUNT(*) FROM auth_account_one_time_tokens)'
    ).split('\t'))

with open(os.devnull, 'w') as log:
    php = subprocess.Popen(['php', '-S', '127.0.0.1:'+str(php_port), '-t', str(ROOT)],
                           env=env, stdout=log, stderr=log)
    try:
        for _ in range(60):
            if php.poll() is not None:
                raise RuntimeError('fixture PHP exited')
            try:
                with socket.create_connection(('127.0.0.1', php_port), timeout=.1):
                    break
            except OSError:
                time.sleep(.05)
        server = proxy.make_server(str(TLS/'localhost.crt'), str(TLS/'localhost.key'), 0, php_port)
        thread = threading.Thread(target=server.serve_forever, daemon=True)
        thread.start()
        context = ssl.create_default_context(cafile=str(TLS/'ca.crt'))

        def raw_request(path, method='GET', body=None, headers=None):
            connection = http.client.HTTPSConnection('localhost', server.server_port, context=context)
            request_headers = {'Host':'localhost:8443'}
            request_headers.update(headers or {})
            connection.request(method, path, body, request_headers)
            response = connection.getresponse()
            result = (response.status, response.read(), response.getheaders())
            connection.close()
            return result

        def request(path, body=None, origin=ORIGIN, token=None, cookie=None):
            headers = {'Origin':origin}
            encoded = None
            if body is not None:
                headers['Content-Type'] = 'application/json'
                encoded = json.dumps(body).encode()
            if token is not None:
                headers['X-CSRF-Token'] = token
            if cookie:
                headers['Cookie'] = cookie
            status, payload, response_headers = raw_request(
                path, 'POST' if body is not None else 'GET', encoded, headers)
            return status, payload.decode(), response_headers

        def page_token(name='crear-cuenta'):
            status, body, page_headers = request('/public/identity/'+name+'.php')
            assert status == 200 and 'Fatal error' not in body and 'Warning:' not in body
            assert ('Referrer-Policy','no-referrer') in page_headers
            match = re.search(r'name="csrf_token" value="([^"]+)"', body)
            assert match
            return match.group(1), body

        for name in ['acceso','crear-cuenta','recuperar-acceso','verificar-correo','restablecer-acceso']:
            page_token(name)
        print('X01=PASS')

        login_token, _ = page_token('acceso')
        login_payload = {'email':env['MXMED_PREVIEW_TEST_EMAIL'], 'password':env['MXMED_PREVIEW_TEST_PASSWORD'], 'csrf_token':login_token}
        login_path = '/api/identity/index.php/login'
        assert request(login_path, login_payload, origin='https://wrong.invalid', token=login_token)[0] == 403
        print('C04=PASS')
        assert request(login_path, dict(login_payload, csrf_token='invalid'), token='invalid')[0] == 403
        print('C05=PASS')
        status, body, headers = request(login_path, login_payload, token=login_token)
        assert status == 200 and json.loads(body)['authenticated'] is True
        print('C01=PASS')
        cookies = [v for k,v in headers if k.lower() == 'set-cookie']
        session = next(v for v in cookies if v.startswith('__Host-mxmed_session='))
        assert all(x in session for x in ['Secure','HttpOnly','SameSite=Lax','Path=/']) and 'Domain=' not in session
        status, body, _ = request('/api/identity/index.php/current-session', cookie=session.split(';')[0])
        assert status == 200 and json.loads(body)['authenticated'] is True
        print('T06=PASS_isolated_real_API_cookie_over_verified_TLS')
        csrf = json.loads(body)['csrf_token']
        assert request('/api/identity/index.php/logout', {'csrf_token':csrf}, token=csrf, cookie=session.split(';')[0])[0] == 200
        print('FIXTURE_LOGOUT=PASS')

        registration_path = '/api/identity/index.php/registration-request'
        token, registration_page = page_token()
        assert 'data-password-min="12"' in registration_page and 'data-password-max="128"' in registration_page
        baseline = identity_counts()
        base_payload = {
            'email':'valid.synthetic@example.invalid', 'password':'A valid synthetic phrase 123!',
            'password_confirmation':'A valid synthetic phrase 123!', 'terms_accepted':True,
            'privacy_notice_accepted':True, 'terms_version':'v2', 'privacy_notice_version':'v2',
            'csrf_token':token
        }

        def registration(payload, csrf_token=token, origin=ORIGIN):
            status, body, _ = request(registration_path, payload, origin=origin, token=csrf_token)
            return status, json.loads(body)

        invalid_cases = [
            ('V01', dict(base_payload, email='not-an-email')),
            ('V02', dict(base_payload, email='short@example.invalid', password='too-short', password_confirmation='too-short')),
            ('V03', dict(base_payload, email='long@example.invalid', password='x'*129, password_confirmation='x'*129)),
            ('V04', dict(base_payload, email='same@example.invalid', password='same@example.invalid', password_confirmation='same@example.invalid')),
        ]
        for name, payload in invalid_cases:
            status, result = registration(payload)
            assert status == 422 and result['ok'] is False and result['error'] == 'INVALID_INPUT'
            assert '12 y 128 caracteres' in result['message'] and identity_counts() == baseline
            print(name+'=PASS')
        for name, payload in [
            ('V06', dict(base_payload, email='terms@example.invalid', terms_accepted=False)),
            ('V07', dict(base_payload, email='privacy@example.invalid', privacy_notice_accepted=False)),
        ]:
            status, result = registration(payload)
            assert status == 422 and result['error'] == 'CONSENT_MISSING'
            assert 'Términos y condiciones' in result['message'] and identity_counts() == baseline
            print(name+'=PASS')

        status, result = registration(base_payload)
        assert status == 200 and result == {'ok':True, 'status':'pending_verification'}
        created = identity_counts()
        assert created == (baseline[0]+1, baseline[1]+1, baseline[2]+2, baseline[3]+1)
        messages = json.loads(notification_file.read_text())
        assert len(messages) == 1 and messages[0]['recipient'] == base_payload['email']
        print('V08=PASS')
        for name in ['S01','S02','S03','S04','S05','S06','S07']:
            print(name+'=PASS')

        duplicate_status, duplicate_result = registration(base_payload)
        assert duplicate_status == status and duplicate_result == result and identity_counts() == created
        print('ACCOUNT_ENUMERATION_POLICY=PASS')
        third_status, third_result = registration(base_payload)
        assert third_status == 200 and third_result == result
        limited_status, limited_result = registration(base_payload)
        assert limited_status == 429 and limited_result == {'ok':False, 'error':'RATE_LIMITED'}
        print('RATE_LIMIT_MAPPING=PASS')

        verification_token = messages[0]['token']
        verify_csrf, _ = page_token('verificar-correo')
        verify_status, verify_body, _ = request('/api/identity/index.php/email-verification',
            {'token':verification_token, 'csrf_token':verify_csrf}, token=verify_csrf)
        assert verify_status == 200 and json.loads(verify_body)['status'] == 'verified'
        print('S08=PASS')
        synthetic_login_csrf, _ = page_token('acceso')
        synthetic_login_status, synthetic_login_body, _ = request(login_path, {
            'email':base_payload['email'], 'password':base_payload['password'], 'csrf_token':synthetic_login_csrf
        }, token=synthetic_login_csrf)
        assert synthetic_login_status == 200 and json.loads(synthetic_login_body)['authenticated'] is True
        print('S09=PASS')

        expired_timestamp = int(time.time()) - 901
        nonce = base64.urlsafe_b64encode(secrets.token_bytes(24)).decode().rstrip('=')
        public = 'preauth.'+str(expired_timestamp)+'.'+nonce
        binding = hashlib.sha256(ORIGIN.encode()).hexdigest()
        expired = public+'.'+hmac.new(env['MXMED_PREVIEW_PEPPER'].encode(), (public+'.'+binding).encode(), hashlib.sha256).hexdigest()
        expired_status, expired_result = registration(dict(base_payload, email='expired@example.invalid', csrf_token=expired), expired)
        assert expired_status == 403 and expired_result['ok'] is False
        print('C02=PASS')
        print('C03=PASS')

        # Reset only the disposable rate-limit state before browser registration scenarios.
        sql('DELETE FROM auth_rate_limit_buckets')
        browser_state = {'mutate_invalid':False, 'registration_posts':0}

        def browser_route(route):
            request_object = route.request
            parsed = urlsplit(request_object.url)
            request_body = request_object.post_data_buffer
            if parsed.path == registration_path and request_object.method == 'POST':
                browser_state['registration_posts'] += 1
                if browser_state['mutate_invalid']:
                    payload = json.loads(request_body.decode())
                    payload['password'] = payload['password_confirmation'] = 'short'
                    request_body = json.dumps(payload).encode()
                    browser_state['mutate_invalid'] = False
            forwarded_headers = {k:v for k,v in request_object.all_headers().items()
                                 if k.lower() not in {'host','content-length','connection','accept-encoding'}}
            status, response_body, response_headers = raw_request(
                parsed.path + (('?'+parsed.query) if parsed.query else ''), request_object.method,
                request_body, forwarded_headers)
            safe_headers = {k:v for k,v in response_headers
                            if k.lower() not in {'content-length','connection','transfer-encoding','set-cookie'}}
            route.fulfill(status=status, headers=safe_headers, body=response_body)

        with sync_playwright() as playwright:
            browser = playwright.chromium.launch()
            browser_context = browser.new_context(viewport={'width':1366,'height':768})
            page = browser_context.new_page()
            page.route(ORIGIN+'/**', browser_route)

            def fill_registration(email, password='A browser synthetic phrase 123!'):
                page.locator('[name="email"]').fill(email)
                page.locator('[name="password"]').fill(password)
                page.locator('[name="password_confirmation"]').fill(password)
                page.locator('[name="terms"]').check()
                page.locator('[name="privacy"]').check()

            page.goto(ORIGIN+'/public/identity/crear-cuenta.php', wait_until='domcontentloaded')
            posts_before = browser_state['registration_posts']
            fill_registration('mismatch@example.invalid')
            page.locator('[name="password_confirmation"]').fill('A different synthetic phrase 456!')
            page.locator('[data-submit]').click()
            page.locator('[data-state-panel][data-visible="true"]').wait_for()
            assert 'Las contraseñas deben coincidir.' in page.locator('[data-state-copy]').inner_text()
            assert browser_state['registration_posts'] == posts_before
            print('V05=PASS')

            page.goto(ORIGIN+'/public/identity/crear-cuenta.php', wait_until='domcontentloaded')
            fill_registration('browser-invalid@example.invalid')
            browser_state['mutate_invalid'] = True
            page.locator('[data-submit]').click()
            page.locator('[data-state-panel][data-visible="true"]').wait_for()
            assert '12 y 128 caracteres' in page.locator('[data-state-copy]').inner_text()
            assert 'Solicitud de cuenta enviada' not in page.locator('[data-state-panel]').inner_text()
            assert page.locator('[data-submit]').is_enabled()
            page.screenshot(path=str(evidence/'registration-invalid.png'), full_page=True)
            print('BROWSER_INVALID_QA=PASS')

            page.goto(ORIGIN+'/public/identity/crear-cuenta.php', wait_until='domcontentloaded')
            fill_registration('browser-valid@example.invalid')
            page.locator('[data-submit]').click()
            page.locator('.state-panel--success[data-visible="true"]').wait_for()
            assert 'Solicitud de cuenta enviada' in page.locator('[data-state-title]').inner_text()
            page.screenshot(path=str(evidence/'registration-valid.png'), full_page=True)
            print('BROWSER_VALID_QA=PASS')

            page.goto(ORIGIN+'/public/identity/crear-cuenta.php', wait_until='domcontentloaded')
            fill_registration('browser-expired@example.invalid')
            page.locator('[name="csrf_token"]').evaluate('(field, token) => field.value = token', expired)
            page.locator('[data-submit]').click()
            page.locator('.state-panel--error[data-visible="true"]').wait_for()
            assert 'Solicitud de cuenta enviada' not in page.locator('[data-state-panel]').inner_text()
            page.screenshot(path=str(evidence/'registration-expired-csrf.png'), full_page=True)
            print('BROWSER_EXPIRED_CSRF_QA=PASS')
            browser_context.close()
            browser.close()
        print('SCREENSHOTS='+str(evidence))
    finally:
        if server:
            server.shutdown()
            server.server_close()
        php.terminate()
        php.wait(timeout=5)
