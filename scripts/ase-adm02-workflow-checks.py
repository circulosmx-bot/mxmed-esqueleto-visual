"""Directed end-to-end consolidation checks. Only called by the fresh owned runner."""
import pathlib,json,subprocess,secrets,hashlib,uuid,re,time,base64,hmac,struct,concurrent.futures

def setup(r,check):
    root=r['ROOT'];sql=r['sql'];owned=r['owned'];db=r['database'];source=r['source_sql']
    for f in ['modules/profiles/db/profiles_doctors_schema.sql','modules/agenda/db/medical_groups_schema.sql',*[f'modules/identity/db/migrations/{s}.sql' for s in ['2026_07_19_02_create_auth_account_consents','2026_07_20_05_create_auth_account_one_time_tokens','2026_07_19_03_create_auth_account_memberships','2026_10_14_01_create_profile_ownership_governance','2026_10_14_02_profile_ownership_r3_endorsements','2026_10_15_01_identity_governance_r3_runtime','2026_10_16_01_governance_security_bindings','2026_10_18_01_internal_master_governance_operations','2026_10_18_05_staff_admission_invitations','2026_10_18_06_staff_private_media','2026_10_18_07_staff_workflow','2026_10_18_08_staff_definitive_removal']]]:source((root/f).read_text())
    # Narrow privileges for the owned HTTP principal; no production grants or global rights.
    tables=['auth_staff_admission_invitations','auth_staff_review_intents','auth_staff_mfa_factors','auth_staff_mfa_challenges','auth_staff_mfa_proofs','auth_governance_cases','auth_governance_events','auth_rate_limit_buckets']
    for t in tables:sql(f"GRANT SELECT,INSERT,UPDATE ON `{db}`.`{t}` TO 'ase_writer'@'127.0.0.1'")
    for t in ['auth_staff_admission_invitation_operations','auth_staff_media_assets','auth_staff_media_operations','auth_staff_document_sets','auth_staff_identity_reviews','auth_staff_admission_completions']:
        sql(f"GRANT SELECT,INSERT ON `{db}`.`{t}` TO 'ase_writer'@'127.0.0.1'")
    sql(f"GRANT UPDATE(status) ON `{db}`.internal_staff TO 'ase_writer'@'127.0.0.1'")
    sql(f"GRANT UPDATE(status,revoked_at) ON `{db}`.internal_capability_delegations TO 'ase_writer'@'127.0.0.1'")
    sql(f"GRANT INSERT ON `{db}`.internal_staff TO 'ase_writer'@'127.0.0.1'")
    sql(f"GRANT UPDATE(bound_account_id,binding_credential_version,binding_reference) ON `{db}`.auth_staff_admission_cases TO 'ase_writer'@'127.0.0.1'")
    for name in ['media-key','media-operation-key','recipient-key','workflow-mfa-key']:(owned/name).write_bytes(secrets.token_bytes(32))
    (owned/'workflow-totp-secret').write_bytes(secrets.token_bytes(20))
    (owned/'applicant-password').write_text(secrets.token_urlsafe(32)+'Aa1!')
    (owned/'staff-private').mkdir(mode=0o700)
    # Private config bound to source, exact checkout, own datadir and expiry. Never a production composition.
    files=[*root.glob('modules/identity/Team/*.php'),*root.glob('modules/identity/StaffWorkflow/*.php'),*root.glob('modules/identity/StaffMedia/*.php'),*root.glob('modules/identity/StaffAdmission/*.php'),root/'modules/identity/services/InternalGovernanceService.php',root/'modules/identity/services/StaffMfaService.php',root/'scripts/ase-adm02-workflow-runtime.php',root/'scripts/ase-adm02-workflow-router.php']
    (owned/'workflow-approval.json').write_text(json.dumps({'root':str(root),'database':db,'expires':int(time.time())+7200,'source_hashes':{str(p.relative_to(root)):hashlib.sha256(p.read_bytes()).hexdigest() for p in files}}))
    check('Fresh schema installed; historical governance operations preserved plus staff review', 'STAFF_ADMISSION_REVIEW' in sql("SELECT CHECK_CLAUSE FROM information_schema.CHECK_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=%s AND CONSTRAINT_NAME='ck_auth_governance_case_operation'",(db,))[0][0])

