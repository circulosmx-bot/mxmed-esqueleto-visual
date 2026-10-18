#!/usr/bin/env python3
"""Fresh owned instances only. Reuses ASE instance lifecycle, never its old suites or DBs."""
import importlib.util,pathlib,subprocess,json,re,secrets,base64,time,hashlib,concurrent.futures
import pymysql
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('owned',ROOT/'scripts/ase-int02a-qa.py');qa=importlib.util.module_from_spec(spec);spec.loader.exec_module(qa)
qa.PHASE='ase-int03b';qa.EXPECTED_BRANCH='feature/ase-int03b-master-privileged-backend';qa.BASE='e71f3618772ff652a40bb597d45c199d5e851d75';qa.OUT=ROOT/'docs/evidence/ase-int03b-imp01';qa.ROUTER='scripts/ase-int03b-router.php'
qa.EXTRA_PROTECTED=['mxmed-ase-int02a-governed-grants','mxmed-ase-int02b-team-permission-ui','mxmed-ase-int03a-advisor-lifecycle','mxmed-ase-int03b-master-governance-prep'];qa.EXTRA_WRITABLE_TABLES=['internal_staff']
qa.EXTRA_SOURCES=[str(p.relative_to(ROOT)) for folder in ['modules/identity/MasterGovernance','api/internal/team/master'] for p in (ROOT/folder).glob('*.php')]+['modules/identity/contracts/StaffIdentityReviewPort.php','modules/identity/contracts/StaffIdentityReviewSnapshot.php','modules/identity/adapters/UnavailableStaffIdentityReview.php','modules/identity/services/StaffMfaService.php','modules/identity/services/SessionService.php','modules/identity/services/GovernanceRuntime.php','modules/identity/services/GovernanceCaseSecurity.php','modules/identity/tests/MasterGovernanceFixture.php','modules/identity/db/migrations/2026_10_18_01_internal_master_governance_operations.sql','scripts/ase-int03b-qa.py','scripts/ase-int03b-extra-qa.py',qa.ROUTER]
I='modules/identity/db/migrations/'
BASE_FILES=[I+n for n in ['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql']]
DEPS=['modules/profiles/db/profiles_doctors_schema.sql','modules/agenda/db/medical_groups_schema.sql',I+'2026_07_19_03_create_auth_account_memberships.sql',I+'2026_10_14_01_create_profile_ownership_governance.sql',I+'2026_10_14_02_profile_ownership_r3_endorsements.sql',I+'2026_10_15_01_identity_governance_r3_runtime.sql','modules/profiles/db/2026_09_11_create_profiles_verified_identities.sql','modules/profiles/db/2026_09_12_create_profiles_doctor_credentials.sql','modules/profiles/db/doctor_contact_points_schema.sql']
ACT02=[I+'2026_10_16_'+s+'.sql' for s in ['01_governance_security_bindings','02_physician_account_binding','03_physician_evidence_foundation','04_physician_authority_issuer','05_public_physician_enrollment','06_reviewed_physician_enrollment']]+['modules/profiles/db/2026_10_16_01_public_correction_reports.sql','modules/profiles/db/2026_10_16_02_correction_applications.sql','modules/subscriptions/db/2026_10_16_01_preloaded_public_free_source.sql','modules/identity/deployment/act02/metadata.sql']
ACT03=I+'2026_10_17_07_professional_source_provisioning.sql';NEW=I+'2026_10_18_01_internal_master_governance_operations.sql'
OPS={'appoint':'INTERNAL_MASTER_APPOINT','remove':'INTERNAL_MASTER_REMOVE','manage':'INTERNAL_ADVISOR_MANAGEMENT_GRANT','unmanage':'INTERNAL_ADVISOR_MANAGEMENT_REVOKE','delegate':'INTERNAL_CAPABILITY_DELEGATE','undelegate':'INTERNAL_CAPABILITY_DELEGATION_REVOKE'}

