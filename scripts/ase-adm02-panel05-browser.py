"""Focused real-browser PANEL05 checks. Only owner-verified synthetic actors and data."""
import json,uuid,subprocess,urllib.parse

def checks(r,check,records,command,consumer,good,csrf,secret,password):
 from playwright.sync_api import sync_playwright,expect
 root,owned,sql,tokens,origin,request=[r[k] for k in ['ROOT','owned','sql','tokens','origin','request']]
 shots=root/'docs/evidence/ase-adm02-panel05/screens';shots.mkdir(parents=True,exist_ok=True)
 url=origin+'/internal/media-review/index.php?section=equipo';uid=lambda:str(uuid.uuid4());errors=[]
 def raw(action,body,actor='director'):
  return request(actor,path='/api/internal/staff-admission/workflow.php',method='POST',body={'action':action,'input':body},headers={'X-CSRF-Token':csrf.get(actor,'')})
 def team(actor='director',**params):return request(actor,path='/api/internal/team/read.php?'+urllib.parse.urlencode(params),method='GET')
 # Preserve a removed fixture and the already suspended fixture, using canonical commands.
 if team(target=records['celia']['account'])[1]['items'][0]['staff_status']!='REMOVED':assert command('remove_advisor',records['celia']['account'])[0]==200
 for cap in team(target=records['alma']['account'])[1]['items'][0]['grants']:assert command('revoke',records['alma']['account'],cap)[0]==200
 check('Suspended and removed commands remain denied',all(command('grant',records[a]['account'],'media_review_read')[0]!=200 for a in ['celia','diego']))
 check('Name filtering before pagination',team(q='Demostración',page='1',page_size='1')[1]['has_more'] and len(team(q='Demostración',page='2',page_size='1')[1]['items'])==1)
 check('Name search respects category and status',len(team(q='Diego',status='ACTIVE')[1]['items'])==0 and len(team(q='Diego',status='SUSPENDED')[1]['items'])==1 and len(team(q='Bruno',category='MASTER_ADMIN')[1]['items'])==0)
 check('Master cannot discover private names or other categories',team('master',q='Bruno')[1]['items']==[] and team('master',category='MASTER_ADMIN')[0]!=200)
 check('Applicant cannot search staff directory',team('fernanda',q='Bruno')[0]!=200)
 check('No global account enumeration',team(q='qa_outsider')[1]['items']==[])
 check('Pending name search paginates before LIMIT',good('cases',dict(q='Demostración',page=1,page_size=1,pending=True))['has_more'] and len(good('cases',dict(q='Fernanda',page=1,page_size=1,pending=True))['items'])==1)
 check('Applicant pending search does not disclose another case',good('cases',dict(q='Elena',page=1,page_size=1,pending=True),'fernanda')['items']==[])
 csrf['master']=good('bootstrap',actor='master')['csrf_token']
 check('Receipt absence is not disclosed without Director authority',raw('case_result',{'operation_id':uid()},'master')[0]==403 and raw('invitation_result',{'operation_id':uid()},'fernanda')[0]==403)
 with sync_playwright() as pw:
  for engine in ['chromium','webkit']:
   b=getattr(pw,engine).launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1440,'height':900});c.add_cookies([dict(name='__Host-mxmed_session',value=tokens['director'],url=origin,secure=True,httpOnly=True,sameSite='Strict')]);p=c.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.goto(url)
   expect(p.locator('#add-member')).to_be_enabled();expect(p.locator('#status-trigger')).to_contain_text('Activos');expect(p.locator('#operational-matrix [data-family="media"] [data-group-count]')).to_be_enabled()
   def status(value):
    p.locator('#status-trigger').click();p.locator('#status-options [data-value="'+value+'"]').click();expect(p.locator('#directory-content')).to_have_attribute('aria-busy','false')
   def wizard(label,email):
    p.locator('#add-member').click();p.locator('#new-given_names').fill(label);p.locator('#new-first_surname').fill('Demostración');p.locator('#new-email').fill(email)
    for n in [2,3,4]:p.locator('#next-onboarding').click();expect(p.locator('#onboarding-title')).to_contain_text('paso '+str(n))
   for w,h in [(1440,900),(1366,768),(390,844)]:
    p.set_viewport_size({'width':w,'height':h})
    if w==390:p.locator('#show-directory').click()
    for how in ['button','escape','backdrop']:
     p.locator('#add-member').click();expect(p.locator('#onboarding')).to_be_visible();p.locator('#onboarding-title').click();expect(p.locator('#onboarding')).to_be_visible()
     if how=='button':p.locator('#cancel-onboarding').click()
     elif how=='escape':p.keyboard.press('Escape')
     else:p.mouse.click(2,2)
     expect(p.locator('#onboarding')).to_be_hidden();expect(p.locator('#add-member')).to_be_focused();check(f'{engine} {w} modal {how}, inside click and focus/scroll restore',p.evaluate('document.body.style.overflow')!='hidden')
    p.locator('#status-trigger').click();expect(p.locator('#status-options')).to_be_visible();box=p.locator('#status-options').bounding_box();check(f'{engine} {w} compact expanded listbox',box['width']<=210 and box['height']<=210);p.screenshot(path=str(shots/f'{engine}-{w}-status-expanded.png'),full_page=True);p.keyboard.press('Escape');expect(p.locator('#status-options')).to_be_hidden();expect(p.locator('#status-trigger')).to_be_focused()
    p.locator('#status-trigger').focus();p.keyboard.press('ArrowDown');p.keyboard.press('ArrowDown');p.keyboard.press('Enter');expect(p.locator('#advisor-detail .status')).to_have_text('Suspendido');check(f'{engine} {w} suspended explanation and controls',p.locator('#advisor-detail').inner_text().find('Sus permisos no pueden modificarse')>=0 and p.locator('#operational-matrix input:not(:disabled)').count()==0)
    if w==390:p.locator('#show-detail').click()
    p.locator('#return-active').click();expect(p.locator('#advisor-detail .status')).to_have_text('Activo')
    if w==390:p.locator('#show-directory').click()
    p.locator('#add-member').click();p.locator('#new-given_names').fill('Cambio sin guardar');p.once('dialog',lambda d:d.dismiss());p.keyboard.press('Escape');expect(p.locator('#onboarding')).to_be_visible();p.once('dialog',lambda d:d.accept());p.locator('#cancel-onboarding').click();expect(p.locator('#onboarding')).to_be_hidden();check(f'{engine} {w} unsaved cancel/discard',True)
    check(f'{engine} {w} no horizontal overflow',p.evaluate('document.documentElement.scrollWidth<=innerWidth+1'))
   p.set_viewport_size({'width':1440,'height':900});p.locator('#filter-advisors').click();expect(p.locator('[data-member]')).to_have_count(2)
   check(engine+' category order and hierarchy',p.locator('.category-filter button').all_inner_texts()==['Administradores maestros','Asesores'] and p.locator('#filter-advisors').get_attribute('aria-pressed')=='true')
   p.locator('#advisor-search').fill('Bruno');expect(p.locator('[data-member]')).to_have_count(1);expect(p.locator('#detail-title')).to_have_text(records['bruno']['name']);p.locator('#advisor-search').fill('Ninguna Persona');expect(p.locator('[data-member]')).to_have_count(0);p.locator('#clear-staff-search').click();expect(p.locator('[data-member]')).to_have_count(2);check(engine+' name/no-results/clear search',True)
   p.locator('#advisor-search').fill('Alma');expect(p.locator('[data-member]')).to_have_count(1);p.locator('#filter-masters').click();expect(p.locator('#advisor-search')).to_have_value('');expect(p.locator('#advisor-detail .eyebrow')).to_have_text('Administrador maestro');p.locator('#filter-advisors').click();expect(p.locator('#advisor-detail .eyebrow')).to_have_text('Asesor');check(engine+' category clears search and stale details',True)
   status('REMOVED');expect(p.locator('#advisor-detail .status')).to_have_text('Baja definitiva');check(engine+' removed read-only controls',p.locator('#operational-matrix input:not(:disabled)').count()==0);status('PENDING');expect(p.locator('[data-case]')).not_to_have_count(0);p.locator('#advisor-search').fill('Fernanda');expect(p.locator('[data-case]')).to_have_count(1);check(engine+' pending names searched on server',True);p.locator('#clear-staff-search').click();status('ACTIVE');expect(p.locator('[data-member]')).to_have_count(2)
   if engine=='webkit':b.close();continue
   draft_label='Invitación Sintética '+uid()[:8]
   p.locator('#add-member').click();p.locator('#new-given_names').fill(draft_label);p.locator('#new-first_surname').fill('PANEL05');p.locator('#new-email').fill('synthetic@example.org');p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('@example.test');expect(p.locator('#onboarding-title')).to_contain_text('paso 1');check('Recipient rejected before final step',True)
   p.locator('#new-email').fill('panel05@example.test');p.locator('#save-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Borrador guardado');p.locator('#cancel-onboarding').click();expect(p.locator('#onboarding')).to_be_hidden();check('Saved canonical draft survives close',sql('SELECT COUNT(*) FROM auth_staff_admission_versions WHERE given_names=%s',(draft_label,))[0][0]==1)
   before=sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0];beforei=sql('SELECT COUNT(*) FROM auth_staff_admission_invitations')[0][0]
   wizard('Invitación Válida PANEL05','valid-panel05@example.test');p.locator('#next-onboarding').dblclick();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada en el entorno sintético');check('Four-step canonical success and doubleclick protection',sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0]==before+1 and sql('SELECT COUNT(*) FROM auth_staff_admission_invitations')[0][0]==beforei+1);p.locator('#cancel-onboarding').click()
   # Lose the acknowledgement only AFTER the real canonical HTTP mutation committed.
   for action in ['prepare','issue']:
    before=sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0];beforei=sql('SELECT COUNT(*) FROM auth_staff_admission_invitations')[0][0];seen=[]
    def lose(route):
     if route.request.post_data_json['action']==action:
      response=route.fetch(headers={**route.request.all_headers(),'Sec-Fetch-Site':'same-origin'});assert response.status==200,(response.status,response.text());seen.append(response.json())
      if action=='issue':route.fulfill(status=200,content_type='application/json',body='not-json')
      else:route.abort('failed')
     else:route.continue_()
    p.route('**/api/internal/staff-admission/workflow.php',lose);wizard('Respuesta Perdida '+action,'lost-'+action+'@example.test');p.locator('#next-onboarding').click();expect(p.locator('#check-onboarding')).to_be_enabled();expect(p.locator('#onboarding-feedback')).to_contain_text('Consulta el estado');p.keyboard.press('Escape');expect(p.locator('#onboarding')).to_be_hidden();p.unroute('**/api/internal/staff-admission/workflow.php',lose)
    p.reload();expect(p.locator('#add-member')).to_be_enabled();p.locator('#add-member').click();expect(p.locator('#check-onboarding')).to_be_enabled();p.locator('#check-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Borrador guardado confirmado' if action=='prepare' else 'Invitación preparada en el entorno sintético')
    check('Lost '+action+' ACK recovers after dismiss/reload without resend',len(seen)==1 and sql('SELECT COUNT(*) FROM auth_staff_admission_cases')[0][0]==before+1 and sql('SELECT COUNT(*) FROM auth_staff_admission_invitations')[0][0]==beforei+(action=='issue'))
    p.locator('#cancel-onboarding').click()
   # No mutation reached server: explicit authoritative read permits explicit retry only.
   intercepted=[]
   def drop(route):
    if route.request.post_data_json['action']=='issue':intercepted.append(route.request.post_data_json['input']['operation_id']);route.abort('failed')
    else:route.continue_()
   p.route('**/api/internal/staff-admission/workflow.php',drop);wizard('Sin Commit PANEL05','noncommit@example.test');p.locator('#next-onboarding').click();expect(p.locator('#check-onboarding')).to_be_enabled();p.unroute('**/api/internal/staff-admission/workflow.php',drop);p.locator('#check-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('no encontró una operación confirmada');p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada en el entorno sintético');check('Confirmed noncommit explicit retry retains operation identity',sql('SELECT COUNT(*) FROM auth_staff_admission_invitation_operations WHERE operation_id=%s',(intercepted[0],))[0][0]==1);p.locator('#cancel-onboarding').click()
   # A real canonical rejection must remain distinguishable from transport uncertainty.
   def stale(route):
    body=route.request.post_data_json
    if body['action']=='issue':body['input']['expected_version']=999;route.continue_(post_data=json.dumps(body))
    else:route.continue_()
   p.route('**/api/internal/staff-admission/workflow.php',stale);wizard('Denegación PANEL05','denied@example.test');p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('no autoriza esta operación');p.unroute('**/api/internal/staff-admission/workflow.php',stale);p.locator('#check-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('no encontró una operación confirmada');p.locator('#next-onboarding').click();expect(p.locator('#onboarding-feedback')).to_contain_text('Invitación preparada en el entorno sintético');p.locator('#cancel-onboarding').click();check('Canonical denial feedback and explicit corrected retry',True)
   # Hold in-flight request; dismissal must work before its eventual acknowledgement.
   held=[]
   def hold(route):
    if route.request.post_data_json['action']=='issue':held.append(route)
    else:route.continue_()
   p.route('**/api/internal/staff-admission/workflow.php',hold);wizard('En Curso PANEL05','pending@example.test');p.locator('#next-onboarding').click();expect(p.locator('#next-onboarding')).to_be_disabled();p.locator('#cancel-onboarding').click();expect(p.locator('#onboarding')).to_be_hidden();p.wait_for_timeout(200);assert held;held[0].fulfill(response=held[0].fetch(headers={**held[0].request.all_headers(),'Sec-Fetch-Site':'same-origin'}));p.unroute('**/api/internal/staff-admission/workflow.php',hold);p.wait_for_timeout(300);check('Close during in-flight submit without abort/duplicate',True)
   # Server recipient/domain and authorization checks, with no external provider involved.
   draft=good('prepare',dict(operation_id=uid(),recipient_reference=uid(),channel='ASSISTED',proposed_category='ADVISOR',given_names='Rechazo Sintético',first_surname='PANEL05',second_surname='',operation_city='',operation_state=''))
   body=dict(operation_id=uid(),case_id=draft['case_id'],expected_version=1,recipient_email='rejected@example.org');rejected=raw('issue',body);check('Invalid synthetic domain rejected before canonical invitation write',rejected[0]==400 and rejected[1]['error']=='synthetic_recipient_required' and good('invitation_status',{'case_id':draft['case_id']})['invitation'] is None)
   check('Master invitation denied canonically',raw('issue',{**body,'recipient_email':'rejected@example.test'},'master')[0]==403)
   # A real permission save, mixed group, persistence, effective consumer and revoke.
   p.reload();expect(p.locator('[data-member]')).to_have_count(2);p.locator('[data-member="'+records['alma']['account']+'"]').click();expect(p.locator('#detail-title')).to_have_text(records['alma']['name']);g=p.locator('#operational-matrix [data-family="media"] [data-group-count]');expect(g).to_be_enabled();g.check();p.locator('[data-expand="use-media"]').click();p.locator('[data-capability="media_review_approve"]').uncheck();check('Active Advisor individual and partial group controls',g.evaluate('(e)=>e.indeterminate'));p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Grant effective in canonical consumer',consumer('alma'));p.reload();p.locator('[data-member="'+records['alma']['account']+'"]').click();p.locator('[data-expand="use-media"]').click();expect(p.locator('[data-capability="media_review_read"]')).to_be_checked();expect(p.locator('[data-capability="media_review_approve"]')).not_to_be_checked();check('Permission save persists after reload',True);p.locator('[data-capability="media_review_read"]').uncheck();p.locator('#save-permissions').click();p.locator('#accept-confirmation').click();expect(p.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=20000);check('Revoke effective in canonical consumer',not consumer('alma'))
   for w,h in [(1440,900),(1366,768),(390,844)]:
    p.set_viewport_size({'width':w,'height':h});p.screenshot(path=str(shots/f'chromium-{w}-final.png'),full_page=True)
   check('Browser storage contains no credentials or invitation tokens',p.evaluate('Object.entries(sessionStorage)')==[])
   b.close()
 check('No JavaScript errors',not errors)
 check('Synthetic delivery artifacts only',all(json.loads(f.read_text())['recipient'].endswith('@example.test') for f in owned.glob('invitation-*.json')))
 print('PANEL05 focused checks complete',flush=True)


def run(r,check,*args):
 # Standard owned harness handles failure/disposal. Development retries are preserved in evidence.
 return checks(r,check,*args)
