"""Read-only shell geometry/guard QA; session supplied locally, never committed."""
import os
from playwright.sync_api import sync_playwright,expect
with sync_playwright() as p:
 b=p.chromium.launch(headless=True)
 for w,h in [(1440,900),(1366,768),(390,844)]:
  page=b.new_page(viewport={'width':w,'height':h});page.context.add_cookies([{'name':'PHPSESSID','value':os.environ['ORD_COMP01_REVIEW_SESSION'],'domain':'127.0.0.1','path':'/'}])
  page.route('**/api/**',lambda route:route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else route.abort())
  page.goto('http://127.0.0.1:18148/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='domcontentloaded')
  page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();page.locator('.vis06-intent-card').first.click()
  page.locator('[data-hier-node="laboratory"]').click();page.locator('[data-hier-node="urine"]').click()
  expect(page.locator('[data-ordcomp-featured] button')).to_have_count(6)
  expect(page.locator('[data-ordcomp-featured] button').first).to_be_visible()
  page.locator('[data-ordcomp-featured] button').first.click()
  if w<768:page.get_by_role('button',name='Ver órdenes',exact=True).click()
  for state in (['compact','expanded'] if w>768 else ['mobile']):
   if state=='expanded':page.evaluate("document.querySelector('#mmSidebar [data-action=sidebar-toggle]').click()")
   page.wait_for_timeout(350)
   print(w,state,page.evaluate("({width:innerWidth,scroll:document.documentElement.scrollWidth,columns:getComputedStyle(document.querySelector('.ordcomp-workspace')).gridTemplateColumns,summary:document.querySelector('.ordcomp-summary').getBoundingClientRect().toJSON()})"),flush=True)
   page.locator('.ordcomp').scroll_into_view_if_needed();page.screenshot(path=f'/tmp/ordcomp01_live_{w}_{state}.png',full_page=True)
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth')
  page.locator('[data-exp-tabs] [data-bs-target="#t-resumen-longitudinal"]').click()
  expect(page.locator('.mx-patient-leave-dialog[open]')).to_be_visible()
  page.locator('.mx-patient-leave-dialog [data-stay]').click()
  expect(page.locator('.ordcomp')).to_be_visible()
  page.close()
 b.close()
