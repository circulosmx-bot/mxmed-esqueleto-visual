#!/usr/bin/env python3
"""Targeted step-up footer and manual login regression; no decisions or fixture writes."""
import pathlib,importlib.util,json,sys
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
review=module('responsive_review',ROOT/'scripts/staff-doc-vis02-review.py')
owned,approval=review.owned_configuration(sys.argv[1]);info,origin,expiry,record=review.verified_preview(owned,approval)
native=module('responsive_native',ROOT/'scripts/ase-advisor-workspace-review.py')
cid=json.loads((owned/'staff06-martha.json').read_text())['case'];out=ROOT/'docs/evidence/staff-doc-vis02';results=[]
with sync_playwright() as pw:
 for engine,width,height in [('chromium',1366,420),('chromium',390,844),('webkit',390,844)]:
  browser=getattr(pw,engine).launch();ctx=browser.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});p=ctx.new_page()
  def login():native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  login()
  if engine=='chromium' and width==1366:
   p.locator('#staff-logout').click() if p.locator('#staff-logout').count() else p.get_by_role('button',name='Cerrar sesión',exact=True).click()
   expect(p.locator('#login-email')).to_be_visible();login()
   results.append({'name':'Actual Director login/logout/login','pass':True})
  if width<640:p.locator('#show-directory').click()
  p.locator('[data-case="'+cid+'"]').click();expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
  action=p.locator('[data-review-action=CORRECTIONS]')
  for details in action.locator('xpath=ancestor::details').all():
   if not details.evaluate('(e)=>e.open'):details.locator('summary').first.click()
  action.click();expect(p.locator('#staff-stepup-title')).to_have_text('Solicitar corrección de documentos');expect(p.locator('#staff-corrections-channel')).to_contain_text('No se envía correo')
  p.locator('#staff-stepup .team-dialog-body').evaluate('(e)=>e.scrollTop=e.scrollHeight');expect(p.locator('#staff-challenge')).to_be_in_viewport();expect(p.locator('#cancel-staff-stepup')).to_be_in_viewport()
  assert p.evaluate('document.documentElement.scrollWidth<=innerWidth')
  p.screenshot(path=str(out/'screens'/('correction-'+engine+'-'+str(width)+'x'+str(height)+'.png')))
  p.locator('#cancel-staff-stepup').click();expect(p.locator('#staff-stepup')).not_to_be_visible();expect(p.locator('#detail-title')).to_contain_text('Martha')
  results.append({'name':engine+' '+str(width)+'x'+str(height)+' correction description, reachable challenge/footer, cancellation preserves selection','pass':True});browser.close()
(out/'responsive-stepup.json').write_text(json.dumps({'result':'PASS','checks':results},indent=2));print('PASS',len(results),'targeted access and correction modal checks')
