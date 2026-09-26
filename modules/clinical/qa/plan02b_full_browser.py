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
 page.locator('[data-ns="orders"]').click();page.locator('[data-order-field="title"]').fill('PLAN02B QA — Estudio A');page.locator('[data-order-field="summary"]').fill('Indicación sintética A');page.locator('[data-ns="add-order"]').click();page.locator('[data-order-field="title"]').nth(1).fill('PLAN02B QA — Estudio B');page.locator('[data-order-field="summary"]').nth(1).fill('Indicación sintética B')
 page.locator('[data-ns="appointment"]').click();page.locator('[name="ns-mode"][value="new"]').check();expect(page.locator('[data-ns-location] option[value="1"]')).to_be_attached();page.locator('[data-ns-days]').fill('10');page.locator('[data-ns-days]').press('Tab');report['date']=page.locator('[data-ns-date]').input_value();report['exact_date']=page.locator('[data-ns-exact]').inner_text();assert report['date']=='2026-10-05';page.locator('[data-ns-location]').select_option('1');page.locator('[data-ns="availability"]').click();expect(page.locator('[data-ns-slot]').first).to_be_visible();assert page.locator('[data-ns-slot][aria-pressed="true"]').count()==0;page.locator('[data-ns-slot]').first.click()
 page.locator('[data-ns="followup"]').click();page.locator('[data-ns-title]').fill('Revisar resultados de los estudios solicitados. PLAN02B QA');page.locator('[data-ns-link]').select_option('yes');expect(page.locator('[data-ns-due]')).not_to_be_visible();assert page.locator('[data-ns-due]').input_value()==''
 for w,h in [(1440,900),(1366,768),(820,1180),(390,844)]:
  page.set_viewport_size({'width':w,'height':h});page.locator('[data-plan02b]').scroll_into_view_if_needed();report[str(w)]=page.evaluate('document.documentElement.scrollWidth<=innerWidth');assert report[str(w)],(w,page.evaluate('document.documentElement.scrollWidth'));page.screenshot(path=str(out/f'review-{w}.png'))
 page.set_viewport_size({'width':1440,'height':900});assert len(writes)==0;page.locator('[data-ns="confirm"]').click();expect(page.locator('[data-ns-message]')).to_have_text('Próximos pasos registrados. La consulta sigue abierta.',timeout=30000);assert len(writes)==4,writes;assert all(r['body']['ok'] for r in writes);report['writes']=writes;page.locator('[data-plan02b]').scroll_into_view_if_needed();page.screenshot(path=str(out/'success.png'))
 (out/'full-results.json').write_text(json.dumps(report,indent=2,ensure_ascii=False));print('PASS integrated full flow',[(r['url'],r['http']) for r in writes],flush=True);b.close()
