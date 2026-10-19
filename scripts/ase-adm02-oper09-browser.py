"""Focused OPER09 browser evidence. All identities and documents are fictional."""
import json,time,subprocess,importlib.util,hashlib,pathlib
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('oper09_review',ROOT/'scripts/ase-adm02-oper09-review.py');review=importlib.util.module_from_spec(spec);spec.loader.exec_module(review)

def wf(page,action,body=None):
 return page.evaluate('''async ([action,input])=>{const url='/api/internal/staff-admission/workflow.php';const b=await(await fetch(url,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();const r=await fetch(url,{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({action,input})});return {status:r.status,body:await r.json()};}''',[action,body or {}])

def selected(p,record):
 if p.evaluate('innerWidth')<600 and p.locator('#show-directory').is_visible():p.locator('#show-directory').click()
 p.locator('[data-member="'+record['account']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Irene')

def open_case(p,record):
 status_filter(p,'Pendientes');p.locator('[data-case="'+record['case']+'"]').click();expect(p.locator('#staff-case')).to_be_visible()

def review_action(p,action,owned,sql):
 p.locator('[data-review-action='+action+']').click();expect(p.locator('#staff-password')).to_be_visible()
 p.locator('#staff-password').fill((owned/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible()
 last=sql("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
 while last is not None and int(time.time())//30<=last:p.wait_for_timeout(300)
 p.locator('#staff-code').fill(review.totp(owned));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=20000)
 expect(p.locator('#staff-case-status')).to_contain_text('operación confirmada')

def run(stage,r,check,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']]
 out=r.get('OUT',ROOT/'docs/evidence/ase-adm02-oper09');out.mkdir(parents=True,exist_ok=True)
 recordfile=owned/'oper09-advisor.json'
 record=json.loads(recordfile.read_text()) if recordfile.exists() else {'email':'oper09_advisor@example.test','name':'Irene Ficticia Oper09'}
 def save():recordfile.write_text(json.dumps(record))
 with sync_playwright() as pw:
  b,c,p=review.native_browser(pw)
  review.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip());expect(p.locator('#add-member')).to_be_enabled()
  if stage=='admission':
   if not record.get('case'):
    p.locator('#add-member').click();p.locator('#new-given_names').fill('Irene Ficticia');p.locator('#new-first_surname').fill('Oper09');p.locator('#new-email').fill(record['email'])
    for step in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(step))
    p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada',timeout=20000)
    record['case']=sql("SELECT c.case_id FROM auth_staff_admission_cases c JOIN auth_staff_admission_versions v ON v.case_id=c.case_id AND v.version=c.version WHERE v.first_surname='Oper09'")[0][0];save();p.locator('#cancel-onboarding').click()
   check('Director canonical login and four-step UI create exact invitation',bool(record['case']))
   if not record.get('account'):
    result=cli('modules/identity/tests/StaffInvitationFixture.php','register',{'actor':'oper09','email':record['email']});record['account']=result['account_id'];save()
   if not record.get('verified'):
    cli('modules/identity/tests/StaffInvitationFixture.php','verify',{'actor':'oper09','email':record['email']});record['verified']=True;save()
   check('New canonical Identity registration and verified synthetic email',sql('SELECT email_verified_at IS NOT NULL FROM auth_accounts WHERE account_id=%s',(record['account'],))[0][0]==1)
   ac=b.new_context(ignore_https_errors=True);a=ac.new_page();review.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip(),'/staff-admission.php');expect(a.locator('#wf-applicant')).to_be_visible()
   if not sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(record['case'],))[0][0]:
    inv=sql('SELECT invitation_id FROM auth_staff_admission_invitations WHERE case_id=%s',(record['case'],))[0][0];msg=json.loads((owned/('invitation-'+inv+'.json')).read_text())
    a.get_by_text('Aceptar invitación',exact=True).click();a.locator('#wf-inv-case').fill(record['case']);a.locator('#wf-inv-id').fill(inv);a.locator('#wf-token').fill(msg['token']);a.locator('#wf-accept-invite').click();expect(a.locator('#wf-given_names')).to_have_value('Irene Ficticia')
   else:a.locator('#wf-cases').select_option(record['case']);expect(a.locator('#wf-given_names')).to_have_value('Irene Ficticia')
   check('Independent applicant login binds intended invitation in UI',sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(record['case'],))[0][0]==record['account'])
   if not record.get('confirmed'):
    a.locator('#wf-operation_city').fill('Ciudad Sintética');a.locator('#wf-save').click();expect(a.locator('#wf-status')).to_have_text('Solicitud consultada.');a.wait_for_timeout(300)
    a.locator('#wf-confirm').click();a.wait_for_timeout(500);record['confirmed']=True;save()
   check('Applicant correction and confirmation use canonical version writer',sql('SELECT provenance FROM auth_staff_admission_versions WHERE case_id=%s ORDER BY version DESC LIMIT 1',(record['case'],))[0][0] in ['APPLICANT_CONFIRMED','APPLICANT_CORRECTED'])
   if not sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(record['case'],))[0][0]:
    a.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-justification').fill('Dos imágenes artificiales; sin documento personal');a.locator('#wf-add-document').click();expect(a.locator('#wf-documents')).to_contain_text('2 cara(s)')
    a.locator('#wf-consent').check();a.locator('#wf-submit').click();a.wait_for_timeout(700)
   check('Applicant submits synthetic two-sided identity evidence through UI',sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(record['case'],))[0][0]==1)
   open_case(p,record)
   if not sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(record['case'],))[0][0]:
    review_action(p,'READ',owned,sql);expect(p.locator('#case-pages img')).to_have_count(2);check('Director actually reads protected evidence with password and MFA',True)
    (out/'screens').mkdir(exist_ok=True);p.screenshot(path=str(out/'screens/identity-read.png'))
    review_action(p,'ACCEPT',owned,sql);check('Director accepts current identity evidence with independent MFA proof',True)
    review_action(p,'ADMIT',owned,sql)
   check('Canonical admission creates exactly one active Advisor relationship',sql("SELECT COUNT(*) FROM internal_staff WHERE account_id=%s AND governance_class='ADVISOR' AND status='ACTIVE'",(record['account'],))[0][0]==1)
   check('Canonical admission audit retained',sql("SELECT COUNT(*) FROM platform_audit_events WHERE action LIKE '%%STAFF%%'")[0][0]>0)
   record['admitted']=True;save();ac.close()
  elif stage=='permissions':permissions(p,b,r,record,check,out,cli)
  elif stage=='modals':modals(p,c,pw,r,record,check,out)
  b.close()

