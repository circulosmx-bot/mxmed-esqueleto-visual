#!/usr/bin/env python3
"""Owned fresh MySQL + private Valkey + signed loopback TLS. Never reads an existing DB.
No ASE-INT01 pointer/ports/PIDs are reused. Only subprocesses created here are stopped.
"""
import concurrent.futures, datetime, hashlib, hmac, http.client, http.server, json, os, pathlib, re, secrets, shutil, socket, ssl, subprocess, tempfile, threading, time
import pymysql
ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/evidence/ase-int02a'
PHASE='ase-int02a'
EXPECTED_BRANCH='feature/ase-int02a-governed-advisor-grants'
ROUTER='scripts/ase-int02a-router.php'
HOOK=None
EXTRA_PROTECTED=[]
EXTRA_SOURCES=[]
checks=[]; concurrency=[]; processes=[]
BASE='09d3aefcf20266641d81aa128cec05f4a9e4ca03'

def check(name,condition):
    checks.append({'name':name,'pass':bool(condition)})
    if not condition:raise AssertionError(name)

def protected():
    result={}
    for name in ['mxmed-step2-vitals-r1','mxmed-ase-vis01-prototype','mxmed-ase-int01-readonly']+EXTRA_PROTECTED:
        root=ROOT.parent/name
        result[name]={'head':subprocess.check_output(['git','rev-parse','HEAD'],cwd=root,text=True).strip(),'status':subprocess.check_output(['git','--no-optional-locks','status','--porcelain'],cwd=root,text=True)}
    ports=[8091,8092,8443,6385,8140,8141]
    result['listeners']={str(p):subprocess.run(['lsof','-t','-nP',f'-iTCP:{p}','-sTCP:LISTEN'],capture_output=True,text=True).stdout.split() for p in ports}
    p=pathlib.Path('/tmp/mxmed-ase-int01-owned.json');result['ase_int01_pointer_sha256']=hashlib.sha256(p.read_bytes()).hexdigest() if p.exists() else None
    return result

def port():
    with socket.socket() as s:s.bind(('127.0.0.1',0));return s.getsockname()[1]

def wait_until(fn,seconds=15):
    until=time.monotonic()+seconds
    while time.monotonic()<until:
        try:
            if fn():return
        except (OSError,pymysql.MySQLError):pass
        time.sleep(.05)
    raise RuntimeError('owned_resource_readiness_timeout')

