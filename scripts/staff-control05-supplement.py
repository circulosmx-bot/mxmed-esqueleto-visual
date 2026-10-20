#!/usr/bin/env python3
"""Focused cancellation, lost-response recovery and browser acceptance; owned only."""
import pathlib,sys,json,subprocess,importlib.util,uuid,time,os
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
def run(path):
 os.umask(0o077)
 review=module('staff05_review',ROOT/'scripts/staff-control05-review.py');owned,approval=review.owned_configuration(path);info,origin,expiry,record=review.verified_preview(owned,approval)
 env=json.loads((owned/'review-env.json').read_text());db=pymysql.connect(unix_socket=str(owned/'mysql.sock'),user='ase_fixture',password=env['ASE_INT02A_FIXTURE_PASSWORD'],database=env['MXMED_DB_NAME'],autocommit=True)
 def sql(q,args=()):
  with db.cursor() as c:c.execute(q,args);return c.fetchall()
 assert pathlib.Path(sql('SELECT @@datadir')[0][0]).resolve()==owned/'mysql'
 helper=module('staff05_helper',ROOT/'scripts/ase-adm02-oper09-browser.py');wf=helper.wf
 native=module('staff05_login',ROOT/'scripts/ase-advisor-workspace-review.py')
 out=ROOT/'docs/evidence/staff-control05';shots=out/'screens';shots.mkdir(parents=True,exist_ok=True)
 results={'checks':[],'page_errors':[]}
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});(out/'supplement.json').write_text(json.dumps(results,indent=2));print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
 def good(p,action,body):
  r=wf(p,action,body);assert r['status']==200,(action,r['status']);return r['body']
 def prepare(p,key,label):
  f=owned/('staff05-'+key+'.json')
  if f.exists():return json.loads(f.read_text())
  c=good(p,'prepare',{'operation_id':str(uuid.uuid4()),'recipient_reference':str(uuid.uuid4()),'channel':'ASSISTED','proposed_category':'ADVISOR','given_names':label,'first_surname':'Ficticia','second_surname':'','operation_state':'Aguascalientes','operation_city':'Aguascalientes'})
  email=key+'_staff05@example.test';inv=good(p,'issue',{'case_id':c['case_id'],'expected_version':c['version'],'operation_id':str(uuid.uuid4()),'recipient_email':email})
  result={'case':c['case_id'],'email':email,'invitation':inv['invitation_id']};f.write_text(json.dumps(result));return result
 def open_case(p,id):
  p.reload();expect(p.locator('[data-case="'+id+'"]').first).to_be_visible();p.locator('[data-case="'+id+'"]').first.click();expect(p.locator('#staff-case-content')).to_have_js_property('inert',False);expect(p.locator('#admission-next-state')).to_be_visible()
 with sync_playwright() as pw:
  b,dc,d=native.native_browser(pw);d.on('pageerror',lambda e:results['page_errors'].append(str(e)))
  native.login(d,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  lucia=prepare(d,'lucia','Lucía Pendiente');id=lucia['case'];open_case(d,id)
  check('Registered unbound candidate has contextual status and zero review controls',d.locator('[data-review-action]').count()==0 and 'Invitación registrada' in d.locator('#invitation-state').inner_text())
  d.locator('#prepared-faculties summary').click();d.locator('[data-intention="media_review_read"]').check();d.locator('#save-intentions').click();expect(d.locator('#staff-case-status')).to_contain_text('Facultades previstas guardadas')
  open_case(d,id);d.locator('#prepared-faculties summary').click()
  check('Saved intended capability survives reload and is never labelled active',d.locator('[data-intention="media_review_read"]').is_checked() and 'Permiso activo' not in d.locator('#pending-grants').inner_text())
  c=good(d,'case',{'case_id':id});v=good(d,'intentions',{'case_id':id})
  denied=wf(d,'prepare_intentions',{'case_id':id,'expected_case_version':c['version'],'expected_version':v['version'],'capabilities':['profile_create'],'operation_id':str(uuid.uuid4())})
  check('Disconnected capability rejected by server, not only hidden by UI',denied['status']==400)
  check('Prepared capability cannot activate before admission',wf(d,'activate_intention',{'case_id':id,'expected_case_version':c['version'],'expected_version':v['version'],'capability':'media_review_read'})['status'] in [400,403,409])
  before=sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(id,))[0][0]
  d.locator('#edit-preparation summary').click();d.locator('#edit-second_surname').fill('Preparada');d.locator('#save-preparation-edit').click();expect(d.locator('#staff-case-status')).to_contain_text('No se creó ni reenvió')
  check('Editing registered preparation never silently recreates invitation',sql('SELECT COUNT(*) FROM auth_staff_admission_invitations WHERE case_id=%s',(id,))[0][0]==before and not good(d,'invitation_status',{'case_id':id})['invitation']['usable'])
  d.locator('#invitation-details summary').click();d.locator('#reissue-email').fill(lucia['email']);d.once('dialog',lambda dialog:dialog.accept());d.locator('#reissue-invitation').click();expect(d.locator('#staff-case-status')).to_contain_text('Nueva invitación registrada')
  check('Separate confirmed reissuance replaces previous token and uses current version',sql('SELECT state FROM auth_staff_admission_invitations WHERE invitation_id=%s',(lucia['invitation'],))[0][0]=='REPLACED' and good(d,'invitation_status',{'case_id':id})['invitation']['usable'])
  for _ in range(3):assert good(d,'simulation_status',{'case_id':id})['state']=='NOT_REQUESTED'
  check('Operation status reader never queues a simulation',not (owned/('simulation-'+id+'.json')).exists())
  d.screenshot(path=str(shots/'native-pending.png'));d.locator('#close-staff-case').click()
  # Lose only the enqueue response, after the authorized server has committed it.
  candela=prepare(d,'candela','Candela Revisión');open_case(d,candela['case'])
  d.evaluate("""()=>{const original=window.fetch;window.fetch=async(...args)=>{const response=await original(...args);let action;try{action=JSON.parse(args[1]?.body||'{}').action;}catch(_){}if(action==='simulate'&&response.ok){window.__staff05LostAck=true;throw new TypeError('Synthetic response loss after server commit');}return response;};}""")
  d.locator('#simulate-onboarding').click();expect(d.locator('#staff-case-status')).to_contain_text('No pudimos confirmar',timeout=25000);assert d.evaluate('window.__staff05LostAck===true')
  open_case(d,candela['case']);expect(d.locator('#simulation-progress')).to_have_attribute('data-state','REVIEW_REQUIRED',timeout=90000)
  result=good(d,'simulation_status',{'case_id':candela['case']});job=json.loads((owned/('simulation-'+candela['case']+'.json')).read_text());count=sql('SELECT COUNT(*) FROM auth_accounts')[0][0]
  same=good(d,'simulate',{'case_id':candela['case']})
  check('Lost enqueue response recovers by status polling with stable operation identity',result['operation_id']==same['operation_id']==candela['case'] and count==sql('SELECT COUNT(*) FROM auth_accounts')[0][0])
  check('Recovery shows six verified stages and next protected review action',d.locator('[data-stage][data-complete=true]').count()==6 and d.locator('[data-review-action=READ]').inner_text()=='Revisar incorporación')
  check('Simulation completion is REVIEW_REQUIRED with no staff or effective grants',not sql('SELECT account_id FROM internal_staff WHERE account_id=%s',(job['account_id'],)) and not sql('SELECT grant_id FROM internal_operator_grants WHERE account_id=%s',(job['account_id'],)))
  d.screenshot(path=str(shots/'simulation-review-required.png'));d.locator('#close-staff-case').click()
  cancelled=prepare(d,'cancel','Solicitud Cancelable');case=good(d,'case',{'case_id':cancelled['case']})
  for action in ['register','verify']:
   r=subprocess.run(['php','modules/identity/tests/StaffInvitationFixture.php',action],cwd=ROOT,env=env,input=json.dumps({'actor':'staff05','email':cancelled['email']}),text=True,capture_output=True);assert r.returncode==0 and json.loads(r.stdout)['ok']
  account=sql('SELECT account_id FROM auth_accounts WHERE email_address=%s',(cancelled['email'],))[0][0];accounts=sql('SELECT COUNT(*) FROM auth_accounts')[0][0]
  open_case(d,cancelled['case']);d.get_by_text('Otras opciones',exact=True).click();d.once('dialog',lambda dialog:dialog.accept());d.locator('#revoke-preparation').click();expect(d.locator('#staff-case')).not_to_be_visible();expect(d.locator('#wf-status')).to_contain_text('cancelada')
  status=good(d,'invitation_status',{'case_id':cancelled['case']});check('Cancellation invalidates effective invitation while preserving canonical history',status['case_state']=='REVOKED' and not status['invitation']['usable'] and sql('SELECT COUNT(*) FROM auth_staff_admission_versions WHERE case_id=%s',(cancelled['case'],))[0][0]>=2)
  applicant=b.new_page(ignore_https_errors=True);native.login(applicant,origin,cancelled['email'],(owned/'applicant-password').read_text().strip(),'/staff-admission.php');inv=json.loads((owned/('invitation-'+cancelled['invitation']+'.json')).read_text())
  acceptance=wf(applicant,'accept',{'case_id':cancelled['case'],'expected_version':status['version'],'invitation_id':cancelled['invitation'],'token':inv['token'],'operation_id':str(uuid.uuid4())})
  check('Cancelled invitation cannot bind a legitimate account; unrelated account survives',acceptance['status']==403 and accounts==sql('SELECT COUNT(*) FROM auth_accounts')[0][0] and sql('SELECT status FROM auth_accounts WHERE account_id=%s',(account,))[0][0]=='active')
  admitted=good(d,'case',{'case_id':record['case']})
  check('Pending cancellation endpoint refuses already admitted staff',wf(d,'revoke',{'case_id':record['case'],'expected_version':admitted['version'],'operation_id':str(uuid.uuid4())})['status']==403 and sql('SELECT status FROM internal_staff WHERE account_id=%s',(record['account'],))[0][0]=='ACTIVE')
  a=b.new_page(ignore_https_errors=True);native.login(a,origin,record['email'],(owned/'applicant-password').read_text().strip(),review.ADVISOR)
  check('Advisor cannot read Director simulation status or prepare faculties',wf(a,'simulation_status',{'case_id':id})['status']==403 and wf(a,'intentions',{'case_id':id})['status']==403)
  for page in [d,a]:
   page.locator('#development-interface-index summary').click();check('Index has two precise launcher commands, one session link and four truthful unavailable contexts',page.locator('#development-interface-index li').count()==6 and page.locator('#development-interface-index li a').count()==1 and 'open-advisor' in page.locator('#development-interface-index').inner_text() and 'open-director' in page.locator('#development-interface-index').inner_text() and page.locator('#development-interface-index').inner_text().count('Interfaz existente; contexto de prueba todavía no habilitado.')==4);page.locator('#development-interface-index summary').click()
  expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');a.locator('[data-doctor-id=workspace_aurora]').click();expect(a.locator('#section-tabs [role=tab]')).to_have_count(15)
  a.locator('#more-toggle').click();a.locator('#more-menu [data-section=agenda]').click();check('Fifteen fixed domains distinguish pending integration in ordinary Spanish','Pendiente de integración para el Asesor' in a.locator('#restricted-message').inner_text())
  # Existing Media decision and its history remain canonical.
  a.locator('#more-toggle').click();a.locator('#more-menu [data-section=images]').click();photo=sql("SELECT submission_id FROM media_review_submissions WHERE owner_id='workspace_aurora' AND purpose='DOCTOR_PROFILE_PHOTO'")[0][0]
  a.locator('#media-items [data-submission-id="'+photo+'"]').click();a.once('dialog',lambda dialog:dialog.accept());a.locator('#approve-image').click();expect(a.locator('#media-items [data-submission-id="'+photo+'"]').last).to_have_count(0)
  a.locator('#tab-history').click();expect(a.locator('.history-entry')).not_to_have_count(0)
  check('Media decision/history preserve hidden physician publication boundary',sql('SELECT review_status FROM media_review_submissions WHERE submission_id=%s',(photo,))[0][0]=='APPROVED' and sql("SELECT profile_status FROM profiles_doctors WHERE doctor_id='workspace_aurora'")[0][0]=='hidden')
  for engine in ['chromium','webkit']:
   browser=getattr(pw,engine).launch()
   for width,height in [(1366,768),(390,844)]:
    ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});page=ctx.new_page();page.on('pageerror',lambda e:results['page_errors'].append(str(e)))
    native.login(page,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR);open_case(page,id)
    page.locator('#prepared-faculties summary').click();page.locator('#save-intentions').scroll_into_view_if_needed();expect(page.locator('#save-intentions')).to_be_visible();page.locator('#close-staff-case').scroll_into_view_if_needed();expect(page.locator('#close-staff-case')).to_be_visible()
    check(engine+' '+str(width)+' pending modal scroll and footer remain accessible',page.evaluate('document.documentElement.scrollWidth<=innerWidth'))
    page.screenshot(path=str(shots/(engine+'-'+str(width)+'.png')));page.locator('#close-staff-case').click();page.locator('#development-interface-index summary').click()
    check(engine+' '+str(width)+' development commands wrap without horizontal overflow',page.evaluate('document.documentElement.scrollWidth<=innerWidth'));ctx.close()
   browser.close()
  check('No browser JavaScript errors',not results['page_errors']);b.close()
 db.close();results['result']='PASS';(out/'supplement.json').write_text(json.dumps(results,indent=2))
if __name__=='__main__':run(sys.argv[1])