def logout(page):
 return page.evaluate('''async()=>{const b=await(await fetch('/api/internal/staff-admission/workflow.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();const r=await fetch('/api/identity/index.php/logout',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({csrf_token:b.csrf_token})});return {status:r.status,body:await r.json()};}''')

def fetch_status(page,path):return page.evaluate('async path=>(await fetch(path,{cache:"no-store"})).status',path)

def permissions(p,b,r,record,check,out,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];selected(p,record)
 def grant(enabled):
  if not p.locator('#use-media').is_visible():p.locator('[data-expand="use-media"]').click()
  box=p.locator('[data-capability="media_review_read"]')
  if box.is_checked()!=enabled:
   box.set_checked(enabled);p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=15000)
  p.reload();expect(p.locator('#add-member')).to_be_enabled();selected(p,record)
  check('Director UI persists '+('grant' if enabled else 'revoke'),bool(sql("SELECT COUNT(*) FROM internal_operator_grants WHERE account_id=%s AND capability='media_review_read' AND status='ACTIVE'",(record['account'],))[0][0])==enabled)
 grant(True)
 ac=b.new_context(ignore_https_errors=True);a=ac.new_page()
 a.goto(origin+'/public/identity/acceso.php');a.locator('#login-email').fill(record['email']);a.locator('#login-password').fill('Wrong-synthetic-password!')
 with a.expect_response('**/api/identity/index.php/login') as denial:a.locator('[data-submit]').click()
 check('Incorrect Advisor credentials denied by Identity HTTP',denial.value.status in [401,403,422])
 review.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR)
 expect(a.locator('#batch-cards [data-batch-id]')).to_have_count(1);a.locator('#batch-cards [data-batch-id]').click();a.get_by_role('button',name='Ver imagen de Perfil ficticio').click();expect(a.locator('#detail-image')).to_be_visible()
 check('Independent Advisor operates existing Media UI and receives real REVIEW image',a.locator('#detail-image').evaluate('(e)=>e.complete&&e.naturalWidth>0'))
 media=json.loads((owned/'oper09-media.json').read_text());sid=media['submission_id']
 read='/api/internal/media-review/review-image.php?submission_id='+sid
 check('Real authorized image consumer ALLOW',fetch_status(a,read)==200)
 check('Ungranted original download consumer DENY',fetch_status(a,'/api/internal/media-review/source-download.php?submission_id='+sid)==403)
 (out/'screens').mkdir(exist_ok=True);a.screenshot(path=str(out/'screens/advisor-real-consumer.png'))
 check('Advisor has no Director directory or cross-staff private read',all(fetch_status(a,url)==403 for url in ['/internal/media-review/index.php?section=equipo','/api/internal/team/read.php?target=qa_master','/api/internal/team/read.php?target=qa_target']))
 check('Advisor cannot open Director identity review challenge',wf(a,'challenge',{'case_id':record['case'],'set_id':'not-authorized','action':'READ','reason':'synthetic negative','password':(owned/'applicant-password').read_text().strip()})['status']==403)
 check('Advisor has no Master governance or financial entry in isolated composition',all(fetch_status(a,url) in [403,404] for url in ['/api/internal/team/master.php','/api/internal/team/read.php?target=qa_master&section=commercial']))
 denied=a.evaluate('''async target=>{const b=await(await fetch('/api/internal/staff-admission/workflow.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'bootstrap',input:{}})})).json();const r=await fetch('/api/internal/team/capability.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':b.csrf_token},body:JSON.stringify({operation:'grant',target,capability:'media_review_source_download'})});return r.status;}''',record['account'])
 check('Advisor self-grant denied by canonical mutation boundary',denied==403)
 cookies=ac.cookies();grant(False);check('Grant revocation enforced on unchanged existing Advisor session',ac.cookies()==cookies and fetch_status(a,read)==403)
 grant(True);check('Explicit UI regrant restores the real consumer',fetch_status(a,read)==200)
 audit_before=sql('SELECT COUNT(*) FROM platform_audit_events')[0][0]
 p.locator('#lifecycle').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=15000)
 check('Canonical suspension denies existing Advisor session',sql('SELECT status FROM internal_staff WHERE account_id=%s',(record['account'],))[0][0]=='SUSPENDED' and fetch_status(a,read)==403)
 check('Suspension preserves admission and prior audit',sql('SELECT COUNT(*) FROM auth_staff_admission_completions WHERE case_id=%s',(record['case'],))[0][0]==1 and sql('SELECT COUNT(*) FROM platform_audit_events')[0][0]>audit_before)
 status_filter(p,'Suspendidos');selected(p,record);p.locator('#lifecycle').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIO DE ACCESO CONFIRMADO',timeout=15000)
 check('Explicit canonical UI reactivation leaves useful ACTIVE demonstration',fetch_status(a,read)==200)
 result=logout(a);check('Advisor independent canonical logout succeeds',result['status']==200 and result['body']['state']=='LOGOUT_SUCCESS')
 ac.add_cookies(cookies);check('Replaying revoked Advisor session is server denied',fetch_status(a,read)==403)
 check('Advisor logout preserves independent Director session',fetch_status(p,'/api/internal/team/read.php')==200)
 ac.close()
 cli('modules/identity/tests/StaffInvitationFixture.php','login',{'actor':'oper09','email':record['email']})
 service=cli('scripts/ase-adm02-oper09-security.php');check('Actual canonical Master and commercial services deny ungranted Advisor',service=={'provider_subscription_without_grant':'DENIED_BY_CANONICAL_AUTHORITY','master_delegation':'DENIED_BY_CANONICAL_AUTHORITY','only_explicit_media_read_grant':True});(out/'security-services.json').write_text(json.dumps(service,indent=2))
 (out/'operational-result.json').write_text(json.dumps({'account':record['account'],'case':record['case'],'capability':'media_review_read','consumer':'/api/internal/media-review/review-image.php','allow':200,'ungranted_source_download':403,'revoked_grant':403,'suspended':403,'revoked_session':403,'final_staff_status':'ACTIVE','audit_event_count':sql('SELECT COUNT(*) FROM platform_audit_events')[0][0],'synthetic':True},indent=2))


