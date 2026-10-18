#!/usr/bin/env python3
"""Bounded SQL rehearsal. New socket-only MySQL datadirs; no persistent target/apply mode.
Route selection is rehearsal-only, using reference schemas compiled from these sources.
This is NOT an operational migration engine or a deployment authorization issuer.
"""
import contextlib,datetime,hashlib,json,os,pathlib,re,secrets,shutil,subprocess,tempfile,time
import pymysql
ROOT=pathlib.Path(__file__).resolve().parents[1]
OUT=ROOT/'docs/evidence/act03-check-compat01'
BASE='e3182791fd414c1195e3a7bb64bbb60d20d00530'
BRANCH='feature/act03-governance-check-compat01'
I='modules/identity/db/migrations/'
OLD=I+'2026_10_17_07_professional_source_provisioning.sql'
CHECK=I+'2026_10_18_02_professional_source_check_compat01.sql'
OBJECTS=I+'2026_10_18_03_professional_source_objects_compat01.sql'
MASTER=I+'2026_10_18_01_internal_master_governance_operations.sql'
FOUNDATION=[I+n for n in ['2026_07_19_01_create_auth_accounts.sql','2026_07_20_04_create_auth_account_credentials.sql','2026_07_20_06_create_auth_rate_limit_buckets.sql','2026_09_08_07_create_internal_operator_grants.sql','2026_09_10_01_internal_governance.sql']]+['modules/platform/db/migrations/2026_07_20_01_create_platform_audit_events.sql']
DEPS=['modules/profiles/db/profiles_doctors_schema.sql','modules/agenda/db/medical_groups_schema.sql',I+'2026_07_19_03_create_auth_account_memberships.sql',I+'2026_10_14_01_create_profile_ownership_governance.sql',I+'2026_10_14_02_profile_ownership_r3_endorsements.sql',I+'2026_10_15_01_identity_governance_r3_runtime.sql','modules/profiles/db/2026_09_11_create_profiles_verified_identities.sql','modules/profiles/db/2026_09_12_create_profiles_doctor_credentials.sql','modules/profiles/db/doctor_contact_points_schema.sql']
PAT=r'ck_auth_governance_case_operation`?\s+CHECK\s*\(\s*`?operation`?\s+IN\s*\((.*?)\)'
checks=[];resources=[];variants=[];references={};source_hashes={}
def digest(v):return hashlib.sha256(v if isinstance(v,bytes) else json.dumps(v,sort_keys=True,default=str).encode()).hexdigest()
def source(f):return (ROOT/f).read_text()
def filehash(f):return hashlib.sha256((ROOT/f).read_bytes()).hexdigest()
def operation_set(f):
    matches=re.findall(PAT,source(f),re.S|re.I)
    assert len(matches)==1,f
    return sorted(set(re.findall("'([A-Z_]+)'",matches[0])))
def check(name,condition):
    checks.append({'name':name,'pass':bool(condition)})
    if not condition:raise AssertionError(name)
def git(*args,cwd=ROOT):return subprocess.check_output(['git',*args],cwd=cwd,text=True).strip()
def protected():
    names=['mxmed-step2-vitals-r1','mxmed-ase-vis01-prototype','mxmed-ase-int01-readonly','mxmed-ase-int02a-governed-grants','mxmed-ase-int02b-team-permission-ui','mxmed-ase-int03a-advisor-lifecycle','mxmed-ase-int03b-master-governance-prep','mxmed-ase-int03b-master-privileged-backend','mxmed-ase-int03b-master-ui']
    d={n:{'head':git('rev-parse','HEAD',cwd=ROOT.parent/n),'branch':git('branch','--show-current',cwd=ROOT.parent/n),'status':git('--no-optional-locks','status','--porcelain',cwd=ROOT.parent/n)} for n in names}
    d['listeners']={str(p):subprocess.run(['lsof','-t','-nP',f'-iTCP:{p}','-sTCP:LISTEN'],capture_output=True,text=True).stdout.split() for p in [6385,8091,8092,8140,8141,8443]}
    d['pointer']=hashlib.sha256(pathlib.Path('/tmp/mxmed-ase-int01-owned.json').read_bytes()).hexdigest();return d

