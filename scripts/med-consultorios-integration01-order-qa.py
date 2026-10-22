#!/usr/bin/env python3
"""Owned synthetic browser QA: real order writes, conflict, rollback and Identity logout."""
import json,pathlib,sys,time,os
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];D=pathlib.Path(sys.argv[1]).resolve();S=json.loads((D/'state.json').read_text());C=json.loads((D/'credentials.json').read_text());O=S['origin']
assert S['source']==str(ROOT) and D.name.startswith('mxmed-init02-') and D.stat().st_uid==os.getuid() and S['expires']>time.time()
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
results=[]
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});p=c.new_page()
  def login():
   p.goto(O+'/public/identity/acceso.php');p.locator('[name=email]').fill(C['owner']['email']);p.locator('[name=password]').fill(C['owner']['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php');enter(p);state(p,'READY')
  def read():return c.request.get(O+'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor').json()
  def handle(n):return p.locator('[data-consultorio-tab-item]').filter(has=p.locator('[data-bs-target="#sede'+n+'"]')).locator('.cons-tab-drag-handle')
  login();initial=read();assert initial['meta']['presentation_order']==['1','2','3']
  try:
   with p.expect_response(lambda r:'/consultorios/reorder' in r.url) as response:handle('2').press('ArrowLeft')
   assert response.value.status==200 and response.value.json()['ok'];assert read()['meta']['presentation_order']==['2','1','3'];assert rows(p)==['#sede2','#sede1','#sede3']
   assert next(x for x in read()['data'] if x['consultorio_id']=='2')['document_is_primary']
   # Canonical optimistic concurrency rejects a stale fingerprint without changing the order.
   r=c.request.put(O+'/api/agenda/index.php/consultorios/reorder',data={'doctor_id':'qa05-doctor','consultorio_ids':['1','2','3'],'expected_order_fingerprint':initial['meta']['presentation_order_fingerprint']});assert not r.json()['ok'] and r.json()['error']=='conflict';assert read()['meta']['presentation_order']==['2','1','3']
   p.reload(wait_until='domcontentloaded');state(p,'READY');assert rows(p)==['#sede2','#sede1','#sede3'];p.close();p=c.new_page();p.goto(O+'/private-app.php');enter(p)
   p.route('**/consultorios/reorder',lambda route:route.fulfill(status=503,json={'ok':False}))
   handle('2').press('ArrowRight');expect(p.locator('[data-cons-order-message]')).to_contain_text('No se guardó');assert rows(p)==['#sede2','#sede1','#sede3'];assert read()['meta']['presentation_order']==['2','1','3'];p.unroute('**/consultorios/reorder')
   with p.expect_response(lambda r:'/consultorios/reorder' in r.url) as response:handle('2').press('ArrowRight')
   assert response.value.json()['ok'];assert read()['meta']['presentation_order']==['1','2','3']
   # Own session only: real logout followed by canonical login.
   p.locator('#mxHeaderAccount').click();p.locator('[data-header-logout]').click();p.wait_for_url('**/public/identity/acceso.php**');r=c.request.get(O+'/api/agenda/index.php/consultorios?doctor_id=qa05-doctor');assert r.status in [401,403];login()
   results.append({'engine':engine,'real_keyboard_reorder':'PASS','primary_and_reload':'PASS','stale_fingerprint_denied':'PASS','failed_write_ui_rollback':'PASS','original_synthetic_order_restored':'PASS','canonical_logout_relogin':'PASS'})
  finally:
   current=read()
   if current.get('ok') and current['meta']['presentation_order']!=['1','2','3']:
    r=c.request.put(O+'/api/agenda/index.php/consultorios/reorder',data={'doctor_id':'qa05-doctor','consultorio_ids':['1','2','3'],'expected_order_fingerprint':current['meta']['presentation_order_fingerprint']});assert r.json()['ok']
   c.close();b.close()
  print(engine,'ordering + Identity PASS',flush=True)
(ROOT/'docs/evidence/med-consultorios-integration01/ordering-identity.json').write_text(json.dumps(results,indent=2)+'\n')
