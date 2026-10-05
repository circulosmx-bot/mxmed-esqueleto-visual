import os
from playwright.sync_api import sync_playwright
BASE=os.environ.get('PATH_CAT02B_BASE','http://127.0.0.1:18148')
queries={'biop':'histopath_biopsy','histo':'histopath_biopsy','cit':'cyto_fna','paaf':'cyto_fna','baaf':'cyto_fna','ihq':'ihc_single_marker','inmuno':'ihc_single_marker','pas':'histochemical_special_stain','groc':'histochemical_special_stain','mass':'histochemical_special_stain','segunda':'pathology_outside_review','lamin':'pathology_outside_review','bloq':'pathology_outside_review','Papanicolau':'cyto_pap'}
with sync_playwright() as p:
 b=p.chromium.launch(headless=True);c=b.new_context(viewport={'width':1440,'height':900});c.add_cookies([{'name':'PHPSESSID','value':os.environ['PATH_CAT02B_SESSION'],'url':BASE}]);pg=c.new_page();errors=[];pg.on('pageerror',lambda e:errors.append(str(e)));pg.goto(BASE+'/index.html?review_patient=plan02ux&qa_tools=hide');pg.locator('[data-exp-tabs] [data-bs-target="#t-estudios"]').click();pg.locator('.vis06-intent-card').first.click();pg.locator('[data-hier-node="pathology"]').click();pg.locator('[data-hier-node="histopathology"]').click();s=pg.locator('.ordcomp [data-tax03c-search]')
 for q,key in queries.items():
  s.fill(q);pg.wait_for_function("() => document.querySelector('.ordcomp [data-tax03c-status]')?.textContent.includes('para esta búsqueda')")
  rows=pg.locator('.ordcomp .tax03c-family-results [data-tax03c-id]').count();family=pg.locator('.ordcomp .tax03c-family-results').inner_text();assert rows and family, (q,rows,family)
  assert pg.locator('.ordcomp .tax03c-family-results').is_visible(),q
 for q,label in [('tac','Imagenología'),('hba','Laboratorio'),('hol','Estudios funcionales')]:
  s.fill(q);pg.wait_for_function("() => document.querySelector('.ordcomp [data-tax03c-status]')?.textContent.includes('para esta búsqueda')")
  assert label in pg.locator('.ordcomp .tax03c-rescue-row').first.inner_text(),q
 assert not errors,errors
 print('QA_PATHOLOGY_SEARCH_REAL_RUNTIME=PASS')
 b.close()
