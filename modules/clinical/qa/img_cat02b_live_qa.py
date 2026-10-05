import os
from playwright.sync_api import sync_playwright, expect
BASE=os.environ.get('IMG_CAT02B_LIVE_BASE','http://127.0.0.1:18148')
SESSION=os.environ['IMG_CAT02B_LIVE_SESSION']
queries=[('cadera','rx_hip'),('tac sen','ct_sinuses'),('rm lum','mr_lumbar_spine'),('rx pie','rx_foot'),('muñeca','rx_wrist'),('codo','rx_elbow'),('rm cerv','mr_cervical_spine'),('tac cue','ct_neck'),('cta','cta_head_neck'),('mra','mra_brain'),('rm pelvis','mr_pelvis'),('tomos','breast_tomosynthesis'),('torac','rx_tspine'),('gamm renal','nm_renal_scan'),('perf mio','nm_myocardial_perfusion')]
with sync_playwright() as p:
 browser=p.chromium.launch(headless=True)
 for width,height,sidebar in [(1440,900,'compact'),(1366,768,'compact'),(1366,768,'expanded'),(390,844,'mobile')]:
  context=browser.new_context(viewport={'width':width,'height':height});context.add_cookies([{'name':'PHPSESSID','value':SESSION,'url':BASE}]);page=context.new_page();errors=[];writes=[]
  page.on('pageerror',lambda e:errors.append(str(e)))
  page.route('**/api/**',lambda route:route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else (writes.append(route.request.url),route.abort()))
  page.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='domcontentloaded')
  page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();page.locator('.vis06-intent-card').first.click();page.locator('[data-hier-node="imaging"]').click()
  if sidebar=='expanded':page.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
  if width==1440:
   for leaf,n in [('radiography',18),('tomography',7),('magnetic_resonance',8),('nuclear',5),('breast_imaging',3)]:
    page.locator(f'[data-hier-node="{leaf}"]').click()
    expect(page.locator('.ordcomp [data-tax03c-search]')).to_be_visible()
    expect(page.locator('.ordcomp .tax03c-status')).to_contain_text(f'{n} estudios',timeout=10000)
    assert not page.locator('.ordcomp [data-tax03c-global]').is_visible()
    page.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='domcontentloaded')
    page.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();page.locator('.vis06-intent-card').first.click();page.locator('[data-hier-node="imaging"]').click()
   page.locator('[data-hier-node="radiography"]').click()
   search=page.locator('.ordcomp [data-tax03c-search]')
   for query,key in queries:
    search.fill(query)
    row=page.locator(f'.ordcomp [data-tax03c-key="{key}"]')
    expect(row).to_be_visible(timeout=10000)
    row.click()
    expect(page.locator('.ordcomp .imaging-parameter-panel')).to_be_visible()
    assert page.locator('.ordcomp .imaging-item-summary').count()>=1
    page.locator('.ordcomp [data-tax03c-remove]').last.click()
   print('QA_LIVE_15_SEARCH_SELECT_PANEL=PASS',flush=True)
   for query in ('hba','psa','ihq','hol'):
    search.fill(query)
    expect(page.locator('.ordcomp .tax03c-rescue-results')).to_be_visible(timeout=10000)
   print('QA_LIVE_CROSS_FAMILY_RESCUE=PASS',flush=True)
  else:
   page.locator('[data-hier-node="radiography"]').click()
  search=page.locator('.ordcomp [data-tax03c-search]');search.fill('cadera');row=page.locator('.ordcomp [data-tax03c-key="rx_hip"]');expect(row).to_be_visible(timeout=10000);row.click()
  if width==390:page.locator('.ordcomp-mobile-bar button').click()
  panel=page.locator('.ordcomp .imaging-parameter-panel');expect(panel).to_be_visible();panel.locator('[data-imaging-field="laterality"]').select_option('RIGHT')
  expect(page.locator('.ordcomp .imaging-item-summary')).to_contain_text('Derecha')
  assert page.evaluate('document.documentElement.scrollWidth <= innerWidth'),(width,sidebar,page.evaluate('document.documentElement.scrollWidth'))
  assert not errors and not writes,(errors,writes)
  print(f'QA_LIVE_{width}x{height}_{sidebar}=PASS',flush=True)
  context.close()
 browser.close()
