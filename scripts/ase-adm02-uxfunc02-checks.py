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
    for actor,name in [('alma','Alma Ficticia'),('bruno','Bruno Ensayo'),('celia','Celia Prueba'),('diego','Diego Simulado'),('outsider','Elena Pendiente')]:
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
        if actor=='outsider':continue
        for action,endpoint in [('READ','evidence'),('ACCEPT','decide'),('ADMIT','admit')]:
            ch=good('challenge',dict(case_id=cid,set_id=current,action=action,reason='Revisión sintética UXFUNC02',email='qa_director@example.test',password=password))
            last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
            while int(time.time())//30<=last:time.sleep(.3)
            digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30), 'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
            proof=good('verify',dict(challenge_id=ch['challenge_id'],challenge=ch['challenge'],code=code));body=dict(intent_id=ch['intent_id'],proof_id=proof['proof_id'],proof=proof['proof'])
            if action=='ADMIT':body['case_id']=cid
            good(endpoint,body)
        check('Canonical bound profile + reviewed admission '+actor,sql('SELECT status FROM internal_staff WHERE account_id=%s',(account,))[0][0]=='ACTIVE')
    (owned/'uxfunc02-records.json').write_text(json.dumps(records))
    (owned/'demo-records.json').write_text(json.dumps({'pending_review_case':records['outsider']['case']}))
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
    browser(r,check,records,command,consumer)

def browser(r,check,records,command,consumer):
    from playwright.sync_api import sync_playwright,expect
    root,owned,sql,tokens,origin=[r[k] for k in ['ROOT','owned','sql','tokens','origin']]
    shots=root/'docs/evidence/ase-adm02-uxfunc02/screens';shots.mkdir(parents=True,exist_ok=True)
    def grants(actor):return {x[0] for x in sql("SELECT capability FROM internal_operator_grants WHERE account_id=%s AND status='ACTIVE' AND revoked_at IS NULL",(records[actor]['account'],))}
    with sync_playwright() as pw:
        b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});c.add_cookies([{'name':'__Host-mxmed_session','value':tokens['director'],'url':origin,'secure':True,'httpOnly':True,'sameSite':'Strict'}]);p=c.new_page();errors=[];p.on('pageerror',lambda e:errors.append(str(e)))
        url=origin+'/internal/media-review/index.php?section=equipo';p.goto(url)
        def select(actor):
            p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records[actor]['name'])
        def box(cap):return p.locator('#operational-matrix [data-capability="'+cap+'"]')
        def save():
            p.locator('#save-permissions').click();expect(p.locator('#confirmation')).to_be_visible()
            with p.expect_response(lambda x:'/api/internal/team/capability.php' in x.url and x.request.method=='POST'):p.locator('#accept-confirmation').click()
            expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled(timeout=20000)
            expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000)
        expect(p.locator('[data-member]')).to_have_count(3);select('alma')
        expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();expect(p.locator('#wf-given_names')).to_be_focused();expect(p.locator('#wf-given_names')).to_have_value('');p.locator('#admission-requests > summary').click();check('Add CTA opens existing empty admission workflow',True)
        expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled()
        p.locator('[data-expand="use-media"]').click();box('media_review_read').check();check('Individual edit is draft until save',not grants('alma'))
        group=p.locator('#operational-matrix [data-family="media"] [data-group-count]');group.check();box('media_review_approve').uncheck();check('Group selects children and individual off yields mixed state',group.evaluate('(e)=>e.indeterminate') and box('media_review_read').is_checked())
        save();check('Canonical SQL saves supported group subset', 'media_review_read' in grants('alma') and 'media_review_approve' not in grants('alma') and len(grants('alma'))>1)
        p.reload();select('alma');p.locator('[data-expand="use-media"]').click();expect(box('media_review_read')).to_be_checked();check('Reload matches persisted grants and consumer authorizes read',consumer('alma'))
        box('media_review_read').uncheck();save();check('Revocation enforced by real MediaReviewAuthority consumer gate',not consumer('alma') and 'media_review_read' not in grants('alma'))
        # Concurrent canonical update after draft; editor must reconcile instead of overwriting it.
        p.locator('[data-expand="use-media"]').click();box('media_review_read').check();assert command('grant',records['alma']['account'],'media_review_approve')[0]==200
        p.locator('#save-permissions').click();expect(p.locator('#permission-result-title')).to_contain_text('CONFLICTO',timeout=20000)
        check('Concurrent grant change produces conflict without silent overwrite','media_review_approve' in grants('alma') and 'media_review_read' not in grants('alma'))
        # Restore zero grants via canonical commands for Director review, not table writes.
        for cap in list(grants('alma')):assert command('revoke',records['alma']['account'],cap)[0]==200
        p.reload();select('celia');p.locator('#remove-member').click();expect(p.locator('#confirmation-description')).to_contain_text(records['celia']['name']);p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=20000)
        expect(p.locator('[data-member]')).to_have_count(2);expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva');expect(p.locator('#remove-member')).to_be_disabled()
        check('Removal updates canonical status and drops from active list',sql('SELECT status FROM internal_staff WHERE account_id=%s',(records['celia']['account'],))[0][0]=='REMOVED' and not grants('celia') and not consumer('celia'))
        check('Removed grant denied',command('grant',records['celia']['account'],'media_review_read')[0]==403)
        check('Historical Identity, admission and audit preserved',sql('SELECT COUNT(*) FROM auth_accounts WHERE account_id=%s',(records['celia']['account'],))[0][0]==1 and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE account_id=%s',(records['celia']['account'],))[0][0]==1)
        p.locator('#staff-status').select_option('REMOVED');expect(p.locator('[data-member]')).to_have_count(1);expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_disabled();p.screenshot(path=str(shots/'removed-history.png'),full_page=True)
        p.reload();expect(p.locator('#staff-status')).to_have_value('ACTIVE');expect(p.locator('[data-member]')).to_have_count(2);check('Reload defaults to exactly two active; removed available in history',True)
        p.locator('#staff-status').select_option('SUSPENDED');expect(p.locator('[data-member]')).to_have_count(1);expect(p.locator('#advisor-detail .status')).to_have_text('Suspendido');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_disabled()
        p.locator('#return-active').click();expect(p.locator('[data-member]')).to_have_count(2)
        p.locator('#staff-status').select_option('PENDING');expect(p.locator('[data-case]')).to_have_count(1);p.locator('[data-case]').click();expect(p.locator('#wf-given_names')).to_have_value('Elena Pendiente');expect(p.locator('#wf-review-state')).to_contain_text('Pendiente');check('Pending case opens existing workflow, not staff relationship',True)
        p.locator('#admission-requests > summary').click();p.locator('#staff-status').select_option('ACTIVE');select('bruno')
        expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled()
        for w,h in [(1440,900),(1366,768),(390,844)]:
            p.set_viewport_size({'width':w,'height':h});p.locator('#add-member').scroll_into_view_if_needed();check('Viewport '+str(w)+' no overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.screenshot(path=str(shots/f'chromium-{w}.png'),full_page=True,animations='disabled')
        check('Zero-grant and partial-grant ACTIVE fixtures preserved',not grants('alma') and grants('bruno')=={'media_review_read'})
        check('No browser exceptions',not errors);b.close()
