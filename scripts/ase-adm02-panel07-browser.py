"""Focused real-browser PANEL07 regression; fresh owned synthetic data only."""
import json,uuid,urllib.parse,time,hmac,struct,subprocess

def run(r,check,records,command,consumer,good,csrf,secret,password):
 from playwright.sync_api import sync_playwright,expect
 root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
 url=origin+'/internal/media-review/index.php?section=equipo';login=origin+'/public/identity/acceso.php?next='+urllib.parse.quote('/internal/media-review/index.php?section=equipo',safe='')
 shots=root/'docs/evidence/ase-adm02-panel07/screens';shots.mkdir(parents=True,exist_ok=True)
 for actor in ['director','master','fernanda','alma']:
  existing=actor in ['director','master'];result=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php','login'],cwd=root,env=r['env'],input=json.dumps(dict(actor=actor,email=('qa_'+actor if existing else actor)+'@example.test',existing=existing)),text=True,capture_output=True);assert result.returncode==0 and json.loads(result.stdout).get('ok')
 tokens.update(json.loads((owned/'sessions.json').read_text()))
 for actor in ['director','master','fernanda','alma']:csrf[actor]=good('bootstrap',actor=actor)['csrf_token']
 def raw(action,body,actor='director',with_csrf=True):return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':body},headers={'X-CSRF-Token':csrf[actor] if with_csrf else ''})
 def case(actor):return good('case',{'case_id':records[actor]['case']})
 def editbody(actor,**fields):c=case(actor);return dict(operation_id=str(uuid.uuid4()),case_id=c['case_id'],expected_version=c['version'],changes=fields)
 def latest(actor):return case(actor)['profile']
 def counts():return sql('SELECT (SELECT COUNT(*) FROM auth_staff_identity_reviews),(SELECT COUNT(*) FROM auth_staff_admission_completions)')[0]
 check('Owned database contains only synthetic login identifiers',sql("SELECT COUNT(*) FROM auth_accounts WHERE email_normalized NOT LIKE '%%@example.test'")[0][0]==0)
 c=case('bruno');bad=dict(case_id=c['case_id'],expected_version=c['version'],operation_id=str(uuid.uuid4()),changes={'given_names':'No autorizado'})
 check('Cross-staff personal read/write denied for Master and another applicant',all(raw(a,{'case_id':c['case_id']} if a=='case' else bad,actor)[0]==403 for actor in ['master','fernanda'] for a in ['case','update']))
 check('Verified email cannot be changed through personal writer',raw('update',{**bad,'changes':{'email_address':'fake@example.test'}})[0]==400)
 check('Personal writer still requires CSRF',raw('update',bad,with_csrf=False)[0]==403)
 blocked=['public_profile_correction_review','public_profile_correction_apply','profile_create','profile_curate','profile_publication_manage','profile_support_assist']
 row=request('director',path='/api/internal/team/read.php?target='+records['bruno']['account'],method='GET')[1]['items'][0]
 check('Six reported capabilities remain outside eligible Advisor edit contract',not set(blocked)&set(row['editable_capabilities']))
 check('Suspended Advisor cannot receive grant',command('grant',records['diego']['account'],'media_review_approve')[0]==403)
 errors=[]
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch();ctx=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':768});p=ctx.new_page();p.on('pageerror',lambda e:errors.append(str(e)))
   def signin():
    p.goto(login);expect(p.locator('#staff-synthetic-login-guide')).to_be_visible();expect(p.locator('#login-password')).to_have_value('');p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill(password);p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled()
   def select(actor='bruno'):
    if p.viewport_size['width']<600:p.locator('#show-directory').click()
    p.locator('[data-member="'+records[actor]['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text(records[actor]['name'].split()[0])
   def openprofile():
    select();p.locator('#personal-data').evaluate('(e)=>e.open=true');p.locator('#member-edit').click();expect(p.locator('#personal-given_names')).to_be_enabled()
   def choose(id,value):
    p.locator('#'+id+'-trigger').click();p.locator('.team-select-options:popover-open [role=option]').filter(has_text=value).click()
   signin();openprofile();saved='Ciudad Ficticia '+engine+' '+uuid.uuid4().hex[:6]
   p.locator('#personal-operation_city').fill(saved);choose('personal-gender','Prefiero no decirlo');p.locator('#personal-contact_phone').fill('+12025550107');choose('personal-avatar','Neutral');p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');p.keyboard.press('Escape');expect(p.locator('#member-edit')).to_be_focused();p.reload();select();p.locator('#personal-data summary').click();expect(p.locator('#personal-data')).to_contain_text(saved)
   check(engine+' personal editing uses versioned writer and persists after reload',latest('bruno')['operation_city']==saved and latest('bruno')['contact_phone']=='+12025550107' and latest('bruno')['avatar']=='NEUTRAL')
   # Real concurrent authorized edit while the first dialog keeps the old version.
   p.locator('#member-edit').click();expect(p.locator('#personal-operation_city')).to_have_value(saved);p.locator('#personal-operation_city').fill('Cambios obsoletos')
   good('update',editbody('bruno',operation_state='Estado Ficticio '+uuid.uuid4().hex[:6]));version=case('bruno')['version'];p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('El estado cambió');check(engine+' stale optimistic version rejected without overwrite',case('bruno')['version']==version and latest('bruno')['operation_city']==saved)
   p.locator('#profile-reconcile').click();expect(p.locator('#staff-profile-status')).to_contain_text('No se encontró una operación guardada');p.keyboard.press('Escape')
   # Current eligible identity case; consultation is a READ with independent real password/TOTP.
   select('alma');p.locator('#identity-evidence summary').click();before=counts();p.locator('#member-evidence').click();expect(p.locator('#staff-case')).to_be_visible();expect(p.locator('#staff-case-content')).to_contain_text(records['alma']['case']);expect(p.locator('#staff-case-content')).to_contain_text('Evidencia aceptada');p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-stepup')).to_be_visible();p.locator('#staff-password').fill(password);p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
   last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
   while int(time.time())//30<=last:time.sleep(.2)
   digest=hmac.new(secret,struct.pack('>Q',int(time.time())//30),'sha1').digest();off=digest[-1]&15;code=str((int.from_bytes(digest[off:off+4],'big')&0x7fffffff)%1000000).zfill(6)
   p.locator('#staff-code').fill(code);p.locator('#staff-execute').click();expect(p.locator('#case-pages img')).to_have_count(2);check(engine+' identity consultation reads real protected evidence without approval/admission',counts()==before);p.keyboard.press('Escape')
   select();p.locator('#identity-evidence summary').click();p.locator('#member-evidence').click();expect(p.locator('#staff-case-content')).to_contain_text('La evidencia presentada no corresponde');expect(p.locator('[data-review-action=READ]')).to_be_disabled();check(engine+' stale review displays its case/history and precise evidence restriction',True);p.keyboard.press('Escape')
   # Every affected popup actually expanded at each requested viewport, including modal bottom.
   def popup(id,label,parent=None):
    trigger=p.locator('#'+id);trigger.scroll_into_view_if_needed();trigger.click();pop=p.locator('.team-select-options:popover-open');expect(pop).to_be_visible();bounds=pop.bounding_box();view=p.evaluate('({w:innerWidth,h:innerHeight})');check(label+' expanded popup contained',bounds['x']>=0 and bounds['y']>=0 and bounds['x']+bounds['width']<=view['w']+1 and bounds['y']+bounds['height']<=view['h']+1 and pop.evaluate('(e)=>e.scrollWidth<=e.clientWidth+1'))
    check(label+' compact option text',pop.locator('button').first.evaluate('(e)=>parseFloat(getComputedStyle(e).fontSize)')<=16)
    p.keyboard.press('End');expect(pop.locator('button:not(:disabled)').last).to_be_focused();p.keyboard.press('Home');expect(pop.locator('button:not(:disabled)').first).to_be_focused();p.screenshot(path=str(shots/(label.replace(' ','-')+'.png')));p.keyboard.press('Escape');expect(pop).to_have_count(0);expect(trigger).to_be_focused()
    if parent:expect(p.locator('#'+parent)).to_be_visible()
   for w,h in [(1440,900),(1366,768),(390,844),(900,400)]:
    p.set_viewport_size({'width':w,'height':h});label=f'{engine}-{w}-{h}'
    if w<600:p.locator('#show-directory').click()
    popup('status-trigger',label+'-status');p.locator('#add-member').click();expect(p.locator('#new-channel-trigger')).to_be_enabled();popup('new-channel-trigger',label+'-channel','onboarding')
    # Proposed category is fixed by the canonical Advisor-only admission writer.
    check(label+' staff category remains correctly unavailable',p.locator('#onboarding select').evaluate_all('(a)=>a.filter(s=>s.id!=="new-channel").every(s=>s.disabled)'))
    p.keyboard.press('Escape');openprofile()
    for kind in ['gender','avatar','presentation']:popup('personal-'+kind+'-trigger',label+'-'+kind,'staff-profile')
    check(label+' no horizontal viewport overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'));p.keyboard.press('Escape')
   p.set_viewport_size({'width':1366,'height':768});p.reload();select()
   for family in ['corrections','profiles']:
    p.locator('[data-expand="use-'+family+'"]').click();expect(p.locator('[data-family="'+family+'"] [data-group-count]')).to_be_disabled();check(engine+' '+family+' exact unavailable controls',p.locator('[data-family="'+family+'"] [data-capability]:enabled').count()==0)
   # Regression on an actually applicable group and its actual consumer.
   p.locator('[data-expand="use-media"]').click();checkbox=p.locator('[data-capability=media_review_read]');checkbox.set_checked(not consumer('bruno'));p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS');check(engine+' applicable consumer follows persisted grant change',consumer('bruno')==checkbox.is_checked());p.locator('[data-expand="use-media"]').click();checkbox.set_checked(not checkbox.is_checked());p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS')
   # Canonical logout/login with same service; no shortcut or fixture cookie replaces this test.
   expect(p.locator('#pending-permissions')).to_have_text('Sin cambios pendientes.');old=ctx.cookies();p.locator('#team-logout').click();p.wait_for_url('**/public/identity/acceso.php?next=*');expect(p.locator('#login-password')).to_have_value('');p.goto(url);p.wait_for_url('**/public/identity/acceso.php?next=*');p.locator('#login-email').fill('qa_director@example.test');p.locator('#login-password').fill('WrongSyntheticPassword_123');p.locator('[data-submit]').click();expect(p.locator('[data-state-title]')).not_to_be_empty();check(engine+' protected route denied and incorrect password fails', '/acceso.php' in p.url)
   p.locator('#login-password').fill(password);p.locator('[data-submit]').click();p.wait_for_url('**/index.php?section=equipo');expect(p.locator('#add-member')).to_be_enabled();check(engine+' canonical login after logout succeeds without recreating environment',True)
   # Revoke only this browser's session, leaving the old UI, to test expired-session action recovery.
   select();p.locator('#personal-data').evaluate('(e)=>e.open=true');session=ctx.request.get(origin+'/api/identity/index.php/current-session').json();resp=ctx.request.post(origin+'/api/identity/index.php/logout',data={'csrf_token':session['csrf_token']},headers={'Origin':origin,'Sec-Fetch-Site':'same-origin'});assert resp.status==200
   p.locator('#member-edit').click();p.wait_for_url('**/public/identity/acceso.php?next=*&reason=session_expired');check(engine+' invalidated session action leads to legitimate login, no silent renewal',True)
   b.close()
 check('No unhandled JavaScript errors on corrected journeys',not errors)
 check('Canonical personal/evidence audit records present',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action LIKE 'STAFF_%%'")[0][0]>0)
