#!/usr/bin/env python3
"""Focused actual-shell/canonical HTTP QA; all fault injection is browser-local and synthetic."""
import hashlib,json,pathlib,sys,time
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];D=pathlib.Path(sys.argv[1]).resolve();S=json.loads((D/'state.json').read_text());E=json.loads((D/'environment.json').read_text());C=json.loads((D/'credentials.json').read_text());O=S['origin']
assert D.name.startswith('mxmed-init02-') and S['source']==str(ROOT) and int(E['MXMED_DB_PORT'])!=3306 and E['MXMED_DB_NAME']=='mxmed_gate4d_preview_adminqa_init02'
OUT=ROOT/'docs/evidence/med-consultorios-init02';OUT.mkdir(parents=True,exist_ok=True)
db=pymysql.connect(host='127.0.0.1',port=int(E['MXMED_DB_PORT']),user=E['MXMED_DB_USER'],password=E['MXMED_DB_PASS'],database=E['MXMED_DB_NAME'],autocommit=True)
with db.cursor() as q:
 q.execute("UPDATE consultorios SET grupo_nombre='' WHERE doctor_id='qa05-doctor'")
 q.execute("UPDATE consultorios SET titulo='Consultorio Centro QA' WHERE doctor_id='qa05-doctor' AND consultorio_id='1'")
 q.execute("INSERT IGNORE INTO profiles_doctors(doctor_id,display_name) VALUES('init02-other','Dra. Contexto Alterno Sintético')")
 q.execute("INSERT IGNORE INTO consultorios(doctor_id,consultorio_id,titulo) VALUES('init02-other','1','Sede alterna sintética')")
 q.execute("UPDATE auth_account_memberships SET profile_doctor_id='init02-other' WHERE membership_id='qa05-membership-administrator'")
def snapshot():
 with db.cursor() as q:
  q.execute('SELECT * FROM consultorios ORDER BY doctor_id,consultorio_id');h=hashlib.sha256(repr(q.fetchall()).encode()).hexdigest();q.execute('SELECT COUNT(*) FROM init02_write_log');return {'hash':h,'writes':q.fetchone()[0]}
def enter(p):
 p.wait_for_load_state('load');p.wait_for_function("window.mxmedPrivateSession?.state==='authenticated'")
 # Existing first-use help can open with Perfil. Let its Bootstrap transition finish.
 def dismiss_help():
  p.wait_for_timeout(400)
  if p.locator('#mx-visibility-help-modal').is_visible():
   p.locator('#mx-visibility-help-modal .modal-footer button').click()
   expect(p.locator('#mx-visibility-help-modal')).to_be_hidden()
 dismiss_help()
 sub=p.locator('button[data-panel="p-consultorio"]')
 if not sub.is_visible():p.locator('button.menu-main[data-group=perfil]').click()
 dismiss_help()
 if not sub.is_visible():p.locator('button.menu-main[data-group=perfil]').click()
 sub.click();expect(p.locator('#p-consultorio')).to_be_visible()
