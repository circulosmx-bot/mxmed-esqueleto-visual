import pathlib,json,subprocess,secrets,hashlib,uuid,re,time,base64,hmac,struct
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
    current=submit()['set_id']
    check('Front/back remain distinct immutable assets in one logical document',front!=back and len(json.loads(sql('SELECT manifest FROM auth_staff_document_sets WHERE set_id=%s',(current,))[0][0])[0]['pages'])==2)
    password=(owned/'fixture-password').read_text().strip()
    def intent(action='READ',actor='director',setid=None):return dict(case_id=cid,set_id=setid or current,action=action,reason='Decisión sintética del ensayo',email='qa_'+actor+'@example.test',password=password)
    secret=(owned/'workflow-totp-secret').read_bytes()
    def code():
        step=int(time.time())//30;digest=hmac.new(secret,struct.pack('>Q',step),hashlib.sha1).digest();off=digest[-1]&15;return str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
    def proof(action='READ',setid=None):
        ch=good('challenge',intent(action,setid=setid))
        last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
        while int(time.time())//30<=last:time.sleep(.3)
        v=good('verify',dict(challenge_id=ch['challenge_id'],challenge=ch['challenge'],code=code()))
        return dict(intent_id=ch['intent_id'],proof_id=v['proof_id'],proof=v['proof'])
    read=good('evidence',proof());check('Director exact scoped MFA read retrieves both synthetic sides',len(read['pages'])==2)
    correction=good('decide',proof('CORRECTIONS'));check('Corrections are a versioned explicit result',correction['decision']=='CORRECTIONS')
    # Replace only back: same logical document ID; front retained, new back immutable.
    back2=upload();current=submit(front,back2)['set_id']
    check('Correction/resubmission preserves old pages and versions',sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(cid,))[0][0]==2 and front in sql('SELECT manifest FROM auth_staff_document_sets WHERE set_id=%s',(current,))[0][0])
    good('evidence',proof())
    accepted=good('decide',proof('ACCEPT'));check('Current positive human review persisted',accepted['decision']=='ACCEPT')
    check('Positive review alone creates no staff or grants',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(account,)) and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(account,)))
    admit=dict(case_id=cid,**proof('ADMIT'))
    good('admit',admit)
    check('Admission does not grant privileges or appoint Master',sql('SELECT governance_class,status FROM internal_staff WHERE account_id=%s',(account,))[0]==('ADVISOR','ACTIVE') and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(account,)))
    status,team,_=request('director',path='/api/internal/team/read.php?category=ADVISOR&page=1&page_size=20&q='+account,method='GET')
    check('Admitted Advisor appears in existing canonical team read model',status==200 and any(x['account_reference']==account for x in team['items']))
    status,boot,_=request('director',path='/api/internal/team/csrf.php',method='GET')
    granted=request('director',path='/api/internal/team/capability.php',method='POST',body={'operation':'grant','target':account,'capability':'media_review_read'},headers={'X-CSRF-Token':boot['csrf_token']})
    check('Explicit grant uses existing canonical permission writer',granted[0]==200 and sql('SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status=\'ACTIVE\'',(account,))==(('media_review_read',),))
    # Keep no secrets in committed evidence. Private review material remains solely in owned directory.
    (owned/'workflow-case.json').write_text(json.dumps({'case_id':cid,'account_id':account,'set_id':current}))
    check('Owned UI Director and applicant entry points respond',request('director',path='/internal/media-review/index.php?section=equipo',method='GET')[0]==200 and request('new',path='/staff-admission.php',method='GET')[0]==200)
    # Additional bound applicant waiting for Director review, preserving admitted history above.
    pending=prepare()
    inv=good('issue',dict(operation_id=uid(),case_id=pending,expected_version=1,recipient_email='qa_outsider@example.test'))
    msg=json.loads((owned/('invitation-'+inv['invitation_id']+'.json')).read_text())
    good('accept',dict(operation_id=uid(),case_id=pending,expected_version=1,invitation_id=inv['invitation_id'],token=msg['token']),'outsider')
    good('confirm',dict(operation_id=uid(),case_id=pending,expected_version=1),'outsider')
    pv=sql('SELECT version FROM auth_staff_admission_cases WHERE case_id=%s',(pending,))[0][0]
    pages=[]
    for n,side in enumerate(['FRONT','BACK']):
        v=good('upload',{'metadata':dict(operation_id=uid(),case_id=pending,expected_case_version=pv,expected_asset_version=n,purpose='STAFF_IDENTITY_EVIDENCE',declared_mime='image/png'),'base64':base64.b64encode(raster).decode()},'outsider');pages.append(dict(side=side,asset_id=v['asset_id']))
    good('upload',{'metadata':dict(operation_id=uid(),case_id=pending,expected_case_version=pv,expected_asset_version=0,purpose='INTERNAL_PRESENTATION_PORTRAIT',declared_mime='image/png'),'base64':base64.b64encode(raster).decode()},'outsider')
    good('submit',dict(operation_id=uid(),case_id=pending,expected_version=pv,documents=[dict(document_id=uid(),kind='INE',justification='Documento artificial pendiente de revisión',pages=pages)],legal_reference='synthetic-staff-acceptance-v1',accepted=True),'outsider')
    (owned/'demo-records.json').write_text(json.dumps({'admitted_case':cid,'pending_review_case':pending,'draft_case':other}))
    check('Synthetic review, portrait, grouped evidence, admitted and draft records ready',True)

    targeted(r,check,account)

