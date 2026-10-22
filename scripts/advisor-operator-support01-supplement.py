import pathlib,json,importlib.util,sys
from playwright.sync_api import sync_playwright,expect
r=pathlib.Path(__file__).resolve().parents[1];o=pathlib.Path(sys.argv[1]).resolve();assert o.parent==pathlib.Path('/private/tmp') and o.name.startswith('mxmed-ase-adm02imp01-');assert json.loads((o/'workflow-approval.json').read_text())['root']==str(r);origin=json.loads((o/'workspace-runtime.json').read_text())['origin'];s=importlib.util.spec_from_file_location('native',r/'scripts/ase-advisor-workspace-review.py');n=importlib.util.module_from_spec(s);s.loader.exec_module(n);password=(o/'fixture-password').read_text().strip();results=[]
def check(name,ok):results.append({'name':name,'pass':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True);assert ok
with sync_playwright() as w:
 b=w.chromium.launch();a=b.new_page(ignore_https_errors=True);d=b.new_page(ignore_https_errors=True);n.login(a,origin,'qa_target@example.test',password);n.login(d,origin,'qa_director@example.test',password,'/internal/media-review/index.php?section=equipo')
 def get(url):return a.evaluate('async path=>{let r=await fetch(path,{cache:"no-store"});return {status:r.status,body:await r.json()}}',url)
 api='/api/internal/workspace/requests.php';before=get(api+'?mode=pending&doctor_id=op01_support')['body']['data'];support=get(api+'?mode=operators&doctor_id=op01_support');token=support['body']['data']['csrf_token']
 status=a.evaluate('async token=>{let r=await fetch("/api/internal/workspace/requests.php",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-Token":token},body:JSON.stringify({action:"invite",input:{}})});return r.status}',token);check('Authenticated CSRF cannot invoke operator mutation',status==400)
 request=get(api+'?mode=read');check('Requests consumer hides ungranted requests',request['status']==200 and request['body']['data']['requests']==[])
 check('History consumer remains available in authorized profile',get(api+'?mode=history&doctor_id=op01_support')['status']==200)
 after=get(api+'?mode=pending&doctor_id=op01_support')['body']['data'];check('Support reads leave pending counters unchanged',all(before[k]==after[k] for k in ['actionable_count','group_count','visible_unresolved_count']))
 def media(on):
  d.goto(origin+'/internal/media-review/index.php?section=equipo');expect(d.locator('#filter-advisors')).to_be_enabled();d.locator('#filter-advisors').click();expect(d.locator('#directory-content')).to_have_attribute('aria-busy','false');d.locator('[data-member="qa_target"]').click();box=d.locator('[data-capability="media_review_read"]')
  for det in box.locator('xpath=ancestor::details').all():
   if not det.evaluate('(e)=>e.open'):det.locator('summary').first.click()
  if not box.is_visible():d.locator('[data-expand="use-media"]').click()
  box.set_checked(on);d.locator('#save-permissions').click();d.locator('#accept-confirmation').click();expect(d.locator('#permission-result-title')).to_have_text('CAMBIOS CONFIRMADOS',timeout=30000)
 media(False)
 try:check('Support grant alone cannot create physician scope',get(api+'?mode=operators&doctor_id=op01_support')['status']==403)
 finally:media(True)
 check('Explicit Director restoration of original scope',get(api+'?mode=operators&doctor_id=op01_support')['status']==200)
 a.goto(origin+'/development/interfaces.php');check('Six development surfaces preserved',a.locator('#development-interface-index li').count()==6);check('Current review commands shown in index','advisor-operator-support01-review.py' in a.locator('body').text_content())
 a.goto(origin+'/internal/staff-workspace/index.php');expect(a.locator('#discovery-list')).to_have_attribute('aria-busy','false');a.locator('[data-doctor-id="op01_support"]').first.click();a.locator('#tab-operators').click();expect(a.locator('#operators-content')).to_contain_text('Lucía');a.locator('#refresh-operators').focus();a.keyboard.press('Enter');expect(a.locator('#operators-content')).to_contain_text('Lucía');check('Keyboard refresh preserves exact context',a.locator('#work-panel').get_attribute('data-doctor-id')=='op01_support')
 # Late responses may not repopulate a different selected context.
 a.route('**/api/internal/workspace/requests.php?mode=operators*',lambda route:route.fulfill(status=403,content_type='application/json',body='{"ok":false,"error":"access_denied"}'))
 a.locator('#refresh-operators').click();expect(a.locator('#operators-content')).to_be_empty();check('Denied response clears previous operator details',True);a.unroute('**/api/internal/workspace/requests.php?mode=operators*');b.close()
(o/'support-evidence/supplement.json').write_text(json.dumps(results,indent=2))
