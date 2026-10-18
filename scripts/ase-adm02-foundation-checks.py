"""A–Z: HTTP and real SQL assertions against the runner-owned fresh instance only."""
import json,subprocess,uuid,concurrent.futures,time,pathlib,hashlib
import pymysql

def run(r,check,races):
    sql=r['sql'];request=r['request'];env=r['env'];root=r['ROOT'] if 'ROOT' in r else pathlib.Path(__file__).resolve().parents[1]
    owned=r['owned'];db=r['database'];fixture=r['fixture'];csrf={}
    def uid():return str(uuid.uuid4())
    def call(action,**b):
        p=subprocess.run(fixture+[action],cwd=root,env=env,input=json.dumps(b),capture_output=True,text=True)
        if p.returncode:(owned/'fixture-error.log').write_text(p.stderr);raise AssertionError('CLI fixture failed')
        return json.loads(p.stdout)
    def http(action,actor='director',body=None,query='',headers=None,method=None):
        return request(actor,path='/api/internal/staff-admission/'+action+'.php'+query,method=method or ('GET' if action in ['csrf','read','result'] else 'POST'),body=body,headers={'X-CSRF-Token':csrf.get(actor,''),**(headers or {})})
    def profile(**kw):return dict(given_names='Persona Ficticia',first_surname='Ensayo',second_surname='',operation_state='',operation_city='',**kw)
    def prepare(**kw):return {'operation_id':uid(),'recipient_reference':uid(),'channel':'ASSISTED','proposed_category':'ADVISOR',**profile(),**kw}
    def mutation(case,v,**kw):return {'operation_id':uid(),'case_id':case,'expected_version':v,**kw}
    def success(action,**kw):
        status,d,h=http(action,**kw)
        if status!=200:
            (owned/'http-failure.json').write_text(json.dumps({'action':action,'status':status,'body':d}));raise AssertionError('HTTP '+action+' status '+str(status))
        check('Private no-store '+action,h.get('Cache-Control')=='private, no-store' and 'Access-Control-Allow-Origin' not in h)
        return d
    baseline={t:sql('SELECT * FROM '+t+' ORDER BY 1') for t in ['auth_accounts','auth_account_credentials','internal_staff','internal_operator_grants','internal_capability_delegations']}
    d=success('csrf');csrf['director']=d['csrf_token']
    for actor in ['master','advisor','target','outsider']:
        csrf[actor]=call('csrf',actor=actor)['data']['csrf_token']
    b=prepare();c=success('prepare',body=b);cid=c['case_id']
    check('A Director prepares exact case',c['version']==1 and c['command']=='prepare')
    view=success('read',query='?case_id='+cid)
    check('E Director provenance, no personal confirmation',view['profile']['source_actor_account_id']=='qa_director' and view['profile']['provenance']=='ADMIN_PREPARED' and view['state']=='PREPARING' and not view['account_bound'])
    check('Unbound case cannot be confirmed by Director',http('confirm',body=mutation(cid,1))[0]==403)
    for actor in ['master','advisor','outsider']:
        check('H/I no preparation authority '+actor,http('prepare',actor=actor,body=prepare())[0]==403)
        check('Exact case read denied '+actor,http('read',actor=actor,query='?case_id='+cid)[0]==403)
    for actor in [None,'expired','revoked','changed','disabled']:
        check('J/K/L invalid canonical session '+str(actor),http('read',actor=actor,query='?case_id='+cid)[0]==401)
    check('M CSRF required',http('prepare',body=prepare(),headers={'X-CSRF-Token':''})[0]==403)
    check('M cross origin denied',http('prepare',body=prepare(),headers={'Origin':'https://example.invalid'})[0]==403)
    check('M foreign Fetch-Site denied',http('prepare',body=prepare(),headers={'Sec-Fetch-Site':'cross-site'})[0]==403)
    check('HTTP method denied',http('prepare',method='GET')[0]==405)
    check('HTTP unknown query denied',http('prepare',body=prepare(),query='?actor=qa_director')[0]==400)
    check('HTTP duplicate keys denied',http('prepare',body=json.dumps(prepare())[:-1]+',"channel":"ASSISTED"}')[0]==400)
    check('HTTP oversized denied',http('prepare',body=' '*2049)[0]==413)
    check('HTTP non JSON denied',http('prepare',body=prepare(),headers={'Content-Type':'text/plain'})[0]==415)
    for field,value in [('actor','qa_director'),('bound_account_id','qa_target'),('binding_reference','fixture'),('identity_verified',True),('photo_reference','fake'),('mobile','123')]:
        check('N/X forbidden field '+field,http('prepare',body=prepare(**{field:value}))[0]==400)
    check('N invalid proposed role',http('prepare',body=prepare(proposed_category='DIRECTOR'))[0]==400)
    check('N category immutable',http('update',body=mutation(cid,1,**profile(),proposed_category='MASTER_ADMIN'))[0]==400)
    check('S same operation same outcome',success('prepare',body=b)==c)
    check('S operation cannot change payload',http('prepare',body={**b,'given_names':'Cambio Ficticio'})[0]==409)
    check('S another operation same recipient conflicts',http('prepare',body={**b,'operation_id':uid()})[0]==409)
    check('T lost ACK read exact persisted result',success('result',query='?operation_id='+b['operation_id'])==c)
    check('T other actor cannot read operation',http('result',actor='target',query='?operation_id='+b['operation_id'])[0]==403)
    remote=success('prepare',body=prepare(channel='REMOTE_PREAUTHORIZED',proposed_category='MASTER_ADMIN'))
    check('Remote channel is same case and no binding',success('read',query='?case_id='+remote['case_id'])['account_bound'] is False)
    check('F CLI exact synthetic account binding',call('bind',case_id=cid,account='qa_target')['ok'])
    own=success('read',actor='target',query='?case_id='+cid);csrf['target']=own['csrf_token']
    check('Canonical account email only after binding',own['account_email']['email_address']=='qa_target@example.test')
    check('G wrong applicant cannot confirm',http('confirm',actor='outsider',body=mutation(cid,1))[0]==403)
    confirmed=success('confirm',actor='target',body=mutation(cid,1))
    check('F exact linked applicant confirmation',confirmed['version']==2)
    v=success('read',actor='target',query='?case_id='+cid)
    check('O personal confirmation distinct from identity approval',v['state']=='PERSON_CONFIRMED' and v['profile']['provenance']=='APPLICANT_CONFIRMED' and not any(k in json.dumps(v) for k in ['reviewVersion','identity_verified','"approved"']))
    old=sql('SELECT * FROM auth_staff_admission_versions WHERE case_id=%s AND version=2',(cid,))
    edit={**profile(),'given_names':'Correccion Ficticia'}
    changed=success('correct',actor='target',body=mutation(cid,2,**edit))
    v=success('read',actor='target',query='?case_id='+cid)
    check('P material change invalidates confirmation preserves prior bytes',v['state']=='PREPARING' and v['profile']['provenance']=='APPLICANT_CORRECTED' and old==sql('SELECT * FROM auth_staff_admission_versions WHERE case_id=%s AND version=2',(cid,)))
    check('Q stale expected version',http('update',body=mutation(cid,2,**profile()))[0]==409)
    check('Cross case operation substitution',http('update',body={'operation_id':b['operation_id'],'case_id':remote['case_id'],'expected_version':1,**profile()})[0]==409)
    # Independent PHP processes, separate SQL connections and canonical private sessions.
    jobs=[{'actor':'director','command':'update','input':mutation(cid,3,**profile())},{'actor':'target','command':'correct','input':mutation(cid,3,**edit)}]
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=%s FOR UPDATE',(cid,))
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures=[pool.submit(call,'command',**j) for j in jobs]
        until=time.monotonic()+10;observed=False
        while time.monotonic()<until:
            waits=sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]
            if waits>=2:observed=True;break
            time.sleep(.05)
        blocker.commit();blocker.close();out=[f.result() for f in futures]
    check('R two independent writers actually wait on SQL case lock',observed)
    check('R exactly one write wins expected_version',sum(o['ok'] for o in out)==1 and any(o.get('error')=='admission_conflict' for o in out))
    races.append({'name':'admin_applicant_same_version','independent_connections':True,'lock_wait_observed':observed,'one_commit':sum(o['ok'] for o in out)==1})
    # Two same-actor creates serialize on actor account and recover the one result.
    duplicate=prepare()
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        out=list(pool.map(lambda _:call('command',actor='director',command='prepare',input=duplicate),range(2)))
    check('S concurrent duplicate has one canonical result',all(o['ok'] for o in out) and out[0]['data']==out[1]['data'])
    races.append({'name':'duplicate_operation','independent_connections':True,'same_canonical_result':True})
    director_updated=sql("SELECT updated_at FROM internal_staff WHERE account_id='qa_director'")[0][0]
    # Revocation while a command is waiting must be observed under the actor locks.
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_director'")
    with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
        future=pool.submit(call,'command',actor='director',command='update',input=mutation(cid,4,**profile()))
        until=time.monotonic()+10;observed=False
        while time.monotonic()<until:
            if sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]>0:observed=True;break
            time.sleep(.05)
        blocker.commit();blocker.close();denied=future.result()
    sql("UPDATE internal_staff SET status='ACTIVE',updated_at=%s WHERE account_id='qa_director'",(director_updated,))
    check('Authority revoked during lock wait denies write',observed and denied.get('error')=='admission_denied')
    races.append({'name':'director_suspension_while_waiting','lock_wait_observed':observed,'denied':not denied['ok']})
    # Native canonical audit failure must roll back all three objects and stream heads.
    before={t:sql('SELECT * FROM '+t+' ORDER BY 1,2') for t in ['auth_staff_admission_cases','auth_staff_admission_versions','auth_staff_admission_operations','platform_audit_events','platform_audit_stream_heads']}
    sql("CREATE TRIGGER admission_qa_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'")
    try:
        failed=call('command',actor='director',command='prepare',input=prepare())
        failed_update=call('command',actor='director',command='update',input=mutation(cid,4,**profile()))
    finally:sql('DROP TRIGGER admission_qa_audit_failure')
    check('U audit failure rolls back create and update',not failed['ok'] and not failed_update['ok'] and all(rows==sql('SELECT * FROM '+t+' ORDER BY 1,2') for t,rows in before.items()))
    rev=success('revoke',body=mutation(cid,4));check('Revocation persists',rev['version']==5)
    check('Revoked intent cannot be reused',http('correct',actor='target',body=mutation(cid,5,**profile()))[0]==409)
    check('Revoked case old prepare replay denied',http('prepare',body=b)[0]==409)
    check('Revoked outcome remains reconcilable',success('result',query='?operation_id='+rev['operation_id'])==rev)
    # SQL invariants, not application-only validation.
    def sql_denied(name,statement,args=()):
        try:sql(statement,args);ok=False
        except pymysql.MySQLError:ok=True
        check(name,ok)
    sql_denied('Immutable historical version SQL update','UPDATE auth_staff_admission_versions SET given_names=%s WHERE case_id=%s AND version=1',('Forgery',cid))
    sql_denied('Immutable historical version SQL delete','DELETE FROM auth_staff_admission_versions WHERE case_id=%s AND version=1',(cid,))
    sql_denied('FK rejects cross-case current version','UPDATE auth_staff_admission_cases SET version=999,current_profile_version=999 WHERE case_id=%s',(remote['case_id'],))
    sql_denied('CHECK rejects approval state',"UPDATE auth_staff_admission_cases SET state='APPROVED' WHERE case_id=%s",(remote['case_id'],))
    check('Schema FK and CHECK active',sql('SELECT @@SESSION.foreign_key_checks')[0][0]==1 and not sql("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND CONSTRAINT_TYPE='CHECK' AND ENFORCED<>'YES'",(db,)))
    for letter,t in [('C','auth_accounts'),('C','auth_account_credentials'),('B/W','internal_staff'),('D','internal_operator_grants'),('D','internal_capability_delegations')]:
        check(letter+' canonical table unchanged '+t,baseline[t]==sql('SELECT * FROM '+t+' ORDER BY 1'))
    events=sql("SELECT real_actor_reference,resource_reference,metadata_json FROM platform_audit_events WHERE action='STAFF_ADMISSION_CASE_CHANGED'")
    check('Audit exact case actor and no personal payload',len(events)>5 and all(e[1].startswith('adm_') and e[0].startswith('account:qa_') and set(json.loads(e[2])['producer_metadata'])=={'operation','case_id','version','provenance'} for e in events))
    reads=sql("SELECT metadata_json FROM platform_audit_events WHERE action='STAFF_ADMISSION_CASE_READ'")
    check('Private reads audited without personal values',len(reads)>0 and all(set(json.loads(row[0])['producer_metadata'])=={'operation','case_id','version','provenance'} for row in reads))
    check('V/Z unavailable staff review still fails closed',call('master_denied').get('error')=='staff_identity_review_source_unavailable')
    for f in ['modules/identity/MasterGovernance/Composition.php','modules/identity/contracts/StaffIdentityReviewPort.php','modules/identity/contracts/StaffIdentityReviewSnapshot.php','modules/identity/adapters/UnavailableStaffIdentityReview.php','modules/identity/services/InternalGovernanceService.php','modules/identity/services/InternalCapabilityCatalog.php','modules/identity/Operational/DeploymentPackage.php','assets/js/internal-team.js','assets/js/internal-team-master.js','partials/identity/internal-team.php']:
        check('Protected source unchanged '+f,subprocess.check_output(['git','show',r['BASE']+':'+f],cwd=root)==(root/f).read_bytes())
    check('X no file endpoints',request(path='/api/internal/staff-admission/upload.php',method='POST')[0]==404)
    check('No notification delivery',not (owned/'inert-notifications.json').exists())
    check('Y unrelated application gates off',env['MXMED_TEAM_ADVISOR_MUTATIONS_ENABLED']=='0' and env['MXMED_MASTER_GOVERNANCE_ENABLED']=='0' and env['MXMED_NORMAL_LOCAL_IDENTITY_EXPLICIT']=='0')
    # Separate default-off process, never the original runtime.
    off_env={**env,'MXMED_STAFF_ADMISSION_FOUNDATION_ENABLED':'0'}
    p=subprocess.run(['php','-r',"$_SERVER['REQUEST_METHOD']='POST';require 'api/internal/staff-admission/prepare.php';fwrite(STDERR,(string)http_response_code());"],cwd=root,env=off_env,capture_output=True,text=True)
    check('Y new feature default-off returns unavailable',p.returncode==0 and p.stderr=='503' and 'service_unavailable' in p.stdout)
    writer=pymysql.connect(host='127.0.0.1',port=r['mysql_port'],user='ase_writer',password=r['password'],database=db,autocommit=True)
    try:
        try:
            with writer.cursor() as cur:cur.execute("UPDATE auth_staff_admission_cases SET bound_account_id='qa_outsider',binding_credential_version=1,binding_reference='forged-binding' WHERE case_id=%s",(remote['case_id'],))
            blocked=False
        except pymysql.MySQLError as e:blocked=e.args[0] in [1142,1143]
        check('HTTP SQL principal cannot establish account binding',blocked)
    finally:writer.close()
    sql_denied('CHECK refuses incomplete binding',"UPDATE auth_staff_admission_cases SET bound_account_id='qa_outsider',binding_reference='bad-fixture' WHERE case_id=%s",(remote['case_id'],))
    sql_denied('Immutable operation result SQL update',"UPDATE auth_staff_admission_operations SET command='update' WHERE actor_account_id='qa_director' AND operation_id=%s",(b['operation_id'],))
    import http.client
    direct=http.client.HTTPConnection('127.0.0.1',r['php_port'])
    try:
        direct.request('GET','/api/internal/staff-admission/csrf.php');resp=direct.getresponse();resp.read();check('Unsigned direct HTTP transport denied',resp.status==403)
    finally:direct.close()
    check('No fixture binding accessible over HTTP',request(path='/modules/identity/tests/StaffAdmissionFixture.php',method='POST')[0]==404)
    # Catalog extension keeps all historical policies intact and new metadata closed.
    p=subprocess.run(['php','modules/platform/tests/PhysicianCredentialAuditPolicyTest.php'],cwd=root,env=env,capture_output=True,text=True)
    check('Canonical audit policy regression',p.returncode==0)
    print('Staff foundation checks complete',flush=True)