def package():
    code="require 'modules/identity/http/IdentityHttpComposition.php'; Identity\\Http\\IdentityHttpComposition::registerAutoloader(); echo json_encode([Identity\\Operational\\DeploymentPackage::migrations(),Identity\\Operational\\DeploymentPackage::professionalSourceMigrations()]);"
    return json.loads(subprocess.check_output(['php','-r',code],cwd=ROOT,text=True))
ACT02,COMPAT=package()

class Instance:
    def __init__(self,label):
        self.label=label;self.owned=pathlib.Path(tempfile.mkdtemp(prefix='mxmed-act03compat-',dir='/tmp'));self.data=self.owned/'mysql';self.socket=self.owned/'mysql.sock';self.db='compat_'+secrets.token_hex(5);self.receipts=[];self.p=None;self.conn=None
        self.record={'label':label,'owned_directory':str(self.owned),'database':self.db,'socket_only':True,'stopped':False,'disposed':False};resources.append(self.record)
    def start(self):
        self.data.mkdir()
        with (self.owned/'init.log').open('wb') as log:subprocess.run([shutil.which('mysqld'),'--no-defaults','--initialize-insecure','--datadir='+str(self.data)],stdout=log,stderr=log,check=True,timeout=60)
        with (self.owned/'mysql.log').open('wb') as log:self.p=subprocess.Popen([shutil.which('mysqld'),'--no-defaults','--datadir='+str(self.data),'--socket='+str(self.socket),'--skip-networking','--mysqlx=0','--skip-log-bin','--pid-file='+str(self.owned/'mysql.pid')],stdout=log,stderr=log)
        until=time.monotonic()+30
        while time.monotonic()<until:
            try:self.conn=pymysql.connect(unix_socket=str(self.socket),user='root',autocommit=True);break
            except pymysql.MySQLError:time.sleep(.1)
        assert self.conn is not None
        check(self.label+' own datadir before DDL',pathlib.Path(self.sql('SELECT @@datadir')[0][0]).resolve()==self.data.resolve())
        self.identity={'uuid':self.sql('SELECT @@server_uuid')[0][0],'database':self.db,'datadir':str(self.data.resolve())}
        self.sql(f'CREATE DATABASE `{self.db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');self.conn.select_db(self.db)
        return self
    def close(self):
        if self.conn:self.conn.close()
        if self.p:
            if self.p.poll() is None:self.p.terminate()
            self.p.wait(timeout=30)
        self.record['stopped']=self.p is None or self.p.poll() is not None
        if self.record['stopped']:
            assert self.owned.resolve().parent==pathlib.Path('/private/tmp') and self.owned.name.startswith('mxmed-act03compat-') and not self.owned.is_symlink()
            shutil.rmtree(self.owned);self.record['disposed']=True
    def sql(self,s,args=()):
        with self.conn.cursor() as c:c.execute(s,args);return c.fetchall()
    def cli(self,s):
        return subprocess.run([shutil.which('mysql'),'--no-defaults','--protocol=SOCKET','--socket='+str(self.socket),'-uroot',self.db],input=s,text=True,capture_output=True)
    def effective(self):
        r=self.sql("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_NAME='ck_auth_governance_case_operation'")
        return sorted(set(re.findall("'([A-Z_]+)'",r[0][0].replace('\\','')))) if r else []
    def schema(self):
        # Compare every table definition, FK, unique, generated expression, CHECK and trigger/routine body.
        # Ignore only data-dependent AUTO_INCREMENT counters and substitute this new schema's identifier.
        def norm(s):return re.sub(r' AUTO_INCREMENT=\d+','',s).replace(self.db,'<owned_schema>')
        d={}
        for (t,) in self.sql('SHOW TABLES'):d['table:'+t]=norm(self.sql('SHOW CREATE TABLE `'+t+'`')[0][1])
        for (t,) in self.sql('SELECT TRIGGER_NAME FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE() ORDER BY TRIGGER_NAME'):
            r=self.sql('SHOW CREATE TRIGGER `'+t+'`')[0];d['trigger:'+t]=[r[1],norm(r[2])]
        for (t,) in self.sql('SELECT ROUTINE_NAME FROM information_schema.ROUTINES WHERE ROUTINE_SCHEMA=DATABASE() ORDER BY ROUTINE_NAME'):d['routine:'+t]=norm(self.sql('SHOW CREATE PROCEDURE `'+t+'`')[0][2])
        return d
    def rows(self):
        d={}
        for (t,) in self.sql('SHOW TABLES'):
            cols=[r[0] for r in self.sql('SHOW COLUMNS FROM `'+t+'`')];rows=self.sql('SELECT '+','.join('`'+c+'`' for c in cols)+' FROM `'+t+'`');d[t]={'columns':cols,'rows':sorted(rows,key=str)}
        return d
    def preserved(self,before,allow_new=False):
        for t,v in before.items():
            rows=self.sql('SELECT '+','.join('`'+c+'`' for c in v['columns'])+' FROM `'+t+'`')
            if allow_new:
                if not all(row in rows for row in v['rows']):return False
            elif sorted(rows,key=str)!=v['rows']:return False
        return True
    def apply(self,f):
        if any(r['file']==f for r in self.receipts):raise RuntimeError('BLOCKED_DDL_REPLAY:'+f)
        before=digest(self.schema());text=source(f).replace('{{METADATA_DEFINER}}',"'root'@'localhost'").replace('{{TARGET_SCHEMA_NAME}}',self.db)
        r=self.cli(text);receipt={'file':f,'sha256':filehash(f),'identity':self.identity,'before_schema':before,'after_schema':digest(self.schema()),'status':'COMPLETE' if r.returncode==0 else 'FAILED'};self.receipts.append(receipt)
        if r.returncode:raise RuntimeError('DDL_FAILED:'+f+':'+r.stderr[:350])
    def install_act02(self):
        for f in FOUNDATION+DEPS+ACT02:self.apply(f)
    def evidence(self):
        return {r['file'] for r in self.receipts if r['status']=='COMPLETE' and r['sha256']==filehash(r['file']) and r['identity']==self.identity}