def status_filter(p,label):
 p.locator('#status-trigger').click();p.locator('.team-select-options:popover-open [role=option]').filter(has_text=label).click()

MEASURE='''d=>{const b=d.querySelector(':scope > .team-dialog-body'),f=d.querySelector(':scope > .dialog-actions'),v=visualViewport;const rect=e=>{const r=e.getBoundingClientRect();return {top:r.top,bottom:r.bottom,left:r.left,right:r.right,height:r.height,width:r.width};};return {dialog:rect(d),footer:rect(f),body:rect(b),viewport:{width:innerWidth,height:innerHeight,visualHeight:v.height,visualTop:v.offsetTop,visualBottom:v.offsetTop+v.height,outerHeight,outerWidth,screenX,screenY,screenAvailableHeight:screen.availHeight,screenAvailableTop:screen.availTop,dpr:devicePixelRatio,cssZoom:getComputedStyle(document.documentElement).zoom},clientHeight:b.clientHeight,scrollHeight:b.scrollHeight,scrollTop:b.scrollTop,remaining:b.scrollHeight-b.clientHeight-b.scrollTop,ancestors:[d,d.parentElement,document.documentElement].map(e=>({tag:e.tagName,overflow:getComputedStyle(e).overflow,position:getComputedStyle(e).position})),actions:[...f.querySelectorAll('button')].filter(e=>!e.hidden&&!e.disabled&&e.getClientRects().length).map(e=>{const r=e.getBoundingClientRect(),hit=document.elementFromPoint(r.x+r.width/2,r.y+r.height/2);return {id:e.id,rect:rect(e),hit:!!hit&&(hit===e||e.contains(hit))};})};}'''

