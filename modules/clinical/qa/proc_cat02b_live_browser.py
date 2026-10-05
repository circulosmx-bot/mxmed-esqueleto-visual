"""Read-only PROC-CAT02B interaction on the actual Director runtime at port 18148."""
import os
from playwright.sync_api import expect,sync_playwright

BASE='http://127.0.0.1:18148'
SESSION=os.environ['PROCCAT02B_REVIEW_SESSION']
with sync_playwright() as playwright:
 browser=playwright.chromium.launch(headless=True)
 for width,height,sidebar in [(1440,900,'compact'),(1366,768,'compact'),(1366,768,'expanded'),(390,844,'mobile')]:
  context=browser.new_context(viewport={'width':width,'height':height})
  context.add_cookies([{'name':'PHPSESSID','value':SESSION,'url':BASE}])
  page=context.new_page();errors=[];writes=[];catalog=[]
  page.on('pageerror',lambda error:errors.append(str(error)))
  page.on('response',lambda response:catalog.append(response.status) if '/study-types?' in response.url else None)
  page.route('**/api/**',lambda route:route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else (writes.append(route.request.url),route.abort()))
  page.goto(BASE+'/index.html?review_patient=plan02ux',wait_until='domcontentloaded')
  if sidebar=='expanded':page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
  page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click()
  page.locator('.vis06-intent-card').first.click()
  page.wait_for_selector('[data-hier-node="procedures"]',timeout=20000)
  page.locator('[data-hier-node="procedures"]').click()
  for leaf,keys in [('gynecology',['colposcopy_diagnostic','hysteroscopy_diagnostic']),('urology',['cystoscopy_diagnostic'])]:
   expect(page.locator(f'[data-hier-node="{leaf}"]')).to_be_visible()
   page.locator(f'[data-hier-node="{leaf}"]').click()
   for key in keys:expect(page.locator(f'.ordcomp [data-tax03c-key="{key}"]')).to_be_visible()
   assert page.locator('.ordcomp [data-ordcomp-featured]').count()==0
   assert page.locator('.ordcomp-full-catalog').count()==0
   if width==1440:
    cases=([('colpos','colposcopy_diagnostic'),('video colpo','colposcopy_diagnostic'),('histero','hysteroscopy_diagnostic'),('panendo','egd_eda_base'),('bronco','bronchoscopy_base'),('laringo','laryngoscopy_base'),('hba','hba1c')]
           if leaf=='gynecology' else [('cisto','cystoscopy_diagnostic'),('cistou','cystoscopy_diagnostic'),('video cisto','cystoscopy_diagnostic'),('colpos','colposcopy_diagnostic')])
    for term,key in cases:
     page.locator('.ordcomp [data-tax03c-search]').fill(term)
     if term=='hba':
      expect(page.locator('.ordcomp')).to_contain_text('Coincidencias en otras familias',timeout=10000)
      expect(page.locator('.ordcomp')).to_contain_text('Hemoglobina glicosilada')
     else:expect(page.locator(f'.ordcomp [data-tax03c-key="{key}"]')).to_be_visible(timeout=10000)
    page.locator('.ordcomp [data-tax03c-search]').fill('')
    for key in keys:expect(page.locator(f'.ordcomp [data-tax03c-key="{key}"]')).to_be_visible()
   for key in keys:page.locator(f'.ordcomp [data-tax03c-key="{key}"]').click()
   summary=page.locator('.ordcomp-summary').inner_text()
   assert ('Diagnóstico ginecológico (2)' if leaf=='gynecology' else 'Diagnóstico urológico (1)') in summary,summary
   assert page.evaluate('document.documentElement.scrollWidth<=innerWidth'),(width,sidebar,leaf,page.evaluate('document.documentElement.scrollWidth'))
   if width in (1440,390):page.screenshot(path=f'/tmp/proc-cat02b-{leaf}-{width}x{height}.png',full_page=True)
   page.get_by_role('button',name='Volver a PROCEDIMIENTOS DIAGNÓSTICOS').click()
  assert catalog and all(code==200 for code in catalog),catalog
  assert not errors and not writes,(errors,writes)
  print(f'QA_REAL_RUNTIME_{width}x{height}_{sidebar}_LEAVES_SELECTOR_PREPARED_NO_OVERFLOW=PASS',flush=True)
  context.close()
 browser.close()
