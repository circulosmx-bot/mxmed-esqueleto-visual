#!/usr/bin/env python3
"""Visible no-results and stale-selection checks on final owned UI."""
import pathlib,json,sys,importlib.util,os
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,file):
 s=importlib.util.spec_from_file_location(name,ROOT/file);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
os.umask(0o077)
review=module('review06_final','scripts/staff-control06-review.py');owned,approval=review.owned_configuration(sys.argv[1]);info,origin,expiry,record=review.verified_preview(owned,approval)
native=module('native06_final','scripts/ase-advisor-workspace-review.py');results=[]
def check(name,ok):
 results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok,name
with sync_playwright() as pw:
 for engine in ['chromium','webkit']:
  b=getattr(pw,engine).launch();p=b.new_page(ignore_https_errors=True,viewport={'width':1440,'height':900});p.on('dialog',lambda d:d.accept())
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),review.DIRECTOR)
  for width,height in [(1440,900),(1366,768),(390,844)]:
   p.set_viewport_size({'width':width,'height':height})
   if width<640:p.locator('#show-directory').click()
   p.locator('#add-member').click();expect(p.locator('#new-operation_state')).to_be_enabled();p.locator('#new-operation_state-trigger').focus();p.keyboard.type('zzzz',delay=60)
   notice=p.locator('.team-select-search-status').filter(has_text='Sin coincidencias');expect(notice).to_be_in_viewport();box=notice.bounding_box();menu=p.locator('.team-select-options:popover-open').bounding_box()
   check(engine+' '+str(width)+' no-results text actually visible inside constrained popup',box['y']>=menu['y'] and box['y']+box['height']<=menu['y']+menu['height']+1 and menu['y']+menu['height']<=height+1)
   p.keyboard.press('Escape');p.keyboard.press('Escape');expect(p.locator('#onboarding')).not_to_be_visible()
  p.set_viewport_size({'width':1440,'height':900})
  martha=json.loads((owned/'staff06-martha.json').read_text());candela=json.loads((owned/'staff06-candela.json').read_text())
  # Delay an authorized READ response in the browser, not the server or mutation transport.
  p.evaluate('''id=>{const original=window.fetch.bind(window);window.fetch=async(url,options)=>{const response=await original(url,options);let body;try{body=JSON.parse(options?.body||'{}');}catch(_){}if(body?.action==='case'&&body.input.case_id===id)await new Promise(resolve=>setTimeout(resolve,1200));return response;};}''',martha['case'])
  p.locator('[data-case="'+martha['case']+'"]').click();p.locator('[data-case="'+candela['case']+'"]').click();expect(p.locator('#detail-title')).to_contain_text('Candela');p.wait_for_timeout(1500)
  check(engine+' stale pending response cannot replace the selected case','Candela' in p.locator('#detail-title').inner_text() and p.locator('[data-case="'+candela['case']+'"]').get_attribute('aria-pressed')=='true')
  lucia=json.loads((owned/'staff06-lucia.json').read_text());p.locator('[data-case="'+lucia['case']+'"]').click();expect(p.locator('#invitation-state')).to_have_text('Invitación vencida — requiere una nueva emisión autorizada')
  check(engine+' expired invitation has precise contextual copy and explicit reissue',p.locator('#reissue-invitation').count()==1 and p.locator('#simulate-onboarding').count()==0)
  b.close()
(ROOT/'docs/evidence/staff-control06/keyboard-final.json').write_text(json.dumps({'result':'PASS','checks':results},indent=2))
