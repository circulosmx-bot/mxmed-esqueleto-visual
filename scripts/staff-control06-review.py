#!/usr/bin/env python3
"""Owner-verified STAFF-CONTROL06 review controls; canonical logins, no token import.

This launcher never starts services, renews sessions, extends expiry, kills a
process, or removes a runtime. Stop only signals the already owning runner.
"""
import argparse
import datetime
import fcntl
import hashlib
import hmac
import http.client
import importlib.util
import json
import os
import pathlib
import re
import ssl
import struct
import subprocess
import sys
import time
from urllib.parse import urlparse
from zoneinfo import ZoneInfo

ROOT = pathlib.Path(__file__).resolve().parents[1]
DIRECTOR = '/internal/media-review/index.php?section=equipo'
ADVISOR = '/internal/staff-workspace/index.php'
BRANCH = 'feature/mxmed-staff-control06-director-ux'
ACTIONS = ['status', 'stop', 'open', 'open-director', 'open-advisor',
           'credentials-director', 'credentials-advisor', 'mfa', 'mfa-director',
           'mfa-advisor', 'logout-director', 'logout-advisor',
           'browser-director', 'browser-advisor']


def require(condition, label):
    if not condition:
        raise RuntimeError(label)


def private_file(owned, name, secret=True):
    path = owned / name
    require(path.is_file() and not path.is_symlink(), 'owned_private_file_required')
    stat = path.stat()
    require(stat.st_uid == os.getuid() and (not secret or stat.st_mode & 0o077 == 0), 'owned_private_file_permissions_required')
    return path


def owned_configuration(path):
    owned = pathlib.Path(path).resolve(strict=True)
    require(owned.parent == pathlib.Path('/private/tmp') and re.fullmatch(r'mxmed-ase-adm02imp01-[A-Za-z0-9_]+', owned.name), 'owned_root_required')
    stat = owned.stat()
    require(stat.st_uid == os.getuid() and stat.st_mode & 0o777 == 0o700, 'owned_root_permissions_required')
    approval = json.loads(private_file(owned, 'workflow-approval.json').read_text())
    require(pathlib.Path(approval['root']).resolve() == ROOT, 'owned_source_root_required')
    return owned, approval


def verified_preview(owned, approval):
    info = json.loads(private_file(owned, 'review-info.json').read_text())
    runtime = json.loads(private_file(owned, 'workspace-runtime.json').read_text())
    require(re.fullmatch(r'https://127\.0\.0\.1:[0-9]+', runtime['origin']), 'loopback_https_required')
    require(info['url'] == runtime['origin'] + DIRECTOR, 'owned_preview_url_mismatch')
    expiry = min(float(info['expiry_unix']), float(approval['expires']))
    require(abs(float(info['expiry_unix']) - float(runtime['expiry_unix'])) < 1, 'owned_preview_expiry_mismatch')
    require(time.time() < expiry <= time.time() + 86400, 'owned_preview_expired_or_invalid')
    require(not (owned / 'stop-review').exists(), 'owned_preview_stopping')
    branch = subprocess.check_output(['git', 'branch', '--show-current'], cwd=ROOT, text=True).strip()
    head = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
    require(branch == BRANCH, 'isolated_feature_branch_required')
    require(private_file(owned, 'workspace-head').read_text().strip() == head, 'owned_preview_head_mismatch')
    for relative, digest in approval['source_hashes'].items():
        path = (ROOT / relative).resolve()
        require(path.is_relative_to(ROOT) and path.is_file() and hashlib.sha256(path.read_bytes()).hexdigest() == digest, 'owned_source_integrity_mismatch')
    owner_pid = info.get('owner_pid')
    require(isinstance(owner_pid, int) and owner_pid > 1, 'owned_runner_required')
    os.kill(owner_pid, 0)  # Existence only; never signals or terminates a process.
    listening = subprocess.run(['lsof', '-a', '-p', str(owner_pid), '-nP', '-iTCP', '-sTCP:LISTEN'], capture_output=True, text=True, check=True).stdout
    port = urlparse(runtime['origin']).port
    require('127.0.0.1:' + str(port) in listening, 'owned_loopback_listener_required')
    require(not re.search(r'\s(?:\*|0\.0\.0\.0|\[::\]):[0-9]+', listening), 'public_listener_forbidden')
    context = ssl.create_default_context(cafile=str(private_file(owned, 'cert.pem')))
    connection = http.client.HTTPSConnection('127.0.0.1', port, context=context, timeout=8)
    try:
        connection.request('GET', '/public/identity/acceso.php')
        response = connection.getresponse()
        require(response.status == 200, 'owned_login_unavailable')
        response.read(100000)
    finally:
        connection.close()
    record = json.loads(private_file(owned, 'oper09-advisor.json').read_text())
    require(record['email'] == 'oper09_advisor@example.test' and bool(record['account']), 'synthetic_advisor_required')
    return info, runtime['origin'], expiry, record