def bottom(p,id,last,label,shot=None):
 d=p.locator('#'+id);body=d.locator(':scope > .team-dialog-body');expect(body).to_be_visible();box=body.bounding_box();p.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2)
 for _ in range(8):p.mouse.wheel(0,800);p.wait_for_timeout(60)
 data=d.evaluate(MEASURE);lr=p.locator(last).bounding_box()
 assert abs(data['remaining'])<2,label+' wheel does not reach content end'
 assert data['dialog']['top']>=data['viewport']['visualTop']-1 and data['dialog']['bottom']<=data['viewport']['visualBottom']+1,label+' dialog exceeds visible viewport'
 assert lr and lr['y']>=data['body']['top']-1 and lr['y']+lr['height']<=data['body']['bottom']+1,label+' final field inaccessible'
 assert data['actions'] and all(x['hit'] and x['rect']['bottom']<=data['viewport']['visualBottom']+1 for x in data['actions']),label+' footer hit test failed'
 # Keyboard scroll is performed on the existing focusable content region, never scrollTop assignment.
 body.press('Home');p.wait_for_timeout(250);body.press('PageDown');p.wait_for_timeout(250);body.press('End');p.wait_for_timeout(500)
 assert abs(body.evaluate('e=>e.scrollHeight-e.clientHeight-e.scrollTop'))<2,label+' End does not reach end'
 reached=set()
 for _ in range(65):
  p.keyboard.press('Tab');active=p.evaluate('document.activeElement.id');reached.add(active)
  if all(x['id'] in reached for x in data['actions']):break
 assert all(x['id'] in reached for x in data['actions']),label+' Tab cannot reach footer'
 # Tab naturally may bring earlier fields into view; wheel again for final screenshot.
 p.mouse.move(box['x']+box['width']/2,box['y']+box['height']/2);p.mouse.wheel(0,10000);p.wait_for_timeout(150)
 if shot:p.screenshot(path=str(shot))
 return {'label':label,'wheel':data,'keyboard_end':True,'tab_footer':True}

def personal(p,record=None):
 if record:selected(p,record)
 if p.locator('#personal-data').get_attribute('open') is None:p.locator('#personal-data > summary').click()
 p.locator('#member-edit').click();expect(p.locator('#personal-given_names')).to_be_enabled()

