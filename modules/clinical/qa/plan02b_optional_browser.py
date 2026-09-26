# Disposable Director fixture only: see PLAN02B_REVIEW.md.
from playwright.sync_api import sync_playwright,expect
from pathlib import Path
import os
import json
out=Path(os.environ['PLAN02B_ARTIFACTS']);base='http://127.0.0.1:18143';result={}
with sync_playwright() as p:
 b=p.chromium.launch(headless=True)
 def boot():
  ctx=b.new_context(viewport={'width':1440,'height':900},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(18000);page.goto(base+'/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide',wait_until='commit');page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1015"',timeout=55000);page.wait_for_timeout(1500);page.locator('[data-m7-section="plan"]').click();return ctx,page
 ctx,page=boot();writes=[];page.on('request',lambda r:writes.append(r.url) if r.method=='POST' and any(x in r.url for x in ['/documents','/appointments','/longitudinal/tasks']) else None)
 page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill('PLAN02B should be discarded');page.locator('[data-m7-section="assessment"]').click();expect(page.locator('.plan02b-leave')).to_be_visible();page.locator('[data-stay]').click();expect(page.locator('[data-plan02b]')).to_be_visible();assert page.locator('[data-ns-title]').input_value()=='PLAN02B should be discarded';page.locator('[data-m7-section="assessment"]').click();page.locator('[data-discard]').click();expect(page.locator('[data-plan02b]')).not_to_be_visible();assert writes==[];result['navigation_guard']='PASS stay preserves; discard writes nothing and navigates';ctx.close()
 for combo in ['o','a','f','oa','of','af']:
  ctx,page=boot();writes=[]
  def response(r):
   if r.request.method=='POST' and any(x in r.url for x in ['/documents','/appointments','/longitudinal/tasks']):writes.append({'path':r.url,'body':r.json(),'payload':r.request.post_data_json})
  page.on('response',response)
  if 'o' in combo:page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('PLAN02B OPTIONAL '+combo+' order')
  if 'f' in combo:
   page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill('PLAN02B OPTIONAL '+combo+' followup')
   if combo=='af':page.locator('[data-ns-due]').fill('2026-10-04T12:00')
  if 'a' in combo:
   page.locator('[data-ns="appointment"]').click();expect(page.locator('[data-ns-existing] option').nth(1)).to_be_attached();value=page.locator('[data-ns-existing] option').nth(1).get_attribute('value');page.locator('[data-ns-existing]').select_option(value)
  if combo=='af':
   page.locator('[data-ns="followup"]').click();page.locator('[data-ns-link]').select_option('yes');expect(page.locator('[data-ns-due]')).to_be_visible();assert page.locator('[data-ns-due]').input_value()=='2026-10-04T12:00';result['explicit_due_preserved']='PASS'
  assert not writes;page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns-message]')).to_contain_text('Próximos pasos registrados',timeout=30000);assert len(writes)==int('o' in combo)+int('f' in combo),(combo,writes);assert not any(x['path'].endswith('/appointments') for x in writes);assert all(x['body']['ok'] for x in writes);result[combo]=writes;(out/'optional-results.json').write_text(json.dumps(result,indent=2,ensure_ascii=False));ctx.close()
 print('PASS navigation guard, six optional combinations, existing appointment causes no booking, explicit deadline retained',flush=True);b.close()