def main():
    assert subprocess.check_output(['git','branch','--show-current'],cwd=ROOT,text=True).strip()==EXPECTED_BRANCH
    assert subprocess.run(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=ROOT).returncode==0
    before=protected();os.umask(0o077)
    owned=pathlib.Path(tempfile.mkdtemp(prefix='mxmed-'+PHASE+'-',dir='/tmp'))
    mysql_port,valkey_port,php_port,tls_port=[port() for _ in range(4)]
    assert len({mysql_port,valkey_port,php_port,tls_port})==4
    assert not {mysql_port,valkey_port,php_port,tls_port}&{3306,3309,8091,8092,8443,6385,8140,8141}
    database='mxmed_gate4d_preview_'+PHASE.replace('-','')+'_'+secrets.token_hex(5)
    password=secrets.token_urlsafe(32);fixture_password=secrets.token_urlsafe(32)
    origin=f'https://127.0.0.1:{tls_port}'
    env={k:v for k,v in os.environ.items() if k in ['PATH','HOME','USER','TMPDIR','LANG','LC_ALL','TZ']}
    env.update(ASE_TEAM_QA_PHASE=PHASE,APP_ENV='local',MXMED_ENVIRONMENT='local',MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT='0',MXMED_PREVIEW_EXPLICIT='1',MXMED_PREVIEW_ORIGIN=origin,MXMED_PREVIEW_PEPPER=secrets.token_hex(32),MXMED_DB_HOST='127.0.0.1',MXMED_DB_PORT=str(mysql_port),MXMED_DB_NAME=database,MXMED_DB_USER='ase_writer',MXMED_DB_PASS=password,MXMED_SESSION_STORE_PORT=str(valkey_port),MXMED_PREVIEW_NOTIFICATION_FILE=str(owned/'inert-notifications.json'),MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED='1',MXMED_GOVERNANCE_PROXY_KEY=secrets.token_hex(32),MXMED_AUDIT_IP_HMAC_SECRET=secrets.token_hex(32),MXMED_AUDIT_IP_HMAC_KEY_VERSION='audit-ip-local-v001',ASE_INT02A_OWNED=str(owned),ASE_INT02A_FIXTURE_PASSWORD=fixture_password)
    tls=None;admin=None;error=None
    def spawn(name,args,child_env=env):
        with (owned/(name+'.log')).open('wb') as log:
            p=subprocess.Popen(args,cwd=ROOT,env=child_env,stdin=subprocess.DEVNULL,stdout=log,stderr=log)
        processes.append(p);return p
    def execute(cursor,sql,args=()):cursor.execute(sql,args);return cursor
    try:
        mysqld=shutil.which('mysqld');mysql=shutil.which('mysql');valkey=shutil.which('valkey-server') or str(pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-runtime/valkey-server')
        assert mysqld and mysql and pathlib.Path(valkey).is_file()
        data=owned/'mysql';data.mkdir()
        with (owned/'initialize.log').open('wb') as log:subprocess.run([mysqld,'--no-defaults','--initialize-insecure','--datadir='+str(data)],stdout=log,stderr=log,check=True,timeout=60)
        spawn('mysql',[mysqld,'--no-defaults','--datadir='+str(data),'--socket='+str(owned/'mysql.sock'),'--port='+str(mysql_port),'--bind-address=127.0.0.1','--skip-name-resolve','--mysqlx=0','--skip-log-bin','--pid-file='+str(owned/'mysql.pid')])
        def connect():
            nonlocal admin
            admin=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',autocommit=True);return True
        wait_until(connect)
        def sql(statement,args=()):
            with admin.cursor() as cur:cur.execute(statement,args);return cur.fetchall()
        check('Owned new datadir verified before fixture DDL',pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==data.resolve())
        sql(f'CREATE DATABASE `{database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');admin.select_db(database)
        def source_sql(source):
            r=subprocess.run([mysql,'--no-defaults','--protocol=SOCKET','--socket='+str(owned/'mysql.sock'),'-uroot',database],input=source,text=True,capture_output=True,env=env)
            if r.returncode:
                (owned/'schema-error.log').write_text(r.stderr);raise RuntimeError('owned_schema_failed')
        for file in ['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql']:
            source_sql((ROOT/'modules/identity/db/migrations'/file).read_text())
        source_sql((ROOT/'modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql').read_text())
        sql('CREATE TABLE platform_audit_stream_heads(stream_key VARCHAR(191) PRIMARY KEY,last_sequence_number BIGINT NOT NULL,last_event_hash CHAR(64) NOT NULL,hash_version VARCHAR(32) NULL,updated_at DATETIME(6) NULL) ENGINE=InnoDB')
        sql("CREATE USER 'ase_audit_definer'@'localhost' IDENTIFIED BY %s ACCOUNT LOCK",(secrets.token_urlsafe(32),))
        sql(f"GRANT SELECT,UPDATE ON `{database}`.platform_audit_stream_heads TO 'ase_audit_definer'@'localhost'")
        for file in ['audit_mp01c_r2_lock.sql','audit_mp01c_r2_cas.sql']:
            source=(ROOT/'modules/platform/db/routines'/file).read_text().replace('{{RESTRICTED_DEFINER}}',"'ase_audit_definer'@'localhost'").replace('`mxmed`.','`'+database+'`.')
            source_sql(source)
        sql(f"GRANT EXECUTE ON `{database}`.* TO 'ase_audit_definer'@'localhost'")
        # Only this fresh instance has this synthetic bootstrap principal. HTTP uses neither root nor it.
        sql("CREATE USER 'ase_fixture'@'localhost' IDENTIFIED BY %s",(fixture_password,));sql(f"GRANT ALL ON `{database}`.* TO 'ase_fixture'@'localhost'");sql("GRANT SHOW_ROUTINE ON *.* TO 'ase_fixture'@'localhost'")
        sql("CREATE USER 'ase_writer'@'127.0.0.1' IDENTIFIED BY %s",(password,))
        for table in ['auth_accounts','auth_account_credentials','internal_staff','internal_capability_delegations']:
            sql(f"GRANT SELECT ON `{database}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT SELECT,INSERT,UPDATE ON `{database}`.internal_operator_grants TO 'ase_writer'@'127.0.0.1'")
        for table in ['platform_audit_events','platform_audit_stream_heads']:
            sql(f"GRANT SELECT,INSERT ON `{database}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT EXECUTE,LOCK TABLES ON `{database}`.* TO 'ase_writer'@'127.0.0.1'");sql("GRANT SHOW_ROUTINE ON *.* TO 'ase_writer'@'127.0.0.1'")
        actors=['director','master','advisor','target','expired','revoked','suspended','disabled','changed','outsider','suspended_target','disabled_target','master2','legacy']
        fixture_secret=secrets.token_urlsafe(32);(owned/'fixture-password').write_text(fixture_secret)
        hashed=subprocess.check_output(['php','-r',"echo password_hash(stream_get_contents(STDIN), PASSWORD_ARGON2ID);"],input=fixture_secret,text=True)
        for actor in actors:
            aid='qa_'+actor
            sql("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(%s,%s,%s,'active',CURRENT_TIMESTAMP)",(aid,aid+'@example.test',aid+'@example.test'))
            sql('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES(%s,%s,CURRENT_TIMESTAMP)',(aid,hashed))
            if actor not in ['outsider','legacy']:
                category='DIRECTOR' if actor=='director' else 'MASTER_ADMIN' if actor in ['master','master2'] else 'ADVISOR'
                sql("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(%s,%s,'ACTIVE')",(aid,category))
        def grant(actor,cap):sql("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),%s,%s,'ACTIVE')",('qa_'+actor,cap))
        grant('master','internal_advisors_manage');grant('target','media_review_read')
        sql("INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(UUID(),'qa_master','media_review_approve','qa_director','ACTIVE')")
        spawn('valkey',[valkey,'--bind','127.0.0.1','--port',str(valkey_port),'--protected-mode','yes','--save','','--appendonly','no','--dir',str(owned)])
        def port_ready(p):
            with socket.create_connection(('127.0.0.1',p),timeout=.2):return True
        wait_until(lambda:port_ready(valkey_port))
        fixture=['php','modules/identity/tests/AdvisorMutationFixture.php']
        r=subprocess.run(fixture+['sessions'],cwd=ROOT,env=env,text=True,capture_output=True)
        if r.returncode:(owned/'fixture-error.log').write_text(r.stderr);raise RuntimeError('synthetic_session_setup_failed')
        tokens=json.loads(r.stdout);(owned/'sessions.json').write_text(r.stdout)
        r=subprocess.run(fixture+['strict'],cwd=ROOT,env=env,text=True,capture_output=True)
        if r.returncode:
            (owned/'native-error.log').write_text(r.stderr);raise RuntimeError('native_writer_failed: '+r.stderr)
        check('Canonical strict writer native transaction',True)
        sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id IN ('qa_suspended','qa_suspended_target')")
        sql("UPDATE auth_accounts SET status='disabled' WHERE account_id IN ('qa_disabled','qa_disabled_target')")
        sql("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_changed'")
        spawn('php',['php','-S','127.0.0.1:'+str(php_port),'-t',str(ROOT),str(ROOT/ROUTER)]);wait_until(lambda:port_ready(php_port))
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(owned/'key.pem'),'-out',str(owned/'cert.pem'),'-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1'],capture_output=True,check=True)
        class Handler(http.server.BaseHTTPRequestHandler):
            def log_message(self,*args):pass
            def run(self):
                if self.headers.get_all('Host')!=[f'127.0.0.1:{tls_port}']:self.send_error(421);return
                if self.headers.get('Transfer-Encoding') or len(self.headers.get_all('Content-Length') or [])>1:self.send_error(400);return
                length=int(self.headers.get('Content-Length','0'))
                if length<0 or length>2048:self.send_error(413);return
                body=self.rfile.read(length)
                headers={k:v for k,v in self.headers.items() if not k.lower().startswith(('x-mxmed-','x-forwarded-')) and k.lower() not in ['connection','host','content-length']}
                headers.update(Host=f'127.0.0.1:{tls_port}',Connection='close');stamp=str(int(time.time()));nonce=secrets.token_hex(16)
                parts=[stamp,nonce,self.command,self.path,headers['Host'],self.headers.get('Origin',''),self.headers.get('Referer',''),self.headers.get('Cookie',''),self.headers.get('X-CSRF-Token',''),hashlib.sha256(body).hexdigest()]
                headers.update({'X-Mxmed-Tls-Time':stamp,'X-Mxmed-Tls-Nonce':nonce,'X-Mxmed-Tls-Proof':hmac.new(env['MXMED_GOVERNANCE_PROXY_KEY'].encode(),'\n'.join(parts).encode(),hashlib.sha256).hexdigest()})
                conn=http.client.HTTPConnection('127.0.0.1',php_port,timeout=20)
                try:
                    conn.request(self.command,self.path,body=body,headers=headers);resp=conn.getresponse();data=resp.read();self.send_response(resp.status)
                    for key,value in resp.getheaders():
                        if key.lower() not in ['connection','transfer-encoding','content-length','server','date']:self.send_header(key,value)
                    self.send_header('Content-Length',str(len(data)));self.end_headers()
                    if self.command!='HEAD':self.wfile.write(data)
                finally:conn.close()
            do_GET=do_HEAD=do_POST=do_PUT=do_PATCH=do_DELETE=do_OPTIONS=run
        tls=http.server.ThreadingHTTPServer(('127.0.0.1',tls_port),Handler)
        tls_context=ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER);tls_context.load_cert_chain(owned/'cert.pem',owned/'key.pem');tls.socket=tls_context.wrap_socket(tls.socket,server_side=True)
        threading.Thread(target=tls.serve_forever,daemon=True).start()
        client_context=ssl.create_default_context(cafile=str(owned/'cert.pem'))
        def request(actor='director',path='/api/internal/team/capability.php',method='POST',body=None,headers=None):
            hs={'Origin':origin,'Sec-Fetch-Site':'same-origin','Content-Type':'application/json'}
            if actor:hs['Cookie']='__Host-mxmed_session='+tokens[actor]
            hs.update(headers or {});hs={k:v for k,v in hs.items() if v is not None}
            payload=json.dumps(body) if isinstance(body,dict) else (body or '')
            c=http.client.HTTPSConnection('127.0.0.1',tls_port,context=client_context,timeout=20)
            try:c.request(method,path,body=payload,headers=hs);r=c.getresponse();raw=r.read();return r.status,json.loads(raw) if raw else {},dict(r.getheaders())
            finally:c.close()
        csrf={}
        for actor in ['director','master']:
            status,d,h=request(actor,path='/api/internal/team/csrf.php',method='GET');check(actor+' authenticated CSRF bootstrap',status==200 and d['csrf_token'].startswith('session.'));csrf[actor]=d['csrf_token']
            check('Private no-store bootstrap '+actor,h.get('Cache-Control')=='private, no-store' and 'Access-Control-Allow-Origin' not in h)
        def command(actor='director',operation='grant',target='qa_target',capability='media_review_approve',**kw):
            return request(actor,body={'operation':operation,'target':target,'capability':capability},headers={'X-CSRF-Token':csrf.get(actor,csrf['director']),**kw})
        if HOOK is not None:
            HOOK(locals())
        else:
            check('A Director individual grant',command()[0]==200)
            check('R Active grant replay returns 409',command()[0]==409)
            check('B Director individual revoke',command(operation='revoke')[0]==200)
            check('R Repeated revoke returns 409',command(operation='revoke')[0]==409)
            status,d,_=request(path='/api/internal/team/read.php?target=qa_target',method='GET')
            check('C U unrelated grant retained and next read observes revocation',status==200 and d['items'][0]['grants']==['media_review_read'])
            check('D Master exact delegated grant',command('master')[0]==200)
            check('E Master outside delegation denied',command('master',capability='media_review_improve')[0]==403)
            sql("UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
            check('F Master lost management denied',command('master',operation='revoke')[0]==403)
            check('F Bootstrap denied after management revoked',request('master',path='/api/internal/team/csrf.php',method='GET')[0]==403)
            sql("UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'")
            for actor in ['director','master']:
                for target in ['qa_master','qa_director','qa_master2']:
                    check('G Protected target '+actor+' '+target,command(actor,target=target)[0]==403)
            for actor,expected in [(None,401),('advisor',403),('target',403),('outsider',403),('expired',401),('revoked',401),('suspended',403),('disabled',401),('changed',401)]:
                check('H I J K Actor denied '+str(actor),command(actor)[0]==expected)
                check('No CSRF exposed to '+str(actor),request(actor,path='/api/internal/team/csrf.php',method='GET')[0]==expected)
            for target in ['qa_suspended_target','qa_disabled_target','qa_outsider','qa_nonexistent']:
                check('L Ineligible target '+target,command(target=target)[0]==403)
            for cap in ['all','*','admin.everything','support.all','media_review','internal_advisors_manage','profile_initial_ownership_approve','clinical_sign']:
                check('Q V W Ineligible capability '+cap,command(capability=cap)[0]==400)
            for op in ['register','appoint_master','remove_master','suspend','reactivate','delegate','revoke_delegation']:
                check('Excluded operation '+op,command(operation=op)[0]==400)
            normal={'operation':'grant','target':'qa_target','capability':'media_review_improve'}
            for body in [dict(normal,actor='qa_director'),dict(normal,role='DIRECTOR'),dict(normal,target=['qa_target']),dict(normal,capability=['media_review_improve']),dict(normal,operation=True),dict(normal,capability=None),dict(normal,target="qa_target' OR 1=1"),{},[],{'operation':'grant'},dict(normal,precondition=None)]:
                check('M Strict fields '+str(len(checks)),request(body=json.dumps(body),headers={'X-CSRF-Token':csrf['director']})[0]==400)
            for body in ['{"operation":"grant","target":"qa_target","capability":"media_review_improve","capability":"media_review_read"}', '{bad', 'null', '{"operation":"grant","target":"qa_target","target":"qa_master","capability":"media_review_read"}']:
                check('M Malformed or duplicate JSON '+str(len(checks)),request(body=body,headers={'X-CSRF-Token':csrf['director']})[0]==400)
            for token in ['', 'invalid',csrf['master']]:check('N CSRF denied '+str(len(checks)),request(body=normal,headers={'X-CSRF-Token':token})[0]==403)
            for hs in [{'Origin':'https://evil.example'},{'Origin':None},{'Sec-Fetch-Site':'cross-site'},{'Sec-Fetch-Site':'same-site'},{'Origin':origin+'.evil'}]:
                check('O Cross-origin/site denied '+str(len(checks)),request(body=normal,headers={'X-CSRF-Token':csrf['director'],**hs})[0]==403)
            for method in ['GET','HEAD','PUT','PATCH','DELETE','OPTIONS']:
                check('P Non POST '+method,request(method=method)[0]==405)
            check('Explicit JSON type',request(body=normal,headers={'X-CSRF-Token':csrf['director'],'Content-Type':'text/plain'})[0]==415)
            check('Bounded payload',request(body='x'*1025)[0]==413)
            check('Query authority rejected',request(path='/api/internal/team/capability.php?role=DIRECTOR',body=normal)[0]==400)
            # A failing native audit append must undo both grant and head changes.
            before_audit=sql('SELECT COUNT(*) FROM platform_audit_events')[0][0];before_heads=sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')
            sql("CREATE TRIGGER ase_int02a_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'")
            try:check('S Audit failure returns unavailable',command(capability='media_review_improve')[0]==503)
            finally:sql('DROP TRIGGER ase_int02a_audit_failure')
            check('S Audit failure rolls back grant and head',not sql("SELECT 1 FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_improve'") and sql('SELECT COUNT(*) FROM platform_audit_events')[0][0]==before_audit and sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key')==before_heads)
            # Genuine independent SQL connection holds actor/target lock before the HTTP writer.
            def race(name,lock_id,change,restore,actor='director'):
                blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=fixture_password,database=database,autocommit=False)
                try:
                    with blocker.cursor() as cur:cur.execute('SELECT status FROM auth_accounts WHERE account_id=%s FOR UPDATE',(lock_id,));cur.fetchall()
                    with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
                        future=pool.submit(command,actor,'grant','qa_target','media_review_improve' if actor=='director' else 'media_review_approve')
                        wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10)
                        wait_rows=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,REQUESTING_THREAD_ID,BLOCKING_ENGINE_TRANSACTION_ID,BLOCKING_THREAD_ID FROM performance_schema.data_lock_waits');waits=len(wait_rows)
                        with blocker.cursor() as cur:cur.execute(change)
                        blocker.commit();response=future.result(timeout=15)
                    check('T '+name,response[0]==403 and waits>0)
                    concurrency.append({'name':name,'connections':'independent fixture SQL transaction + HTTP canonical writer','observed_data_lock_waits':waits,'lock_wait_transaction_and_thread_ids':wait_rows,'http_status':response[0],'result':'PASS'})
                finally:blocker.rollback();blocker.close();sql(restore)
            race('target promoted while request waits','qa_target',"UPDATE internal_staff SET governance_class='MASTER_ADMIN' WHERE account_id='qa_target'","UPDATE internal_staff SET governance_class='ADVISOR' WHERE account_id='qa_target'")
            race('target suspended while request waits','qa_target',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'","UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_target'")
            race('target account disabled while request waits','qa_target',"UPDATE auth_accounts SET status='disabled' WHERE account_id='qa_target'","UPDATE auth_accounts SET status='active' WHERE account_id='qa_target'")
            race('actor suspended while request waits','qa_master',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_master'","UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_master'",'master')
            race('management revoked while request waits','qa_master',"UPDATE internal_operator_grants SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE account_id='qa_master' AND capability='internal_advisors_manage'","UPDATE internal_operator_grants SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_master' AND capability='internal_advisors_manage'",'master')
            race('delegation revoked while request waits','qa_master',"UPDATE internal_capability_delegations SET status='REVOKED',revoked_at=CURRENT_TIMESTAMP WHERE master_account_id='qa_master'","UPDATE internal_capability_delegations SET status='ACTIVE',revoked_at=NULL WHERE master_account_id='qa_master'",'master')
            race('credential changes while request waits','qa_director',"UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_director'","UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='qa_director'")
            # Legacy callers retain their existing semantics; only the new adapter supplies the strict scope.
            for actor,op,target,cap in [('master','register','qa_legacy',None),('director','suspend','qa_legacy',None),('master','reactivate','qa_legacy',None),('director','appoint_master','qa_legacy',None),('director','grant','qa_legacy','internal_advisors_manage'),('director','delegate','qa_legacy','media_review_read'),('director','grant','qa_legacy','media_review_read'),('director','remove_master','qa_legacy',None),('director','grant','qa_director','media_review_read'),('director','revoke','qa_director','media_review_read')]:
                r=subprocess.run(fixture+['legacy',actor,op,target]+([cap] if cap else []),cwd=ROOT,env=env,text=True,capture_output=True)
                if r.returncode:(owned/'legacy-error.log').write_text(r.stderr)
                check('IW01 backward compatibility '+op+' '+target,r.returncode==0)
            check('IW01 demotion revokes management and delegation preserves operational',sql("SELECT capability FROM internal_operator_grants WHERE account_id='qa_legacy' AND status='ACTIVE'")==(('media_review_read',),) and not sql("SELECT 1 FROM internal_capability_delegations WHERE master_account_id='qa_legacy' AND status='ACTIVE'"))
            events=sql("SELECT real_actor_reference,resource_reference,metadata_json FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED'")
            check('Canonical audit actor target capability operation retained',len(events)>=13 and all(json.loads(e[2])['producer_metadata']['target_account_id']==e[1] and e[0].startswith('account:qa_') for e in events))
            check('Historical revoked grant retained',bool(sql("SELECT 1 FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_approve' AND status='REVOKED' AND revoked_at IS NOT NULL")))
            check('No clinical tables or membership writes',not sql("SHOW TABLES LIKE 'auth_account_memberships'") and not sql("SHOW TABLES LIKE 'clinical%%'"))
            check('No notification sent',not (owned/'inert-notifications.json').exists())
            for f in ['assets/js/internal-team.js','partials/identity/internal-team.php','assets/css/internal-team.css','assets/css/internal-team-commercial.css','assets/css/internal-team-admission.css','assets/css/internal-team-readonly.css','modules/identity/services/InternalCapabilityCatalog.php']:
                check('UI/catalog unchanged '+f,subprocess.check_output(['git','show',BASE+':'+f],cwd=ROOT)==(ROOT/f).read_bytes())
            check('X Fresh synthetic instance only',sql('SELECT DATABASE()')[0][0]==database and sql('SELECT @@port')[0][0]==mysql_port)
            print('QA checks complete',len(checks),flush=True)
    except Exception as exc:
        error=type(exc).__name__+': '+str(exc)
        # Errors here contain no cookies or credentials; private subprocess logs stay outside Git.
        print('QA failed:',error,flush=True)
        for file in ['schema-error.log','fixture-error.log','legacy-error.log','php.log']:
            p=owned/file
            if p.exists() and file!='php.log':print('Private diagnostic path:',p,flush=True)
    finally:
        if tls:tls.shutdown();tls.server_close()
        if admin:admin.close()
        for p in reversed(processes):
            if p.poll() is None:p.terminate()
            try:p.wait(timeout=15)
            except subprocess.TimeoutExpired:
                error=(error or '')+' owned process did not terminate; directory retained'
        stopped=all(p.poll() is not None for p in processes)
        after=protected();unchanged=before==after
        if not unchanged:error=(error or '')+' protected worktree/listener changed'
        # Only our newly-created directory is disposed; failed private logs retained for diagnosis.
        if stopped and error is None:shutil.rmtree(owned)
        report={'result':'PASS' if error is None else 'FAIL','error':error,'source_baseline':BASE,'checks':checks,'check_count':len(checks),'concurrency':concurrency,'environment':{'database':database,'new_datadir':str(owned/'mysql'),'mysql_port':mysql_port,'valkey_port':valkey_port,'php_port':php_port,'tls_origin':origin,'http_principal':'ase_writer','native_audit_routines':True,'real_database_connections':0,'real_staff_operations':0},'rollback':{'owned_processes_stopped':stopped,'owned_directory_deleted':not owned.exists(),'protected_unchanged':unchanged},'protected_before':before,'protected_after':after,'ui_still_read_only':HOOK is None,'operational_activation':False}
        sources=['modules/identity/services/InternalGovernanceService.php','modules/identity/Team/MutationHttp.php','api/internal/team/capability.php','api/internal/team/csrf.php','modules/identity/tests/AdvisorMutationFixture.php','scripts/ase-int02a-router.php','scripts/ase-int02a-qa.py','modules/identity/services/InternalCapabilityCatalog.php','modules/identity/http/CanonicalHttpSessionResolver.php','modules/identity/http/IdentityHttpComposition.php','modules/identity/http/CsrfTokenService.php','modules/identity/http/GovernanceTransport.php','modules/identity/Team/ReadService.php']
        report['source_sha256']={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in sources+EXTRA_SOURCES}
        OUT.mkdir(parents=True,exist_ok=True)
        path=OUT/('qa.json' if error is None else 'failed-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'.json')
        path.write_text(json.dumps(report,indent=2)+'\n');print('EVIDENCE',path,flush=True)
    if error:raise SystemExit(1)

if __name__=='__main__':main()
