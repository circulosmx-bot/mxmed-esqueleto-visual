import json,pathlib,ssl,urllib.request,urllib.error,importlib.util,sys
from playwright.sync_api import sync_playwright
ROOT=pathlib.Path(__file__).resolve().parents[1]
spec=importlib.util.spec_from_file_location('hub02',ROOT/'scripts/interface-hub02.py');review=importlib.util.module_from_spec(spec);spec.loader.exec_module(review)
O,_,origin,_,_=review.hub.load(sys.argv[1]);OUT=ROOT/'docs/evidence/interface-hub02';OUT.mkdir(parents=True,exist_ok=True);hub=review.URL
checks={};ca=pathlib.Path.home()/'Library/Application Support/MXMED/local-identity-tls/ca.crt';sslcontext=ssl.create_default_context(cafile=str(ca))
for suffix in ['', '?review=safari','index.html','development/interfaces.php']:
 with urllib.request.urlopen(hub+suffix,context=sslcontext) as r:checks['HTTP200 '+suffix]=r.status==200
try:urllib.request.urlopen(urllib.request.Request(hub,data=b'launch'),context=sslcontext)
except urllib.error.HTTPError as e:checks['POST rejected']=e.code==501
with sync_playwright() as p:
 b=p.webkit.launch();c=b.new_context(ignore_https_errors=True,java_script_enabled=False);page=c.new_page();page.goto(hub);checks['six HTML entries without JS']=page.locator('li h2').count()==6
 page.get_by_role('link',name='Abrir panel médico',exact=True).click();checks['ordinary medical link']=page.url.startswith('https://localhost:8443/public/identity/acceso.php');checks['no popup']=len(c.pages)==1;c.close()
 contexts=[];role_browsers=[]
 for role,email,file,target in [('director','qa_director@example.test','fixture-password','/internal/media-review/index.php?section=equipo'),('advisor','full_grants_closure@example.test','applicant-password','/internal/staff-workspace/index.php')]:
  rb=p.webkit.launch();role_browsers.append(rb);c=rb.new_context(ignore_https_errors=True);contexts.append(c);page=c.new_page();page.goto(hub);page.get_by_role('link',name='Abrir '+('Director' if role=='director' else 'Asesor'),exact=True).click();page.wait_for_load_state('load');page.locator('#login-email').fill(email);page.locator('#login-password').fill((O/file).read_text().strip());page.locator('[data-submit]').click();page.wait_for_url(origin+target,wait_until='domcontentloaded');checks[role+' canonical login via hub']=True;print(role+' PASS',flush=True)
  checks[role+' no medical cookies']=not c.cookies('https://localhost:8443')
 a=[x['value'] for x in contexts[0].cookies(origin) if x['name']=='__Host-mxmed_session'];d=[x['value'] for x in contexts[1].cookies(origin) if x['name']=='__Host-mxmed_session'];checks['WebKit contexts distinct staff sessions']=bool(a and d and a!=d)
 for rb in role_browsers:rb.close()
 b.close()
 b=p.webkit.launch();c=b.new_context(ignore_https_errors=True,java_script_enabled=False);page=c.new_page();page.goto(hub);checks['browser restart hub available']=page.locator('li h2').count()==6
 page.set_viewport_size({'width':390,'height':844});checks['mobile no overflow']=page.evaluate('document.documentElement.scrollWidth<=innerWidth');page.screenshot(path='/tmp/hub02-mobile.png');b.close()
assert all(checks.values()),checks
(OUT/'checks.json').write_text(json.dumps(checks,indent=2)+'\n');print(json.dumps(checks,indent=2))
