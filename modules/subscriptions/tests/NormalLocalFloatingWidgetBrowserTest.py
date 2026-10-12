from playwright.sync_api import sync_playwright
from pathlib import Path
import subprocess,hashlib,base64,json
import argparse,tempfile,os,ssl,urllib.request
parser=argparse.ArgumentParser(description='Explicit live normal-local UI QA; ends in Real; never writes subscription or Identity rows.')
parser.add_argument('--live-normal-local',action='store_true',required=True)
parser.parse_args()
os.chdir(Path(__file__).resolve().parents[3])
out=Path(tempfile.mkdtemp(prefix='mxmed-devwidget04-'))
cert=Path.home()/'Library/Application Support/MXMED/local-identity-tls/localhost.crt'
pem=subprocess.check_output(['openssl','x509','-in',str(cert),'-pubkey','-noout']);der=subprocess.check_output(['openssl','pkey','-pubin','-outform','DER'],input=pem);pin=base64.b64encode(hashlib.sha256(der).digest()).decode()
old_app=subprocess.check_output(['git','show','a8e3087ff406230a27d21df8ee650fd45691eccb:assets/js/app.js'],text=True)
old_class=subprocess.check_output(['git','show','a8e3087ff406230a27d21df8ee650fd45691eccb:assets/js/review/classification-simulator.js'],text=True)
def checksum():
 return subprocess.check_output(['php','-r',"$p=new PDO('mysql:host=127.0.0.1;dbname=mxmed','root','');$p->exec('SET SESSION TRANSACTION READ ONLY');foreach(['profile_subscriptions','auth_account_memberships','auth_accounts','auth_account_credentials','patients_doctor_links'] as $t)echo json_encode($p->query('CHECKSUM TABLE '.$t.' EXTENDED')->fetch(PDO::FETCH_ASSOC)).PHP_EOL;"],text=True)
before=checksum();(out/'before-checksums.txt').write_text(before)
def reset_real():
 ctx=ssl.create_default_context(cafile=str(Path.home()/'Library/Application Support/MXMED/local-identity-tls/ca.crt'))
 url='https://localhost:8443/api/local-review/index.php'
 data=json.load(urllib.request.urlopen(urllib.request.Request(url,headers={'Origin':'https://localhost:8443'}),context=ctx))
 req=urllib.request.Request(url,data=b'{}',method='DELETE',headers={'Origin':'https://localhost:8443','X-CSRF-Token':data['csrf_token'],'Content-Type':'application/json'})
 with urllib.request.urlopen(req,context=ctx) as response: assert response.status==200