def delivered_modal(p,ctx,owned):
 record=json.loads((owned/'oper09-advisor.json').read_text());personal(p)
 result=bottom(p,'staff-profile','#portrait-version','delivered-native',owned/'oper09-delivered-footer.png')
 cdp=ctx.new_cdp_session(p);native=cdp.send('Browser.getWindowForTarget');cdp.detach();v=result['wheel']['viewport'];bounds=native['bounds']
 assert bounds['top']>=v['screenAvailableTop'] and bounds['top']+bounds['height']<=v['screenAvailableTop']+v['screenAvailableHeight'],'Native window outside physical work area'
 assert v['outerHeight']>=v['height'] and v['height']<v['screenAvailableHeight'],'Fixed emulation obscures native client area'
 p.locator('#cancel-staff-profile').click();personal(p);bottom(p,'staff-profile','#portrait-version','delivered-reopen');p.locator('#cancel-staff-profile').click()
 result['native_window']=native;result['cancel_and_reopen']=True
 return result

def modals(p,c,pw,r,record,check,out):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];shots=out/'screens';shots.mkdir(exist_ok=True);results=[]
 # Reproduce the historical launcher against this exact full form and account.
 old=pw.chromium.launch(headless=False);oldctx=old.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});op=oldctx.new_page()
 review.login(op,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip());personal(op,record)
 baseline=bottom(op,'staff-profile','#portrait-version','historical-emulated-900',shots/'historical-emulated-footer.png')
 cd=oldctx.new_cdp_session(op);baseline['native_window']=cd.send('Browser.getWindowForTarget');cd.detach();old.close()
 v=baseline['wheel']['viewport'];w=baseline['native_window']['bounds'];baseline['physical_overflow']=w['top']+w['height']-(v['screenAvailableTop']+v['screenAvailableHeight'])
 (out/'modal-root-cause.json').write_text(json.dumps(baseline,indent=2))
 check('Historical fixed viewport reproduces physical content overflow despite DOM-visible footer',v['height']>v['outerHeight'] or baseline['physical_overflow']>0)
 selected(p,record);p.locator('#personal-data').evaluate('e=>e.open=true')
 result=delivered_modal(p,c,owned);results.append(result);check('Actual fitted native Chromium window reaches footer with wheel keyboard and hit-test',True)
 # Save through the exact headed preview and verify authoritative readback.
 background_restore(p,check)
 personal(p);city='Ciudad Sintética OPER09 Sur' if p.locator('#personal-operation_city').input_value()=='Ciudad Sintética OPER09 Norte' else 'Ciudad Sintética OPER09 Norte';p.locator('#personal-operation_city').fill(city);results.append(bottom(p,'staff-profile','#portrait-version','native-save',shots/'native-save-footer.png'));p.locator('#save-staff-profile').click();expect(p.locator('#staff-profile-status')).to_contain_text('Nueva versión personal guardada');p.locator('#cancel-staff-profile').click();p.reload();personal(p,record);expect(p.locator('#personal-operation_city')).to_have_value(city);p.locator('#cancel-staff-profile').click();check('Actual headed Chromium bottom Save persists after reload',True)
 prepare_pending(p,pw,r,check)
 for engine in ['chromium','webkit']:
  browser=getattr(pw,engine).launch();context=browser.new_context(ignore_https_errors=True);page=context.new_page();page.on('dialog',lambda d:d.accept())
  review.login(page,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip())
  for width,height in [(1440,900),(1366,768),(1280,720),(390,844),(390,600)]:
   page.set_viewport_size({'width':width,'height':height});label=f'{engine}-{width}x{height}';personal(page,record)
   results.append(bottom(page,'staff-profile','#portrait-version',label+'-personal',shots/(label+'-personal.png')));page.locator('#cancel-staff-profile').click();personal(page);results.append(bottom(page,'staff-profile','#portrait-version',label+'-reopen'));page.locator('#cancel-staff-profile').click()
   if width<600:page.locator('#show-directory').click()
   page.locator('#add-member').click();results.append(bottom(page,'onboarding','#new-image-help',label+'-wizard',shots/(label+'-wizard.png')));page.locator('#cancel-onboarding').click()
   check(label+' personal/portrait and wizard wheel PageDown End Tab footer cancel/reopen',True)
  avatar_modal(page,record,check,results,shots,engine)
  identity_modals(page,r,check,results,shots,engine)
  browser.close()
 # Verify served bytes against this worktree (new origin and new profiles, no stale cache).
 asset_hashes={}
 for file in ['assets/js/team-dialogs.js','assets/js/staff-personal.js','assets/js/staff-workflow.js','assets/css/internal-team-admission.css']:
  response=c.request.get(origin+'/'+file);digest=hashlib.sha256(response.body()).hexdigest();check('Exact served asset '+file,response.status==200 and digest==hashlib.sha256((ROOT/file).read_bytes()).hexdigest());asset_hashes[file]=digest
 (out/'modal-bounds.json').write_text(json.dumps(results,indent=2));(out/'served-assets.json').write_text(json.dumps(asset_hashes,indent=2))


