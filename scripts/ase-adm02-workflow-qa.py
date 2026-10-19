#!/usr/bin/env python3
"""Owned fresh MySQL + private Valkey + signed loopback TLS. Never reads an existing DB.
No ASE-INT01 pointer/ports/PIDs are reused. Only subprocesses created here are stopped.
"""
import concurrent.futures, datetime, hashlib, hmac, http.client, http.server, json, os, pathlib, re, secrets, shutil, socket, ssl, subprocess, tempfile, threading, time
import pymysql
ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/evidence/ase-adm02-functional'
PHASE='ase-adm02imp01'
EXPECTED_BRANCH='feature/ase-adm02-functional-consolidation'
ROUTER='scripts/ase-adm02-workflow-router.php'
HOOK=None
SETUP_HOOK=None
SEED_STAFF_ACTORS=None
REVIEW_SECONDS=3600
EXTRA_PROTECTED=['mxmed-ase-int02a-governed-grants','mxmed-ase-int02b-team-permission-ui','mxmed-ase-int03a-advisor-lifecycle','mxmed-ase-int03b-master-governance-prep','mxmed-ase-int03b-master-privileged-backend','mxmed-ase-int03b-master-ui','mxmed-act03-governance-check-compat01','mxmed-ase-adm02-staff-admission-policy','mxmed-ase-adm02-staff-case-foundation','mxmed-ase-adm02-staff-invitation-binding','mxmed-ase-adm02-private-staff-media']
EXTRA_SOURCES=[]
EXTRA_WRITABLE_TABLES=[]
checks=[]; concurrency=[]; processes=[]
BASE='58f3fcf5db81ff5f2804833fd8470eed7c46e2c8'

