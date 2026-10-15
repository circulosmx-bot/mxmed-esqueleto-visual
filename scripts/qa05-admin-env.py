#!/usr/bin/env python3
"""Persistent, isolated local QA05 environment. Destructive actions fail closed."""
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
SUPPORT = pathlib.Path.home() / 'Library/Application Support/MXMED/qa05-admin'
RUNTIME = SUPPORT / 'runtime'
TLS = SUPPORT / 'tls'
MEDIA = SUPPORT / 'media'
PRIVATE_MEDIA = MEDIA / 'private'
PUBLIC_MEDIA = MEDIA / 'public'
TMP = SUPPORT / 'tmp'
NOTIFICATIONS = pathlib.Path('/tmp') / ('mxmed-qa05-'+str(os.getuid())) / 'notifications.json'
VALKEY = SUPPORT / 'valkey'
CONFIG = SUPPORT / 'environment.json'
CREDS_JSON = SUPPORT / 'credentials.json'
CREDS_TEXT = SUPPORT / 'Safari credentials.txt'
STATE = RUNTIME / 'processes.json'
DATABASE = 'mxmed_gate4d_preview_adminqa_qa05'
DB_USER = 'mxmed_qa05'
ORIGIN = 'https://127.0.0.1:8444'
PORTS = {'app':8093, 'https':8444, 'valkey':6386}
REQUIRED_TABLES = {
    'auth_accounts', 'auth_account_credentials', 'auth_account_consents',
    'auth_account_memberships', 'profiles_doctors', 'consultorios',
    'doctor_contact_points', 'consultorio_schedule', 'agenda_settings',
    'agenda_appointments', 'agenda_appointment_events', 'platform_audit_events',
}

def private_dir(path):
    if path.is_symlink(): raise RuntimeError('qa05 private path must not be a symlink')
    path.mkdir(mode=0o700, parents=True, exist_ok=True)
    path.chmod(0o700)
    if path.stat().st_uid != os.getuid() or path.stat().st_mode & 0o077: raise RuntimeError('qa05 private path must be owner-only')

def safe_config():
    if not CONFIG.is_file() or CONFIG.is_symlink() or CONFIG.stat().st_mode & 0o077: raise RuntimeError('qa05 private configuration unavailable')
    values=json.loads(CONFIG.read_text())
    required={
        'APP_ENV':'local','MXMED_ENVIRONMENT':'local','MXMED_PREVIEW_EXPLICIT':'1',
        'MXMED_QA05_ADMIN_ENV':'1','MXMED_DB_NAME':DATABASE,'MXMED_QA05_DATABASE':DATABASE,
        'MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_USER':DB_USER,
        'MXMED_PREVIEW_ORIGIN':ORIGIN,'MXMED_SESSION_STORE_PORT':str(PORTS['valkey']),
    }
    for key,value in required.items():
        if values.get(key)!=value: raise RuntimeError('qa05 isolation configuration rejected')
    if values.get('MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT','') not in ('','0'): raise RuntimeError('qa05 normal-local composition forbidden')
    if not str(values.get('MXMED_PRIVATE_MEDIA_ROOT','')).startswith(str(SUPPORT)) or not str(values.get('MXMED_PUBLIC_MEDIA_ROOT','')).startswith(str(SUPPORT)): raise RuntimeError('qa05 media isolation rejected')
    return values

def admin_mysql(sql, database=None, capture=False):
    command=['mysql','--protocol=TCP','--host=127.0.0.1','--port=3306','--user=root','--batch','--skip-column-names']
    if database: command.append(database)
    return subprocess.run(command,input=sql,text=True,capture_output=capture,check=True)

def qa_mysql(values, sql, capture=False):
    env=dict(os.environ);env['MYSQL_PWD']=values['MXMED_DB_PASS']
    command=['mysql','--protocol=TCP','--host=127.0.0.1','--port=3306','--user='+DB_USER,'--batch','--skip-column-names',DATABASE]
    return subprocess.run(command,input=sql,text=True,capture_output=capture,env=env,check=True)

