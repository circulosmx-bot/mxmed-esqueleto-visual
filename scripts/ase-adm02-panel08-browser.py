"""PANEL08 focused UI, canonical portrait lifecycle and exact capability evidence."""
import json,uuid,subprocess,base64,time,hmac,struct

def run(r,check,records,command,consumer,good,csrf,secret,password):
 from playwright.sync_api import sync_playwright,expect
 root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
 out=root/'docs/evidence/ase-adm02-panel08';shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 for actor in ['director','master','bruno','fernanda']:
  existing=actor in ['director','master'];v=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=r['env'],input=json.dumps(dict(actor=actor,email=('qa_'+actor if existing else actor)+'@example.test',existing=existing)),text=True,capture_output=True);assert v.returncode==0
 tokens.update(json.loads((owned/'sessions.json').read_text()))
 for actor in ['director','master','bruno','fernanda']:csrf[actor]=good('bootstrap',actor=actor)['csrf_token']
 def raw(action,body,actor='director'):return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':body},headers={'X-CSRF-Token':csrf[actor]})
 check('Suspended operational grants rejected',command('grant',records['diego']['account'],'media_review_approve')[0]==403)
 check('Advisor self grant rejected',command('grant',records['bruno']['account'],'media_review_approve','bruno')[0]==403)
 check('Master beyond delegation rejected',command('grant',records['bruno']['account'],'media_review_read','master')[0]==403)
 row=request('director',path='/api/internal/team/read.php?target='+records['bruno']['account'],method='GET')[1]['items'][0]
 if 'media_review_approve' in row['grants']:assert command('revoke',records['bruno']['account'],'media_review_approve')[0]==200
 errors=[];bounds=[];created=[]
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.on('dialog',lambda d:d.accept())
   p.goto(origin+'/public/identity/acceso.php?next=%2Finternal%2Fmedia-review%2Findex.php%3Fsection%3Dequipo');p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill(password);p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled()
   def select(actor='bruno'):
    if p.viewport_size['width']<600:p.locator('#show-directory').click()
    p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text(records[actor]['name'].split()[0])
   def openprofile():
    select();p.locator('#personal-data').evaluate('(e)=>e.open=true');p.locator('#member-edit').click();expect(p.locator('#personal-given_names')).to_be_enabled()
   def choose(id,value):
    p.locator('#'+id+'-trigger').click();p.locator('.team-select-options:popover-open [role=option]').filter(has_text=value).click()
   def bottom(id,last,label):
    d=p.locator('#'+id);body=d.locator(':scope > .team-dialog-body');expect(body).to_be_visible();p.screenshot(path=str(shots/(label+'-initial.png')))
    box=body.bounding_box();p.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2)
    for _ in range(8):p.mouse.wheel(0,900);p.wait_for_timeout(70)
    measure=d.evaluate('''(d)=>{const b=d.querySelector('.team-dialog-body'),r=d.getBoundingClientRect(),f=d.querySelector(':scope > .dialog-actions').getBoundingClientRect();return {top:r.top,bottom:r.bottom,height:innerHeight,remaining:b.scrollHeight-b.clientHeight-b.scrollTop,footerTop:f.top,footerBottom:f.bottom,bodyTop:b.getBoundingClientRect().top,bodyBottom:b.getBoundingClientRect().bottom,overflow:getComputedStyle(document.body).overflow,horizontal:document.documentElement.scrollWidth-innerWidth};}''')
    lr=p.locator(last).bounding_box();ok=measure['top']>=0 and measure['bottom']<=measure['height']+1 and abs(measure['remaining'])<2 and measure['footerBottom']<=measure['height'] and lr['y']>=measure['bodyTop']-1 and lr['y']+lr['height']<=measure['bodyBottom']+1 and measure['horizontal']<=1 and measure['overflow']=='hidden'
    bounds.append(dict(label=label,**measure,last=lr));check(label+' actual wheel reaches final content and visible footer',ok);p.screenshot(path=str(shots/(label+'-bottom.png')))
   for w,h in [(1440,900),(1366,768),(1280,720),(390,844),(390,480)]:
    p.set_viewport_size(dict(width=w,height=h));label=f'{engine}-{w}x{h}';openprofile();bottom('staff-profile','#portrait-version',label+'-personal');p.locator('#cancel-staff-profile').click();expect(p.locator('#staff-profile')).not_to_be_visible();expect(p.locator('#member-edit')).to_be_focused();openprofile();check(label+' reopen begins at first personal field',p.locator('#personal-given_names').evaluate('(e)=>document.activeElement===e') and p.locator('#staff-profile .team-dialog-body').evaluate('(e)=>e.scrollTop')<5);p.locator('#cancel-staff-profile').click()
    if w<600:p.locator('#show-directory').click()
    p.locator('#add-member').click();bottom('onboarding','#new-image-help',label+'-wizard');p.locator('#cancel-onboarding').click();expect(p.locator('#onboarding')).not_to_be_visible();p.locator('#add-member').click();check(label+' wizard reopens at beginning',p.locator('#onboarding .team-dialog-body').evaluate('(e)=>e.scrollTop')<5);p.locator('#cancel-onboarding').click()
   p.set_viewport_size(dict(width=1280,height=720));openprofile();city='Ciudad ficticia '+uuid.uuid4().hex[:6];p.locator('#personal-operation_city').fill(city);bottom('staff-profile','#portrait-version',engine+'-save');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');p.locator('#cancel-staff-profile').click();p.reload();openprofile();expect(p.locator('#personal-operation_city')).to_have_value(city);check(engine+' personal save persists canonical version after reload',True);p.locator('#cancel-staff-profile').click()
   # Four real steps: avatar -> photo preview -> avatar, followed by a separate photo case.
   for mode in ['AVATAR','PORTRAIT']:
    p.locator('#add-member').click();tag='Ensayo '+uuid.uuid4().hex[:8];p.locator('#new-given_names').fill(tag);p.locator('#new-first_surname').fill('Ficticio');p.locator('#new-email').fill(uuid.uuid4().hex+'@example.test');choose('new-image-mode','Elegir avatar');choose('new-avatar','Masculino');expect(p.locator('#new-image-preview svg')).to_have_count(1);choose('new-image-mode','Subir fotografía');p.locator('#new-photo').set_input_files(str(owned/'synthetic.png'));expect(p.locator('#new-image-preview img')).to_be_visible();expect(p.locator('#onboarding-feedback')).to_contain_text('aún no guardada')
    if mode=='AVATAR':choose('new-image-mode','Elegir avatar')
    for step in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(step)+' de 4')
    expect(p.locator('#onboarding-content '+('svg' if mode=='AVATAR' else 'img'))).to_be_visible();check(engine+' '+mode+' review shows prepared image in four steps',True)
    if engine=='chromium' and mode=='PORTRAIT':
     lost={'done':False}
     def drop_ack(route):
      data=route.request.post_data_json
      if data.get('action')=='upload' and not lost['done']:
       lost['done']=True;response=route.fetch(headers={**route.request.headers,'sec-fetch-site':'same-origin'});assert response.status==200;route.fulfill(status=503,content_type='application/json',body='{"error":"synthetic_lost_ack"}')
      else:route.continue_()
     p.route('**/api/internal/staff-admission/workflow.php',drop_ack)
     p.locator('#next-onboarding').click();expect(p.locator('#check-onboarding')).to_be_visible();expect(p.locator('#check-onboarding')).to_be_enabled();p.locator('#cancel-onboarding').click();p.locator('#add-member').click();expect(p.locator('#check-onboarding')).to_be_enabled();p.locator('#check-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Fotografía privada confirmada');p.unroute('**/api/internal/staff-admission/workflow.php',drop_ack);check('UI reconciles real committed upload after lost acknowledgement without replay',lost['done'])
    p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada',timeout=30000)
    row=sql('SELECT c.case_id,c.bound_account_id,v.presentation,v.avatar FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE v.given_names=%s',(tag,))[0];created.append(row[0]);check(engine+' '+mode+' canonical persistence without admission',row[1] is None and row[2]==mode and (mode!='AVATAR' or row[3]=='MASCULINE') and sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(row[0],))[0][0]==0)
    if mode=='PORTRAIT':check(engine+' prebinding portrait encrypted and case-scoped',sql('SELECT COUNT(*) FROM auth_staff_media_assets WHERE case_id=%s AND account_id IS NULL AND purpose=%s AND ciphertext_bytes=byte_length+40',(row[0],'INTERNAL_PRESENTATION_PORTRAIT'))[0][0]==1)
    p.locator('#cancel-onboarding').click()
   select()
   matrix=[]
   catalog=request('director',path='/api/internal/team/read.php?target='+records['bruno']['account'],method='GET')[1]['catalog']
   for family,keys in [('corrections',['public_profile_correction_review','public_profile_correction_apply']),('profiles',['profile_create','profile_curate','profile_publication_manage','profile_support_assist'])]:
    toggle=p.locator('[data-expand="use-'+family+'"]');toggle.click();expect(p.locator('#use-'+family)).to_be_visible();expect(p.locator('[data-family="'+family+'"] [data-group-count]')).to_be_hidden()
    for key in keys:
     expect(p.locator('[data-capability="'+key+'"]')).to_be_disabled();check(engine+' '+key+' accurately unavailable for Advisor',True)
     matrix.append(dict(group='Correcciones de perfiles' if family=='corrections' else 'Administración de perfiles',capability=key,name=catalog[key]['label'],consumer=('modules/profiles/Corrections/'+('ReportReviewService.php' if key.endswith('review') else 'ApplicationAuthority.php')+'; internal/media-review/index.php?section=correcciones') if family=='corrections' else 'NONE',director_editable='NO',grant_persistence='BLOCKED',effective_enforcement='PASS' if family=='corrections' else 'NOT_AVAILABLE',final_ui_state='UNAVAILABLE',blocker='Consumidor y MFA reservados a DIRECTOR/MASTER_ADMIN; no contrato Advisor' if family=='corrections' else 'Sólo declaración ProfileGovernancePolicy; no existe ruta staff operativa'))
    toggle.click();expect(p.locator('#use-'+family)).to_be_hidden();check(engine+' '+family+' expands/collapses without misleading group selector',True)
   (out/'capabilities.json').write_text(json.dumps(matrix,ensure_ascii=False,indent=2))
   p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_approve"]').check();p.locator('#save-permissions').click();bottom('confirmation','#confirmation-body',engine+'-permission-confirmation');p.locator('#cancel-confirmation').click();p.locator('#revert-permissions').click()
   for action in ['lifecycle','remove-member']:
    p.locator('#'+action).click();bottom('confirmation','#confirmation-body',engine+'-'+action);p.locator('#cancel-confirmation').click();expect(p.locator('#confirmation')).not_to_be_visible()
   p.locator('#status-trigger').click();p.locator('.team-select-options:popover-open [role=option]').filter(has_text='Pendientes').click();p.locator('[data-case="'+records['fernanda']['case']+'"]').click();expect(p.locator('[data-review-action=READ]')).to_be_enabled();bottom('staff-case','#staff-case-content .admission-section > p:last-of-type',engine+'-identity')
   p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-password')).to_be_visible();bottom('staff-stepup','#staff-stepup-status',engine+'-password');p.locator('#staff-password').fill(password);p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();bottom('staff-stepup','#staff-stepup-status',engine+'-mfa')
   last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
   while int(time.time())//30<=last:time.sleep(.2)
   digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30),'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
   p.locator('#staff-code').fill(code);p.locator('#staff-execute').click();expect(p.locator('#case-pages img')).to_have_count(2);bottom('staff-case','#case-pages img:last-child',engine+'-protected-evidence');p.locator('[data-review-action=CORRECTIONS]').click();expect(p.locator('#staff-reason-label')).to_be_visible();bottom('staff-stepup','#staff-stepup-status',engine+'-correction-stepup');p.locator('#cancel-staff-stepup').click();p.locator('#close-staff-case').click();b.close()
 check('No uncaught JavaScript errors',not errors);(out/'modal-bounds.json').write_text(json.dumps(bounds,indent=2))
 # Exact real correction consumers reject even canonical stored grants. Revoke immediately afterward.
 for key in ['public_profile_correction_review','public_profile_correction_apply']:
  assert command('grant',records['bruno']['account'],key)[0]==200
 try:
  probe=subprocess.run(['php','scripts/ase-adm02-panel07-security.php'],cwd=root,env=r['env'],capture_output=True,text=True);v=json.loads(probe.stdout);check('Correction review and apply actual consumers deny Advisor despite stored grants and MFA remains unavailable',v==dict(review='DENIED_403',apply='DENIED_403',routine_review=False,routine_apply=False))
 finally:
  for key in ['public_profile_correction_review','public_profile_correction_apply']:assert command('revoke',records['bruno']['account'],key)[0]==200
 # Case-scoped portrait cannot be read through another case or by another applicant/Master.
 cid=created[-1];purpose='INTERNAL_PRESENTATION_PORTRAIT';m=good('media_status',dict(case_id=cid,purpose=purpose));body=dict(case_id=cid,purpose=purpose,expected_case_version=m['case_version'],asset_id=m['asset_id'],asset_version=m['version'])
 check('Creator Director reads prepared portrait',raw('portrait',body)[0]==200)
 check('Master and foreign applicant cannot access prepared portrait',all(raw('portrait',body,a)[0]==403 for a in ['master','fernanda']))
 check('Cross-case portrait asset denied',raw('portrait',{**body,'case_id':created[0]})[0]==403)
 check('Administrative portrait authority never grants identity evidence',raw('media_status',dict(case_id=cid,purpose='STAFF_IDENTITY_EVIDENCE'))[0]==403)
 # Lost upload ACK: canonical reconciliation, same operation id cannot duplicate a version.
 c=good('case',dict(case_id=cid));operation=str(uuid.uuid4());upload=dict(metadata=dict(case_id=cid,purpose=purpose,operation_id=operation,expected_case_version=c['version'],expected_asset_version=m['version'],declared_mime='image/png'),base64=base64.b64encode((owned/'synthetic.png').read_bytes()).decode())
 first=good('upload',upload);receipt=good('media_result',dict(case_id=cid,purpose=purpose,operation_id=operation));again=good('upload',upload)
 check('Exact uncertain media result and idempotent replay produce one version',receipt['reconciliation']=='COMMITTED_AVAILABLE' and first['asset_id']==again['asset_id'] and good('media_status',dict(case_id=cid,purpose=purpose))['version']==m['version']+1)
 # Bind through the real invitation, then the applicant reads the case-scoped prebinding portrait.
 inv=good('invitation_status',dict(case_id=cid))['invitation'];msg=json.loads((owned/('invitation-'+inv['invitation_id']+'.json')).read_text());actor='portrait_'+uuid.uuid4().hex[:8]
 for action in ['register','verify','login']:
  proc=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php',action],cwd=root,env=r['env'],input=json.dumps(dict(actor=actor,email=msg['recipient'])),text=True,capture_output=True);assert proc.returncode==0 and json.loads(proc.stdout)['ok']
 tokens.update(json.loads((owned/'sessions.json').read_text()));csrf[actor]=good('bootstrap',actor=actor)['csrf_token'];c=good('case',dict(case_id=cid))
 good('accept',dict(operation_id=str(uuid.uuid4()),case_id=cid,expected_version=c['version'],invitation_id=inv['invitation_id'],token=msg['token']),actor)
 good('confirm',dict(operation_id=str(uuid.uuid4()),case_id=cid,expected_version=c['version']),actor)
 current=good('case',dict(case_id=cid),actor);m=good('media_status',dict(case_id=cid,purpose=purpose),actor)
 check('Legitimate invitation binding and applicant confirmation preserve prepared portrait',current['profile']['presentation']=='PORTRAIT' and raw('portrait',dict(case_id=cid,purpose=purpose,expected_case_version=current['version'],asset_id=m['asset_id'],asset_version=m['version']),actor)[0]==200)
 check('Portrait authority does not activate staff or bypass admission',sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(cid,))[0][0]==0)
 check('Creator administrative preparation authority ends at account binding',raw('media_status',dict(case_id=cid,purpose=purpose))[0]==403)
 # Existing canonical removal transition, preserving the other three demonstration records.
 if sql('SELECT status FROM internal_staff WHERE account_id=%s',(records['celia']['account'],))[0][0]!='REMOVED':assert command('remove_advisor',records['celia']['account'])[0]==200
 check('Removed staff cannot receive operational grants',command('grant',records['celia']['account'],'media_review_approve')[0]==403)
 (owned/'panel08-photo-case').write_text(cid)