try:
 with sync_playwright() as p:
  browser=p.chromium.launch(args=['--ignore-certificate-errors-spki-list='+pin])
  historical=browser.new_context(viewport={'width':1440,'height':900});page=historical.new_page()
  def old_route(r):
   if r.request.method not in ['GET','HEAD']:r.abort()
   elif '/assets/js/app.js?' in r.request.url:r.fulfill(body=old_app,content_type='text/javascript')
   elif '/assets/js/review/classification-simulator.js?' in r.request.url:r.fulfill(body=old_class,content_type='text/javascript')
   elif '/floating-qa-widget-visibility.css?' in r.request.url:r.fulfill(body='',content_type='text/css')
   else:r.continue_()
  page.route('**/*',old_route);page.goto('https://localhost:8443/',wait_until='domcontentloaded')
  page.locator('#mxmed_qa_classification_trigger').wait_for(state='visible')
  page.locator('#mxmed_dev_role_switcher').screenshot(path=str(out/'historical-pre-hide.png'))
  print('HISTORICAL exact JS + classification replay screenshot PASS');historical.close()
  context=browser.new_context();page=context.new_page();fixture_requests=[]
  def safe_route(r):
   if 'session-fixture' in r.request.url:fixture_requests.append(r.request.url);r.abort()
   elif r.request.method not in ['GET','HEAD'] and '/api/local-review/index.php' not in r.request.url:r.abort()
   else:r.continue_()
  page.route('**/*',safe_route)
  for w,h in [(1440,900),(1366,768)]:
   page.set_viewport_size({'width':w,'height':h});page.goto('https://localhost:8443/',wait_until='domcontentloaded')
   panel=page.locator('#mxmed_dev_role_switcher');panel.wait_for(state='visible')
   page.locator('#mxmed_qa_classification_trigger').wait_for(state='visible')
   assert page.locator('#mxmed-local-review').count()==0
   assert page.evaluate('getComputedStyle(document.body).paddingRight')=='0px'
   assert page.locator('#mxmed_qa_real_entity_select').is_disabled()
   assert page.locator('#mxmed_qa_real_entity_select').input_value()=='1'
   assert page.locator('#mxmed_qa_plan_select option').evaluate_all('(els)=>els.map(e=>e.value)')==['real','standard','optimum','professional']
   for plan,expected in [('standard',False),('optimum',True),('professional',True),('real',False)]:
    with page.expect_response(lambda r:'/api/local-review/index.php' in r.url and r.request.method in ['POST','DELETE']) as response:
     page.locator('#mxmed_qa_plan_select').select_option(plan)
    assert response.value.status==200
    page.wait_for_function('(plan)=>document.querySelector("#mxmed_dev_role_switcher [data-scenario]").dataset.scenario===plan',arg=plan)
    data=page.evaluate("async()=> (await fetch('/api/local-review/index.php',{cache:'no-store'})).json()")
    assert all(data['current']['feature_access'][k]['available']==expected for k in ['patients','clinical_record','prescriptions'])
    print(w,plan,'canonical PASS')
   for role in ['operator_1','operator_2','doctor']:
    page.locator('#mxmed_dev_role_select').select_option(role)
    assert page.evaluate('mxmedGetDevRole().value')==role
   assert page.evaluate('mxmedReviewClassification.options().length')==134
   page.locator('#mxmed_qa_classification_trigger').click()
   page.locator('#mxmed_qa_classification_dialog [data-classification-value]').nth(1).click()
   assert page.evaluate('mxmedReviewClassification.current()!==null')
   page.locator('#mxmed_qa_classification_trigger').click();page.locator('[data-classification-value="real"]').click()
   assert page.evaluate('mxmedReviewClassification.current()') is None
   assert page.evaluate('document.documentElement.scrollWidth <= innerWidth')
   panel.screenshot(path=str(out/f'restored-{w}x{h}.png'))
   page.locator('#mxmed_qa_collapse').click()
   assert not page.locator('#mxmed_qa_plan_select').is_visible()
   assert panel.bounding_box()['height']<60
   panel.screenshot(path=str(out/f'collapsed-{w}x{h}.png'))
   page.locator('#mxmed_qa_collapse').click();assert page.locator('#mxmed_qa_plan_select').is_visible()
   print(w,'roles/classification/fixed target/collapse/no overflow PASS')
  page.goto('https://localhost:8443/?qa_tools=hide',wait_until='domcontentloaded');page.wait_for_timeout(1000);assert page.locator('#mxmed_dev_role_switcher').count()==0
  # A stale browser override must neither change plan nor activate fixture hydration.
  page.goto('https://localhost:8443/',wait_until='domcontentloaded')
  page.locator('#mxmed_qa_plan_select').wait_for(state='visible')
  page.evaluate("sessionStorage.setItem('mxmed.qa.plan', JSON.stringify({value:'professional',plan:'professional'}));sessionStorage.setItem('mxmed.qa.realEntity',JSON.stringify({doctor_id:'990105'}))")
  page.reload(wait_until='domcontentloaded');page.locator('#mxmed_qa_plan_select').wait_for(state='visible')
  assert page.locator('#mxmed_qa_plan_select').input_value()=='real'
  assert page.locator('#mxmed_qa_real_entity_select').input_value()=='1'
  page.goto('https://localhost:8443/public/identity/acceso.php',wait_until='domcontentloaded')
  assert page.locator('#mxmed_dev_role_switcher').count()==0
  page.route('**/api/local-review/index.php',lambda route:route.fulfill(status=403,json={'error':'local_review_forbidden'}))
  page.goto('https://localhost:8443/',wait_until='domcontentloaded');page.wait_for_timeout(1000)
  assert page.locator('#mxmed_dev_role_switcher').count()==0
  assert not fixture_requests,fixture_requests
  print('Persisted browser override ignored; public path and denied server absent PASS')
  context.close();browser.close()
 assert before==checksum();print('ALL DATABASE CHECKSUMS UNCHANGED PASS')
finally:
 reset_real()
print('Evidence: '+str(out))