@contextlib.contextmanager
def instance(label):
    x=Instance(label)
    try:yield x.start()
    finally:x.close()

def plan(x):
    # No production auto-selector. This bounded rehearsal requires metadata compiled from exact sources,
    # completed execution evidence bound to this fresh target, and all prerequisites. No filename-only skip.
    if any(r['status']!='COMPLETE' for r in x.receipts):raise RuntimeError('BLOCKED_PARTIAL_DDL')
    evidence=x.evidence()
    if not set(FOUNDATION+DEPS+ACT02)<=evidence:raise RuntimeError('BLOCKED_MISSING_MIGRATION_EVIDENCE')
    actual=x.schema();ops=x.effective()
    if actual==references['act02'] and ops==operation_set('modules/profiles/db/2026_10_16_02_correction_applications.sql'):return [CHECK,OBJECTS]
    if actual==references['legacy'] and OLD in evidence:return [CHECK]
    installed=OLD in evidence or {CHECK,OBJECTS}<=evidence
    if actual==references['compatible'] and installed and CHECK in evidence:return []
    if actual==references['master'] and installed and MASTER in evidence:return []
    raise RuntimeError('BLOCKED_UNKNOWN_OR_PARTIAL_SCHEMA')

def seed(x,corrections=False):
    for a in ['qa_requester','qa_target']:x.sql("INSERT INTO auth_accounts(account_id,email_address,email_normalized,status) VALUES(%s,%s,%s,'active')",(a,a+'@example.invalid',a+'@example.invalid'))
    x.sql("INSERT INTO profiles_doctors(doctor_id) VALUES('qa_profile')")
    case(x,'historical_recovery','MFA_RECOVERY')
    if corrections:
        for op in ['PUBLIC_PROFILE_CORRECTION_REVIEW','PUBLIC_PROFILE_CORRECTION_APPLY']:case(x,'historical_'+op,op,'OPEN')
    x.sql("INSERT INTO platform_audit_events(stream_key,sequence_number,event_id,schema_version,occurred_at_utc,action,risk_level,outcome,correlation_id,request_id,case_reference,metadata_json,previous_hash,event_hash,created_at_utc) VALUES('synthetic-history',1,'qa_event','v1',UTC_TIMESTAMP(6),'SYNTHETIC_GOVERNANCE_HISTORY','L1','SUCCESS','qa_correlation','qa_request','historical_recovery',JSON_OBJECT('synthetic',true),%s,%s,UTC_TIMESTAMP(6))",('0'*64,'1'*64))

