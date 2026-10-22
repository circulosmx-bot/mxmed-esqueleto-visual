#!/usr/bin/env python3
"""User-space normal-local runtime. No migrations, account setup or trust-store edits."""
import argparse
import json
import os
import pathlib
import secrets
import shutil
import signal
import socket
import subprocess
import sys
import time

ROOT = pathlib.Path(__file__).resolve().parents[1]
SUPPORT = pathlib.Path.home() / 'Library/Application Support/MXMED'
TLS = SUPPORT / 'local-identity-tls'
RUNTIME = SUPPORT / 'local-identity-runtime'
CONFIG = RUNTIME / 'environment.json'
STATE = RUNTIME / 'processes.json'
PROXY = ROOT / 'scripts/normal-local-https-proxy.py'


def private_dir(path):
    if path.is_symlink():
        raise RuntimeError('private directory must not be a symlink')
    path.mkdir(mode=0o700, parents=True, exist_ok=True)
    if path.stat().st_uid != os.getuid() or path.stat().st_mode & 0o077:
        raise RuntimeError('private directory must be owner-only')


def prepare(binary):
    private_dir(TLS)
    private_dir(RUNTIME)
    files = [TLS / n for n in ['ca.key', 'ca.crt', 'localhost.key', 'localhost.crt']]
    if any(p.is_symlink() for p in files):
        raise RuntimeError('TLS material must not use symlinks')
    if any(p.exists() for p in files) and not all(p.is_file() for p in files):
        raise RuntimeError('partial TLS material; inspect before retrying')
    if not any(p.exists() for p in files):
        ca = TLS / 'ca.cnf'
        leaf = TLS / 'localhost.cnf'
        ca.write_text('[req]\nprompt=no\ndistinguished_name=dn\nx509_extensions=ca\n'
                      '[dn]\nCN=MXMED Normal Local Web Development CA\n[ca]\n'
                      'basicConstraints=critical,CA:true,pathlen:0\nkeyUsage=critical,keyCertSign,cRLSign\n'
                      'subjectKeyIdentifier=hash\n')
        leaf.write_text('[req]\nprompt=no\ndistinguished_name=dn\n[dn]\nCN=localhost\n'
                        '[web]\nbasicConstraints=critical,CA:false\nkeyUsage=critical,digitalSignature,keyEncipherment\n'
                        'extendedKeyUsage=serverAuth\nsubjectAltName=DNS:localhost,IP:127.0.0.1\n'
                        'subjectKeyIdentifier=hash\nauthorityKeyIdentifier=keyid,issuer\n')
        commands = [
            ['openssl','req','-x509','-newkey','rsa:3072','-nodes','-sha256','-days','3650',
             '-config',str(ca),'-keyout',str(files[0]),'-out',str(files[1])],
            ['openssl','req','-new','-newkey','rsa:2048','-nodes','-sha256','-config',str(leaf),
             '-keyout',str(files[2]),'-out',str(TLS/'localhost.csr')],
            ['openssl','x509','-req','-in',str(TLS/'localhost.csr'),'-CA',str(files[1]),
             '-CAkey',str(files[0]),'-set_serial','0x'+secrets.token_hex(16),'-days','365','-sha256',
             '-extfile',str(leaf),'-extensions','web','-out',str(files[3])]]
        for command in commands:
            subprocess.run(command, check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        for path in TLS.iterdir():
            if path.is_file():
                path.chmod(0o600)
    if not CONFIG.exists():
        if not binary or not pathlib.Path(binary).is_file():
            raise RuntimeError('supply --valkey-binary pointing to an existing binary; no package is installed')
        target = RUNTIME / 'valkey-server'
        shutil.copy2(binary, target)
        target.chmod(0o700)
        notifications = pathlib.Path('/tmp') / ('mxmed-normal-local-identity-'+str(os.getuid()))
        private_dir(notifications)
        values = {'APP_ENV':'local','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT':'1','MXMED_DB_NAME':'mxmed',
                  'MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_USER':'root','MXMED_DB_PASS':'',
                  'MXMED_NORMAL_LOCAL_VALKEY_HOST':'127.0.0.1','MXMED_NORMAL_LOCAL_VALKEY_PORT':'6385',
                  'MXMED_NORMAL_LOCAL_SESSION_PREFIX':'mxmed:local:session:',
                  'MXMED_NORMAL_LOCAL_IDENTITY_PEPPER':secrets.token_hex(32),
                  'MXMED_NORMAL_LOCAL_IDENTITY_ORIGIN':'https://localhost:8443',
                  'MXMED_NORMAL_LOCAL_NOTIFICATION_FILE':str(notifications/'notifications.json')}
        CONFIG.write_text(json.dumps(values, indent=2)+'\n')
        CONFIG.chmod(0o600)
    print('Prepared dedicated TLS and private runtime configuration. macOS trust was not changed.')


def listener(port):
    result = subprocess.run(['lsof','-tiTCP:'+str(port),'-sTCP:LISTEN'], capture_output=True, text=True)
    return [int(v) for v in result.stdout.split()]


def command_for(pid):
    return subprocess.run(['ps','-p',str(pid),'-o','command='], capture_output=True, text=True).stdout.strip()


def owned(record):
    return bool(record and command_for(record['pid']) == record['command'])


def load_state():
    return json.loads(STATE.read_text()) if STATE.exists() else {}


def launch(name, command, port, state, env):
    record = state.get(name)
    if owned(record) and record['pid'] in listener(port):
        return
    if listener(port):
        raise RuntimeError('required port '+str(port)+' is occupied; no alternate browser URL selected')
    with (RUNTIME/(name+'.log')).open('ab') as log:
        process = subprocess.Popen(command, cwd=ROOT, env=env, stdin=subprocess.DEVNULL,
                                   stdout=log, stderr=log, start_new_session=True)
    for _ in range(60):
        if process.poll() is not None:
            raise RuntimeError(name+' failed to start; inspect its private log')
        if process.pid in listener(port):
            state[name] = {'pid':process.pid,'command':command_for(process.pid)}
            STATE.write_text(json.dumps(state))
            return
        time.sleep(.1)
    process.terminate()
    raise RuntimeError(name+' startup timed out')


def start(replace):
    if not CONFIG.is_file() or CONFIG.is_symlink() or CONFIG.stat().st_mode & 0o077:
        raise RuntimeError('prepare a private runtime config first')
    values = json.loads(CONFIG.read_text())
    env = {k:v for k,v in os.environ.items() if k in ['PATH','HOME','USER','LOGNAME','TMPDIR','SHELL','TERM']}
    env.update(values)
    # Validate with the same PHP configuration contract, without connecting to DB/store.
    validation = ('require "modules/identity/http/IdentityHttpComposition.php"; '
                  'Identity\\Http\\IdentityHttpComposition::registerAutoloader(); '
                  'try { Identity\\Http\\NormalLocalIdentityConfiguration::fromProcessEnvironment(); } '
                  'catch (Throwable $e) { fwrite(STDERR,"normal_local_configuration_invalid\\n"); exit(1); }')
    subprocess.run(['php','-r',validation], cwd=ROOT, env=env, check=True)
    state = load_state()
    if listener(8443) and not owned(state.get('https')):
        raise RuntimeError('8443 occupied; STOP, no alternate browser URL selected')
    for port in [8091,8092]:
        pids = listener(port)
        for pid in pids:
            if '-t '+str(ROOT) not in command_for(pid):
                raise RuntimeError('required PHP port belongs to another checkout')
        if pids and not owned(state.get(str(port))):
            if not replace:
                raise RuntimeError('existing PHP requires explicit --replace-dev-servers to load normal-local env')
            for pid in pids:
                cmd = command_for(pid)
                if 'php ' not in cmd or '-S 127.0.0.1:'+str(port) not in cmd or '-t '+str(ROOT) not in cmd:
                    raise RuntimeError('refusing to replace a server outside this checkout')
    if replace:
        subprocess.run(['bash','scripts/dev-servers.sh','stop'], cwd=ROOT, env=env, check=True)
    launch('valkey',[str(RUNTIME/'valkey-server'),'--bind','127.0.0.1','--protected-mode','yes',
                    '--port',values['MXMED_NORMAL_LOCAL_VALKEY_PORT'],'--save','','--appendonly',
                    'yes' if values.get('MXMED_NORMAL_LOCAL_VALKEY_AOF')=='1' else 'no','--appendfsync','always',
                    '--dir',str(RUNTIME)],int(values['MXMED_NORMAL_LOCAL_VALKEY_PORT']),state,env)
    # Same PHP flags/ports as dev-servers.sh, with independently detached ownership.
    # This keeps the servers alive when the parent CLI/agent shell exits.
    for port in [8091,8092]:
        php_env = dict(env)
        if port == 8092:
            php_env['MXMED_API_BASE'] = 'http://127.0.0.1:8091'
        launch(str(port), ['php','-d','upload_max_filesize=10M','-d','post_max_size=12M',
                          '-d','memory_limit=256M','-S','127.0.0.1:'+str(port),'-t',str(ROOT),str(ROOT/'scripts/canonical-private-router.php')],
               port, state, php_env)
    launch('https',[sys.executable,str(PROXY),'--cert',str(TLS/'localhost.crt'),
                    '--key',str(TLS/'localhost.key')],8443,state,env)
    print('DIRECTOR_CANONICAL_URL=https://localhost:8443/')
    print('Identity schema readiness and macOS trust are separate checks.')


def stop(proxy_only=False):
    state = load_state()
    for name in ['https'] if proxy_only else ['https','8092','8091','valkey']:
        record = state.get(name)
        if owned(record):
            os.kill(record['pid'], signal.SIGTERM)
            for _ in range(30):
                if not owned(record):
                    break
                time.sleep(.1)
            if owned(record):
                raise RuntimeError('process did not stop; no forced termination performed')
        state.pop(name, None)
    if STATE.exists():
        STATE.write_text(json.dumps(state))


if __name__ == '__main__':
    os.umask(0o077)
    parser = argparse.ArgumentParser()
    parser.add_argument('action', choices=['prepare','start','stop','stop-proxy','status'])
    parser.add_argument('--valkey-binary')
    parser.add_argument('--replace-dev-servers', action='store_true')
    args = parser.parse_args()
    try:
        if args.action == 'prepare': prepare(args.valkey_binary)
        elif args.action == 'start': start(args.replace_dev_servers)
        elif args.action == 'stop': stop()
        elif args.action == 'stop-proxy': stop(True)
        else:
            for name, record in load_state().items():
                print(name+': '+('running' if owned(record) else 'stopped'))
    except RuntimeError as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
    except (OSError, subprocess.CalledProcessError, ValueError):
        # No environment, secret values or connection arguments in public errors.
        print('Normal-local operation failed. Verify private configuration, required ports and process ownership.', file=sys.stderr)
        sys.exit(1)
