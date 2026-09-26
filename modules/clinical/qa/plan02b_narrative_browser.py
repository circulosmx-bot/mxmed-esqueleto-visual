# Disposable Director fixture only: see PLAN02B_REVIEW.md.
from playwright.sync_api import sync_playwright,expect
from pathlib import Path
import os
import json
out=Path(os.environ['PLAN02B_ARTIFACTS']);base='http://127.0.0.1:18143';report={};writes=[]
with sync_playwright() as p:
 b=p.chromium.launch(headless=True);ctx=b.new_context(viewport={'width':1440,'height':900},timezone_id='America/Mexico_City');page=ctx.new_page();page.set_default_timeout(22000)
 page.on('pageerror',lambda e:print('JSERROR',e,flush=True))
 def response(r):
  if r.request.method=='POST' and ('/documents' in r.url or r.url.endswith('/appointments') or '/longitudinal/tasks' in r.url):
   writes.append({'url':r.url,'http':r.status,'payload':r.request.post_data_json,'key':r.request.headers.get('idempotency-key'),'body':r.json()});(out/'full-writes.json').write_text(json.dumps(writes,indent=2))
 page.on('response',response)
 page.goto(base+'/index.html?review_patient=plan02&review_encounter=open&qa_tools=hide',wait_until='commit');page.wait_for_function('document.querySelector("#m7-workspace [data-m7-body]")?.dataset.encounterId==="1015"',timeout=55000);page.locator('[data-m7-section="plan"]').click();expect(page.locator('[data-plan02b]')).to_be_visible();page.screenshot(path=str(out/'compact.png'))
 ed=page.locator('[data-m7-editor-text]');original=ed.input_value();draft=original+'\nPLAN02B temporary unsaved narrative';ed.fill(draft)
 page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill('PLAN02B narrative preservation QA');page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns-message]')).to_contain_text('Próximos pasos registrados');assert ed.input_value()==draft;assert len(writes)==1 and '/longitudinal/tasks' in writes[0]['url'];assert page.locator('#m7-workspace [data-m7-body]').get_attribute('data-encounter-state')=='open';ed.fill(original);print('PASS unsaved Plan narrative retained, only explicit task writer executed, encounter OPEN',flush=True);b.close()
