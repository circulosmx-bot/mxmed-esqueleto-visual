"""PANEL06 focused browser/domain checks against the runner's owner-verified synthetic DB."""
import json,uuid,urllib.parse,base64,time,hmac,struct,subprocess

def run(r,check,records,command,consumer,good,csrf,secret,password):
 from playwright.sync_api import sync_playwright,expect
 root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
 url=origin+'/internal/media-review/index.php?section=equipo';purpose='INTERNAL_PRESENTATION_PORTRAIT';uid=lambda:str(uuid.uuid4())
 shots=root/'docs/evidence/ase-adm02-panel06/screens';shots.mkdir(parents=True,exist_ok=True);errors=[]
 def raw(action,b,actor='director'):return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':b},headers={'X-CSRF-Token':csrf.get(actor,'')})
 def team(actor='director',**params):return request(actor,path='/api/internal/team/read.php?'+urllib.parse.urlencode(params),method='GET')
 def renew_fixture_sessions():
  # Separate QA actors reauthenticate normally; never extend TTL or auto-login the product browser.
  for actor in ['director','master','alma','fernanda']:
   existing=actor in ['director','master'];email=('qa_'+actor if existing else actor)+'@example.test'
   result=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=r['env'],input=json.dumps(dict(actor=actor,email=email,existing=existing)),text=True,capture_output=True);assert result.returncode==0 and json.loads(result.stdout).get('ok'),'Canonical fixture reauthentication failed'
  tokens.update(json.loads((owned/'sessions.json').read_text()))
  for actor in ['director','master','alma','fernanda']:csrf[actor]=good('bootstrap',actor=actor)['csrf_token']
 renew_fixture_sessions()
 check('Requested email absent; inspected only owned synthetic database',sql("SELECT COUNT(*) FROM auth_accounts WHERE email_normalized NOT LIKE '%@example.test'")[0][0]==0)
 expected={'media_review_read','media_review_approve','media_review_source_download','media_review_corrected_upload','media_review_improve','media_review_request_replacement','physician_accreditation_review','professional_source_provision','provider_claim_review','provider_subscription_activate'}
 check('Exact ten editable catalog capabilities for ACTIVE Advisor',set(team(target=records['bruno']['account'])[1]['items'][0]['editable_capabilities'])==expected)
 for cap in expected-set(team(target=records['bruno']['account'])[1]['items'][0]['grants']):
  result=command('grant',records['bruno']['account'],cap);assert result[0]==200,(cap,result[0])
 check('All ten canonical grants persist',expected<=set(team(target=records['bruno']['account'])[1]['items'][0]['grants']))
 for cap in expected-{'media_review_read'}:assert command('revoke',records['bruno']['account'],cap)[0]==200
 check('Canonical individual revoke persists',set(team(target=records['bruno']['account'])[1]['items'][0]['grants'])=={'media_review_read'})
 if team(target=records['celia']['account'])[1]['items'][0]['staff_status']!='REMOVED':assert command('remove_advisor',records['celia']['account'])[0]==200
 check('Suspended and removed grant denied',all(command('grant',records[a]['account'],'media_review_read')[0]!=200 for a in ['celia','diego']))
 cid=records['bruno']['case'];c=good('case',{'case_id':cid})
 check('Changed fields reject credential edits and invalid gender/phone',all(raw('update',dict(operation_id=uid(),case_id=cid,expected_version=c['version'],changes=patch))[0]==400 for patch in [{'email':'other@example.test'},{'gender':'INFERRED'},{'contact_phone':'555'}]))
 check('Master and other applicant cannot read private profile',raw('case',{'case_id':cid},'master')[0]==403 and raw('case',{'case_id':cid},'fernanda')[0]==403)
 # Start deterministic current presentation, preserving all earlier versions.
 good('update',dict(operation_id=uid(),case_id=cid,expected_version=c['version'],changes={'gender':'UNSPECIFIED','contact_phone':'','avatar':'NONE','presentation':'PLACEHOLDER'}))
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport=dict(width=1440,height=900));p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(origin+'/public/identity/acceso.php?next='+urllib.parse.quote('/internal/media-review/index.php?section=equipo',safe=''));p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill(password);p.locator('[data-submit]').click();p.wait_for_url('**/internal/media-review/index.php?section=equipo',timeout=20000);expect(p.locator('#add-member')).to_be_enabled()
   def select(actor='bruno'):
    if p.viewport_size['width']<600:p.locator('#show-directory').click()
    p.locator('#advisor-search').fill(records[actor]['name'].split()[0]);expect(p.locator('[data-member="'+records[actor]['account']+'"]')).to_be_visible();p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records[actor]['name'])
    if p.viewport_size['width']<600:p.locator('#show-detail').click()
   def open_profile():
    select();p.locator('#personal-data').evaluate('(e)=>e.open=true');p.locator('#member-edit').click();expect(p.locator('#staff-profile')).to_be_visible()
   def dismiss(id,how):
    if how=='button':p.locator({'onboarding':'#cancel-onboarding','staff-profile':'#cancel-staff-profile','staff-case':'#close-staff-case','staff-stepup':'#cancel-staff-stepup','confirmation':'#cancel-confirmation'}[id]).click()
    elif how=='escape':p.keyboard.press('Escape')
    else:p.mouse.click(2,2)
    expect(p.locator('#'+id)).to_be_hidden()
   def reachable(id,label):
    d=p.locator('#'+id);box=d.bounding_box();check(label+' viewport bounds',box['y']>=-1 and box['y']+box['height']<=p.viewport_size['height']+1 and d.evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'))
    elements=d.locator('input:not([type=hidden]),select,textarea,button');reached=[]
    for i in range(elements.count()):
     e=elements.nth(i)
     if not e.is_visible():continue
     e.scroll_into_view_if_needed();rect=e.bounding_box();reached.append(rect['y']>=box['y']-1 and rect['y']+rect['height']<=box['y']+box['height']+1)
    check(label+' all visible fields and footer controls reachable',bool(reached) and all(reached))
    # Native trap tested at its terminal controls, including real Shift+Tab.
    p.keyboard.press('Tab');check(label+' native focus contained',d.evaluate('(e)=>e.contains(document.activeElement)'));p.keyboard.press('Shift+Tab')
   for w,h,zoom in [(1440,900,1),(1366,768,1),(390,844,1),(900,400,1),(1366,768,1.5)]:
    p.set_viewport_size(dict(width=w,height=h));p.evaluate('(z)=>document.documentElement.style.zoom=z',zoom);label=f'{engine} {w}x{h} zoom {zoom}'
    if w<600:p.locator('#show-directory').click()
    for how in ['button','escape','backdrop']:
     p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#onboarding-title').click();expect(p.locator('#onboarding')).to_be_visible();dismiss('onboarding',how)
    check(label+' wizard dismissal and background unlock',p.evaluate("document.body.style.overflow!=='hidden'"))
    p.locator('#add-member').click();p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Completa');reachable('onboarding',label+' long wizard');dismiss('onboarding','escape')
    for how in ['button','escape','backdrop']:
     open_profile();p.locator('#staff-profile-title').click();expect(p.locator('#staff-profile')).to_be_visible();dismiss('staff-profile',how)
    open_profile();reachable('staff-profile',label+' expanded profile');p.locator('#save-staff-profile').scroll_into_view_if_needed();p.screenshot(path=str(shots/f'{engine}-{w}-{h}-{zoom}-profile-footer.png'));dismiss('staff-profile','escape')
    # Contextual documents and nested correction/MFA with no proof or mutation.
    p.evaluate('(id)=>window.staffWorkflowDirectory.open(id)',records['fernanda']['case']);expect(p.locator('#staff-case')).to_be_visible();reachable('staff-case',label+' documents')
    for action in ['READ','CORRECTIONS','REJECT']:
     for how in ['button','escape','backdrop']:
      p.locator('[data-review-action="'+action+'"]').click();expect(p.locator('#staff-stepup')).to_be_visible();p.locator('#staff-stepup-title').click();reachable('staff-stepup',label+' '+action+' '+how);dismiss('staff-stepup',how);expect(p.locator('#staff-case')).to_be_visible();check(label+' nested background remains locked',p.evaluate("document.body.style.overflow==='hidden'"))
    dismiss('staff-case','backdrop');check(label+' final background unlock',p.evaluate("document.body.style.overflow!=='hidden'"))
    for how in ['button','escape']:
     p.evaluate('(id)=>window.staffWorkflowDirectory.open(id)',records['fernanda']['case']);expect(p.locator('#staff-case')).to_be_visible();dismiss('staff-case',how)
    select();p.locator('[data-expand="use-media"]').click();box=p.locator('[data-capability="media_review_approve"]');box.set_checked(not box.is_checked())
    for how in ['button','escape','backdrop']:
     p.locator('#save-permissions').click();expect(p.locator('#confirmation')).to_be_visible();reachable('confirmation',label+' grant confirmation');dismiss('confirmation',how)
    p.locator('#revert-permissions').click()
    for action in ['lifecycle','remove-member']:
     for how in ['button','escape','backdrop']:
      p.locator('#'+action).click();expect(p.locator('#confirmation')).to_be_visible();reachable('confirmation',label+' '+action);dismiss('confirmation',how)
    check(label+' no horizontal page overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   renew_fixture_sessions()
   p.evaluate("document.documentElement.style.zoom='1'");p.set_viewport_size(dict(width=1440,height=900));open_profile()
   p.locator('#personal-operation_city').fill('Sin guardar');p.once('dialog',lambda d:d.dismiss());p.keyboard.press('Escape');expect(p.locator('#staff-profile')).to_be_visible();p.once('dialog',lambda d:d.accept());p.keyboard.press('Escape');expect(p.locator('#staff-profile')).to_be_hidden();check(engine+' unsaved profile discard confirmation',True)
   open_profile();p.locator('#personal-gender').select_option('PREFER_NOT_TO_SAY');phone='+525500000006' if engine=='chromium' else '+525500000007';p.locator('#personal-contact_phone').fill(phone);p.locator('#personal-avatar').select_option('FEMININE');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');dismiss('staff-profile','escape');p.reload();select();p.locator('#personal-data').evaluate('(e)=>e.open=true');expect(p.locator('#personal-data')).to_contain_text(phone);expect(p.locator('#personal-data')).to_contain_text('Prefiero no decirlo');check(engine+' profile persists and avatar independent of gender',good('case',{'case_id':cid})['profile']['avatar']=='FEMININE')
   open_profile();p.locator('#portrait-file').set_input_files(str(owned/'synthetic.png'));expect(p.locator('#staff-profile-status')).to_contain_text('Fotografía privada guardada',timeout=20000);p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');dismiss('staff-profile','escape');p.reload();select();p.locator('#personal-data').evaluate('(e)=>e.open=true');expect(p.locator('#personal-image img')).to_be_visible();check(engine+' private optimized portrait displayed',p.locator('#personal-image img').get_attribute('src').startswith('data:image/'))
   open_profile();p.locator('#personal-presentation').select_option('AVATAR');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');dismiss('staff-profile','escape');check(engine+' reversible avatar switch retains private photo',good('media_status',{'case_id':cid,'purpose':purpose})['version']>=1)
   # Document bytes remain gated by actual password plus current MFA; no replay or role bypass.
   p.evaluate('(id)=>window.staffWorkflowDirectory.open(id)',records['fernanda']['case']);expect(p.locator('#staff-case')).to_be_visible()
   def execute_review(action,lose_ack=False):
    p.locator('[data-review-action="'+action+'"]').click();expect(p.locator('#staff-stepup')).to_be_visible();p.locator('#staff-password').fill(password);p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible(timeout=15000)
    p.locator('#staff-code').fill('xxxxxx');p.locator('#staff-execute').click();expect(p.locator('#staff-stepup-status')).to_contain_text('no autoriza');expect(p.locator('#staff-stepup')).to_be_visible()
    last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
    while int(time.time())//30<=last:p.wait_for_timeout(300)
    digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30),'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
    lost=[]
    def intercept(route):
     if route.request.post_data_json['action']=='decide':
      response=route.fetch(headers={**route.request.all_headers(),'Sec-Fetch-Site':'same-origin'});assert response.status==200;lost.append(route.request.post_data_json['input']['intent_id']);route.abort('failed')
     else:route.continue_()
    if lose_ack:p.route('**/api/internal/staff-admission/workflow.php',intercept)
    p.locator('#staff-code').fill(code);p.locator('#staff-execute').click()
    if lose_ack:
     expect(p.locator('#staff-stepup-status')).to_contain_text('Consulta el estado');dismiss('staff-stepup','escape');dismiss('staff-case','escape');p.unroute('**/api/internal/staff-admission/workflow.php',intercept);p.reload();expect(p.locator('#review-reconcile')).to_be_visible();p.locator('#review-reconcile').click();expect(p.locator('#wf-status')).to_contain_text('Operación confirmada');check(engine+' lost MFA decision receipt recovered after close/reload without replay',len(lost)==1 and sql('SELECT COUNT(*) FROM auth_staff_identity_reviews WHERE review_id=%s',(lost[0],))[0][0]==1)
     p.evaluate('(id)=>window.staffWorkflowDirectory.open(id)',records['fernanda']['case']);expect(p.locator('#staff-case')).to_be_visible()
    else:expect(p.locator('#staff-stepup')).to_be_hidden(timeout=20000);expect(p.locator('#staff-case-status')).to_contain_text('operación confirmada')
   execute_review('READ');expect(p.locator('#case-pages img')).to_have_count(2);reachable('staff-case',engine+' expanded private evidence');check(engine+' real MFA and private document read preserved',True)
   if engine=='chromium':
    execute_review('ACCEPT',True)
    for how in ['button','escape','backdrop']:
     p.locator('[data-review-action="ADMIT"]').click();expect(p.locator('#staff-stepup')).to_be_visible();reachable('staff-stepup',engine+' admission confirmation');dismiss('staff-stepup',how)
   dismiss('staff-case','escape')
   if engine=='chromium':
    open_profile();p.locator('#personal-operation_city').fill('Ciudad Sintética '+uid()[:8]);before=good('case',{'case_id':cid})['version'];lost=[]
    def lose_profile(route):
     if route.request.post_data_json['action']=='update':
      response=route.fetch(headers={**route.request.all_headers(),'Sec-Fetch-Site':'same-origin'});assert response.status==200;lost.append(response.json());route.abort('failed')
     else:route.continue_()
    p.route('**/api/internal/staff-admission/workflow.php',lose_profile);p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Consulta el resultado');dismiss('staff-profile','escape');p.unroute('**/api/internal/staff-admission/workflow.php',lose_profile);p.reload();open_profile();p.locator('#profile-reconcile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Operación guardada y confirmada');check('Personal version lost receipt reconciles exact UUID after reload',len(lost)==1 and good('case',{'case_id':cid})['version']==before+1);dismiss('staff-profile','escape')
   # Permissions UI: group toggles, mixed, confirmed save, refresh and actual consumer gate.
   select('alma');p.locator('[data-expand="use-media"]').click();g=p.locator('[data-family="media"] [data-group-count]');g.check();p.locator('[data-capability="media_review_improve"]').uncheck();check(engine+' mixed group state',g.evaluate('(e)=>e.indeterminate'));p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check(engine+' group persistence and effective read consumer',consumer('alma'));p.reload();select('alma');p.locator('[data-expand="use-media"]').click();expect(p.locator('[data-capability="media_review_read"]')).to_be_checked();p.locator('[data-capability="media_review_read"]').uncheck();p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check(engine+' individual revoke enforced by real consumer',not consumer('alma'))
   check(engine+' unavailable functions remain disabled with clear explanation',p.locator('[data-capability="profile_create"]').is_disabled() and p.locator('[data-capability="public_profile_correction_review"]').is_disabled() and 'Consumidor genérico pendiente' not in p.locator('#advisor-detail').inner_text())
   # Canonical logout, Back denial, direct denial, failed and valid ordinary product login.
   oldcookie=next(x['value'] for x in ctx.cookies(origin) if x['name']=='__Host-mxmed_session');p.locator('#team-logout').click();p.wait_for_url('**/public/identity/acceso.php?*');expect(p.locator('#login-password')).to_have_value('');check(engine+' canonical logout session revoked',request(None,path='/api/identity/index.php/current-session',method='GET',headers={'Cookie':'__Host-mxmed_session='+oldcookie})[0]==401 and all(x['name']!='__Host-mxmed_session' for x in ctx.cookies(origin)))
   p.go_back();p.goto(url);p.wait_for_url('**/public/identity/acceso.php?*');check(engine+' back and direct protected URL deny logged-out session',p.locator('#advisor-detail').count()==0)
   p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill('IncorrectaSintetica123!');p.locator('[data-submit]').click();expect(p.locator('[data-state-panel]')).to_contain_text('contraseña');check(engine+' invalid password denied',p.locator('#advisor-detail').count()==0)
   p.locator('#login-password').fill(password);p.locator('[data-submit]').click();p.wait_for_url('**/internal/media-review/index.php?section=equipo',timeout=20000);expect(p.locator('#team-role')).to_have_text('Director');check(engine+' ordinary Identity email/password login restores authorized team',True)
   p.locator('#team-logout').click();p.wait_for_url('**/public/identity/acceso.php?*');p.locator('#login-email').fill('fernanda@example.test');p.locator('#login-password').fill((owned/'applicant-password').read_text().strip());p.locator('[data-submit]').click();p.wait_for_url('**/internal/media-review/index.php?section=equipo',timeout=20000);expect(p.locator('body')).to_contain_text('no tiene acceso');check(engine+' legitimate applicant login does not grant Director access',p.locator('#advisor-detail').count()==0)
   # Each ordinary login uses an independent browser session; fixture cookies remain separate.
   b.close()
 # Portrait privacy: all reads remain exact purpose/case/version and canonical authority.
 s=good('media_status',{'case_id':cid,'purpose':purpose});c=good('case',{'case_id':cid});body=dict(case_id=cid,purpose=purpose,asset_id=s['asset_id'],asset_version=s['version'],expected_case_version=c['version'])
 check('Private portrait cross-staff and Master denied',raw('portrait',body,'fernanda')[0]==403 and raw('portrait',body,'master')[0]==403)
 check('Identity evidence cannot be read through portrait route',raw('portrait',{**body,'purpose':'STAFF_IDENTITY_EVIDENCE'})[0]==403)
 row=sql('SELECT account_id,source_actor_account_id,storage_key FROM auth_staff_media_assets WHERE asset_id=%s',(s['asset_id'],))[0]
 check('Portrait subject separated from Director provenance and outside webroot',row[0]==records['bruno']['account'] and row[1]=='qa_director' and not (root/row[2]).exists())
 check('No browser JavaScript exceptions',not errors)