def background_restore(p,check):
 p.locator('#member-edit').scroll_into_view_if_needed();before=p.evaluate('({x:scrollX,y:scrollY,overflow:getComputedStyle(document.body).overflow})')
 personal(p);bottom(p,'staff-profile','#portrait-version','background-locked');p.locator('#cancel-staff-profile').click();p.wait_for_timeout(150)
 after=p.evaluate('({x:scrollX,y:scrollY,overflow:getComputedStyle(document.body).overflow})')
 check('Closing full modal restores prior background position and overflow',before==after)
 p.mouse.move(8,250);p.mouse.wheel(0,500 if before['y']==0 else -500);p.wait_for_timeout(200)
 check('Background wheel scrolling works again after dismissal',p.evaluate('scrollY')!=before['y'])

def prepare_pending(p,pw,r,check):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']];file=owned/'oper09-pending.json'
 advisor=json.loads((owned/'oper09-advisor.json').read_text());ab=pw.chromium.launch();ac=ab.new_context(ignore_https_errors=True);ap=ac.new_page()
 review.login(ap,origin,advisor['email'],(owned/'applicant-password').read_text().strip(),'/staff-admission.php');expect(ap.locator('#wf-cases option[value="'+advisor['case']+'"]').first).to_have_count(1);ap.locator('#wf-cases').select_option(advisor['case']);expect(ap.locator('#wf-given_names')).to_have_value('Irene Ficticia')
 if sql('SELECT state FROM auth_staff_admission_cases WHERE case_id=%s',(advisor['case'],))[0][0]!='PERSON_CONFIRMED':ap.locator('#wf-confirm').click();ap.wait_for_timeout(500)
 check('Advisor personally confirms edited version; original admission remains historical',sql('SELECT state FROM auth_staff_admission_cases WHERE case_id=%s',(advisor['case'],))[0][0]=='PERSON_CONFIRMED')
 import uuid
 version=sql('SELECT version FROM auth_staff_admission_cases WHERE case_id=%s',(advisor['case'],))[0][0]
 denial=wf(ap,'submit',{'operation_id':str(uuid.uuid4()),'case_id':advisor['case'],'expected_version':version,'documents':[],'legal_reference':'synthetic-staff-acceptance-v1','accepted':True})
 check('Existing post-admission resubmission boundary preserved without bypass',denial['status']==409)
 ab.close()
 record=json.loads(file.read_text()) if file.exists() else {'email':'qa_outsider@example.test'}
 if not record.get('case'):
  p.locator('#add-member').click();p.locator('#new-given_names').fill('Julia Ficticia');p.locator('#new-first_surname').fill('Pendiente Oper09');p.locator('#new-email').fill(record['email'])
  for step in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(step))
  p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada',timeout=15000)
  record['case']=sql("SELECT case_id FROM auth_staff_admission_versions WHERE first_surname='Pendiente Oper09'")[0][0];file.write_text(json.dumps(record));p.locator('#cancel-onboarding').click()
 if not sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(record['case'],))[0][0]:
  b=pw.chromium.launch();c=b.new_context(ignore_https_errors=True);a=c.new_page();review.login(a,origin,record['email'],(owned/'fixture-password').read_text().strip(),'/staff-admission.php');expect(a.locator('#wf-applicant')).to_be_visible()
  if not sql('SELECT bound_account_id FROM auth_staff_admission_cases WHERE case_id=%s',(record['case'],))[0][0]:
   inv=sql('SELECT invitation_id FROM auth_staff_admission_invitations WHERE case_id=%s',(record['case'],))[0][0];msg=json.loads((owned/('invitation-'+inv+'.json')).read_text())
   a.get_by_text('Aceptar invitación',exact=True).click();a.locator('#wf-inv-case').fill(record['case']);a.locator('#wf-inv-id').fill(inv);a.locator('#wf-token').fill(msg['token']);a.locator('#wf-accept-invite').click();expect(a.locator('#wf-given_names')).to_have_value('Julia Ficticia')
  else:a.locator('#wf-cases').select_option(record['case']);expect(a.locator('#wf-given_names')).to_have_value('Julia Ficticia')
  if sql('SELECT state FROM auth_staff_admission_cases WHERE case_id=%s',(record['case'],))[0][0]!='PERSON_CONFIRMED':a.locator('#wf-confirm').click();a.wait_for_timeout(500)
  a.locator('#wf-front').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-back').set_input_files(str(owned/'synthetic.png'));a.locator('#wf-add-document').click();expect(a.locator('#wf-documents')).to_contain_text('2 cara(s)');a.locator('#wf-consent').check();a.locator('#wf-submit').click();a.wait_for_timeout(700);b.close()
 check('Pending-only canonical case supplies protected evidence modal; no second Advisor',sql('SELECT COUNT(*) FROM auth_staff_document_sets WHERE case_id=%s',(record['case'],))[0][0]==1 and sql("SELECT COUNT(*) FROM internal_staff WHERE governance_class='ADVISOR'")[0][0]==1)


