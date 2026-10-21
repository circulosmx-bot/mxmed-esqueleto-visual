"""Real browser layout sampling, delayed-response races and canonical access regression."""
import pathlib,json,importlib.util
from urllib.parse import urlparse,parse_qs
from playwright.sync_api import sync_playwright,expect
ROOT=pathlib.Path(__file__).resolve().parents[1]
def module(name,path):
 s=importlib.util.spec_from_file_location(name,path);m=importlib.util.module_from_spec(s);s.loader.exec_module(m);return m

def run(r,check,cli):
 owned,origin,sql=[r[k] for k in ['owned','origin','sql']]
 native=module('n07',ROOT/'scripts/ase-advisor-workspace-review.py');helper=module('h07',ROOT/'scripts/ase-adm02-oper09-browser.py')
 record=json.loads((owned/'oper09-advisor.json').read_text());martha=json.loads((owned/'staff06-martha.json').read_text())
 active='[data-member="'+record['account']+'"]';pending='[data-case="'+martha['case']+'"]'
 results=[];errors=[];shots=r['OUT']/'screens'
 def login(p):
  native.login(p,origin,'qa_director@example.test',(owned/'fixture-password').read_text().strip(),'/internal/media-review/index.php?section=equipo')
  expect(p.locator('#directory-content')).to_have_attribute('aria-busy','false');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def directory(p):
  if p.evaluate('innerWidth')<640:p.locator('#show-directory').click()
 def case(p):
  directory(p);p.locator(pending).click();expect(p.locator('#detail-title')).to_contain_text('Martha');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def active_member(p):
  directory(p);p.locator(active).click();expect(p.locator('#detail-title')).to_contain_text('Irene');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
 def sample(p):
  p.evaluate('''()=>{window.team07={frames:[],shifts:[],running:true};const selectors=['.workspace-header','#team-directory','.category-filter','.team-top-controls','#advisor-search','#team-pagination','#directory-content'];if(innerWidth>=640)selectors.push('#advisor-detail');function tick(){if(!team07.running)return;team07.frames.push({rects:selectors.map(s=>{let r=document.querySelector(s).getBoundingClientRect();return [r.x,r.y,r.width,r.height]}),scroll:[scrollX,scrollY]});requestAnimationFrame(tick)};requestAnimationFrame(tick);if(PerformanceObserver.supportedEntryTypes.includes('layout-shift')){team07.observer=new PerformanceObserver(l=>{for(const e of l.getEntries())team07.shifts.push({value:e.value,recentInput:e.hadRecentInput,sources:e.sources.map(s=>({node:s.node?.id||s.node?.className||s.node?.nodeName,previous:s.previousRect.toJSON(),current:s.currentRect.toJSON()}))})});team07.observer.observe({type:'layout-shift'});}}''')
 def finish(p,label):
  data=p.evaluate('''()=>{team07.running=false;team07.observer?.disconnect();return {frames:team07.frames,shifts:team07.shifts}}''');frames=data.pop('frames');assert len(frames)>2
  max_delta=max(abs(frame['rects'][i][j]-frames[0]['rects'][i][j]) for frame in frames for i in range(len(frames[0]['rects'])) for j in range(4))
  scroll_delta=max(abs(frame['scroll'][j]-frames[0]['scroll'][j]) for frame in frames for j in range(2))
  data.update(label=label,frames=len(frames),max_control_delta_px=max_delta,max_window_scroll_delta_px=scroll_delta)
  results.append(data);(r['OUT']/'navigation.json').write_text(json.dumps({'measurements':results,'page_errors':errors},indent=2));check(label+' fixed navigation geometry during category loads',max_delta<=1 and scroll_delta<=1)
  if p.evaluate("PerformanceObserver.supportedEntryTypes.includes('layout-shift')"):
   check(label+' no disruptive layout shifts including recent user input',sum(e['value'] for e in data['shifts'])<.02)
 def exercise(p,label):
  p.on('pageerror',lambda e:errors.append(str(e)));p.on('dialog',lambda d:d.accept());login(p);directory(p)
  # Slow responses keep the loading state observable across actual animation frames.
  def slow(route):
   response=route.fetch();p.wait_for_timeout(140);route.fulfill(response=response)
  p.route('**/api/internal/team/read.php?**',slow);sample(p)
  for i in range(6):
   p.locator('#filter-masters' if i%2==0 else '#filter-advisors').click()
   expect(p.locator('#directory-content')).to_have_attribute('aria-busy','false');expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false')
   expect(p.locator('#staff-stepup')).not_to_be_visible()
  finish(p,label);p.unroute('**/api/internal/team/read.php?**',slow)
  if p.evaluate('innerWidth')>=640:
   sample(p)
   for _ in range(2):case(p);active_member(p)
   finish(p,label+' members')
  # Hold a Master list after it was read, then release it after a newer Advisor list.
  held=[]
  def late_list(route):
   q=parse_qs(urlparse(route.request.url).query)
   if q.get('category')==['MASTER_ADMIN'] and 'target' not in q:held.append((route,route.fetch()))
   else:route.continue_()
  p.route('**/api/internal/team/read.php?**',late_list);p.locator('#filter-masters').click();p.wait_for_timeout(200);assert held
  p.locator('#filter-advisors').click();expect(p.locator(active)).to_be_visible();expect(p.locator('#directory-content')).to_have_attribute('aria-busy','false')
  for route,response in held:route.fulfill(response=response)
  p.wait_for_timeout(250);expect(p.locator('#filter-advisors')).to_have_attribute('aria-pressed','true');expect(p.locator(active)).to_be_visible();p.unroute('**/api/internal/team/read.php?**',late_list)
  check(label+' late category response cannot replace current category',True)
  held=[]
  def late_member(route):
   if parse_qs(urlparse(route.request.url).query).get('target')==[record['account']]:held.append((route,route.fetch()))
   else:route.continue_()
  p.route('**/api/internal/team/read.php?**',late_member);p.locator(active).click();p.wait_for_timeout(200);assert held
  expect(p.locator('#detail-title')).to_have_text('Cargando integrante…');case(p)
  for route,response in held:route.fulfill(response=response)
  p.wait_for_timeout(250);expect(p.locator('#detail-title')).to_contain_text('Martha');p.unroute('**/api/internal/team/read.php?**',late_member)
  check(label+' late member response cannot overwrite selected pending case',p.locator('#directory-content [aria-pressed=true]').count()==1)
  held=[]
  def late_case(route):
   b=route.request.post_data_json
   if b.get('action')=='case' and b.get('input',{}).get('case_id')==martha['case']:held.append((route,route.fetch()))
   else:route.continue_()
  p.route('**/api/internal/staff-admission/workflow.php',late_case);directory(p);p.locator(pending).click();p.wait_for_timeout(200);assert held;active_member(p)
  for route,response in held:route.fulfill(response=response)
  p.wait_for_timeout(600);expect(p.locator('#detail-title')).to_contain_text('Irene');p.unroute('**/api/internal/staff-admission/workflow.php',late_case)
  check(label+' late case response cannot overwrite selected active member',p.locator('dialog[open]').count()==0)
  directory(p);p.screenshot(path=str(shots/(label.replace(' ','-')+'-directory.png')))
  case(p);p.locator('[data-review-action=READ]').click();expect(p.locator('#staff-stepup-title')).to_have_text('Consultar evidencia');expect(p.locator('#staff-stepup-account')).to_have_text('Cuenta de Director: qa_director@example.test');expect(p.locator('#staff-stepup-status')).to_contain_text('Paso 1 de 2');expect(p.locator('#staff-reason')).not_to_be_visible();expect(p.locator('#staff-code')).not_to_be_visible()
  p.locator('#staff-stepup .synthetic-review-help summary').click();p.locator('#staff-stepup .team-dialog-body').evaluate('(e)=>e.scrollTop=e.scrollHeight');expect(p.locator('#cancel-staff-stepup')).to_be_in_viewport();p.screenshot(path=str(shots/(label.replace(' ','-')+'-stepup.png')));p.keyboard.press('Escape');expect(p.locator('#staff-stepup')).not_to_be_visible()
  check(label+' action-specific stepup and modal footer remain accessible',p.evaluate('document.documentElement.scrollWidth<=innerWidth'))
 with sync_playwright() as pw:
  for engine,sizes in [('chromium',[(1440,900),(1366,768),(390,844)]),('webkit',[(1366,768),(390,844)])]:
   b=getattr(pw,engine).launch()
   for width,height in sizes:
    c=b.new_context(ignore_https_errors=True,viewport={'width':width,'height':height});p=c.new_page();exercise(p,engine+' '+str(width));c.close()
   b.close()
  b,c,p=native.native_browser(pw);exercise(p,'native Chromium')
  active_member(p);p.locator('[data-expand=use-media]').click();box=p.locator('#use-media [data-capability=media_review_read]');expect(box).to_be_enabled()
  for grant in [False,True]:
   box.set_checked(grant);p.locator('#save-permissions').click();expect(p.locator('#confirmation')).to_be_visible();expect(p.locator('#staff-stepup')).not_to_be_visible();p.locator('#accept-confirmation').click();expect(p.locator('#confirmation')).not_to_be_visible()
   expect(p.locator('[data-outcome=success]')).to_be_visible();expect(p.locator('#pending-permissions')).to_have_text('Sin cambios pendientes.');check('Canonical permission '+('grant' if grant else 'revocation')+' uses confirmation without reauthentication',bool(sql("SELECT 1 FROM internal_operator_grants WHERE account_id=%s AND capability='media_review_read' AND status='ACTIVE'",(record['account'],)))==grant)
   p.locator('#operational-matrix [data-expand=use-media]').click() if not p.locator('#use-media').is_visible() else None
   box=p.locator('#use-media [data-capability=media_review_read]')
  directory(p);p.locator('#filter-masters').click();expect(p.locator('#advisor-detail')).to_have_attribute('aria-busy','false');expect(p.locator('#designation')).to_be_disabled()
  check('Master appointment gate remains independent and cannot use staff-review authentication',p.locator('#designation').is_disabled())
  p.locator('#team-logout').click();expect(p).to_have_url(__import__('re').compile('/public/identity/acceso.php'));check('Canonical UI logout returns to Identity',True);login(p);check('Canonical Director login after logout succeeds',p.locator('#directory-heading').inner_text()=='STAFF');b.close()
 check('No JavaScript errors in navigation and race tests',not errors)
 (r['OUT']/'navigation.json').write_text(json.dumps({'result':'PASS','measurements':results,'page_errors':errors},indent=2))