def case(x,cid,op,status='REVOKED',requester='qa_requester',operation_id=None):
    x.sql("INSERT INTO auth_governance_cases(case_id,operation_id,operation,requester_account_id,beneficiary_account_id,profile_doctor_id,reason,status,expires_at) VALUES(%s,COALESCE(%s,UUID()),%s,%s,'qa_target','qa_profile','Synthetic compatibility rehearsal',%s,'2099-01-01')",(cid,operation_id,op,requester,status))

def denied(x,name,sql,args=(),code=3819):
    try:x.sql(sql,args)
    except pymysql.MySQLError as e:check(x.label+' '+name,e.args[0]==code);return
    check(x.label+' '+name,False)

def validate(x,expected):
    check(x.label+' exact effective operation set',x.effective()==expected)
    check(x.label+' FK session enforced',x.sql('SELECT @@SESSION.foreign_key_checks')[0][0]==1)
    check(x.label+' all CHECKs enforced',not x.sql("SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND CONSTRAINT_TYPE='CHECK' AND ENFORCED<>'YES'"))
    for i,op in enumerate(operation_set(MASTER)+['UNKNOWN_OPERATION','all','admin.everything']):
        try:case(x,'probe_'+str(i),op);accepted=True
        except pymysql.MySQLError as e:accepted=False;check(x.label+' operation rejection uses CHECK '+op,e.args[0]==3819)
        check(x.label+' allowlist insertion '+op,accepted==(op in expected))
    denied(x,'FK rejects missing requester',"UPDATE auth_governance_cases SET requester_account_id='missing' WHERE case_id='historical_recovery'",code=1452)
    denied(x,'operation UUID uniqueness',"UPDATE auth_governance_cases SET operation_id=(SELECT id FROM (SELECT operation_id id FROM auth_governance_cases WHERE case_id='historical_recovery') s) WHERE case_id='probe_0'",code=1062)
    case(x,'lifecycle','MFA_RECOVERY','OPEN')
    try:case(x,'duplicate_active','MFA_RECOVERY','OPEN');check('duplicate active denied',False)
    except pymysql.MySQLError as e:check(x.label+' active-target uniqueness',e.args[0]==1062)
    denied(x,'invalid consumed lifecycle rejected',"UPDATE auth_governance_cases SET status='CONSUMED' WHERE case_id='lifecycle'")
    x.sql("UPDATE auth_governance_cases SET status='APPROVED',approved_at=UTC_TIMESTAMP(6) WHERE case_id='lifecycle'")
    x.sql("UPDATE auth_governance_cases SET status='CONSUMED',consumed_at=UTC_TIMESTAMP(6) WHERE case_id='lifecycle'")
    case(x,'next_lifecycle','MFA_RECOVERY','OPEN');check(x.label+' lifecycle releases active target',True)
    denied(x,'audit update guard',"UPDATE platform_audit_events SET action='changed' WHERE event_id='qa_event'",code=1644)
    denied(x,'audit delete guard',"DELETE FROM platform_audit_events WHERE event_id='qa_event'",code=1644)
    check(x.label+' no staff grant delegation MFA seeding',all(x.sql('SELECT COUNT(*) FROM '+t)[0][0]==0 for t in ['internal_staff','internal_operator_grants','internal_capability_delegations','auth_staff_mfa_factors']))