def schema_clone():
    dump=subprocess.Popen(['mysqldump','--protocol=TCP','--host=127.0.0.1','--port=3306','--user=root','--no-data','--triggers','--single-transaction','--skip-comments','--skip-dump-date','--set-gtid-purged=OFF','mxmed'],stdout=subprocess.PIPE,stderr=subprocess.PIPE)
    load=subprocess.run(['mysql','--protocol=TCP','--host=127.0.0.1','--port=3306','--user=root',DATABASE],stdin=dump.stdout,stdout=subprocess.DEVNULL,stderr=subprocess.PIPE)
    dump.stdout.close(); dump_error=dump.stderr.read().decode(); dump_status=dump.wait()
    if dump_status or load.returncode: raise RuntimeError('qa05 schema clone failed: '+(dump_error or load.stderr.decode())[:300])

def seed(values):
    env=clean_env(values); env['MXMED_QA05_CREDENTIALS_JSON']=str(CREDS_JSON)
    subprocess.run(['php',str(ROOT/'scripts/qa05-fixture.php')],cwd=ROOT,env=env,check=True)

def create_database(values):
    if DATABASE.lower()=='mxmed' or not DATABASE.startswith('mxmed_gate4d_preview_adminqa_'): raise RuntimeError('qa05 destructive target rejected')
    password=values['MXMED_DB_PASS'].replace("'","''")
    admin_mysql(f"CREATE DATABASE IF NOT EXISTS `{DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\nCREATE USER IF NOT EXISTS '{DB_USER}'@'127.0.0.1' IDENTIFIED BY '{password}';\nALTER USER '{DB_USER}'@'127.0.0.1' IDENTIFIED BY '{password}';\nREVOKE ALL PRIVILEGES, GRANT OPTION FROM '{DB_USER}'@'127.0.0.1';\nGRANT ALL PRIVILEGES ON `{DATABASE}`.* TO '{DB_USER}'@'127.0.0.1';\n")
    table_rows=admin_mysql("SELECT table_name FROM information_schema.tables WHERE table_schema='"+DATABASE+"';",capture=True).stdout.splitlines()
    tables=set(table_rows)
    if not tables:
        schema_clone()
        tables=set(admin_mysql("SELECT table_name FROM information_schema.tables WHERE table_schema='"+DATABASE+"';",capture=True).stdout.splitlines())
    if not REQUIRED_TABLES.issubset(tables): raise RuntimeError('qa05 schema is incomplete; run the guarded reset')
    grants=admin_mysql("SHOW GRANTS FOR '"+DB_USER+"'@'127.0.0.1';",capture=True).stdout
    if '`mxmed`.*' in grants or f'`{DATABASE}`.*' not in grants: raise RuntimeError('qa05 database grants rejected')
    qa_mysql(values,"SELECT 1;",capture=True)
    if admin_mysql("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='mxmed';",capture=True).stdout.strip()=='0': raise RuntimeError('normal-local database unavailable')

def snapshot_normal():
    sql="SELECT CONCAT('memberships=',COUNT(*),'|',COALESCE(GROUP_CONCAT(CONCAT(role_code,':',scope_code,':',status) ORDER BY membership_id),'none')) FROM mxmed.auth_account_memberships WHERE profile_doctor_id='1';SELECT CONCAT('consultorios=',COUNT(*)) FROM mxmed.consultorios WHERE doctor_id='1';SELECT CONCAT('appointments=',COUNT(*)) FROM mxmed.agenda_appointments WHERE doctor_id='1';"
    return admin_mysql(sql,capture=True).stdout.strip().splitlines()