def run(r):
    sql=r['sql'];env=r['env'];owned=r['owned'];check=qa.check;database=r['database'];rehearsal=[]
    def apply(db,path,expect_failure=False):
        source=(ROOT/path).read_text().replace('{{METADATA_DEFINER}}',"'ase_audit_definer'@'localhost'").replace('{{TARGET_SCHEMA_NAME}}',db)
        p=subprocess.run([r['mysql'],'--no-defaults','--protocol=SOCKET','--socket='+str(owned/'mysql.sock'),'-uroot',db],input=source,text=True,capture_output=True)
        if expect_failure:return p
        if p.returncode:(owned/'master-schema-error.log').write_text(path+'\n'+p.stderr);raise RuntimeError('master_schema_failed:'+path)
    def constraint(db):
        rows=sql("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND CONSTRAINT_NAME='ck_auth_governance_case_operation'",(db,))

        if not rows: print('Missing constraint metadata',db,sql("SELECT CONSTRAINT_SCHEMA,CONSTRAINT_NAME FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_NAME='ck_auth_governance_case_operation'"),flush=True)
        return sorted(re.findall("'([A-Z_]+)'",rows[0][0].replace(chr(92),''))) if rows else []
    historical=set()
    for path in [I+'2026_10_15_01_identity_governance_r3_runtime.sql',*ACT02,ACT03]:
        src=(ROOT/path).read_text()
        for m in re.finditer(r'CONSTRAINT\s+`?ck_auth_governance_case_operation`?\s+CHECK\s*\(\s*`?operation`?\s+IN\s*\((.*?)\)',src,re.I|re.S):historical.update(re.findall("'([A-Z_]+)'",m.group(1)))
    expected=sorted(historical|set(OPS.values()));check('Union derived from versioned sources',len(expected)==17)
    def shape(db):
        return [sql('SELECT TABLE_NAME,CONSTRAINT_NAME,CONSTRAINT_TYPE,ENFORCED FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s ORDER BY TABLE_NAME,CONSTRAINT_NAME',(db,)),sql('SELECT TABLE_NAME,INDEX_NAME,COLUMN_NAME,NON_UNIQUE FROM information_schema.STATISTICS WHERE TABLE_SCHEMA=%s ORDER BY TABLE_NAME,INDEX_NAME,SEQ_IN_INDEX',(db,))]
    for variant in ['A','B','C','D','E']:
        db=database if variant=='E' else database+'_'+variant.lower()
        if variant!='E':
            sql(f'CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci')
            for f in BASE_FILES:apply(db,f)
            apply(db,'modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql')
        for f in DEPS:apply(db,f)
        stages=[]
        for f in (ACT02[:1] if variant=='A' else ACT02):
            apply(db,f);stages.append({'file':f,'effective_operations':constraint(db)})
        # Existing synthetic accounts only in the main DB; no real data import.
        if variant!='E':
            for aid in ['qa_director','qa_target']:
                sql(f"INSERT INTO `{db}`.auth_accounts(account_id,email_address,email_normalized,status,email_verified_at) VALUES(%s,%s,%s,'active',CURRENT_TIMESTAMP)",(aid,aid+'@example.invalid',aid+'@example.invalid'))
        if variant=='D':
            sql(f"INSERT INTO `{db}`.profiles_doctors(doctor_id) VALUES('qa_profile')")
            for op in ['PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY']:
                sql(f"INSERT INTO `{db}`.auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at) VALUES(%s,UUID(),%s,'qa_director','qa_target','qa_profile','Historical synthetic correction','OPEN',UTC_TIMESTAMP(6)+INTERVAL 5 MINUTE)",('historic_'+op,op))
            before=sql(f'SELECT * FROM `{db}`.auth_governance_cases ORDER BY case_id');old=constraint(db);p=apply(db,ACT03,True)
            check('D ACT03 incompatible correction upgrade stops without row changes',p.returncode!=0 and '3819' in p.stderr and before==sql(f'SELECT * FROM `{db}`.auth_governance_cases ORDER BY case_id') and old==constraint(db))
            rehearsal.append({'variant':variant,'result':'BLOCKED_HISTORICAL_ACT03_CHECK','mysql_error_code':3819,'rows_preserved':True,'constraint_preserved':True,'remaining_migrations_not_run':True,'stages':stages});continue
        if variant in ['C','E']:
            apply(db,ACT03);stages.append({'file':ACT03,'effective_operations':constraint(db)})
            check(variant+' ACT03 mismatch observed',not {'PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY'}&set(constraint(db)))
        sql(f"INSERT INTO `{db}`.auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,expires_at) VALUES('historic_recovery',UUID(),'MFA_RECOVERY','qa_director','qa_target','Historical synthetic case','OPEN',UTC_TIMESTAMP(6)+INTERVAL 5 MINUTE)")
        before=sql(f'SELECT * FROM `{db}`.auth_governance_cases ORDER BY case_id');audit=sql(f'SELECT * FROM `{db}`.platform_audit_events ORDER BY event_id');metadata=shape(db)
        apply(db,NEW)
        if constraint(db)!=expected: print('CHECK raw',sql("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND CONSTRAINT_NAME='ck_auth_governance_case_operation'",(db,)),flush=True)
        check(variant+' final effective CHECK union',constraint(db)==expected)
        check(variant+' existing rows audit FK indexes preserved',before==sql(f'SELECT * FROM `{db}`.auth_governance_cases ORDER BY case_id') and audit==sql(f'SELECT * FROM `{db}`.platform_audit_events ORDER BY event_id') and metadata==shape(db))
        for op in expected+['UNKNOWN_OPERATION']:
            try:
                sql(f"INSERT INTO `{db}`.auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,reason,status,expires_at) VALUES(%s,UUID(),%s,'qa_director','qa_target','Synthetic CHECK validation','REVOKED',UTC_TIMESTAMP(6)+INTERVAL 5 MINUTE)",('check_'+op,op));ok=True
            except pymysql.MySQLError as e:ok=False;check(variant+' unknown is CHECK denied',e.args[0]==3819)
            check(variant+' CHECK '+op,ok==(op!='UNKNOWN_OPERATION'))
        check(variant+' FK and CHECK enforcement on',sql('SELECT @@SESSION.foreign_key_checks')[0][0]==1 and not sql("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND CONSTRAINT_TYPE='CHECK' AND ENFORCED<>'YES'",(db,)))
        rehearsal.append({'variant':variant,'result':'PASS','effective_operations':constraint(db),'history_preserved':True,'stages':stages})
    qa.OUT.mkdir(parents=True,exist_ok=True);(qa.OUT/'migration-rehearsal.json').write_text(json.dumps({'variants':rehearsal,'historical_operations':sorted(historical),'new_operations':list(OPS.values()),'real_database_connections':0},indent=2)+'\n')
    for table in ['auth_governance_cases','auth_staff_mfa_factors','auth_staff_mfa_challenges','auth_staff_mfa_proofs','internal_staff','internal_capability_delegations','auth_rate_limit_buckets']:
        sql(f"GRANT SELECT,INSERT,UPDATE ON `{database}`.`{table}` TO 'ase_writer'@'127.0.0.1'")
    sql("CREATE TABLE ase_master_test_reviews(account_id VARCHAR(64) PRIMARY KEY,bound_account_id VARCHAR(64),credential_version INT NOT NULL,review_version VARCHAR(128) NOT NULL,status VARCHAR(16) NOT NULL)")
    sql("INSERT INTO ase_master_test_reviews VALUES('qa_target','qa_target',1,'review-v1','APPROVED')")
    sql(f"GRANT SELECT ON `{database}`.ase_master_test_reviews TO 'ase_writer'@'127.0.0.1'")
    env.update(MXMED_STAFF_MFA_ENCRYPTION_KEY=base64.b64encode(secrets.token_bytes(32)).decode(),MXMED_STAFF_MFA_KEY_ID='synthetic-master-key',MXMED_GOVERNANCE_ORGANIZATION_ID='synthetic-master-qa',MXMED_MASTER_GOVERNANCE_ENABLED='1')
    (owned/'mfa-secret').write_bytes(secrets.token_bytes(20))
    now=[int(time.time())]
    def call(action,**data):
        p=subprocess.run(['php','modules/identity/tests/MasterGovernanceFixture.php'],input=json.dumps({'action':action,'time':now[0],**data}),cwd=ROOT,env=env,text=True,capture_output=True)
        if p.returncode:(owned/'master-fixture-error.log').write_text(p.stderr);raise RuntimeError('master_fixture_failed')
        try:return json.loads(p.stdout)
        except ValueError:(owned/'master-fixture-error.log').write_text(p.stdout+p.stderr);raise RuntimeError('master_fixture_invalid_json')
    def success(action,**data):
        result=call(action,**data)
        if not result['ok']:raise AssertionError(action+': '+result['error'])
        return result['data']
    def deny(name,action,**data):
        result=call(action,**data);check(name,not result['ok']);return result
    success('enroll');now[0]+=30
    def open_case(op,target='qa_target',cap=None,**kw):
        return success('open',operation=OPS[op],target=target,capability='internal_advisors_manage' if op in ['manage','unmanage'] else cap,**kw)
    def authorize(c):
        now[0]+=30
        challenge=success('confirm',case_id=c['case_id'],intent_digest=c['intent_digest'])
        proof=success('verify',case_id=c['case_id'],challenge_id=challenge['challenge_id'],challenge=challenge['challenge'])
        return {'case_id':c['case_id'],'proof_id':proof['proof_id'],'proof':proof['proof']},challenge
    def execute(op,target='qa_target',cap=None):
        c=open_case(op,target,cap);args,ch=authorize(c);out=success('execute',**args);check(op+' canonical case result',success('read',case_id=c['case_id'])['result']==out);return c,args,ch
    def state():return sql("SELECT a.status,s.governance_class,s.status FROM auth_accounts a JOIN internal_staff s ON s.account_id=a.account_id WHERE a.account_id='qa_target'")[0]
    def audit():return sql("SELECT * FROM platform_audit_events ORDER BY event_id")
    def counts():return [sql("SELECT * FROM internal_operator_grants WHERE account_id='qa_target' ORDER BY grant_id"),sql("SELECT * FROM internal_capability_delegations WHERE master_account_id='qa_target' ORDER BY delegation_id")]
    # Positive full lifecycle with canonical MFA and writer.
    initial=counts();c,args,ch=execute('appoint');check('Appointment creates no implicit powers',state()[1]=='MASTER_ADMIN' and counts()==initial)
    deny('Duplicate execution consumes once','execute',**args);deny('Challenge replay','verify',case_id=c['case_id'],challenge_id=ch['challenge_id'],challenge=ch['challenge'])
    execute('manage');execute('delegate',cap='media_review_read');execute('unmanage');check('Management revoke retains delegation',bool(sql("SELECT 1 FROM internal_capability_delegations WHERE master_account_id='qa_target' AND status='ACTIVE'")))
    execute('manage');execute('undelegate',cap='media_review_read');execute('delegate',cap='media_review_read')
    own=sql("SELECT * FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_read'")
    execute('remove');check('Demotion preserves operational grants and state',state()==('active','ADVISOR','ACTIVE') and own==sql("SELECT * FROM internal_operator_grants WHERE account_id='qa_target' AND capability='media_review_read'") and not sql("SELECT 1 FROM internal_capability_delegations WHERE master_account_id='qa_target' AND status='ACTIVE'"))
    deny('Normal product review source fails closed','open',operation=OPS['appoint'],target='qa_target',product_eligibility=True)
    for status in ['UNKNOWN','PENDING','REJECTED']:
        sql('UPDATE ase_master_test_reviews SET status=%s',(status,));deny('Review '+status,'open',operation=OPS['appoint'],target='qa_target')
    sql("UPDATE ase_master_test_reviews SET status='APPROVED',bound_account_id='qa_advisor'");deny('Wrong review account','open',operation=OPS['appoint'],target='qa_target')
    sql("UPDATE ase_master_test_reviews SET bound_account_id='qa_target',credential_version=2");deny('Wrong review credential version','open',operation=OPS['appoint'],target='qa_target')
    sql('UPDATE ase_master_test_reviews SET credential_version=1')
    for who in ['master','advisor','target','suspended','disabled','changed','revoked','expired']:
        for op in OPS.values():deny(who+' cannot open '+op,'open',actor=who,operation=op,target='qa_target',capability='internal_advisors_manage' if 'MANAGEMENT' in op else 'media_review_read' if 'CAPABILITY' in op else None)
    for target in ['qa_director','qa_outsider','qa_nonexistent','qa_suspended_target','qa_disabled_target']:deny('Target ineligible '+target,'open',operation=OPS['appoint'],target=target)
    c=open_case('appoint');deny('Wrong Director password','confirm',case_id=c['case_id'],intent_digest=c['intent_digest'],bad_password=True);deny('Wrong intent digest','confirm',case_id=c['case_id'],intent_digest='0'*64)
    sql("UPDATE ase_master_test_reviews SET review_version='review-v2'");deny('Review changed after preview','confirm',case_id=c['case_id'],intent_digest=c['intent_digest']);success('cancel',case_id=c['case_id'])
    # Failures with real persisted proofs; every attempt must leave mutation untouched.
    for fault in ['expired_case','expired_proof','expired_reauth','revoked_factor','wrong_proof','wrong_operation','wrong_target','wrong_case','wrong_digest','wrong_session','wrong_version','audit_failure']:
        c=open_case('appoint');args,ch=authorize(c);before=state();n=len(audit());heads=sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key');restore=None
        if fault=='expired_case':now[0]+=301
        elif fault=='expired_proof':sql("UPDATE auth_staff_mfa_proofs SET verified_at=UTC_TIMESTAMP(6)-INTERVAL 2 MINUTE,expires_at=UTC_TIMESTAMP(6)-INTERVAL 1 MINUTE WHERE proof_id=%s",(args['proof_id'],))
        elif fault=='expired_reauth':sql("UPDATE auth_staff_mfa_challenges SET reauthentication_context=JSON_SET(reauthentication_context,'$.reauthenticated_at','2000-01-01T00:00:00Z') WHERE challenge_id=%s",(ch['challenge_id'],))
        elif fault=='revoked_factor':sql("UPDATE auth_staff_mfa_factors SET status='REVOKED',revoked_at=UTC_TIMESTAMP(6) WHERE account_id='qa_director'");restore="UPDATE auth_staff_mfa_factors SET status='ACTIVE',revoked_at=NULL WHERE account_id='qa_director'"
        elif fault=='wrong_proof':args['proof']='invalid'
        elif fault in ['wrong_operation','wrong_target','wrong_case','wrong_session','wrong_version']:
            column,value={'wrong_operation':('operation','INITIAL_OWNER'),'wrong_target':('target_account_id','qa_advisor'),'wrong_case':('case_id','historic_recovery'),'wrong_session':('staff_session_id','wrong-session'),'wrong_version':('credential_version',9)}[fault]
            sql(f'UPDATE auth_staff_mfa_proofs SET {column}=%s WHERE proof_id=%s',(value,args['proof_id']))
        elif fault=='wrong_digest':sql("UPDATE auth_staff_mfa_challenges SET reauthentication_context=JSON_SET(reauthentication_context,'$.master_intent_digest','wrong') WHERE challenge_id=%s",(ch['challenge_id'],))
        elif fault=='audit_failure':sql("CREATE TRIGGER master_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic audit failure'")
        deny(fault+' denied','execute',**args)
        check(fault+' no effective mutation',state()==before and len(audit())==n and heads==sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key'))
        check(fault+' proof and case rollback',sql('SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=%s',(args['proof_id'],))[0][0]=='VERIFIED' and sql('SELECT status FROM auth_governance_cases WHERE case_id=%s',(c['case_id'],))[0][0]=='OPEN')
        if fault=='audit_failure':sql('DROP TRIGGER master_audit_failure')
        if restore:sql(restore)
        success('cancel',case_id=c['case_id'])
    # Authority-reducing operations preserve disabled/suspended state.
    for account_status,staff_status in [('active','SUSPENDED'),('disabled','ACTIVE'),('disabled','SUSPENDED')]:
        execute('appoint');execute('manage');execute('delegate',cap='media_review_read')
        sql("UPDATE auth_accounts SET status=%s WHERE account_id='qa_target'",(account_status,));sql("UPDATE internal_staff SET status=%s WHERE account_id='qa_target'",(staff_status,))
        deny('No management increase '+account_status+staff_status,'open',operation=OPS['manage'],target='qa_target',capability='internal_advisors_manage')
        deny('No delegation increase '+account_status+staff_status,'open',operation=OPS['delegate'],target='qa_target',capability='media_review_approve')
        execute('unmanage');execute('undelegate',cap='media_review_read');execute('remove');check('Reduction never reactivates '+account_status+staff_status,state()==(account_status,'ADVISOR',staff_status))
        sql("UPDATE auth_accounts SET status='active' WHERE account_id='qa_target'");sql("UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_target'")
    # Real independent SQL connections, observed writer lock waits, no monkeypatch of services.
    for name,lock,change,restore in [
        ('target suspended','qa_target',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_target'","UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_target'"),
        ('target promoted','qa_target',"UPDATE internal_staff SET governance_class='MASTER_ADMIN' WHERE account_id='qa_target'","UPDATE internal_staff SET governance_class='ADVISOR' WHERE account_id='qa_target'"),
        ('actor suspended','qa_director',"UPDATE internal_staff SET status='SUSPENDED' WHERE account_id='qa_director'","UPDATE internal_staff SET status='ACTIVE' WHERE account_id='qa_director'"),
        ('credential rotated','qa_director',"UPDATE auth_account_credentials SET credential_version=2 WHERE account_id='qa_director'","UPDATE auth_account_credentials SET credential_version=1 WHERE account_id='qa_director'"),
        ('review version changed','qa_target',"UPDATE ase_master_test_reviews SET review_version=CONCAT(review_version,'x') WHERE account_id='qa_target'",None),
    ]:
        c=open_case('appoint');args,ch=authorize(c);n=len(audit());blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=r['fixture_password'],database=database,autocommit=False)
        try:
            with blocker.cursor() as cur:cur.execute('SELECT account_id FROM auth_accounts WHERE account_id=%s FOR UPDATE',(lock,));cur.fetchall()
            with concurrent.futures.ThreadPoolExecutor(max_workers=1) as pool:
                future=pool.submit(call,'execute',**args);qa.wait_until(lambda:bool(sql('SELECT 1 FROM performance_schema.data_lock_waits LIMIT 1')),10)
                waits=sql('SELECT REQUESTING_ENGINE_TRANSACTION_ID,BLOCKING_ENGINE_TRANSACTION_ID FROM performance_schema.data_lock_waits')
                with blocker.cursor() as cur:cur.execute(change)
                blocker.commit();result=future.result(20)
            check('Race '+name,not result['ok'] and len(audit())==n and bool(waits));qa.concurrency.append({'name':name,'independent_connections':True,'observed_lock_waits':waits,'denied':True})
        finally:blocker.rollback();blocker.close()
        if restore:sql(restore)
        if name=='credential rotated':
            # Canonical validation revoked the old token; issue a fresh legitimate synthetic session.
            p=subprocess.run(r['fixture']+['refresh_sessions'],cwd=ROOT,env=env,text=True,capture_output=True,check=True);r['tokens'].update(json.loads(p.stdout));(owned/'sessions.json').write_text(json.dumps(r['tokens']))
            # Execution remains bound to the old session; the fresh Director session may cancel it.
            success('cancel',case_id=c['case_id'])
        else:success('cancel',case_id=c['case_id'])
    # Existing Advisor HTTP and UI preserved.
    for actor in ['director','master']:r['csrf'][actor]=r['request'](actor,path='/api/internal/team/csrf.php',method='GET')[1]['csrf_token']
    check('Advisor grants API regression',r['command'](capability='media_review_approve')[0]==200 and r['command'](operation='revoke',capability='media_review_approve')[0]==200)
    for op in ['suspend','reactivate']:check('Advisor lifecycle '+op,r['request'](path='/api/internal/team/lifecycle.php',body={'operation':op,'target':'qa_target'},headers={'X-CSRF-Token':r['csrf']['director']})[0]==200)
    for path in ['assets/js/internal-team.js','assets/js/internal-team-editor.js','partials/identity/internal-team.php','modules/identity/services/InternalCapabilityCatalog.php']:
        check('UI/catalog preserved '+path,subprocess.check_output(['git','show',qa.BASE+':'+path],cwd=ROOT)==(ROOT/path).read_bytes())
    check('No external notification',not (owned/'inert-notifications.json').exists())
    extra_spec=importlib.util.spec_from_file_location('master_extra',ROOT/'scripts/ase-int03b-extra-qa.py');extra=importlib.util.module_from_spec(extra_spec);extra_spec.loader.exec_module(extra);extra.run(dict(locals(),qa=qa,ROOT=ROOT,OPS=OPS))
    print('Master security checks',len(qa.checks),flush=True)
qa.HOOK=run
if __name__=='__main__':qa.main()