def guards(x):
    # Actual guarded rows, no authority granted: collection/files/journal only, no person documents.
    x.sql("INSERT INTO auth_physician_enrollment_origins(origin_id,handle_digest,browser_digest,start_digest,initial_origin,original_profile_doctor_id,original_public_path,source_provenance,account_id,status,intake_version,audit_reference,created_at,expires_at,bound_at) VALUES('qa_origin',%s,%s,%s,'CLAIM_EXISTING_PROFILE','qa_profile','/synthetic',JSON_OBJECT('synthetic',true),'qa_target','BOUND',1,'qa_audit',UTC_TIMESTAMP(6),UTC_TIMESTAMP(6)+INTERVAL 10 MINUTE,UTC_TIMESTAMP(6))",('a'*64,'b'*64,'c'*64))
    x.sql("INSERT INTO auth_professional_source_requests(origin_id,account_id,profile_doctor_id,intake_version,collection_case_id,audit_reference,created_at) VALUES('qa_origin','qa_target','qa_profile',1,'historical_recovery','qa_audit',UTC_TIMESTAMP(6))")
    x.sql("INSERT INTO auth_professional_source_files(file_id,origin_id,kind,storage_key,key_id,ciphertext_hash,mime_type,retention_policy_reference,created_at) VALUES('qa_file','qa_origin','OFFICIAL_ID','synthetic/no-file','synthetic-key',%s,'application/pdf','synthetic-retention',UTC_TIMESTAMP(6))",('d'*64,))
    x.sql("INSERT INTO auth_professional_source_journal(event_id,origin_id,step,audit_reference,created_at) VALUES('qa_source_event','qa_origin','COLLECTION_OPENED','qa_audit',UTC_TIMESTAMP(6))")
    for table,key in [('auth_professional_source_files',"file_id='qa_file'"),('auth_professional_source_journal',"event_id='qa_source_event'")]:
        denied(x,table+' immutable update','UPDATE '+table+' SET audit_reference=\'x\' WHERE '+key if table.endswith('journal') else 'UPDATE '+table+" SET key_id='changed' WHERE "+key,code=1644)
        denied(x,table+' immutable delete','DELETE FROM '+table+' WHERE '+key,code=1644)
    denied(x,'source binding guard',"UPDATE auth_professional_source_requests SET intake_version=2 WHERE origin_id='qa_origin'",code=1644)
    check(x.label+' all seven source guards structurally identical',all(x.schema()[k]==references['compatible'][k] for k in references['compatible'] if k.startswith('trigger:source_')))

def record(x,label,before,route,extra=None):
    check(label+' all historical column values preserved',x.preserved(before,allow_new=True))
    variants.append({'variant':label,'result':'PASS','route':route,'effective_check':x.effective(),'before_rows_hash':digest(before),'preserved_original_columns':True,'schema_hash':digest(x.schema()),'receipts':x.receipts,**(extra or {})})

