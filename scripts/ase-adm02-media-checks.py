"""A–AJ synthetic files only; uses real canonical applicant sessions and joined audit SQL."""
import concurrent.futures,hashlib,json,pathlib,secrets,subprocess,time,uuid
import pymysql
PORTRAIT='INTERNAL_PRESENTATION_PORTRAIT';EVIDENCE='STAFF_IDENTITY_EVIDENCE'
def run(r,check,races):
    root=r['ROOT'];owned=r['owned'];sql=r['sql'];env=r['env'];db=r['database']
    check('IMP03 preconditions: all 201 admission/invitation regressions passed',len(check.__globals__['checks'])==201 and all(c['pass'] for c in check.__globals__['checks']))
    r['source_sql']((root/'modules/identity/db/migrations/2026_10_18_06_staff_private_media.sql').read_text())
    private=owned/'staff-private';private.mkdir(mode=0o700)
    (owned/'media-key').write_bytes(secrets.token_bytes(32));(owned/'media-operation-key').write_bytes(secrets.token_bytes(32));password=secrets.token_urlsafe(32);(owned/'media-db-password').write_text(password)
    sql("CREATE USER 'ase_media'@'127.0.0.1' IDENTIFIED BY %s",(password,))
    for t in ['auth_accounts','auth_account_credentials','internal_staff','auth_staff_admission_cases']:
        sql(f"GRANT SELECT ON `{db}`.`{t}` TO 'ase_media'@'127.0.0.1'")
    for t in ['auth_staff_media_assets','auth_staff_media_operations','platform_audit_events','platform_audit_stream_heads']:
        sql(f"GRANT SELECT,INSERT ON `{db}`.`{t}` TO 'ase_media'@'127.0.0.1'")
    sql(f"GRANT EXECUTE,LOCK TABLES ON `{db}`.* TO 'ase_media'@'127.0.0.1'");sql("GRANT SHOW_ROUTINE ON *.* TO 'ase_media'@'127.0.0.1'")
    preserved=['auth_accounts','auth_account_credentials','auth_account_consents','internal_staff','internal_operator_grants','internal_capability_delegations','auth_staff_admission_cases','auth_staff_admission_versions','auth_staff_admission_operations','auth_staff_admission_invitations','auth_staff_admission_invitation_operations']
    before={t:sql('SELECT * FROM '+t+' ORDER BY 1') for t in preserved}
    candidates=sql("SELECT c.case_id,c.version FROM auth_staff_admission_cases c JOIN auth_staff_admission_invitations i ON i.invitation_id=c.binding_reference WHERE c.bound_account_id='qa_outsider' AND c.state='PREPARING' AND i.state='CONSUMED' ORDER BY c.case_id")
    case,case_version=candidates[0];other_case,other_version=candidates[1]
    new_case,new_case_version=sql("SELECT c.case_id,c.version FROM auth_staff_admission_cases c JOIN auth_staff_admission_invitations i ON i.invitation_id=c.binding_reference WHERE c.bound_account_id=i.consumed_account_id AND c.bound_account_id<>'qa_outsider' AND c.state<>'REVOKED' AND i.state='CONSUMED' ORDER BY c.case_id LIMIT 1")[0]
    def uid():return str(uuid.uuid4())
    def call(action,actor='outsider',input=None,**kw):
        p=subprocess.run(['php','modules/identity/tests/StaffMediaFixture.php',action],cwd=root,env=env,input=json.dumps(dict(actor=actor,input=input or {},**kw)),capture_output=True,text=True)
        if p.returncode:(owned/'media-private-process.log').write_text(p.stderr);raise AssertionError('media CLI process failed')
        try:return json.loads(p.stdout)
        except ValueError:raise AssertionError('media CLI result invalid')
    def good(action,**kw):
        v=call(action,**kw)
        if not v['ok']:raise AssertionError('media '+action+' '+v['error'])
        return v['data']
    def write(purpose=PORTRAIT,version=0,**kw):
        return dict(operation_id=uid(),case_id=case,expected_case_version=case_version,expected_asset_version=version,purpose=purpose,declared_mime='image/png',**kw)
    def read(a):return dict(case_id=a['case_id'],expected_case_version=case_version,purpose=a['purpose'],asset_id=a['asset_id'],asset_version=a['asset_version'])
    def result(b):return dict(case_id=b['case_id'],operation_id=b['operation_id'],purpose=b['purpose'])
    def row(a):
        keys=['storage_key','ciphertext_hash','mime_type','source_mime','byte_length','case_version','account_id','source_actor_account_id']
        return dict(zip(keys,sql('SELECT '+','.join(keys)+' FROM auth_staff_media_assets WHERE asset_id=%s',(a['asset_id'],))[0]))
    def files():return set(private.glob('private/staff-admission/*.enc'))
    def deny(label,action='read',**kw):check(label,not call(action,**kw)['ok'])
    good('setup')
    pb=write();portrait=good('write',input=pb,file='metadata.png');pr=good('read',input=read(portrait));pm=row(portrait)
    check('A/B portrait transformed privately to WebP',pr['mime_type']=='image/webp' and not any('url' in k for k in portrait) and portrait['identity_review']=='UNAVAILABLE')
    check('I embedded portrait metadata removed',pr['metadata_removed'] and b'SYNTHETIC_METADATA_REMOVE' in (private/'metadata.png').read_bytes())
    check('D ciphertext only private 0600 at exact staff namespace',pm['storage_key'].startswith('private/staff-admission/sma_') and (private/pm['storage_key']).stat().st_mode&0o777==0o600 and (private/pm['storage_key']).read_bytes()[:4]!=b'RIFF')
    eb=write(EVIDENCE);evidence=good('write',input=eb,file='synthetic.png');er=good('read',input=read(evidence));em=row(evidence)
    check('C/D evidence stays distinct original validated raster, privately encrypted',er['mime_type']=='image/png' and er['sha256']==hashlib.sha256((private/'synthetic.png').read_bytes()).hexdigest() and em['storage_key']!=pm['storage_key'])
    deny('C portrait permission/request cannot read evidence purpose',input={**read(evidence),'purpose':PORTRAIT})
    check('Metadata exact account/profile/source actor provenance',em['account_id']==em['source_actor_account_id']=='qa_outsider' and em['case_version']==case_version and em['byte_length']==(private/'synthetic.png').stat().st_size)
    # Never accept nominal browser MIME, PDF, trailing/polyglot or unbounded/invalid raster data.
    for filename,label,mime in [('synthetic.png','E actual MIME mismatch','image/jpeg'),('malformed.png','F malformed raster','image/png'),('oversized.png','G input byte bound','image/png'),('wide.png','H side resource bound','image/png'),('pixels.png','H pixel resource bound','image/png'),('polyglot.png','E trailing active/polyglot payload','image/png'),('unsupported.pdf','PDF intentionally not implemented','image/png')]:
        deny(label,'write',input={**write(version=1),'declared_mime':mime},file=filename)
    deny('Evidence rejects metadata-bearing source outside explicit subset','write',input=write(EVIDENCE,1),file='metadata.png')
    jpeg_b=write(EVIDENCE,1);jpeg_b['declared_mime']='image/jpeg';jpeg=good('write',input=jpeg_b,file='synthetic.jpg')
    check('JPEG actual decode accepted only as private evidence',good('read',input=read(jpeg))['mime_type']=='image/jpeg')
    old_cipher=(private/pm['storage_key']).read_bytes();replacement_b=write(version=1);replacement=good('write',input=replacement_b,file='synthetic.png')
    historical=good('read',input=read(portrait))
    check('J/K replacement increments asset version preserves prior cipher/history',replacement['asset_version']==2 and not historical['is_current'] and (private/pm['storage_key']).read_bytes()==old_cipher and sql('SELECT previous_version FROM auth_staff_media_assets WHERE asset_id=%s',(replacement['asset_id'],))[0][0]==1)
    check('W exact duplicate returns canonical asset without another file',good('write',input=replacement_b,file='synthetic.png')==replacement)
    deny('W conflicting same operation content rejected','write',input=replacement_b,file='metadata.png')
    check('AB lost ACK reconciles persisted operation and audit',good('result',input=result(replacement_b))['reconciliation']=='COMMITTED_AVAILABLE')
    # Canonical sessions + exact case/asset/account/purpose/version. No administrative shortcut.
    for actor,label in [('new','M other legitimate applicant'),('master','O unauthorized Master'),('advisor','P Advisor peer'),('director','Director directory authority is not evidence authority'),('','N token/no session'),('disabled','Disabled canonical account'),('changed','Stale canonical credential'),('revoked','Revoked session')]:
        deny(label,input=read(evidence),actor=actor)
    for actor in ['new','master','advisor','director']:
        deny('Write requires exact applicant '+actor,'write',actor=actor,input=write(version=2),file='synthetic.png')
    deny('Invitation token is not an accepted authority field','write',input={**write(version=2),'invitation_token':secrets.token_urlsafe(32)},file='synthetic.png')
    deny('L own other case cannot substitute asset',input={**read(evidence),'case_id':other_case,'expected_case_version':other_version})
    for patch in [{'asset_id':'sma_'+secrets.token_hex(16)},{'asset_version':99},{'expected_case_version':case_version+1},{'case_id':new_case}]:
        deny('Q forged exact asset/case/version',input={**read(evidence),**patch})
    deny('Other applicant cannot reconcile operation','result',actor='new',input=result(eb))
    check('Denied authenticated sensitive access is canonically audited',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='STAFF_MEDIA_READ' AND outcome='DENIED'")[0][0]>=8)
    # AEAD context binds all dimensions, key and integrity. No raw plaintext in returned diagnostics.
    for name,passed in good('crypto',asset_id=evidence['asset_id']).items():check('R/S/T cipher '+name,passed)
    deny('S wrong encryption key',input=read(evidence),key_mode='wrong')
    deny('S unknown encryption key',input=read(evidence),key_mode='unknown')
    check('Unknown key reconciliation explicit and not deleted',good('result',input=result(eb),key_mode='unknown')['reconciliation']=='COMMITTED_KEY_UNAVAILABLE' and (private/em['storage_key']).exists())
    cipher_path=private/em['storage_key'];saved=cipher_path.read_bytes();tampered=bytearray(saved);tampered[-1]^=1;cipher_path.write_bytes(tampered)
    deny('T ciphertext tampering rejected before readback',input=read(evidence))
    check('Integrity reconciliation explicit',good('result',input=result(eb))['reconciliation']=='COMMITTED_INTEGRITY_UNAVAILABLE')
    cipher_path.write_bytes(saved)
    # Move exact test-owned file aside; no product cleanup method is invoked.
    missing=private/'owned-unavailable-cipher';cipher_path.rename(missing)
    deny('AA committed file unavailable does not return bytes',input=read(evidence))
    check('AA committed SQL remains explicit while file unavailable',good('result',input=result(eb))['reconciliation']=='COMMITTED_FILE_UNAVAILABLE' and sql('SELECT COUNT(*) FROM auth_staff_media_assets WHERE asset_id=%s',(evidence['asset_id'],))[0][0]==1)
    missing.rename(cipher_path)
    for name,passed in good('storage').items():check('U/V storage '+name,passed)
    # Y rollback SQL + audit, retain encrypted orphan; never auto-delete or overwrite on retry.
    ab=write(version=2);before_files=files();counts=sql('SELECT (SELECT COUNT(*) FROM auth_staff_media_assets),(SELECT COUNT(*) FROM auth_staff_media_operations),(SELECT COUNT(*) FROM platform_audit_events)')[0]
    sql("CREATE TRIGGER synthetic_staff_media_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='STAFF_MEDIA_CHANGED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_media_audit_failure'; END IF; END")
    failed=call('write',input=ab,file='synthetic.png')
    sql('DROP TRIGGER synthetic_staff_media_audit_failure')
    check('Y audit failure rolls back all media SQL and audit',not failed['ok'] and counts==sql('SELECT (SELECT COUNT(*) FROM auth_staff_media_assets),(SELECT COUNT(*) FROM auth_staff_media_operations),(SELECT COUNT(*) FROM platform_audit_events)')[0])
    orphans=files()-before_files
    check('Z encrypted orphan retained and exactly reconciled',len(orphans)==1 and good('result',input=result(ab))['reconciliation']=='ORPHAN_ENCRYPTED_RETAINED')
    orphan=next(iter(orphans));orphan_hash=hashlib.sha256(orphan.read_bytes()).hexdigest()
    check('Z blind retry cannot overwrite orphan',call('write',input=ab,file='synthetic.png').get('error')=='staff_media_reconcile_required' and hashlib.sha256(orphan.read_bytes()).hexdigest()==orphan_hash)
    unknown={**result(ab),'operation_id':uid()}
    check('Uncommitted operation without file distinguished',good('result',input=unknown)['reconciliation']=='NO_COMMITTED_OPERATION_OR_FILE')
    # Fail after audit append but before asset insertion: joined stream heads must roll back too.
    sql_b=write(version=2);sql_counts=sql('SELECT (SELECT COUNT(*) FROM auth_staff_media_assets),(SELECT COUNT(*) FROM auth_staff_media_operations),(SELECT COUNT(*) FROM platform_audit_events)')[0]
    heads=sql("SELECT * FROM platform_audit_stream_heads ORDER BY stream_key")
    sql("CREATE TRIGGER synthetic_media_sql_failure BEFORE INSERT ON auth_staff_media_assets FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_media_sql_failure'")
    failed=call('write',input=sql_b,file='synthetic.png');sql('DROP TRIGGER synthetic_media_sql_failure')
    check('SQL failure after successful audit append rolls back rows and stream heads',not failed['ok'] and sql_counts==sql('SELECT (SELECT COUNT(*) FROM auth_staff_media_assets),(SELECT COUNT(*) FROM auth_staff_media_operations),(SELECT COUNT(*) FROM platform_audit_events)')[0] and heads==sql('SELECT * FROM platform_audit_stream_heads ORDER BY stream_key'))
    check('SQL insertion failure retains identifiable encrypted orphan',good('result',input=result(sql_b))['reconciliation']=='ORPHAN_ENCRYPTED_RETAINED')
    sql("CREATE TRIGGER synthetic_media_read_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='STAFF_MEDIA_READ' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_media_read_audit_failure'; END IF; END")
    failed=call('read',input=read(evidence));sql('DROP TRIGGER synthetic_media_read_audit_failure')
    check('Sensitive read audit failure returns no plaintext/data',not failed['ok'] and 'data' not in failed)
    # Independent SQL connections serialize on same canonical actor and case.
    a=write(version=2);b=write(version=2)
    blocker=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='root',database=db,autocommit=False)
    with blocker.cursor() as cur:cur.execute('SELECT case_id FROM auth_staff_admission_cases WHERE case_id=%s FOR UPDATE',(case,))
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        futures=[pool.submit(call,'write',input=x,file='synthetic.png') for x in [a,b]]
        end=time.monotonic()+10;observed=False
        while time.monotonic()<end:
            if sql('SELECT COUNT(*) FROM performance_schema.data_lock_waits')[0][0]>=2:observed=True;break
            time.sleep(.05)
        blocker.commit();blocker.close();out=[f.result() for f in futures]
    check('X concurrent replacements have one authoritative version winner',observed and sum(o['ok'] for o in out)==1 and sql('SELECT COUNT(*) FROM auth_staff_media_assets WHERE case_id=%s AND purpose=%s AND asset_version=3',(case,PORTRAIT))[0][0]==1)
    races.append({'name':'media_concurrent_replacement','independent_connections':True,'lock_wait_observed':observed,'one_commit':sum(o['ok'] for o in out)==1})
    duplicate=write(version=3)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:out=list(pool.map(lambda _:call('write',input=duplicate,file='synthetic.png'),range(2)))
    check('W concurrent same operation has one immutable result',all(o['ok'] for o in out) and out[0]==out[1])
    races.append({'name':'media_duplicate_operation','independent_connections':True,'same_result':out[0]==out[1]})
    # Storage immutability/purpose/schema and unchanged case/invitation/staff writers.
    for statement in ["UPDATE auth_staff_media_assets SET case_version=99 LIMIT 1","DELETE FROM auth_staff_media_assets LIMIT 1","UPDATE auth_staff_media_operations SET request_digest=REPEAT('a',64) LIMIT 1"]:
        try:sql(statement);blocked=False
        except pymysql.MySQLError:blocked=True
        check('Immutable media SQL '+statement.split()[0],blocked)
    for t in preserved:check('AC/AD/AE no existing canonical data changed '+t,before[t]==sql('SELECT * FROM '+t+' ORDER BY 1'))
    audit=repr(sql("SELECT metadata_json FROM platform_audit_events WHERE action IN ('STAFF_MEDIA_CHANGED','STAFF_MEDIA_READ')"))
    check('Audit metadata excludes private storage, names, bytes and keys',all(term not in audit for term in ['SYNTHETIC_METADATA_REMOVE','private/staff-admission','qa_outsider@example.test','ciphertext','key_id','given_names']))
    for action in ['upload','document','portrait','media-read']:
        check('AH no file intake/download endpoint '+action,r['request'](path='/api/internal/staff-admission/'+action+'.php')[0]==404)
    check('AI media default gate closed',call('production',enabled='0').get('error')=='staff_media_disabled')
    check('AI flag cannot ratify production policy',call('production',enabled='1').get('error')=='staff_media_production_policy_unavailable')
    for path in ['modules/identity/StaffAdmission/Service.php','modules/identity/StaffAdmission/UnitOfWork.php','modules/identity/StaffAdmission/InvitationService.php','modules/identity/MasterGovernance/Composition.php','modules/identity/adapters/UnavailableStaffIdentityReview.php','modules/media/storage/LocalPersistentPrivateMediaStorage.php','modules/media/services/DoctorProfilePhotoService.php','modules/media/services/GdPublicLogoProcessor.php','modules/identity/Accreditation/EvidenceCipher.php','modules/identity/Operational/DeploymentPackage.php']:
        check('AF/AG protected source unchanged '+path,subprocess.check_output(['git','show',r['BASE']+':'+path],cwd=root)==(root/path).read_bytes())
    p=subprocess.run(['php','modules/platform/tests/StaffMediaAuditPolicyTest.php'],cwd=root,env=env,capture_output=True,text=True)
    check('Narrow canonical media audit policy plus historical 59 pin',p.returncode==0)
    # Only the owning runner removes all its encrypted files at teardown. Product delete remains closed.
    check('AJ private root and disposable keys owned before disposal',private.resolve().parent==owned.resolve() and private.stat().st_mode&0o777==0o700 and all((owned/f).stat().st_mode&0o777==0o600 for f in ['media-key','media-operation-key','media-db-password']))
    for f in ['media-key','media-operation-key','media-db-password']:
        p=owned/f
        with p.open('r+b') as stream:stream.write(b'\0'*p.stat().st_size);stream.flush()
        p.unlink()
    check('AJ keys removed; private files scheduled for owned datadir cleanup',not any((owned/f).exists() for f in ['media-key','media-operation-key','media-db-password']))
    print('Staff media A–AJ checks complete',flush=True)