def prepare():
    for path in [SUPPORT,RUNTIME,TLS,MEDIA,PRIVATE_MEDIA,PUBLIC_MEDIA,TMP,VALKEY]: private_dir(path)
    normal_tls=pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-tls'
    for source_name,target_name in [('localhost.crt','qa05.crt'),('localhost.key','qa05.key')]:
        source=normal_tls/source_name; target=TLS/target_name
        if not source.is_file() or source.is_symlink(): raise RuntimeError('verified normal-local TLS material required')
        if not target.exists(): shutil.copy2(source,target);target.chmod(0o600)
        if not target.is_file() or target.is_symlink() or target.stat().st_mode & 0o077: raise RuntimeError('qa05 TLS material must be owner-only')
    if not CONFIG.exists():
        db_password=secrets.token_urlsafe(32)
        passwords={role:secrets.token_urlsafe(18)+'!Qa05' for role in ['owner','administrator','collaborator']}
        credentials={role:{'email':f'qa05-{role}@example.invalid','password':passwords[role]} for role in passwords}
        CREDS_JSON.write_text(json.dumps(credentials,indent=2)+'\n');CREDS_JSON.chmod(0o600)
        CREDS_TEXT.write_text('MXMED QA05 — credenciales exclusivamente sintéticas\nURL: '+ORIGIN+'/public/identity/acceso.php\n\n'+''.join(f"{role.upper()}\nCorreo: {entry['email']}\nContraseña: {entry['password']}\n\n" for role,entry in credentials.items()));CREDS_TEXT.chmod(0o600)
        private_dir(NOTIFICATIONS.parent)
        values={'APP_ENV':'local','MXMED_ENVIRONMENT':'local','MXMED_PREVIEW_EXPLICIT':'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT':'0','MXMED_QA05_ADMIN_ENV':'1','MXMED_QA05_DATABASE':DATABASE,
                'MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_NAME':DATABASE,'MXMED_DB_USER':DB_USER,'MXMED_DB_PASS':db_password,
                'MXMED_PREVIEW_PEPPER':secrets.token_hex(32),'MXMED_PREVIEW_ORIGIN':ORIGIN,'MXMED_PREVIEW_NOTIFICATION_FILE':str(NOTIFICATIONS),
                'MXMED_SESSION_STORE_PORT':str(PORTS['valkey']),'MXMED_PRIVATE_MEDIA_ROOT':str(PRIVATE_MEDIA),'MXMED_PUBLIC_MEDIA_ROOT':str(PUBLIC_MEDIA),
                'TMPDIR':str(TMP),'MXMED_EMAIL_PROVIDER':'disabled','MXMED_QA05_EXTERNAL_DELIVERY':'disabled','MXMED_AUDIT_IP_HASH_SECRET':secrets.token_hex(32)}
        CONFIG.write_text(json.dumps(values,indent=2)+'\n');CONFIG.chmod(0o600)
    values=safe_config(); create_database(values)
    count=qa_mysql(values,"SELECT COUNT(*) FROM auth_accounts WHERE account_id LIKE 'qa05-%';",capture=True).stdout.strip()
    if count=='0': seed(values)
    baseline=SUPPORT/'normal-local-baseline.json'
    if not baseline.exists(): baseline.write_text(json.dumps(snapshot_normal(),indent=2)+'\n');baseline.chmod(0o600)
    print('QA05_PREPARED=true');print('QA05_CREDENTIALS='+str(CREDS_TEXT))

def clean_env(values):
    env={k:v for k,v in os.environ.items() if k in ['PATH','HOME','USER','LOGNAME','SHELL','TERM']}
    env.update({k:str(v) for k,v in values.items()});return env

def listener(port):
    result=subprocess.run(['lsof','-tiTCP:'+str(port),'-sTCP:LISTEN'],capture_output=True,text=True)
    return [int(v) for v in result.stdout.split()]

def command_for(pid): return subprocess.run(['ps','-p',str(pid),'-o','command='],capture_output=True,text=True).stdout.strip()
def owned(record): return bool(record and command_for(record.get('pid',0))==record.get('command',''))
def state_load(): return json.loads(STATE.read_text()) if STATE.exists() else {}
def state_save(state): STATE.write_text(json.dumps(state,indent=2)+'\n');STATE.chmod(0o600)

