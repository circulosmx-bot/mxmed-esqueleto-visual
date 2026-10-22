#!/usr/bin/env python3
"""Owned disposable MySQL/Valkey/PHP preview. No original data copied or written.
Schema-only read of original local tables; synthetic QA05 seed reused. No QA05 runtime touched.
"""
import argparse,json,os,pathlib,secrets,socket,subprocess,sys,tempfile,time,signal,shutil
import pymysql
ROOT=pathlib.Path(__file__).resolve().parents[1]
def port():
 with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]
def command(pid):return subprocess.run(['ps','-p',str(pid),'-o','command='],capture_output=True,text=True).stdout.strip()
def run(d):
 os.umask(0o077);d=pathlib.Path(d).resolve();assert d.name.startswith('mxmed-init02-') and d.stat().st_uid==os.getuid()
 env={k:v for k,v in os.environ.items() if k in ('PATH','HOME','USER','LOGNAME','SHELL')};processes=[]
 def launch(args):
  f=(d/('service-'+str(len(processes))+'.log')).open('ab');p=subprocess.Popen(args,cwd=ROOT,env=env,stdout=f,stderr=f);processes.append(p);return p
 try:
  dbport,phpport,tlsport,vport=[port() for _ in range(4)]
  data=d/'mysql';data.mkdir();sock=str(d/'mysql.sock')
  subprocess.run(['mysqld','--no-defaults','--initialize-insecure','--datadir='+str(data)],check=True,stdout=subprocess.DEVNULL,stderr=(d/'init.log').open('wb'))
  launch(['mysqld','--no-defaults','--datadir='+str(data),'--socket='+sock,'--port='+str(dbport),'--bind-address=127.0.0.1','--mysqlx=OFF','--log-error='+str(d/'mysql.log')])
  for _ in range(150):
   try:db=pymysql.connect(unix_socket=sock,user='root',autocommit=True);break
   except pymysql.Error:time.sleep(.2)
  else:raise RuntimeError('owned mysql unavailable')
  database='mxmed_gate4d_preview_adminqa_init02';password=secrets.token_urlsafe(32)
  with db.cursor() as q:
   q.execute('CREATE DATABASE '+database);q.execute("CREATE USER 'mxmed_qa05'@'127.0.0.1' IDENTIFIED BY %s",(password,));q.execute("GRANT ALL ON "+database+".* TO 'mxmed_qa05'@'127.0.0.1'")
  # Explicit read-only metadata extraction, no records, triggers, routines, views or grants.
  source=json.loads((pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/environment.json').read_text())
  assert source['MXMED_DB_HOST']=='127.0.0.1' and source['MXMED_DB_NAME']=='mxmed' and source['MXMED_DB_PORT']=='3306'
  original=pymysql.connect(host='127.0.0.1',port=3306,user=source['MXMED_DB_USER'],password=source['MXMED_DB_PASS'],database='mxmed')
  with original.cursor() as q:
   q.execute('START TRANSACTION READ ONLY');q.execute("SHOW FULL TABLES WHERE Table_type='BASE TABLE'");tables=[r[0] for r in q.fetchall()]
  original.rollback();original.close()
  dumpenv=dict(env,MYSQL_PWD=source['MXMED_DB_PASS']);schema=subprocess.check_output(['mysqldump','--no-defaults','-h127.0.0.1','-P3306','-u'+source['MXMED_DB_USER'],'--no-data','--skip-triggers','--skip-lock-tables','--set-gtid-purged=OFF','mxmed',*tables],env=dumpenv)
  subprocess.run(['mysql','--no-defaults','--socket='+sock,'-uroot',database],input=schema,check=True,stdout=subprocess.DEVNULL)
  creds={r:{'email':'qa05-'+r+'@example.invalid','password':secrets.token_urlsafe(22)+'!A2'} for r in ['owner','administrator','collaborator']};(d/'credentials.json').write_text(json.dumps(creds))
  origin='https://127.0.0.1:'+str(tlsport)
  env.update({'APP_ENV':'local','MXMED_ENVIRONMENT':'local','MXMED_PREVIEW_EXPLICIT':'1','MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT':'0','MXMED_DB_HOST':'127.0.0.1','MXMED_DB_PORT':str(dbport),'MXMED_DB_NAME':database,'MXMED_DB_USER':'mxmed_qa05','MXMED_DB_PASS':password,'MXMED_PREVIEW_ORIGIN':origin,'MXMED_PREVIEW_PEPPER':secrets.token_hex(32),'MXMED_SESSION_STORE_PORT':str(vport),'MXMED_PREVIEW_NOTIFICATION_FILE':'/tmp/'+d.name+'/notifications.json','MXMED_QA05_CREDENTIALS_JSON':str(d/'credentials.json'),'MXMED_PRIVATE_MEDIA_ROOT':str(d/'private-media'),'MXMED_PUBLIC_MEDIA_ROOT':str(d/'public-media')})
  (d/'private-media').mkdir();(d/'public-media').mkdir();(d/'environment.json').write_text(json.dumps(env))
  subprocess.run(['php',str(ROOT/'scripts/qa05-fixture.php')],env=env,check=True,stdout=subprocess.DEVNULL)
  with db.cursor() as q:
   q.execute('USE '+database);q.execute('CREATE TABLE init02_write_log(id BIGINT AUTO_INCREMENT PRIMARY KEY, operation VARCHAR(10))')
   for op in ['INSERT','UPDATE','DELETE']:q.execute('CREATE TRIGGER init02_'+op.lower()+' AFTER '+op+' ON consultorios FOR EACH ROW INSERT INTO init02_write_log(operation) VALUES(\''+op+'\')')
  db.close()
  launch([str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server'),'--bind','127.0.0.1','--port',str(vport),'--dir',str(d),'--save','','--appendonly','no'])
  launch(['php','-d','memory_limit=256M','-S','127.0.0.1:'+str(phpport),'-t',str(ROOT)])
  subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(d/'tls.key'),'-out',str(d/'tls.crt'),'-days','2','-subj','/CN=127.0.0.1'],check=True,stdout=subprocess.DEVNULL,stderr=subprocess.DEVNULL)
  proxy=(ROOT/'scripts/qa05-https-proxy.py').read_text().replace('8444',str(tlsport)).replace('__Host-mxmed_qa05_session','__Host-mxmed_init02_session')
  proxy=proxy.replace("        if 'Cookie' in headers: headers['Cookie']=backend_cookie_header(headers['Cookie'])","        cookie_keys=[k for k in headers if k.lower()=='cookie']\n        raw='; '.join(headers.pop(k) for k in cookie_keys)\n        if raw: headers['Cookie']=backend_cookie_header(raw)")
  (d/'proxy.py').write_text(proxy);launch([sys.executable,str(d/'proxy.py'),'--cert',str(d/'tls.crt'),'--key',str(d/'tls.key'),'--backend-port',str(phpport)])
  state={'owner_pid':os.getpid(),'command':command(os.getpid()),'origin':origin,'expires':time.time()+86400,'source':str(ROOT),'dbport':dbport};(d/'state.json').write_text(json.dumps(state));print(json.dumps({'instance':str(d),'url':origin+'/private-app.php'}),flush=True)
  while time.time()<state['expires'] and not (d/'stop').exists():
   if any(p.poll() is not None for p in processes):raise RuntimeError('owned child stopped')
   time.sleep(1)
 finally:
  for p in reversed(processes):
   if p.poll() is None:p.terminate()
  for p in reversed(processes):
   try:p.wait(timeout=15)
   except subprocess.TimeoutExpired:p.kill();p.wait()
if __name__=='__main__':
 p=argparse.ArgumentParser();p.add_argument('action',choices=['start','run','stop','status','credentials-physician']);p.add_argument('instance',nargs='?');a=p.parse_args()
 if a.action=='start':
  d=pathlib.Path(tempfile.mkdtemp(prefix='mxmed-init02-',dir='/tmp'));log=(d/'runner.log').open('ab');subprocess.Popen([sys.executable,__file__,'run',str(d)],stdout=log,stderr=log,start_new_session=True);print(d)
 elif a.action=='run':run(a.instance)
 else:
  d=pathlib.Path(a.instance).resolve();assert d.name.startswith('mxmed-init02-') and d.stat().st_uid==os.getuid();s=json.loads((d/'state.json').read_text());assert s['source']==str(ROOT) and command(s['owner_pid'])==s['command']
  if a.action=='credentials-physician':
   assert time.time()<s['expires'], 'Preview expired; do not reuse credentials from an expired instance'
   f=d/'credentials.json';assert not f.is_symlink() and f.stat().st_uid==os.getuid() and f.stat().st_mode & 0o077 == 0
   c=json.loads(f.read_text())['owner'];assert c['email']=='qa05-owner@example.invalid'
   subprocess.run(['pbcopy'],input=c['password'],text=True,check=True)
   print('Cuenta sintética: '+c['email']+'\nContraseña sintética copiada al portapapeles.\nAcceso: '+s['origin']+'/public/identity/acceso.php\nDespués: Perfil → Consultorios. No usar credenciales del entorno original.')
  elif a.action=='stop':(d/'stop').touch()
  else:print(json.dumps({'url':s['origin']+'/private-app.php','expires':s['expires']}))
