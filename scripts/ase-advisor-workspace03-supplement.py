#!/usr/bin/env python3
"""Read/denial/late-response smoke only against a verified owned WORKSPACE03 preview."""
import argparse,importlib.util,json,pathlib
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m
control=module('ws03_control',ROOT/'scripts/ase-advisor-workspace03-review.py')
qa=module('ws03_smoke',ROOT/'scripts/ase-advisor-workspace03-browser.py')
def main():
 parser=argparse.ArgumentParser();parser.add_argument('owned');args=parser.parse_args();owned,approval=control.owned_configuration(args.owned)
 info,origin,expiry,record=control.verified_preview(owned,approval)
 out=ROOT/'docs/evidence/ase-advisor-workspace03';results={'checks':[],'page_errors':[]}
 def check(name,ok):
  results['checks'].append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
  (out/'supplement.json').write_text(json.dumps(results,indent=2)+'\n');assert ok,name
 with sync_playwright() as pw:
  b,c,p=qa.review.native_browser(pw);p.on('pageerror',lambda e:results['page_errors'].append(str(e)))
  qa.review.login(p,origin,record['email'],(owned/'applicant-password').read_text().strip());expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  start=qa.pending(p,'workspace_aurora');p.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click()
  expect(p.locator('#tab-images .tab-count')).to_have_text('3');expect(p.locator('#tab-requests .tab-count')).to_have_text('1')
  check('Final source renders four visible physician items, section counts and seven actionable items', '4 pendientes' in p.locator('#physician-statuses').inner_text() and p.locator('#pending-count').inner_text()==str(start['actionable_count']) and start['actionable_count']==7)
  snapshot=qa.ws.previous.read(p,'/api/internal/workspace/read.php?doctor_id=workspace_aurora')['body']['data'];sid=snapshot['pending_items'][0]['submission_id']
  denied=qa.ws.previous.post(p,'/api/internal/workspace/media.php?mode=approve&doctor_id=workspace_aurora',{'submission_id':sid,'csrf':'invalid'})
  check('Failed canonical mutation does not decrement counts',denied['status']==403 and qa.pending(p,'workspace_aurora')['context']==start['context'])
  check('Repeated authoritative reads do not duplicate or decrement work',all(qa.pending(p,'workspace_aurora')['context']==start['context'] for _ in range(3)))
  held=[]
  def delay(route):held.append((route,route.fetch()))
  p.locator('#pending-toggle').click();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  p.route('**/requests.php?mode=pending&doctor_id=workspace_aurora',delay)
  p.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();p.wait_for_timeout(350)
  p.locator('#discovery-list [data-doctor-id="workspace_bruno"]').click();expect(p.locator('#physician-name')).to_contain_text('Bruno')
  for route,response in held:
   try:route.fulfill(response=response)
   except Exception:pass
  p.unroute('**/requests.php?mode=pending&doctor_id=workspace_aurora',delay);p.wait_for_timeout(200)
  check('Late physician A count response cannot populate physician B',bool(held) and 'Bruno' in p.locator('#physician-name').inner_text() and p.locator('#work-panel').get_attribute('data-doctor-id')=='workspace_bruno' and p.locator('#tab-requests .tab-count').count()==0 and p.locator('#tab-images .tab-count').inner_text()=='4')
  def unavailable(route):route.fulfill(status=503,content_type='application/json',body='{"ok":false,"error":"unavailable"}')
  p.route('**/requests.php?mode=pending*',unavailable);qa.tab(p,'images');p.locator('#refresh-images').click();expect(p.locator('#pending-count')).to_have_text('—')
  check('Unavailable aggregate is not represented as zero and does not block Media',p.locator('#media-items button').count()==4 and p.locator('#images-panel').is_visible() and 'pendientes visibles' not in p.locator('#physician-statuses').inner_text())
  p.unroute('**/requests.php?mode=pending*',unavailable);p.locator('#refresh-images').click();expect(p.locator('#pending-count')).to_have_text('7')
  check('Authoritative count recovery restores exact current values',p.locator('#tab-images .tab-count').inner_text()=='4')
  # Native view of grouped inbox plus retained physician context.
  p.locator('#pending-toggle').click();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false');p.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();qa.tab(p,'requests');p.locator('#pending-toggle').click();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false');p.evaluate('scrollTo(0,0)')
  p.screenshot(path=str(out/'screens/native-grouped-inbox.png'))
  db,dc,d=qa.review.native_browser(pw)
  def grants(desired):
   qa.review.login(d,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),qa.review.DIRECTOR);qa.old.selected(d,record)
   for group in ['requests','media']:
    if not d.locator('#use-'+group).is_visible():d.locator('[data-expand="use-'+group+'"]').click()
   changed=False
   for cap in qa.CAPS:
    box=d.locator('[data-capability="'+cap+'"]');value=cap in desired
    if box.is_checked()!=value:box.set_checked(value);changed=True
   if changed:
    d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=25000)
  try:
   grants(qa.ws.MEDIA+[qa.SUMMARY,'physician_request_read'])
   p.reload();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false');p.locator('#discovery-list [data-doctor-id="workspace_aurora"]').click();qa.tab(p,'images');p.locator('#media-items button').first.click();expect(p.locator('#review-image')).to_be_visible()
   grants([x for x in qa.ws.MEDIA if x!='media_review_read']+[qa.SUMMARY,'physician_request_read'])
   p.locator('#pending-toggle').click();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false')
   check('Live Media read revocation removes old protected rows and blob without reload',p.locator('#media-items button').count()==0 and p.locator('#summary-pending [data-submission-id]').count()==0 and p.locator('#review-image').get_attribute('src') is None)
   check('Retained request scope exposes only authorized Media summary after revocation',p.locator('#restricted-panel').is_visible() and p.locator('#tab-images .tab-count').inner_text()=='3' and p.locator('#pending-count').inner_text()=='0')
  finally:
   grants(qa.ws.MEDIA+[qa.SUMMARY]);db.close()
  p.locator('#close-discovery').click();p.locator('#pending-toggle').click();expect(p.locator('#discovery-list')).to_have_attribute('aria-busy','false')
  check('Regrant restores current operational data and exact seven-item queue',p.locator('#media-items button').count()==3 and p.locator('#pending-count').inner_text()=='7')
  check('No JavaScript errors during denial, late response and unavailable-reader recovery',results['page_errors']==[])
  results['result']='PASS';(out/'supplement.json').write_text(json.dumps(results,indent=2)+'\n');b.close()
if __name__=='__main__':main()