def run(r,check,races):
    root=r['ROOT'];owned=r['owned'];sql=r['sql'];env=r['env'];request=r['request'];tokens=r['tokens'];csrf={}
    def uid():return str(uuid.uuid4())
    def cli(file,action,body):
        p=subprocess.run(['php','modules/identity/tests/'+file,action],cwd=root,env=env,input=json.dumps(body),text=True,capture_output=True)
        if p.returncode:(owned/'workflow-cli.log').write_text(p.stderr);raise AssertionError('owned CLI failed: '+action)
        v=json.loads(p.stdout)
        if not v.get('ok'):raise AssertionError('owned CLI rejected: '+action)
        return v.get('data',{})
    def call(action,b=None,actor='director'):
        return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':b or {}},headers={'X-CSRF-Token':csrf.get(actor,'')})
    def good(action,b=None,actor='director'):
        status,v,_=call(action,b,actor)
        if status!=200:raise AssertionError(f'HTTP {action} {status} {v}')
        return v
    for actor in ['director','master','advisor','outsider']:csrf[actor]=good('bootstrap',actor=actor)['csrf_token']
    check('Canonical authenticated HTTP bootstrap; no anonymous access',call('bootstrap',actor=None)[0]!=200)
    cli('StaffWorkflowFixture.php','enroll',{})
    check('Director MFA enrolled by distinct registrar through canonical ceremony',sql("SELECT COUNT(*) FROM auth_staff_mfa_factors WHERE account_id='qa_director' AND status='ACTIVE'")[0][0]==1)
    profile=dict(given_names='Persona Ficticia',first_surname='Ensayo',second_surname='',operation_state='',operation_city='')
    def prepare():return good('prepare',dict(operation_id=uid(),recipient_reference=uid(),channel='ASSISTED',proposed_category='ADVISOR',**profile))['case_id']
    cid=prepare();other=prepare()
    inv=good('issue',dict(operation_id=uid(),case_id=cid,expected_version=1,recipient_email='new_applicant@example.test'))
    message=json.loads((owned/('invitation-'+inv['invitation_id']+'.json')).read_text())
    b=dict(operation_id=uid(),case_id=cid,expected_version=1,invitation_id=inv['invitation_id'],token=message['token'])
    check('Wrong-account invitation acceptance denied',call('accept',b,'outsider')[0]!=200)
    for action in ['register','verify','login']:cli('StaffInvitationFixture.php',action,dict(actor='new',email='new_applicant@example.test'))
    tokens.update(json.loads((owned/'sessions.json').read_text()));csrf['new']=good('bootstrap',actor='new')['csrf_token']
    accepted=good('accept',b,'new');account=sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0][0]
    check('Invitation + canonical registration/email/session bind exact applicant',accepted['outcome']=='ACCOUNT_CASE_BOUND' and not sql('SELECT * FROM internal_staff WHERE account_id=%s',(account,)))
    good('correct',dict(operation_id=uid(),case_id=cid,expected_version=1,**profile),'new')
    good('confirm',dict(operation_id=uid(),case_id=cid,expected_version=2),'new');version=3
    check('Personal correction and confirmation persist without staff activation',sql('SELECT state,version FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0]==('PERSON_CONFIRMED',3))
    # Artificial 32x24 raster; no photograph or official-looking identification.
    php="$im=imagecreatetruecolor(32,24);imagefilledrectangle($im,0,0,31,23,imagecolorallocate($im,50,120,190));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
    raster=subprocess.check_output(['php','-r',php]);(owned/'synthetic.png').write_bytes(raster)
    asset_version=0
    def upload():
        nonlocal asset_version
        v=good('upload',{'metadata':dict(operation_id=uid(),case_id=cid,expected_case_version=version,expected_asset_version=asset_version,purpose='STAFF_IDENTITY_EVIDENCE',declared_mime='image/png'),'base64':base64.b64encode(raster).decode()},'new');asset_version+=1;return v['asset_id']
    front,back=upload(),upload();doc=uid()
    def submit(f=front,b=back):return good('submit',dict(operation_id=uid(),case_id=cid,expected_version=version,documents=[dict(document_id=doc,kind='INE',justification='Documento artificial para ensayo',pages=[dict(side='FRONT',asset_id=f),dict(side='BACK',asset_id=b)])],legal_reference='synthetic-staff-acceptance-v1',accepted=True),'new')
    check('Missing staff acceptance cannot submit',call('submit',dict(operation_id=uid(),case_id=cid,expected_version=version,documents=[],legal_reference='synthetic-staff-acceptance-v1',accepted=False),'new')[0]!=200)
    current=submit()['set_id']
    check('Front/back remain distinct immutable assets in one logical document',front!=back and len(json.loads(sql('SELECT manifest FROM auth_staff_document_sets WHERE set_id=%s',(current,))[0][0])[0]['pages'])==2)
    check('Cross-case document view denied',call('view',{'case_id':cid},'outsider')[0]!=200)
    check('Cross-case asset submission denied',call('submit',dict(operation_id=uid(),case_id=other,expected_version=1,documents=[],legal_reference='synthetic-staff-acceptance-v1',accepted=True),'new')[0]!=200)
    password=(owned/'fixture-password').read_text().strip()
    def intent(action='READ',actor='director',setid=None):return dict(case_id=cid,set_id=setid or current,action=action,reason='Decisión sintética del ensayo',email='qa_'+actor+'@example.test',password=password)
    for actor in ['master','advisor','new']:
        b=intent(actor=actor)
        if actor=='new':b.update(email='new_applicant@example.test',password=(owned/'applicant-password').read_text())
        check('Review denied '+actor,call('challenge',b,actor)[0]!=200)
    check('Missing MFA denies read and decision',call('decide',dict(intent_id='missing',proof_id='missing',proof='missing'))[0]!=200)
    secret=(owned/'workflow-totp-secret').read_bytes()
    def code():
        step=int(time.time())//30;digest=hmac.new(secret,struct.pack('>Q',step),hashlib.sha1).digest();off=digest[-1]&15;return str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
    def proof(action='READ',setid=None):
        ch=good('challenge',intent(action,setid=setid))
        last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
        while int(time.time())//30<=last:time.sleep(.3)
        v=good('verify',dict(challenge_id=ch['challenge_id'],challenge=ch['challenge'],code=code()))
        return dict(intent_id=ch['intent_id'],proof_id=v['proof_id'],proof=v['proof'])
    ch=good('challenge',intent());check('Invalid MFA code denied',call('verify',dict(challenge_id=ch['challenge_id'],challenge=ch['challenge'],code='invalid'))[0]!=200)
    read=good('evidence',proof());check('Director exact scoped MFA read retrieves both synthetic sides',len(read['pages'])==2)
    correction=good('decide',proof('CORRECTIONS'));check('Corrections are a versioned explicit result',correction['decision']=='CORRECTIONS')
    # Replace only back: same logical document ID; front retained, new back immutable.
    back2=upload();current=submit(front,back2)['set_id']
    check('Correction/resubmission preserves old pages and versions',sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(cid,))[0][0]==2 and front in sql('SELECT manifest FROM auth_staff_document_sets WHERE set_id=%s',(current,))[0][0])
    good('evidence',proof());accept_proof=proof('ACCEPT')
    # Stale set cannot commit even with a valid proof; rollback proof consumption.
    back3=upload();check('Evidence replacement invalidates pending approval',call('decide',accept_proof)[0]!=200)
    current=submit(front,back3)['set_id'];good('evidence',proof())
    accepted=good('decide',proof('ACCEPT'));check('Current positive human review persisted',accepted['decision']=='ACCEPT')
    check('Positive review alone creates no staff or grants',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(account,)) and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(account,)))
    admit=dict(case_id=cid,**proof('ADMIT'))
    sql('UPDATE auth_staff_admission_cases SET binding_credential_version=2 WHERE case_id=%s',(cid,))
    check('Invalid account-to-case credential binding blocks admission',call('admit',admit)[0]!=200 and not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(account,)))
    sql('UPDATE auth_staff_admission_cases SET binding_credential_version=1 WHERE case_id=%s',(cid,))
    sql("CREATE TRIGGER workflow_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='INTERNAL_GOVERNANCE_CHANGED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_audit_failure'; END IF; END")
    failed=call('admit',admit);sql('DROP TRIGGER workflow_audit_failure')
    check('Failed audit rolls back canonical registration, completion and proof',failed[0]!=200 and not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(account,)) and sql('SELECT status FROM auth_staff_mfa_proofs WHERE proof_id=%s',(admit['proof_id'],))[0][0]=='VERIFIED')
    # Independent PHP/SQL connections contend on canonical sorted account locks.
    def worker():return cli('StaffWorkflowFixture.php','admit',dict(actor='director',target=account,input=admit))
    with concurrent.futures.ThreadPoolExecutor(2) as pool:results=list(pool.map(lambda _:worker(),range(2)))
    check('Concurrent duplicate admission produces one Advisor and one completion',len(sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(account,)))==1 and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(cid,))[0][0]==1)
    races.append({'name':'canonical_duplicate_admission','independent_connections':True,'one_relationship':True,'one_completion':True})
    check('Admission does not grant privileges or appoint Master',sql('SELECT governance_class,status FROM internal_staff WHERE account_id=%s',(account,))[0]==('ADVISOR','ACTIVE') and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(account,)))
    status,team,_=request('director',path='/api/internal/team/read.php?category=ADVISOR&page=1&page_size=20&q='+account,method='GET')
    check('Admitted Advisor appears in existing canonical team read model',status==200 and any(x['account_reference']==account for x in team['items']))
    status,boot,_=request('director',path='/api/internal/team/csrf.php',method='GET')
    granted=request('director',path='/api/internal/team/capability.php',method='POST',body={'operation':'grant','target':account,'capability':'media_review_read'},headers={'X-CSRF-Token':boot['csrf_token']})
    check('Explicit grant uses existing canonical permission writer',granted[0]==200 and sql('SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status=\'ACTIVE\'',(account,))==(('media_review_read',),))
    check('Applicant cannot escalate privileges',request('new',path='/api/internal/team/capability.php',method='POST',body={'operation':'grant','target':account,'capability':'internal_advisors_manage'},headers={'X-CSRF-Token':csrf['new']})[0]!=200)
    check('Normal media endpoint remains fail closed without isolated composition','Http::handle();' in (root/'api/internal/staff-admission/workflow.php').read_text())
    master_test=subprocess.run(r['fixture']+['master_denied'],cwd=root,env=env,input='{}',capture_output=True,text=True)
    check('Canonical Master appointment actually rejects unavailable review',json.loads(master_test.stdout).get('error')=='staff_identity_review_source_unavailable')
    check('Master unavailable review source preserved','new UnavailableStaffIdentityReview()' in (root/'modules/identity/MasterGovernance/Composition.php').read_text())
    # Keep no secrets in committed evidence. Private review material remains solely in owned directory.
    (owned/'workflow-case.json').write_text(json.dumps({'case_id':cid,'account_id':account,'set_id':current}))
    check('Owned UI Director and applicant entry points respond',request('director',path='/internal/media-review/index.php?section=equipo',method='GET')[0]==200 and request('new',path='/staff-admission.php',method='GET')[0]==200)
    import importlib.util
    spec=importlib.util.spec_from_file_location('wf_ui',root/'scripts/ase-adm02-workflow-ui.py');ui=importlib.util.module_from_spec(spec);spec.loader.exec_module(ui);ui.run(r,check)
    print('Workflow HTTP + canonical SQL + browser checks complete',flush=True)
