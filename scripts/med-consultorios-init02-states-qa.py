#!/usr/bin/env python3
"""Visible pending/retry states and keyboard recovery, against the owned synthetic instance."""
import json,pathlib,sys,hashlib
import pymysql
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1];D=pathlib.Path(sys.argv[1]).resolve();S=json.loads((D/'state.json').read_text());E=json.loads((D/'environment.json').read_text());C=json.loads((D/'credentials.json').read_text());O=S['origin'];OUT=ROOT/'docs/evidence/med-consultorios-init02'
assert D.name.startswith('mxmed-init02-') and S['source']==str(ROOT) and int(E['MXMED_DB_PORT'])!=3306
conn=pymysql.connect(host='127.0.0.1',port=int(E['MXMED_DB_PORT']),user=E['MXMED_DB_USER'],password=E['MXMED_DB_PASS'],database=E['MXMED_DB_NAME'],autocommit=True)
def snapshot():
 with conn.cursor() as q:
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
results=[]
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();c=b.new_context(ignore_https_errors=True,viewport={'width':1366,'height':900});p=c.new_page();p.goto(O+'/public/identity/acceso.php',wait_until='load');p.locator('[name=email]').fill(C['owner']['email']);p.locator('[name=password]').fill(C['owner']['password']);p.locator('[data-submit]').click();p.wait_for_url('**/private-app.php');enter(p)
  data=p.evaluate("async()=>await (await fetch('/api/agenda/index.php/consultorios?doctor_id=qa05-doctor')).json()")
  before=snapshot();held=[];writes=[]
  p.on('request',lambda r:writes.append(r.method) if r.url.startswith(O+'/') and r.method in ['POST','PUT','PATCH','DELETE'] else None)
  p.route('**/api/agenda/index.php/consultorios?*',lambda route:held.append(route))
  p.reload(wait_until='domcontentloaded');enter(p)
  expect(p.locator('#p-consultorio')).to_have_attribute('data-cons-load-state','LOADING');expect(p.locator('[data-cons-records]')).to_be_hidden();expect(p.locator('#p-consultorio')).to_have_attribute('aria-busy','true');p.screenshot(path=str(OUT/(engine+'-loading.png')))
  assert len(held)==1;held.pop().fulfill(status=503,json={'ok':False});expect(p.locator('[data-cons-load-retry]')).to_be_visible();p.locator('[data-cons-load-retry]').focus();p.keyboard.press('Enter');expect(p.locator('#p-consultorio')).to_have_attribute('data-cons-load-state','RETRYING');expect(p.locator('[data-cons-records]')).to_be_hidden()
  p.wait_for_timeout(100);assert len(held)==1;held.pop().fulfill(status=200,json=data);expect(p.locator('#p-consultorio')).to_have_attribute('data-cons-load-state','READY');p.wait_for_timeout(700)
  expect(p.locator('[data-cons-order-status] strong')).to_have_text('Orden confirmado.')
  assert snapshot()==before and writes==[]
  assert 'No se pudo ubicar automáticamente' not in p.locator('#sede1').inner_text()
  lookups=[]
  def geocode(route):
   lookups.append(route.request.post_data_json);route.fulfill(status=200,json={'ok':True,'data':{'candidates':[]}})
  p.route('**/api/agenda/index.php/geocode/google',geocode)
  p.locator('#sede1 [data-map-recalc-for]').click();p.wait_for_timeout(1000)
  assert lookups and all(v['doctor_id']=='qa05-doctor' and v['consultorio_id']=='1' for v in lookups)
  assert snapshot()==before and all(method=='POST' for method in writes)
  results.append({'engine':engine,'loading_visible':True,'retrying_visible':True,'keyboard_retry':True,'primary_presentation_unchanged':True,'explicit_map_lookup_preserved':True,'map_lookup_count':len(lookups),'unexpected_writes':0,'before':before,'after':snapshot()});c.close();b.close();print(engine,'visible states PASS',flush=True)
(OUT/'states.json').write_text(json.dumps(results,indent=2)+'\n');conn.close()