def clipboard(value):
    subprocess.run(['pbcopy'], input=value, check=True)


def mfa_code(owned, actor):
    secret = private_file(owned, 'workspace02-totp-secret' if actor == 'advisor' else 'workflow-totp-secret').read_bytes()
    digest = hmac.new(secret, struct.pack('>Q', int(time.time()) // 30), hashlib.sha1).digest()
    offset = digest[-1] & 15
    return str((int.from_bytes(digest[offset:offset + 4], 'big') & 0x7fffffff) % 1000000).zfill(6)


def canonical_logout(page):
    return page.evaluate('''async()=>{
      const current=await(await fetch('/api/identity/index.php/current-session',{cache:'no-store'})).json();
      const r=await fetch('/api/identity/index.php/logout',{method:'POST',
        headers:{'Content-Type':'application/json','X-CSRF-Token':current.csrf_token},
        body:JSON.stringify({csrf_token:current.csrf_token})});
      const result=await r.json();return r.status===200&&result.state==='LOGOUT_SUCCESS';
    }''')


def browser(owned, origin, expiry, record, actor):
    from playwright.sync_api import sync_playwright, expect
    spec = importlib.util.spec_from_file_location('workspace02_native', ROOT / 'scripts/ase-advisor-workspace-review.py')
    native = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(native)
    lock = (owned / ('staff-control06-' + actor + '-browser.lock')).open('a+')
    try:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
    except BlockingIOError:
        (owned / ('focus-staff-control06-' + actor)).touch(mode=0o600)
        print('La ventana aislada ya está abierta; se solicitó mostrarla.')
        lock.close()
        return
    marker = owned / ('logout-staff-control06-' + actor)
    if marker.exists():
        require(not marker.is_symlink(), 'owned_marker_required')
        marker.unlink()  # Only this launcher's disposable logout request.
    password = private_file(owned, 'applicant-password' if actor == 'advisor' else 'fixture-password').read_text().strip()
    with sync_playwright() as pw:
        chromium, context, page = native.native_browser(pw)
        native.login(page, origin, record['email'] if actor == 'advisor' else 'qa_director@example.test', password, ADVISOR if actor == 'advisor' else DIRECTOR)
        del password
        if actor == 'director':
            candidate = owned / 'staff06-martha.json'
            if candidate.exists():
                pending = json.loads(private_file(owned, 'staff06-martha.json').read_text())
                page.locator('[data-case="' + pending['case'] + '"]').first.click()
                expect(page.locator('#detail-title')).to_contain_text('Martha')
            else:
                page.locator('[data-member="' + record['account'] + '"]').click()
                expect(page.locator('#detail-title')).to_contain_text('Irene')
        else:
            expect(page.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
            card=page.locator('#discovery-list [data-doctor-id="workspace_aurora"]')
            if card.count():
                card.click()
                expect(page.locator('#physician-name')).to_contain_text('Aurora')
                page.locator('#tab-requests').click()
                page.locator('#pending-toggle').click()
                expect(page.locator('#discovery-list')).to_have_attribute('aria-busy','false',timeout=20000)
        proof = page.evaluate('({width:innerWidth,height:innerHeight,outerWidth,outerHeight,availableHeight:screen.availHeight,scrollWidth:document.documentElement.scrollWidth})')
        require(proof['outerHeight'] <= proof['availableHeight'] and proof['scrollWidth'] <= proof['width'], 'native_viewport_out_of_bounds')
        page.screenshot(path=str(owned / ('staff-control06-' + actor + '-review.png')))
        (owned / ('staff-control06-' + actor + '-ready.json')).write_text(json.dumps({'opened_at': time.time(), 'url': origin + (ADVISOR if actor == 'advisor' else DIRECTOR), 'proof': proof, 'canonical_login': True, 'synthetic_account': record['email'] if actor == 'advisor' else 'qa_director@example.test'}))
        while chromium.is_connected() and time.time() < expiry and not (owned / 'stop-review').exists():
            try:
                focus = owned / ('focus-staff-control06-' + actor)
                if focus.exists() and not focus.is_symlink():
                    page.bring_to_front(); focus.unlink()
                if marker.exists():
                    require(canonical_logout(page), 'canonical_logout_unconfirmed')
                    page.goto(origin + '/public/identity/acceso.php')
                    break
                page.wait_for_timeout(1000)
            except Exception:
                break
        if chromium.is_connected():
            chromium.close()
    lock.close()


def main():
    os.umask(0o077)
    parser = argparse.ArgumentParser(description='Revisión sintética STAFF-CONTROL06 en infraestructura propia.')
    parser.add_argument('owned')
    parser.add_argument('action', nargs='?', choices=ACTIONS, default='status')
    args = parser.parse_args()
    owned, approval = owned_configuration(args.owned)
    if args.action == 'stop':
        marker = owned / 'stop-review'
        require(not marker.is_symlink(), 'owned_marker_required')
        marker.touch(mode=0o600)
        print('Cierre solicitado al runner propietario. No se eliminaron archivos ni se terminaron procesos desde este comando.')
        return
    info, origin, expiry, record = verified_preview(owned, approval)
    if args.action == 'status':
        print(json.dumps({'status': 'HEALTHY_OWNED_SYNTHETIC', 'director_url': origin + DIRECTOR,
                          'advisor_url': origin + ADVISOR, 'expires': expiry,
                          'expires_local': datetime.datetime.fromtimestamp(expiry, ZoneInfo('America/Mexico_City')).isoformat(),
                          'expiry_extended': False, 'production_activation': False}, ensure_ascii=False))
    elif args.action.startswith('credentials-'):
        advisor = args.action.endswith('advisor')
        clipboard(private_file(owned, 'applicant-password' if advisor else 'fixture-password').read_bytes().strip())
        print('Contraseña sintética copiada. Cuenta: ' + (record['email'] if advisor else 'qa_director@example.test'))
    elif args.action in ['mfa', 'mfa-director', 'mfa-advisor']:
        actor = 'advisor' if args.action == 'mfa-advisor' else 'director'
        clipboard(mfa_code(owned, actor).encode())
        print('Código del autenticador sintético copiado para ' + actor + '. Usa un código vigente y distinto del último consumido; la clave privada no se muestra.')
    elif args.action in ['open', 'open-director', 'open-advisor']:
        for actor in (['director', 'advisor'] if args.action == 'open' else [args.action.removeprefix('open-')]):
            with (owned / ('staff-control06-' + actor + '-browser.log')).open('ab') as log:
                subprocess.Popen([sys.executable, str(pathlib.Path(__file__).resolve()), str(owned), 'browser-' + actor],
                                 stdin=subprocess.DEVNULL, stdout=log, stderr=log, start_new_session=True)
        print('Apertura solicitada para Chromium aislado con login Identity legítimo. La expiración original se conserva.')
    elif args.action.startswith('logout-'):
        marker = owned / ('logout-staff-control06-' + args.action.removeprefix('logout-'))
        require(not marker.is_symlink(), 'owned_marker_required')
        marker.touch(mode=0o600)
        print('Cierre canónico solicitado a la ventana aislada de esta cuenta.')
    else:
        browser(owned, origin, expiry, record, args.action.removeprefix('browser-'))


if __name__ == '__main__':
    try:
        main()
    except (RuntimeError, OSError, ValueError, KeyError, subprocess.SubprocessError) as error:
        print('No se ejecutó la acción: ' + str(error) if isinstance(error, RuntimeError) else 'No se ejecutó la acción: el estado de la instancia requiere revisión.', file=sys.stderr)
        raise SystemExit(1)
