"""Focused UXFUNC02 checks. All data stays in the fresh owner-verified synthetic runner."""
import json,subprocess,uuid,time,base64,hmac,struct

def run(r,check,races):
    root,owned,sql,env,request,tokens=[r[k] for k in ['ROOT','owned','sql','env','request','tokens']]
    csrf={};uid=lambda:str(uuid.uuid4())
    def cli(file,action,body):
        p=subprocess.run(['php','modules/identity/tests/'+file,action],cwd=root,env=env,input=json.dumps(body),text=True,capture_output=True)
        if p.returncode: (owned/'uxfunc02-cli.log').write_text(p.stderr);raise AssertionError('private CLI failed '+action)
        v=json.loads(p.stdout);assert v.get('ok'),action;return v.get('data',{})
    def good(action,body=None,actor='director'):
        status,v,_=request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':body or {}},headers={'X-CSRF-Token':csrf.get(actor,'')})
        assert status==200,(action,status,v);return v
    for a in ['director','outsider']:csrf[a]=good('bootstrap',actor=a)['csrf_token']
    cli('StaffWorkflowFixture.php','enroll',{})
    secret=(owned/'workflow-totp-secret').read_bytes();password=(owned/'fixture-password').read_text().strip()
    php="$im=imagecreatetruecolor(32,24);imagefilledrectangle($im,0,0,31,23,imagecolorallocate($im,50,120,190));ob_start();imagepng($im);$b=ob_get_clean();$out=substr($b,0,8);for($i=8;$i<strlen($b);){$n=unpack('N',substr($b,$i,4))[1];if(substr($b,$i+4,4)!=='pHYs')$out.=substr($b,$i,$n+12);$i+=$n+12;}echo $out;"
    raster=subprocess.check_output(['php','-r',php]);(owned/'synthetic.png').write_bytes(raster);records={}
    for actor,name in [('alma','Alma Ficticia'),('bruno','Bruno Ensayo'),('celia','Celia Prueba'),('diego','Diego Simulado'),('outsider','Elena Pendiente'),('fernanda','Fernanda Solicitud')]:
        print('Preparing canonical synthetic admission: '+actor,flush=True)
        profile=dict(given_names=name,first_surname='Demostración',second_surname='',operation_city='',operation_state='')
        cid=good('prepare',dict(operation_id=uid(),recipient_reference=uid(),channel='ASSISTED',proposed_category='ADVISOR',**profile))['case_id']
        email=('qa_outsider' if actor=='outsider' else actor)+'@example.test'
        if actor!='outsider':
            for action in ['register','verify','login']:cli('StaffInvitationFixture.php',action,dict(actor=actor,email=email))
            tokens.update(json.loads((owned/'sessions.json').read_text()));csrf[actor]=good('bootstrap',actor=actor)['csrf_token']
        inv=good('issue',dict(operation_id=uid(),case_id=cid,expected_version=1,recipient_email=email));msg=json.loads((owned/('invitation-'+inv['invitation_id']+'.json')).read_text())
        good('accept',dict(operation_id=uid(),case_id=cid,expected_version=1,invitation_id=inv['invitation_id'],token=msg['token']),actor)
        good('confirm',dict(operation_id=uid(),case_id=cid,expected_version=1),actor)
        account,version=sql('SELECT bound_account_id,version FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0];pages=[]
        for n,side in enumerate(['FRONT','BACK']):
            a=good('upload',{'metadata':dict(operation_id=uid(),case_id=cid,expected_case_version=version,expected_asset_version=n,purpose='STAFF_IDENTITY_EVIDENCE',declared_mime='image/png'),'base64':base64.b64encode(raster).decode()},actor)
            pages.append(dict(side=side,asset_id=a['asset_id']))
        current=good('submit',dict(operation_id=uid(),case_id=cid,expected_version=version,documents=[dict(document_id=uid(),kind='INE',justification='Imagen artificial de ensayo',pages=pages)],legal_reference='synthetic-staff-acceptance-v1',accepted=True),actor)['set_id']
        records[actor]=dict(account=account,case=cid,name=name+' Demostración')
        if actor in ['outsider','fernanda']:continue
        for action,endpoint in [('READ','evidence'),('ACCEPT','decide'),('ADMIT','admit')]:
            ch=good('challenge',dict(case_id=cid,set_id=current,action=action,reason='Revisión sintética UXFUNC02',password=password))
            last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
            while int(time.time())//30<=last:time.sleep(.3)
            digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30), 'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
            proof=good('verify',dict(challenge_id=ch['challenge_id'],challenge=ch['challenge'],code=code));body=dict(intent_id=ch['intent_id'],proof_id=proof['proof_id'],proof=proof['proof'])
            if action=='ADMIT':body['case_id']=cid
            good(endpoint,body)
        check('Canonical bound profile + reviewed admission '+actor,sql('SELECT status FROM internal_staff WHERE account_id=%s',(account,))[0][0]=='ACTIVE')
    (owned/'uxfunc02-records.json').write_text(json.dumps(records))
    (owned/'demo-records.json').write_text(json.dumps({'pending_review_case':records['fernanda']['case']}))
    def teamcsrf(a):return request(a,path='/api/internal/team/csrf.php',method='GET')[1].get('csrf_token','')
    def command(op,target,cap=None,actor='director'):
        body={'operation':op,'target':target}
        if cap:body['capability']=cap
        return request(actor,path='/api/internal/team/'+('capability' if cap else 'lifecycle')+'.php',method='POST',body=body,headers={'X-CSRF-Token':teamcsrf(actor)})
    def consumer(actor):return cli('StaffWorkflowFixture.php','consumer',dict(actor=actor))['allowed']
    for actor in ['bruno','celia','diego']:assert command('grant',records[actor]['account'],'media_review_read')[0]==200
    assert consumer('diego');assert command('suspend',records['diego']['account'])[0]==200
    check('Suspended staff denied with pre-existing session',not consumer('diego'))
    check('Advisor self-grant denied',command('grant',records['alma']['account'],'media_review_read','alma')[0]==403)
    check('Undelegated Master cannot override grant authority',command('grant',records['alma']['account'],'media_review_read','master')[0]==403)
    check('Removed state not inferred; default canonical directory has three active profiles',len(request('director',path='/api/internal/team/read.php',method='GET')[1]['items'])==3)
    browser(r,check,records,command,consumer,good,csrf,secret,password)

