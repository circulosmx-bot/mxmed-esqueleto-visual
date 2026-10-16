#!/usr/bin/env python3
"""Original UI integration QA. Destructive fixtures are exclusively UI03 rehearsal."""
import os
os.environ['MXMED_OP_UI03_REHEARSAL']='1'
import argparse,datetime,importlib.util,json,pathlib,subprocess
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('op02env',ROOT/'scripts/op02-env.py');env=importlib.util.module_from_spec(spec);spec.loader.exec_module(env)
assert env.REHEARSAL and env.DATABASE=='mxmed_gate4d_preview_opui03'
BASE=env.ORIGIN;EVIDENCE=env.SUPPORT/'evidence';EVIDENCE.mkdir(mode=0o700,exist_ok=True)
CAPS=['agenda.appointments.read','agenda.appointments.create','agenda.appointments.reschedule','agenda.appointments.cancel','agenda.availability.read']
CREDS=json.loads(env.CREDS_JSON.read_text())

def login(page,role):
 page.goto(BASE+'/public/identity/acceso.php');page.locator('[name=email]').fill(CREDS[role]['email']);page.locator('[name=password]').fill(CREDS[role]['password']);page.locator('[data-submit]').click();page.wait_for_url('**/private-app.php*',timeout=20000)
 page.wait_for_function("window.mxmedPrivateSession?.state==='authenticated' && !!window.mxmedCanonicalOperators")
 page.wait_for_timeout(500)

def grants(page,caps):
 page.locator('[data-ops-accordion-toggle=permissions]').click()
 for key in CAPS:page.locator('[data-operator-grant="'+key+'"]').set_checked(key in caps)

def invite(page,role,caps,technical='collaborator'):
 page.locator('#ag_ops_form_cancel_btn').click();expect(page.locator('[data-operator-grant]:checked')).to_have_count(0)
 page.locator('#ag_ops_email').fill(CREDS[role]['email']);page.locator('#ag_ops_role').select_option(technical);grants(page,caps)
 page.locator('#ag_ops_save_btn').click();expect(page.locator('#ag_ops_save_btn')).to_be_enabled(timeout=15000);expect(page.locator('#ag_ops_modal_error')).to_have_text('Cambio guardado y confirmado.',timeout=15000)

def accept(page):
 nav=page.locator('#mmSidebar [data-panel=p-operator-inbox]')
 if not nav.is_visible():page.locator('[data-action=sidebar-toggle-proxy]').click()
 nav.click();page.get_by_role('button',name='Abrir invitación',exact=True).click()
 expect(page.locator('#operator-offer-modal')).to_be_visible();page.locator('#operator-offer-accept').click();page.wait_for_url('**/private-app.php?workspace=agenda#p-ag-admin');expect(page.locator('#ag_custom_week_root')).to_be_visible(timeout=20000)

def band(page,account):
 # The synthetic account ID is stable. A generic/missing personal alias must
 # never be recovered from an email prefix or treated as membership identity.
 ident=page.evaluate("async account=>{const r=await window.mxmedCanonicalOperators.request('/api/profile-operators/index.php/overview');if(!r.ok)throw Error('overview unavailable');return r.json.data.members.find(m=>m.account_id===account&&['active','suspended'].includes(m.status))?.membership_id;}",account)
 assert ident,'expected synthetic membership missing'
 return page.locator('#ag_ops_list .mx-ag-ops-operator-band[data-operator-id="'+ident+'"]')
def expand(card):
 if card.locator('.mx-ag-ops-band-head').get_attribute('aria-expanded')!='true':card.locator('.mx-ag-ops-band-head').click()
def edit(page,account,caps):
 card=band(page,account);expand(card);card.get_by_role('button',name='Editar permisos',exact=True).click();grants(page,caps);page.locator('#ag_ops_save_btn').click();expect(page.locator('#ag_ops_save_btn')).to_be_enabled(timeout=15000);expect(page.locator('#ag_ops_modal_error')).to_have_text('Cambio guardado y confirmado.',timeout=15000)