def check(name,condition):
    checks.append({'name':name,'pass':bool(condition)})
    print(('PASS ' if condition else 'FAIL ')+name,flush=True)
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
    env.update(ASE_TEAM_QA_PHASE=PHASE,APP_ENV='local',MXMED_ENVIRONMENT='local',MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT='0',MXMED_PREVIEW_EXPLICIT='1',MXMED_PREVIEW_ORIGIN=origin,MXMED_PREVIEW_PEPPER=secrets.token_hex(32),MXMED_DB_HOST='127.0.0.1',MXMED_DB_PORT=str(mysql_port),MXMED_DB_NAME=database,MXMED_DB_USER='ase_writer',MXMED_DB_PASS=password,MXMED_SESSION_STORE_PORT=str(valkey_port),MXMED_PREVIEW_NOTIFICATION_FILE=str(owned/'inert-notifications.json'),MXMED_STAFF_ADMISSION_FOUNDATION_ENABLED='1',MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED='1',MXMED_MASTER_GOVERNANCE_ENABLED='0',MXMED_GOVERNANCE_PROXY_KEY=secrets.token_hex(32),MXMED_AUDIT_IP_HMAC_SECRET=secrets.token_hex(32),MXMED_AUDIT_IP_HMAC_KEY_VERSION='audit-ip-local-v001',ASE_INT02A_OWNED=str(owned),ASE_INT02A_FIXTURE_PASSWORD=fixture_password)
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
        source_sql((ROOT/'modules/identity/db/migrations/2026_10_18_04_staff_admission_foundation.sql').read_text())
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
        for table in EXTRA_WRITABLE_TABLES:
            assert table=='internal_staff'
            sql(f"GRANT UPDATE ON `{database}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT SELECT,INSERT,UPDATE ON `{database}`.internal_operator_grants TO 'ase_writer'@'127.0.0.1'")
        for table in ['platform_audit_events','platform_audit_stream_heads']:
            sql(f"GRANT SELECT,INSERT ON `{database}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT EXECUTE,LOCK TABLES ON `{database}`.* TO 'ase_writer'@'127.0.0.1'");sql("GRANT SHOW_ROUTINE ON *.* TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT SELECT,INSERT ON `{database}`.auth_staff_admission_versions TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT SELECT,INSERT ON `{database}`.auth_staff_admission_operations TO 'ase_writer'@'127.0.0.1'")
        sql(f"GRANT SELECT, INSERT(case_id,channel,creator_account_id,recipient_reference,proposed_category,created_at,updated_at), UPDATE(version,current_profile_version,state,updated_at) ON `{database}`.auth_staff_admission_cases TO 'ase_writer'@'127.0.0.1'")
        actors=['director' ,'master','advisor','target','expired','revoked','suspended','disabled','changed','outsider','suspended_target','disabled_target','master2','legacy']
        fixture_secret=secrets.token_urlsafe(32);(owned/'fixture-password').write_text(fixture_secret)
        hashed=subprocess.check_output(['php','-r',"echo password_hash(stream_get_contents(STDIN), PASSWORD_ARGON2ID);"],input=fixture_secret,text=True)
        for actor in actors:
            aid='qa_'+actor
            sql("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(%s,%s,%s,'active',CURRENT_TIMESTAMP)",(aid,aid+'@example.test',aid+'@example.test'))
            sql('INSERT INTO auth_account_credentials(account_id,password_hash,password_changed_at) VALUES(%s,%s,CURRENT_TIMESTAMP)',(aid,hashed))
            if actor not in ['outsider','legacy'] and (SEED_STAFF_ACTORS is None or actor in SEED_STAFF_ACTORS):
                category='DIRECTOR' if actor=='director' else 'MASTER_ADMIN' if actor in ['master','master2'] else 'ADVISOR'
                sql("INSERT INTO internal_staff(account_id,governance_class,status) VALUES(%s,%s,'ACTIVE')",(aid,category))
        def grant(actor,cap):sql("INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),%s,%s,'ACTIVE')",('qa_'+actor,cap))
        grant('master','internal_advisors_manage')
        if SEED_STAFF_ACTORS is None or 'target' in SEED_STAFF_ACTORS:grant('target','media_review_read')
        sql("INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(UUID(),'qa_master','media_review_approve','qa_director','ACTIVE')")
        spawn('valkey',[valkey,'--bind','127.0.0.1','--port',str(valkey_port),'--protected-mode','yes','--save','','--appendonly','no','--dir',str(owned)])
        def port_ready(p):
            with socket.create_connection(('127.0.0.1',p),timeout=.2):return True
        wait_until(lambda:port_ready(valkey_port))
        fixture=['php','modules/identity/tests/StaffAdmissionFixture.php']
        r=subprocess.run(fixture+['sessions'],cwd=ROOT,env=env,text=True,capture_output=True)
        if r.returncode:(owned/'fixture-error.log').write_text(r.stderr);raise RuntimeError('synthetic_session_setup_failed')
        tokens=json.loads(r.stdout);(owned/'sessions.json').write_text(r.stdout)
        sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id IN ('qa_suspended','qa_suspended_target')")
        sql("UPDATE auth_accounts SET status='disabled' WHERE account_id IN ('qa_disabled','qa_disabled_target')")
        sql("UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_changed'")
        pin=subprocess.check_output(fixture+['deployment'],cwd=ROOT,env=env,text=True).strip()
        env.update(MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION=str(owned/'admission-deployment.json'),MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256=pin)
        spec_setup=__import__('importlib.util',fromlist=['spec_from_file_location'])
        spec=spec_setup.spec_from_file_location('wf_setup',ROOT/'scripts/ase-adm02-workflow-checks.py');wf=spec_setup.module_from_spec(spec);spec.loader.exec_module(wf)
        wf.setup({**locals(),'ROOT':ROOT},check)
        if SETUP_HOOK:SETUP_HOOK({**locals(),'ROOT':ROOT},check)
        assert 3600<=REVIEW_SECONDS<=86400
        review_deadline=time.time()+REVIEW_SECONDS
        if REVIEW_SECONDS>3600:
            approval=json.loads((owned/'workflow-approval.json').read_text());approval['expires']=int(review_deadline);(owned/'workflow-approval.json').write_text(json.dumps(approval))
            deployment=json.loads((owned/'admission-deployment.json').read_text());deployment['expires_at']=datetime.datetime.fromtimestamp(review_deadline,datetime.timezone.utc).isoformat();payload=json.dumps(deployment);(owned/'admission-deployment.json').write_text(payload);env['MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256']=hashlib.sha256(payload.encode()).hexdigest()
        (owned/'review-env.json').write_text(json.dumps(env))
        spawn('php',['php','-S','127.0.0.1:'+str(php_port),'-t',str(ROOT),str(ROOT/ROUTER)]);wait_until(lambda:port_ready(php_port))
        subprocess.run(['openssl','req','-x509','-newkey','rsa:2048','-nodes','-keyout',str(owned/'key.pem'),'-out',str(owned/'cert.pem'),'-days','1','-subj','/CN=127.0.0.1','-addext','subjectAltName=IP:127.0.0.1'],capture_output=True,check=True)
        class Handler(http.server.BaseHTTPRequestHandler):
            def log_message(self,*args):pass
            def run(self):
                if self.headers.get_all('Host')!=[f'127.0.0.1:{tls_port}']:self.send_error(421);return
                if self.headers.get('Transfer-Encoding') or len(self.headers.get_all('Content-Length') or [])>1:self.send_error(400);return
                length=int(self.headers.get('Content-Length','0'))
                if length<0 or length>200000:self.send_error(413);return
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
            try:
                c.request(method,path,body=payload,headers=hs);r=c.getresponse();raw=r.read()
                try:decoded=json.loads(raw) if raw else {}
                except ValueError:decoded={'non_json_transport_response':True}
                return r.status,decoded,dict(r.getheaders())
            finally:c.close()
        from importlib.util import spec_from_file_location, module_from_spec
        spec=spec_from_file_location('admission_checks',ROOT/'scripts/ase-adm02-workflow-checks.py')
        tests=module_from_spec(spec);spec.loader.exec_module(tests);(HOOK or tests.run)({**locals(),'ROOT':ROOT,'BASE':BASE},check,concurrency)
        if os.environ.get('MXMED_KEEP_OWNED_WORKFLOW')=='1':
            expiry=review_deadline
            (owned/'review-info.json').write_text(json.dumps({'url':origin+'/internal/media-review/index.php?section=equipo','expiry_unix':expiry,'owner_pid':os.getpid()}))
            files=[*ROOT.glob('modules/identity/StaffWorkflow/*.php'),*ROOT.glob('modules/identity/StaffMedia/*.php'),*ROOT.glob('modules/identity/StaffAdmission/*.php'),*ROOT.glob('scripts/ase-adm02-workflow*'),ROOT/'modules/identity/services/InternalGovernanceService.php',ROOT/'modules/identity/services/StaffMfaService.php',ROOT/'modules/identity/MasterGovernance/Composition.php',ROOT/'modules/identity/db/migrations/2026_10_18_07_staff_workflow.sql',ROOT/'modules/identity/tests/StaffWorkflowFixture.php',ROOT/'assets/js/staff-workflow.js',ROOT/'assets/js/internal-team.js',ROOT/'partials/identity/internal-team.php',ROOT/'partials/identity/staff-workflow.php']
            after=protected();assert before==after
            OUT.mkdir(parents=True,exist_ok=True)
            (OUT/'qa.json').write_text(json.dumps({'result':'PASS','source_baseline':BASE,'checks':checks,'check_count':len(checks),'concurrency':concurrency,'protected_before':before,'protected_after':after,'protected_unchanged':True,'preview':{'url':origin+'/internal/media-review/index.php?section=equipo','expiry_unix':expiry,'owned_directory':str(owned),'status':'HEALTHY_OWNED_EXPIRING'},'source_sha256':{str(f.relative_to(ROOT)):hashlib.sha256(f.read_bytes()).hexdigest() for f in files if f.is_file()},'ui_changed':True,'real_personal_data_used':False,'operational_activation':False},indent=2)+'\n')
            print('OWNED_REVIEW',origin,'OWNER',str(owned),flush=True)
            while time.time()<expiry and not (owned/'stop-review').exists():time.sleep(1)

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
        report={'result':'PASS' if error is None else 'FAIL','error':error,'source_baseline':BASE,'checks':checks,'check_count':len(checks),'concurrency':concurrency,'environment':{'database':database,'new_datadir':str(owned/'mysql'),'mysql_port':mysql_port,'valkey_port':valkey_port,'php_port':php_port,'tls_origin':origin,'http_principal':'ase_writer','native_audit_routines':True,'real_database_connections':0,'real_staff_operations':0},'rollback':{'owned_processes_stopped':stopped,'owned_directory_deleted':not owned.exists(),'protected_unchanged':unchanged},'protected_before':before,'protected_after':after,'ui_changed':True,'operational_activation':False}
        sources=['modules/identity/services/InternalGovernanceService.php','modules/identity/Team/MutationHttp.php','api/internal/team/capability.php','api/internal/team/csrf.php','modules/identity/tests/AdvisorMutationFixture.php','scripts/ase-int02a-router.php','scripts/ase-int02a-qa.py','modules/identity/services/InternalCapabilityCatalog.php','modules/identity/http/CanonicalHttpSessionResolver.php','modules/identity/http/IdentityHttpComposition.php','modules/identity/http/CsrfTokenService.php','modules/identity/http/GovernanceTransport.php','modules/identity/Team/ReadService.php']
        report['source_sha256']={p:hashlib.sha256((ROOT/p).read_bytes()).hexdigest() for p in sources+[str(p.relative_to(ROOT)) for p in (ROOT/'modules/identity/StaffAdmission').glob('*.php')]+[str(p.relative_to(ROOT)) for p in (ROOT/'api/internal/staff-admission').glob('*.php')]+['modules/identity/db/migrations/2026_10_18_04_staff_admission_foundation.sql','modules/identity/tests/StaffAdmissionFixture.php','scripts/ase-adm02-foundation-qa.py','scripts/ase-adm02-foundation-checks.py','scripts/ase-adm02-router.php','modules/platform/services/CanonicalAuditPolicyRegistry.php','modules/platform/contracts/CanonicalAuditEventType.php','modules/platform/services/SourceModuleCatalog.php','modules/platform/services/CorrelatableOperationCatalog.php']}
        for pattern in ['modules/identity/StaffWorkflow/*.php','scripts/ase-adm02-workflow*','assets/js/staff-workflow.js','partials/identity/staff-workflow.php','modules/identity/db/migrations/2026_10_18_07_staff_workflow.sql','modules/identity/tests/StaffWorkflowFixture.php','modules/identity/services/StaffMfaService.php','modules/identity/MasterGovernance/Composition.php','assets/js/internal-team.js','partials/identity/internal-team.php']:
            for f in ROOT.glob(pattern):
                if f.is_file():report['source_sha256'][str(f.relative_to(ROOT))]=hashlib.sha256(f.read_bytes()).hexdigest()
        OUT.mkdir(parents=True,exist_ok=True)
        path=(pathlib.Path('/tmp')/('mxmed-workflow-disposal-'+str(os.getpid())+'.json')) if os.environ.get('MXMED_KEEP_OWNED_WORKFLOW')=='1' and error is None else OUT/('qa.json' if error is None else 'failed-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'.json')
        path.write_text(json.dumps(report,indent=2)+'\n');print('EVIDENCE',path,flush=True)
    if error:raise SystemExit(1)

if __name__=='__main__':main()
