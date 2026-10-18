"""IMP02 A–AL: real canonical services + independent SQL, all targets owned by the harness."""
import concurrent.futures,hashlib,json,pathlib,secrets,subprocess,time,uuid
import pymysql

def run(r,check,races):
    foundation_count=len(check.__globals__['checks'])
    check('AI all 101 foundation checks passed before invitation tests',foundation_count==101 and all(c['pass'] for c in check.__globals__['checks']))
    root=r['ROOT'];owned=r['owned'];sql=r['sql'];env=r['env'];db=r['database']
    for name in ['2026_07_19_02_create_auth_account_consents.sql','2026_07_20_05_create_auth_account_one_time_tokens.sql','2026_10_18_05_staff_admission_invitations.sql']:
        r['source_sql']((root/'modules/identity/db/migrations'/name).read_text())
    check('IMP02 migration installed only after verified owned datadir',pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==(owned/'mysql').resolve())
    password=secrets.token_urlsafe(32);(owned/'invitation-db-password').write_text(password)
    (owned/'recipient-key').write_bytes(secrets.token_bytes(32));(owned/'applicant-password').write_text(secrets.token_urlsafe(32)+'Aa1!')
    sql("CREATE USER 'ase_invitation'@'127.0.0.1' IDENTIFIED BY %s",(password,))
    for table in ['auth_accounts','auth_account_credentials','internal_staff','auth_staff_admission_cases','auth_staff_admission_versions']:
        sql(f"GRANT SELECT ON `{db}`.`{table}` TO 'ase_invitation'@'127.0.0.1'")
    sql(f"GRANT UPDATE(bound_account_id,binding_credential_version,binding_reference,updated_at) ON `{db}`.auth_staff_admission_cases TO 'ase_invitation'@'127.0.0.1'")
    for table in ['auth_staff_admission_invitations','auth_rate_limit_buckets']:
        sql(f"GRANT SELECT,INSERT,UPDATE ON `{db}`.`{table}` TO 'ase_invitation'@'127.0.0.1'")
    for table in ['auth_staff_admission_invitation_operations','platform_audit_events','platform_audit_stream_heads']:
        sql(f"GRANT SELECT,INSERT ON `{db}`.`{table}` TO 'ase_invitation'@'127.0.0.1'")
    sql(f"GRANT EXECUTE,LOCK TABLES ON `{db}`.* TO 'ase_invitation'@'127.0.0.1'")
    sql("GRANT SHOW_ROUTINE ON *.* TO 'ase_invitation'@'127.0.0.1'")
    baseline={t:sql('SELECT * FROM '+t+' ORDER BY 1') for t in ['auth_accounts','auth_account_credentials','internal_staff','internal_operator_grants','internal_capability_delegations']}
    def uid():return str(uuid.uuid4())
    def call(action,actor='director',input=None,**kw):
        p=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php',action],cwd=root,env=env,input=json.dumps({'actor':actor,'input':input or {},**kw}),capture_output=True,text=True)
        if p.returncode:(owned/'invitation-private-process.log').write_text(p.stderr);raise AssertionError('invitation CLI process failed')
        try:return json.loads(p.stdout)
        except ValueError:raise AssertionError('invitation CLI result invalid')
    def okay(action,**kw):
        v=call(action,**kw)
        if not v['ok']:raise AssertionError('invitation '+action+' '+v['error'])
        return v.get('data',{})
    def foundation(action,actor='director',**body):
        p=subprocess.run(r['fixture']+['command'],cwd=root,env=env,input=json.dumps({'actor':actor,'command':action,'input':body}),capture_output=True,text=True)
        v=json.loads(p.stdout)
        if not v['ok']:raise AssertionError('foundation '+action+' failed')
        return v['data']
    def profile():return dict(given_names='Persona Ficticia',first_surname='Ensayo',second_surname='',operation_state='',operation_city='')
    def prepare(channel='REMOTE_PREAUTHORIZED'):
        return foundation('prepare',operation_id=uid(),recipient_reference=uid(),channel=channel,proposed_category='ADVISOR',**profile())['case_id']
    def clear_limits():sql('DELETE FROM auth_rate_limit_buckets') # Disposable fixture reset between independent scenarios, never product behavior.
    def issue(email='qa_outsider@example.test',case=None,**kw):
        clear_limits();case=case or prepare();b=dict(operation_id=uid(),case_id=case,expected_version=1,recipient_email=email)
        d=okay('issue',input=b,**kw)
        path=owned/('invitation-'+d['invitation_id']+'.json')
        m=json.loads(path.read_text()) if path.exists() else None
        return b,d,m
    def acceptance(d,m):return dict(operation_id=uid(),case_id=d['case_id'],expected_version=d['version'],invitation_id=d['invitation_id'],token=m['token'])
    def bound(cid):return sql('SELECT bound_account_id,binding_reference FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0]
    def state(iid):return sql('SELECT state FROM auth_staff_admission_invitations WHERE invitation_id=%s',(iid,))[0][0]
    def denied(name,action='accept',**kw):
        v=call(action,**kw);check(name,v.get('error') in ['admission_denied','admission_authentication_required']);return v
    def sql_denied(name,statement,args=()):
        try:sql(statement,args);failed=False
        except pymysql.MySQLError:failed=True
        check(name,failed)
    # Production flags and router never choose a synthetic authority adapter.
    for action in ['issue','reissue','revoke','accept','result','csrf']:
        status,body,headers=r['request'](path='/api/internal/staff-admission/invitation-'+action+'.php',method='GET' if action in ['result','csrf'] else 'POST')
        check('AL invitation HTTP default disabled '+action,status==503 and headers.get('Cache-Control')=='private, no-store')
    check('AL enabling flag still requires unratified production policy',call('production_gate').get('error')=='staff_invitation_production_policy_unavailable')
    check('No invitation test entry over HTTP',r['request'](path='/modules/identity/tests/StaffInvitationFixture.php')[0]==404)
    # New applicant: invitation precedes account creation, canonical registration/verification/login.
    b,d,m=issue('new_applicant@example.test',case=prepare('ASSISTED'))
    check('A/B exact case, purpose, version and creator',sql('SELECT case_id,case_version,creator_account_id,purpose FROM auth_staff_admission_invitations WHERE invitation_id=%s',(d['invitation_id'],))[0]==(d['case_id'],1,'qa_director','STAFF_ADMISSION_ACCOUNT_BINDING'))
    check('C invitation creates no staff or account',all(baseline[t]==sql('SELECT * FROM '+t+' ORDER BY 1') for t in baseline))
    check('D delivery acknowledged only by inert owner-only sink',d['outcome']=='INVITATION_CREATED' and d['delivery_state']=='DELIVERY_CONFIRMED' and (owned/('invitation-'+d['invitation_id']+'.json')).stat().st_mode&0o777==0o600)
    check('Token entropy and digest-only persistence',len(m['token'])==43 and sql('SELECT token_digest FROM auth_staff_admission_invitations WHERE invitation_id=%s',(d['invitation_id'],))[0][0]==hashlib.sha256(m['token'].encode()).hexdigest())
    check('Email not plaintext or unsalted hash in invitation',m['recipient'] not in repr(sql('SELECT * FROM auth_staff_admission_invitations')) and hashlib.sha256(m['recipient'].encode()).hexdigest() not in repr(sql('SELECT * FROM auth_staff_admission_invitations')))
    new=okay('register',email='new_applicant@example.test')['account_id']
    check('E canonical registration pending with two personal consents',sql('SELECT status,email_verified_at FROM auth_accounts WHERE account_id=%s',(new,))[0]==('pending_verification',None) and sql('SELECT COUNT(*) FROM auth_account_consents WHERE account_id=%s',(new,))[0][0]==2)
    check('Registration alone leaves case unbound',bound(d['case_id'])==(None,None))
    check('L unverified canonical login denied',call('login_denied',email='new_applicant@example.test')['ok'])
    denied('K email/token without session denied',actor='',input=acceptance(d,m))
    okay('verify',email='new_applicant@example.test')
    check('F actual canonical verification consumed token and activated Identity only',sql('SELECT status,email_verified_at IS NOT NULL FROM auth_accounts WHERE account_id=%s',(new,))[0]==('active',1) and sql('SELECT COUNT(*) FROM auth_account_one_time_tokens WHERE account_id=%s AND consumed_at IS NOT NULL',(new,))[0][0]==1 and bound(d['case_id'])==(None,None))
    okay('login',actor='new',email='new_applicant@example.test')
    a=acceptance(d,m);accepted=okay('accept',actor='new',input=a)
    check('H atomic matching verified account binding and consumption',bound(d['case_id'])==(new,d['invitation_id']) and state(d['invitation_id'])=='CONSUMED' and accepted['outcome']=='ACCOUNT_CASE_BOUND')
    check('X exact duplicate acceptance returns canonical receipt',okay('accept',actor='new',input=a)==accepted)
    denied('Conflicting acceptance operation intention denied',actor='new',input={**a,'token':secrets.token_urlsafe(32)})
    check('Y ACK reconciliation uses actor-owned result',okay('result',actor='new',input={'operation_id':a['operation_id']})==accepted)
    denied('Result not visible to another account',action='result',actor='outsider',input={'operation_id':a['operation_id']})
    denied('W consumed token cannot bind another account',actor='outsider',input={**a,'operation_id':uid()})
    # New legitimate session now reaches existing case HTTP reader and correction/confirmation.
    r['tokens'].update(json.loads((owned/'sessions.json').read_text()))
    def http(action,actor='new',body=None,query='',csrf=''):
        return r['request'](actor,path='/api/internal/staff-admission/'+action+'.php'+query,method='GET' if action=='read' else 'POST',body=body,headers={'X-CSRF-Token':csrf})
    code,view,_=http('read',query='?case_id='+d['case_id']);check('Z bound applicant own canonical HTTP read',code==200 and view['confirmation_available'])
    csrf=view['csrf_token'];other=prepare()
    check('Z cannot read other case',http('read',query='?case_id='+other)[0]==403)
    body=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,**profile());body['operation_city']='Ciudad Ficticia'
    check('AA own correction through IMP01 HTTP',http('correct',body=body,csrf=csrf)[0]==200)
    check('AA own confirmation through IMP01 HTTP',http('confirm',body=dict(operation_id=uid(),case_id=d['case_id'],expected_version=2),csrf=csrf)[0]==200)
    check('Personal confirmation distinct from accreditation',sql('SELECT state,version FROM auth_staff_admission_cases WHERE case_id=%s',(d['case_id'],))[0]==('PERSON_CONFIRMED',3) and not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(new,)))
    for key in ['proposed_category','creator_account_id','recipient_email','bound_account_id','grant']:
        check('AB applicant cannot inject '+key,http('correct',body={**body,'expected_version':3,key:'forged'},csrf=csrf)[0]==400)
    check('AA cannot correct other case',http('correct',body={**body,'case_id':other},csrf=csrf)[0]==403)
    # Existing legitimate account uses canonical password login, unchanged credentials.
    okay('login',actor='outsider',email='qa_outsider@example.test',existing=True)
    b,d,m=issue(' QA_OUTSIDER@EXAMPLE.TEST ');a=acceptance(d,m)
    check('G existing canonical account authenticates and normalized recipient accepts',okay('accept',actor='outsider',input=a)['committed'] and bound(d['case_id'])[0]=='qa_outsider')
    # Wrong account and same declared display name have no matching authority.
    b,d,m=issue();a=acceptance(d,m)
    wrong=denied('I wrong account',actor='new',input=a)
    check('J case display name equal to applicant name is irrelevant',sql('SELECT given_names FROM auth_staff_admission_versions WHERE case_id=%s AND version=3',(accepted['case_id'],))[0][0]==sql('SELECT given_names FROM auth_staff_admission_versions WHERE case_id=%s AND version=1',(d['case_id'],))[0][0] and call('accept',actor='new',input={**a,'operation_id':uid()})==wrong)
    for actor in ['disabled','suspended','changed','expired','revoked']:
        denied('M invalid current account/session '+actor,actor=actor,input=a)
    for actor in ['disabled','suspended']:
        sb,sd,sm=issue('qa_'+actor+'@example.test')
        denied('M matching invalid current account/staff '+actor,actor=actor,input=acceptance(sd,sm))
    # Active existing staff, even matching email, must not gain onboarding authority.
    sb,sd,sm=issue('qa_target@example.test')
    denied('Existing ACTIVE staff requires separate review',actor='target',input=acceptance(sd,sm))
    check('Existing staff untouched and unbound',bound(sd['case_id'])==(None,None))
    # Expiration is durably terminal, even after clock rollback.
    b,d,m=issue();a=acceptance(d,m)
    denied('N expired token',actor='outsider',input=a,clock_offset=180)
    check('Expiration terminal persisted',state(d['invitation_id'])=='EXPIRED')
    denied('Expired token cannot revive after backwards clock',actor='outsider',input=a,clock_offset=-180)
    sql_denied('SQL terminal state cannot reactivate',"UPDATE auth_staff_admission_invitations SET state='ACTIVE' WHERE invitation_id=%s",(d['invitation_id'],))
    b,d,m=issue();a=acceptance(d,m)
    revoke=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,invitation_id=d['invitation_id'])
    okay('revoke',input=revoke);denied('O revoked token',actor='outsider',input=a)
    denied('Revoked token stays terminal under clock rollback',actor='outsider',input=a,clock_offset=-180)
    # Reissue and intentional recipient change cannot replay old token.
    b,d,m=issue();a=acceptance(d,m)
    re=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,invitation_id=d['invitation_id'],recipient_email='new_applicant@example.test')
    next_d=okay('reissue',input=re);next_m=json.loads((owned/('invitation-'+next_d['invitation_id']+'.json')).read_text())
    denied('P replaced token denied',actor='outsider',input=a)
    denied('R former recipient cannot use new token',actor='outsider',input=acceptance(next_d,next_m))
    check('R explicit Director intention changes recipient',okay('accept',actor='new',input=acceptance(next_d,next_m))['committed'] and state(d['invitation_id'])=='REPLACED')
    check('Reissue same operation does not deliver another invitation',okay('reissue',input=re)['invitation_id']==next_d['invitation_id'])
    # Case revocation and version drift.
    for command,label in [('revoke','S revoked case'),('update','T changed profile version')]:
        b,d,m=issue();a=acceptance(d,m)
        foundation(command,operation_id=uid(),case_id=d['case_id'],expected_version=1,**(profile() if command=='update' else {}))
        denied(label,actor='outsider',input=a);check(label+' leaves unbound',bound(d['case_id'])==(None,None))
    b,d,m=issue();a=acceptance(d,m);b2,d2,m2=issue()
    denied('U wrong case/token pair',actor='outsider',input={**a,'case_id':d2['case_id']})
    denied('V cross-case token substitution',actor='outsider',input={**acceptance(d2,m2),'token':m['token']})
    # Current authority, not management/class in payload.
    for actor in ['master','advisor','new']:
        denied('AD/AE no invitation management '+actor,action='issue',actor=actor,input={**b,'operation_id':uid()})
        denied('No unauthorized revoke '+actor,action='revoke',actor=actor,input=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,invitation_id=d['invitation_id']))
    saved=sql("SELECT status,updated_at FROM internal_staff WHERE account_id='qa_director'")[0]
    sql("UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_director'")
    denied('AC suspended Director cannot issue',action='issue',input={**b,'operation_id':uid()})
    sql("UPDATE internal_staff SET status=%s,updated_at=%s WHERE account_id='qa_director'",saved)
    # Notification failure leaves creation committed/pending; replay does not deliver or replace.
    b,d,m=issue(delivery_failure=True)
    check('Failed transport retains pending creation',m is None and d['delivery_state']=='DELIVERY_PENDING')
    retry=okay('issue',input=b)
    check('Lost delivery ACK replay never resends',retry==d and not (owned/('invitation-'+d['invitation_id']+'.json')).exists())
    check('Pending result reconciles canonical creation',okay('result',input={'operation_id':b['operation_id']})==d)
    re=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,invitation_id=d['invitation_id'],recipient_email='qa_outsider@example.test')
    recovered=okay('reissue',input=re)
    check('Explicit reissue recovers failed delivery and invalidates old',recovered['delivery_state']=='DELIVERY_CONFIRMED' and state(d['invitation_id'])=='REPLACED')
    # Audit insert forced failure must revert BOTH consumption and binding, not the attempt counter.
    b,d,m=issue();a=acceptance(d,m)
    sql("CREATE TRIGGER synthetic_invitation_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_audit_failure'")
    failed=call('accept',actor='outsider',input=a)
    sql('DROP TRIGGER synthetic_invitation_audit_failure')
    check('AG audit failure rolls back consumption binding and receipt',not failed['ok'] and state(d['invitation_id'])=='ACTIVE' and bound(d['case_id'])==(None,None) and sql('SELECT COUNT(*) FROM auth_staff_admission_invitation_operations WHERE operation_id=%s',(a['operation_id'],))[0][0]==0)
    check('AG successful retry only after authoritative absence confirmed',okay('accept',actor='outsider',input=a)['committed'])
    # Q independent process/SQL connection race on one actor/case/token: one effective acceptance.
    b,d,m=issue();a=acceptance(d,m);a2={**a,'operation_id':uid()}
    denied('Race warmup creates canonical attempt bucket',actor='outsider',input={**a,'token':secrets.token_urlsafe(32)})
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=%s FOR UPDATE',(d['case_id'],))
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures=[pool.submit(call,'accept',actor='outsider',input=x) for x in [a,a2]]
        end=time.monotonic()+10;observed=False
        while time.monotonic()<end:
            if sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]>=2:observed=True;break
            time.sleep(.05)
        blocker.commit();blocker.close();out=[f.result() for f in futures]
    (owned/'race-diagnostic.json').write_text(json.dumps({'observed':observed,'results':out,'state':state(d['invitation_id'])}))
    check('Q independent connections wait then consume exactly once',observed and sum(o['ok'] for o in out)==1 and state(d['invitation_id'])=='CONSUMED' and sql("SELECT COUNT(*) FROM auth_staff_admission_invitation_operations WHERE invitation_id=%s AND command='accept'",(d['invitation_id'],))[0][0]==1)
    races.append({'name':'invitation_acceptance_single_consumption','independent_connections':True,'lock_wait_observed':observed,'one_commit':sum(o['ok'] for o in out)==1})
    b,d,m=issue();a=acceptance(d,m)
    denied('Duplicate race warmup creates canonical attempt bucket',actor='outsider',input={**a,'token':secrets.token_urlsafe(32)})
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:out=list(pool.map(lambda _:call('accept',actor='outsider',input=a),range(2)))
    check('Concurrent same operation has one identical receipt',all(o['ok'] for o in out) and out[0]==out[1])
    races.append({'name':'invitation_duplicate_acceptance','independent_connections':True,'same_canonical_result':out[0]==out[1]})
    # Acceptance vs explicit replacement, two different actors and SQL connections.
    b,d,m=issue();a=acceptance(d,m)
    re=dict(operation_id=uid(),case_id=d['case_id'],expected_version=1,invitation_id=d['invitation_id'],recipient_email='new_applicant@example.test')
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=%s FOR UPDATE',(d['case_id'],))
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures=[pool.submit(call,'accept',actor='outsider',input=a),pool.submit(call,'reissue',input=re)]
        end=time.monotonic()+10;observed=False
        while time.monotonic()<end:
            if sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]>=2:observed=True;break
            time.sleep(.05)
        blocker.commit();blocker.close();out=[f.result() for f in futures]
    check('Replacement/acceptance serialized with one winning intention',observed and sum(o['ok'] for o in out)==1 and state(d['invitation_id']) in ['CONSUMED','REPLACED'])
    races.append({'name':'acceptance_vs_reissue','independent_connections':True,'lock_wait_observed':observed,'one_commit':sum(o['ok'] for o in out)==1})
    # Canonical Valkey revocation after initial session resolution, before SQL commit.
    b,d,m=issue();a=acceptance(d,m)
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=%s FOR UPDATE',(d['case_id'],))
    with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
        future=pool.submit(call,'accept',actor='outsider',input=a)
        end=time.monotonic()+10;observed=False
        while time.monotonic()<end:
            if sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]>=1:observed=True;break
            time.sleep(.05)
        okay('logout',actor='outsider');blocker.commit();blocker.close();out=future.result()
    check('Final session revalidation rolls back acceptance after Valkey logout',observed and not out['ok'] and state(d['invitation_id'])=='ACTIVE' and bound(d['case_id'])==(None,None))
    races.append({'name':'session_revocation_before_commit','independent_connections':True,'lock_wait_observed':observed,'binding_rolled_back':not out['ok']})
    okay('login',actor='outsider',email='qa_outsider@example.test',existing=True)
    # Generic nonenumeration and real canonical rate control (no reset within this scenario).
    b,d,m=issue();a=acceptance(d,m)
    errors=[]
    for n in range(6):errors.append(call('accept',actor='outsider',input={**a,'operation_id':uid(),'invitation_id':'sti_'+secrets.token_hex(16)}))
    errors.append(call('accept',actor='outsider',input=a))
    check('AH unknown and rate-blocked valid invitation have identical generic errors',all(e=={'ok':False,'error':'admission_denied'} for e in errors) and state(d['invitation_id'])=='ACTIVE')
    check('AH canonical attempt bucket persisted',sql("SELECT MAX(attempts_count),MAX(blocked_until IS NOT NULL) FROM auth_rate_limit_buckets WHERE operation_code='token_consume'")[0]==(6,1))
    # SQL intent immutable, no raw token/email in rows/audit/receipts, restricted principal cannot create authority.
    sql_denied('SQL recipient intent immutable',"UPDATE auth_staff_admission_invitations SET recipient_digest=REPEAT('a',64) WHERE invitation_id=%s",(d['invitation_id'],))
    sql_denied('SQL expiry cannot be extended',"UPDATE auth_staff_admission_invitations SET expires_at=DATE_ADD(expires_at,INTERVAL 1 DAY) WHERE invitation_id=%s",(d['invitation_id'],))
    sql_denied('SQL receipt immutable',"UPDATE auth_staff_admission_invitation_operations SET command='accept' LIMIT 1")
    p=pymysql.connect(host='127.0.0.1',port=r['mysql_port'],user='ase_invitation',password=password,database=db,autocommit=True)
    try:
        for table in ['auth_accounts','internal_staff','internal_operator_grants']:
            try:
                with p.cursor() as cur:cur.execute('DELETE FROM '+table+' WHERE 1=0')
                blocked=False
            except pymysql.MySQLError:blocked=True
            check('Invitation principal cannot write '+table,blocked)
    finally:p.close()
    invitation_dump=repr(sql('SELECT * FROM auth_staff_admission_invitations'))+repr(sql('SELECT * FROM auth_staff_admission_invitation_operations'))+repr(sql('SELECT * FROM platform_audit_events'))
    sinks=list(owned.glob('invitation-sti_*.json'))
    check('No plaintext recipient or raw invitation in SQL/audit',all(json.loads(p.read_text())['token'] not in invitation_dump and json.loads(p.read_text())['recipient'] not in invitation_dump for p in sinks))
    for t in ['internal_staff','internal_operator_grants','internal_capability_delegations']:
        check('AK no staff/grant/delegation changes '+t,baseline[t]==sql('SELECT * FROM '+t+' ORDER BY 1'))
    check('AF existing accounts and credentials untouched',all(row in sql('SELECT * FROM auth_accounts ORDER BY 1') for row in baseline['auth_accounts']) and all(row in sql('SELECT * FROM auth_account_credentials ORDER BY 1') for row in baseline['auth_account_credentials']))
    check('Only one explicitly synthetic canonical account created',len(sql('SELECT * FROM auth_accounts'))==len(baseline['auth_accounts'])+1)
    check('AJ Master fail-closed regression was executed',any(c['name']=='V/Z unavailable staff review still fails closed' and c['pass'] for c in check.__globals__['checks']))
    # Erase token-bearing private files before the owner disposes the fresh MySQL datadir.
    for path in [*sinks,owned/'canonical-registration-sink.json',owned/'recipient-key',owned/'applicant-password',owned/'invitation-db-password']:
        if path.exists():
            with path.open('r+b') as f:f.write(b'\x00'*path.stat().st_size);f.flush()
            path.unlink()
    check('Synthetic token/key files removed after verification',not list(owned.glob('invitation-sti_*.json')) and not (owned/'canonical-registration-sink.json').exists())
    print('Staff invitation A–AL checks complete',flush=True)