def identity_modals(p,r,check,results,shots,engine):
 record=json.loads((r['owned']/'oper09-pending.json').read_text())
 for w,h in [(1280,720),(390,600)]:
  p.set_viewport_size({'width':w,'height':h});
  if w<600 and p.locator('#show-directory').is_visible():p.locator('#show-directory').click()
  open_case(p,record);expect(p.locator('[data-review-action=READ]')).to_be_enabled()
  label=f'{engine}-{w}x{h}-identity';results.append(bottom(p,'staff-case','#staff-case-content .admission-section > p:last-of-type',label,shots/(label+'.png')))
  p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-password')).to_be_visible();results.append(bottom(p,'staff-stepup','#staff-stepup-status',label+'-password'))
  if w==390:
   p.locator('#staff-password').fill((r['owned']/'fixture-password').read_text().strip());p.locator('#staff-challenge').click();expect(p.locator('#staff-code-stage')).to_be_visible();results.append(bottom(p,'staff-stepup','#staff-stepup-status',label+'-mfa',shots/(label+'-mfa.png')))
   last=r['sql']("SELECT last_totp_time_step FROM auth_staff_mfa_factors WHERE account_id='qa_director'")[0][0]
   while int(time.time())//30<=last:p.wait_for_timeout(250)
   p.locator('#staff-code').fill(review.totp(r['owned']));p.locator('#staff-execute').click();expect(p.locator('#staff-stepup')).not_to_be_visible(timeout=15000);expect(p.locator('#case-pages img')).to_have_count(2)
   results.append(bottom(p,'staff-case','#case-pages img:last-child',label+'-protected-pages',shots/(label+'-evidence.png')))
  else:p.locator('#cancel-staff-stepup').click()
  p.locator('#close-staff-case').click();check(label+' password/MFA/evidence footer reachable',True)


def open_evidence(p):
 if p.locator('#identity-evidence').get_attribute('open') is None:p.locator('#identity-evidence > summary').click()
 p.locator('#member-evidence').click()


def avatar_modal(p,record,check,results,shots,engine):
 p.set_viewport_size({'width':390,'height':600});personal(p,record)
 p.locator('#personal-avatar-trigger').click();p.locator('.team-select-options:popover-open [role=option]').filter(has_text='Neutral').click();expect(p.locator('#avatar-example svg')).to_have_count(1)
 results.append(bottom(p,'staff-profile','#portrait-version',engine+'-avatar-unsaved',shots/(engine+'-avatar-footer.png')))
 prompts=[];p.once('dialog',lambda d:prompts.append(d.message));p.locator('#cancel-staff-profile').click();expect(p.locator('#staff-profile')).not_to_be_visible()
 check(engine+' avatar selector and footer cancel preserve unsaved-change confirmation',len(prompts)==1 and 'descartar' in prompts[0].lower())