def run():
    check('Package successor order',COMPAT==ACT02+[CHECK,OBJECTS])
    original=source(OLD);body=source(OBJECTS)
    check('Historical object DDL preserved byte-for-byte',body[body.index('CREATE TABLE auth_professional_source_requests'):]==original[original.index('CREATE TABLE auth_professional_source_requests'):])
    historical=set()
    for f in [I+'2026_10_15_01_identity_governance_r3_runtime.sql',*ACT02,OLD]:
        if re.search(PAT,source(f),re.S|re.I):historical.update(operation_set(f))
    check('Compatible CHECK is exact source-derived cumulative union',operation_set(CHECK)==sorted(historical) and len(historical)==11)
    check('Master adds exactly six separately versioned operations',set(operation_set(MASTER))-historical=={x for x in operation_set(MASTER) if x.startswith('INTERNAL_')} and len(operation_set(MASTER))==17)
    check('Historical migration untouched',subprocess.check_output(['git','show',BASE+':'+OLD],cwd=ROOT)==(ROOT/OLD).read_bytes())
    with instance('reference') as x:
        x.install_act02();references['act02']=x.schema();x.apply(OLD);references['legacy']=x.schema();x.apply(CHECK);references['compatible']=x.schema();x.apply(MASTER);references['master']=x.schema()
    for label in ['A','B','C','D','E','F','G']:
        with instance(label) as x:
            x.install_act02()
            if label in ['C','E']:x.apply(OLD)
            seed(x,corrections=label in ['D','F'])
            before=x.rows();old_schema=x.schema();extra={}
            if label=='D':
                r=x.cli(source(OLD));check('D historical ACT03 fails 3819 before object DDL',r.returncode!=0 and '3819' in r.stderr and x.schema()==old_schema and x.preserved(before));extra['historical_error']=3819
            route=plan(x)
            for f in route:x.apply(f)
            check(label+' complete physical compatible schema',x.schema()==references['compatible']);check(label+' migration preserved all original rows',x.preserved(before))
            # Stage 11 never permits Master. Stage 12 never silently adds those operations.
            if label in ['A','B','D','E','G']:validate(x,operation_set(CHECK))
            if label in ['C','F']:
                pre=x.rows();x.apply(MASTER);check(label+' Master extension preserves existing rows',x.preserved(pre));validate(x,operation_set(MASTER));route.append(MASTER)
            guards(x)
            if label=='G':
                stable=x.schema();data=x.rows();count=len(x.receipts);check('G complete evidence plus schema yields no-op',plan(x)==[] and len(x.receipts)==count and x.schema()==stable and x.rows()==data)
                try:x.apply(OBJECTS);check('G duplicate blocked',False)
                except RuntimeError as e:check('G recorded duplicate blocked before DDL','BLOCKED_DDL_REPLAY' in str(e) and x.schema()==stable and x.rows()==data)
                saved=x.receipts;x.receipts=[r for r in saved if r['file']!=OBJECTS]
                try:plan(x);check('G schema without execution evidence denied',False)
                except RuntimeError:check('G schema without execution evidence denied',True)
                x.receipts=saved
                # Do not run the 11-operation repair over an installed Master schema.
                x.apply(MASTER);stable=x.schema();check('G Master repeat preserves 17 without downgrade',plan(x)==[] and x.schema()==stable and len(x.effective())==17);route.append(MASTER)
                data=x.rows();r=x.cli(source(CHECK));check('G raw CHECK successor cannot downgrade Master',r.returncode!=0 and '3819' in r.stderr and x.schema()==stable and x.rows()==data)
                extra['repeated_install']='VERIFIED_NOOP_NOT_SQL_REPLAY'
            record(x,label,before,route,extra)
    # Interruption after committed historical DDL: no in-place guess/replay. Restore full prestate to a NEW datadir.
    with instance('H-interrupted') as x:
        x.install_act02();seed(x);before=x.rows();pre_schema=x.schema();prior=list(x.receipts)
        dump=subprocess.check_output([shutil.which('mysqldump'),'--no-defaults','--protocol=SOCKET','--socket='+str(x.socket),'-uroot','--single-transaction','--no-create-info','--skip-triggers','--skip-add-locks','--skip-disable-keys','--complete-insert','--skip-extended-insert','--set-gtid-purged=OFF',x.db],text=True)
        cut=source(OLD).index('CREATE TABLE auth_professional_source_files')
        r=x.cli(source(OLD)[:cut]+"SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_interruption';")
        x.receipts.append({'file':OLD,'sha256':filehash(OLD),'identity':x.identity,'status':'FAILED','after_schema':digest(x.schema())})
        check('H failure after committed CHECK and first CREATE',r.returncode!=0 and x.effective()==operation_set(OLD) and 'table:auth_professional_source_requests' in x.schema() and x.schema()!=pre_schema)
        x.sql('ROLLBACK');check('H ROLLBACK does not undo DDL',x.schema()!=pre_schema and x.preserved(before))
        try:plan(x);check('H partial in-place blocked',False)
        except RuntimeError as e:check('H partial in-place blocked',str(e)=='BLOCKED_PARTIAL_DDL')
        # Restore all prestate DDL from exact source receipts, then every backed-up INSERT in FK order.
        # Never execute mysqldump's session prologue (which can disable FK checks).
        with instance('H-restored') as restored:
            restored.install_act02()
            inserts={}
            for line in dump.splitlines():
                m=re.match(r'INSERT INTO `([a-z_]+)` ',line)
                if m:inserts.setdefault(m[1],[]).append(line)
            check('H backup contains every populated table',set(inserts)=={t for t,v in before.items() if v['rows']})
            edges=restored.sql('SELECT TABLE_NAME,REFERENCED_TABLE_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL')
            remaining=set(inserts)
            while remaining:
                ready=sorted(t for t in remaining if not any(child==t and parent in remaining and parent!=t for child,parent in edges))
                check('H restore dependency order available',bool(ready))
                for table in ready:
                    for statement in inserts[table]:restored.sql(statement)
                    remaining.remove(table)
            check('H full backup restored on fresh owned target',restored.schema()==pre_schema and restored.preserved(before) and restored.sql('SELECT @@SESSION.foreign_key_checks')[0][0]==1)
            for receipt in restored.receipts:receipt['restored_from']={'identity':x.identity,'backup_sha256':digest(dump.encode()),'source_receipt_sha256':digest(prior)}
            route=plan(restored)
            for f in route:restored.apply(f)
            validate(restored,operation_set(CHECK));guards(restored)
            record(restored,'H',before,route,{'recovery':'FULL_VERIFIED_BACKUP_TO_NEW_DATADIR','partial_in_place':'BLOCKED_PARTIAL_DDL','transactional_ddl_rollback_claimed':False,'partial_schema_hash':digest(x.schema()),'backup_sha256':digest(dump.encode())})
    # Record complete physical reference metadata, not inferred migration markers.
    (OUT/'schema-reference.json').write_text(json.dumps({'source_sha256':source_hashes,'schemas':references},indent=2)+'\n')