def targeted(r,check,account):
    from playwright.sync_api import sync_playwright,expect
    root=r['ROOT'];owned=r['owned'];sql=r['sql'];request=r['request'];tokens=r['tokens'];origin=r['origin']
    shots=root/'docs/evidence/ase-adm02-uxfunc01/screens';shots.mkdir(parents=True,exist_ok=True)
    csrf={a:request(a,path='/api/internal/team/csrf.php',method='GET')[1].get('csrf_token','') for a in ['director','master']}
    def command(op,target,actor='director',token=None):
        return request(actor,path='/api/internal/team/lifecycle.php',method='POST',body={'operation':op,'target':target},headers={'X-CSRF-Token':csrf.get(actor,'') if token is None else token})
    check('Removal rejects invalid CSRF',command('remove_advisor','qa_target',token='invalid')[0]==403)
    check('Master cannot remove an Advisor',command('remove_advisor','qa_target','master')[0]==403)
    check('Director and Master targets protected',all(command('remove_advisor',t)[0]==403 for t in ['qa_director','qa_master']))
    sql("CREATE TRIGGER uxfunc_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='INTERNAL_GOVERNANCE_CHANGED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_audit_failure'; END IF; END")
    failed=command('remove_advisor','qa_target');sql('DROP TRIGGER uxfunc_audit_failure')
    check('Audit failure rolls back removal and grant revocation',failed[0]==503 and sql("SELECT status FROM internal_staff WHERE account_id='qa_target'")[0][0]=='ACTIVE' and sql("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id='qa_target' AND status='ACTIVE'")[0][0]==1)
    with sync_playwright() as pw:
        b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(origin+'/internal/media-review/index.php?section=equipo')
        expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();expect(p.locator('#wf-given_names')).to_be_focused();expect(p.locator('#wf-given_names')).to_have_value('')
        p.locator('#wf-given_names').fill('Persona de prueba UX');p.locator('#wf-first_surname').fill('Ficticia');p.locator('#wf-new').click();expect(p.locator('#wf-status')).to_contain_text('Solicitud preparada')
        check('Visible add action creates canonical draft from empty fields',sql("SELECT COUNT(*) FROM auth_staff_admission_versions WHERE given_names='Persona de prueba UX'")[0][0]==1)
        p.once('dialog',lambda dialog:dialog.accept());p.locator('#add-member').click();expect(p.locator('#wf-given_names')).to_have_value('');expect(p.locator('#wf-cases')).to_have_value('');p.locator('#admission-requests > summary').click()
        p.locator('[data-member="'+account+'"]').click();expect(p.locator('#detail-title')).to_have_text('Persona Ficticia Ensayo')
        group=p.locator('#operational-matrix [data-family="accreditation"] [data-group-count]');expect(group).to_be_enabled();group.check();expect(p.locator('#save-permissions')).to_be_enabled();p.locator('#save-permissions').click();expect(p.locator('#confirmation')).to_be_visible();expect(p.locator('#confirmation-description')).to_contain_text('Persona Ficticia Ensayo');p.locator('#cancel-confirmation').click()
        check('Group selection stays a draft until confirmation',sql("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s AND capability IN ('physician_accreditation_review','professional_source_provision') AND status='ACTIVE'",(account,))[0][0]==0)
        p.locator('#save-permissions').click()
        with p.expect_response(lambda response:'/api/internal/team/capability.php' in response.url and response.request.method=='POST'):
            p.locator('#accept-confirmation').click()
        expect(p.locator('#operational-matrix [data-family="accreditation"] [data-group-count]')).to_be_enabled(timeout=20000)
        expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000)
        check('Group grants persisted through canonical writer',sql("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(account,))[0][0]==3)
        group=p.locator('#operational-matrix [data-family="accreditation"] [data-group-count]');expect(group).to_be_enabled();group.uncheck();p.locator('#save-permissions').click()
        with p.expect_response(lambda response:'/api/internal/team/capability.php' in response.url and response.request.method=='POST'):
            p.locator('#accept-confirmation').click()
        expect(p.locator('#operational-matrix [data-family="accreditation"] [data-group-count]')).to_be_enabled(timeout=20000)
        expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000)
        check('Group revoke persists and preserves unrelated permission',sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE'",(account,))==(('media_review_read',),))
        expect(p.locator('#operational-matrix [data-family="profiles"] [data-group-count]')).to_be_disabled();expect(p.locator('#operational-matrix [data-family="profiles"] .family-limit')).to_contain_text('no habilitadas')
        for state in ['SUSPENDED','ACTIVE']:
            p.locator('#lifecycle').click()
            with p.expect_response(lambda response:'/api/internal/team/lifecycle.php' in response.url and response.request.method=='POST'):
                p.locator('#accept-confirmation').click()
            expect(p.locator('#lifecycle')).to_be_enabled(timeout=20000)
            expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=20000)
            check('Browser canonical lifecycle '+state,sql('SELECT status FROM internal_staff WHERE account_id=%s',(account,))[0][0]==state)
        p.locator('[data-member="qa_target"]').click();expect(p.locator('#remove-member')).to_be_enabled();p.locator('#remove-member').click();expect(p.locator('#confirmation-description')).to_contain_text('qa_target');expect(p.locator('#confirmation-body')).to_contain_text('definitivamente');p.locator('#cancel-confirmation').click()
        check('Cancel definitive removal writes nothing',sql("SELECT status FROM internal_staff WHERE account_id='qa_target'")[0][0]=='ACTIVE')
        p.locator('#remove-member').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=20000);expect(p.locator('#remove-member')).to_be_disabled();expect(p.locator('#lifecycle')).to_be_disabled();expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva')
        check('Definitive removal keeps Identity and history, revokes grants',sql("SELECT s.status,a.status FROM internal_staff s JOIN auth_accounts a USING(account_id) WHERE s.account_id='qa_target'")[0]==('REMOVED','active') and sql("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id='qa_target' AND status='ACTIVE'")[0][0]==0)
        check('Removed member cannot reactivate or remove twice',all(command(op,'qa_target')[0]==403 for op in ['reactivate','remove_advisor']))
        check('Removal produces one canonical audit event',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action='INTERNAL_GOVERNANCE_CHANGED' AND JSON_UNQUOTE(JSON_EXTRACT(metadata_json,'$.producer_metadata.operation'))='remove_advisor'")[0][0]==1)
        p.locator('[data-member="'+account+'"]').click()
        for w,h in [(1366,900),(820,1180),(390,844)]:
            p.set_viewport_size({'width':w,'height':h});p.locator('#add-member').scroll_into_view_if_needed();check('Chromium viewport '+str(w)+' no horizontal overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(shots/f'chromium-{w}.png'),animations='disabled')
        check('Chromium no JavaScript exceptions',not errors);b.close()
    print('UXFUNC targeted functional checks complete',flush=True)
