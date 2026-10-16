#!/usr/bin/env python3
"""Exercise the real localhost TLS proxy with disposable certificates/listeners only."""
import pathlib, tempfile, subprocess, ssl, http.server, http.client, threading, json, importlib.util, os
from unittest.mock import patch
ROOT = pathlib.Path(__file__).resolve().parents[1]
spec = importlib.util.spec_from_file_location('tls_proxy', ROOT/'scripts/normal-local-https-proxy.py')
proxy = importlib.util.module_from_spec(spec); spec.loader.exec_module(proxy)
class Backend(http.server.BaseHTTPRequestHandler):
    def do_POST(self):
        body = self.rfile.read(int(self.headers.get('Content-Length', 0)))
        server = {'REQUEST_METHOD': self.command, 'REQUEST_URI': self.path, 'REMOTE_ADDR': '127.0.0.1'}
        server.update({'HTTP_'+k.upper().replace('-', '_'): v for k, v in self.headers.items()})
        payload = json.dumps({'server': server, 'body': body.decode()}).encode()
        self.send_response(200); self.send_header('Content-Length', str(len(payload))); self.end_headers(); self.wfile.write(payload)
    def log_message(self, *_): pass

def verify(server, body, key):
    php = "require $argv[1];$v=json_decode(stream_get_contents(STDIN),true);echo Identity\\Http\\GovernanceTransport::secure($v['server'],$v['body'],$v['key'])?'yes':'no';"
    r = subprocess.run(['php', '-r', php, str(ROOT/'modules/identity/http/GovernanceTransport.php')], input=json.dumps({'server':server,'body':body,'key':key}), text=True, capture_output=True, check=True)
    return r.stdout == 'yes'

with tempfile.TemporaryDirectory(prefix='mxmed-gov03b-tls-') as directory:
    base = pathlib.Path(directory); base.chmod(0o700)
    subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(base/'key.pem'),'-out',str(base/'cert.pem'),'-days','1','-subj','/CN=localhost','-addext','subjectAltName=DNS:localhost'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL, check=True)
    key = 'synthetic-test-only-' + 'k'*40
    backend = http.server.ThreadingHTTPServer(('127.0.0.1', 0), Backend)
    with patch.dict(os.environ, {'MXMED_GOVERNANCE_PROXY_KEY': key}):
        tls = proxy.make_server(str(base/'cert.pem'),str(base/'key.pem'),port=0,backend_port=backend.server_port)
    for server in [backend,tls]: threading.Thread(target=server.serve_forever,daemon=True).start()
    try:
        context = ssl.create_default_context(cafile=str(base/'cert.pem'))
        conn = http.client.HTTPSConnection('localhost', tls.server_port, context=context, timeout=10)
        conn.request('POST','/api/identity/governance.php/cases/exact/finalize', body='{"synthetic":true}', headers={'Host':'localhost:8443','Origin':'https://localhost:8443','Cookie':'synthetic=only','X-CSRF-Token':'synthetic-csrf','X-Forwarded-Proto':'https','X-MXMED-TLS-Proof':'client-forgery','X-MXMED-TLS-Time':'0','X-MXMED-TLS-Nonce':'forged'})
        response=conn.getresponse(); assert response.status==200; value=json.loads(response.read()); conn.close()
        server,body=value['server'],value['body']
        assert 'HTTP_X_FORWARDED_PROTO' not in server and server['HTTP_X_MXMED_TLS_PROOF']!='client-forgery'
        assert verify(server,body,key)
        print('PASS real_TLS_verified_proxy_overwrites_untrusted_headers')
        for field,value in [('REQUEST_URI','/another/case'),('REQUEST_METHOD','GET'),('HTTP_COOKIE','another'),('HTTP_ORIGIN','https://wrong.invalid'),('REMOTE_ADDR','192.0.2.1'),('HTTP_X_MXMED_TLS_TIME','0')]:
            changed={**server,field:value}; assert not verify(changed,body,key); print('PASS transport_tamper_'+field)
        assert not verify(server,body+'tampered',key) and not verify(server,body,'')
        print('PASS body_tampering_and_missing_deployment_key_denied')
        tls.governance_transport_key=None
        conn=http.client.HTTPSConnection('localhost',tls.server_port,context=context,timeout=10)
        conn.request('POST','/test',body='{}',headers={'Host':'localhost:8443','X-MXMED-TLS-Proof':'client-forgery'})
        r=conn.getresponse(); value=json.loads(r.read()); conn.close(); assert 'HTTP_X_MXMED_TLS_PROOF' not in value['server']
        print('PASS no_implicit_key_no_client_proof_forwarding')
    finally:
        for server in [tls,backend]: server.shutdown(); server.server_close()
print('GOV03B_DISPOSABLE_TLS_TRANSPORT=PASS')