def state(p,value):expect(p.locator('#p-consultorio')).to_have_attribute('data-cons-load-state',value,timeout=18000)
def rows(p):return p.locator('#p-consultorio [data-consultorio-tab-item]:not([hidden]) .nav-link').evaluate_all("bs=>bs.map(b=>b.getAttribute('data-bs-target'))")
results=[];baseline=snapshot()
def passed(engine,name,**extra):results.append({'engine':engine,'test':name,'result':'PASS',**extra});print(engine,name,'PASS',flush=True);(OUT/'progress.json').write_text(json.dumps(results,indent=2)+'\n')
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  browser=getattr(pw,engine).launch(headless=True)
  def login(role):
   c=browser.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});p=c.new_page();p.goto(O+'/public/identity/acceso.php',wait_until='load');p.locator('[name=email]').fill(C[role]['email']);p.locator('[name=password]').fill(C[role]['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php',timeout=20000);state(p,'READY');return c,p
  c,p=login('owner');enter(p);assert rows(p)==['#sede1','#sede2','#sede3'];assert p.locator('#cons-titulo2').input_value()=='Consultorio Norte QA';assert p.locator('[data-bs-target="#sede2"] .tab-lbl').inner_text()=='CONSULTORIO NORTE QA';auth=c.storage_state();p.screenshot(path=str(OUT/(engine+'-ready.png')));passed(engine,'canonical_login_first_three');c.close()
  for mode in ['success','delayed','http_error','network','timeout','invalid_json','invalid_shape','wrong_doctor','duplicate_ids','missing_scope','empty','retry','rapid_navigation','stale_context']:
   before=snapshot();writes=[];errors=[];held=[];attempt=[0];c=browser.new_context(ignore_https_errors=True,storage_state=auth,viewport={"width":1366,"height":900});p=c.new_page();p.on('pageerror',lambda e:errors.append(str(e)));p.on('request',lambda r:writes.append({'method':r.method,'path':r.url.split('?')[0].replace(O,'')}) if r.url.startswith(O+'/') and r.method in ['POST','PUT','PATCH','DELETE'] else None)
   def intercept(route):
    if route.request.method!='GET':route.continue_();return
    attempt[0]+=1
    if mode=='timeout':held.append(route);return
    if mode=='stale_context' and attempt[0]==1:
     response=route.fetch();held.append((route,response.body()));return
    if mode=='network':route.abort('connectionfailed');return
    if mode=='http_error' or (mode=='retry' and attempt[0]==1):route.fulfill(status=503,json={'ok':False});return
    if mode=='invalid_json':route.fulfill(status=200,body='<html>error</html>');return
    response=route.fetch();data=response.json()
    if mode=='delayed':
     time.sleep(.8)
    if mode=='invalid_shape':data={'ok':True,'data':{}}
    if mode=='wrong_doctor':data['data'][0]['doctor_id']='init02-other'
    if mode=='duplicate_ids':data['data'].append(data['data'][0])
    if mode=='empty':data['data']=[];data['meta']['presentation_order']=[]
    route.fulfill(response=response,json=data)
   p.route('**/api/agenda/index.php/consultorios?*',intercept)
   p.goto(O+'/private-app.php',wait_until='domcontentloaded');enter(p)
   if mode=='missing_scope':
    state(p,'READY');p.evaluate("document.body.dataset.doctorId=''");state(p,'ERROR');assert attempt[0]==1;p.evaluate("document.body.dataset.doctorId='qa05-doctor'");state(p,'READY');assert rows(p)==['#sede1','#sede2','#sede3']
   elif mode=='stale_context':
    p.wait_for_timeout(700);assert held
    p.evaluate("document.body.dataset.doctorId='init02-other'");state(p,'ERROR');held[0][0].fulfill(status=200,content_type='application/json',body=held[0][1]);p.wait_for_timeout(200);state(p,'ERROR');expect(p.locator('[data-cons-records]')).to_be_hidden()
   elif mode in ['http_error','network','timeout','invalid_json','invalid_shape','wrong_doctor','duplicate_ids','retry']:
    state(p,'ERROR');expect(p.locator('[data-cons-records]')).to_be_hidden();expect(p.locator('[data-cons-load-retry]')).to_be_visible()
    if mode=='http_error':p.screenshot(path=str(OUT/(engine+'-error.png')))
    if mode=='retry':p.locator('[data-cons-load-retry]').click();state(p,'READY');assert rows(p)==['#sede1','#sede2','#sede3']
   elif mode=='empty':
    state(p,'EMPTY');expect(p.locator('[data-cons-records]')).to_be_hidden();expect(p.locator('[data-cons-add-first]')).to_be_visible();p.locator('[data-cons-add-first]').click();state(p,'READY');p.wait_for_timeout(600)
   else:
    state(p,'READY');assert rows(p)==['#sede1','#sede2','#sede3']
    if mode=='rapid_navigation':
     for _ in range(5):enter(p)
     assert attempt[0]==1;p.reload(wait_until='domcontentloaded');state(p,'READY');assert rows(p)==['#sede1','#sede2','#sede3']
   p.wait_for_timeout(650);assert not writes,(mode,writes);assert snapshot()==before,(mode,snapshot(),before);assert not errors,(mode,errors)
   passed(engine,mode,unexpected_writes=0,before=before,after=snapshot());p.unroute_all(behavior='ignoreErrors');c.close()
  # Actual independent authenticated collaborator/other physician, not a mocked reader.
  c,p=login('collaborator');enter(p);assert rows(p)==['#sede1','#sede2','#sede3'];cross=p.evaluate("async()=>{let r=await fetch('/api/agenda/index.php/consultorios?doctor_id=init02-other');return {status:r.status,body:await r.json()}}")
  assert cross['body']['ok'] is False and not cross['body'].get('data');passed(engine,'collaborator_read_cross_physician_denial',http=cross['status']);c.close()
  c,p=login('administrator');enter(p);assert rows(p)==['#sede1'];assert p.locator('#cons-titulo').input_value()=='Sede alterna sintética';passed(engine,'independent_account_same_slot_different_doctor');c.close()
  assert snapshot()==baseline
  # Real user editing after initialization, via unchanged canonical writer.
  c,p=login('owner');enter(p);original=p.locator('#cons-titulo').input_value();writes=[];p.on('request',lambda r:writes.append(r.method) if '/consultorios' in r.url and r.method!='GET' else None)
  p.locator('#cons-titulo').fill('Sede editada sintética INIT02');enter(p);assert p.locator('#cons-titulo').input_value()=='Sede editada sintética INIT02';p.wait_for_timeout(1000)
  actual=p.evaluate("async()=> (await (await fetch('/api/agenda/index.php/consultorios?doctor_id=qa05-doctor')).json()).data[0].titulo")
  assert actual=='Sede editada sintética INIT02',(actual,writes);assert writes==['PUT'],writes
  p.locator('#cons-titulo').fill(original);p.wait_for_timeout(1000);assert p.evaluate("async()=> (await (await fetch('/api/agenda/index.php/consultorios?doctor_id=qa05-doctor')).json()).data[0].titulo")==original;assert writes==['PUT','PUT'];passed(engine,'genuine_edit_persisted_dirty_navigation_preserved',expected_puts=len(writes));c.close();baseline=snapshot();browser.close()
report={'result':'PASS','checks':results,'final_snapshot':snapshot(),'unexpected_write_count':0,'authority_limit':'Uses unchanged normal preview Identity/scope path; prepared QA05 role gate is not enabled. No new collaborator write authority claimed.'}
(OUT/'results.json').write_text(json.dumps(report,indent=2)+'\n');db.close();print('FOCUSED_QA=PASS')
