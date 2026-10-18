"""Additional targeted native races and the real gated HTTP composition; no standalone entry."""
import concurrent.futures,json,pathlib,subprocess,time,hashlib,hmac,struct
import pymysql,http.client

def run(c):
    r=c['r'];qa=c['qa'];ROOT=c['ROOT'];OPS=c['OPS'];sql=c['sql'];call=c['call'];success=c['success'];deny=c['deny'];check=c['check'];now=c['now'];owned=c['owned'];env=c['env'];database=c['database'];open_case=c['open_case'];authorize=c['authorize'];execute=c['execute'];audit=c['audit']
    # The product adapter also rejects consuming an otherwise valid synthetic appointment case.
    case=open_case('appoint');args,ch=authorize(case);deny('Production eligibility cannot consume synthetic approved review','execute',product_eligibility=True,**args);success('cancel',case_id=case['case_id'])
    # Direct MFA entry points must deny changed Director class at all three stages.
    case=open_case('appoint')
    for role in ['MASTER_ADMIN','ADVISOR']:
        sql("UPDATE internal_staff SET governance_class=%s WHERE account_id='qa_director'",(role,));deny('MFA issuance director only '+role,'direct_issue',case_id=case['case_id']);sql("UPDATE internal_staff SET governance_class='DIRECTOR' WHERE account_id='qa_director'")
    now[0]+=30;ch=success('confirm',case_id=case['case_id'],intent_digest=case['intent_digest'])
    for role in ['MASTER_ADMIN','ADVISOR']:
        sql("UPDATE internal_staff SET governance_class=%s WHERE account_id='qa_director'",(role,));deny('MFA verification director only '+role,'direct_verify',**ch);sql("UPDATE internal_staff SET governance_class='DIRECTOR' WHERE account_id='qa_director'")
    proof=success('verify',case_id=case['case_id'],**ch);args={'case_id':case['case_id'],'proof_id':proof['proof_id'],'proof':proof['proof']}
    for role in ['MASTER_ADMIN','ADVISOR']:
        sql("UPDATE internal_staff SET governance_class=%s WHERE account_id='qa_director'",(role,));deny('MFA consumption director only '+role,'direct_consume',**args);sql("UPDATE internal_staff SET governance_class='DIRECTOR' WHERE account_id='qa_director'")
    success('cancel',case_id=case['case_id'])
    # Factor absence, challenge expiry, persisted attempt throttling.
    case=open_case('appoint');sql("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_director'")
    deny('No implicit MFA enrollment','confirm',case_id=case['case_id'],intent_digest=case['intent_digest']);sql("UPDATE auth_staff_mfa_factors SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_director'");success('cancel',case_id=case['case_id'])
    case=open_case('appoint');now[0]+=30;ch=success('confirm',case_id=case['case_id'],intent_digest=case['intent_digest']);now[0]+=301
    deny('Expired challenge rejected','verify',case_id=case['case_id'],**ch);success('expire',case_id=case['case_id'])
    case=open_case('appoint');now[0]+=30;ch=success('confirm',case_id=case['case_id'],intent_digest=case['intent_digest'])
    for i in range(5):deny('TOTP invalid attempt '+str(i+1),'verify',case_id=case['case_id'],**ch,code='bad')
    check('MFA attempts persisted',sql('SELECT attempts_count FROM auth_staff_mfa_challenges WHERE challenge_id=%s',(ch['challenge_id'],))[0][0]==5)
    deny('Factor lock denies valid TOTP','verify',case_id=case['case_id'],**ch);success('cancel',case_id=case['case_id']);now[0]+=901
    # Proof for capability A cannot authorize capability B, even if a case digest were recomputed server-side.
    execute('appoint');case=open_case('delegate',cap='media_review_read');args,ch=authorize(case)
    context=json.loads(sql('SELECT security_context FROM auth_governance_cases WHERE case_id=%s',(case['case_id'],))[0][0]);context['intent']['capability']='media_review_approve';context['digest']=hashlib.sha256(json.dumps(context['intent'],sort_keys=True,separators=(',',':')).encode()).hexdigest()
    sql('UPDATE auth_governance_cases SET security_context=%s WHERE case_id=%s',(json.dumps(context),case['case_id']))
    deny('Exact capability challenge digest binding','execute',**args);success('cancel',case_id=case['case_id'])
    # Relevant assignment state changes on an independent transaction invalidate confirmation.
    for name,op,cap,change in [
        ('management granted concurrently','manage',None,"INSERT INTO internal_operator_grants(grant_id,account_id,capability,status) VALUES(UUID(),'qa_target','internal_advisors_manage','ACTIVE')"),
        ('delegation granted concurrently','delegate','media_review_read',"INSERT INTO internal_capability_delegations(delegation_id,master_account_id,capability,authorized_by_account_id,status) VALUES(UUID(),'qa_target','media_review_read','qa_director','ACTIVE')"),
    ]:
        case=open_case(op,cap=cap);args,ch=authorize(case);n=len(audit());blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=r['fixture_password'],database=database,autocommit=False)
        try:
            with blocker.cursor() as q:q.execute("SELECT account_id FROM auth_accounts WHERE account_id IN ('qa_director','qa_target') ORDER BY account_id FOR UPDATE");q.fetchall()
            with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
                future=pool.submit(call,'execute',**args);qa.wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10);waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,BLOCKING_ENGINE_TRANSACTION_ID FROM performance_schema.data_lock_waits')
                with blocker.cursor() as q:q.execute(change)
                blocker.commit();result=future.result(20)
            check(name+' denies stale proof',not result['ok'] and len(audit())==n);qa.concurrency.append({'name':name,'observed_lock_waits':waits,'independent_connections':True,'denied':True})
        finally:blocker.rollback();blocker.close()
        success('cancel',case_id=case['case_id'])
    execute('remove')
    # Two writer processes compete on the same proof under an observed lock wait.
    case=open_case('appoint');args,ch=authorize(case);n=len(audit());blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=r['fixture_password'],database=database,autocommit=False)
    try:
        with blocker.cursor() as q:q.execute("SELECT account_id FROM auth_accounts WHERE account_id='qa_director' FOR UPDATE");q.fetchall()
        with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
            jobs=[pool.submit(call,'execute',**args) for _ in range(2)];qa.wait_until(lambda:len(sql('SELECT DISTINCT REQUESTING_ENGINE_TRANSACTION_ID FROM performance_schema.data_lock_waits'))>=2,15);waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,BLOCKING_ENGINE_TRANSACTION_ID FROM performance_schema.data_lock_waits');blocker.commit();results=[f.result(20) for f in jobs]
        check('Concurrent duplicate has exactly one commit',sum(x['ok'] for x in results)==1 and len(audit())==n+1);qa.concurrency.append({'name':'duplicate proof execution','observed_lock_waits':waits,'independent_connections':True,'effective_mutations':1})
    finally:blocker.rollback();blocker.close()
    execute('remove')
    # Canonical logout finishes while writer waits, before the final authority point.
    case=open_case('appoint');args,ch=authorize(case);n=len(audit());blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=r['fixture_password'],database=database,autocommit=False)
    try:
        with blocker.cursor() as q:q.execute("SELECT account_id FROM auth_accounts WHERE account_id='qa_target' FOR UPDATE");q.fetchall()
        with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
            future=pool.submit(call,'execute',**args);qa.wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10);waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,BLOCKING_ENGINE_TRANSACTION_ID FROM performance_schema.data_lock_waits');success('logout');blocker.commit();result=future.result(20)
        check('Logout before final authority check denies',not result['ok'] and len(audit())==n);qa.concurrency.append({'name':'canonical Valkey logout before final check','observed_lock_waits':waits,'independent_connections':True,'denied':True,'sql_valkey_atomicity_claimed':False})
    finally:blocker.rollback();blocker.close()
    p=subprocess.run(r['fixture']+['refresh_sessions'],cwd=ROOT,env=env,text=True,capture_output=True,check=True);r['tokens'].update(json.loads(p.stdout));(owned/'sessions.json').write_text(json.dumps(r['tokens']));success('cancel',case_id=case['case_id'])
    # Historical MFA primitives remain usable with their original exact bindings.
    # This is not a claim of executing full physician ownership or clinical workflows.
    for op in sorted(c['historical']):
        now[0]+=30
        check('Historical MFA primitive '+op,success('historical_mfa',operation=op)['consumed'])
    # HTTP uses the normal composition (unavailable review adapter), never the test adapter.
    execute('appoint')
    sql("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_director' AND status='ACTIVE'")
    now[0]=int(time.time());success('enroll')
    auth=success('deployment');env['MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION']=str(owned/'master-deployment.json');env['MXMED_GOVERNANCE_DEPLOYMENT_AUTHORIZATION_SHA256']=auth['pin']
    php=next(p for p in qa.processes if p.poll() is None and p.args[0]=='php' and '-S' in p.args);php.terminate();php.wait(timeout=10)
    r['spawn']('master-http',['php','-S','127.0.0.1:'+str(r['php_port']),'-t',str(ROOT),str(ROOT/qa.ROUTER)],env);qa.wait_until(lambda:r['port_ready'](r['php_port']))
    request=r['request'];prefix='/api/internal/team/master/'
    status,body,headers=request(path=prefix+'csrf.php',method='GET');check('Director master HTTP CSRF bootstrap',status==200)
    csrf=body['csrf_token'];check('Master HTTP no-store no CORS',headers.get('Cache-Control')=='private, no-store' and 'Access-Control-Allow-Origin' not in headers)
    def post(action,body,actor='director',headers=None):return request(actor,path=prefix+action+'.php',body=body,headers={'X-CSRF-Token':csrf,**(headers or {})})
    body={'operation':OPS['manage'],'target':'qa_target','capability':'internal_advisors_manage','reason':'Synthetic Director review'}
    for actor,status in [(None,401),('master',403),('advisor',403),('disabled',401),('revoked',401)]:check('HTTP authority '+str(actor),post('open',body,actor)[0]==status)
    for h in [{'Origin':None},{'Origin':'https://evil.example'},{'Sec-Fetch-Site':'cross-site'},{'X-CSRF-Token':'invalid'}]:check('HTTP transport CSRF '+str(h.keys()),post('open',body,headers=h)[0]==403)
    for b in [{**body,'actor':'qa_director'},{**body,'verified':True},{**body,'security_context':{}},{**body,'operation':'remove_master'},{**body,'capability':'all'},[],json.dumps(body)[:-1]+',"target":"qa_master"}']:
        check('HTTP closed JSON '+str(len(qa.checks)),post('open',b)[0]==400)
    for method in ['GET','HEAD','PUT','DELETE']:check('HTTP method '+method,request(path=prefix+'open.php',method=method)[0]==405)
    oversized=http.client.HTTPConnection('127.0.0.1',r['php_port'],timeout=10)
    try:
        oversized.request('POST',prefix+'open.php',body='x'*2049);response=oversized.getresponse();response.read();check('PHP payload bounded before transport parsing',response.status==413)
    finally:oversized.close()
    check('HTTP JSON media type',post('open',body,headers={'Content-Type':'text/plain'})[0]==415)
    status,case,_=post('open',body);check('HTTP exact case opened',status==200)
    before_read=[sql('SELECT * FROM '+t) for t in ['auth_governance_cases','auth_staff_mfa_challenges','auth_staff_mfa_proofs','platform_audit_events']]
    for _ in range(2):check('GET case returns current intent',request(path=prefix+'read.php?case_id='+case['case_id'],method='GET')[0]==200)
    check('GET leaves case MFA and audit unchanged',before_read==[sql('SELECT * FROM '+t) for t in ['auth_governance_cases','auth_staff_mfa_challenges','auth_staff_mfa_proofs','platform_audit_events']])
    password=(owned/'fixture-password').read_text().strip();confirm={'case_id':case['case_id'],'intent_digest':case['intent_digest'],'email':'qa_director@example.test','password':password}
    check('HTTP wrong password denies',post('confirm',{**confirm,'password':'wrong'})[0]==403)
    status,ch,_=post('confirm',confirm);check('HTTP canonical password and challenge',status==200)
    secret=(owned/'mfa-secret').read_bytes();step=int(time.time())//30+1;digest=hmac.new(secret,struct.pack('>Q',step),hashlib.sha1).digest();o=digest[-1]&15;code=str((int.from_bytes(digest[o:o+4],'big')&0x7fffffff)%1000000).zfill(6)
    status,proof,_=post('verify',{'case_id':case['case_id'],'challenge_id':ch['challenge_id'],'challenge':ch['challenge'],'code':code});check('HTTP exact TOTP proof',status==200)
    payload={'case_id':case['case_id'],'proof_id':proof['proof_id'],'proof':proof['proof']};n=len(audit());status,result,_=post('execute',payload);check('HTTP one command commits',status==200 and len(audit())==n+1)
    # Deliberately ignore the command response: canonical case/audit is sufficient to reconcile.
    status,reconciled,_=request(path=prefix+'read.php?case_id='+case['case_id'],method='GET');check('Lost ACK reconciles exact case and audit',status==200 and reconciled['status']=='CONSUMED' and reconciled['result']['operation_id']==case['operation_id'])
    check('HTTP replay no second mutation',post('execute',payload)[0] in [403,409] and len(audit())==n+1)
    # An ordinary active Advisor still cannot be promoted by the real HTTP composition.
    sql("INSERT INTO ase_master_test_reviews VALUES('qa_advisor','qa_advisor',1,'review-test-only','APPROVED')")
    check('HTTP cannot select test eligibility',post('open',{'operation':OPS['appoint'],'target':'qa_advisor','capability':None,'reason':'Synthetic product fail closed'})[0]==503)
    # Missing schema, keys or authorization fail closed; never fall back to Advisor gate.
    php=next(p for p in reversed(qa.processes) if p.poll() is None and p.args[0]=='php' and '-S' in p.args);php.terminate();php.wait(timeout=10)
    off=env.copy();off.pop('MXMED_MASTER_GOVERNANCE_ENABLED');r['spawn']('master-gate-off',['php','-S','127.0.0.1:'+str(r['php_port']),'-t',str(ROOT),str(ROOT/qa.ROUTER)],off);qa.wait_until(lambda:r['port_ready'](r['php_port']))
    check('Master default gate off despite Advisor gate on',request(path=prefix+'csrf.php',method='GET')[0]==503)
    # Products/UI files are byte-identical; source regression consumers were run earlier.
    qa.OUT.mkdir(parents=True,exist_ok=True);(qa.OUT/'security-boundaries.json').write_text(json.dumps({'product_appointment':'BLOCKED_PENDING_STAFF_IDENTITY_REVIEW','test_adapter':'CLI_ONLY_OWNED_DATADIR','http_composition':'NORMAL_FAIL_CLOSED_REVIEW','master_ui_enabled':False,'session_authorization_point':'AFTER_LOCKS_AND_PROOF_BEFORE_MUTATION','logout_before_point_denied':True,'sql_valkey_atomicity':False,'uncertain_response':'RECONCILE_CASE_UUID_AND_AUDIT_NO_AUTOMATIC_RETRY','actual_lost_network_ack_injected':False,'response_ignored_then_reconciled':True,'real_staff_changed':False},indent=2)+'\n')
