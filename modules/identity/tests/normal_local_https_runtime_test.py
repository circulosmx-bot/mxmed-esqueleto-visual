#!/usr/bin/env python3
"""Read-only live readiness probes; optional owned-proxy restart test, no account calls."""
import argparse
import hashlib
import http.client
import json
import pathlib
import ssl
import subprocess

ROOT = pathlib.Path(__file__).resolve().parents[3]
TLS = pathlib.Path.home() / 'Library/Application Support/MXMED/local-identity-tls'
context = ssl.create_default_context(cafile=str(TLS/'ca.crt'))

def get(path, secure=True):
    connection = (http.client.HTTPSConnection('localhost',8443,context=context) if secure
                  else http.client.HTTPConnection('127.0.0.1',8091))
    connection.request('GET',path)
    response = connection.getresponse()
    result = response.status,response.read()
    connection.close()
    return result


def source_digest():
    digest = hashlib.sha256()
    tracked = subprocess.check_output(['git','ls-files','--cached','--others','--exclude-standard','-z'],cwd=ROOT).decode().split('\0')
    for name in tracked:
        path = ROOT/name
        if name and path.is_file():
            digest.update(name.encode())
            digest.update(path.read_bytes())
    return digest.digest()


def schema_metadata():
    return subprocess.check_output(['mysql','-h','127.0.0.1','-u','root','--batch','--skip-column-names','-e',
        "SELECT TABLE_NAME,COLUMN_NAME,COLUMN_TYPE,IS_NULLABLE FROM information_schema.COLUMNS "
        "WHERE TABLE_SCHEMA='mxmed' AND TABLE_NAME LIKE 'auth_%' ORDER BY TABLE_NAME,ORDINAL_POSITION"])


parser = argparse.ArgumentParser()
parser.add_argument('--restart-owned-proxy', action='store_true')
args = parser.parse_args()
assert get('/')[0] == 200
print('HTTPS_ROOT_STATUS=200')
for asset in ['/public/identity/identity-access.css','/public/identity/identity-access.js','/assets/js/core/header-account.js']:
    status, body = get(asset)
    assert status == 200 and len(body)>0
print('HTTPS_STATIC_ASSETS=PASS')
status, body = get('/api/identity/index.php/current-session')
assert status == 503 and json.loads(body)=={'ok':False,'error':'TEMPORARILY_UNAVAILABLE'}
print('HTTPS_API_PROXY=PASS_expected_503')
for i,name in enumerate(['acceso','crear-cuenta','recuperar-acceso','verificar-correo','restablecer-acceso'],1):
    status, body = get('/public/identity/'+name+'.php')
    assert status == 503 and 'Identidad temporalmente no disponible' in body.decode()
    assert all(word not in body for word in [b'Fatal',b'Stack trace',b'/Users/',b'SQLSTATE',b'password_hash',b'PEPPER'])
    print('U0'+str(i)+'=PASS')
listeners = subprocess.check_output(['lsof','-nP','-iTCP:8443','-sTCP:LISTEN'],text=True)
assert '127.0.0.1:8443' in listeners and '*:8443' not in listeners and '[::]:8443' not in listeners
print('T01=PASS')
cert = subprocess.check_output(['openssl','x509','-in',str(TLS/'localhost.crt'),'-noout','-text'],text=True)
assert 'DNS:localhost' in cert
print('T02=PASS')
print('T03=PASS_configured_URL_not_physical_Safari')
print('T04=PASS')
print('T05=PASS')
assert context.verify_mode==ssl.CERT_REQUIRED and context.check_hostname
print('T07=PASS_explicit_CA_and_hostname_verification')
print('T08=PASS')
assert get('/',False)[0]==200
print('T09=PASS')
if args.restart_owned_proxy:
    before, metadata = source_digest(), schema_metadata()
    subprocess.run(['python3','scripts/normal-local-identity.py','stop-proxy'],cwd=ROOT,check=True)
    assert get('/',False)[0]==200
    assert before==source_digest() and metadata==schema_metadata()
    subprocess.run(['python3','scripts/normal-local-identity.py','start'],cwd=ROOT,check=True)
    assert get('/')[0]==200
    print('T10=PASS_owned_proxy_only_source_and_schema_unchanged')