def launch(name,command,port,state,env):
    record=state.get(name)
    if owned(record) and record['pid'] in listener(port): return
    if listener(port): raise RuntimeError('qa05 port occupied: '+str(port))
    with (RUNTIME/(name+'.log')).open('ab') as log: process=subprocess.Popen(command,cwd=ROOT,env=env,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
    for _ in range(80):
        if process.poll() is not None: raise RuntimeError('qa05 service failed: '+name)
        if process.pid in listener(port): state[name]={'pid':process.pid,'command':command_for(process.pid),'port':port};state_save(state);return
        time.sleep(.1)
    process.terminate();raise RuntimeError('qa05 service startup timeout: '+name)

def start():
    values=safe_config(); create_database(values)
    check=qa_mysql(values,"SELECT CONCAT(DATABASE(),'|',CURRENT_USER());",capture=True).stdout.strip()
    if check!=DATABASE+'|'+DB_USER+'@127.0.0.1': raise RuntimeError('qa05 runtime database identity rejected')
    env=clean_env(values);state=state_load()
    binary=shutil.which('valkey-server') or str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server')
    if not pathlib.Path(binary).is_file(): raise RuntimeError('qa05 valkey binary unavailable')
    launch('valkey',[binary,'--bind','127.0.0.1','--protected-mode','yes','--port',str(PORTS['valkey']),'--appendonly','yes','--appenddirname','appendonlydir','--dir',str(VALKEY),'--dbfilename','qa05.rdb'],PORTS['valkey'],state,env)
    launch('app',['php','-d','upload_max_filesize=10M','-d','post_max_size=12M','-d','memory_limit=256M','-S','127.0.0.1:'+str(PORTS['app']),'-t',str(ROOT)],PORTS['app'],state,env)
    launch('https',[sys.executable,str(ROOT/'scripts/qa05-https-proxy.py'),'--cert',str(TLS/'qa05.crt'),'--key',str(TLS/'qa05.key'),'--backend-port',str(PORTS['app'])],PORTS['https'],state,env)
    print('QA05_URL='+ORIGIN+'/private-app.php');print('QA05_CREDENTIALS='+str(CREDS_TEXT))

def stop():
    state=state_load()
    for name in ['https','app','valkey']:
        record=state.get(name)
        if owned(record):
            os.kill(record['pid'],signal.SIGTERM)
            for _ in range(40):
                if not owned(record): break
                time.sleep(.1)
            if owned(record): raise RuntimeError('qa05 service did not stop: '+name)
        state.pop(name,None)
    state_save(state);print('QA05_STOPPED=true')

def status():
    values=safe_config(); state=state_load()
    print('QA05_URL='+ORIGIN+'/private-app.php')
    for name,port in PORTS.items():
        record=state.get(name);print('QA05_'+name.upper()+'='+('RUNNING' if owned(record) and record['pid'] in listener(port) else 'STOPPED'))
    print('QA05_DATABASE='+DATABASE);print('QA05_CREDENTIALS='+str(CREDS_TEXT))
    print('NORMAL_LOCAL_BASELINE_MATCH='+str(snapshot_normal()==json.loads((SUPPORT/'normal-local-baseline.json').read_text())).lower())

def reset():
    values=safe_config();stop()
    if DATABASE.lower()=='mxmed' or not DATABASE.startswith('mxmed_gate4d_preview_adminqa_'): raise RuntimeError('qa05 reset target rejected')
    admin_mysql(f"DROP DATABASE `{DATABASE}`; CREATE DATABASE `{DATABASE}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;")
    schema_clone();seed(values)
    for target in [VALKEY/'qa05.rdb',VALKEY/'appendonlydir']:
        if target.is_dir(): shutil.rmtree(target)
        elif target.exists(): target.unlink()
    print('QA05_RESET=true')

def destroy(confirm):
    if confirm!=DATABASE: raise RuntimeError('destroy requires --confirm '+DATABASE)
    values=safe_config();stop()
    if DATABASE.lower()=='mxmed' or not DATABASE.startswith('mxmed_gate4d_preview_adminqa_'): raise RuntimeError('qa05 destroy target rejected')
    admin_mysql(f"DROP DATABASE IF EXISTS `{DATABASE}`; DROP USER IF EXISTS '{DB_USER}'@'127.0.0.1';")
    if SUPPORT.is_symlink() or SUPPORT.name!='qa05-admin': raise RuntimeError('qa05 support path rejected')
    shutil.rmtree(SUPPORT);print('QA05_DESTROYED=true')

if __name__=='__main__':
    os.umask(0o077);parser=argparse.ArgumentParser();parser.add_argument('action',choices=['prepare','start','stop','status','reset','destroy']);parser.add_argument('--confirm');args=parser.parse_args()
    try:
        {'prepare':prepare,'start':start,'stop':stop,'status':status,'reset':reset,'destroy':lambda:destroy(args.confirm)}[args.action]()
    except (RuntimeError,subprocess.CalledProcessError,OSError,ValueError,json.JSONDecodeError) as error:
        print('QA05 operation failed: '+str(error),file=sys.stderr);sys.exit(1)