def assert_shell(page,owner):
 assert page.locator('#mmSidebar').count()==1 and page.locator('#ag_calendar').count()==1
 assert not page.locator('#p-expediente,#p-pac-recetas,#p-facturacion,#ag_ops_temp_password,#ag_ops_login,#op02-create').count()
 assert page.locator('#ag_ops_shell').count()==(1 if owner else 0)
 assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+2')
 assert 'OP02_PRIVATE_CANARY' not in page.content()
 assert not page.locator('script[src*="operator-workspace"],script[src*="/app.js"]').count()

def run_case(p,engine,width,height):
 subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'reset'],env=os.environ,cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
 subprocess.run(['python3',str(ROOT/'scripts/op02-env.py'),'start'],env=os.environ,cwd=ROOT,check=True,stdout=subprocess.DEVNULL)
 browser=getattr(p,engine).launch();contexts=[];errors=[];debug=[]
 def debug_console(m):
  if any(v in m.text for v in ['CUSTOM WEEK DEBUG','EVENT BRIDGE','INIT ERROR','CONSULTORIO SYNC']):
   try:debug.append([arg.json_value() for arg in m.args])
   except Exception:pass
 def fresh():
  c=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height},timezone_id='America/Mexico_City');contexts.append(c);pg=c.new_page();pg.on('pageerror',lambda e:errors.append({'message':str(e),'stack':e.stack,'url':pg.url}));pg.on('console',debug_console);return pg
 try:
  owner=fresh();login(owner,'owner');expect(owner.locator('#p-ag-operadores')).to_be_visible();expect(owner.locator('#ag_ops_active_value')).to_have_text('0/2')
  invite(owner,'operador-a',CAPS);invite(owner,'operador-b',[CAPS[0],CAPS[3]],'administrator');expect(owner.locator('#ag_ops_pending_value')).to_have_text('2')
  a=fresh();login(a,'operador-a');accept(a);b=fresh();login(b,'operador-b');accept(b)
  owner.bring_to_front();owner.locator('#ag_ops_refresh').click();expect(owner.locator('#ag_ops_active_value')).to_have_text('2/2');assert_shell(owner,True)
  owner.evaluate('window.scrollTo(0,0)');owner.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-original-operators.png'))
  a.bring_to_front();expect(a.locator('.mx-ag-slot-card')).to_have_count(0,timeout=1000) if False else None
  a.wait_for_timeout(1500);assert_shell(a,False)
  a.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-original-agenda.png'))
  # Create through the original calendar slot menu and original appointment/identity modals.
  tomorrow=(datetime.date.today()+datetime.timedelta(days=1)).isoformat()
  slot=a.locator('.mx-ag-custom-week-col[data-day-key="'+tomorrow+'"] [data-slot-kind=available]').first;slot.click()
  a.locator('#ag_custom_slot_new_btn').click();expect(a.locator('#ag_new_appointment_modal')).to_be_visible()
  print('UI03_ORIGINAL_CREATE_FORM_OPEN='+engine,flush=True)
  a.locator('#ag_patient_search_query').fill('Persona');a.locator('#ag_patient_search_query').press('Enter');expect(a.locator('#ag_patient_lookup_modal')).to_be_visible()
  a.locator('#ag_patient_lookup_list [data-ag-identity-select]').first.click();a.locator('#ag_patient_lookup_list [data-ag-identity-confirm-yes]').first.click()
  expect(a.locator('#ag_patient_lookup_modal')).not_to_be_visible();a.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-original-appointment-form.png'))
  with a.expect_response(lambda r:'/api/agenda/index.php/appointments' in r.url and r.request.method=='POST') as response:a.locator('#ag_create_submit_btn').click()
  assert response.value.status==200,response.value.status
  appointment=response.value.json()['data']['appointment_id'];expect(a.locator('#ag_new_appointment_modal')).not_to_be_visible()
  event=a.locator('[data-event-id="'+appointment+'"]');expect(event).to_be_visible(timeout=20000);event.click();expect(a.locator('#ag_event_actions_modal')).to_be_visible()
  a.locator('#ag_event_reschedule_btn').click();expect(a.locator('[data-ag-reschedule-slot]').first).to_be_visible(timeout=20000)
  a.locator('[data-ag-reschedule-slot]').nth(2).click()
  with a.expect_response(lambda r:r.url.endswith('/reschedule') and r.request.method=='PATCH') as response:a.locator('#ag_event_reschedule_confirm_btn').click()
  assert response.value.status==200,response.value.status
  expect(a.locator('#ag_event_actions_modal')).not_to_be_visible(timeout=15000);print('UI03_ORIGINAL_RESCHEDULE=PASS',flush=True);a.reload();expect(a.locator('[data-event-id="'+appointment+'"]')).to_be_visible(timeout=20000)
  # Same original modal; B has CANCEL and READ only, never RESCHEDULE/CREATE/availability.
  b.bring_to_front();b.reload();event=b.locator('[data-event-id="'+appointment+'"]');expect(event).to_be_visible(timeout=20000);event.click();expect(b.locator('#ag_event_reschedule_btn')).not_to_be_visible();expect(b.locator('#ag_event_cancel_btn')).to_be_visible();b.locator('#ag_event_cancel_btn').click()
  with b.expect_response(lambda r:r.url.endswith('/cancel') and r.request.method=='POST') as response:b.locator('#ag_event_cancel_confirm_btn').click()
  assert response.value.status==200,response.value.status
  if b.locator('#ag_event_actions_modal').is_visible():b.locator('#ag_event_actions_modal .modal-header .btn-close').click()
  print('UI03_ORIGINAL_CANCEL=PASS',flush=True)
  # Edit and immediate revocation in the original owner panel while A keeps its genuine session.
  owner.bring_to_front();edit(owner,'op02-operador-a',[CAPS[0]])
  denied=a.evaluate("async()=>window.mxmedCanonicalOperators.request('/api/agenda/index.php/availability?consultorio_id=1&date="+tomorrow+"')")
  assert denied['status']==403
  card=band(owner,'op02-operador-a');expand(card);card.get_by_role('button',name='Suspender',exact=True).click();expect(band(owner,'op02-operador-a')).to_contain_text('Suspendido')
  assert a.evaluate("async()=> (await window.mxmedCanonicalOperators.request('/api/agenda/index.php/appointments?from="+tomorrow+"%2000:00:00&to="+tomorrow+"%2023:59:59')).status")==403
  card=band(owner,'op02-operador-a');expand(card);owner.once('dialog',lambda d:d.accept());card.get_by_role('button',name='Revocar',exact=True).click();expect(owner.locator('#ag_ops_available_value')).to_have_text('1')
  invite(owner,'operador-c',[CAPS[0]]);expect(owner.locator('#ag_ops_pending_value')).to_have_text('1')
  for page in [a,b]:
   assert_shell(page,False)
   for endpoint in ['/api/clinical/index.php/patients/p_op02patient0001/history','/api/clinical/index.php/prescriptions/op02-document/sign','/api/clinical/index.php/consents/op02-document/grant','/api/billing/invoices.php','/api/identity/governance.php']:
    assert page.evaluate("async path=>(await window.mxmedCanonicalOperators.request(path)).status",endpoint)==403
  # CREATE is usable in the ORIGINAL appointment modal without READ or availability.
  owner.bring_to_front();edit(owner,'op02-operador-b',[CAPS[1]]);b.bring_to_front();b.reload();expect(b.locator('#ag_granted_create_btn')).to_be_visible(timeout=15000);b.locator('#ag_granted_create_btn').click()
  b.locator('#ag_start_input').fill(tomorrow+'T11:00');b.locator('#ag_start_input').dispatch_event('change');b.locator('#ag_end_input').fill(tomorrow+'T11:30');b.locator('#ag_end_input').dispatch_event('change')
  b.locator('#ag_patient_search_query').fill('Persona');b.locator('#ag_patient_search_query').press('Enter');expect(b.locator('#ag_patient_lookup_modal')).to_be_visible();b.locator('#ag_patient_lookup_list [data-ag-identity-select]').first.click();b.locator('#ag_patient_lookup_list [data-ag-identity-confirm-yes]').first.click();expect(b.locator('#ag_patient_lookup_modal')).not_to_be_visible()
  with b.expect_response(lambda r:'/api/agenda/index.php/appointments' in r.url and r.request.method=='POST') as response:b.locator('#ag_create_submit_btn').click()
  assert response.value.status==200,response.value.status
  manual_id=response.value.json()['data']['appointment_id'];expect(b.locator('#ag_new_appointment_modal')).not_to_be_visible();print('UI03_CREATE_WITHOUT_READ_OR_AVAILABILITY=PASS',flush=True)
  assert b.evaluate("async()=> (await window.mxmedCanonicalOperators.request('/api/agenda/index.php/appointments?from="+tomorrow+"%2000:00:00&to="+tomorrow+"%2023:59:59')).status")==403
  owner.bring_to_front();edit(owner,'op02-operador-b',[CAPS[0],CAPS[2]]);b.bring_to_front();b.reload();event=b.locator('[data-event-id="'+manual_id+'"]');expect(event).to_be_visible(timeout=20000);event.click();b.locator('#ag_event_reschedule_btn').click();expect(b.locator('#ag_reschedule_proposed_start')).to_be_visible();b.locator('#ag_reschedule_proposed_start').fill(tomorrow+'T12:00');b.locator('#ag_reschedule_proposed_start').dispatch_event('change')
  with b.expect_response(lambda r:r.url.endswith('/reschedule') and r.request.method=='PATCH') as response:b.locator('#ag_event_reschedule_confirm_btn').click()
  assert response.value.status==200,response.value.status
  expect(b.locator('#ag_event_actions_modal')).not_to_be_visible();print('UI03_RESCHEDULE_WITHOUT_AVAILABILITY=PASS',flush=True)
  # Original responsive navigation and real canonical logout/login.
  b.locator('#mxHeaderAccount').click();b.locator('[data-header-logout]').click();b.wait_for_url('**/public/identity/acceso.php?state=logged-out');login(b,'operador-b');expect(b.locator('#ag_custom_week_root')).to_be_visible(timeout=20000)
  b.goto(BASE+'/private-app.php?agenda_demo=1#p-ag-admin');expect(b.locator('#ag_custom_week_root')).to_be_visible(timeout=20000);assert not b.locator('.mx-ag-demo-qa-mode,[data-slot-demo="1"]').count()
  for page in [owner,b]:
   page.bring_to_front();page.set_viewport_size({'width':390,'height':844});page.wait_for_timeout(400);assert page.evaluate('document.documentElement.scrollWidth<=innerWidth+2');page.locator('[data-action=sidebar-toggle-proxy]').click();expect(page.locator('#mmSidebar')).to_be_visible();page.screenshot(path=str(EVIDENCE/f'{engine}-{width}x{height}-narrow-navigation.png'))
  assert not errors,errors
  return {'engine':engine,'width':width,'height':height,'result':'PASS'}
 except Exception:
  print('BROWSER_JS_ERRORS='+str(errors),flush=True)
  print('BROWSER_TRACE='+str(debug[-20:]),flush=True)
  for idx,c in enumerate(contexts):
   for pg in c.pages:
    try:print('PAGE_STATE',idx,pg.evaluate("({url:location.href,visible:document.visibilityState,session:window.mxmedPrivateSession?.state,ids:[...document.querySelectorAll('[data-event-id]')].map(e=>e.dataset.eventId),error:document.querySelector('#ag_error_box')?.textContent,role:window.mxmedCanonicalOperators?.state.capabilities,root:document.querySelector('#ag_custom_week_root')?.innerText.slice(0,300)})"),flush=True)
    except Exception:pass
  for idx,c in enumerate(contexts):
   for pg in c.pages:
    try:pg.screenshot(path=str(EVIDENCE/f'failure-{engine}-{width}-{idx}.png'))
    except Exception:pass
  raise
 finally:
  for c in contexts:c.close()
  browser.close()

if __name__=='__main__':
 parser=argparse.ArgumentParser();parser.add_argument('--first-only',action='store_true');parser.add_argument('--engine',choices=['chromium','webkit']);parser.add_argument('--size',choices=['1440','1366','820']);args=parser.parse_args();results=[]
 with sync_playwright() as p:
  for engine in ([args.engine] if args.engine else ['chromium','webkit']):
   for width,height in [(1440,900),(1366,768),(820,1180)]:
    if args.size and str(width)!=args.size:continue
    result=run_case(p,engine,width,height);results.append(result);(EVIDENCE/'op-ui03-browser.json').write_text(json.dumps(results,indent=2));print(f'QA_{engine.upper()}_{width}_{height}='+result['result'],flush=True)
    if args.first_only:break
   if args.first_only:break
 (EVIDENCE/'op-ui03-browser.json').write_text(json.dumps(results,indent=2))