def browser(r,check,records,command,consumer,good,csrf,secret,password):
    from playwright.sync_api import sync_playwright,expect
    root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
    shots=root/'docs/evidence/ase-adm02-uxflow03/screens';shots.mkdir(parents=True,exist_ok=True)
    def raw(action,body,actor='director'):
        return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':body},headers={'X-CSRF-Token':csrf[actor]})
    pending=records['outsider']['case'];view=good('view',{'case_id':pending});setid=view['sets'][0]['set_id']
    intent=dict(case_id=pending,set_id=setid,action='READ',reason='Revisión documental',password=password)
    check('Browser-supplied Director email rejected',raw('challenge',{**intent,'email':'qa_master@example.test'})[0]==400)
    own=dict(intent,case_id=records['fernanda']['case'],set_id=good('view',{'case_id':records['fernanda']['case']})['sets'][0]['set_id'],password=(owned/'applicant-password').read_text().strip())
    check('Applicant self-review denied',raw('challenge',own,'fernanda')[0]!=200)
    check('Cross-case applicant access denied',raw('case',{'case_id':records['alma']['case']},'fernanda')[0]!=200)
    csrf['master']=good('bootstrap',actor='master')['csrf_token']
    check('Master cannot review identity',raw('challenge',intent,'master')[0]!=200)
    check('No sensitive decision without proof',raw('decide',dict(intent_id='intent_'+'0'*32,proof_id='missing',proof='missing'))[0]!=200)
    before=sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0]
    sql("CREATE TRIGGER uxflow_audit_failure BEFORE INSERT ON platform_audit_events FOR EACH ROW BEGIN IF NEW.action='STAFF_ADMISSION_CASE_CHANGED' THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='synthetic_audit_failure'; END IF; END")
    try:
        failed=raw('prepare',dict(operation_id=str(uuid.uuid4()),recipient_reference=str(uuid.uuid4()),channel='ASSISTED',proposed_category='ADVISOR',given_names='Auditoría Ficticia',first_surname='Prueba',second_surname='',operation_city='',operation_state=''))
    finally:sql('DROP TRIGGER uxflow_audit_failure')
    check('Canonical audit failure rolls back preparation',failed[0]!=200 and sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0]==before)
    with sync_playwright() as pw:
        b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)));p.goto(origin+'/internal/media-review/index.php?section=equipo')
        def select(actor):
            p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records[actor]['name'])
        # A: approved modal structure, real saved draft, preserved values, invitation and no activation.
        expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Completa nombre')
        p.locator('#new-given_names').fill('Gabriela Ficticia');p.locator('#new-first_surname').fill('Demostración');p.locator('#new-email').fill('gabriela@example.test');p.locator('#save-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Borrador guardado')
        check('Wizard saves one canonical draft',sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0]==before+1)
        p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso 2');p.locator('#previous-onboarding').click();expect(p.locator('#new-given_names')).to_have_value('Gabriela Ficticia')
        for step in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(step))
        check('New wizard excludes permanent security and global action controls',p.locator('#onboarding input[type=password],#onboarding #wf-action,#onboarding #staff-reason').count()==0 and p.locator('#wf-reauth-email').count()==0)
        for w,h in [(1440,900),(1366,768),(390,844)]:
            p.set_viewport_size({'width':w,'height':h});check('Wizard responsive '+str(w),p.locator('#onboarding').evaluate('(e)=>e.scrollWidth<=e.clientWidth+1') and p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(shots/f'wizard-{w}.png'),animations='disabled')
        p.set_viewport_size({'width':1440,'height':900});p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada');p.locator('#cancel-onboarding').click()
        cid=sql("SELECT case_id FROM auth_staff_admission_versions WHERE given_names='Gabriela Ficticia'")[0][0]
        check('Invitation prepared without binding or staff activation',sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(cid,))[0][0] is None and sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(cid,))[0][0]==1)
        p.locator('#staff-status').select_option('PENDING');expect(p.locator('[data-case="'+cid+'"]')).to_be_visible()
        # B: contextual protected read, accept, independently admit; real password/TOTP.
        p.locator('[data-case="'+pending+'"]').click();expect(p.locator('#staff-case')).to_be_visible();expect(p.locator('[data-review-action="ACCEPT"]')).to_be_disabled();expect(p.locator('[data-review-action="ADMIT"]')).to_be_disabled()
        p.locator('[data-review-action="CORRECTIONS"]').click();expect(p.locator('#staff-reason-label')).to_be_visible();p.locator('#cancel-staff-stepup').click()
        def execute(action):
            p.locator('[data-review-action="'+action+'"]').click();expect(p.locator('#staff-stepup')).to_be_visible();expect(p.locator('#staff-reason-label')).to_be_hidden();p.locator('#staff-password').fill(password)
            with p.expect_request(lambda req:'/workflow.php' in req.url and '"action":"challenge"' in (req.post_data or '')) as req:p.locator('#staff-challenge').click()
            check('Session-derived actor at '+action,'email' not in json.loads(req.value.post_data)['input']);expect(p.locator('#staff-code-stage')).to_be_visible();expect(p.locator('#staff-password')).to_have_value('')
            last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
            while int(time.time())//30<=last:p.wait_for_timeout(300)
            digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30),'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
            p.locator('#staff-code').fill(code);p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).to_be_hidden(timeout=20000);expect(p.locator('#staff-case-status')).to_contain_text('operación confirmada')
        execute('READ');expect(p.locator('#case-pages img')).to_have_count(2);expect(p.locator('[data-review-action="ACCEPT"]')).to_be_enabled();check('Protected evidence shown only after canonical step-up',True)
        execute('ACCEPT');expect(p.locator('[data-review-action="ADMIT"]')).to_be_enabled();check('Human acceptance persists, audits and grants no staff',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(records['outsider']['account'],)) and sql("SELECT decision FROM auth_staff_identity_reviews WHERE case_id=%s ORDER BY created_at DESC LIMIT 1",(pending,))[0][0]=='ACCEPT')
        execute('ADMIT');expect(p.locator('[data-review-action="ADMIT"]')).to_be_disabled();check('Separate admission creates exactly one Advisor without grants',sql('SELECT COUNT(*) FROM internal_staff WHERE account_id=%s',(records['outsider']['account'],))[0][0]==1 and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(records['outsider']['account'],)))
        p.locator('#close-staff-case').click();p.locator('#staff-status').select_option('ACTIVE')
        # C: real versioned personal edit plus existing permission matrix and removal.
        select('alma');p.locator('#personal-data > summary').click();p.locator('#member-edit').click();expect(p.locator('#staff-profile')).to_be_visible();p.locator('#edit-operation_city').fill('Ciudad Ficticia');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile')).to_be_hidden();select('alma');p.locator('#personal-data > summary').click();expect(p.locator('#personal-data')).to_contain_text('Ciudad Ficticia')
        check('Existing profile edit persists canonical version and provenance',sql("SELECT operation_city,provenance FROM auth_staff_admission_versions WHERE case_id=%s ORDER BY version DESC LIMIT 1",(records['alma']['case'],))[0]==('Ciudad Ficticia','ADMIN_PREPARED'))
        group=p.locator('#operational-matrix [data-family="media"] [data-group-count]');expect(group).to_be_enabled();group.check();p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_approve"]').uncheck();check('Permission group and individual mixed draft preserved',group.evaluate('(e)=>e.indeterminate'))
        p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Permission save effective at actual consumer',consumer('alma'))
        p.reload();select('alma');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled();p.locator('#personal-data > summary').click();expect(p.locator('#personal-data')).to_contain_text('Ciudad Ficticia');check('Existing profile and grants reload from canonical data',True)
        p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_read"]').uncheck();p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Permission revoke still enforced',not consumer('alma'))
        select('celia');p.locator('#remove-member').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=20000);expect(p.locator('[data-member]')).to_have_count(3);p.reload();expect(p.locator('[data-member]')).to_have_count(3)
        p.locator('#staff-status').select_option('REMOVED');expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_disabled();check('Removal/filter and grant denial regression',command('grant',records['celia']['account'],'media_review_read')[0]==403)
        p.locator('#staff-status').select_option('SUSPENDED');expect(p.locator('#advisor-detail .status')).to_have_text('Suspendido');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_disabled();check('Suspended stale session still denied',not consumer('diego'))
        p.locator('#staff-status').select_option('ACTIVE');select('bruno')
        for w,h in [(1440,900),(1366,768),(390,844)]:
            p.set_viewport_size({'width':w,'height':h});check('Administration responsive '+str(w),p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(shots/f'team-{w}.png'),full_page=True)
        check('No JavaScript errors across three journeys',not errors);b.close()
    print('UXFLOW03 focused journeys complete',flush=True)
