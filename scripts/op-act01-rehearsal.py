#!/usr/bin/env python3
"""Owned deployment rehearsal: schema ONLY from mxmed, synthetic rows, no fixture resets."""
import pathlib,os,json,secrets,subprocess,re,shutil,sys,time,signal
ROOT=pathlib.Path(__file__).resolve().parents[1]
SUPPORT=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-rehearsal'
EVIDENCE=pathlib.Path.home()/'Library/Application Support/MXMED/op-act01-evidence'
def mysql(sql,db=None):
 return subprocess.check_output(['mysql','-uroot','-h127.0.0.1','-N','-B','-r',*([db] if db else [])],input=sql,text=True)
def env():
 v=json.loads((SUPPORT/'environment.json').read_text());return {**{k:v for k,v in os.environ.items() if k in ['PATH','HOME','USER','LOGNAME','TMPDIR']},**v}
def prepare():
 os.umask(0o077)
 if SUPPORT.exists():raise RuntimeError('existing rehearsal must be inspected, never overwritten/reset')
 SUPPORT.mkdir(mode=0o700);EVIDENCE.mkdir(mode=0o700,exist_ok=True)
 suffix=secrets.token_hex(5);db='mxmed_operator_deploy_'+suffix;user='mxmed_act01_'+suffix;definer='mxmed_audit_'+suffix;password=secrets.token_urlsafe(32)
 (SUPPORT/'ownership.json').write_text(json.dumps({'db':db,'user':user,'definer':definer}))
 mysql(f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{user}'@'127.0.0.1' IDENTIFIED BY '{password}'; GRANT ALL ON `{db}`.* TO '{user}'@'127.0.0.1'; GRANT SHOW_ROUTINE ON *.* TO '{user}'@'127.0.0.1'; CREATE USER '{definer}'@'127.0.0.1' IDENTIFIED BY '{secrets.token_urlsafe(32)}';")
 dump=subprocess.check_output(['mysqldump','-uroot','-h127.0.0.1','--no-data','--skip-triggers','--skip-add-drop-table','--set-gtid-purged=OFF','--column-statistics=0','--skip-comments','mxmed'],text=True)
 dump=re.sub(r'AUTO_INCREMENT=\d+','',dump).replace('`mxmed`.','`'+db+'`.')
 mysql(dump,db);(EVIDENCE/'normal-schema-only.sql').write_text(dump)
 # Faithful triggers execute with a user having rights only in the owned DB.
 triggerNames=mysql("SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mxmed' ORDER BY TRIGGER_NAME;").splitlines()
 for name in triggerNames:
  raw=mysql("SELECT JSON_QUOTE(ACTION_STATEMENT),EVENT_MANIPULATION,EVENT_OBJECT_TABLE,ACTION_TIMING FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA='mxmed' AND TRIGGER_NAME='"+name+"';").strip().split('\t')
  body=json.loads(raw[0]).replace('`mxmed`.','`'+db+'`.');mysql(f"DELIMITER $$\nCREATE DEFINER='{user}'@'127.0.0.1' TRIGGER `{name}` {raw[3]} {raw[1]} ON `{raw[2]}` FOR EACH ROW {body}$$\nDELIMITER ;\n",db)
 mysql(f"GRANT SELECT,UPDATE ON `{db}`.platform_audit_stream_heads TO '{definer}'@'127.0.0.1';")
 for file in ['05_audit_lock.sql','06_audit_cas.sql']:
  text=(ROOT/'modules/identity/deployment/op-act01'/file).read_text().replace('{{TARGET_SCHEMA}}','`'+db+'`').replace('{{RESTRICTED_DEFINER}}',"'"+definer+"'@'127.0.0.1'")
  # mysql client delimiter is transport only; the reviewed statement is unchanged.
  mysql('DELIMITER $$\n'+text.rstrip().rstrip(';')+'$$\nDELIMITER ;\n',db)
 mysql(f"GRANT EXECUTE ON `{db}`.* TO '{definer}'@'127.0.0.1';")
 notificationDir=pathlib.Path('/tmp')/('mxmed-op-act01-'+suffix);notificationDir.mkdir(mode=0o700)
 config={'PHP_CLI_SERVER_WORKERS':'4','APP_ENV':'local','MXMED_ENVIRONMENT':'local','MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':'3306','MXMED_DB_NAME':db,'MXMED_DB_USER':user,'MXMED_DB_PASS':password,'MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT':'0','MXMED_OPERATOR_DEPLOYMENT_REHEARSAL':'1','MXMED_OPERATORS_RUNTIME_ENABLED':'1','MXMED_PREVIEW_ORIGIN':'https://127.0.0.1:8448','MXMED_SESSION_STORE_PORT':'6390','MXMED_PREVIEW_PEPPER':secrets.token_hex(32),'MXMED_PREVIEW_NOTIFICATION_FILE':str(notificationDir/'notifications.json'),'MXMED_OPERATOR_DELIVERY_KEY':__import__('base64').b64encode(secrets.token_bytes(32)).decode(),'MXMED_OPERATOR_DEPLOYMENT_RECEIPT':str(SUPPORT/'receipt.json'),'MXMED_AUDIT_IP_HMAC_SECRET':secrets.token_hex(32),'MXMED_AUDIT_IP_HMAC_KEY_VERSION':'audit-ip-local-v001'}
 creds={role:{'email':'op02-'+role+'@example.invalid','password':secrets.token_urlsafe(22)+'!Aa1'} for role in ['owner','operador-a','operador-b','operador-c','other-owner']}
 (SUPPORT/'credentials.json').write_text(json.dumps(creds));(SUPPORT/'environment.json').write_text(json.dumps(config));
 subprocess.run(['php',str(ROOT/'scripts/op-act01-fixture.php')],cwd=ROOT,env=env(),check=True)
 print('OP_ACT01_DISPOSABLE_PREPARED=true')
def seal():subprocess.run(['php',str(ROOT/'scripts/op-act01-fixture.php'),'--seal'],cwd=ROOT,env=env(),check=True)
def start():
 seal();v=env();state=json.loads((SUPPORT/'processes.json').read_text()) if (SUPPORT/'processes.json').exists() else {}
 commands=[('valkey',6390,[str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server'),'--bind','127.0.0.1','--port','6390','--save','','--appendonly','no','--dir',str(SUPPORT)]),('app',8097,['php','-S','127.0.0.1:8097','-t',str(ROOT),str(ROOT/'scripts/canonical-private-router.php')]),('tls',8448,[sys.executable,str(ROOT/'scripts/op-act01-tls.py')])]
 for name,port,cmd in commands:
  record=state.get(name)
  occupied=subprocess.run(['lsof','-tiTCP:'+str(port),'-sTCP:LISTEN'],capture_output=True,text=True).stdout.split()
  if occupied:
   if record and str(record['pid']) in occupied and subprocess.run(['ps','-p',str(record['pid']),'-o','command='],capture_output=True,text=True).stdout.strip()==record['command']:continue
   raise RuntimeError('rehearsal port occupied by unowned process')
  with (SUPPORT/(name+'.log')).open('ab') as log:p=subprocess.Popen(cmd,cwd=ROOT,env=v,stdin=subprocess.DEVNULL,stdout=log,stderr=log,start_new_session=True)
  time.sleep(.4)
  if p.poll() is not None:raise RuntimeError('rehearsal service startup failed: '+name)
  command=subprocess.check_output(['ps','-p',str(p.pid),'-o','command='],text=True).strip();state[name]={'pid':p.pid,'command':command};(SUPPORT/'processes.json').write_text(json.dumps(state))
 print('OP_ACT01_ORIGINAL_APP=https://127.0.0.1:8448/private-app.php')
def quiesce():
 state=json.loads((SUPPORT/'processes.json').read_text())
 for name in ['tls','app']:
  r=state.get(name)
  if r and subprocess.run(['ps','-p',str(r['pid']),'-o','command='],capture_output=True,text=True).stdout.strip()==r['command']:os.killpg(r['pid'],signal.SIGTERM)
 time.sleep(.4)
def reload():
 quiesce();start()
def destroy():
 ownership=json.loads((SUPPORT/'ownership.json').read_text());assert re.fullmatch(r'mxmed_operator_deploy_[a-f0-9]{10}',ownership['db'])
 for r in json.loads((SUPPORT/'processes.json').read_text() if (SUPPORT/'processes.json').exists() else '{}').values():
  cmd=subprocess.run(['ps','-p',str(r['pid']),'-o','command='],capture_output=True,text=True).stdout.strip()
  if cmd==r['command']:os.killpg(r['pid'],signal.SIGTERM)
 for name in ['app.log','tls.log','valkey.log']:
  if (SUPPORT/name).exists():shutil.copyfile(SUPPORT/name,EVIDENCE/name)
 mysql("DROP DATABASE `"+ownership['db']+"`; DROP USER '"+ownership['user']+"'@'127.0.0.1'; DROP USER '"+ownership['definer']+"'@'127.0.0.1';")
 notificationDir=pathlib.Path('/tmp')/('mxmed-op-act01-'+ownership['db'].removeprefix('mxmed_operator_deploy_'))
 if notificationDir.is_dir() and not notificationDir.is_symlink():shutil.rmtree(notificationDir)
 shutil.rmtree(SUPPORT);print('OP_ACT01_OWNED_REHEARSAL_DELETED=true')
if __name__=='__main__':
 {'prepare':prepare,'seal':seal,'start':start,'reload':reload,'quiesce':quiesce,'destroy':destroy}[sys.argv[1]]()