def main():
    assert git('branch','--show-current')==BRANCH and subprocess.run(['git','merge-base','--is-ancestor',BASE,'HEAD'],cwd=ROOT).returncode==0
    assert all(shutil.which(x) for x in ['mysqld','mysql','mysqldump']);os.umask(0o077);OUT.mkdir(parents=True,exist_ok=True)
    before=protected();error=None
    source_hashes.update({f:filehash(f) for f in FOUNDATION+DEPS+ACT02+[OLD,CHECK,OBJECTS,MASTER,'modules/identity/Operational/DeploymentPackage.php','scripts/act03-check-compat01-qa.py']})
    try:run()
    except Exception as e:error=str(e);print('QA FAILED:',error,flush=True)
    after=protected();unchanged=before==after
    if not unchanged:error=(error or '')+' protected state changed'
    if not all(r['stopped'] and r['disposed'] for r in resources):error=(error or '')+' owned cleanup incomplete'
    result={'result':'PASS' if error is None else 'FAIL','error':error,'baseline':BASE,'source_sha256':source_hashes,'checks':checks,'check_count':len(checks),'variants':variants,'resources':resources,'protected_before':before,'protected_after':after,'protected_unchanged':unchanged,'real_database_connections':0,'real_ddl_executed':False,'operational_activation':False,'scope':'SQL_ONLY_NO_MASTER_UI_SUITE_OR_AUTHORITY_CHANGES'}
    name='qa.json' if error is None else 'failed-'+datetime.datetime.now(datetime.timezone.utc).strftime('%Y%m%dT%H%M%SZ')+'.json'
    (OUT/name).write_text(json.dumps(result,indent=2)+'\n');print('RESULT',result['result'],'CHECKS',len(checks),'EVIDENCE',OUT/name,flush=True)
    if error:raise SystemExit(1)
if __name__=='__main__':main()
