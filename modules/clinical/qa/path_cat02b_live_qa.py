import os
from playwright.sync_api import sync_playwright, expect
BASE=os.environ.get('PATH_CAT02B_BASE','http://127.0.0.1:18148')
leaves={'histopathology':2,'cervical_cytology':2,'fluid_cytology':3,'cytology_fna':1,'respiratory_cytology':2,'immunohistochemistry':2,'special_stains':1,'immunofluorescence':2,'outside_review':1}
with sync_playwright() as p:
 b=p.chromium.launch(headless=True)
 for width,height,sidebar in [(1440,900,'compact'),(1366,768,'compact'),(1366,768,'expanded'),(390,844,'mobile')]:
  c=b.new_context(viewport={'width':width,'height':height});c.add_cookies([{'name':'PHPSESSID','value':os.environ['PATH_CAT02B_SESSION'],'url':BASE}]);pg=c.new_page(); errors=[];writes=[]
  pg.on('pageerror',lambda err: errors.append(str(err)))
  pg.route('**/api/**',lambda route: route.continue_() if route.request.method in ('GET','HEAD','OPTIONS') else (writes.append(route.request.url),route.abort()))
  pg.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='domcontentloaded')
  pg.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();pg.locator('.vis06-intent-card').first.click();pg.locator('[data-hier-node="pathology"]').click()
  if sidebar=='expanded': pg.evaluate('document.querySelector("#mmSidebar [data-action=sidebar-toggle]").click()')
  assert pg.locator('[data-hier-node]').evaluate_all('(els)=>els.filter(e=>e.offsetParent!==null).map(e=>e.dataset.hierNode)')==['histopathology','cytology','immunohistochemistry','special_stains','immunofluorescence','outside_review']
  if width==1440:
   for leaf,n in leaves.items():
    pg.locator('[data-hier-node="'+('cytology' if leaf in ('cervical_cytology','fluid_cytology','cytology_fna','respiratory_cytology') else leaf)+'"]').click()
    if leaf in ('cervical_cytology','fluid_cytology','cytology_fna','respiratory_cytology'):pg.locator('[data-hier-node="'+leaf+'"]').click()
    expect(pg.locator('.ordcomp [data-tax03c-id]')).to_have_count(n)
    assert pg.locator('.ordcomp .ordcomp-full-catalog').count()==0
    assert not any(x.is_visible() for x in pg.locator('.ordcomp [data-tax03c-global]').all())
    if leaf not in ('cervical_cytology','fluid_cytology'):
     for index in range(n):
      pg.locator('.ordcomp [data-tax03c-id]').nth(index).click()
      expect(pg.locator('.ordcomp .pathology-parameter-host')).to_be_visible()
      expect(pg.locator('.ordcomp .pathology-specimen')).to_have_count(1)
      pg.locator('.ordcomp [data-tax03c-remove]').click()
    pg.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide',wait_until='domcontentloaded')
    pg.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();pg.locator('.vis06-intent-card').first.click();pg.locator('[data-hier-node="pathology"]').click()
  pg.locator('[data-hier-node="histopathology"]').click()
  expect(pg.locator('.ordcomp [data-tax03c-id]')).to_have_count(2)
  pg.locator('.ordcomp [data-tax03c-id]').first.click()
  if width==390: pg.locator('.ordcomp-mobile-bar button').click()
  expect(pg.locator('.pathology-parameter-host')).to_be_visible()
  pg.locator('.pathology-parameter-host').get_by_label('Sitio anatómico (descripción)').fill('Mama derecha')
  pg.locator('.pathology-parameter-host').get_by_label('Sitio anatómico (categoría)').select_option('BREAST')
  pg.locator('.pathology-parameter-host').get_by_label('Lateralidad (si aplica)').select_option('RIGHT')
  assert pg.locator('.ordcomp .pathology-item-summary').first.inner_text().find('Mama derecha')>=0
  assert pg.locator('.pathology-parameter-host .pathology-add').is_visible()
  pg.locator('.pathology-parameter-host .pathology-add').click()
  expect(pg.locator('.pathology-parameter-host .pathology-specimen')).to_have_count(2)
  assert pg.evaluate('document.documentElement.scrollWidth<=innerWidth'),('overflow',width,sidebar,pg.evaluate('document.documentElement.scrollWidth'))
  assert not errors and not writes,(errors,writes)
  print('QA_LIVE_'+str(width)+'x'+str(height)+'_'+sidebar+'=PASS')
  c.close()
 b.close()
