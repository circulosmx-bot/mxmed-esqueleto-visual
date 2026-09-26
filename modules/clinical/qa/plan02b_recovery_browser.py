# Disposable Director fixture only: see PLAN02B_REVIEW.md.
from playwright.sync_api import sync_playwright,expect
from pathlib import Path
import os
import json,uuid
out=Path(os.environ['PLAN02B_ARTIFACTS']);base='http://127.0.0.1:18143';result={}
with sync_playwright() as p:
 b=p.chromium.launch(headless=True)
 def boot():
  ctx=b.new_context(viewport={'width':1440,'height':900},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(20000);page.on('pageerror',lambda e:print('JSERROR',e,flush=True));page.goto(base+'/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide',wait_until='commit');page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1015"',timeout=55000);page.locator('[data-m7-section="plan"]').click();expect(page.locator('[data-plan02b]')).to_be_visible();page.wait_for_timeout(1500);return ctx,page
 def prepare(page,tag,date,two=False):
  page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill(tag+' order A');page.locator('[data-order-field="summary"]').fill('QA sintética')
  if two:page.locator('[data-ns="add-order"]').click();page.locator('[data-order-field="title"]').nth(1).fill(tag+' order B')
  page.locator('[data-ns="appointment"]').click();page.locator('[name="ns-mode"][value="new"]').check();expect(page.locator('[data-ns-location] option[value="1"]')).to_be_attached();page.locator('[data-ns-date]').fill(date);page.locator('[data-ns-date]').press('Tab');page.locator('[data-ns-location]').select_option('1');page.locator('[data-ns="availability"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();page.locator('[data-ns-slot]').first.click();page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill(tag+' followup');page.locator('[data-ns-link]').select_option('yes')
 def saved(page):return page.evaluate('JSON.parse(sessionStorage.getItem(Object.keys(sessionStorage).find(k=>k.startsWith("mxmed-plan02b:"))))')
 ctx,page=boot();prepare(page,'PLAN02B COLLISION',os.environ['PLAN02B_COLLISION_DATE']);draft=saved(page);slot=draft['appointment']['selection'];payload=dict(doctor_id='1',consultorio_id='1',patient_id='p_plan02_review',start_at=slot['start_at'],end_at=slot['end_at'],modality='in_person',channel_origin='doctor',created_by_role='doctor',created_by_id='1');r=ctx.request.post(base+'/api/agenda/index.php/appointments',data=payload,headers={'Idempotency-Key':str(uuid.uuid4())});assert r.json()['ok'];writes=[]
 def capture(r):
  if r.request.method=='POST' and any(x in r.url for x in ['/documents','/appointments','/longitudinal/tasks']):writes.append({'path':r.url,'body':r.json(),'key':r.request.headers.get('idempotency-key'),'payload':r.request.post_data_json});(out/'collision-writes.json').write_text(json.dumps(writes,indent=2))
 page.on('response',capture);page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns-message]')).to_contain_text('Se conservaron',timeout=30000);s=saved(page);assert s['orders'][0]['state']=='SUCCESS' and s['appointment']['state']=='FAILED' and s['followup']['state']=='BLOCKED_BY_DEPENDENCY';assert not any('/longitudinal/tasks' in r['path'] for r in writes);oldkey=s['appointment']['key'];orderid=s['orders'][0]['result']['document_id'];page.screenshot(path=str(out/'collision.png'))
 page.locator('[data-ns="appointment"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();page.locator('[data-ns-slot]').first.click();assert saved(page)['appointment']['key']!=oldkey;page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns-message]')).to_contain_text('Próximos pasos registrados',timeout=30000);s=saved(page);assert s['orders'][0]['result']['document_id']==orderid and sum('/documents' in r['path'] for r in writes)==1;assert s['followup']['result']['item']['appointment_id']==s['appointment']['result']['appointment_id'];result['collision']=s;ctx.close()
 ctx,page=boot();prepare(page,'PLAN02B AMBIGUOUS',os.environ['PLAN02B_RECOVERY_DATE'],two=True);lost={};attempts=[]
 def intercept(route):
  req=route.request
  if req.method!='POST':route.continue_();return
  kind='order' if req.url.endswith('/documents') else 'appointment' if req.url.endswith('/appointments') else 'followup' if req.url.endswith('/longitudinal/tasks') else None
  if not kind:route.continue_();return
  payload=req.post_data_json;key=req.headers['idempotency-key'];attempts.append({'kind':kind,'key':key,'payload':payload})
  lose=kind not in lost and (kind!='order' or payload['title'].endswith('B'))
  if lose:
   response=route.fetch();v=response.json();assert v['ok'],v;lost[kind]={'key':key,'payload':payload,'body':v};(out/'lost-successes.json').write_text(json.dumps(lost,indent=2));route.abort('failed')
  else:route.continue_()
 page.route('**/api/**',intercept)
 for i in range(3):
  page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns="confirm"]')).to_have_text('Confirmar acciones',timeout=30000)
  s=saved(page)
  if i<2:assert any(a and a['state']!='SUCCESS' for a in s['orders']+[s['appointment'],s['followup']]),s
 assert all(a['state']=='SUCCESS' for a in s['orders']+[s['appointment'],s['followup']]);assert len(attempts)==7,len(attempts)
 for kind,v in lost.items():
  matching=[a for a in attempts if a['key']==v['key']];assert len(matching)==2 and matching[0]['payload']==matching[1]['payload']
 assert s['orders'][1]['result']['document_id']==lost['order']['body']['data']['document_id'];assert s['appointment']['result']['appointment_id']==lost['appointment']['body']['data']['appointment_id'];assert s['followup']['result']['item']['task_id']==lost['followup']['body']['data']['item']['task_id'];result['ambiguous']={'state':s,'attempts':attempts,'lost':lost};page.screenshot(path=str(out/'recovered.png'));ctx.close()
 (out/'recovery-results.json').write_text(json.dumps(result,indent=2,ensure_ascii=False));print('PASS real collision/reselection + lost responses for all three writers; no success resubmitted',flush=True);b.close()
