#!/usr/bin/env python3
"""Actual UI/API HTTP integration behind TLS, only against the owned Gate QA DB."""
import http.client
import importlib.util
import json
import os
import pathlib
import re
import socket
import ssl
import subprocess
import sys
import threading
import time

ROOT = pathlib.Path(__file__).resolve().parents[3]
TLS = pathlib.Path.home() / 'Library/Application Support/MXMED/local-identity-tls'
if not re.fullmatch(r'mxmed_gate4d_preview_idlocal03a_[a-f0-9]{12}', os.environ.get('MXMED_DB_NAME', '')):
    raise SystemExit('owned disposable fixture required')

sys.dont_write_bytecode = True
spec = importlib.util.spec_from_file_location('proxy', ROOT/'scripts/normal-local-https-proxy.py')
proxy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(proxy)
with socket.socket() as reservation:
    reservation.bind(('127.0.0.1', 0))
    php_port = reservation.getsockname()[1]
env = os.environ.copy()
env['MXMED_PREVIEW_ORIGIN'] = 'https://localhost:8443'
server = None
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
        def request(path, body=None, origin='https://localhost:8443', token=None, cookie=None):
            connection = http.client.HTTPSConnection('localhost', server.server_port, context=context)
            headers = {'Host':'localhost:8443','Origin':origin}
            if body is not None:
                headers['Content-Type'] = 'application/json'
            if token is not None:
                headers['X-CSRF-Token'] = token
            if cookie:
                headers['Cookie'] = cookie
            connection.request('POST' if body is not None else 'GET',path,
                               json.dumps(body) if body is not None else None,headers)
            response = connection.getresponse()
            result = (response.status, response.read().decode(), response.getheaders())
            connection.close()
            return result
        token = None
        for name in ['acceso','crear-cuenta','recuperar-acceso','verificar-correo','restablecer-acceso']:
            status, body, page_headers = request('/public/identity/'+name+'.php')
            assert status == 200 and 'Fatal error' not in body and 'Warning:' not in body
            assert ('Referrer-Policy','no-referrer') in page_headers
            match = re.search(r'name="csrf_token" value="([^"]+)"',body)
            assert match
            if name == 'acceso': token = match.group(1)
        print('X01=PASS')
        payload = {'email':env['MXMED_PREVIEW_TEST_EMAIL'], 'password':env['MXMED_PREVIEW_TEST_PASSWORD'], 'csrf_token':token}
        path = '/api/identity/index.php/login'
        assert request(path,payload,origin='https://wrong.invalid',token=token)[0] == 403
        print('X03=PASS')
        assert request(path,dict(payload,csrf_token='invalid'),token='invalid')[0] == 403
        print('X04=PASS')
        status, body, headers = request(path,payload,token=token)
        assert status == 200 and json.loads(body)['authenticated'] is True
        print('X02=PASS')
        cookies = [v for k,v in headers if k.lower() == 'set-cookie']
        session = next(v for v in cookies if v.startswith('__Host-mxmed_session='))
        assert all(x in session for x in ['Secure','HttpOnly','SameSite=Lax','Path=/']) and 'Domain=' not in session
        status, body, _ = request('/api/identity/index.php/current-session',cookie=session.split(';')[0])
        assert status == 200 and json.loads(body)['authenticated'] is True
        print('T06=PASS_isolated_real_API_cookie_over_verified_TLS')
        # Log out the disposable account with the authenticated CSRF from current-session.
        csrf = json.loads(body)['csrf_token']
        assert request('/api/identity/index.php/logout',{'csrf_token':csrf},token=csrf,cookie=session.split(';')[0])[0] == 200
        print('FIXTURE_LOGOUT=PASS')
    finally:
        if server:
            server.shutdown()
            server.server_close()
        php.terminate()
        php.wait(timeout=5)
